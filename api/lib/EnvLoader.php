<?php

declare(strict_types=1);

namespace FokusLog;

/**
 * Robuster .env-Datei-Parser.
 *
 * Unterstützt:
 *  - KEY=VALUE (einfach)
 *  - KEY="Wert mit Sonderzeichen !@#$%"
 *  - KEY='Single-Quotes'
 *  - # Kommentare (ganzer Zeile und nach Wert mit Space)
 *  - Leere Zeilen werden übersprungen
 *  - export KEY=VALUE (Shell-Syntax wird toleriert)
 *
 * Warum kein parse_ini_file()? Der eingebaute Parser schlägt bei
 * Sonderzeichen wie '!' in unquotierten Werten fehl und gibt
 * PHP-Warnings aus (bekanntes PHP-Bug).
 */
class EnvLoader
{
    /**
     * Lädt eine .env-Datei und gibt ein assoziatives Array zurück.
     *
     * @param string $path Absoluter Pfad zur .env-Datei
     * @return array<string, string> Key-Value-Paare
     * @throws \RuntimeException wenn die Datei nicht gelesen werden kann
     */
    public static function load(string $path): array
    {
        if (!is_file($path)) {
            throw new \RuntimeException("ENV-Datei nicht gefunden: {$path}");
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("ENV-Datei konnte nicht gelesen werden: {$path}");
        }

        return self::parse($content);
    }

    /**
     * Parst einen .env-String und gibt Key-Value-Paare zurück.
     *
     * @param string $content Inhalt einer .env-Datei
     * @return array<string, string>
     */
    public static function parse(string $content): array
    {
        $result = [];
        $lines = explode("\n", str_replace("\r\n", "\n", $content));

        foreach ($lines as $line) {
            $line = trim($line);

            // Leere Zeilen und Kommentare überspringen
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Optionales "export " Präfix entfernen
            if (str_starts_with($line, 'export ')) {
                $line = substr($line, 7);
            }

            // KEY=VALUE splitten (nur beim ersten '=')
            $eqPos = strpos($line, '=');
            if ($eqPos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $eqPos));
            $rawValue = substr($line, $eqPos + 1);

            // Nur gültige Schlüsselnamen akzeptieren
            if (!preg_match('/^[A-Z_][A-Z0-9_]*$/i', $key)) {
                continue;
            }

            $result[$key] = self::parseValue($rawValue);
        }

        return $result;
    }

    /**
     * Parst einen einzelnen Wert und entfernt Quotes und Inline-Kommentare.
     */
    private static function parseValue(string $raw): string
    {
        $raw = trim($raw);

        // Doppelt-gequoteter Wert: "wert" — alles dazwischen literal nehmen
        if (strlen($raw) >= 2 && $raw[0] === '"' && str_ends_with($raw, '"')) {
            return substr($raw, 1, -1);
        }

        // Einfach-gequoteter Wert: 'wert'
        if (strlen($raw) >= 2 && $raw[0] === "'" && str_ends_with($raw, "'")) {
            return substr($raw, 1, -1);
        }

        // Kein Quote: Inline-Kommentar (#) nach Leerzeichen entfernen
        $commentPos = strpos($raw, ' #');
        if ($commentPos !== false) {
            $raw = substr($raw, 0, $commentPos);
        }

        return trim($raw);
    }
}
