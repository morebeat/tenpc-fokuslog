/**
 * Entry Page Module — Tagebucheintrag erstellen und bearbeiten
 * 
 * Verwaltet das Eintragsformular mit Medikamenten-Auswahl, Tags,
 * Skalen-Ratings (Fokus, Stimmung, etc.) und Auto-Load bei Datums-/Zeit-Änderung.
 * 
 * @module pages/entry
 */
(function (global) {
    const FokusLog = global.FokusLog || (global.FokusLog = {});
    const pages = FokusLog.pages || (FokusLog.pages = {});
    const components = FokusLog.components || (FokusLog.components = {});

    /**
     * Entry-Seiten-Controller
     * @type {{init: function(): Promise<void>}}
     */
    pages.entry = {
        init: async ({ utils }) => {
            const ratingUtils = utils?.ratingHints;
            initRatingUi();
            await initEntryForm(utils, ratingUtils);
        }
    };

    /**
     * Initialisiert die Rating-UI (Skalen-Buttons) mit Hover/Click-Effekten.
     * @private
     */
    function initRatingUi() {
        const ratingGroups = document.querySelectorAll('.rating-group');
        ratingGroups.forEach(group => {
            const inputs = group.querySelectorAll('input[type="radio"]');
            const labels = group.querySelectorAll('label');

            labels.forEach((label, index) => {
                label.addEventListener('mouseenter', () => {
                    labels.forEach(l => l.classList.remove('highlight'));
                    if (!inputs[index].checked) label.classList.add('highlight');
                });

                label.addEventListener('mouseleave', () => {
                    label.classList.remove('highlight');
                });

                label.addEventListener('click', () => {
                    inputs.forEach(input => input.checked = false);
                    inputs[index].checked = true;
                    labels.forEach(l => l.classList.remove('active'));
                    label.classList.add('active');
                    labels.forEach(l => l.classList.remove('highlight'));
                });
            });

            inputs.forEach((input, index) => {
                if (input.checked) {
                    labels[index].classList.add('active');
                }
            });
        });
    }

    /**
     * Initialisiert das Eintragsformular mit allen Event-Listenern.
     * @async
     * @private
     * @param {Object} [ratingUtils] - Rating-Hints Utilities
     * @returns {Promise<void>}
     */
    async function initEntryForm(utils, ratingUtils) {
        const dateInput = document.getElementById('date');

        // "Heute"-Button dynamisch hinzufügen
        if (dateInput) {
            const todayBtn = document.createElement('button');
            todayBtn.type = 'button';
            todayBtn.textContent = 'Heute';
            todayBtn.className = 'button button-secondary';
            todayBtn.style.marginLeft = '10px';
            todayBtn.style.padding = '0.3rem 0.8rem';
            todayBtn.style.fontSize = '0.85rem';
            
            todayBtn.addEventListener('click', () => {
                const now = new Date();
                // Lokales Datum im Format YYYY-MM-DD erzwingen
                const localDate = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
                dateInput.value = localDate;
                dateInput.dispatchEvent(new Event('change'));
            });
            dateInput.parentNode.insertBefore(todayBtn, dateInput.nextSibling);

            // "Gestern"-Button hinzufügen
            const yesterdayBtn = document.createElement('button');
            yesterdayBtn.type = 'button';
            yesterdayBtn.textContent = 'Gestern';
            yesterdayBtn.className = 'button button-secondary';
            yesterdayBtn.style.marginLeft = '5px';
            yesterdayBtn.style.padding = '0.3rem 0.8rem';
            yesterdayBtn.style.fontSize = '0.85rem';
            
            yesterdayBtn.addEventListener('click', () => {
                const date = new Date();
                date.setDate(date.getDate() - 1);
                const localDate = date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
                dateInput.value = localDate;
                dateInput.dispatchEvent(new Event('change'));
            });
            dateInput.parentNode.insertBefore(yesterdayBtn, todayBtn.nextSibling);

            // Max-Datum auf Heute setzen (keine Zukunftseinträge)
            const now = new Date();
            const maxDate = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
            dateInput.max = maxDate;
        }

        const timeInput = document.getElementById('time');
        const medSelect = document.getElementById('medication_id');
        const form = document.getElementById('entry-form');
        const tagsContainer = document.getElementById('tags-selection-container');
        const msgContainer = document.getElementById('message-container');
        const ratingHintSections = document.querySelectorAll('.rating-section[data-scale]');
        let entryExists = false;
        let currentEntryId = null;
        let hasUnsavedChanges = false;
        const DRAFT_KEY = 'fokuslog_entry_draft';

        window.addEventListener('beforeunload', (e) => {
            if (hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        const urlParams = new URLSearchParams(window.location.search);
        const paramDate = urlParams.get('date');
        const paramTime = urlParams.get('time');
        const paramUserId = urlParams.get('user_id');

        if (paramDate) {
            dateInput.value = paramDate;
        } else if (dateInput && !dateInput.value) {
            dateInput.value = new Date().toISOString().split('T')[0];
        }

        if (paramTime) {
            if (timeInput) timeInput.value = paramTime;
        } else if (timeInput) {
            const h = new Date().getHours();
            if (h >= 4 && h < 11) timeInput.value = 'morning';
            else if (h >= 11 && h < 15) timeInput.value = 'noon';
            else timeInput.value = 'evening';
        }

        let medicationMap = {};

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
            if (!input || !ratingUtils) return;
            const section = input.closest('.rating-section[data-scale]');
            if (!section) return;
            const value = parseInt(input.value, 10);
            if (ratingUtils.shouldShowHelpHint(value)) {
                const label = section.dataset.helpLabel || 'diesem Bereich';
                const helpUrl = section.dataset.helpUrl || 'help/index.html';
                const message = ratingUtils.buildHelpHintMessage({ value, label, helpUrl });
                showScaleHint(section, message);
            } else {
                hideScaleHint(section);
            }
        };

        ratingHintSections.forEach(section => {
            const radios = section.querySelectorAll('input[type="radio"]');
            radios.forEach(radio => {
                radio.addEventListener('change', () => applyScaleHint(radio));
                if (radio.checked) applyScaleHint(radio);
            });
        });

        const loadMedications = async () => {
            try {
                const data = await utils.apiCall('/api/medications');
                if (data.medications) {
                    medicationMap = {};
                    while (medSelect.options.length > 2) {
                        medSelect.remove(2);
                    }
                    data.medications.forEach(med => {
                        medicationMap[med.id] = med.default_dose;
                        const option = document.createElement('option');
                        option.value = med.id;
                        option.textContent = `${med.name} ${med.default_dose ? '(' + med.default_dose + ')' : ''}`;
                        medSelect.appendChild(option);
                    });
                }
            } catch (error) {
                utils.error('Fehler beim Laden der Medikamente', error);
            }
        };

        const loadTagsForEntry = async () => {
            if (!tagsContainer) return;
            try {
                const data = await utils.apiCall('/api/tags');
                if (data) {
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
            } catch (error) {
                utils.error('Fehler beim Laden der Tags', error);
            }
        };

        const loadLastEntryDefaults = async () => {
            try {
                let url = '/api/entries?limit=1';
                if (paramUserId) {
                    url += `&user_id=${paramUserId}`;
                }
                const data = await utils.apiCall(url);
                if (data) {
                    if (data.entries && data.entries.length > 0) {
                        const lastEntry = data.entries[0];
                        if (lastEntry.medication_id) {
                            medSelect.value = lastEntry.medication_id;
                        } else if (lastEntry.medication_id === null) {
                            medSelect.value = '0';
                        }
                        const weightInput = document.getElementById('weight');
                        if (weightInput && lastEntry.weight) {
                            weightInput.value = lastEntry.weight;
                        }
                    }
                }
            } catch (error) {
                utils.error('Fehler beim Laden der Standardwerte', error);
            }
        };

        const restoreDraft = () => {
            if (entryExists) return; // Keine Entwürfe laden, wenn Eintrag existiert
            try {
                const raw = localStorage.getItem(DRAFT_KEY);
                if (!raw) return;
                const draft = JSON.parse(raw);
                
                // Nur wiederherstellen, wenn der Entwurf zum aktuellen Datum passt (optional)
                if (draft.date && draft.date !== dateInput.value) return;

                Object.keys(draft).forEach(key => {
                    const input = form.querySelector(`[name="${key}"]`);
                    if (input && input.type !== 'file' && input.type !== 'submit') {
                        input.value = draft[key];
                        input.dispatchEvent(new Event('change')); // Trigger für UI-Updates (z.B. Slider/Radios)
                    }
                });
                utils.toast('Entwurf wiederhergestellt', 'info');
            } catch (e) { console.error('Draft restore failed', e); }
        };

        const loadEntryIfExists = async () => {
            const dateVal = dateInput.value;
            const timeVal = timeInput.value;
            if (!dateVal || !timeVal) return;
            try {
                let url = `/api/entries?date_from=${dateVal}&date_to=${dateVal}&time=${timeVal}`;
                if (paramUserId) {
                    url += `&user_id=${paramUserId}`;
                }
                const data = await utils.apiCall(url);
                if (data) {
                    if (data.entries && data.entries.length > 0) {
                        const entry = data.entries[0];
                        entryExists = true;
                        currentEntryId = entry.id;
                        const header = document.querySelector('header');
                        if (header) {
                            header.textContent = entry.username ? `Eintrag bearbeiten: ${entry.username}` : 'Eintrag bearbeiten';
                        }
                        const submitBtn = form.querySelector('input[type="submit"]');
                        if (submitBtn) submitBtn.value = 'Änderungen speichern';
                        showDeleteButton(true);
                        for (const key in entry) {
                            const input = form.querySelector(`[name="${key}"]`);
                            if (input && (input.type === 'text' || input.type === 'number' || input.tagName === 'SELECT' || input.tagName === 'TEXTAREA')) {
                                input.value = entry[key] || '';
                            }
                        }
                        if (entry.medication_id === null) {
                            medSelect.value = '0';
                        }
                        ['sleep', 'hyperactivity', 'mood', 'irritability', 'appetite', 'focus'].forEach(metric => {
                            if (entry[metric] !== null) {
                                const radio = form.querySelector(`input[name="${metric}"][value="${entry[metric]}"]`);
                                if (radio) {
                                    radio.checked = true;
                                    const label = radio.nextElementSibling;
                                    if (label && label.tagName === 'LABEL') {
                                        const group = radio.closest('.rating-group');
                                        if (group) {
                                            group.querySelectorAll('label').forEach(l => l.classList.remove('active'));
                                        }
                                        label.classList.add('active');
                                    }
                                    applyScaleHint(radio);
                                }
                            } else {
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
                        if (entry.tag_ids) {
                            const tagIds = entry.tag_ids.split(',').map(id => id.trim());
                            tagIds.forEach(id => {
                                const cb = document.getElementById(`tag-${id}`);
                                if (cb) cb.checked = true;
                            });
                        }
                    } else {
                        entryExists = false;
                        currentEntryId = null;
                        showDeleteButton(false);
                        const inputs = form.querySelectorAll('input:not([type="submit"]):not([type="hidden"]):not([type="date"]), select, textarea');
                        inputs.forEach(input => {
                            if (input.id !== 'date' && input.id !== 'time') {
                                input.value = '';
                            }
                        });
                        const radios = form.querySelectorAll('input[type="radio"]');
                        radios.forEach(r => {
                            r.checked = false;
                            if (r.nextElementSibling) {
                                r.nextElementSibling.classList.remove('active');
                            }
                        });
                        ratingHintSections.forEach(section => hideScaleHint(section));
                        const checkboxes = form.querySelectorAll('input[type="checkbox"]');
                        checkboxes.forEach(cb => cb.checked = false);
                        await loadLastEntryDefaults();
                        restoreDraft(); // Versuchen, Entwurf zu laden, wenn kein Server-Eintrag da ist
                    }
                }
                hasUnsavedChanges = false;
            } catch (error) {
                utils.error('Fehler beim Laden des Eintrags:', error);
            }
        };

        const showDeleteButton = (show) => {
            let deleteBtn = document.getElementById('btn-delete-entry');
            if (show) {
                if (!deleteBtn) {
                    deleteBtn = document.createElement('button');
                    deleteBtn.id = 'btn-delete-entry';
                    deleteBtn.type = 'button';
                    deleteBtn.textContent = 'Eintrag löschen';
                    deleteBtn.className = 'button';
                    deleteBtn.style.backgroundColor = '#dc3545';
                    deleteBtn.style.marginLeft = '10px';
                    deleteBtn.addEventListener('click', async () => {
                        if (confirm('Möchten Sie diesen Eintrag wirklich löschen?')) {
                            try {
                                await utils.apiCall(`/api/entries/${currentEntryId}`, { method: 'DELETE' });
                                utils.toast('Eintrag gelöscht.', 'success');
                                hasUnsavedChanges = false;
                                setTimeout(() => window.location.reload(), 1000);
                            } catch (error) {
                                utils.toast('Fehler beim Löschen.', 'error');
                            }
                        }
                    });
                    const submitBtn = form.querySelector('input[type="submit"]');
                    if (submitBtn) submitBtn.parentNode.insertBefore(deleteBtn, submitBtn.nextSibling);
                }
                deleteBtn.style.display = 'inline-block';
            } else if (deleteBtn) {
                deleteBtn.style.display = 'none';
            }
        };

        if (dateInput && timeInput) {
            dateInput.addEventListener('change', loadEntryIfExists);
            timeInput.addEventListener('change', loadEntryIfExists);
        }

        if (form) {
            form.addEventListener('input', () => { 
                hasUnsavedChanges = true;
                // Auto-Save Draft
                const formData = new FormData(form);
                localStorage.setItem(DRAFT_KEY, JSON.stringify(Object.fromEntries(formData.entries())));
            });
            form.addEventListener('change', () => { hasUnsavedChanges = true; });

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                if (entryExists && !confirm('Für diesen Zeitraum existiert bereits ein Eintrag. Möchten Sie ihn überschreiben?')) {
                    return;
                }
                if (msgContainer) {
                    msgContainer.textContent = 'Speichere...';
                    msgContainer.style.color = 'inherit';
                }
                const formData = new FormData(form);
                const data = Object.fromEntries(formData.entries());

                // FIX: Explizites Auslesen der Radio-Buttons für Skalenwerte
                // Stellt sicher, dass Werte auch bei Custom-UI-Interaktionen korrekt übernommen werden
                ['mood', 'focus', 'energy', 'sleep', 'irritability', 'appetite', 'hyperactivity'].forEach(key => {
                    const checked = form.querySelector(`input[name="${key}"]:checked`);
                    if (checked) {
                        data[key] = checked.value;
                    }
                });

                // Validierung: Keine Zukunft
                const now = new Date();
                const todayStr = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
                if (data.date && data.date > todayStr) {
                    utils.toast('Einträge für die Zukunft sind nicht möglich.', 'warning');
                    if (msgContainer) msgContainer.textContent = '';
                    return;
                }

                // Validierung des Gewichts (falls angegeben)
                if (data.weight) {
                    const w = parseFloat(data.weight);
                    if (isNaN(w) || w < 10 || w > 150) {
                        utils.toast('Bitte ein Gewicht zwischen 10 und 150 kg eingeben.', 'warning');
                        if (msgContainer) msgContainer.textContent = '';
                        return;
                    }
                    // Auf 1 Nachkommastelle runden (z.B. 35.5)
                    data.weight = w.toFixed(1);
                }

                const selectedTags = [];
                form.querySelectorAll('input[name="tags[]"]:checked').forEach(cb => {
                    selectedTags.push(cb.value);
                });
                data.tags = selectedTags;
                if (paramUserId) {
                    data.target_user_id = paramUserId;
                }
                if (data.medication_id && medicationMap[data.medication_id]) {
                    data.dose = medicationMap[data.medication_id];
                }
                try {
                    const resData = await utils.apiCall('/api/entries', {
                        method: 'POST',
                        body: JSON.stringify(data)
                    });
                    
                    if (msgContainer) {
                        msgContainer.textContent = 'Eintrag erfolgreich gespeichert!';
                        msgContainer.style.color = 'green';
                    }

                    hasUnsavedChanges = false;
                    localStorage.removeItem(DRAFT_KEY); // Entwurf löschen

                    utils.toast('Eintrag gespeichert!', 'success');

                    if (resData.gamification && components.gamification) {
                        components.gamification.notifyAchievements(resData.gamification, utils);
                    }
                    
                    setTimeout(() => window.location.href = 'dashboard.html', 1500);
                } catch (error) {
                    const msg = (error.body && error.body.error) || error.message || 'Verbindung nicht möglich.';
                    if (msgContainer) {
                        msgContainer.textContent = 'Fehler: ' + msg;
                        msgContainer.style.color = 'red';
                    }
                    utils.toast(msg, 'error');
                }
            });
        }

        await loadMedications();
        loadTagsForEntry();
        loadEntryIfExists();
    }
})(window);
