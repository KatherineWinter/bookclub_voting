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
    <title>Vote — Bookclub</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
</head>
<body>
    <div class="container">
        <h1 id="poll-title">Loading...</h1>

        <div class="panel">
            <h2>Your Name</h2>
            <input type="text" id="voter-name" placeholder="Enter your name" required maxlength="100">
        </div>

        <div class="panel">
            <h2>Drag to Rank (Top = Favorite)</h2>
            <ul id="book-list" class="book-list"></ul>
            <button id="submit-vote" class="submit-btn" disabled>Submit Vote</button>
            <div id="vote-status" class="status-message hidden"></div>
        </div>

        <a href="results.php?poll_id=<?= $poll_id ?>" class="results-link">View Results &rarr;</a>
    </div>

    <script>
        const POLL_ID = <?= json_encode($poll_id) ?>;
    </script>
    <script src="js/vote.js"></script>
</body>
</html>
