(function (global) {
    const FokusLog = global.FokusLog || (global.FokusLog = {});
    const pages = FokusLog.pages || (FokusLog.pages = {});

    pages.login = {
        init: async ({ utils }) => {
            const loginForm = document.getElementById('login-form');
            if (loginForm) {
                loginForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const errorDiv = document.getElementById('login-error');
                    const formData = new FormData(loginForm);
                    const data = Object.fromEntries(formData.entries());
                    try {
                        const response = await fetch('/api/login', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(data)
                        });
                        if (response.ok) {
                            window.location.href = 'dashboard.html';
                        } else {
                            const result = await response.json();
                            if (errorDiv) errorDiv.textContent = result.error || 'Etwas ist schiefgelaufen. Bitte versuche es erneut.';
                        }
                    } catch (error) {
                        if (errorDiv) errorDiv.textContent = 'Verbindung nicht möglich.';
                    }
                });
            }
            utils?.addFooterLinks?.();
        }
    };

    pages.register = {
        init: async ({ utils }) => {
            const registerForm = document.getElementById('register-form');
            if (!registerForm) {
                utils?.addFooterLinks?.();
                return;
            }
            const radios = registerForm.querySelectorAll('input[name="account_type"]');
            const familyGroup = document.getElementById('family-name-group');
            const familyInput = document.getElementById('family_name');
            const usernameLabel = document.getElementById('username-label');
            const familynameLabel = document.getElementById('familyname-label');

            const typeHint = document.createElement('div');
            typeHint.style.fontSize = '0.85rem';
            typeHint.style.color = '#bbb';
            typeHint.style.marginBottom = '15px';
            typeHint.innerHTML = 'ℹ️ <strong>Einzelperson:</strong> Für dich alleine.<br>ℹ️ <strong>Familie:</strong> Du verwaltest Accounts für Kinder/Partner.';
            if (familyGroup) {
                familyGroup.parentNode.insertBefore(typeHint, familyGroup);
            }

            const nameHint = document.createElement('div');
            nameHint.style.fontSize = '0.8rem';
            nameHint.style.color = '#bbb';
            nameHint.style.padding = '4px';
            nameHint.textContent = '(Kein Klarname nötig, Pseudonym empfohlen)';
            if (familynameLabel) familynameLabel.insertAdjacentElement('afterend', nameHint);

            const privacyDiv = document.createElement('div');
            privacyDiv.style.margin = '15px 0';
            const privacyCheckbox = document.createElement('input');
            privacyCheckbox.type = 'checkbox';
            privacyCheckbox.id = 'privacy_accepted';
            privacyCheckbox.name = 'privacy_accepted';
            privacyCheckbox.required = true;
            privacyCheckbox.style.marginRight = '8px';
            privacyCheckbox.style.verticalAlign = 'middle';
            privacyCheckbox.style.cursor = 'pointer';
            const privacyLabel = document.createElement('label');
            privacyLabel.htmlFor = 'privacy_accepted';
            privacyLabel.style.fontSize = '0.9rem';
            privacyLabel.style.verticalAlign = 'middle';
            privacyLabel.style.cursor = 'pointer';
            privacyLabel.innerHTML = 'Ich akzeptiere die <a href="privacy.html" target="_blank">Datenschutzerklärung</a>.';
            privacyDiv.appendChild(privacyCheckbox);
            privacyDiv.appendChild(privacyLabel);
            const submitBtn = registerForm.querySelector('button[type="submit"]') || registerForm.querySelector('input[type="submit"]');
            if (submitBtn) {
                submitBtn.parentNode.insertBefore(privacyDiv, submitBtn);
            } else {
                registerForm.appendChild(privacyDiv);
            }

            radios.forEach(radio => {
                radio.addEventListener('change', (e) => {
                    if (e.target.value === 'individual') {
                        familyGroup.style.display = 'none';
                        familyInput.required = false;
                        usernameLabel.textContent = 'Benutzername:';
                    } else {
                        familyGroup.style.display = 'block';
                        familyInput.required = true;
                        usernameLabel.textContent = 'Benutzername (Elternteil):';
                    }
                });
            });

            registerForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const errorDiv = document.getElementById('register-error');
                const formData = new FormData(registerForm);
                const data = Object.fromEntries(formData.entries());
                try {
                    const response = await fetch('/api/register', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });
                    if (response.ok) {
                        alert('Registrierung erfolgreich! Sie können sich nun anmelden.');
                        window.location.href = 'login.html';
                    } else {
                        const result = await response.json();
                        if (errorDiv) errorDiv.textContent = result.error || 'Etwas ist schiefgelaufen. Bitte versuche es erneut.';
                    }
                } catch (error) {
                    if (errorDiv) errorDiv.textContent = 'Verbindung nicht möglich.';
                }
            });
            utils?.addFooterLinks?.();
        }
    };
})(window);
