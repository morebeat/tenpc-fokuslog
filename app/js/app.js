document.addEventListener('DOMContentLoaded', async () => {
    // Smiley UI Logic - KORRIGIERT
    const ratingGroups = document.querySelectorAll('.rating-group');

    ratingGroups.forEach(group => {
        const inputs = group.querySelectorAll('input[type="radio"]');
        const labels = group.querySelectorAll('label');

        labels.forEach((label, index) => {
            label.addEventListener('mouseenter', () => {
                // Entferne alle Highlights
                labels.forEach(l => l.classList.remove('highlight'));
                // Füge Highlight nur zum aktuellen Label hinzu, wenn es nicht checked ist
                if (!inputs[index].checked) {
                    label.classList.add('highlight');
                }
            });

            label.addEventListener('mouseleave', () => {
                // Entferne Highlight vom aktuellen Label
                label.classList.remove('highlight');
            });

            label.addEventListener('click', () => {
                // Entferne checked Status von allen inputs
                inputs.forEach(input => input.checked = false);
                // Setze checked Status für den angeklickten
                inputs[index].checked = true;
                // Entferne active Klasse von allen Labels
                labels.forEach(l => l.classList.remove('active'));
                // Füge active Klasse zum angeklickten Label hinzu
                label.classList.add('active');
                // Entferne alle Highlights
                labels.forEach(l => l.classList.remove('highlight'));
            });
        });

        // Initialisiere active Klasse für bereits ausgewählte Smileys
        inputs.forEach((input, index) => {
            if (input.checked) {
                labels[index].classList.add('active');
            }
        });
    });

    const page = document.body.dataset.page;
    const logoutBtn = document.getElementById('logout-btn');
    const welcomeMsg = document.getElementById('welcome');

    // Helper: XSS-Schutz (Global im Scope von DOMContentLoaded)
    const escapeHtml = (unsafe) => {
        if (typeof unsafe !== 'string' && typeof unsafe !== 'number') return unsafe;
        return String(unsafe)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    };

    // Helper: Footer Links einfügen
    const addFooterLinks = () => {
        if (document.getElementById('app-footer')) return;

        // Sticky Footer: Body als Flex-Container konfigurieren
        document.body.style.display = 'flex';
        document.body.style.flexDirection = 'column';
        document.body.style.minHeight = '100vh';
        document.body.style.margin = '0'; // Verhindert Scrollbalken bei 100vh
        const main = document.querySelector('main');
        if (main) main.style.flex = '1';

        const footer = document.createElement('footer');
        footer.id = 'app-footer';
        
        // Design-Verbesserungen: Hintergrund, Trennlinie, Abstände
        footer.style.marginTop = '40px';
        footer.style.padding = '30px 0';
        footer.style.backgroundColor = '#f8f9fa';
        footer.style.borderTop = '1px solid #e9ecef';
        footer.style.textAlign = 'center';
        footer.style.color = '#6c757d';
        footer.style.fontSize = '0.9rem';

        const linkStyle = 'color: #495057; text-decoration: none; margin: 0 10px; font-weight: 500;';

        // Pfad-Korrektur für Unterseiten (z.B. help/index.html)
        const isHelpPage = window.location.pathname.includes('/help/');
        const basePath = isHelpPage ? '../' : '';

        footer.innerHTML = `
            <div style="max-width: 960px; margin: 0 auto; padding: 0 15px;">
                <p style="margin-bottom: 10px;">
                    <a href="${basePath}impressum.html" style="${linkStyle}">Impressum</a> &bull;
                    <a href="${basePath}privacy.html" style="${linkStyle}">Datenschutz</a> &bull;
                    <a href="${basePath}help/index.html" style="${linkStyle}">Hilfe</a>
                </p>
                <p style="margin: 0; font-size: 0.8rem; opacity: 0.8;">&copy; ${new Date().getFullYear()} FokusLog</p>
            </div>`;
        document.body.appendChild(footer);
    };

    // 1. Login-Logik
    if (page === 'login') {
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
        addFooterLinks();
        return;
    }

    // 1b. Registrierungs-Logik (NEU)
    if (page === 'register') {
        const registerForm = document.getElementById('register-form');
        if (registerForm) {
            // UI-Logik für Account-Typ
            const radios = registerForm.querySelectorAll('input[name="account_type"]');
            const familyGroup = document.getElementById('family-name-group');
            const familyInput = document.getElementById('family_name');
            const usernameLabel = document.getElementById('username-label');
            const fanilynameLabel = document.getElementById('familyname-label');

            // HINWEISE EINFÜGEN (Dynamisch)
            // 1. Erklärung zu Einzelperson vs. Familie
            const typeHint = document.createElement('div');
            typeHint.style.fontSize = '0.85rem';
            typeHint.style.color = '#bbb';
            typeHint.style.marginBottom = '15px';
            typeHint.innerHTML = 'ℹ️ <strong>Einzelperson:</strong> Für dich alleine.<br>ℹ️ <strong>Familie:</strong> Du verwaltest Accounts für Kinder/Partner.';
            if (familyGroup) {
                familyGroup.parentNode.insertBefore(typeHint, familyGroup);
            }

            // 2. Hinweis zum Pseudonym
            const nameHint = document.createElement('div');
            nameHint.style.fontSize = '0.8rem';
            nameHint.style.color = '#bbb';
            nameHint.style.padding = '4px';
            nameHint.textContent = '(Kein Klarname nötig, Pseudonym empfohlen)';
            if (fanilynameLabel) fanilynameLabel.insertAdjacentElement('afterend', nameHint);

            // 3. Datenschutzerklärung (Dynamisch)
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
        }
    }

    // 2. Logout-Logik
    if (logoutBtn) {
        logoutBtn.addEventListener('click', async () => {
            try {
                await fetch('/api/logout', { method: 'POST' });
                window.location.href = 'login.html';
            } catch (error) {
                console.error('Logout fehlgeschlagen', error);
            }
        });
    }

    // 3. Authentifizierungs-Check und UI-Anpassung
    // Seiten, die öffentlich zugänglich sind (kein Redirect bei fehlendem Login)
    const publicPages = ['login', 'register', 'privacy', 'help'];

    try {
        const response = await fetch('/api/me');

        if (response.status === 401) {
            // Wenn nicht eingeloggt und auf einer geschützten Seite -> Redirect
            if (!publicPages.includes(page)) {
                window.location.href = 'login.html';
            } else {
                addFooterLinks();
            }
            return;
        }

        if (response.ok) {
            const user = await response.json();

            // Begrüßung im Dashboard
            if (welcomeMsg) {
                welcomeMsg.textContent = `Hallo, ${user.username}!`;
            }
            
            if (user.role === 'child') {
                displayGamificationStats(user);
            }

            // Erste Schritte Karte anzeigen (wenn keine Punkte vorhanden sind)
            if (page === 'dashboard' && (!user.points || user.points === 0)) {
                const firstStepsCard = displayFirstStepsCard();
                updateFirstStepsCardState(firstStepsCard);
            }

            // Verwaltungs-Buttons ausblenden, wenn Benutzer keine Admin-Rechte hat
            // oder eine Einzelperson ist (Parent ohne weitere Mitglieder).
            const isAdmin = user.role === 'parent' || user.role === 'adult';
            const isIndividual = isAdmin && user.family_member_count <= 1;

            if (!isAdmin || isIndividual) {
                const adminSection = document.getElementById('admin-section');
                if (adminSection) adminSection.style.display = 'none';
            }
        }
    } catch (error) {
        console.error('Fehler beim Auth-Check:', error);
    }

    // 4. Entry-Page Logik: Vorhandene Einträge laden
    if (page === 'entry') {
        const dateInput = document.getElementById('date');
        const timeInput = document.getElementById('time');
        const medSelect = document.getElementById('medication_id');
        const form = document.getElementById('entry-form');
        const tagsContainer = document.getElementById('tags-selection-container');
        const msgContainer = document.getElementById('message-container');
        let entryExists = false; // Status für Überschreib-Check
        let currentEntryId = null; // ID des geladenen Eintrags
        const ratingHintSections = document.querySelectorAll('.rating-section[data-scale]');

        const hideScaleHint = (section) => {
            if (!section) return;
            const hint = section.querySelector('.help-hint');
            if (hint) {
                hint.classList.add('hidden');
                hint.textContent = '';
            }
        };

        const showScaleHint = (section, message) => {
            if (!section) return;
            const hint = section.querySelector('.help-hint');
            if (hint) {
                hint.innerHTML = message;
                hint.classList.remove('hidden');
            }
        };

        const applyScaleHint = (input) => {
            if (!input) return;
            const section = input.closest('.rating-section[data-scale]');
            if (!section) return;
            const value = parseInt(input.value, 10);
            if (value === 1 || value === 5) {
                const label = section.dataset.helpLabel || 'diesem Bereich';
                const helpUrl = section.dataset.helpUrl || 'help/index.html';
                const intensity = value === 1 ? 'sehr niedrigen' : 'sehr hohen';
                const message = `Hinweis: Bei ${intensity} Werten in ${label} findest du Ideen im <a href="${helpUrl}" target="_blank" rel="noopener">Hilfe-Bereich</a>.`;
                showScaleHint(section, message);
            } else {
                hideScaleHint(section);
            }
        };

        ratingHintSections.forEach(section => {
            const radios = section.querySelectorAll('input[type="radio"]');
            radios.forEach(radio => {
                radio.addEventListener('change', () => applyScaleHint(radio));
                if (radio.checked) {
                    applyScaleHint(radio);
                }
            });
        });

        // URL-Parameter auslesen (für Bearbeitung)
        const urlParams = new URLSearchParams(window.location.search);
        const paramDate = urlParams.get('date');
        const paramTime = urlParams.get('time');
        const paramUserId = urlParams.get('user_id');

        if (paramDate) {
            dateInput.value = paramDate;
        } else if (dateInput && !dateInput.value) {
            // Standard-Datum auf heute setzen
            dateInput.value = new Date().toISOString().split('T')[0];
        }

        if (paramTime) {
            timeInput.value = paramTime;
        }

        let medicationMap = {};

        // Medikamente laden und Dropdown befüllen
        const loadMedications = async () => {
            try {
                const response = await fetch('/api/medications');
                if (response.ok) {
                    const data = await response.json();
                    medicationMap = {}; // Reset Map
                    // Bestehende Optionen (außer den ersten beiden: Bitte wählen + Kein Medikament) entfernen
                    while (medSelect.options.length > 2) {
                        medSelect.remove(2);
                    }
                    data.medications.forEach(med => {
                        medicationMap[med.id] = med.default_dose;
                        const option = document.createElement('option');
                        option.value = med.id;
                        // Format: Name (Dosis)
                        option.textContent = `${med.name} ${med.default_dose ? '(' + med.default_dose + ')' : ''}`;
                        medSelect.appendChild(option);
                    });
                }
            } catch (e) {
                console.error('Fehler beim Laden der Medikamente', e);
            }
        };

        const loadWeight = async () => {
            const weightInput = document.getElementById('weight');
            if (!weightInput) return;

            try {
                const response = await fetch('/api/me/latest-weight');
                if (response.ok) {
                    const data = await response.json();
                    if (data.weight !== null) {
                        weightInput.value = data.weight;
                    }
                }
            } catch (error) {
                console.error('Fehler beim Laden des Gewichts:', error);
            }
        };

        // Tags laden und Checkboxen rendern
        const loadTagsForEntry = async () => {
            if (!tagsContainer) return;
            try {
                const response = await fetch('/api/tags');
                if (response.ok) {
                    const data = await response.json();
                    if (data.tags && data.tags.length > 0) {
                        tagsContainer.innerHTML = '';
                        data.tags.forEach(tag => {
                            const wrapper = document.createElement('div');
                            wrapper.style.display = 'inline-block';
                            
                            const checkbox = document.createElement('input');
                            checkbox.type = 'checkbox';
                            checkbox.id = `tag-${tag.id}`;
                            checkbox.name = 'tags[]';
                            checkbox.value = tag.id;
                            
                            const label = document.createElement('label');
                            label.htmlFor = `tag-${tag.id}`;
                            label.textContent = tag.name;
                            label.style.fontWeight = 'normal';
                            label.style.marginLeft = '5px';
                            label.style.marginRight = '10px';
                            label.style.fontSize = '0.9rem';

                            wrapper.appendChild(checkbox);
                            wrapper.appendChild(label);
                            tagsContainer.appendChild(wrapper);
                        });
                    }
                }
            } catch (e) {
                console.error('Fehler beim Laden der Tags', e);
            }
        };

        const loadLastEntryDefaults = async () => {
            try {
                let url = '/api/entries?limit=1';
                if (paramUserId) {
                    url += `&user_id=${paramUserId}`;
                }
                const response = await fetch(url);
                if (response.ok) {
                    const data = await response.json();
                    if (data.entries && data.entries.length > 0) {
                        const lastEntry = data.entries[0];
                        
                        // Medikament setzen
                        if (lastEntry.medication_id) {
                            medSelect.value = lastEntry.medication_id;
                        } else if (lastEntry.medication_id === null) {
                            medSelect.value = "0"; // Kein Medikament
                        }

                        // Gewicht setzen
                        const weightInput = document.getElementById('weight');
                        if (weightInput && lastEntry.weight) {
                            weightInput.value = lastEntry.weight;
                        }
                    }
                }
            } catch (e) {
                console.error('Fehler beim Laden der Standardwerte', e);
            }
        };

        const loadEntryIfExists = async () => {
            const dateVal = dateInput.value;
            const timeVal = timeInput.value;

            if (!dateVal || !timeVal) return;

            try {
                // Abfrage mit spezifischem Time-Slot
                let url = `/api/entries?date_from=${dateVal}&date_to=${dateVal}&time=${timeVal}`;
                if (paramUserId) {
                    url += `&user_id=${paramUserId}`;
                }
                const res = await fetch(url);
                if (res.ok) {
                    const data = await res.json();
                    if (data.entries && data.entries.length > 0) {
                        const entry = data.entries[0];
                        console.log('Eintrag gefunden, lade Daten:', entry);
                        entryExists = true;
                        currentEntryId = entry.id;

                        // UI-Update: Zeige an, dass wir bearbeiten
                        const header = document.querySelector('header');
                        if (header) {
                            header.textContent = entry.username ? `Eintrag bearbeiten: ${entry.username}` : 'Eintrag bearbeiten';
                        }
                        const submitBtn = form.querySelector('input[type="submit"]');
                        if (submitBtn) submitBtn.value = 'Änderungen speichern';

                        // Löschen-Button anzeigen
                        showDeleteButton(true);

                        // Formularfelder befüllen
                        // Text/Select/Number Inputs
                        for (const key in entry) {
                            const input = form.querySelector(`[name="${key}"]`);
                            if (input && (input.type === 'text' || input.type === 'number' || input.tagName === 'SELECT' || input.tagName === 'TEXTAREA')) {
                                input.value = entry[key] || '';
                            }
                        }
                        
                        // Spezielle Behandlung für medication_id=null (Kein Medikament)
                        if (entry.medication_id === null) {
                            medSelect.value = "0";
                        }

                        // Radio Buttons (Skalen 1-5)
                        // Erwartet Namen wie "mood", "sleep" etc.
                        ['sleep', 'hyperactivity', 'mood', 'irritability', 'appetite', 'focus'].forEach(metric => {
                            if (entry[metric] !== null) {
                                const radio = form.querySelector(`input[name="${metric}"][value="${entry[metric]}"]`);
                                if (radio) {
                                    radio.checked = true;
                                    // Füge active Klasse zum entsprechenden Label hinzu
                                    const label = radio.nextElementSibling;
                                    if (label && label.tagName === 'LABEL') {
                                        // Entferne active von allen Labels in dieser Gruppe
                                        const group = radio.closest('.rating-group');
                                        if (group) {
                                            group.querySelectorAll('label').forEach(l => l.classList.remove('active'));
                                        }
                                        label.classList.add('active');
                                    }
                                    applyScaleHint(radio);
                                }
                            } else {
                                // Reset radio buttons if null
                                const radios = form.querySelectorAll(`input[name="${metric}"]`);
                                radios.forEach(r => {
                                    r.checked = false;
                                    const label = r.nextElementSibling;
                                    if (label && label.tagName === 'LABEL') {
                                        label.classList.remove('active');
                                    }
                                });
                                const section = form.querySelector(`.rating-section[data-scale="${metric}"]`);
                                hideScaleHint(section);
                            }
                        });

                        // Tags setzen
                        if (entry.tag_ids) {
                            const tagIds = entry.tag_ids.split(',').map(id => id.trim());
                            tagIds.forEach(id => {
                                const cb = document.getElementById(`tag-${id}`);
                                if (cb) cb.checked = true;
                            });
                        }
                    } else {
                        // Kein Eintrag gefunden -> Formular (teilweise) zurücksetzen?
                        // Optional: Hier könnte man Felder leeren, wenn man sicherstellen will, 
                        // dass man nicht versehentlich einen alten Eintrag überschreibt.
                        entryExists = false;
                        currentEntryId = null;
                        showDeleteButton(false);
                        
                        // Reset Form fields (except date/time)
                        // Wir wollen Gewicht und Medikation vom letzten Eintrag, Rest leer.
                        
                        // 1. Reset Textareas and Selects (except date, time)
                        const inputs = form.querySelectorAll('input:not([type="submit"]):not([type="hidden"]):not([type="date"]), select, textarea');
                        inputs.forEach(input => {
                            if (input.id !== 'date' && input.id !== 'time') {
                                input.value = '';
                            }
                        });
                        
                        // 2. Reset Radios
                        const radios = form.querySelectorAll('input[type="radio"]');
                        radios.forEach(r => {
                            r.checked = false;
                            if (r.nextElementSibling) {
                                r.nextElementSibling.classList.remove('active');
                            }
                        });

                        ratingHintSections.forEach(section => hideScaleHint(section));

                        // 3. Reset Checkboxes
                        const checkboxes = form.querySelectorAll('input[type="checkbox"]');
                        checkboxes.forEach(cb => cb.checked = false);

                        // 4. Load Defaults from last entry
                        await loadLastEntryDefaults();
                    }
                }
            } catch (err) {
                console.error('Fehler beim Laden des Eintrags:', err);
            }
        };

        // Helper: Löschen-Button ein/ausblenden
        const showDeleteButton = (show) => {
            let deleteBtn = document.getElementById('btn-delete-entry');
            if (show) {
                if (!deleteBtn) {
                    deleteBtn = document.createElement('button');
                    deleteBtn.id = 'btn-delete-entry';
                    deleteBtn.type = 'button';
                    deleteBtn.textContent = 'Eintrag löschen';
                    deleteBtn.className = 'button'; // Nutzt existierende Klasse
                    deleteBtn.style.backgroundColor = '#dc3545'; // Rot
                    deleteBtn.style.marginLeft = '10px';
                    
                    deleteBtn.addEventListener('click', async () => {
                        if (confirm('Möchten Sie diesen Eintrag wirklich löschen?')) {
                            try {
                                const res = await fetch(`/api/entries/${currentEntryId}`, { method: 'DELETE' });
                                if (res.ok) {
                                    alert('Eintrag gelöscht.');
                                    window.location.reload();
                                } else {
                                    alert('Fehler beim Löschen.');
                                }
                            } catch (e) {
                                alert('Verbindung fehlgeschlagen.');
                            }
                        }
                    });

                    const submitBtn = form.querySelector('input[type="submit"]');
                    if (submitBtn) submitBtn.parentNode.insertBefore(deleteBtn, submitBtn.nextSibling);
                }
                deleteBtn.style.display = 'inline-block';
            } else {
                if (deleteBtn) deleteBtn.style.display = 'none';
            }
        };

        if (dateInput && timeInput) {
            dateInput.addEventListener('change', loadEntryIfExists);
            timeInput.addEventListener('change', loadEntryIfExists);
        }

        // Formular absenden
        if (form) {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();

                // Überschreib-Warnung
                if (entryExists) {
                    if (!confirm("Für diesen Zeitraum existiert bereits ein Eintrag. Möchten Sie ihn überschreiben?")) {
                        return;
                    }
                }

                msgContainer.textContent = 'Speichere...';
                msgContainer.style.color = 'inherit';

                const formData = new FormData(form);
                const data = Object.fromEntries(formData.entries());

                // Tags manuell sammeln, da FormData bei Checkboxen mit gleichem Namen tricky sein kann
                // oder wir nutzen getAll wenn verfügbar, aber hier einfacher:
                const selectedTags = [];
                form.querySelectorAll('input[name="tags[]"]:checked').forEach(cb => {
                    selectedTags.push(cb.value);
                });
                data.tags = selectedTags;

                // Target User ID hinzufügen, falls vorhanden (Bearbeitung durch Parent)
                if (paramUserId) {
                    data.target_user_id = paramUserId;
                }

                // Dosis aus der Medication-Map hinzufügen, da das Feld im UI fehlt
                if (data.medication_id && medicationMap[data.medication_id]) {
                    data.dose = medicationMap[data.medication_id];
                }

                try {
                    const response = await fetch('/api/entries', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });

                    if (response.ok) {
                        const resData = await response.json();
                        msgContainer.textContent = 'Eintrag erfolgreich gespeichert!';
                        
                        if (resData.gamification && resData.gamification.points_earned > 0) {
                            msgContainer.textContent += ` (+${resData.gamification.points_earned} Punkte!)`;
                            
                            let alertMsg = `Super! Du hast ${resData.gamification.points_earned} Punkte erhalten.\nAktueller Streak: ${resData.gamification.streak} Tage.`;

                            if (resData.gamification.new_badges && resData.gamification.new_badges.length > 0) {
                                alertMsg += `\n\n🎉 NEUES ABZEICHEN! 🎉\n`;
                                resData.gamification.new_badges.forEach(badge => {
                                    alertMsg += `\n- ${badge.name}: ${badge.description}`;
                                });
                            }
                            alert(alertMsg);
                        }
                        msgContainer.style.color = 'green';
                        // Redirect zum Dashboard nach kurzer Verzögerung oder direkt
                        window.location.href = 'dashboard.html';
                    } else {
                        const err = await response.json();
                        msgContainer.textContent = 'Fehler: ' + (err.error || 'Etwas ist schiefgelaufen. Bitte versuche es erneut.');
                        msgContainer.style.color = 'red';
                    }
                } catch (error) {
                    msgContainer.textContent = 'Verbindung nicht möglich.';
                    msgContainer.style.color = 'red';
                }
            });
        }

        // Initialisierung
        await loadMedications();
        // loadWeight();
        loadTagsForEntry();
        loadEntryIfExists();
    }

    // Helper: Gamification Stats anzeigen
    function displayGamificationStats(user) {
        // Prüfen, ob Container schon existiert, sonst erstellen
        let statsContainer = document.getElementById('gamification-stats');
        if (!statsContainer) {
            statsContainer = document.createElement('div');
            statsContainer.id = 'gamification-stats';
            statsContainer.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
            statsContainer.style.color = 'white';
            statsContainer.style.padding = '15px';
            statsContainer.style.borderRadius = '10px';
            statsContainer.style.marginTop = '20px';
            statsContainer.style.marginBottom = '20px';
            statsContainer.style.textAlign = 'center';
            statsContainer.style.boxShadow = '0 4px 6px rgba(0,0,0,0.1)';
            
            // Nach der Begrüßung einfügen
            if (welcomeMsg && welcomeMsg.parentNode) {
                welcomeMsg.parentNode.insertBefore(statsContainer, welcomeMsg.nextSibling);
            } else {
                const main = document.querySelector('main');
                if (main) main.prepend(statsContainer);
            }
        }

        const points = user.points || 0;
        const streak = user.streak_current || 0;

        const badges = user.badges || [];
        let badgesHtml = '<div class="badges-container" style="margin-top: 15px; display: flex; flex-wrap: wrap; gap: 10px; justify-content: center;">';
        if (badges.length > 0) {
            badges.forEach(badge => {
                // Simple emoji as placeholder, could be replaced by CSS icons via badge.icon_class
                const badgeIcons = { 'badge-bronze': '🥉', 'badge-silver': '🥈', 'badge-gold': '🥇', 'badge-platinum': '🏆' };
                const icon = badgeIcons[badge.icon_class] || '🏅';
                badgesHtml += `<div class="badge" title="${badge.name}: ${badge.description}" style="font-size: 2.5em; cursor: help;">${icon}</div>`;
            });
        } else {
            badgesHtml += '<p style="font-size: 0.9em; opacity: 0.7; width: 100%;">Sammle weiter Einträge, um Abzeichen zu verdienen!</p>';
        }
        badgesHtml += '</div>';

        statsContainer.innerHTML = `
            <h3 style="margin: 0 0 10px 0;">Dein Fortschritt</h3>
            <div style="display: flex; justify-content: space-around; align-items: center;">
                <div style="font-size: 1.2em; font-weight: bold;">⭐ ${points} Punkte</div>
                <div style="font-size: 1.2em; font-weight: bold;">🔥 ${streak} Tage</div>
            </div>
            ${badgesHtml}
        `;

        // Link zur neuen Badges-Seite hinzufügen
        const badgesLink = document.createElement('a');
        badgesLink.href = 'badges.html';
        badgesLink.textContent = 'Alle Abzeichen ansehen';
        badgesLink.className = 'button';
        badgesLink.style.marginTop = '20px';
        badgesLink.style.display = 'inline-block';
        statsContainer.appendChild(badgesLink);
    }

    // Helper: Erste Schritte Karte anzeigen
    function displayFirstStepsCard() {
        const container = document.createElement('div');
        container.id = 'first-steps-card';
        container.style.background = '#fff';
        container.style.padding = '20px';
        container.style.borderRadius = '10px';
        container.style.boxShadow = '0 4px 6px rgba(0,0,0,0.1)';
        container.style.marginTop = '10px';
        container.style.marginBottom = '30px';
        container.style.borderLeft = '5px solid #4e8cff';
        
        container.innerHTML = `
            <h3 style="margin-top: 0; color: #013c4a;">👋 Willkommen bei FokusLog!</h3>
            <p>Es sieht so aus, als wärst du neu hier. Hier sind die ersten Schritte, um loszulegen:</p>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-top: 20px;">
                <a id="first-steps-med-cta" class="first-steps-cta" href="help/index.html?tab=setup" style="text-decoration: none; color: inherit;">
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb; height: 100%; display: flex; flex-direction: column; align-items: flex-start; transition: background 0.2s;">
                        <div style="font-size: 24px; margin-bottom: 10px;">⚙️</div>
                        <strong style="font-size: 1.1rem; margin-bottom: 5px;">Einrichtung starten</strong>
                        <p style="font-size: 0.9rem; color: #666; margin-bottom: 0;">Medikamente & Profil konfigurieren.</p>
                    </div>
                </a>
                <a id="first-steps-entry-cta" class="first-steps-cta" href="entry.html" style="text-decoration: none; color: inherit;">
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb; height: 100%; display: flex; flex-direction: column; align-items: flex-start; transition: background 0.2s;">
                        <div style="font-size: 24px; margin-bottom: 10px;">📝</div>
                        <strong style="font-size: 1.1rem; margin-bottom: 5px;">Ersten Eintrag erstellen</strong>
                        <p style="font-size: 0.9rem; color: #666; margin-bottom: 0;">Erstelle deinen ersten Tageseintrag.</p>
                    </div>
                </a>
            </div>
        `;
        
        const welcomeMsg = document.getElementById('welcome');
        if (welcomeMsg && welcomeMsg.parentNode) {
            welcomeMsg.parentNode.insertBefore(container, welcomeMsg.nextSibling);
        } else {
            const main = document.querySelector('main');
            if (main) main.prepend(container);
        }

        return container;
    }

    async function updateFirstStepsCardState(cardElement = document.getElementById('first-steps-card')) {
        if (!cardElement) return;
        const medCta = document.getElementById('first-steps-med-cta');
        const entryCta = document.getElementById('first-steps-entry-cta');
        let hasMedications = false;
        let hasEntries = false;

        try {
            const [medRes, entryRes] = await Promise.all([
                fetch('/api/medications'),
                fetch('/api/entries?limit=1')
            ]);

            if (medRes.ok) {
                const medData = await medRes.json();
                hasMedications = Array.isArray(medData.medications) && medData.medications.length > 0;
            }

            if (entryRes.ok) {
                const entryData = await entryRes.json();
                hasEntries = Array.isArray(entryData.entries) && entryData.entries.length > 0;
            }
        } catch (error) {
            console.error('Fehler bei der Aktualisierung der Erste-Schritte-Kacheln:', error);
        }

        if (medCta) {
            medCta.classList.toggle('hidden', hasMedications);
        }
        if (entryCta) {
            entryCta.classList.toggle('hidden', hasEntries);
        }

        const medHidden = !medCta || medCta.classList.contains('hidden');
        const entryHidden = !entryCta || entryCta.classList.contains('hidden');
        cardElement.style.display = (medHidden && entryHidden) ? 'none' : 'block';
    }

    // 5. Report-Page Logik
    if (page === 'report') {
        const dateFromInput = document.getElementById('date_from');
        const dateToInput = document.getElementById('date_to');
        const userFilterContainer = document.getElementById('user-filter-container');
        const userFilter = document.getElementById('user-filter');
        const filterBtn = document.getElementById('filter-btn');
        const exportCsvBtn = document.getElementById('export-csv-btn');
        const exportPdfBtn = document.getElementById('export-pdf-btn');
        const entriesTable = document.getElementById('entries-table');
        let reportChart = null;
        let weightChart = null;
        const reportUtils = window.FokusLogReportUtils;

        if (!reportUtils) {
            console.error('FokusLogReportUtils wurde nicht geladen.');
            return;
        }

        const {
            formatDateLabel,
            labelForTimeSlot,
            groupEntriesByDate,
            buildChartData
        } = reportUtils;

        const loadUsersForFilter = async () => {
            try {
                const meResponse = await fetch('/api/me');
                if (!meResponse.ok) {
                    if (userFilterContainer) userFilterContainer.style.display = 'none';
                    return;
                }
                const me = await meResponse.json();

                if (me.role === 'parent' && userFilterContainer) {
                    const usersResponse = await fetch('/api/users');
                    if (usersResponse.ok) {
                        const data = await usersResponse.json();
                        if (data.users && userFilter) {
                            userFilter.innerHTML = ''; // Clear existing options

                            let selfOption = document.createElement('option');
                            selfOption.value = me.id;
                            selfOption.textContent = `${me.username} (Ich)`;
                            userFilter.appendChild(selfOption);

                            data.users.forEach(u => {
                                if (u.id !== me.id && (u.role === 'parent' || u.role === 'child')) {
                                    const option = document.createElement('option');
                                    option.value = u.id;
                                    option.textContent = u.username;
                                    userFilter.appendChild(option);
                                }
                            });
                            userFilterContainer.style.display = 'block';
                        }
                    }
                } else if (userFilterContainer) {
                    userFilterContainer.style.display = 'none';
                }
            } catch (error) {
                console.error('Fehler beim Laden der Benutzer für den Filter:', error);
                if (userFilterContainer) userFilterContainer.style.display = 'none';
            }
        };

        // Funktion zum Setzen der Standard-Datumswerte (letzte 7 Tage)
        const setDefaultDates = () => {
            const today = new Date();
            const sevenDaysAgo = new Date(today);
            sevenDaysAgo.setDate(today.getDate() - 7);

            dateFromInput.value = sevenDaysAgo.toISOString().split('T')[0];
            dateToInput.value = today.toISOString().split('T')[0];
        };

        // Funktion zum Laden der Einträge
        const loadEntries = async () => {
            const dateFrom = dateFromInput.value;
            const dateTo = dateToInput.value;
            const userId = userFilter ? userFilter.value : null;

            if (!dateFrom || !dateTo) {
                entriesTable.innerHTML = '<p>Bitte wählen Sie einen Datumsbereich aus.</p>';
                return;
            }

            entriesTable.innerHTML = '<p>Lade Einträge...</p>';

            try {
                let url = `/api/entries?date_from=${dateFrom}&date_to=${dateTo}`;
                if (userId) {
                    url += `&user_id=${userId}`;
                }
                const response = await fetch(url);

                if (response.ok) {
                    const data = await response.json();

                    if (data.entries && data.entries.length > 0) {
                        displayEntries(data.entries);
                        updateChart(data.entries);
                    } else {
                        entriesTable.innerHTML = '<p>Keine Einträge im ausgewählten Zeitraum gefunden.</p>';
                        updateChart([]);
                    }
                } else {
                    entriesTable.innerHTML = '<p>Fehler beim Laden der Einträge.</p>';
                }
            } catch (error) {
                console.error('Fehler beim Laden der Einträge:', error);
                entriesTable.innerHTML = '<p>Verbindung nicht möglich.</p>'; // display weight data if loaded
                if (document.getElementById('weight-history')) loadWeightData();
            }
        };

        // Funktion zum Anzeigen der Einträge in einer Tabelle
        const displayEntries = (entries) => {
            const grouped = groupEntriesByDate(entries);

            let html = '<div class="accordion-container">';
            
            // Sortiere Datum absteigend
            Object.keys(grouped).sort().reverse().forEach(date => {
                const dayEntries = grouped[date];
                const dateLabel = formatDateLabel(date);
                
                html += `
                    <div class="accordion-item">
                        <div class="accordion-header">
                            <span>${dateLabel}</span>
                            <span>${dayEntries.length} Eintrag/Einträge</span>
                        </div>
                        <div class="accordion-content">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Zeit</th>
                                        <th>Med</th>
                                        <th style="text-align: center;">Stimmung</th>
                                        <th style="text-align: center;">Fokus</th>
                                        <th style="text-align: center;">Aktion</th>
                                    </tr>
                                </thead>
                                <tbody>
                `;

                dayEntries.forEach(entry => {
                    const timeLabel = labelForTimeSlot(entry.time);
                    html += `
                        <tr>
                            <td>${timeLabel}</td>
                            <td>${escapeHtml(entry.medication_name) || '-'} (${escapeHtml(entry.dose) || '-'})</td>
                            <td style="text-align: center;">${entry.mood || '-'}</td>
                            <td style="text-align: center;">${entry.focus || '-'}</td>
                            <td style="text-align: center;">
                                <a href="entry.html?date=${entry.date}&time=${entry.time}&user_id=${entry.user_id}">Bearbeiten</a>
                            </td>
                        </tr>
                    `;
                });

                html += `</tbody></table></div></div>`;
            });

            html += '</div>';
            entriesTable.innerHTML = html;

            // Event Listener für Accordion
            const headers = entriesTable.querySelectorAll('.accordion-header');
            headers.forEach(header => {
                header.addEventListener('click', () => {
                    const content = header.nextElementSibling;
                    content.style.display = content.style.display === 'block' ? 'none' : 'block';
                });
            });
        };

        // Funktion zum Aktualisieren des Charts
        const updateChart = (entries) => {
            const canvas = document.getElementById('reportChart');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');

            if (reportChart) {
                reportChart.destroy();
            }

            if (!entries || entries.length === 0) {
                reportChart = new Chart(ctx, {
                    type: 'line',
                    data: { labels: [], datasets: [] },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: { display: true, text: 'Verlauf der Metriken (keine Daten)' }
                        }
                    }
                });
                return;
            }

            const { labels, moodData, focusData, sleepData } = buildChartData(entries);

            reportChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Stimmung',
                            data: moodData,
                            borderColor: 'rgb(255, 99, 132)',
                            backgroundColor: 'rgba(255, 99, 132, 0.2)',
                            tension: 0.1,
                            spanGaps: true
                        },
                        {
                            label: 'Fokus',
                            data: focusData,
                            borderColor: 'rgb(54, 162, 235)',
                            backgroundColor: 'rgba(54, 162, 235, 0.2)',
                            tension: 0.1,
                            spanGaps: true
                        },
                        {
                            label: 'Schlaf',
                            data: sleepData,
                            borderColor: 'rgb(75, 192, 192)',
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
                            tension: 0.1,
                            spanGaps: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 5,
                            ticks: {
                                stepSize: 1
                            }
                        },
                        x: {
                            ticks: {
                                callback: function(value, index, ticks) {
                                    const label = this.getLabelForValue(value);
                                    if (!Array.isArray(label)) return label;

                                    const currentDate = label[0];
                                    
                                    if (index > 0) {
                                        const prevLabel = this.getLabelForValue(value - 1);
                                        if (Array.isArray(prevLabel) && prevLabel[0] === currentDate) {
                                            return ''; // Gleicher Tag, kein Label anzeigen
                                        }
                                    }
                                    return currentDate; // Neuer Tag, Datum anzeigen
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        title: {
                            display: true,
                            text: 'Verlauf der Metriken'
                        },
                        tooltip: {
                            callbacks: {
                                title: function(tooltipItems) {
                                    const label = tooltipItems[0].label;
                                    if (Array.isArray(label)) {
                                        return `${label[0]} (${label[1]})`; // z.B. "24.05.2024 (Morgen)"
                                    }
                                    return label;
                                },
                                afterBody: function(tooltipItems) {
                                    const label = tooltipItems[0].label;
                                    if (Array.isArray(label) && label.length > 2 && label[2] !== '-') {
                                        return ['', 'Medikation:', label[2]];
                                    }
                                    return [];
                                }
                            }
                        }
                    }
                }
            });
        };

        // CSV Export Funktion
        const exportToCSV = async () => {
            const dateFrom = dateFromInput.value;
            const dateTo = dateToInput.value;
            const userId = userFilter ? userFilter.value : null;

            if (!dateFrom || !dateTo) {
                alert('Bitte wählen Sie einen Datumsbereich aus.');
                return;
            }

            try {
                let url = `/api/entries?date_from=${dateFrom}&date_to=${dateTo}`;
                if (userId) {
                    url += `&user_id=${userId}`;
                }
                const response = await fetch(url);

                if (response.ok) {
                    const data = await response.json();

                    if (data.entries && data.entries.length > 0) {
                        let csv = 'Benutzername,Datum,Zeit,Medikament,Dosis,Stimmung,Fokus,Schlaf,Hyperaktivität,Reizbarkeit,Appetit,Notizen\n';

                        data.entries.forEach(entry => {
                            const timeLabel = labelForTimeSlot(entry.time);

                            const notes = [
                                entry.other_effects,
                                entry.side_effects,
                                entry.special_events,
                                entry.tags ? `Tags: ${entry.tags}` : null,
                                entry.teacher_feedback,
                                entry.emotional_reactions
                            ].filter(Boolean).join('; ');

                            csv += `"${entry.username || ''}",`;
                            csv += `"${formatDateLabel(entry.date)}",`;
                            csv += `"${timeLabel}",`;
                            csv += `"${entry.medication_name || ''}",`;
                            csv += `"${entry.dose || ''}",`;
                            csv += `"${entry.mood || ''}",`;
                            csv += `"${entry.focus || ''}",`;
                            csv += `"${entry.sleep || ''}",`;
                            csv += `"${entry.hyperactivity || ''}",`;
                            csv += `"${entry.irritability || ''}",`;
                            csv += `"${entry.appetite || ''}",`;
                            csv += `"${(notes || '').replace(/"/g, '""')}"\n`;
                        });

                        // Download CSV
                        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                        const link = document.createElement('a');
                        const url = URL.createObjectURL(blob);
                        link.setAttribute('href', url);
                        link.setAttribute('download', `fokuslog_export_${dateFrom}_${dateTo}.csv`);
                        link.style.visibility = 'hidden';
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                    } else {
                        alert('Keine Einträge zum Exportieren gefunden.');
                    }
                } else {
                    alert('Fehler beim Laden der Einträge.');
                }
            } catch (error) {
                console.error('Fehler beim Export:', error);
                alert('Fehler beim Exportieren der Daten.');
            }
        };

        const exportToPDF = async () => {
            const { jsPDF } = window.jspdf;
            const dateFrom = dateFromInput.value;
            const dateTo = dateToInput.value;
            const userId = userFilter ? userFilter.value : null;
            const username = userFilter && userFilter.selectedIndex !== -1 ? userFilter.options[userFilter.selectedIndex].text : 'Aktueller Benutzer';

            if (!dateFrom || !dateTo) {
                alert('Bitte wählen Sie einen Datumsbereich aus.');
                return;
            }

            let entries = [];
            try {
                let url = `/api/entries?date_from=${dateFrom}&date_to=${dateTo}`;
                if (userId) {
                    url += `&user_id=${userId}`;
                }
                const response = await fetch(url);
                if (response.ok) {
                    const data = await response.json();
                    if (data.entries && data.entries.length > 0) {
                        entries = data.entries;
                    }
                } else {
                    throw new Error('Fehler beim Laden der Einträge für PDF-Export.');
                }
            } catch (error) {
                console.error(error);
                alert(error.message);
                return;
            }

            const pdf = new jsPDF({ orientation: 'p', unit: 'mm', format: 'a4' });

            // Header
            pdf.setFontSize(18);
            pdf.text(`FokusLog Report für ${username}`, 14, 22);
            pdf.setFontSize(11);
            pdf.setTextColor(100);
            pdf.text(`Zeitraum: ${formatDateLabel(dateFrom)} - ${formatDateLabel(dateTo)}`, 14, 28);

            // Charts
            const canvasReport = document.getElementById('reportChart');
            const canvasWeight = document.getElementById('weightChart');
            let yPos = 40;

            if (canvasReport && canvasReport.height > 10) {
                const reportImgData = canvasReport.toDataURL('image/png');
                pdf.addImage(reportImgData, 'PNG', 14, yPos, 180, 100);
                yPos += 110;
            }

            if (canvasWeight && canvasWeight.height > 10) {
                if (yPos + 100 > 280) { // check if it fits on the page
                    pdf.addPage();
                    yPos = 20;
                }
                const weightImgData = canvasWeight.toDataURL('image/png');
                pdf.addImage(weightImgData, 'PNG', 14, yPos, 180, 100);
            }

            // Table
            if (entries.length > 0) {
                pdf.addPage();
                const tableHead = [['Datum', 'Zeit', 'Medikament', 'Dosis', 'Stimmung', 'Fokus', 'Schlaf', 'Notizen']];
                const tableBody = entries.map(entry => {
                    const timeLabel = labelForTimeSlot(entry.time);
                    const notes = [
                        entry.other_effects, 
                        entry.side_effects, 
                        entry.special_events, 
                        entry.tags ? `Tags: ${entry.tags}` : null,
                        entry.teacher_feedback, 
                        entry.emotional_reactions
                    ].filter(Boolean).join('; ');
                    return [formatDateLabel(entry.date), timeLabel, entry.medication_name || '-', entry.dose || '-', entry.mood || '-', entry.focus || '-', entry.sleep || '-', notes || '-'];
                });

                pdf.autoTable({
                    head: tableHead,
                    body: tableBody,
                    startY: 20,
                    theme: 'grid',
                    styles: { fontSize: 8 },
                    headStyles: { fillColor: [1, 60, 74] } // #013c4a
                });
            }

            pdf.save(`fokuslog_report_${username.replace(/\s/g, '_')}_${dateFrom}_${dateTo}.pdf`);
        };

        const loadWeightData = async () => {
            const dateFrom = dateFromInput.value;
            const dateTo = dateToInput.value;
            const userId = userFilter ? userFilter.value : null;
            const weightContainer = document.getElementById('weight-history');

            if (!dateFrom || !dateTo || !weightContainer) {
                if(weightContainer) weightContainer.style.display = 'none';
                return;
            }

            try {
                let url = `/api/weight?date_from=${dateFrom}&date_to=${dateTo}`;
                if (userId) {
                    url += `&user_id=${userId}`;
                }
                const response = await fetch(url);

                if (response.ok) {
                    const data = await response.json();
                    if (data.weights && data.weights.length > 0) {
                        weightContainer.style.display = 'block';
                        updateWeightChart(data.weights);
                    } else {
                        weightContainer.style.display = 'none';
                        if (weightChart) {
                            weightChart.destroy();
                            weightChart = null;
                        }
                    }
                } else {
                    weightContainer.style.display = 'none';
                }
            } catch (error) {
                console.error('Fehler beim Laden der Gewichtsdaten:', error);
                weightContainer.style.display = 'none';
            }
        };

        const updateWeightChart = (weights) => {
            const canvas = document.getElementById('weightChart');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');

            if (weightChart) {
                weightChart.destroy();
            }

            const labels = weights.map(w => formatDateLabel(w.date));
            const data = weights.map(w => w.weight);

            weightChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Gewichtsverlauf (kg)',
                        data: data,
                        borderColor: 'rgb(153, 102, 255)',
                        backgroundColor: 'rgba(153, 102, 255, 0.2)',
                        tension: 0.1,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: false }
                    },
                    plugins: {
                        legend: { display: true, position: 'top' },
                        title: { display: true, text: 'Gewichtsverlauf' }
                    }
                }
            });
        };

        // Event Listener
        if (filterBtn) {
            filterBtn.addEventListener('click', () => {
                loadEntries();
                if (typeof loadWeightData === 'function') loadWeightData(); // Load weight data when filtering
            });
        }

        if (userFilter) {
            userFilter.addEventListener('change', () => {
                loadEntries();
                if (typeof loadWeightData === 'function') loadWeightData();
            });
        }

        if (exportCsvBtn) {
            exportCsvBtn.addEventListener('click', exportToCSV);
        }

        if (exportPdfBtn) {
            exportPdfBtn.addEventListener('click', exportToPDF);
        }

        // Initialisierung
        loadUsersForFilter();
        setDefaultDates();
        loadEntries();
        if (document.getElementById('weight-history') && typeof loadWeightData === 'function') {
            loadWeightData();
        }
    }

    // 6. Badges-Page Logik
    if (page === 'badges') {
        const container = document.getElementById('badges-container');

        const loadBadges = async () => {
            try {
                const response = await fetch('/api/badges');
                if (!response.ok) {
                    if (response.status === 401) window.location.href = 'login.html';
                    throw new Error('Fehler beim Laden der Abzeichen.');
                }
                const data = await response.json();
                
                container.innerHTML = ''; // Container leeren

                const badgeIcons = { 'badge-bronze': '🥉', 'badge-silver': '🥈', 'badge-gold': '🥇', 'badge-platinum': '🏆' };

                data.badges.forEach(badge => {
                    const card = document.createElement('div');
                    card.className = 'badge-card ' + (badge.earned ? 'earned' : 'unearned');

                    const icon = badgeIcons[badge.icon_class] || '🏅';
                    
                    let progressHtml = '';
                    if (!badge.earned) {
                        const progress = Math.min(100, (data.current_streak / badge.required_streak) * 100);
                        progressHtml = `
                            <div class="badge-progress">Fortschritt: ${data.current_streak} / ${badge.required_streak}</div>
                            <div class="progress-bar">
                                <div class="progress-bar-inner" style="width: ${progress}%;"></div>
                            </div>
                        `;
                    }

                    card.innerHTML = `
                        <div class="badge-icon">${icon}</div>
                        <div class="badge-name">${badge.name}</div>
                        <div class="badge-description">${badge.description}</div>
                        ${progressHtml}
                    `;
                    container.appendChild(card);
                });

            } catch (error) {
                container.innerHTML = `<p style="color: red; grid-column: 1 / -1;">${error.message}</p>`;
            }
        };

        loadBadges();
    }

    // 7. Account Page Logik
    if (page === 'account') {
        const form = document.getElementById('change-password-form');
        const msgContainer = document.getElementById('message-container');

        if (form) {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                msgContainer.textContent = '';
                msgContainer.className = 'hidden'; // Reset classes

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
    }

    // 8. Manage Users Page (Hinzugefügt)
    if (page === 'manage_users') {
        const usersList = document.getElementById('users-list');
        const editModal = document.getElementById('edit-user-modal');
        const editForm = document.getElementById('edit-user-form');
        const modalTitle = editModal ? editModal.querySelector('h2') : null;
        const editErrorMessage = document.getElementById('edit-users-error');
        let isEditMode = false;

        // Falls vorhanden, Logik zum Laden der User
        const loadUsers = async () => {
            try {
                const response = await fetch('/api/users');
                if (response.ok) {
                    const data = await response.json();
                    renderUsers(data.users);
                } else {
                    if(usersList) usersList.innerHTML = '<p>Fehler beim Laden.</p>';
                }
            } catch (e) {
                if(usersList) usersList.innerHTML = '<p>Verbindungsfehler.</p>';
            }
        };

        const renderUsers = (users) => {
            if (!usersList) return;
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
        loadUsers();

        // Add User Button
        const btnAdd = document.getElementById('btn-add-user');
        if (btnAdd && editModal) {
            btnAdd.addEventListener('click', () => {
                isEditMode = false;
                if (modalTitle) modalTitle.textContent = 'Neuen Benutzer anlegen';
                editForm.reset();
                document.getElementById('edit-user-id').value = '';
                document.getElementById('label-password').textContent = 'Passwort (erforderlich):';
                document.getElementById('edit-password').placeholder = '';
                document.getElementById('edit-password').required = true;
                if (editErrorMessage) editErrorMessage.textContent = '';
                editModal.style.display = 'block';
            });
        }

        // Edit Button (Delegation)
        if (usersList && editModal) {
            usersList.addEventListener('click', async (e) => {
                if (e.target.classList.contains('btn-edit')) {
                    const userId = e.target.dataset.id;
                    isEditMode = true;
                    if (modalTitle) modalTitle.textContent = 'Benutzer bearbeiten';
                    if (editErrorMessage) editErrorMessage.textContent = '';
                    
                    // Fetch user details
                    try {
                        const response = await fetch(`/api/users/${userId}`);
                        if (response.ok) {
                            const data = await response.json();
                            const user = data.user;
                            document.getElementById('edit-user-id').value = user.id;
                            document.getElementById('edit-username').value = user.username;
                            document.getElementById('edit-role').value = user.role;
                            
                            document.getElementById('label-password').textContent = 'Neues Passwort (optional):';
                            document.getElementById('edit-password').placeholder = 'Leer lassen, um nicht zu ändern';
                            document.getElementById('edit-password').required = false;
                            document.getElementById('edit-password').value = '';
                            
                            editModal.style.display = 'block';
                        }
                    } catch (err) {
                        console.error(err);
                    }
                }
            });
        }

        // Modal Close
        if (editModal) {
            const closeBtn = editModal.querySelector('.close-button');
            if (closeBtn) closeBtn.onclick = () => editModal.style.display = 'none';
            window.onclick = (e) => { if (e.target == editModal) editModal.style.display = 'none'; };
        }

        // Form Submit
        if (editForm) {
            editForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(editForm);
                const data = Object.fromEntries(formData.entries());
                
                if (isEditMode && !data.password) delete data.password;

                const url = isEditMode ? `/api/users/${data.id}` : '/api/users';
                const method = isEditMode ? 'PUT' : 'POST';

                try {
                    const response = await fetch(url, {
                        method: method,
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });
                    if (response.ok) {
                        editModal.style.display = 'none';
                        loadUsers();
                    } else {
                        const res = await response.json();
                        if (editErrorMessage) editErrorMessage.textContent = res.error || 'Fehler';
                    }
                } catch (err) {
                    if (editErrorMessage) editErrorMessage.textContent = 'Verbindungsfehler';
                }
            });
        }
    }

    // 9. Edit User Page
    if (page === 'edit_user') {
        const form = document.getElementById('edit-user-form');
        const pageTitle = document.getElementById('page-title');
        const userIdInput = document.getElementById('user_id');
        const passwordInput = document.getElementById('password');
        const passwordHint = document.getElementById('password-hint');
        const deleteBtn = document.getElementById('delete-user-btn');
        const resetPasswordBtn = document.getElementById('reset-password-btn');
        const msgContainer = document.getElementById('message-container');

        const urlParams = new URLSearchParams(window.location.search);
        const userId = urlParams.get('id');

        const isEditMode = !!userId;

        const loadUserData = async () => {
            if (!isEditMode) {
                pageTitle.textContent = 'Neuen Benutzer anlegen';
                passwordInput.required = true;
                if (resetPasswordBtn) {
                    resetPasswordBtn.style.display = 'none';
                }
                passwordHint.style.display = 'none';
                return;
            }

            pageTitle.textContent = 'Benutzer bearbeiten';
            passwordInput.required = false;
            deleteBtn.style.display = 'inline-block';
            if (resetPasswordBtn) {
                resetPasswordBtn.style.display = 'inline-block';
            }
            userIdInput.value = userId;

            try {
                // Use dedicated endpoint GET /api/users/{id}
                const response = await fetch(`/api/users/${userId}`);
                if (!response.ok) {
                    if (response.status === 404) {
                        throw new Error('Benutzer nicht gefunden oder Zugriff verweigert.');
                    }
                    throw new Error('Etwas ist schiefgelaufen. Bitte versuche es erneut.');
                }
                
                const data = await response.json();
                const user = data.user;

                if (user) {
                    document.getElementById('username').value = user.username;
                    document.getElementById('role').value = user.role;
                    document.getElementById('gender').value = user.gender || '';
                } else {
                    throw new Error('Benutzerdaten konnten nicht verarbeitet werden.');
                }
            } catch (error) {
                msgContainer.textContent = error.message;
                msgContainer.style.color = 'red';
                form.style.display = 'none';
            }
        };

        if (resetPasswordBtn) {
            resetPasswordBtn.addEventListener('click', () => {
                // Generate a simple random password (8 characters)
                const newPassword = Math.random().toString(36).substring(2, 10);
                
                if (passwordInput && passwordHint) {
                    passwordInput.value = newPassword;
                    // Change type to text to make it visible
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

            // Passwort nur senden, wenn es nicht leer ist
            if (data.password === '') {
                delete data.password;
            }

            const url = isEditMode ? `/api/users/${userId}` : '/api/users';
            const method = isEditMode ? 'PUT' : 'POST';

            try {
                const response = await fetch(url, {
                    method: method,
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

        loadUserData();
    }

    // 11. Manage Tags Page
    if (page === 'manage_tags') {
        const tagsList = document.getElementById('tags-list');
        const addTagForm = document.getElementById('add-tag-form');
        const errorDiv = document.getElementById('tags-error');

        const loadTags = async () => {
            try {
                const response = await fetch('/api/tags');
                if (response.ok) {
                    const data = await response.json();
                    renderTags(data.tags);
                } else {
                    tagsList.innerHTML = '<p>Fehler beim Laden.</p>';
                }
            } catch (e) {
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
            const response = await fetch('/api/tags', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data)});
            if (response.ok) {
                addTagForm.reset();
                loadTags();
            } else {
                errorDiv.textContent = 'Fehler beim Erstellen.';
            }
        });

        tagsList.addEventListener('click', async (e) => {
            if (e.target.classList.contains('btn-delete')) {
                if(!confirm('Tag löschen?')) return;
                const id = e.target.dataset.id;
                await fetch(`/api/tags/${id}`, { method: 'DELETE' });
                loadTags();
            }
        });

        loadTags();
    }

    // 11b. Manage Meds Page
    if (page === 'manage_meds') {
        console.log('Manage Meds Page logic loaded');
        const medsList = document.getElementById('meds-list');
        const addMedForm = document.getElementById('add-med-form');
        const errorDiv = document.getElementById('meds-error');

        const loadMeds = async () => {
            try {
                const response = await fetch('/api/medications');
                if (response.ok) {
                    const data = await response.json();
                    renderMeds(data.medications);
                } else {
                    if (medsList) medsList.innerHTML = '<p>Fehler beim Laden.</p>';
                    console.error('API Fehler beim Laden der Medikamente:', response.status);
                }
            } catch (e) {
                if (medsList) medsList.innerHTML = '<p>Verbindung nicht möglich.</p>';
                console.error('Netzwerkfehler beim Laden der Medikamente:', e);
            }
        };

        const renderMeds = (meds) => {
            if (!medsList) return;
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

        if (addMedForm) {
            addMedForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                if (errorDiv) {
                    errorDiv.textContent = '';
                    errorDiv.classList.add('hidden');
                }
                
                const formData = new FormData(addMedForm);
                const data = Object.fromEntries(formData.entries());
                
                try {
                    const response = await fetch('/api/medications', { 
                        method: 'POST', 
                        headers: {'Content-Type': 'application/json'}, 
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
                } catch (err) {
                    if (errorDiv) {
                        errorDiv.textContent = 'Verbindung nicht möglich.';
                        errorDiv.classList.remove('hidden');
                    }
                }
            });
        }

        if (medsList) {
            medsList.addEventListener('click', async (e) => {
                if (e.target.classList.contains('btn-delete')) {
                    if(!confirm('Medikament wirklich löschen?')) return;
                    const id = e.target.dataset.id;
                    try {
                        const response = await fetch(`/api/medications/${id}`, { method: 'DELETE' });
                        if (response.ok) {
                            loadMeds();
                        } else {
                            alert('Fehler beim Löschen.');
                        }
                    } catch (err) {
                        alert('Verbindung fehlgeschlagen.');
                    }
                }
            });
        }

        loadMeds();
    }

    // 12. Hilfe-Seite Tabs
    if (page === 'help') {
        const tabs = document.querySelectorAll('.tab-btn');
        const contents = document.querySelectorAll('.tab-content');

        const switchTab = (tabId) => {
            tabs.forEach(t => t.classList.remove('active'));
            contents.forEach(c => c.classList.remove('active'));
            
            const targetBtn = document.querySelector(`.tab-btn[data-tab="${tabId}"]`);
            const targetContent = document.getElementById(tabId);
            
            if (targetBtn && targetContent) {
                targetBtn.classList.add('active');
                targetContent.classList.add('active');
            }
        };

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                switchTab(tab.dataset.tab);
            });
        });

        // URL Parameter prüfen (z.B. help.html?tab=setup)
        const urlParams = new URLSearchParams(window.location.search);
        const tabParam = urlParams.get('tab');
        if (tabParam) {
            switchTab(tabParam);
        }
    }

    // Add footer links to all pages that load app.js
    addFooterLinks();
});

/**
 * Service Worker Management
 * Prüft auf Updates und benachrichtigt den Nutzer.
 */
function initServiceWorker() {
    if (!('serviceWorker' in navigator)) return;

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/app/service-worker.js', { scope: '/app/', updateViaCache: 'none' })
            .then(registration => {
                // Fall 1: Ein Update wartet bereits (z.B. nach Reload)
                if (registration.waiting) {
                    notifyUpdate(registration.waiting);
                }

                // Fall 2: Ein neues Update wird gefunden
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            notifyUpdate(newWorker);
                        }
                    });
                });
            })
            .catch(err => console.error('SW Registration failed:', err));
    });

    // Wenn der neue SW die Kontrolle übernimmt, Seite neu laden
    let refreshing;
    navigator.serviceWorker.addEventListener('controllerchange', () => {
        if (refreshing) return;
        window.location.reload();
        refreshing = true;
    });
}

function notifyUpdate(worker) {
    const notification = document.createElement('div');
    notification.style.cssText = 'position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: #333; color: white; padding: 15px 20px; border-radius: 5px; box-shadow: 0 4px 6px rgba(0,0,0,0.2); z-index: 10000; display: flex; align-items: center; gap: 15px; font-family: sans-serif; font-size: 14px;';
    notification.innerHTML = `
        <span>Neue Version verfügbar.</span>
        <button id="sw-update-btn" style="background: #4e8cff; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer;">Aktualisieren</button>
    `;

    document.body.appendChild(notification);

    document.getElementById('sw-update-btn').addEventListener('click', () => {
        worker.postMessage({ action: 'skipWaiting' });
    });
}

initServiceWorker();