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
                    const response = await fetch('/api/medications');
                    if (response.ok) {
                        const data = await response.json();
                        renderMeds(data.medications);
                    } else {
                        medsList.innerHTML = '<p>Fehler beim Laden.</p>';
                        utils.error('API Fehler beim Laden der Medikamente:', response.status);
                    }
                } catch (error) {
                    medsList.innerHTML = '<p>Verbindung nicht möglich.</p>';
                    utils.error('Netzwerkfehler beim Laden der Medikamente:', error);
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
                    const response = await fetch('/api/medications', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });
                    if (response.ok) {
                        addMedForm.reset();
                        loadMeds();
                    } else {
                        const res = await response.json();
                        if (errorDiv) {
                            errorDiv.textContent = res.error || 'Fehler beim Erstellen.';
                            errorDiv.classList.remove('hidden');
                        }
                    }
                } catch (error) {
                    if (errorDiv) {
                        errorDiv.textContent = 'Verbindung nicht möglich.';
                        errorDiv.classList.remove('hidden');
                    }
                }
            });

            medsList.addEventListener('click', async (e) => {
                if (e.target.classList.contains('btn-delete')) {
                    if (!confirm('Medikament wirklich löschen?')) return;
                    const id = e.target.dataset.id;
                    try {
                        const response = await fetch(`/api/medications/${id}`, { method: 'DELETE' });
                        if (response.ok) {
                            loadMeds();
                        } else {
                            alert('Fehler beim Löschen.');
                        }
                    } catch (error) {
                        alert('Verbindung fehlgeschlagen.');
                    }
                }
            });

            loadMeds();
        }
    };
})(window);
