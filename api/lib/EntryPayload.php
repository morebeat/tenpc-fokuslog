<?php

declare(strict_types=1);

use DateTimeImmutable;
use InvalidArgumentException;

final class EntryPayload
{
    private const DATE_ERROR = 'UngÃ¼ltiges oder fehlendes Datum';
    private const FUTURE_ERROR = 'EintrÃ¤ge in der Zukunft sind nicht erlaubt.';
    private const VALID_TIMES = ['morning', 'noon', 'evening'];

    public static function normalizeDate(string $value, ?DateTimeImmutable $today = null): string
    {
        $value = self::sanitizeDateValue($value);

        if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new InvalidArgumentException(self::DATE_ERROR);
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date) {
            throw new InvalidArgumentException(self::DATE_ERROR);
        }

        $today = $today ?? new DateTimeImmutable('today');
        if ($date > $today) {
            throw new InvalidArgumentException(self::FUTURE_ERROR);
        }

        return $date->format('Y-m-d');
    }

    public static function normalizeTime(string $value): string
    {
        $value = trim($value);
        if (!in_array($value, self::VALID_TIMES, true)) {
            throw new InvalidArgumentException('UngÃ¼ltiger time Slot');
        }

        return $value;
    }

    public static function intOrNull($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (int)$value;
    }

    public static function decimalOrNull($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = str_replace(',', '.', (string)$value);
        if (!is_numeric($normalized)) {
            return null;
        }

        return number_format((float)$normalized, 2, '.', '');
    }

    public static function normalizeMedicationId($value): ?int
    {
        $int = self::intOrNull($value);
        if ($int === null || $int <= 0) {
            return null;
        }

        return $int;
    }

    private static function sanitizeDateValue(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        // Entferne unsichtbare Steuerzeichen und geschÃ¼tzte Leerzeichen
        $value = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{202A}\x{202C}\x{2060}\x{00A0}]/u', '', $value) ?? $value;

        // Ersetze sprachspezifische oder typografische Bindestriche durch ASCII-
        $value = str_replace([
            "\xE2\x80\x90", // HYPHEN
            "\xE2\x80\x91", // NON-BREAKING HYPHEN
            "\xE2\x80\x92", // FIGURE DASH
            "\xE2\x80\x93", // EN DASH
            "\xE2\x80\x94", // EM DASH
            "\xE2\x80\x95", // HORIZONTAL BAR
            "\xE2\x88\x92", // MINUS SIGN
            "\xEF\xBC\x8D", // FULLWIDTH HYPHEN-MINUS
            "\xEF\xB9\xA3", // SMALL HYPHEN-MINUS
        ], '-', $value);

        // Erlaube alternative Trenner
        $value = str_replace(['/', '.'], '-', $value);

        // ISO-Zeiten (2024-01-01T12:00:00Z) auf reinen Datumsanteil kÃ¼rzen
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $matches)) {
            $value = $matches[1];
        }

        return $value;
    }
}

