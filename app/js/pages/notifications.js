(function (global) {
    const FokusLog = global.FokusLog || (global.FokusLog = {});
    const pages = FokusLog.pages || (FokusLog.pages = {});

    pages.notifications = {
        init: async ({ utils }) => {
            const pushEnabled = document.getElementById('push_enabled');
            const pushDetails = document.getElementById('push-details');
            const pushNotSupported = document.getElementById('push-not-supported');
            const pushSettings = document.getElementById('push-settings');
            
            const emailInput = document.getElementById('email');
            const emailStatus = document.getElementById('email-status');
            const emailSettings = document.getElementById('email-settings');
            const verifyEmailBtn = document.getElementById('verify-email-btn');
            
            const emailWeeklyDigest = document.getElementById('email_weekly_digest');
            const digestDaySetting = document.getElementById('digest-day-setting');
            const emailMissingAlert = document.getElementById('email_missing_alert');
            const missingDaysSetting = document.getElementById('missing-days-setting');
            
            const saveBtn = document.getElementById('save-settings-btn');
            const messageContainer = document.getElementById('message-container');
            const notificationStatus = document.getElementById('notification-status');

            let currentSettings = {};
            let pushSubscription = null;

            // Check Push API support
            const pushSupported = 'serviceWorker' in navigator && 'PushManager' in window;
            if (!pushSupported) {
                pushNotSupported.style.display = 'block';
                pushSettings.style.display = 'none';
            }

            // Show/hide push details
            pushEnabled?.addEventListener('change', () => {
                pushDetails.style.display = pushEnabled.checked ? 'block' : 'none';
            });

            // Show/hide email settings based on email input
            emailInput?.addEventListener('input', () => {
                const hasEmail = emailInput.value.trim().length > 0;
                emailSettings.style.display = hasEmail ? 'block' : 'none';
            });

            // Show/hide digest day setting
            emailWeeklyDigest?.addEventListener('change', () => {
                digestDaySetting.style.display = emailWeeklyDigest.checked ? 'block' : 'none';
            });

            // Show/hide missing days setting
            emailMissingAlert?.addEventListener('change', () => {
                missingDaysSetting.style.display = emailMissingAlert.checked ? 'block' : 'none';
            });

            // Load current settings
            const loadSettings = async () => {
                try {
                    const response = await fetch('/api/notifications/settings');
                    if (response.ok) {
                        const data = await response.json();
                        currentSettings = data.settings || {};
                        applySettings(currentSettings);
                    }
                } catch (error) {
                    console.error('Fehler beim Laden der Einstellungen:', error);
                    showMessage('Fehler beim Laden der Einstellungen', 'error');
                }
            };

            const applySettings = (settings) => {
                // Push settings
                if (pushEnabled) {
                    pushEnabled.checked = settings.push_enabled || false;
                    pushDetails.style.display = settings.push_enabled ? 'block' : 'none';
                }
                
                document.getElementById('push_morning').checked = settings.push_morning !== false;
                document.getElementById('push_noon').checked = settings.push_noon !== false;
                document.getElementById('push_evening').checked = settings.push_evening !== false;
                
                document.getElementById('push_morning_time').value = settings.push_morning_time || '08:00';
                document.getElementById('push_noon_time').value = settings.push_noon_time || '12:00';
                document.getElementById('push_evening_time').value = settings.push_evening_time || '18:00';
                
                // Email settings
                if (emailInput) {
                    emailInput.value = settings.email || '';
                    emailSettings.style.display = settings.email ? 'block' : 'none';
                    
                    if (settings.email) {
                        if (settings.email_verified) {
                            emailStatus.textContent = '✓ Verifiziert';
                            emailStatus.className = 'status-badge verified';
                            verifyEmailBtn.style.display = 'none';
                        } else {
                            emailStatus.textContent = '⚠ Nicht verifiziert';
                            emailStatus.className = 'status-badge unverified';
                            verifyEmailBtn.style.display = 'inline-block';
                        }
                    } else {
                        emailStatus.textContent = '';
                        verifyEmailBtn.style.display = 'none';
                    }
                }
                
                if (emailWeeklyDigest) {
                    emailWeeklyDigest.checked = settings.email_weekly_digest || false;
                    digestDaySetting.style.display = settings.email_weekly_digest ? 'block' : 'none';
                }
                
                document.getElementById('email_digest_day').value = settings.email_digest_day || 0;
                
                if (emailMissingAlert) {
                    emailMissingAlert.checked = settings.email_missing_alert || false;
                    missingDaysSetting.style.display = settings.email_missing_alert ? 'block' : 'none';
                }
                
                document.getElementById('email_missing_days').value = settings.email_missing_days || 3;
            };

            // Load notification status
            const loadStatus = async () => {
                try {
                    const response = await fetch('/api/notifications/status');
                    if (response.ok) {
                        const data = await response.json();
                        displayStatus(data);
                    }
                } catch (error) {
                    console.error('Fehler beim Laden des Status:', error);
                    notificationStatus.innerHTML = '<p class="error">Status konnte nicht geladen werden.</p>';
                }
            };

            const displayStatus = (data) => {
                let html = '<div class="status-grid">';
                
                // Last entry
                if (data.last_entry_date) {
                    const daysAgo = data.days_since_entry;
                    const daysText = daysAgo === 0 ? 'Heute' : 
                                    daysAgo === 1 ? 'Gestern' : 
                                    `Vor ${daysAgo} Tagen`;
                    html += `
                        <div class="status-item">
                            <span class="status-value">${daysText}</span>
                            <span class="status-label">Letzter Eintrag</span>
                        </div>
                    `;
                } else {
                    html += `
                        <div class="status-item">
                            <span class="status-value">-</span>
                            <span class="status-label">Noch keine Einträge</span>
                        </div>
                    `;
                }
                
                // Missing slots today
                if (data.today_missing_slots && data.today_missing_slots.length > 0) {
                    const slotLabels = {
                        morning: 'Morgen',
                        noon: 'Mittag',
                        evening: 'Abend'
                    };
                    const missing = data.today_missing_slots.map(s => slotLabels[s]).join(', ');
                    html += `
                        <div class="status-item">
                            <span class="status-value">${missing}</span>
                            <span class="status-label">Heute noch offen</span>
                        </div>
                    `;
                } else {
                    html += `
                        <div class="status-item">
                            <span class="status-value">✓</span>
                            <span class="status-label">Heute alle Einträge</span>
                        </div>
                    `;
                }
                
                // Notification status
                html += `
                    <div class="status-item">
                        <span class="status-value">${data.notifications?.push_enabled ? '✓' : '✗'}</span>
                        <span class="status-label">Push aktiv</span>
                    </div>
                    <div class="status-item">
                        <span class="status-value">${data.notifications?.email_enabled ? '✓' : '✗'}</span>
                        <span class="status-label">E-Mail aktiv</span>
                    </div>
                `;
                
                html += '</div>';
                notificationStatus.innerHTML = html;
            };

            // Save settings
            const saveSettings = async () => {
                const newSettings = {
                    push_morning: document.getElementById('push_morning').checked,
                    push_noon: document.getElementById('push_noon').checked,
                    push_evening: document.getElementById('push_evening').checked,
                    push_morning_time: document.getElementById('push_morning_time').value,
                    push_noon_time: document.getElementById('push_noon_time').value,
                    push_evening_time: document.getElementById('push_evening_time').value,
                    email: emailInput.value.trim() || null,
                    email_weekly_digest: emailWeeklyDigest.checked,
                    email_digest_day: parseInt(document.getElementById('email_digest_day').value, 10),
                    email_missing_alert: emailMissingAlert.checked,
                    email_missing_days: parseInt(document.getElementById('email_missing_days').value, 10)
                };

                try {
                    // Handle push subscription
                    if (pushSupported && pushEnabled.checked && !currentSettings.push_enabled) {
                        await subscribeToPush();
                    } else if (pushSupported && !pushEnabled.checked && currentSettings.push_enabled) {
                        await unsubscribeFromPush();
                    }

                    // Save other settings
                    const response = await fetch('/api/notifications/settings', {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(newSettings)
                    });

                    if (response.ok) {
                        const data = await response.json();
                        currentSettings = data.settings || {};
                        applySettings(currentSettings);
                        showMessage('Einstellungen gespeichert!', 'success');
                        loadStatus();
                    } else {
                        const error = await response.json();
                        showMessage(error.error || 'Fehler beim Speichern', 'error');
                    }
                } catch (error) {
                    console.error('Fehler beim Speichern:', error);
                    showMessage('Fehler beim Speichern der Einstellungen', 'error');
                }
            };

            // Push subscription management
            const subscribeToPush = async () => {
                try {
                    const registration = await navigator.serviceWorker.ready;
                    
                    // Request permission
                    const permission = await Notification.requestPermission();
                    if (permission !== 'granted') {
                        showMessage('Push-Benachrichtigungen wurden nicht erlaubt', 'error');
                        pushEnabled.checked = false;
                        return;
                    }

                    // Get VAPID public key from server (would need an endpoint for this)
                    // For now, use a placeholder
                    const vapidPublicKey = await getVapidPublicKey();
                    
                    if (!vapidPublicKey) {
                        console.warn('VAPID public key not available');
                        // Still save settings, push might work differently
                    }

                    const subscription = await registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: vapidPublicKey ? urlBase64ToUint8Array(vapidPublicKey) : undefined
                    });

                    // Send subscription to server
                    const response = await fetch('/api/notifications/push/subscribe', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ subscription: subscription.toJSON() })
                    });

                    if (!response.ok) {
                        throw new Error('Failed to save subscription');
                    }

                    pushSubscription = subscription;
                    showMessage('Push-Benachrichtigungen aktiviert!', 'success');
                } catch (error) {
                    console.error('Push subscription error:', error);
                    pushEnabled.checked = false;
                    showMessage('Push-Benachrichtigungen konnten nicht aktiviert werden', 'error');
                }
            };

            const unsubscribeFromPush = async () => {
                try {
                    const registration = await navigator.serviceWorker.ready;
                    const subscription = await registration.pushManager.getSubscription();
                    
                    if (subscription) {
                        await subscription.unsubscribe();
                    }

                    await fetch('/api/notifications/push/unsubscribe', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' }
                    });

                    pushSubscription = null;
                } catch (error) {
                    console.error('Push unsubscription error:', error);
                }
            };

            const getVapidPublicKey = async () => {
                // TODO: Implement endpoint to get VAPID public key
                // For now, return null (push uses fallback)
                return null;
            };

            const urlBase64ToUint8Array = (base64String) => {
                const padding = '='.repeat((4 - base64String.length % 4) % 4);
                const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
                const rawData = window.atob(base64);
                const outputArray = new Uint8Array(rawData.length);
                for (let i = 0; i < rawData.length; ++i) {
                    outputArray[i] = rawData.charCodeAt(i);
                }
                return outputArray;
            };

            // Resend verification email
            const resendVerification = async () => {
                try {
                    const response = await fetch('/api/notifications/email/resend-verification', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' }
                    });

                    if (response.ok) {
                        showMessage('Verifizierungs-E-Mail wurde gesendet!', 'success');
                    } else {
                        const error = await response.json();
                        showMessage(error.error || 'Fehler beim Senden', 'error');
                    }
                } catch (error) {
                    console.error('Resend verification error:', error);
                    showMessage('Fehler beim Senden der E-Mail', 'error');
                }
            };

            // Show message helper
            const showMessage = (text, type = 'info') => {
                messageContainer.textContent = text;
                messageContainer.className = `message-container ${type}`;
                messageContainer.style.display = 'block';
                
                setTimeout(() => {
                    messageContainer.style.display = 'none';
                }, 5000);
            };

            // Event listeners
            saveBtn?.addEventListener('click', saveSettings);
            verifyEmailBtn?.addEventListener('click', resendVerification);

            // Initial load
            await loadSettings();
            await loadStatus();
        }
    };
})(window);
