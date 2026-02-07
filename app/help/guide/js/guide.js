/**
 * FokusLog Guide Logic
 * Handles navigation and rendering of markdown help files.
 */

const guideStructure = [
    { title: "Einführung", file: "README.md" },
    { title: "Erste Schritte", file: "00-erste-schritte.md" },
    { title: "Grundlagen ADHS", file: "01-grundlagen-adhs.md" },
    { title: "Warum Medikation?", file: "02-warum-medikation.md" },
    { title: "Medikamente Überblick", file: "03-medikamente-ueberblick.md" },
    { title: "Eindosierung & Verlauf", file: "04-eindosierung-und-verlauf.md" },
    { title: "Mythen & Vorurteile", file: "05-mythen-und-vorurteile.md" },
    { title: "Alltag, Ernährung & Sport", file: "06-alltag-ernaehrung-sport.md" },
    { title: "Reisen, Verkehr & Recht", file: "07-reisen-verkehr-recht.md" },
    { title: "Arztgespräch", file: "08-arztgespraech.md" },
    { 
        title: "Zielgruppen", 
        items: [
            { title: "Eltern", file: "zielgruppen/eltern.md" },
            { title: "Kinder & Jugendliche", file: "zielgruppen/kinder-und-jugendliche.md" },
            { title: "Lehrkräfte", file: "zielgruppen/lehrkraefte.md" },
            { title: "Erwachsene", file: "zielgruppen/erwachsene.md" },
            { title: "Frauen & ADHS", file: "zielgruppen/frauen-und-adhs.md" }
        ]
    },
    {
        title: "Anhang",
        items: [
            { title: "FAQ", file: "zielgruppen/anhang/faq.md" },
            { title: "Glossar", file: "zielgruppen/anhang/glossar.md" }
        ]
    }
];

let searchIndex = [];

document.addEventListener('DOMContentLoaded', () => {
    if (document.body.dataset.page === 'help') {
        initGuide();
    }
});

function initGuide() {
    const navContainer = document.getElementById('guide-nav');
    const contentContainer = document.getElementById('guide-content');
    
    setupMobileNav(navContainer);
    setupSearch(navContainer);
    renderNavigation(navContainer);
    
    // Load default or hash
    const initialFile = window.location.hash ? window.location.hash.substring(1) : 'README.md';
    loadGuidePage(initialFile);

    window.addEventListener('hashchange', () => {
        const file = window.location.hash.substring(1);
        loadGuidePage(file);
    });
}

function renderNavigation(container) {
    let html = '<ul class="nav-list">';
    guideStructure.forEach(section => {
        if (section.items) {
            html += `<li class="nav-item has-children">
                        <div class="nav-header">
                            <span>${section.title}</span>
                            <span class="nav-toggle-icon">›</span>
                        </div>
                        <ul class="nav-sub-list">`;
            section.items.forEach(item => {
                html += `<li><a href="#${item.file}" data-file="${item.file}">${item.title}</a></li>`;
            });
            html += '</ul></li>';
        } else {
            html += `<li class="nav-item"><a href="#${section.file}" data-file="${section.file}">${section.title}</a></li>`;
        }
    });
    html += '</ul>';
    container.innerHTML = html;

    // Event Listeners für Toggle
    container.querySelectorAll('.nav-header').forEach(header => {
        header.addEventListener('click', () => {
            const parentItem = header.parentElement;
            // Accordion: Wenn wir öffnen, schließen wir andere offene Menüs
            if (!parentItem.classList.contains('expanded')) {
                container.querySelectorAll('.nav-item.expanded').forEach(item => {
                    item.classList.remove('expanded');
                });
            }
            parentItem.classList.toggle('expanded');
        });
    });
}

async function loadGuidePage(filename) {
    const contentContainer = document.getElementById('guide-content');
    const links = document.querySelectorAll('.help-nav a');
    
    // Update Active State
    links.forEach(l => l.classList.remove('active'));
    const activeLink = document.querySelector(`.help-nav a[data-file="${filename}"]`);
    if (activeLink) {
        activeLink.classList.add('active');
        // Parent ausklappen, falls Sub-Item
        const subList = activeLink.closest('.nav-sub-list');
        if (subList) {
            const parentItem = subList.closest('.nav-item');
            if (parentItem) {
                // Accordion: Auch beim Laden eines Links andere Menüs schließen
                document.querySelectorAll('.nav-item.expanded').forEach(item => {
                    if (item !== parentItem) item.classList.remove('expanded');
                });
                parentItem.classList.add('expanded');
            }
        }
    }

    contentContainer.innerHTML = '<p>Lade Inhalte...</p>';

    try {
        const response = await fetch(`guide/${filename}`);
        if (!response.ok) throw new Error('Datei nicht gefunden');
        const text = await response.text();
        contentContainer.innerHTML = parseMarkdown(text);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (error) {
        contentContainer.innerHTML = `<p class="error">Fehler beim Laden: ${error.message}</p>`;
    }
}

/**
 * Simple Markdown Parser for the Guide
 * Supports: H1-H3, Lists, Bold, Paragraphs
 */
function parseMarkdown(markdown) {
    let html = markdown;
    
    // Headers
    html = html.replace(/^# (.*$)/gim, '<h1>$1</h1>');
    html = html.replace(/^## (.*$)/gim, '<h2>$1</h2>');
    html = html.replace(/^### (.*$)/gim, '<h3>$1</h3>');
    
    // Images
    html = html.replace(/!\[(.*?)\]\((.*?)\)/gim, '<img src="$2" alt="$1" class="guide-image">');
    
    // Lists
    html = html.replace(/^\s*-\s(.*$)/gim, '<li>$1</li>');
    html = html.replace(/(<li>.*<\/li>)/gim, '<ul>$1</ul>'); // Very basic wrapping, might need CSS fix
    
    // Formatting
    html = html.replace(/\*\*(.*)\*\*/gim, '<b>$1</b>');
    
    // Paragraphs (everything that isn't a tag)
    html = html.replace(/^([^<].*)/gim, '<p>$1</p>');
    
    return html;
}

function setupSearch(navContainer) {
    const searchInput = document.createElement('input');
    searchInput.type = 'search';
    searchInput.placeholder = 'Suche im Leitfaden...';
    searchInput.className = 'guide-search';
    searchInput.addEventListener('input', (e) => handleSearch(e.target.value));
    
    // Insert before the navigation container
    navContainer.parentNode.insertBefore(searchInput, navContainer);
    
    buildSearchIndex();
}

async function buildSearchIndex() {
    const queue = [];
    
    const traverse = (nodes, parentTitle = '') => {
        nodes.forEach(node => {
            if (node.file) {
                queue.push({ 
                    title: node.title, 
                    fullTitle: parentTitle ? `${parentTitle} > ${node.title}` : node.title,
                    file: node.file 
                });
            }
            if (node.items) {
                traverse(node.items, node.title);
            }
        });
    };

    traverse(guideStructure);

    const promises = queue.map(async (item) => {
        try {
            const response = await fetch(`guide/${item.file}`);
            if (response.ok) {
                const text = await response.text();
                return {
                    ...item,
                    content: text.toLowerCase()
                };
            }
        } catch (e) {
            console.error('Index error:', item.file);
        }
        return null;
    });

    const results = await Promise.all(promises);
    searchIndex = results.filter(item => item !== null);
}

function handleSearch(query) {
    const navContainer = document.getElementById('guide-nav');
    
    if (!query || query.length < 2) {
        renderNavigation(navContainer);
        return;
    }
    
    const q = query.toLowerCase();
    const results = searchIndex.filter(item => 
        item.fullTitle.toLowerCase().includes(q) || 
        item.content.includes(q)
    );
    
    if (results.length === 0) {
        navContainer.innerHTML = '<p class="search-no-results">Keine Treffer.</p>';
        return;
    }
    
    let html = '<ul class="search-results">';
    results.forEach(item => {
        html += `<li><a href="#${item.file}" data-file="${item.file}">${item.fullTitle}</a></li>`;
    });
    html += '</ul>';
    navContainer.innerHTML = html;
}

function setupMobileNav(navContainer) {
    const sidebar = navContainer.parentNode;
    
    // Toggle Button erstellen
    const toggleBtn = document.createElement('button');
    toggleBtn.className = 'nav-toggle';
    toggleBtn.innerHTML = '☰ Menü & Suche';
    
    // Button nach der Überschrift (h3) einfügen
    const h3 = sidebar.querySelector('h3');
    if (h3) {
        h3.insertAdjacentElement('afterend', toggleBtn);
    } else {
        sidebar.prepend(toggleBtn);
    }

    toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('expanded');
    });
    
    // Menü schließen, wenn ein Link geklickt wird (auf Mobile)
    navContainer.addEventListener('click', (e) => {
        if (e.target.tagName === 'A' && window.innerWidth <= 768) {
            sidebar.classList.remove('expanded');
        }
    });
}
