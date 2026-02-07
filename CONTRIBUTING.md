# Mitwirken an FokusLog

Vielen Dank für dein Interesse, an FokusLog mitzuarbeiten! Wir freuen uns über jede Hilfe, die das Projekt besser macht.

## Leitprinzipien

*   **Respekt:** Wir achten auf unsere Zielgruppen (Kinder, Familien, Lehrkräfte).
*   **Kleine Schritte:** Änderungen sollten klein, verständlich und fokussiert sein.
*   **Klarheit vor Cleverness:** Lesbarer Code ist wichtiger als "smarte" Einzeiler.

## Wie kann ich beitragen?

Wir freuen uns besonders über:
*   🐛 **Bugfixes** (Fehlerbehebungen)
*   ♿ **Barrierefreiheit** (Accessibility Improvements)
*   📝 **Dokumentation** (Verbesserungen an Texten und Anleitungen)
*   🎨 **UX-Optimierungen** (Benutzerfreundlichkeit)

Bitte vermeide:
*   Riesige, unkoordinierte Feature-Updates (bitte vorher ein Issue öffnen, um die Idee zu besprechen).
*   Komplette Rewrites in anderen Frameworks.
*   Änderungen, die den Datenschutz oder die Barrierefreiheit verschlechtern.

## Der Pull Request Prozess (Schritt für Schritt)

Wir nutzen den Standard-GitHub-Workflow. So reichst du deine Änderungen ein:

1.  **Forken**:
    Klicke oben rechts auf "Fork", um eine Kopie des Repositories in deinem GitHub-Account zu erstellen.

2.  **Klonen**:
    Lade deinen Fork auf deinen lokalen Rechner herunter.
    ```bash
    git clone https://github.com/DEIN_USER/fokuslog-app.git
    cd fokuslog-app
    ```

3.  **Branch erstellen**:
    Erstelle einen neuen Branch für deine Änderung. Wähle einen sprechenden Namen (z. B. `fix/login-error` oder `feat/dark-mode`).
    ```bash
    git checkout -b feat/mein-neues-feature
    ```

4.  **Änderungen implementieren**:
    Nimm deine Änderungen vor. Achte darauf, dass der Code sauber und verständlich bleibt.

5.  **Testen**:
    *   Führe, wenn möglich, die API-Tests aus (`php api/run_tests.php`).
    *   Prüfe deine Änderungen manuell im Browser.

6.  **Committen & Pushen**:
    ```bash
    git add .
    git commit -m "feat: Beschreibe kurz, was du getan hast"
    git push origin feat/mein-neues-feature
    ```

7.  **Pull Request (PR) öffnen**:
    *   Gehe auf GitHub zu deinem Fork oder zum Original-Repository.
    *   Du solltest einen Hinweis sehen: "Compare & pull request".
    *   Fülle das PR-Formular aus. Beschreibe **was** du geändert hast und **warum**.
    *   Füge Screenshots hinzu, falls du die Benutzeroberfläche geändert hast.

## Code-Richtlinien

*   **Sprache**: Wir nutzen Deutsch für Dokumentation/Issues und Englisch für Code/Kommentare.
*   **Stil**:
    *   **PHP**: Orientierung an PSR-12.
    *   **JS**: ES6+, Semikolons verwenden, klare Variablennamen.
*   **Keine "Magie"**: Code sollte explizit und nachvollziehbar sein.

## Lizenz

Mit dem Einreichen eines Pull Requests stimmst du zu, dass deine Beiträge unter der **CC BY-NC-SA 4.0** Lizenz des Projekts veröffentlicht werden.