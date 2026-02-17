(function (global) {
    const FokusLog = global.FokusLog || (global.FokusLog = {});
    const pages = FokusLog.pages || (FokusLog.pages = {});

    pages.entry = {
        init: async () => {
            const ratingUtils = FokusLog.utils?.ratingHints;
            initRatingUi();
            await initEntryForm(ratingUtils);
        }
    };

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

    async function initEntryForm(ratingUtils) {
        const dateInput = document.getElementById('date');
        const timeInput = document.getElementById('time');
        const medSelect = document.getElementById('medication_id');
        const form = document.getElementById('entry-form');
        const tagsContainer = document.getElementById('tags-selection-container');
        const msgContainer = document.getElementById('message-container');
        const ratingHintSections = document.querySelectorAll('.rating-section[data-scale]');
        let entryExists = false;
        let currentEntryId = null;

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
            timeInput.value = paramTime;
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
                const response = await fetch('/api/medications');
                if (response.ok) {
                    const data = await response.json();
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
                console.error('Fehler beim Laden der Medikamente', error);
            }
        };

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
            } catch (error) {
                console.error('Fehler beim Laden der Tags', error);
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
                console.error('Fehler beim Laden der Standardwerte', error);
            }
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
                const res = await fetch(url);
                if (res.ok) {
                    const data = await res.json();
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
                    }
                }
            } catch (error) {
                console.error('Fehler beim Laden des Eintrags:', error);
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
                                const res = await fetch(`/api/entries/${currentEntryId}`, { method: 'DELETE' });
                                if (res.ok) {
                                    alert('Eintrag gelöscht.');
                                    window.location.reload();
                                } else {
                                    alert('Fehler beim Löschen.');
                                }
                            } catch (error) {
                                alert('Verbindung fehlgeschlagen.');
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
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                if (entryExists && !confirm('Für diesen Zeitraum existiert bereits ein Eintrag. Möchten Sie ihn überschreiben?')) {
                    return;
                }
                msgContainer.textContent = 'Speichere...';
                msgContainer.style.color = 'inherit';
                const formData = new FormData(form);
                const data = Object.fromEntries(formData.entries());
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

        await loadMedications();
        loadTagsForEntry();
        loadEntryIfExists();
    }
})(window);
