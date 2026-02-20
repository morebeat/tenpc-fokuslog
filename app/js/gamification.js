(function (global) {
    const FokusLog = global.FokusLog || (global.FokusLog = {});
    const components = FokusLog.components || (FokusLog.components = {});

    components.gamification = {
        render: async (container, user, utils) => {
            if (!container || !user) return;

            // Responsive container styling
            container.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
            container.style.color = 'white';
            container.style.padding = '20px';
            container.style.borderRadius = '10px';
            container.style.boxShadow = '0 4px 6px rgba(0,0,0,0.1)';
            container.style.textAlign = 'center';
            container.setAttribute('role', 'region');
            container.setAttribute('aria-label', 'Gamification Status');

            // Fetch badge data for progress calculation
            let nextBadge = null;
            let progressPercent = 0;
            try {
                const response = await fetch('/api/badges');
                if (response.ok) {
                    const data = await response.json();
                    const badges = data.badges || [];
                    nextBadge = badges.find(b => !b.earned);
                    if (nextBadge) {
                        progressPercent = Math.min(100, Math.max(0, (data.current_streak / nextBadge.required_streak) * 100));
                    }
                }
            } catch (e) {
                console.error('Failed to load badge progress', e);
            }

            const points = user.points || 0;
            const streak = user.streak_current || 0;
            const badges = user.badges || [];
            const rank = user.rank_info ? user.rank_info.rank : null;

            // Badges HTML
            let badgesHtml = '<div class="badges-container" style="margin-top: 15px; display: flex; flex-wrap: wrap; gap: 10px; justify-content: center;">';
            if (badges.length > 0) {
                badges.forEach(badge => {
                    const badgeIcons = { 'badge-bronze': '🥉', 'badge-silver': '🥈', 'badge-gold': '🥇', 'badge-platinum': '🏆' };
                    const icon = badgeIcons[badge.icon_class] || '🏅';
                    badgesHtml += `<div class="badge" title="${utils.escapeHtml(badge.name)}: ${utils.escapeHtml(badge.description)}" tabindex="0" aria-label="Abzeichen: ${utils.escapeHtml(badge.name)}" style="font-size: 2.5em; cursor: help;">${icon}</div>`;
                });
            } else {
                badgesHtml += '<p style="font-size: 0.9em; opacity: 0.8;">Sammle weiter Einträge, um Abzeichen zu verdienen!</p>';
            }
            badgesHtml += '</div>';

            // Progress Bar HTML
            let progressHtml = '';
            if (nextBadge) {
                progressHtml = `
                    <div style="margin-top: 15px; text-align: left;">
                        <div style="font-size: 0.85rem; margin-bottom: 5px; display: flex; justify-content: space-between;">
                            <span>Nächstes Ziel: <strong>${utils.escapeHtml(nextBadge.name)}</strong></span>
                            <span>${streak} / ${nextBadge.required_streak} Tage</span>
                        </div>
                        <div style="background: rgba(255,255,255,0.3); border-radius: 10px; height: 10px; overflow: hidden;" role="progressbar" aria-valuenow="${progressPercent}" aria-valuemin="0" aria-valuemax="100" aria-label="Fortschritt zum nächsten Abzeichen">
                            <div style="background: #ffd700; width: ${progressPercent}%; height: 100%; transition: width 1s ease-in-out;"></div>
                        </div>
                    </div>
                `;
            }

            container.innerHTML = `
                <h3 style="margin: 0 0 15px 0; font-size: 1.3rem;">Dein Fortschritt</h3>
                <div style="display: flex; justify-content: space-around; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div style="font-size: 1.2em; font-weight: bold;" title="Gesamtpunkte">⭐ ${points} Punkte</div>
                    <div style="font-size: 1.2em; font-weight: bold;" title="Aktueller Streak">🔥 ${streak} Tage Streak</div>
                    ${rank ? `<div style="font-size: 1.2em; font-weight: bold;" title="Aktueller Rang">🏆 Platz ${rank}</div>` : ''}
                </div>
                ${progressHtml}
                ${badgesHtml}
                <a href="badges.html" class="button" style="margin-top: 20px; display: inline-block; background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.4);">Alle Abzeichen ansehen</a>
            `;

            // Trigger confetti if a new badge was just earned (simple check logic could be added here)
            if (global.confetti && user.new_badges && user.new_badges.length > 0) {
                global.confetti({ particleCount: 100, spread: 70, origin: { y: 0.6 } });
            }
        },

        notifyAchievements: (gamificationData, utils) => {
            if (!gamificationData || !gamificationData.points_earned || gamificationData.points_earned <= 0) return;

            const g = gamificationData;
            setTimeout(() => utils.toast(`+${g.points_earned} Punkte! Streak: ${g.streak} Tage 🔥`, 'gamification', 5000), 500);
            
            if (g.new_badges && g.new_badges.length > 0) {
                g.new_badges.forEach((badge, idx) => {
                    setTimeout(() => utils.toast(`🏆 Neues Abzeichen: ${badge.name}`, 'badge', 6000), 1000 + (idx * 500));
                });
            }

            if (global.confetti) {
                global.confetti({ particleCount: 100, spread: 70, origin: { y: 0.6 } });
            }
        },

        fetchRank: async (utils) => {
            try {
                return await utils.apiCall('/api/badges/rank');
            } catch (error) {
                console.error('Failed to fetch rank', error);
                return null;
            }
        }
    };
})(window);