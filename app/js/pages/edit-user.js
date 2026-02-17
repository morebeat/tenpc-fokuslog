(function (global) {
    const FokusLog = global.FokusLog || (global.FokusLog = {});
    const pages = FokusLog.pages || (FokusLog.pages = {});

    pages.edit_user = {
        init: async () => {
            const form = document.getElementById('edit-user-form');
            const pageTitle = document.getElementById('page-title');
            const userIdInput = document.getElementById('user_id');
            const passwordInput = document.getElementById('password');
            const passwordHint = document.getElementById('password-hint');
            const deleteBtn = document.getElementById('delete-user-btn');
            const resetPasswordBtn = document.getElementById('reset-password-btn');
            const msgContainer = document.getElementById('message-container');
            if (!form) return;

            const urlParams = new URLSearchParams(window.location.search);
            const userId = urlParams.get('id');
            const isEditMode = !!userId;

            const loadUserData = async () => {
                if (!isEditMode) {
                    if (pageTitle) pageTitle.textContent = 'Neuen Benutzer anlegen';
                    if (passwordInput) passwordInput.required = true;
                    if (resetPasswordBtn) resetPasswordBtn.style.display = 'none';
                    if (passwordHint) passwordHint.style.display = 'none';
                    return;
                }
                if (pageTitle) pageTitle.textContent = 'Benutzer bearbeiten';
                if (passwordInput) passwordInput.required = false;
                if (deleteBtn) deleteBtn.style.display = 'inline-block';
                if (resetPasswordBtn) resetPasswordBtn.style.display = 'inline-block';
                if (userIdInput) userIdInput.value = userId;
                try {
                    const data = await FokusLog.utils.apiCall(`/api/users/${userId}`);
                    const user = data.user;
                    document.getElementById('username').value = user.username;
                    document.getElementById('role').value = user.role;
                    document.getElementById('gender').value = user.gender || '';
                } catch (error) {
                    const msg = (error.body && error.body.error) || error.message || 'Fehler beim Laden.';
                    if (msgContainer) {
                        msgContainer.textContent = msg;
                        msgContainer.style.color = 'red';
                        msgContainer.style.display = 'block';
                    }
                    form.style.display = 'none';
                }
            };

            if (resetPasswordBtn) {
                resetPasswordBtn.addEventListener('click', () => {
                    // Use crypto.getRandomValues for secure password generation
                    const array = new Uint8Array(8);
                    crypto.getRandomValues(array);
                    const newPassword = Array.from(array, b => b.toString(36).padStart(2, '0')).join('').substring(0, 10);
                    if (passwordInput && passwordHint) {
                        passwordInput.value = newPassword;
                        passwordInput.type = 'text';
                        passwordHint.textContent = `Neues Passwort: ${newPassword} (bitte kopieren und speichern).`;
                        passwordHint.style.color = '#013c4a';
                        passwordHint.style.fontWeight = 'bold';
                    }
                });
            }

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                if (msgContainer) {
                    msgContainer.textContent = 'Speichere...';
                    msgContainer.style.color = 'inherit';
                    msgContainer.style.display = 'block';
                }

                const formData = new FormData(form);
                const data = Object.fromEntries(formData.entries());
                if (data.password === '') {
                    delete data.password;
                }
                const url = isEditMode ? `/api/users/${userId}` : '/api/users';
                const method = isEditMode ? 'PUT' : 'POST';
                try {
                    await FokusLog.utils.apiCall(url, {
                        method,
                        body: JSON.stringify(data)
                    });
                    
                    FokusLog.utils.toast('Benutzer erfolgreich gespeichert!', 'success');
                    setTimeout(() => window.location.href = 'manage_users.html', 1000);
                } catch (error) {
                    const msg = (error.body && error.body.error) || error.message || 'Verbindung nicht möglich.';
                    FokusLog.utils.toast('Fehler: ' + msg, 'error');
                    if (msgContainer) {
                        msgContainer.textContent = 'Fehler: ' + msg;
                        msgContainer.style.color = 'red';
                        msgContainer.style.display = 'block';
                    }
                }
            });

            if (deleteBtn) {
                deleteBtn.addEventListener('click', async () => {
                    if (!confirm('Sind Sie sicher, dass Sie diesen Benutzer löschen möchten? Diese Aktion kann nicht rückgängig gemacht werden.')) {
                        return;
                    }
                    try {
                        await FokusLog.utils.apiCall(`/api/users/${userId}`, { method: 'DELETE' });
                        FokusLog.utils.toast('Benutzer erfolgreich gelöscht.', 'success');
                        setTimeout(() => window.location.href = 'manage_users.html', 1000);
                    } catch (error) {
                        const msg = (error.body && error.body.error) || error.message || 'Fehler beim Löschen.';
                        FokusLog.utils.toast(msg, 'error');
                    }
                });
            }

            loadUserData();
        }
    };
})(window);
