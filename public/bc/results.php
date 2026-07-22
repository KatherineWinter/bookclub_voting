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

        <div class="panel">
            <h2>Voters</h2>
            <p id="voter-list">No votes yet</p>
        </div>

        <div class="panel how-it-works">
            <h2>How Ranking Works</h2>
            <p>We use <strong>Borda count</strong> scoring. Each voter drags books into their preferred order. With <strong id="book-count">N</strong> books in the poll:</p>
            <ul>
                <li>1st place = <strong id="max-points">N</strong> points</li>
                <li>2nd place = <strong id="second-points">N-1</strong> points</li>
                <li>Last place = <strong>1</strong> point</li>
            </ul>
            <p>The book with the highest total score across all voters wins!</p>
        </div>

        <a href="vote.php?poll_id=<?= $poll_id ?>" class="results-link">&larr; Back to Voting</a>
    </div>

    <script>
        const POLL_ID = <?= json_encode($poll_id) ?>;
    </script>
    <script src="js/results.js"></script>
</body>
</html>