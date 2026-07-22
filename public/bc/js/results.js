(function () {
    const resultsList = document.getElementById('results-list');
    const voterList = document.getElementById('voter-list');

    async function loadResults() {
        try {
        const res = await fetch('api.php?action=get_results&poll_id=' + POLL_ID);
        if (!res.ok) {
            document.querySelector('.refresh-indicator').textContent = 'Error: server returned ' + res.status;
            return;
        }
        const data = await res.json();

        if (data.error) {
            document.getElementById('poll-title').textContent = 'Poll not found';
            return;
        }

        document.getElementById('poll-title').textContent = data.poll.title + ' — Results';

        // Update "how it works" numbers
        const n = data.book_count;
        document.getElementById('book-count').textContent = n;
        document.getElementById('max-points').textContent = n;
        document.getElementById('second-points').textContent = n - 1;

        // Render results
        resultsList.innerHTML = '';
        data.results.forEach((book, index) => {
            const li = document.createElement('li');
            let titleHtml;
            if (book.url) {
                titleHtml = '<a href="' + escapeAttr(book.url) + '" target="_blank" rel="noopener" class="book-link">' +
                    escapeHtml(book.title) + '</a>';
            } else {
                titleHtml = escapeHtml(book.title);
            }
            li.innerHTML =
                '<span class="rank-number">#' + (index + 1) + '</span> ' +
                '<span class="book-title">' + titleHtml + '</span>' +
                '<span class="book-score">' + book.score + ' pts</span>';
            resultsList.appendChild(li);
        });

        // Render voters
        if (data.voters.length === 0) {
            voterList.textContent = 'No votes yet';
        } else {
            voterList.textContent = data.voters.join(', ');
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function escapeAttr(text) {
        return text.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

        } catch (e) {
            document.querySelector('.refresh-indicator').textContent = 'Error: ' + e.message;
        }
    }

    // Initial load + auto-refresh every 5 seconds
    loadResults();
    setInterval(loadResults, 5000);
})();