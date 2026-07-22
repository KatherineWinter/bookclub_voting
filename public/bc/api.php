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

    // Calculate Borda scores: rank 1 = N points, rank 2 = N-1, ..., rank N = 1
    $scores = [];
    foreach ($books as $book) {
        $scores[$book['id']] = [
            'id' => $book['id'],
            'title' => $book['title'],
            'added_by' => $book['added_by'],
            'url' => $book['url'],
            'score' => 0
        ];
    }

    $stmt = $pdo->prepare('SELECT book_id, `rank` FROM bc_votes WHERE poll_id = ?');
    $stmt->execute([$poll_id]);
    $votes = $stmt->fetchAll();

    foreach ($votes as $vote) {
        $book_id = $vote['book_id'];
        if (isset($scores[$book_id])) {
            $scores[$book_id]['score'] += ($book_count - $vote['rank'] + 1);
        }
    }

    // Sort by score descending
    usort($scores, fn($a, $b) => $b['score'] - $a['score']);

    // Get list of voters
    $stmt = $pdo->prepare('SELECT DISTINCT voter_name FROM bc_votes WHERE poll_id = ?');
    $stmt->execute([$poll_id]);
    $voters = array_column($stmt->fetchAll(), 'voter_name');

    echo json_encode([
        'poll' => $poll,
        'results' => array_values($scores),
        'voters' => $voters,
        'book_count' => $book_count
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
