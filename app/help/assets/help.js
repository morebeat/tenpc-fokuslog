document.addEventListener('DOMContentLoaded', () => {
    console.log('FokusLog Hilfe-System geladen.');

    const initModeToggle = () => {
        const modeToggle = document.querySelector('.mode-toggle');
        if (!modeToggle) {
            return;
        }

        const buttons = modeToggle.querySelectorAll('button[data-mode]');
        if (!buttons.length) {
            return;
        }

        const sections = document.querySelectorAll('.help-section');
        const availableModes = new Set();
        sections.forEach(section => {
            section.classList.forEach(cls => availableModes.add(cls));
        });

        const setMode = (mode) => {
            if (!mode || !availableModes.has(mode)) {
                return;
            }

            sections.forEach(section => {
                section.classList.toggle('hidden', !section.classList.contains(mode));
            });

            buttons.forEach(button => {
                button.classList.toggle('active', button.dataset.mode === mode);
            });

            localStorage.setItem('helpMode', mode);
        };

        buttons.forEach(button => {
            button.addEventListener('click', () => setMode(button.dataset.mode));
        });

        setMode(localStorage.getItem('helpMode') || buttons[0].dataset.mode);
    };

    const initOverlay = () => {
        const overlay = document.getElementById('help-overlay');
        if (!overlay) {
            return;
        }

        const overlayText = document.getElementById('overlay-text');
        const overlayMore = document.getElementById('overlay-more');

        // Container für Lexikon-Daten
        let helpContent = {};

        // Daten dynamisch aus der Datenbank laden
        fetch('/api/glossary')
            .then(response => response.json())
            .then(data => {
                if (data.glossary) {
                    data.glossary.forEach(item => {
                        helpContent[item.slug] = {
                            text: item.content,
                            link: item.link
                        };
                    });
                }
            })
            .catch(err => console.error('Fehler beim Laden des Lexikons:', err));

        const openOverlay = (key) => {
            const content = helpContent[key];
            if (!content || !overlayText) {
                return;
            }

            overlayText.textContent = content.text;

            if (overlayMore && content.link) {
                overlayMore.href = content.link;
                overlayMore.classList.remove('hidden');
            } else if (overlayMore) {
                overlayMore.classList.add('hidden');
            }

            overlay.classList.remove('hidden');
        };

        const closeOverlay = () => {
            overlay.classList.add('hidden');
        };

        window.closeOverlay = closeOverlay;

        document.querySelectorAll('.help-icon[data-help]').forEach(icon => {
            icon.addEventListener('click', (event) => {
                openOverlay(event.currentTarget.dataset.help);
            });
        });

        overlay.addEventListener('click', (event) => {
            if (event.target === overlay) {
                closeOverlay();
            }
        });
    };

    const initTreeControls = () => {
        const tree = document.querySelector('.help-tree');
        if (!tree) {
            return;
        }

        const detailNodes = tree.querySelectorAll('details');
        const buttons = tree.querySelectorAll('[data-tree-action]');

        const toggleAll = (expanded) => {
            detailNodes.forEach(node => {
                node.open = expanded;
            });
        };

        buttons.forEach(button => {
            button.addEventListener('click', () => {
                if (button.dataset.treeAction === 'expand') {
                    toggleAll(true);
                }
                if (button.dataset.treeAction === 'collapse') {
                    toggleAll(false);
                }
            });
        });
    };

    initModeToggle();
    initOverlay();
    initTreeControls();
});