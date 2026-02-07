(function (global) {
    const FokusLog = global.FokusLog || (global.FokusLog = {});
    const pages = FokusLog.pages || (FokusLog.pages = {});

    pages.badges = {
        init: async () => {
            const container = document.getElementById('badges-container');
            if (!container) return;
            try {
                const response = await fetch('/api/badges');
                if (!response.ok) {
                    if (response.status === 401) window.location.href = 'login.html';
                    throw new Error('Fehler beim Laden der Abzeichen.');
                }
                const data = await response.json();
                container.innerHTML = '';
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
        }
    };
})(window);
