<?php

declare(strict_types=1);

namespace FokusLog;

/**
 * Eingabe-Validierung für API-Requests.
 *
 * Wirft eine ValidationException bei ungültigen Eingaben.
 * Alle Handler sollen diese Klasse nutzen anstatt ad-hoc trim()/empty()-Checks.
 *
 * Verwendung in Controllern:
 *   use FokusLog\Validator;
 *   use FokusLog\ValidationException;
 *
 *   try {
 *       $name = Validator::string($data, 'name', ['min' => 1, 'max' => 100]);
 *       $role = Validator::enum($data, 'role', ['parent', 'child', 'teacher', 'adult']);
 *   } catch (ValidationException $e) {
 *       $this->respond(400, ['error' => $e->getMessage()]);
 *   }
 */
class Validator
{
    /**
     * Pflichtfeld: String mit optionaler min/max-Länge.
     *
     * @param array<string, mixed> $data
     * @param array{min?: int, max?: int} $options
     */
    public static function string(array $data, string $field, array $options = []): string
    {
        if (!array_key_exists($field, $data)) {
            throw new ValidationException("Das Feld '{$field}' ist erforderlich.");
        }

        $value = trim((string)($data[$field] ?? ''));

        if ($value === '') {
            throw new ValidationException("Das Feld '{$field}' darf nicht leer sein.");
        }

        if (isset($options['min']) && strlen($value) < $options['min']) {
            throw new ValidationException(
                "Das Feld '{$field}' muss mindestens {$options['min']} Zeichen lang sein."
            );
        }

        if (isset($options['max']) && strlen($value) > $options['max']) {
            throw new ValidationException(
                "Das Feld '{$field}' darf maximal {$options['max']} Zeichen lang sein."
            );
        }

        return $value;
    }

    /**
     * Optionales Feld: String oder null, wenn nicht gesetzt oder leer.
     *
     * @param array<string, mixed> $data
     * @param array{max?: int} $options
     */
    public static function stringOptional(array $data, string $field, array $options = []): ?string
    {
        if (!array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
            return null;
        }

        $value = trim((string)$data[$field]);

        if (isset($options['max']) && strlen($value) > $options['max']) {
            throw new ValidationException(
                "Das Feld '{$field}' darf maximal {$options['max']} Zeichen lang sein."
            );
        }

        return $value;
    }

    /**
     * Pflichtfeld: Integer mit optionalem min/max-Bereich.
     *
     * @param array<string, mixed> $data
     * @param array{min?: int, max?: int} $options
     */
    public static function int(array $data, string $field, array $options = []): int
    {
        if (!array_key_exists($field, $data)) {
            throw new ValidationException("Das Feld '{$field}' ist erforderlich.");
        }

        if (!is_numeric($data[$field])) {
            throw new ValidationException("Das Feld '{$field}' muss eine ganze Zahl sein.");
        }

        $value = (int)$data[$field];

        if (isset($options['min']) && $value < $options['min']) {
            throw new ValidationException("Das Feld '{$field}' muss mindestens {$options['min']} sein.");
        }

        if (isset($options['max']) && $value > $options['max']) {
            throw new ValidationException("Das Feld '{$field}' darf maximal {$options['max']} sein.");
        }

        return $value;
    }

    /**
     * Optionales Feld: Integer oder null.
     *
     * @param array<string, mixed> $data
     * @param array{min?: int, max?: int} $options
     */
    public static function intOptional(array $data, string $field, array $options = []): ?int
    {
        if (!array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
            return null;
        }

        return self::int($data, $field, $options);
    }

    /**
     * Pflichtfeld: Wert muss einer der erlaubten Optionen entsprechen.
     *
     * @param array<string, mixed> $data
     * @param string[] $allowed
     */
    public static function enum(array $data, string $field, array $allowed): string
    {
        $value = self::string($data, $field);

        if (!in_array($value, $allowed, true)) {
            $list = implode(', ', $allowed);
            throw new ValidationException("Das Feld '{$field}' muss einer der folgenden Werte sein: {$list}.");
        }

        return $value;
    }

    /**
     * Optionales Enum-Feld.
     *
     * @param array<string, mixed> $data
     * @param string[] $allowed
     */
    public static function enumOptional(array $data, string $field, array $allowed): ?string
    {
        if (!array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
            return null;
        }
        return self::enum($data, $field, $allowed);
    }

    /**
     * Pflichtfeld: Datum im Format YYYY-MM-DD.
     *
     * @param array<string, mixed> $data
     */
    public static function date(array $data, string $field): string
    {
        $value = self::string($data, $field);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new ValidationException("Das Feld '{$field}' muss ein gültiges Datum im Format YYYY-MM-DD sein.");
        }

        $parts = explode('-', $value);
        if (!checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0])) {
            throw new ValidationException("Das Feld '{$field}' enthält kein gültiges Datum.");
        }

        return $value;
    }

    /**
     * Optionales Datum.
     *
     * @param array<string, mixed> $data
     */
    public static function dateOptional(array $data, string $field): ?string
    {
        if (!array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
            return null;
        }
        return self::date($data, $field);
    }

    /**
     * Optionale E-Mail-Adresse.
     *
     * @param array<string, mixed> $data
     */
    public static function emailOptional(array $data, string $field): ?string
    {
        if (!array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
            return null;
        }

        $value = trim((string)$data[$field]);

        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException("Das Feld '{$field}' enthält keine gültige E-Mail-Adresse.");
        }

        if (strlen($value) > 100) {
            throw new ValidationException("Die E-Mail-Adresse darf maximal 100 Zeichen lang sein.");
        }

        return $value;
    }

    /**
     * Optionales Rating 1–5 (für Mood, Focus, Sleep etc.).
     *
     * @param array<string, mixed> $data
     */
    public static function ratingOptional(array $data, string $field): ?int
    {
        return self::intOptional($data, $field, ['min' => 1, 'max' => 10]);
    }
}
