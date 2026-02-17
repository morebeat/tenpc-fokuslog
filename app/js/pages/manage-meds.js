(function (global) {
    const FokusLog = global.FokusLog || (global.FokusLog = {});
    const pages = FokusLog.pages || (FokusLog.pages = {});

    pages.manage_meds = {
        init: async ({ utils }) => {
            const medsList = document.getElementById('meds-list');
            const addMedForm = document.getElementById('add-med-form');
            const errorDiv = document.getElementById('meds-error');
            const escapeHtml = utils?.escapeHtml || ((value) => value);
            if (!medsList || !addMedForm) return;

            const loadMeds = async () => {
                try {
                    const data = await utils.apiCall('/api/medications');
                    renderMeds(data.medications);
                } catch (error) {
                    medsList.innerHTML = '<p>Fehler beim Laden der Medikamente.</p>';
                    utils.error('Fehler beim Laden:', error);
                }
            };

            const renderMeds = (meds) => {
                if (!meds || meds.length === 0) {
                    medsList.innerHTML = '<p>Keine Medikamente vorhanden.</p>';
                    return;
                }
                let html = '<table><thead><tr><th>Name</th><th>Dosis</th><th>Aktion</th></tr></thead><tbody>';
                meds.forEach(med => {
                    html += `
                        <tr>
                            <td>${escapeHtml(med.name)}</td>
                            <td>${escapeHtml(med.default_dose || '-')}</td>
                            <td>
                                <button class="btn btn-delete" data-id="${med.id}" style="padding: 5px 10px; font-size: 0.8rem;">Löschen</button>
                            </td>
                        </tr>
                    `;
                });
                html += '</tbody></table>';
                medsList.innerHTML = html;
            };

            addMedForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                if (errorDiv) {
                    errorDiv.textContent = '';
                    errorDiv.classList.add('hidden');
                }
                const data = Object.fromEntries(new FormData(addMedForm).entries());
                try {
                    await utils.apiCall('/api/medications', {
                        method: 'POST',
                        body: JSON.stringify(data)
                    });
                    addMedForm.reset();
                    utils.toast('Medikament hinzugefügt.', 'success');
                    loadMeds();
                } catch (error) {
                    const msg = (error.body && error.body.error) || error.message || 'Fehler beim Erstellen.';
                    if (errorDiv) {
                        errorDiv.textContent = msg;
                        errorDiv.classList.remove('hidden');
                    }
                }
            });

            medsList.addEventListener('click', async (e) => {
                if (e.target.classList.contains('btn-delete')) {
                    if (!confirm('Medikament wirklich löschen?')) return;
                    const id = e.target.dataset.id;
                    try {
                        await utils.apiCall(`/api/medications/${id}`, { method: 'DELETE' });
                        utils.toast('Medikament gelöscht.', 'success');
                        loadMeds();
                    } catch (error) {
                        utils.toast('Fehler beim Löschen.', 'error');
                    }
                }
            });

            loadMeds();
        }
    };
})(window);
