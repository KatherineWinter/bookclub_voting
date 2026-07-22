(function () {
    const bookList = document.getElementById('book-list');
    const submitBtn = document.getElementById('submit-vote');
    const voteStatus = document.getElementById('vote-status');
    const voterNameInput = document.getElementById('voter-name');
    let knownBookIds = new Set();

    // Initialize SortableJS
    Sortable.create(bookList, {
        animation: 150,
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        dragClass: 'sortable-drag'
    });

    function buildBookItem(book) {
        const li = document.createElement('li');
        li.dataset.bookId = book.id;

        let titleHtml;
        if (book.url) {
            titleHtml = '<a href="' + escapeAttr(book.url) + '" target="_blank" rel="noopener" class="book-link">' +
                escapeHtml(book.title) + '</a>';
        } else {
            titleHtml = escapeHtml(book.title);
        }

        li.innerHTML = '<span class="drag-handle">&#9776;</span> ' +
            '<span class="book-title">' + titleHtml + '</span>';
        return li;
    }

    // Fetch poll data and populate book list
    async function loadPoll() {
        const res = await fetch('api.php?action=get_poll&poll_id=' + POLL_ID);
        const data = await res.json();

        if (data.error) {
            document.getElementById('poll-title').textContent = 'Poll not found';
            return;
        }

        document.getElementById('poll-title').textContent = data.poll.title;

        // Add any new books (preserve existing order for books already in the list)
        const newBooks = data.books.filter(b => !knownBookIds.has(b.id));
        // Shuffle new books to prevent position bias
        for (let i = newBooks.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [newBooks[i], newBooks[j]] = [newBooks[j], newBooks[i]];
        }
        newBooks.forEach(book => {
            knownBookIds.add(book.id);
            bookList.appendChild(buildBookItem(book));
        });

        submitBtn.disabled = bookList.children.length === 0;
    }

    // Submit vote
    submitBtn.addEventListener('click', async () => {
        const name = voterNameInput.value.trim();
        if (!name) {
            voterNameInput.focus();
            return alert('Please enter your name');
        }

        const items = bookList.querySelectorAll('li');
        if (items.length === 0) return;

        const ranking = Array.from(items).map(li => parseInt(li.dataset.bookId));

        const res = await fetch('api.php?action=submit_vote&poll_id=' + POLL_ID, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ voter_name: name, ranking })
        });
        const data = await res.json();

        voteStatus.classList.remove('hidden');
        if (data.success) {
            voteStatus.textContent = 'Vote submitted! You can update it by submitting again.';
            voteStatus.className = 'status-message success';
        } else {
            voteStatus.textContent = 'Error: ' + (data.error || 'Unknown error');
            voteStatus.className = 'status-message error';
        }
    });

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function escapeAttr(text) {
        return text.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // Initial load
    loadPoll();
})();
