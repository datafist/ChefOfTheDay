<?php

namespace App\Tests\Unit\Service;

use App\Service\GermanHolidayService;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests für die Feiertagsberechnung (Baden-Württemberg)
 */
class GermanHolidayServiceTest extends TestCase
{
    private GermanHolidayService $service;

    protected function setUp(): void
    {
        $this->service = new GermanHolidayService();
    }

    // ========== Feste Feiertage ==========

    public function testFixedHolidays2025(): void
    {
        $holidays = $this->service->getHolidaysForYear(2025);

        $this->assertArrayHasKey('2025-01-01', $holidays);
        $this->assertSame('Neujahr', $holidays['2025-01-01']);

        $this->assertArrayHasKey('2025-01-06', $holidays);
        $this->assertSame('Heilige Drei Könige', $holidays['2025-01-06']);

        $this->assertArrayHasKey('2025-05-01', $holidays);
        $this->assertSame('Tag der Arbeit', $holidays['2025-05-01']);

        $this->assertArrayHasKey('2025-10-03', $holidays);
        $this->assertSame('Tag der Deutschen Einheit', $holidays['2025-10-03']);

        $this->assertArrayHasKey('2025-11-01', $holidays);
        $this->assertSame('Allerheiligen', $holidays['2025-11-01']);

        $this->assertArrayHasKey('2025-12-25', $holidays);
        $this->assertSame('1. Weihnachtstag', $holidays['2025-12-25']);

        $this->assertArrayHasKey('2025-12-26', $holidays);
        $this->assertSame('2. Weihnachtstag', $holidays['2025-12-26']);
    }

    // ========== Bewegliche Feiertage (Ostern-basiert) ==========

    public function testEasterBasedHolidays2025(): void
    {
        // Ostern 2025: 20. April
        $holidays = $this->service->getHolidaysForYear(2025);

        // Karfreitag: 18. April 2025
        $this->assertArrayHasKey('2025-04-18', $holidays);
        $this->assertSame('Karfreitag', $holidays['2025-04-18']);

        // Ostermontag: 21. April 2025
        $this->assertArrayHasKey('2025-04-21', $holidays);
        $this->assertSame('Ostermontag', $holidays['2025-04-21']);

        // Christi Himmelfahrt: 29. Mai 2025 (39 Tage nach Ostern)
        $this->assertArrayHasKey('2025-05-29', $holidays);
        $this->assertSame('Christi Himmelfahrt', $holidays['2025-05-29']);

        // Pfingstmontag: 9. Juni 2025 (50 Tage nach Ostern)
        $this->assertArrayHasKey('2025-06-09', $holidays);
        $this->assertSame('Pfingstmontag', $holidays['2025-06-09']);

        // Fronleichnam: 19. Juni 2025 (60 Tage nach Ostern) - BW
        $this->assertArrayHasKey('2025-06-19', $holidays);
        $this->assertSame('Fronleichnam', $holidays['2025-06-19']);
    }

    public function testEasterBasedHolidays2024(): void
    {
        // Ostern 2024: 31. März
        $holidays = $this->service->getHolidaysForYear(2024);

        $this->assertArrayHasKey('2024-03-29', $holidays); // Karfreitag
        $this->assertArrayHasKey('2024-04-01', $holidays); // Ostermontag
        $this->assertArrayHasKey('2024-05-09', $holidays); // Himmelfahrt
        $this->assertArrayHasKey('2024-05-20', $holidays); // Pfingstmontag
        $this->assertArrayHasKey('2024-05-30', $holidays); // Fronleichnam
    }

    // ========== Anzahl Feiertage ==========

    public function testTotalHolidayCountBW(): void
    {
        // BW hat 12 gesetzliche Feiertage (7 fest + 5 beweglich)
        $holidays = $this->service->getHolidaysForYear(2025);

        $this->assertCount(12, $holidays);
    }

    // ========== getHolidaysForKitaYear() ==========

    public function testHolidaysForKitaYear2024(): void
    {
        // Kita-Jahr 2024/25: Sep 2024 bis Aug 2025
        $holidays = $this->service->getHolidaysForKitaYear(2024);

        // September-Dezember 2024
        $this->assertArrayHasKey('2024-10-03', $holidays); // Tag der Deutschen Einheit
        $this->assertArrayHasKey('2024-11-01', $holidays); // Allerheiligen
        $this->assertArrayHasKey('2024-12-25', $holidays); // 1. Weihnachtstag
        $this->assertArrayHasKey('2024-12-26', $holidays); // 2. Weihnachtstag

        // Januar-August 2025
        $this->assertArrayHasKey('2025-01-01', $holidays); // Neujahr
        $this->assertArrayHasKey('2025-01-06', $holidays); // Drei Könige
        $this->assertArrayHasKey('2025-04-18', $holidays); // Karfreitag
        $this->assertArrayHasKey('2025-04-21', $holidays); // Ostermontag
        $this->assertArrayHasKey('2025-05-01', $holidays); // Tag der Arbeit
        $this->assertArrayHasKey('2025-05-29', $holidays); // Himmelfahrt
        $this->assertArrayHasKey('2025-06-09', $holidays); // Pfingstmontag
        $this->assertArrayHasKey('2025-06-19', $holidays); // Fronleichnam

        // NICHT enthalten: Feiertage vor September 2024 oder nach August 2025
        $this->assertArrayNotHasKey('2024-01-01', $holidays); // Neujahr 2024
        $this->assertArrayNotHasKey('2025-10-03', $holidays); // Tag der Deutschen Einheit 2025
    }

    // ========== isHoliday() ==========

    public function testIsHolidayTrue(): void
    {
        $date = new \DateTimeImmutable('2025-01-01'); // Neujahr
        $this->assertTrue($this->service->isHoliday($date));
    }

    public function testIsHolidayFalse(): void
    {
        $date = new \DateTimeImmutable('2025-01-02'); // Kein Feiertag
        $this->assertFalse($this->service->isHoliday($date));
    }

    public function testIsHolidayWeekend(): void
    {
        // Wochenende, aber kein gesetzlicher Feiertag
        $date = new \DateTimeImmutable('2025-01-04'); // Samstag
        $this->assertFalse($this->service->isHoliday($date));
    }

    // ========== getHolidayName() ==========

    public function testGetHolidayNameExists(): void
    {
        $date = new \DateTimeImmutable('2025-12-25');
        $this->assertSame('1. Weihnachtstag', $this->service->getHolidayName($date));
    }

    public function testGetHolidayNameNull(): void
    {
        $date = new \DateTimeImmutable('2025-01-02');
        $this->assertNull($this->service->getHolidayName($date));
    }

    // ========== Sortierung ==========

    public function testHolidaysAreSortedByDate(): void
    {
        $holidays = $this->service->getHolidaysForYear(2025);
        $dates = array_keys($holidays);

        $sortedDates = $dates;
        sort($sortedDates);

        $this->assertSame($sortedDates, $dates, 'Feiertage sollten chronologisch sortiert sein');
    }

    public function testKitaYearHolidaysAreSortedByDate(): void
    {
        $holidays = $this->service->getHolidaysForKitaYear(2024);
        $dates = array_keys($holidays);

        $sortedDates = $dates;
        sort($sortedDates);

        $this->assertSame($sortedDates, $dates, 'Kita-Jahr-Feiertage sollten chronologisch sortiert sein');
    }

    // ========== Verschiedene Jahre ==========

    public function testDifferentYearsHaveDifferentEasterDates(): void
    {
        $holidays2024 = $this->service->getHolidaysForYear(2024);
        $holidays2025 = $this->service->getHolidaysForYear(2025);

        // Karfreitag soll an verschiedenen Tagen liegen
        $karfreitag2024 = array_search('Karfreitag', $holidays2024);
        $karfreitag2025 = array_search('Karfreitag', $holidays2025);

        $this->assertNotSame($karfreitag2024, $karfreitag2025);
    }
}
