<?php

namespace App\Service;

use App\Entity\KitaYear;
use App\Repository\HolidayRepository;
use App\Repository\VacationRepository;

/**
 * Service zur Berechnung ausgeschlossener Tage (Wochenenden, Feiertage, Ferien)
 */
class DateExclusionService
{
    public function __construct(
        private readonly HolidayRepository $holidayRepository,
        private readonly VacationRepository $vacationRepository,
    ) {}

    /**
     * Ermittelt alle ausgeschlossenen Tage für ein Kita-Jahr
     * 
     * @return array<string, bool> date => true
     */
    public function getExcludedDatesForKitaYear(KitaYear $kitaYear): array
    {
        $excludedDates = [];
        
        // Feiertage
        $holidays = $this->holidayRepository->findBy(['kitaYear' => $kitaYear]);
        foreach ($holidays as $holiday) {
            $excludedDates[$holiday->getDate()->format('Y-m-d')] = true;
        }
        
        // Ferien
        $vacations = $this->vacationRepository->findBy(['kitaYear' => $kitaYear]);
        foreach ($vacations as $vacation) {
            $period = new \DatePeriod(
                $vacation->getStartDate(),
                new \DateInterval('P1D'),
                $vacation->getEndDate()->modify('+1 day')
            );
            foreach ($period as $date) {
                $excludedDates[$date->format('Y-m-d')] = true;
            }
        }
        
        // Wochenenden
        $period = new \DatePeriod(
            $kitaYear->getStartDate(),
            new \DateInterval('P1D'),
            $kitaYear->getEndDate()->modify('+1 day')
        );
        foreach ($period as $date) {
            $dayOfWeek = (int)$date->format('N');
            if ($dayOfWeek === 6 || $dayOfWeek === 7) {
                $excludedDates[$date->format('Y-m-d')] = true;
            }
        }
        
        return $excludedDates;
    }
}
