(function (global) {
    const FokusLog = global.FokusLog || (global.FokusLog = {});
    const pages = FokusLog.pages || (FokusLog.pages = {});

    pages.manage_tags = {
        init: async ({ utils }) => {
            const tagsList = document.getElementById('tags-list');
            const addTagForm = document.getElementById('add-tag-form');
            const errorDiv = document.getElementById('tags-error');
            const escapeHtml = utils?.escapeHtml || ((value) => value);
            if (!tagsList || !addTagForm) return;

            const loadTags = async () => {
                try {
                    const response = await fetch('/api/tags');
                    if (response.ok) {
                        const data = await response.json();
                        renderTags(data.tags);
                    } else {
                        tagsList.innerHTML = '<p>Fehler beim Laden.</p>';
                    }
                } catch (error) {
                    tagsList.innerHTML = '<p>Verbindung nicht möglich.</p>';
                }
            };

            const renderTags = (tags) => {
                if (!tags || tags.length === 0) {
                    tagsList.innerHTML = '<p>Keine Tags vorhanden.</p>';
                    return;
                }
                let html = '<ul style="list-style: none; padding: 0;">';
                tags.forEach(tag => {
                    html += `
                        <li style="background: rgba(255,255,255,0.5); margin-bottom: 5px; padding: 10px; border-radius: 5px; display: flex; justify-content: space-between; align-items: center;">
                            <span>${escapeHtml(tag.name)}</span>
                            <button class="btn btn-delete" data-id="${tag.id}" style="padding: 5px 10px; font-size: 0.8rem;">Löschen</button>
                        </li>
                    `;
                });
                html += '</ul>';
                tagsList.innerHTML = html;
            };

            addTagForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const data = Object.fromEntries(new FormData(addTagForm).entries());
                try {
                    const response = await fetch('/api/tags', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });
                    if (response.ok) {
                        addTagForm.reset();
                        loadTags();
                    } else {
                        errorDiv.textContent = 'Fehler beim Erstellen.';
                    }
                } catch (error) {
                    errorDiv.textContent = 'Fehler beim Erstellen.';
                }
            });

            tagsList.addEventListener('click', async (e) => {
                if (e.target.classList.contains('btn-delete')) {
                    if (!confirm('Tag löschen?')) return;
                    const id = e.target.dataset.id;
                    await fetch(`/api/tags/${id}`, { method: 'DELETE' });
                    loadTags();
                }
            });

            loadTags();
        }
    };
})(window);
