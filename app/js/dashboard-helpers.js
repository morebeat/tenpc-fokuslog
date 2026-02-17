(function (global) {
    const FokusLog = global.FokusLog || (global.FokusLog = {});
    const components = FokusLog.components || (FokusLog.components = {});

    components.dashboardHelpers = {
        /**
         * Renders the "First Steps" card if the user hasn't completed setup.
         * Checks for existing medications and entries.
         * @param {HTMLElement} container - The container to render into.
         * @param {Object} utils - Utility functions (apiCall, etc.).
         */
        renderFirstSteps: async (container, utils) => {
            if (!container) return;
            
            // Initial Loading State
            container.innerHTML = '<div class="loading-spinner" aria-label="Lade Vorschläge..."></div>';
            container.style.display = 'block';

            // Check status
            let hasMedications = false;
            let hasEntries = false;
            try {
                // Use utils.apiCall if available, otherwise fetch
                const medPromise = utils.apiCall ? utils.apiCall('/api/medications') : fetch('/api/medications').then(r => r.json());
                const entryPromise = utils.apiCall ? utils.apiCall('/api/entries?limit=1') : fetch('/api/entries?limit=1').then(r => r.json());

                const [medData, entryData] = await Promise.all([medPromise, entryPromise]);
                
                hasMedications = Array.isArray(medData.medications) && medData.medications.length > 0;
                hasEntries = Array.isArray(entryData.entries) && entryData.entries.length > 0;
            } catch (error) {
                console.error('Fehler bei der Aktualisierung der Erste-Schritte-Kacheln:', error);
                container.innerHTML = ''; // Hide on error
                container.style.display = 'none';
                return;
            }

            const showMedCta = !hasMedications;
            const showEntryCta = !hasEntries;

            if (!showMedCta && !showEntryCta) {
                container.style.display = 'none';
                container.innerHTML = '';
                return;
            }

            // Styling
            container.style.background = '#fff';
            container.style.padding = '20px';
            container.style.borderRadius = '10px';
            container.style.boxShadow = '0 4px 6px rgba(0,0,0,0.1)';
            container.style.borderLeft = '5px solid #4e8cff';
            container.style.marginBottom = '30px';
            container.setAttribute('role', 'region');
            container.setAttribute('aria-label', 'Erste Schritte');
            
            let html = `
                <h3 style="margin-top: 0; color: #013c4a;">👋 Willkommen bei FokusLog!</h3>
                <p>Hier sind die ersten Schritte, um loszulegen:</p>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-top: 20px;">
            `;

            if (showMedCta) {
                html += `
                    <a href="help/index.html?tab=setup" class="card-link" style="text-decoration: none; color: inherit; display: block;">
                        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb; height: 100%; transition: transform 0.2s; cursor: pointer;" role="button" tabindex="0">
                            <div style="font-size: 24px; margin-bottom: 10px;" aria-hidden="true">⚙️</div>
                            <strong style="font-size: 1.1rem;">Einrichtung starten</strong>
                            <p style="font-size: 0.9rem; color: #666; margin: 5px 0 0 0;">Medikamente & Profil konfigurieren.</p>
                        </div>
                    </a>
                `;
            }

            if (showEntryCta) {
                html += `
                    <a href="entry.html" class="card-link" style="text-decoration: none; color: inherit; display: block;">
                        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb; height: 100%; transition: transform 0.2s; cursor: pointer;" role="button" tabindex="0">
                            <div style="font-size: 24px; margin-bottom: 10px;" aria-hidden="true">📝</div>
                            <strong style="font-size: 1.1rem;">Ersten Eintrag erstellen</strong>
                            <p style="font-size: 0.9rem; color: #666; margin: 5px 0 0 0;">Erstelle deinen ersten Tageseintrag.</p>
                        </div>
                    </a>
                `;
            }

            html += `</div>`;
            container.innerHTML = html;
        },

        toggleAdminSection: (user) => {
            const adminSection = document.getElementById('admin-section');
            if (!adminSection) return;
            
            const canManage = user.role === 'parent' || user.role === 'adult';
            adminSection.style.display = canManage ? 'block' : 'none';
            adminSection.setAttribute('aria-hidden', canManage ? 'false' : 'true');
        }
    };
})(window);