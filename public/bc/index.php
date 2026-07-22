<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bookclub Voting</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h1>Bookclub Voting</h1>
        <div class="panel" id="create-panel">
            <h2>Create a New Poll</h2>
            <form id="create-form">
                <input type="text" id="poll-title" placeholder="Enter poll title (e.g. August Picks)" required maxlength="255">
                <button type="submit">Create Poll</button>
            </form>
        </div>

        <div class="panel hidden" id="books-panel">
            <h2>Add Books</h2>
            <div class="url-lookup">
                <input type="text" id="book-url" placeholder="Paste a Goodreads or book link (optional)">
                <button type="button" id="lookup-btn">Lookup</button>
            </div>
            <form id="add-book-form">
                <input type="text" id="new-book-title" placeholder="Book title" required maxlength="255">
                <button type="submit">Add</button>
            </form>
            <ul id="book-list" class="book-list"></ul>
        </div>

        <div class="panel hidden" id="link-panel">
            <h2>Share with your bookclub</h2>
            <p>Send this link so members can vote:</p>
            <div class="share-link">
                <input type="text" id="share-url" readonly>
                <button id="copy-btn">Copy</button>
            </div>
            <a id="vote-link" href="#">Go to Vote Page &rarr;</a>
            <br><br>
            <a id="results-link" href="#">Go to Results Page &rarr;</a>
        </div>
    </div>
    <script>
        let pollId = null;
        let pendingUrl = '';

        // Step 1: Create poll
        document.getElementById('create-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const title = document.getElementById('poll-title').value.trim();
            if (!title) return;

            const res = await fetch('api.php?action=create_poll', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({title})
            });
            const data = await res.json();
            pollId = data.id;

            const baseUrl = window.location.origin + window.location.pathname.replace('index.php', '');
            const voteUrl = baseUrl + 'vote.php?poll_id=' + pollId;
            const resultsUrl = baseUrl + 'results.php?poll_id=' + pollId;

            document.getElementById('share-url').value = voteUrl;
            document.getElementById('vote-link').href = voteUrl;
            document.getElementById('results-link').href = resultsUrl;

            document.getElementById('create-panel').classList.add('hidden');
            document.getElementById('books-panel').classList.remove('hidden');
            document.getElementById('link-panel').classList.remove('hidden');
        });

        // URL lookup
        document.getElementById('lookup-btn').addEventListener('click', async () => {
            const url = document.getElementById('book-url').value.trim();
            if (!url) return;

            const btn = document.getElementById('lookup-btn');
            btn.disabled = true;
            btn.textContent = 'Looking up...';

            try {
                const res = await fetch('api.php?action=fetch_title', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ url })
                });
                const data = await res.json();

                if (data.title) {
                    document.getElementById('new-book-title').value = data.title;
                    pendingUrl = url;
                } else {
                    alert('Could not extract title. Type the title manually — the link will still be saved.');
                    pendingUrl = url;
                }
            } catch (e) {
                alert('Lookup failed. Type the title manually — the link will still be saved.');
                pendingUrl = url;
            }

            btn.disabled = false;
            btn.textContent = 'Lookup';
        });

        // Add book
        document.getElementById('add-book-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const title = document.getElementById('new-book-title').value.trim();
            if (!title || !pollId) return;

            const url = pendingUrl || document.getElementById('book-url').value.trim() || '';

            const res = await fetch('api.php?action=add_book&poll_id=' + pollId, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ title, added_by: 'admin', url: url || undefined })
            });
            const book = await res.json();
            if (!book.error) {
                const li = document.createElement('li');
                let titleHtml;
                if (book.url) {
                    titleHtml = '<a href="' + escapeAttr(book.url) + '" target="_blank" rel="noopener" class="book-link">' +
                        escapeHtml(book.title) + '</a>';
                } else {
                    titleHtml = escapeHtml(book.title);
                }
                li.dataset.bookId = book.id;
                li.innerHTML = '<span class="book-title">' + titleHtml + '</span>' +
                    '<button class="remove-btn" title="Remove book">&times;</button>';
                document.getElementById('book-list').appendChild(li);

                document.getElementById('new-book-title').value = '';
                document.getElementById('book-url').value = '';
                pendingUrl = '';
            }
        });

        // Remove book
        document.getElementById('book-list').addEventListener('click', async (e) => {
            const btn = e.target.closest('.remove-btn');
            if (!btn) return;
            const li = btn.closest('li');
            const bookId = parseInt(li.dataset.bookId);

            const res = await fetch('api.php?action=remove_book&poll_id=' + pollId, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ book_id: bookId })
            });
            const data = await res.json();
            if (data.success) li.remove();
        });

        // Copy link
        document.getElementById('copy-btn').addEventListener('click', () => {
            const input = document.getElementById('share-url');
            input.select();
            navigator.clipboard.writeText(input.value);
            document.getElementById('copy-btn').textContent = 'Copied!';
            setTimeout(() => document.getElementById('copy-btn').textContent = 'Copy', 2000);
        });

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function escapeAttr(text) {
            return text.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }
    </script>
</body>
</html>
