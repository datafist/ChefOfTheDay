<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Availability;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests für die Availability Entity
 */
class AvailabilityTest extends TestCase
{
    // ========== isDateAvailable() ==========

    public function testIsDateAvailableTrue(): void
    {
        $availability = new Availability();
        $availability->setAvailableDates(['2024-10-01', '2024-10-08', '2024-10-15']);

        $this->assertTrue($availability->isDateAvailable('2024-10-01'));
        $this->assertTrue($availability->isDateAvailable('2024-10-08'));
    }

    public function testIsDateAvailableFalse(): void
    {
        $availability = new Availability();
        $availability->setAvailableDates(['2024-10-01', '2024-10-08']);

        $this->assertFalse($availability->isDateAvailable('2024-10-02'));
        $this->assertFalse($availability->isDateAvailable('2024-10-15'));
    }

    public function testIsDateAvailableEmptyDates(): void
    {
        $availability = new Availability();

        $this->assertFalse($availability->isDateAvailable('2024-10-01'));
    }

    // ========== addAvailableDate() ==========

    public function testAddAvailableDate(): void
    {
        $availability = new Availability();
        $availability->addAvailableDate('2024-10-01');
        $availability->addAvailableDate('2024-10-08');

        $dates = $availability->getAvailableDates();
        $this->assertCount(2, $dates);
        $this->assertContains('2024-10-01', $dates);
        $this->assertContains('2024-10-08', $dates);
    }

    public function testAddAvailableDateNoDuplicates(): void
    {
        $availability = new Availability();
        $availability->addAvailableDate('2024-10-01');
        $availability->addAvailableDate('2024-10-01'); // Duplikat

        $this->assertCount(1, $availability->getAvailableDates());
    }

    public function testAddAvailableDateUpdatesTimestamp(): void
    {
        $availability = new Availability();
        $oldUpdated = $availability->getUpdatedAt();

        // Kleine Verzögerung um Timestamp-Unterschied sicherzustellen
        usleep(1000);
        $availability->addAvailableDate('2024-10-01');

        $this->assertGreaterThanOrEqual($oldUpdated, $availability->getUpdatedAt());
    }

    // ========== removeAvailableDate() ==========

    public function testRemoveAvailableDate(): void
    {
        $availability = new Availability();
        $availability->setAvailableDates(['2024-10-01', '2024-10-08', '2024-10-15']);

        $availability->removeAvailableDate('2024-10-08');

        $dates = $availability->getAvailableDates();
        $this->assertCount(2, $dates);
        $this->assertContains('2024-10-01', $dates);
        $this->assertContains('2024-10-15', $dates);
        $this->assertNotContains('2024-10-08', $dates);
    }

    public function testRemoveAvailableDateReindexes(): void
    {
        $availability = new Availability();
        $availability->setAvailableDates(['2024-10-01', '2024-10-08', '2024-10-15']);

        $availability->removeAvailableDate('2024-10-08');

        $dates = $availability->getAvailableDates();
        // Array sollte re-indexiert sein (0, 1 statt 0, 2)
        $this->assertSame(['2024-10-01', '2024-10-15'], array_values($dates));
    }

    public function testRemoveAvailableDateNonExistent(): void
    {
        $availability = new Availability();
        $availability->setAvailableDates(['2024-10-01']);

        $availability->removeAvailableDate('2024-10-99');

        $this->assertCount(1, $availability->getAvailableDates());
    }

    // ========== setAvailableDates() ==========

    public function testSetAvailableDatesUpdatesTimestamp(): void
    {
        $availability = new Availability();
        $oldUpdated = $availability->getUpdatedAt();

        usleep(1000);
        $availability->setAvailableDates(['2024-10-01']);

        $this->assertGreaterThanOrEqual($oldUpdated, $availability->getUpdatedAt());
    }

    // ========== Konstruktor ==========

    public function testConstructorInitializesEmptyDates(): void
    {
        $availability = new Availability();

        $this->assertSame([], $availability->getAvailableDates());
        $this->assertNotNull($availability->getUpdatedAt());
    }
}
