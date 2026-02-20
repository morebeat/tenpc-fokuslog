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
                    const response = await fetch(`/api/users/${userId}`);
                    if (!response.ok) {
                        if (response.status === 404) {
                            throw new Error('Benutzer nicht gefunden oder Zugriff verweigert.');
                        }
                        throw new Error('Etwas ist schiefgelaufen. Bitte versuche es erneut.');
                    }
                    const data = await response.json();
                    const user = data.user;
                    document.getElementById('username').value = user.username;
                    document.getElementById('role').value = user.role;
                    document.getElementById('gender').value = user.gender || '';
                } catch (error) {
                    msgContainer.textContent = error.message;
                    msgContainer.style.color = 'red';
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
                msgContainer.textContent = 'Speichere...';
                msgContainer.style.color = 'inherit';
                const formData = new FormData(form);
                const data = Object.fromEntries(formData.entries());
                if (data.password === '') {
                    delete data.password;
                }
                const url = isEditMode ? `/api/users/${userId}` : '/api/users';
                const method = isEditMode ? 'PUT' : 'POST';
                try {
                    const response = await fetch(url, {
                        method,
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });
                    const resData = await response.json();
                    if (response.ok) {
                        alert('Benutzer erfolgreich gespeichert!');
                        window.location.href = 'manage_users.html';
                    } else {
                        msgContainer.textContent = 'Fehler: ' + (resData.error || 'Unbekannt');
                        msgContainer.style.color = 'red';
                    }
                } catch (error) {
                    msgContainer.textContent = 'Verbindung nicht möglich.';
                    msgContainer.style.color = 'red';
                }
            });

            if (deleteBtn) {
                deleteBtn.addEventListener('click', async () => {
                    if (!confirm('Sind Sie sicher, dass Sie diesen Benutzer löschen möchten? Diese Aktion kann nicht rückgängig gemacht werden.')) {
                        return;
                    }
                    try {
                        const response = await fetch(`/api/users/${userId}`, { method: 'DELETE' });
                        if (response.ok) {
                            alert('Benutzer erfolgreich gelöscht.');
                            window.location.href = 'manage_users.html';
                        } else {
                            const resData = await response.json();
                            alert('Fehler beim Löschen: ' + (resData.error || 'Unbekannt'));
                        }
                    } catch (error) {
                        alert('Verbindung nicht möglich.');
                    }
                });
            }

            loadUserData();
        }
    };
})(window);
