import { test, expect } from '@playwright/test';
import { uniqueUsername } from './utils';

test.describe('Registrierung', () => {
    let createdUser: { username: string, password: string } | null = null;

    test.afterEach(async ({ request }) => {
        if (createdUser) {
            // 1. Login via API um Session zu bekommen
            const loginResponse = await request.post('/api/login', {
                data: createdUser
            });

            if (loginResponse.ok()) {
                // 2. Eigene ID abrufen
                const meResponse = await request.get('/api/me');
                const me = await meResponse.json();

                // 3. Benutzer löschen
                await request.delete(`/api/users/${me.id}`);
                console.log(`Cleanup: Benutzer ${createdUser.username} gelöscht.`);
            }
            createdUser = null;
        }
    });

    test('Neuer Benutzer kann sich erfolgreich registrieren', async ({ page }) => {
        // 1. Eindeutigen Benutzernamen mit der Utility-Funktion generieren
        const username = uniqueUsername('testuser');
        const password = 'SecurePassword123!';
        
        // Für Cleanup speichern
        createdUser = { username, password };

        // 2. Zur Registrierungsseite navigieren
        await page.goto('/register.html');

        // 3. Formular ausfüllen
        // Falls Account-Typ-Auswahl existiert (Individual/Family)
        const typeRadio = page.locator('input[name="account_type"][value="individual"]');
        if (await typeRadio.isVisible()) {
            await typeRadio.check();
        }

        await page.fill('input[name="username"]', username);
        await page.fill('input[name="password"]', password);
        
        // Datenschutz-Checkbox akzeptieren (wird von auth.js dynamisch generiert)
        await page.check('#privacy_accepted');

        // 4. Alert-Dialog automatisch akzeptieren (kommt bei Erfolg)
        page.on('dialog', async dialog => {
            await dialog.accept();
        });

        // 5. Formular absenden
        await page.click('button[type="submit"], input[type="submit"]');

        // 6. Erwartung: Weiterleitung zur Login-Seite
        await expect(page).toHaveURL(/.*login\.html/);
    });
});