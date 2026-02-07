/**
 * Gamification-Logik für FokusLog
 * 
 * Integration:
 * 1. Library einbinden: <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.2/dist/confetti.browser.min.js"></script>
 * 2. Dieses Script einbinden: <script src="gamification.js"></script>
 * 3. Aufruf nach Fetch: handleGamification(response.gamification);
 */

/**
 * Feuert den Konfetti-Effekt ab (3 Sekunden lang).
 */
function triggerConfetti() {
    if (typeof confetti === 'undefined') {
        console.warn('Konfetti-Library nicht geladen.');
        return;
    }

    const duration = 3000;
    const end = Date.now() + duration;

    (function frame() {
        // Konfetti von links und rechts einfliegen lassen
        confetti({
            particleCount: 5,
            angle: 60,
            spread: 55,
            origin: { x: 0 },
            colors: ['#2563eb', '#fbbf24', '#ef4444'] // Blau, Gelb, Rot
        });
        confetti({
            particleCount: 5,
            angle: 120,
            spread: 55,
            origin: { x: 1 },
            colors: ['#2563eb', '#fbbf24', '#ef4444']
        });

        if (Date.now() < end) {
            requestAnimationFrame(frame);
        }
    }());
}

/**
 * Zeigt ein Modal mit den gewonnenen Badges an.
 */
function showBadgeModal(badges) {
    let modal = document.getElementById('gamification-modal');
    
    // Modal dynamisch erstellen, falls noch nicht vorhanden
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'gamification-modal';
        modal.className = 'modal';
        modal.style.display = 'none';
        document.body.appendChild(modal);
        
        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.style.display = 'none';
        });
    }

    const badgesHtml = badges.map(b => `
        <div class="badge-item">
            <div class="badge-icon animated">${b.icon_class || '🏆'}</div>
            <h2 style="color: #ea580c; margin: 0.5rem 0;">${b.name}</h2>
            <p>${b.description}</p>
        </div>
    `).join('<hr style="margin: 1rem 0; opacity: 0.3;">');

    modal.innerHTML = `
        <div class="modal-content gamification-badge-earned">
            <span class="close-button" onclick="document.getElementById('gamification-modal').style.display='none'">&times;</span>
            <h3 style="margin-top:0;">Herzlichen Glückwunsch!</h3>
            ${badgesHtml}
            <button class="button" style="margin-top:1rem;" onclick="document.getElementById('gamification-modal').style.display='none'">Weiter so!</button>
        </div>
    `;

    modal.style.display = 'flex';
}

/**
 * Hauptfunktion: Verarbeitet die API-Antwort
 */
function handleGamification(data) {
    if (!data) return;

    if (data.new_badges && data.new_badges.length > 0) {
        triggerConfetti();
        showBadgeModal(data.new_badges);
    }
    
    // Optional: Streak und Progress Bar aktualisieren
    if (data.streak !== undefined) {
        const streakEl = document.querySelector('.streak-counter');
        if (streakEl) {
            streakEl.innerHTML = `<span class="streak-fire">🔥</span> ${data.streak} Tage`;
            streakEl.classList.add('updated');
        }
    }

    if (data.next_badge && data.streak) {
        const progressEl = document.querySelector('.badge-progress-bar');
        const progressText = document.querySelector('.badge-progress-text');
        
        if (progressEl) {
            const percent = Math.min(100, (data.streak / data.next_badge.required_streak) * 100);
            progressEl.style.width = `${percent}%`;
        }
        
        if (progressText) {
            progressText.innerHTML = `Noch <strong>${data.next_badge.days_left} Tage</strong> bis zum <strong>${data.next_badge.name}</strong>!`;
        }
    }
}