<?php
require_once __DIR__ . '/../../db.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$pdo = get_db();
$action = $_GET['action'] ?? '';
$poll_id = $_GET['poll_id'] ?? '';

switch ($action) {
    case 'create_poll':
        handle_create_poll($pdo);
        break;
    case 'get_poll':
        handle_get_poll($pdo, $poll_id);
        break;
    case 'add_book':
        handle_add_book($pdo, $poll_id);
        break;
    case 'submit_vote':
        handle_submit_vote($pdo, $poll_id);
        break;
    case 'get_results':
        handle_get_results($pdo, $poll_id);
        break;
    case 'remove_book':
        handle_remove_book($pdo, $poll_id);
        break;
    case 'fetch_title':
        handle_fetch_title();
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}

function handle_create_poll(PDO $pdo): void {
    $data = json_decode(file_get_contents('php://input'), true);
    $title = trim($data['title'] ?? '');
    if ($title === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Title is required']);
        return;
    }

    $id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );

    $stmt = $pdo->prepare('INSERT INTO bc_polls (id, title) VALUES (?, ?)');
    $stmt->execute([$id, $title]);

    echo json_encode(['id' => $id, 'title' => $title]);
}

function handle_get_poll(PDO $pdo, string $poll_id): void {
    $stmt = $pdo->prepare('SELECT id, title, created_at FROM bc_polls WHERE id = ?');
    $stmt->execute([$poll_id]);
    $poll = $stmt->fetch();

    if (!$poll) {
        http_response_code(404);
        echo json_encode(['error' => 'Poll not found']);
        return;
    }

    $stmt = $pdo->prepare('SELECT id, title, added_by, url FROM bc_books WHERE poll_id = ?');
    $stmt->execute([$poll_id]);
    $books = $stmt->fetchAll();

    echo json_encode(['poll' => $poll, 'books' => $books]);
}

function handle_add_book(PDO $pdo, string $poll_id): void {
    $data = json_decode(file_get_contents('php://input'), true);
    $title = trim($data['title'] ?? '');
    $added_by = trim($data['added_by'] ?? '');
    $url = trim($data['url'] ?? '');

    if ($title === '' || $added_by === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Title and added_by are required']);
        return;
    }

    // Verify poll exists
    $stmt = $pdo->prepare('SELECT id FROM bc_polls WHERE id = ?');
    $stmt->execute([$poll_id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Poll not found']);
        return;
    }

    $url = $url !== '' ? $url : null;

    $stmt = $pdo->prepare('INSERT INTO bc_books (poll_id, title, added_by, url) VALUES (?, ?, ?, ?)');
    $stmt->execute([$poll_id, $title, $added_by, $url]);

    echo json_encode(['id' => (int)$pdo->lastInsertId(), 'title' => $title, 'added_by' => $added_by, 'url' => $url]);
}

function handle_remove_book(PDO $pdo, string $poll_id): void {
    $data = json_decode(file_get_contents('php://input'), true);
    $book_id = (int)($data['book_id'] ?? 0);

    if ($book_id === 0) {
        http_response_code(400);
        echo json_encode(['error' => 'book_id is required']);
        return;
    }

    // Verify the book exists
    $stmt = $pdo->prepare('SELECT id FROM bc_books WHERE id = ? AND poll_id = ?');
    $stmt->execute([$book_id, $poll_id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Book not found']);
        return;
    }

    // Delete votes for this book first (FK constraint), then the book
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('DELETE FROM bc_votes WHERE book_id = ?');
    $stmt->execute([$book_id]);
    $stmt = $pdo->prepare('DELETE FROM bc_books WHERE id = ?');
    $stmt->execute([$book_id]);
    $pdo->commit();

    echo json_encode(['success' => true]);
}

function handle_submit_vote(PDO $pdo, string $poll_id): void {
    $data = json_decode(file_get_contents('php://input'), true);
    $voter_name = trim($data['voter_name'] ?? '');
    $ranking = $data['ranking'] ?? []; // Array of book IDs in order

    if ($voter_name === '' || empty($ranking)) {
        http_response_code(400);
        echo json_encode(['error' => 'voter_name and ranking are required']);
        return;
    }

    $pdo->beginTransaction();

    // Delete any existing votes for this voter in this poll
    $stmt = $pdo->prepare('DELETE FROM bc_votes WHERE poll_id = ? AND voter_name = ?');
    $stmt->execute([$poll_id, $voter_name]);

    // Insert new votes
    $stmt = $pdo->prepare('INSERT INTO bc_votes (poll_id, voter_name, book_id, `rank`) VALUES (?, ?, ?, ?)');
    foreach ($ranking as $rank => $book_id) {
        $stmt->execute([$poll_id, $voter_name, (int)$book_id, $rank + 1]);
    }

    $pdo->commit();

    echo json_encode(['success' => true, 'voter_name' => $voter_name]);
}

function handle_get_results(PDO $pdo, string $poll_id): void {
    // Get poll info
    $stmt = $pdo->prepare('SELECT id, title, created_at FROM bc_polls WHERE id = ?');
    $stmt->execute([$poll_id]);
    $poll = $stmt->fetch();

    if (!$poll) {
        http_response_code(404);
        echo json_encode(['error' => 'Poll not found']);
        return;
    }

    // Get all books for this poll
    $stmt = $pdo->prepare('SELECT id, title, added_by, url FROM bc_books WHERE poll_id = ?');
    $stmt->execute([$poll_id]);
    $books = $stmt->fetchAll();
    $book_count = count($books);

    $book_info = [];
    foreach ($books as $book) {
        $book_info[$book['id']] = [
            'id' => $book['id'],
            'title' => $book['title'],
            'added_by' => $book['added_by'],
            'url' => $book['url'],
        ];
    }

    // Build per-voter ballots ordered by rank
    $stmt = $pdo->prepare('SELECT voter_name, book_id, `rank` FROM bc_votes WHERE poll_id = ? ORDER BY voter_name, `rank`');
    $stmt->execute([$poll_id]);
    $vote_rows = $stmt->fetchAll();

    $ballots_by_voter = [];
    foreach ($vote_rows as $row) {
        $ballots_by_voter[$row['voter_name']][] = (int)$row['book_id'];
    }
    $ballots = array_values($ballots_by_voter);
    $voters = array_keys($ballots_by_voter);

    // Build voter breakdown: each voter's ranked list with book titles
    $voter_ballots = [];
    foreach ($ballots_by_voter as $name => $book_ids) {
        $ranked = [];
        foreach ($book_ids as $bid) {
            if (isset($book_info[$bid])) {
                $ranked[] = $book_info[$bid]['title'];
            }
        }
        $voter_ballots[] = ['name' => $name, 'ranking' => $ranked];
    }

    // Run RCV (Instant Runoff Voting)
    $eliminated = [];
    $rounds = [];
    $winner = null;
    $total_ballots = count($ballots);

    while ($winner === null && count($eliminated) < $book_count) {
        // Count first-choice votes among non-eliminated books
        $counts = [];
        foreach ($book_info as $id => $_) {
            if (!isset($eliminated[$id])) {
                $counts[$id] = 0;
            }
        }

        $exhausted = 0;
        foreach ($ballots as $ballot) {
            $found = false;
            foreach ($ballot as $book_id) {
                if (!isset($eliminated[$book_id])) {
                    $counts[$book_id]++;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $exhausted++;
            }
        }

        $active_ballots = $total_ballots - $exhausted;

        // Record this round
        $round_data = [];
        foreach ($counts as $id => $count) {
            $round_data[] = [
                'id' => $id,
                'title' => $book_info[$id]['title'],
                'votes' => $count,
            ];
        }
        usort($round_data, fn($a, $b) => $b['votes'] - $a['votes']);
        $rounds[] = [
            'candidates' => $round_data,
            'exhausted' => $exhausted,
        ];

        // Check for majority winner
        if ($active_ballots > 0 && $round_data[0]['votes'] > $active_ballots / 2) {
            $winner = $round_data[0]['id'];
            break;
        }

        // If only one candidate left, they win
        if (count($counts) <= 1) {
            if (count($counts) === 1) {
                $winner = array_key_first($counts);
            }
            break;
        }

        // Eliminate the one candidate with fewest votes
        // On tie for last, eliminate the one with the lowest book ID
        $min_votes = min($counts);
        $eliminate_id = null;
        foreach ($counts as $id => $count) {
            if ($count === $min_votes && ($eliminate_id === null || $id < $eliminate_id)) {
                $eliminate_id = $id;
            }
        }
        $eliminated[$eliminate_id] = true;
    }

    // Build final ranking: winner first, then by last round they survived
    $final_results = [];
    foreach ($book_info as $id => $info) {
        $last_round = 0;
        $last_votes = 0;
        foreach ($rounds as $ri => $round) {
            foreach ($round['candidates'] as $c) {
                if ($c['id'] === $id) {
                    $last_round = $ri;
                    $last_votes = $c['votes'];
                }
            }
        }
        $final_results[] = array_merge($info, [
            'last_round' => $last_round,
            'last_votes' => $last_votes,
            'is_winner' => ($id === $winner),
        ]);
    }
    usort($final_results, function ($a, $b) {
        if ($a['is_winner'] !== $b['is_winner']) return $b['is_winner'] - $a['is_winner'];
        if ($a['last_round'] !== $b['last_round']) return $b['last_round'] - $a['last_round'];
        return $b['last_votes'] - $a['last_votes'];
    });

    echo json_encode([
        'poll' => $poll,
        'results' => $final_results,
        'rounds' => $rounds,
        'voters' => $voters,
        'voter_ballots' => $voter_ballots,
        'book_count' => $book_count,
        'winner' => $winner,
    ]);
}

function handle_fetch_title(): void {
    $data = json_decode(file_get_contents('php://input'), true);
    $url = trim($data['url'] ?? '');

    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Valid URL is required']);
        return;
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'user_agent' => 'Mozilla/5.0 (compatible; BookclubVoting/1.0)',
            'follow_location' => true,
        ],
        'ssl' => [
            'verify_peer' => true,
        ],
    ]);

    $title = null;

    $html = @file_get_contents($url, false, $context);
    if ($html !== false) {
        // Try Open Graph title first (most reliable for book sites)
        if (preg_match('/<meta\s+(?:property|name)=["\']og:title["\']\s+content=["\']([^"\']+)["\']/i', $html, $matches)) {
            $title = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
        }
        // Fallback to <title> tag
        if ($title === null && preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $matches)) {
            $title = html_entity_decode(trim($matches[1]), ENT_QUOTES, 'UTF-8');
        }

        // Clean up common suffixes from book sites
        if ($title !== null) {
            $title = preg_replace('/\s*[\|–—-]\s*(Goodreads|Amazon|Barnes & Noble|Google Books).*$/i', '', $title);
            $title = trim($title);
        }
    }

    // Fallback: extract title from URL slug (e.g. /40604556-red-seas-under-red-skies)
    if ($title === null || $title === '') {
        $path = parse_url($url, PHP_URL_PATH);
        if ($path !== null) {
            // Get the last path segment
            $slug = basename($path);
            // Strip leading numeric ID (e.g. "40604556-red-seas-under-red-skies" -> "red-seas-under-red-skies")
            $slug = preg_replace('/^\d+[-.]/', '', $slug);
            // Strip file extensions
            $slug = preg_replace('/\.\w+$/', '', $slug);
            // Replace hyphens/underscores with spaces and title-case
            if ($slug !== '') {
                $title = ucwords(str_replace(['-', '_'], ' ', $slug));
            }
        }
    }

    echo json_encode(['title' => $title]);
}
