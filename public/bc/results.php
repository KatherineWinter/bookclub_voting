<?php
$poll_id = htmlspecialchars($_GET['poll_id'] ?? '', ENT_QUOTES, 'UTF-8');
if (empty($poll_id)) {
    die('Missing poll_id');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results — Bookclub</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h1 id="poll-title">Loading...</h1>
        <p class="refresh-indicator">Results refresh every 5 seconds</p>

        <div class="panel">
            <h2>Rankings</h2>
            <ol id="results-list" class="results-list"></ol>
        </div>

        <div class="tab-bar">
            <button class="tab-btn active" data-tab="voters">Voters</button>
            <button class="tab-btn" data-tab="rounds">Rounds</button>
        </div>

        <div class="tab-content panel" id="tab-voters">
            <h2>Voters</h2>
            <p id="voter-list">No votes yet</p>

            <h2>How Everyone Voted</h2>
            <div id="voter-ballots"></div>
        </div>

        <div class="tab-content panel hidden" id="tab-rounds">
            <h2>Rounds</h2>
            <div id="rounds-detail"></div>

            <div class="how-it-works">
                <h2>How Ranking Works</h2>
                <p>We use <strong>Ranked Choice Voting</strong> (instant runoff). Each voter drags books into their preferred order.</p>
                <ul>
                    <li>First, everyone's <strong>#1 choice</strong> is counted</li>
                    <li>If a book has a <strong>majority</strong> (over 50%), it wins</li>
                    <li>Otherwise, the book with the <strong>fewest votes</strong> is eliminated</li>
                    <li>Voters who chose that book have their vote <strong>transferred</strong> to their next choice</li>
                    <li>This repeats until a book wins a majority</li>
                </ul>
            </div>
        </div>

        <a href="vote.php?poll_id=<?= $poll_id ?>" class="results-link">&larr; Back to Voting</a>
    </div>

    <script>
        const POLL_ID = <?= json_encode($poll_id) ?>;
    </script>
    <script src="js/results.js"></script>
</body>
</html>