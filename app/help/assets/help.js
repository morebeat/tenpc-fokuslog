document.addEventListener('DOMContentLoaded', () => {
    // Debug nur in Development (FOKUSLOG_DEBUG flag)
    const log = (...args) => {
        if (window.FOKUSLOG_DEBUG) console.log('[Help]', ...args);
    };
    log('FokusLog Hilfe-System geladen.');

    // ─── Help Search ──────────────────────────────────────────────────────────────
    const initSearch = () => {
        const searchInput = document.getElementById('help-search');
        const resultsContainer = document.getElementById('help-search-results');
        if (!searchInput || !resultsContainer) return;

        // Index aus allen Links auf der Seite erstellen
        const searchIndex = [];
        document.querySelectorAll('.tree-links a, .nav-list a, .help-grid a').forEach(link => {
            const title = link.textContent.trim();
            const href = link.getAttribute('href');
            if (!href) return;
            const hrefLower = href.trim().toLowerCase();
            if (
                hrefLower.startsWith('#') ||
                hrefLower.startsWith('javascript:') ||
                hrefLower.startsWith('data:') ||
                hrefLower.startsWith('vbscript:')
            ) {
                return;
            }

            // Kategorie aus übergeordnetem Element ermitteln
            const categoryEl = link.closest('.tree-level, .grid-item, details');
            let category = '';
            if (categoryEl) {
                const summaryEl = categoryEl.querySelector('summary, h3');
                if (summaryEl) category = summaryEl.textContent.trim();
            }

            searchIndex.push({ title, href, category });
        });

        // Duplikate entfernen (nach href)
        const uniqueIndex = [];
        const seenHrefs = new Set();
        searchIndex.forEach(item => {
            if (!seenHrefs.has(item.href)) {
                seenHrefs.add(item.href);
                uniqueIndex.push(item);
            }
        });

        log('Search Index erstellt:', uniqueIndex.length, 'Artikel');

        // Suche durchführen
        const search = (query) => {
            if (!query || query.length < 2) return [];

            const normalizedQuery = query.toLowerCase().trim();
            const terms = normalizedQuery.split(/\s+/).filter(t => t.length >= 2);
            if (terms.length === 0) return [];

            return uniqueIndex
                .map(item => {
                    const titleLower = item.title.toLowerCase();
                    const categoryLower = item.category.toLowerCase();
                    let score = 0;

                    terms.forEach(term => {
                        // Exakter Match im Titel = höchste Gewichtung
                        if (titleLower.includes(term)) score += 10;
                        // Match am Wortanfang
                        if (titleLower.startsWith(term) || titleLower.includes(' ' + term)) score += 5;
                        // Match in Kategorie
                        if (categoryLower.includes(term)) score += 3;
                    });

                    return { ...item, score };
                })
                .filter(item => item.score > 0)
                .sort((a, b) => b.score - a.score)
                .slice(0, 10);
        };

        // Ergebnis-HTML erstellen
        const highlightText = (text, terms) => {
            let result = text;
            terms.forEach(term => {
                const regex = new RegExp(`(${term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
                result = result.replace(regex, '<mark>$1</mark>');
            });
            return result;
        };

        const renderResults = (results, query) => {
            const terms = query.toLowerCase().split(/\s+/).filter(t => t.length >= 2);

            if (results.length === 0) {
                resultsContainer.innerHTML = '<div class="help-search-no-results">Keine Ergebnisse gefunden</div>';
                resultsContainer.classList.remove('hidden');
                return;
            }

            resultsContainer.innerHTML = results.map(item => `
                <a href="${item.href}" class="help-search-result" role="option">
                    <div class="help-search-result-title">${highlightText(item.title, terms)}</div>
                    ${item.category ? `<div class="help-search-result-category">${item.category}</div>` : ''}
                </a>
            `).join('');
            resultsContainer.classList.remove('hidden');
        };

        const hideResults = () => {
            resultsContainer.classList.add('hidden');
        };

        // Event Listeners
        let debounceTimer;
        searchInput.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                const query = searchInput.value.trim();
                if (query.length >= 2) {
                    const results = search(query);
                    renderResults(results, query);
                } else {
                    hideResults();
                }
            }, 150);
        });

        // Escape schließt Ergebnisse
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                hideResults();
                searchInput.blur();
            }
            // Pfeil-Navigation
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                const firstResult = resultsContainer.querySelector('.help-search-result');
                if (firstResult) firstResult.focus();
            }
        });

        // Klick außerhalb schließt Ergebnisse
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.help-search-container')) {
                hideResults();
            }
        });

        // Keyboard-Navigation in Ergebnissen
        resultsContainer.addEventListener('keydown', (e) => {
            const focused = document.activeElement;
            if (!focused.classList.contains('help-search-result')) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                const next = focused.nextElementSibling;
                if (next) next.focus();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                const prev = focused.previousElementSibling;
                if (prev) prev.focus();
                else searchInput.focus();
            } else if (e.key === 'Escape') {
                hideResults();
                searchInput.focus();
            }
        });
    };

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

    initSearch();
    initModeToggle();
    initOverlay();
    initTreeControls();
});