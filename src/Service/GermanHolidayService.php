<?php

namespace App\Service;

/**
 * Service zur Berechnung deutscher Feiertage für Baden-Württemberg
 * 
 * Berechnet automatisch alle gesetzlichen Feiertage in BW für ein gegebenes Jahr
 * basierend auf beweglichen (Ostern) und festen Feiertagen.
 */
class GermanHolidayService
{
    /**
     * Gibt alle Feiertage für Baden-Württemberg für ein bestimmtes Jahr zurück
     * 
     * @param int $year Das Jahr (z.B. 2025)
     * @return array<string, string> Array mit Datum (Y-m-d) => Feiertagsname
     */
    public function getHolidaysForYear(int $year): array
    {
        $holidays = [];
        
        // Feste Feiertage
        $holidays["$year-01-01"] = 'Neujahr';
        $holidays["$year-01-06"] = 'Heilige Drei Könige';
        $holidays["$year-05-01"] = 'Tag der Arbeit';
        $holidays["$year-10-03"] = 'Tag der Deutschen Einheit';
        $holidays["$year-11-01"] = 'Allerheiligen';
        $holidays["$year-12-25"] = '1. Weihnachtstag';
        $holidays["$year-12-26"] = '2. Weihnachtstag';
        
        // Bewegliche Feiertage (basierend auf Ostern)
        $easterDate = $this->getEasterDate($year);
        
        // Karfreitag (2 Tage vor Ostern)
        $goodFriday = $easterDate->modify('-2 days');
        $holidays[$goodFriday->format('Y-m-d')] = 'Karfreitag';
        
        // Ostermontag (1 Tag nach Ostern)
        $easterMonday = $easterDate->modify('+1 day');
        $holidays[$easterMonday->format('Y-m-d')] = 'Ostermontag';
        
        // Christi Himmelfahrt (39 Tage nach Ostern)
        $ascension = $easterDate->modify('+39 days');
        $holidays[$ascension->format('Y-m-d')] = 'Christi Himmelfahrt';
        
        // Pfingstmontag (50 Tage nach Ostern)
        $pentecostMonday = $easterDate->modify('+50 days');
        $holidays[$pentecostMonday->format('Y-m-d')] = 'Pfingstmontag';
        
        // Fronleichnam (60 Tage nach Ostern) - nur BW, BY, HE, NW, RP, SL
        $corpusChristi = $easterDate->modify('+60 days');
        $holidays[$corpusChristi->format('Y-m-d')] = 'Fronleichnam';
        
        // Sortiere nach Datum
        ksort($holidays);
        
        return $holidays;
    }
    
    /**
     * Gibt alle Feiertage für ein Kita-Jahr zurück (Sep bis Aug)
     * 
     * @param int $startYear Das Startjahr des Kita-Jahres (z.B. 2024 für 2024/25)
     * @return array<string, string> Array mit Datum (Y-m-d) => Feiertagsname
     */
    public function getHolidaysForKitaYear(int $startYear): array
    {
        $endYear = $startYear + 1;
        
        // Hole Feiertage für beide Jahre
        $holidaysStartYear = $this->getHolidaysForYear($startYear);
        $holidaysEndYear = $this->getHolidaysForYear($endYear);
        
        // Filtere nur Feiertage die im Kita-Jahr liegen (Sep-Aug)
        $kitaYearHolidays = [];
        
        // Von September bis Dezember des Startjahres
        foreach ($holidaysStartYear as $date => $name) {
            $month = (int)substr($date, 5, 2);
            if ($month >= 9) {
                $kitaYearHolidays[$date] = $name;
            }
        }
        
        // Von Januar bis August des Endjahres
        foreach ($holidaysEndYear as $date => $name) {
            $month = (int)substr($date, 5, 2);
            if ($month <= 8) {
                $kitaYearHolidays[$date] = $name;
            }
        }
        
        ksort($kitaYearHolidays);
        
        return $kitaYearHolidays;
    }
    
    /**
     * Berechnet das Osterdatum für ein bestimmtes Jahr
     * 
     * Verwendet die Gaußsche Osterformel
     * 
     * @param int $year Das Jahr
     * @return \DateTimeImmutable Das Osterdatum
     */
    private function getEasterDate(int $year): \DateTimeImmutable
    {
        // PHP hat eine eingebaute Funktion für Ostern
        $easterDays = easter_days($year);
        $baseDate = new \DateTimeImmutable("$year-03-21");
        $easterDate = $baseDate->modify("+$easterDays days");
        
        return $easterDate;
    }
    
    /**
     * Prüft ob ein bestimmtes Datum ein Feiertag ist
     * 
     * @param \DateTimeImmutable $date Das zu prüfende Datum
     * @return bool True wenn Feiertag, sonst false
     */
    public function isHoliday(\DateTimeImmutable $date): bool
    {
        $year = (int)$date->format('Y');
        $holidays = $this->getHolidaysForYear($year);
        
        return isset($holidays[$date->format('Y-m-d')]);
    }
    
    /**
     * Gibt den Namen eines Feiertags zurück
     * 
     * @param \DateTimeImmutable $date Das Datum
     * @return string|null Der Name des Feiertags oder null
     */
    public function getHolidayName(\DateTimeImmutable $date): ?string
    {
        $year = (int)$date->format('Y');
        $holidays = $this->getHolidaysForYear($year);
        
        return $holidays[$date->format('Y-m-d')] ?? null;
    }
}
