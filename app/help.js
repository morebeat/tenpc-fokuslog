document.addEventListener('DOMContentLoaded', () => {
    const buttons = document.querySelectorAll('.mode-toggle button');
    const sections = document.querySelectorAll('.help-section');

    buttons.forEach(btn => {
        btn.addEventListener('click', () => {
            // 1. Buttons umschalten
            buttons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            // 2. Modus auslesen (alltag vs wissen)
            const mode = btn.dataset.mode;

            // 3. Sektionen umschalten
            sections.forEach(section => {
                if (section.classList.contains(mode)) {
                    section.classList.remove('hidden');
                } else {
                    section.classList.add('hidden');
                }
            });
        });
    });
});