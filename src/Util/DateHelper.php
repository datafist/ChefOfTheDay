<?php

namespace App\Util;

/**
 * Helper-Klasse für Datum-bezogene Utility-Funktionen
 */
class DateHelper
{
    private const MONTH_NAMES_DE = [
        1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April',
        5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember'
    ];

    private const DAY_NAMES_DE = [
        1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 
        4 => 'Donnerstag', 5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag',
    ];

    /**
     * Gibt den deutschen Monatsnamen zurück
     * 
     * @param int $month Monat (1-12)
     * @return string Deutscher Monatsname
     */
    public static function getMonthNameGerman(int $month): string
    {
        return self::MONTH_NAMES_DE[$month] ?? '';
    }

    /**
     * Gibt den deutschen Wochentagsnamen zurück
     * 
     * @param int $dayNumber ISO-8601 Wochentag (1=Montag, 7=Sonntag)
     * @return string Deutscher Wochentagsname
     */
    public static function getDayNameGerman(int $dayNumber): string
    {
        return self::DAY_NAMES_DE[$dayNumber] ?? '';
    }
}
