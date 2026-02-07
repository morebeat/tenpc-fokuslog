document.addEventListener('DOMContentLoaded', () => {
    console.log('FokusLog Hilfe-System geladen.');

    const modeToggle = document.querySelector('.mode-toggle');
    if (modeToggle) {
        const buttons = modeToggle.querySelectorAll('button');
        const sections = document.querySelectorAll('.help-section');

        const setMode = (mode) => {
            if (!mode) return; // Nichts tun, wenn kein Modus da ist
            sections.forEach(sec => {
                sec.classList.toggle('hidden', !sec.classList.contains(mode));
            });
            buttons.forEach(btn => {
                btn.classList.toggle('active', btn.dataset.mode === mode);
            });
            localStorage.setItem('helpMode', mode);
        };

        buttons.forEach(btn => {
            btn.addEventListener('click', () => setMode(btn.dataset.mode));
        });

        // Gespeicherten Modus wiederherstellen oder Standard ('alltag')
        setMode(localStorage.getItem('helpMode') || 'alltag');
    }

    // --- Overlay functionality for fokus.html ---
    const overlay = document.getElementById('help-overlay');
    if (overlay) {
        const overlayText = document.getElementById('overlay-text');
        const overlayMore = document.getElementById('overlay-more');

        const helpContent = {
            steuerbarkeit: {
                text: 'Steuerbarkeit bedeutet, die Aufmerksamkeit bewusst auf eine Sache lenken zu können, auch wenn sie langweilig ist, und Ablenkungen widerstehen zu können. Es geht nicht darum, wie stark man sich für etwas Interessantes konzentrieren kann (Hyperfokus).',
            },
            wirkdauer: {
                text: 'Die Wirkung von Medikamenten lässt über den Tag nach. Ein Abfall der Konzentration am Nachmittag kann auf das Wirkende oder einen Rebound hindeuten. Beides wird auf der Seite zu Nebenwirkungen genauer erklärt.',
                link: 'help/nebenwirkungen.html#rebound-effekt'
            },
            widerspruch: {
                text: 'Ein guter Fokus bei gleichzeitig schlechter Stimmung kann ein Zeichen für eine zu hohe Dosis sein ("Tunnelblick"). Die Person ist zwar ruhig, fühlt sich aber unwohl oder "roboterhaft". Mehr dazu unter Nebenwirkungen.',
                link: 'help/nebenwirkungen.html#stimmungsschwankungen'
            },
            schwankungen: {
                text: 'ADHS-Symptome sind nicht jeden Tag gleich. Stress, Schlaf, Ernährung und das allgemeine Befinden haben einen großen Einfluss auf die Konzentrationsfähigkeit, auch mit Medikation.'
            },
            einzeltage: {
                text: 'Ein einzelner schlechter Tag ist selten aussagekräftig. Wichtig für die ärztliche Beurteilung sind Muster und Trends über einen längeren Zeitraum (z.B. eine Woche).'
            }
        };

        const openOverlay = (key) => {
            const content = helpContent[key];
            if (!content) return;

            overlayText.textContent = content.text;

            if (content.link) {
                overlayMore.href = content.link;
                overlayMore.classList.remove('hidden');
            } else {
                overlayMore.classList.add('hidden');
            }

            overlay.classList.remove('hidden');
        };

        const closeOverlay = () => {
            overlay.classList.add('hidden');
        };

        // Make closeOverlay globally available for the inline onclick attribute in fokus.html
        window.closeOverlay = closeOverlay;

        // Attach event listeners to all help icons
        document.querySelectorAll('.help-icon[data-help]').forEach(icon => {
            icon.addEventListener('click', (e) => {
                openOverlay(e.target.dataset.help);
            });
        });

        // Allow closing overlay by clicking the background
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                closeOverlay();
            }
        });
    }
});