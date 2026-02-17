/**
 * Dashboard Page Module — Hauptübersicht des Benutzers
 * 
 * Zeigt Gamification-Statistiken für Kinder, Admin-Bereich für Eltern,
 * und Erste-Schritte-Karte für neue Benutzer.
 * 
 * @module pages/dashboard
 */
(function (global) {
    const FokusLog = global.FokusLog || (global.FokusLog = {});
    const pages = FokusLog.pages || (FokusLog.pages = {});

    /**
     * Dashboard-Seiten-Controller
     * @type {{init: function({user: Object, utils: Object}): Promise<void>}}
     */
    pages.dashboard = {
        /**
         * Initialisiert die Dashboard-Seite.
         * @async
         * @param {Object} params - Parameter-Objekt
         * @param {Object} params.user - Aktueller Benutzer
         * @param {Object} params.utils - Utility-Funktionen
         * @returns {Promise<void>}
         */
        init: async ({ user, utils }) => {
            if (!user) return;
            if (user.role === 'child') {
                displayGamificationStats(user);
            }
            toggleAdminSection(user);
            if (!user.points || user.points === 0) {
                const card = ensureFirstStepsCard();
                await updateFirstStepsCardState(card);
            }
        }
    };

    /**
     * Zeigt oder versteckt den Admin-Bereich je nach Benutzerrolle.
     * @private
     * @param {Object} user - Benutzerobjekt mit role-Property
     */
    function toggleAdminSection(user) {
        const adminSection = document.getElementById('admin-section');
        if (!adminSection) return;
        const role = (user.role || '').toLowerCase().trim();
        const canManage = role === 'parent' || role === 'adult';
        if (canManage) {
            adminSection.style.display = 'block';
            adminSection.classList.remove('hidden');
        } else {
            adminSection.style.display = 'none';
        }
    }

    /**
     * Zeigt Gamification-Statistiken (Punkte, Streak, Badges) für Kinder an.
     * @private
     * @param {Object} user - Benutzerobjekt mit points, streak_current, badges
     */
    function displayGamificationStats(user) {
        let statsContainer = document.getElementById('gamification-stats');
        const welcomeMsg = document.getElementById('welcome');
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

        const badgesLink = document.createElement('a');
        badgesLink.href = 'badges.html';
        badgesLink.textContent = 'Alle Abzeichen ansehen';
        badgesLink.className = 'button';
        badgesLink.style.marginTop = '20px';
        badgesLink.style.display = 'inline-block';
        statsContainer.appendChild(badgesLink);
    }

    function ensureFirstStepsCard() {
        let container = document.getElementById('first-steps-card');
        if (container) return container;
        container = document.createElement('div');
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

    async function updateFirstStepsCardState(cardElement) {
        if (!cardElement) return;
        const medCta = document.getElementById('first-steps-med-cta');
        const entryCta = document.getElementById('first-steps-entry-cta');
        let hasMedications = false;
        let hasEntries = false;
        try {
            const [medData, entryData] = await Promise.all([
                utils.apiCall('/api/medications').catch(() => ({ medications: [] })),
                utils.apiCall('/api/entries?limit=1').catch(() => ({ entries: [] }))
            ]);
            hasMedications = Array.isArray(medData.medications) && medData.medications.length > 0;
            hasEntries = Array.isArray(entryData.entries) && entryData.entries.length > 0;
        } catch (error) {
            (window.FokusLog?.utils?.error || (() => {}))('Fehler bei der Aktualisierung der Erste-Schritte-Kacheln:', error);
        }

        const showMedCta = !hasMedications;
        const showEntryCta = !hasEntries;
        const hideCard = !showMedCta && !showEntryCta;

        if (medCta) {
            medCta.classList.toggle('hidden', !showMedCta);
        }
        if (entryCta) {
            entryCta.classList.toggle('hidden', !showEntryCta);
        }
        cardElement.style.display = hideCard ? 'none' : 'block';
    }
})(window);
