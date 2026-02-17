(function (global) {
    const FokusLog = global.FokusLog || (global.FokusLog = {});
    const pages = FokusLog.pages || (FokusLog.pages = {});

    pages.manage_users = {
        init: async ({ utils }) => {
            const usersList = document.getElementById('users-list');
            const editModal = document.getElementById('edit-user-modal');
            const editForm = document.getElementById('edit-user-form');
            const modalTitle = editModal ? editModal.querySelector('h2') : null;
            const editErrorMessage = document.getElementById('edit-users-error');
            const btnAdd = document.getElementById('btn-add-user');
            const escapeHtml = utils?.escapeHtml || ((value) => value);
            let isEditMode = false;

            if (!usersList) return;

            const loadUsers = async () => {
                try {
                    const data = await utils.apiCall('/api/users');
                    renderUsers(data.users);
                } catch (error) {
                    usersList.innerHTML = '<p>Fehler beim Laden der Benutzer.</p>';
                    utils.error('Fehler beim Laden:', error);
                }
            };

            const renderUsers = (users) => {
                let html = `
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Geschlecht</th>
                                <th>Aktion</th>
                            </tr>
                        </thead>
                        <tbody>`;
                if (users && users.length > 0) {
                    users.forEach(u => {
                        const genderMap = { male: 'Männlich', female: 'Weiblich', diverse: 'Divers' };
                        const genderText = genderMap[u.gender] || 'Keine Angabe';
                        html += `
                            <tr>
                                <td>${escapeHtml(u.username)}</td>
                                <td>${genderText}</td>
                                <td><button class="button button-secondary btn-edit" data-id="${u.id}">Bearbeiten</button></td>
                            </tr>`;
                    });
                } else {
                    html += '<tr><td colspan="3" style="text-align: center; padding: 1rem;">Keine Benutzer angelegt.</td></tr>';
                }
                html += '</tbody></table>';
                usersList.innerHTML = html;
            };

            if (btnAdd && editModal) {
                btnAdd.addEventListener('click', () => {
                    isEditMode = false;
                    if (modalTitle) modalTitle.textContent = 'Neuen Benutzer anlegen';
                    if (editForm) editForm.reset();
                    document.getElementById('edit-user-id').value = '';
                    document.getElementById('label-password').textContent = 'Passwort (erforderlich):';
                    document.getElementById('edit-password').placeholder = '';
                    document.getElementById('edit-password').required = true;
                    if (editErrorMessage) editErrorMessage.textContent = '';
                    editModal.style.display = 'block';
                });
            }

            if (usersList && editModal) {
                usersList.addEventListener('click', async (e) => {
                    if (e.target.classList.contains('btn-edit')) {
                        const userId = e.target.dataset.id;
                        isEditMode = true;
                        if (modalTitle) modalTitle.textContent = 'Benutzer bearbeiten';
                        if (editErrorMessage) editErrorMessage.textContent = '';
                        try {
                            const data = await utils.apiCall(`/api/users/${userId}`);
                            const user = data.user;
                                document.getElementById('edit-user-id').value = user.id;
                                document.getElementById('edit-username').value = user.username;
                                document.getElementById('edit-role').value = user.role;
                                document.getElementById('label-password').textContent = 'Neues Passwort (optional):';
                                const passwordInput = document.getElementById('edit-password');
                                passwordInput.placeholder = 'Leer lassen, um nicht zu ändern';
                                passwordInput.required = false;
                                passwordInput.value = '';
                                editModal.style.display = 'block';
                        } catch (error) {
                            utils.toast('Fehler beim Laden des Benutzers.', 'error');
                            utils.error('Fehler beim Laden:', error);
                        }
                    }
                });
            }

            if (editModal) {
                const closeBtn = editModal.querySelector('.close-button');
                if (closeBtn) closeBtn.onclick = () => editModal.style.display = 'none';
                window.addEventListener('click', (e) => {
                    if (e.target === editModal) editModal.style.display = 'none';
                });
            }

            if (editForm) {
                editForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const formData = new FormData(editForm);
                    const data = Object.fromEntries(formData.entries());
                    if (isEditMode && !data.password) delete data.password;
                    const url = isEditMode ? `/api/users/${data.id}` : '/api/users';
                    const method = isEditMode ? 'PUT' : 'POST';
                    try {
                        await utils.apiCall(url, {
                            method,
                            body: JSON.stringify(data)
                        });
                        editModal.style.display = 'none';
                        utils.toast('Benutzer erfolgreich gespeichert.', 'success');
                        loadUsers();
                    } catch (error) {
                        const msg = (error.body && error.body.error) || error.message || 'Fehler beim Speichern.';
                        if (editErrorMessage) editErrorMessage.textContent = msg;
                    }
                });
            }

            loadUsers();
        }
    };
})(window);
