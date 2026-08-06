(function () {
    const resultsList = document.getElementById('results-list');
    const voterList = document.getElementById('voter-list');
    const roundsDetail = document.getElementById('rounds-detail');

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

        // Render final rankings
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
            const badge = book.is_winner ? ' <span class="winner-badge">Winner</span>' : '';
            li.innerHTML =
                '<span class="rank-number">#' + (index + 1) + '</span> ' +
                '<span class="book-title">' + titleHtml + badge + '</span>';
            resultsList.appendChild(li);
        });

        // Render rounds
        roundsDetail.innerHTML = '';
        if (data.rounds && data.rounds.length > 0) {
            data.rounds.forEach((round, ri) => {
                const heading = document.createElement('h3');
                heading.textContent = 'Round ' + (ri + 1);
                roundsDetail.appendChild(heading);

                const table = document.createElement('table');
                table.className = 'round-table';
                let html = '<tr><th>Book</th><th>Votes</th></tr>';
                round.candidates.forEach(c => {
                    const isWinner = c.id === data.winner && ri === data.rounds.length - 1;
                    const cls = isWinner ? ' class="round-winner"' : '';
                    html += '<tr' + cls + '><td>' + escapeHtml(c.title) + '</td><td>' + c.votes + '</td></tr>';
                });
                if (round.exhausted > 0) {
                    html += '<tr class="exhausted"><td>Exhausted ballots</td><td>' + round.exhausted + '</td></tr>';
                }
                table.innerHTML = html;
                roundsDetail.appendChild(table);

                // Show elimination and vote transfers
                if (round.eliminated) {
                    const p = document.createElement('p');
                    p.className = 'eliminated-note';
                    p.textContent = 'Eliminated: ' + round.eliminated;
                    roundsDetail.appendChild(p);

                    if (round.eliminated_reason) {
                        const reason = document.createElement('p');
                        reason.className = 'eliminated-reason';
                        reason.textContent = round.eliminated_reason;
                        roundsDetail.appendChild(reason);
                    }

                    if (round.transfers && round.transfers.length > 0) {
                        const ul = document.createElement('ul');
                        ul.className = 'transfer-list';
                        round.transfers.forEach(t => {
                            const li = document.createElement('li');
                            li.innerHTML = escapeHtml(t.voter) + '\'s vote → ' +
                                (t.to ? escapeHtml(t.to) : '<em>exhausted</em>');
                            ul.appendChild(li);
                        });
                        roundsDetail.appendChild(ul);
                    }
                }
            });
        }

        // Render voters
        if (data.voters.length === 0) {
            voterList.textContent = 'No votes yet';
        } else {
            voterList.textContent = data.voters.join(', ');
        }

        // Render voter ballots
        const ballotsDiv = document.getElementById('voter-ballots');
        ballotsDiv.innerHTML = '';
        if (data.voter_ballots && data.voter_ballots.length > 0) {
            data.voter_ballots.forEach(vb => {
                const p = document.createElement('p');
                p.className = 'voter-ballot';
                p.innerHTML = '<strong>' + escapeHtml(vb.name) + ':</strong> ' +
                    vb.ranking.map((title, i) => (i + 1) + '. ' + escapeHtml(title)).join(', ');
                ballotsDiv.appendChild(p);
            });
        }

        } catch (e) {
            document.querySelector('.refresh-indicator').textContent = 'Error: ' + e.message;
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

    // Tab switching
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
            btn.classList.add('active');
            document.getElementById('tab-' + btn.dataset.tab).classList.remove('hidden');
        });
    });

    // Initial load + auto-refresh every 5 seconds
    loadResults();
    setInterval(loadResults, 5000);
})();
