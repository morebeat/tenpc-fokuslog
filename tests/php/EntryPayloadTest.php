<?php

declare(strict_types=1);

require_once __DIR__ . '/../../api/lib/EntryPayload.php';

class EntryPayloadTest
{
    public function testNormalizeDateReturnsIsoFormat(): void
    {
        $today = new DateTimeImmutable('2024-12-31');
        $result = EntryPayload::normalizeDate('2024-05-01', $today);
        Assert::equals('2024-05-01', $result, 'Datum sollte im ISO-Format verbleiben');
    }

    public function testNormalizeDateAcceptsIsoWithTime(): void
    {
        $today = new DateTimeImmutable('2024-12-31');
        $result = EntryPayload::normalizeDate('2024-05-01T10:15:00Z', $today);
        Assert::equals('2024-05-01', $result, 'ISO-Strings mit Uhrzeit sollten auf das Datum reduziert werden');
    }

    public function testNormalizeDateNormalizesUnicodeHyphen(): void
    {
        $today = new DateTimeImmutable('2024-12-31');
        $value = "2024\xE2\x80\x90" . "05\xE2\x80\x90" . '01';
        $result = EntryPayload::normalizeDate($value, $today);
        Assert::equals('2024-05-01', $result, 'Unicode-Bindestriche sollten akzeptiert werden');
    }

    public function testNormalizeDateRejectsFuture(): void
    {
        $today = new DateTimeImmutable('2024-05-01');
        try {
            EntryPayload::normalizeDate('2024-05-02', $today);
            throw new Exception('Future dates should trigger an exception');
        } catch (InvalidArgumentException $e) {
            Assert::equals('Einträge in der Zukunft sind nicht erlaubt.', $e->getMessage(), 'Fehlermeldung für zukünftige Daten prüfen');
        }
    }

    public function testNormalizeTimeFiltersInvalidValues(): void
    {
        $normalized = EntryPayload::normalizeTime('morning');
        Assert::equals('morning', $normalized, 'Zeit-Slot sollte unverändert bleiben');

        try {
            EntryPayload::normalizeTime('night');
            throw new Exception('Ungültige Zeitwerte sollten eine Exception auslösen');
        } catch (InvalidArgumentException $e) {
            Assert::equals('Ungültiger time Slot', $e->getMessage(), 'Fehlermeldung für ungültige Zeit prüfen');
        }
    }

    public function testIntOrNullCleansInput(): void
    {
        Assert::equals(3, EntryPayload::intOrNull('3'), 'Numerische Strings sollten in Integer umgewandelt werden');
        Assert::equals(null, EntryPayload::intOrNull('abc'), 'Nicht numerische Werte sollten null liefern');
    }

    public function testDecimalOrNullFormatsNumbers(): void
    {
        Assert::equals('12.50', EntryPayload::decimalOrNull('12,5'), 'Kommawerte sollten korrekt normalisiert werden');
        Assert::equals(null, EntryPayload::decimalOrNull('abc'), 'Ungültige Werte sollten null liefern');
    }

    public function testNormalizeMedicationIdRejectsZero(): void
    {
        Assert::equals(null, EntryPayload::normalizeMedicationId(0), '0 ist keine gültige ID');
        Assert::equals(5, EntryPayload::normalizeMedicationId('5'), 'String-ID sollte in Integer umgewandelt werden');
    }
}
