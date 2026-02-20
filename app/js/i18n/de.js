/**
 * FokusLog — Deutsche Übersetzungen (i18n/de.js)
 *
 * Befüllt FokusLog.i18n mit deutschen Strings.
 * Zugriff via: FokusLog.utils.t('schluessel')
 * Mit Platzhaltern: FokusLog.utils.t('error.http', { status: 404 })
 *
 * Einbinden in HTML vor app.js:
 *   <script src="js/i18n/de.js"></script>
 */
(function (global) {
    const FokusLog = global.FokusLog || (global.FokusLog = {});
    FokusLog.i18n = Object.assign(FokusLog.i18n || {}, {

        // Allgemeine Fehler
        'error.network':          'Netzwerkfehler. Bitte Verbindung prüfen.',
        'error.unauthorized':     'Nicht angemeldet. Bitte einloggen.',
        'error.forbidden':        'Zugriff verweigert.',
        'error.not_found':        'Ressource nicht gefunden.',
        'error.server':           'Serverfehler. Bitte später erneut versuchen.',
        'error.http':             'Fehler {status}: {message}',
        'error.load_page':        'Die Seite konnte nicht geladen werden. Bitte neu laden.',
        'error.load_timeout':     'Zeitüberschreitung beim Laden der Seite.',

        // Authentifizierung
        'auth.login_success':     'Erfolgreich angemeldet.',
        'auth.logout_success':    'Abgemeldet.',
        'auth.session_expired':   'Sitzung abgelaufen. Bitte erneut einloggen.',

        // Einträge
        'entry.saved':            'Eintrag gespeichert.',
        'entry.deleted':          'Eintrag gelöscht.',
        'entry.error_save':       'Fehler beim Speichern des Eintrags.',
        'entry.error_delete':     'Fehler beim Löschen des Eintrags.',
        'entry.future_date':      'Einträge für zukünftige Tage sind nicht möglich.',

        // Medikamente
        'meds.added':             'Medikament hinzugefügt.',
        'meds.updated':           'Medikament aktualisiert.',
        'meds.deleted':           'Medikament gelöscht.',
        'meds.error':             'Fehler bei der Medikamentenverwaltung.',

        // Benutzer
        'user.updated':           'Profil aktualisiert.',
        'user.password_changed':  'Passwort erfolgreich geändert.',
        'user.created':           'Benutzer {username} angelegt.',
        'user.deleted':           'Benutzer gelöscht.',

        // Benachrichtigungen
        'notif.push_enabled':     'Push-Benachrichtigungen aktiviert.',
        'notif.push_disabled':    'Push-Benachrichtigungen deaktiviert.',
        'notif.email_saved':      'E-Mail-Einstellungen gespeichert.',
        'notif.verify_sent':      'Bestätigungs-E-Mail wurde gesendet.',

        // Allgemein
        'loading':                'Wird geladen…',
        'save':                   'Speichern',
        'cancel':                 'Abbrechen',
        'delete':                 'Löschen',
        'confirm_delete':         'Wirklich löschen?',
    });
})(window);
