(function (global) {
    const FokusLog = global.FokusLog || (global.FokusLog = {});
    const pages = FokusLog.pages || (FokusLog.pages = {});

    pages.account = {
        init: async () => {
            const form = document.getElementById('change-password-form');
            const msgContainer = document.getElementById('message-container');
            if (!form || !msgContainer) return;
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                msgContainer.textContent = '';
                msgContainer.className = 'hidden';
                const formData = new FormData(form);
                const data = Object.fromEntries(formData.entries());
                if (data.new_password !== data.confirm_password) {
                    msgContainer.textContent = 'Fehler: Die neuen Passwörter stimmen nicht überein.';
                    msgContainer.className = 'error-message';
                    return;
                }
                try {
                    const response = await fetch('/api/users/me/password', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });
                    const resData = await response.json();
                    if (response.ok) {
                        msgContainer.textContent = resData.message;
                        msgContainer.className = 'success-message';
                        form.reset();
                    } else {
                        msgContainer.textContent = 'Fehler: ' + (resData.error || 'Unbekannt');
                        msgContainer.className = 'error-message';
                    }
                } catch (error) {
                    msgContainer.textContent = 'Verbindung nicht möglich.';
                    msgContainer.className = 'error-message';
                }
            });
        }
    };
})(window);
