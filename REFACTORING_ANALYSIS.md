# Code-Duplikate Analyse & Refactoring-Plan

**Datum:** 6. Oktober 2025  
**Ziel:** Leichtgewichtiges Refactoring zur Reduzierung von Code-Duplikaten

## 🔍 Identifizierte Code-Duplikate

### 1. **getExcludedDates() - HOHE PRIORITÄT**

**Vorkommen:**
- `DashboardController::getExcludedDates()` (Zeilen 419-460)
- `CookingPlanGenerator::getExcludedDates()` (Zeilen 100-137)

**Duplikation:** ~95% identisch

**Code-Beispiel:**
```php
// Beide Methoden tun fast genau dasselbe:
// 1. Laden Feiertage und addieren sie zu excludedDates
// 2. Laden Ferien und addieren alle Tage im Range
// 3. Addieren alle Wochenenden im KitaYear
```

**Lösung:** Service-Methode erstellen
- Neuer Service: `DateExclusionService`
- Methode: `getExcludedDatesForKitaYear(KitaYear $kitaYear): array`

---

### 2. **getMonthNameGerman() - MITTLERE PRIORITÄT**

**Vorkommen:**
- `DashboardController::getMonthNameGerman(int $month)` (Zeile 532)
- `ParentController::getMonthNameGerman(string $monthNumber)` (Zeile 298)
- `PdfExportService::getMonthNameGerman(string $monthNumber)` (Zeile 64)

**Duplikation:** 100% identisch (nur Signatur-Unterschied int vs string)

**Code:**
```php
private function getMonthNameGerman(...): string
{
    $names = [
        1 => 'Januar', 2 => 'Februar', 3 => 'März', ...
    ];
    return $names[$month] ?? '';
}
```

**Lösung:** Utility-Klasse erstellen
- Neue Klasse: `Util\DateHelper`
- Statische Methode: `getMonthNameGerman(int $month): string`

---

### 3. **Aktives Kita-Jahr laden - NIEDRIGE PRIORITÄT**

**Vorkommen (Pattern):**
```php
$activeYear = $kitaYearRepository->findOneBy(['isActive' => true]);
```

**Häufigkeit:** 
- DashboardController: 7x
- KitaYearController: 3x
- PartyController: 3x
- VacationController: 2x
- HolidayController: 1x
- ParentController: 1x

**Lösung:** Repository-Methode
- Neue Methode in `KitaYearRepository`: `findActiveYear(): ?KitaYear`

---

### 4. **CSRF-Validierung - NIEDRIGE PRIORITÄT**

**Vorkommen:**
```php
if (!$this->isCsrfTokenValid('delete'.$entity->getId(), $request->request->get('_token'))) {
    // error handling
}
```

**Häufigkeit:** 
- PartyController: 1x
- VacationController: 1x
- KitaYearController: 1x
- DashboardController: 3x (verschiedene Typen)

**Lösung:** Trait erstellen (optional)
- Trait: `CsrfValidationTrait`
- Methode: `validateCsrfOrFlashError(string $tokenId, string $token): bool`

---

### 5. **buildCalendar() - NIEDRIGE PRIORITÄT (komplex)**

**Vorkommen:**
- `DashboardController::buildCalendarView()` (Zeilen 461-530)
- `ParentController::buildCalendar()` (Zeilen 184-297)

**Duplikation:** ~60% ähnlich, aber unterschiedliche Anforderungen

**Unterschiede:**
- DashboardController: Wochenstruktur, Assignments eingebettet
- ParentController: Tag-für-Tag Struktur, exclusion-Gründe

**Empfehlung:** **NICHT refactoren** - Die Logik ist zu unterschiedlich und würde durch Abstraktion komplexer werden.

---

## 📋 Refactoring-Plan (Priorisiert)

### Phase 1: Service-Extraktion (HOHE PRIORITÄT)

#### 1.1 DateExclusionService erstellen

**Datei:** `src/Service/DateExclusionService.php`

```php
<?php

namespace App\Service;

use App\Entity\KitaYear;
use App\Repository\HolidayRepository;
use App\Repository\VacationRepository;

class DateExclusionService
{
    public function __construct(
        private readonly HolidayRepository $holidayRepository,
        private readonly VacationRepository $vacationRepository,
    ) {}

    /**
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
```

**Änderungen:**
- `CookingPlanGenerator`: Injiziere `DateExclusionService`, entferne private Methode
- `DashboardController`: Injiziere `DateExclusionService`, entferne private Methode

**Nutzen:** -40 Zeilen duplicated Code

---

### Phase 2: Utility-Klassen (MITTLERE PRIORITÄT)

#### 2.1 DateHelper erstellen

**Datei:** `src/Util/DateHelper.php`

```php
<?php

namespace App\Util;

class DateHelper
{
    private const MONTH_NAMES_DE = [
        1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April',
        5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember'
    ];

    public static function getMonthNameGerman(int $month): string
    {
        return self::MONTH_NAMES_DE[$month] ?? '';
    }
    
    public static function getDayNameGerman(int $dayNumber): string
    {
        $days = [
            1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 
            4 => 'Donnerstag', 5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag',
        ];
        return $days[$dayNumber] ?? '';
    }
}
```

**Änderungen:**
- `DashboardController`: Ersetze `getMonthNameGerman()` durch `DateHelper::getMonthNameGerman()`
- `ParentController`: Ersetze beide Helper-Methoden
- `PdfExportService`: Ersetze `getMonthNameGerman()`

**Nutzen:** -30 Zeilen duplicated Code

---

### Phase 3: Repository-Verbesserung (NIEDRIGE PRIORITÄT)

#### 3.1 KitaYearRepository erweitern

**Datei:** `src/Repository/KitaYearRepository.php`

```php
public function findActiveYear(): ?KitaYear
{
    return $this->findOneBy(['isActive' => true]);
}
```

**Änderungen:** Alle Controller ersetzen Pattern durch `findActiveYear()`

**Nutzen:** +Lesbarkeit, -0 Zeilen aber bessere Semantik

---

## 📊 Impact-Analyse

| Refactoring | Dateien geändert | Zeilen gespart | Risiko | Aufwand |
|-------------|------------------|----------------|--------|---------|
| DateExclusionService | 2 | ~40 | Niedrig | 30 min |
| DateHelper | 3 | ~30 | Sehr niedrig | 15 min |
| findActiveYear() | 6 | ~0 | Sehr niedrig | 10 min |
| **TOTAL** | **11** | **~70** | **Niedrig** | **~1h** |

---

## ✅ Vorteile des Refactorings

1. **Wartbarkeit**: Änderungen an getExcludedDates() müssen nur an einer Stelle gemacht werden
2. **Testbarkeit**: Services können leichter getestet werden als private Controller-Methoden
3. **Wiederverwendbarkeit**: Andere Controller können die Services nutzen
4. **Konsistenz**: Einheitliche Logik garantiert identisches Verhalten
5. **Single Responsibility**: Services haben klare, einzelne Aufgaben

---

## ⚠️ Nicht refactoren

### buildCalendar() Methoden

**Grund:** Die beiden Kalender-Methoden haben unterschiedliche Zwecke:
- Admin-Kalender: Zeigt Assignments pro Tag, Drag&Drop-Funktionalität
- Eltern-Kalender: Zeigt Verfügbarkeits-Auswahl, exclusion reasons

Eine Abstraktion würde mehr Komplexität schaffen als sie spart.

**Empfehlung:** Belassen wie es ist.

---

## 🔧 Implementierungs-Reihenfolge

1. ✅ **DateExclusionService** erstellen und testen
2. ✅ **CookingPlanGenerator** refactoren
3. ✅ **DashboardController** refactoren
4. ✅ **DateHelper** erstellen
5. ✅ Alle Controller auf DateHelper umstellen
6. ✅ Tests durchführen
7. ✅ **findActiveYear()** implementieren (optional)

---

## 🧪 Test-Checkliste

Nach jedem Refactoring testen:

- [ ] Kochplan generieren funktioniert
- [ ] Kalender-Ansicht lädt korrekt
- [ ] PDF-Export funktioniert
- [ ] Eltern-Verfügbarkeit funktioniert
- [ ] Keine PHP-Fehler im Log
- [ ] Keine JavaScript-Fehler in der Console

---

## 💡 Weitere Optimierungsmöglichkeiten (Zukunft)

1. **FlashMessageTrait**: Für konsistente Flash-Messages
2. **EntityCountService**: Für wiederholte count()-Aufrufe
3. **AvailabilityService**: Logik aus ParentController extrahieren
4. **CalendarBuilderService**: Wenn Kalender-Logik in Zukunft vereinheitlicht werden soll

---

**Status:** Bereit zur Implementierung  
**Geschätzte Zeit:** 1 Stunde  
**Risiko-Level:** Niedrig
