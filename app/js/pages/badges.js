(function (global) {
    const FokusLog = global.FokusLog || (global.FokusLog = {});
    const pages = FokusLog.pages || (FokusLog.pages = {});

    pages.badges = {
        init: async ({ user, utils }) => {
            const main = document.querySelector('main');
            if (!main) return;

            main.innerHTML = '<div class="loading-spinner">Lade Erfolge...</div>';

            try {
                const response = await fetch('/api/badges');
                if (!response.ok) throw new Error('Fehler beim Laden der Badges');
                const data = await response.json();
                renderBadgesPage(main, data);
            } catch (error) {
                console.error(error);
                main.innerHTML = `<div class="error-message">Konnte Abzeichen nicht laden: ${error.message}</div>`;
            }
        }
    };

    function renderBadgesPage(container, data) {
        const { badges, current_streak, points } = data;

        // Mapping für Icons (analog zum Dashboard)
        const badgeIcons = { 
            'badge-bronze': '🥉', 
            'badge-silver': '🥈', 
            'badge-gold': '🥇', 
            'badge-platinum': '🏆' 
        };

        const badgesHtml = badges.map(badge => {
            const isEarned = badge.earned;
            // WICHTIG: Hier setzen wir die Klasse für das Styling
            const statusClass = isEarned ? 'earned' : 'locked';
            const icon = badgeIcons[badge.icon_class] || '🏅';
            
            // Datum formatieren, falls vorhanden
            let metaInfo = '';
            if (isEarned && badge.earned_at) {
                const date = new Date(badge.earned_at).toLocaleDateString('de-DE');
                metaInfo = `<div class="badge-meta earned-date">Erhalten am ${date}</div>`;
            } else {
                metaInfo = `<div class="badge-meta requirement">Benötigt ${badge.required_streak} Tage Streak</div>`;
            }

            return `
                <div class="badge-card ${statusClass}">
                    <div class="badge-icon">${icon}</div>
                    <div class="badge-content">
                        <h3 class="badge-title">${badge.name}</h3>
                        <p class="badge-desc">${badge.description}</p>
                        ${metaInfo}
                    </div>
                    ${isEarned ? '<div class="check-mark">✓</div>' : '<div class="lock-icon">🔒</div>'}
                </div>
            `;
        }).join('');

        // Nächstes Ziel berechnen
        const nextBadge = badges.find(b => !b.earned);
        let progressHtml = '';
        
        if (nextBadge) {
            const percent = Math.min(100, Math.max(0, (current_streak / nextBadge.required_streak) * 100));
            const daysLeft = Math.max(0, nextBadge.required_streak - current_streak);
            
            progressHtml = `
                <div class="next-badge-card">
                    <div class="next-badge-header">
                        <span>Nächstes Ziel: <strong>${nextBadge.name}</strong></span>
                        <span class="days-left">Noch ${daysLeft} Tage</span>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: 0%" data-width="${percent}%"></div>
                    </div>
                    <div class="progress-stats">
                        <span>${current_streak} / ${nextBadge.required_streak} Tage Streak</span>
                    </div>
                </div>
            `;
        } else {
            progressHtml = `
                <div class="next-badge-card completed">
                    <strong>🎉 Alle Abzeichen verdient! Du bist unglaublich!</strong>
                </div>
            `;
        }

        container.innerHTML = `
            <div class="badges-header">
                <h1>Deine Erfolge</h1>
                <div class="stats-summary">
                    <div class="stat-box">
                        <span class="stat-value">${points}</span>
                        <span class="stat-label">Punkte</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-value">🔥 ${current_streak}</span>
                        <span class="stat-label">Tage Streak</span>
                    </div>
                </div>
                ${progressHtml}
            </div>
            
            <div class="badges-grid">
                ${badgesHtml}
            </div>
            <div style="margin-top: 2rem; text-align: center;"><a href="index.html" class="button">Zurück zum Dashboard</a></div>
        `;

        // Animation der Progress-Bar starten
        setTimeout(() => {
            const bar = container.querySelector('.progress-bar-fill');
            if (bar) {
                bar.style.width = bar.dataset.width;
            }
        }, 100);
    }
})(window);