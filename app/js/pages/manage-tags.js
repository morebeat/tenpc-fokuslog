(function (global) {
    const FokusLog = global.FokusLog || (global.FokusLog = {});
    const pages = FokusLog.pages || (FokusLog.pages = {});

    pages.manage_tags = {
        init: async ({ utils }) => {
            const tagsList = document.getElementById('tags-list');
            const addTagForm = document.getElementById('add-tag-form');
            const errorDiv = document.getElementById('tags-error');
            const escapeHtml = utils?.escapeHtml || ((value) => value);
            let currentTags = [];

            if (!tagsList || !addTagForm) return;

            const loadTags = async () => {
                try {
                    const data = await utils.apiCall('/api/tags');
                    const tags = data.tags || [];
                    tags.sort((a, b) => a.name.localeCompare(b.name, 'de', { sensitivity: 'base' }));
                    currentTags = tags;
                    renderTags(tags);
                } catch (error) {
                    tagsList.innerHTML = '<p>Fehler beim Laden der Tags.</p>';
                    utils.error('Fehler beim Laden:', error);
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
                if (errorDiv) errorDiv.textContent = '';
                const data = Object.fromEntries(new FormData(addTagForm).entries());

                const newName = (data.name || '').trim();
                if (currentTags.some(t => t.name.toLowerCase() === newName.toLowerCase())) {
                    if (errorDiv) errorDiv.textContent = 'Dieser Tag existiert bereits.';
                    return;
                }

                try {
                    await utils.apiCall('/api/tags', {
                        method: 'POST',
                        body: JSON.stringify(data)
                    });
                    addTagForm.reset();
                    utils.toast('Tag erfolgreich erstellt.', 'success');
                    loadTags();
                } catch (error) {
                    const msg = (error.body && error.body.error) || error.message || 'Fehler beim Erstellen.';
                    if (errorDiv) errorDiv.textContent = msg;
                }
            });

            tagsList.addEventListener('click', async (e) => {
                if (e.target.classList.contains('btn-delete')) {
                    if (!confirm('Tag wirklich löschen?')) return;
                    const id = e.target.dataset.id;
                    try {
                        await utils.apiCall(`/api/tags/${id}`, { method: 'DELETE' });
                        utils.toast('Tag gelöscht.', 'success');
                        loadTags();
                    } catch (error) {
                        utils.toast('Fehler beim Löschen.', 'error');
                    }
                }
            });

            loadTags();
        }
    };
})(window);
