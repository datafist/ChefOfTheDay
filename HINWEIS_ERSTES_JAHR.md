# Hinweis: LastYearCooking für Jahr-Übergang

## Wichtig: Erstes Jahr (2024/25)

Das Kita-Jahr 2024/25 ist das **allererste Jahr** der Anwendung.
- ❌ Es gibt **KEIN Vorjahr** (kein 2023/24)
- ❌ Es gibt **KEINE LastYearCooking Daten**
- ✅ Der Algorithmus funktioniert trotzdem korrekt

## Wie funktioniert der Algorithmus im ersten Jahr?

### Bei Familien ohne LastYearCooking:
```php
if (isset($lastAssignmentDate[$partyId])) {
    // Familie hatte letztes Jahr gekocht
    $daysSinceLastAssignment = $lastAssignmentDate[$partyId]->diff($date)->days;
    // Prüfe Mindestabstand...
} else {
    // Familie noch NIE zugewiesen: immer geeignet (höchste Priorität)
    $eligiblePartiesTarget[] = $party;
}
```

**Im ersten Jahr:** Alle Familien haben **KEINE** LastYearCooking Einträge
→ Alle Familien sind beim **ersten Durchlauf** gleichberechtigt
→ Sortierung erfolgt nach Anzahl bisheriger Zuweisungen (0 für alle zu Beginn)

### Während des Jahres (innerhalb 24/25):

Sobald eine Familie zugewiesen wurde:
```php
// Nach Zuweisung wird gespeichert:
$lastAssignmentDate[$partyId] = $date;
```

**Beispiel:**
- Familie A: 02.09.2024 zugewiesen
- Nächste Prüfung am 05.09.2024:
  - Abstand: 3 Tage
  - < 28 Tage → ❌ Familie A **blockiert**
  - Andere Familien werden bevorzugt

## Vorbereitung für Jahr 2025/26

Am **Ende von Jahr 24/25** müssen LastYearCooking Einträge erstellt werden!

### Automatisches Script (Empfohlen)

**Datei:** `bin/create_last_year_cooking.php`

```php
#!/usr/bin/env php
<?php

require __DIR__.'/../vendor/autoload.php';

use App\Entity\LastYearCooking;
use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__.'/../.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();
$em = $container->get('doctrine')->getManager();

// Finde aktives Kita-Jahr
$kitaYear = $em->getRepository(\App\Entity\KitaYear::class)
    ->findOneBy(['isActive' => true]);

if (!$kitaYear) {
    echo "❌ Kein aktives Kita-Jahr gefunden!\n";
    exit(1);
}

echo "Erstelle LastYearCooking Einträge für Jahr {$kitaYear->getStartDate()->format('Y')}/...\n\n";

// Finde letzte Zuweisung jeder Familie in diesem Jahr
$parties = $em->getRepository(\App\Entity\Party::class)->findAll();

foreach ($parties as $party) {
    // Finde letzte Zuweisung
    $lastAssignment = $em->getRepository(\App\Entity\CookingAssignment::class)
        ->createQueryBuilder('ca')
        ->where('ca.party = :party')
        ->andWhere('ca.kitaYear = :kitaYear')
        ->setParameter('party', $party)
        ->setParameter('kitaYear', $kitaYear)
        ->orderBy('ca.assignedDate', 'DESC')
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
    
    if (!$lastAssignment) {
        echo "⚠️  {$party->getChildName()}: Keine Zuweisung in diesem Jahr\n";
        continue;
    }
    
    // Prüfe ob bereits LastYearCooking existiert
    $existing = $em->getRepository(LastYearCooking::class)
        ->findOneBy([
            'party' => $party,
            'kitaYear' => $kitaYear
        ]);
    
    if ($existing) {
        echo "✓ {$party->getChildName()}: Bereits vorhanden ({$existing->getLastCookingDate()->format('d.m.Y')})\n";
        continue;
    }
    
    // Erstelle LastYearCooking Eintrag
    $lastYearCooking = new LastYearCooking();
    $lastYearCooking->setParty($party);
    $lastYearCooking->setKitaYear($kitaYear);
    $lastYearCooking->setLastCookingDate($lastAssignment->getAssignedDate());
    
    $em->persist($lastYearCooking);
    
    echo "✅ {$party->getChildName()}: Erstellt ({$lastAssignment->getAssignedDate()->format('d.m.Y')})\n";
}

$em->flush();

echo "\n✅ Fertig! LastYearCooking Einträge für neues Jahr vorbereitet.\n";
```

### Ausführung (Ende August 2025):

```bash
# Vor Erstellung des neuen Jahres 2025/26:
php bin/create_last_year_cooking.php
```

### Manuell über Admin-Interface

Alternative: Admin-Interface erweitern mit Button "LastYearCooking erstellen"

## Workflow: Jahr-Übergang 24/25 → 25/26

### 1. Ende August 2025:

```bash
# Script ausführen
php bin/create_last_year_cooking.php
```

**Ergebnis:** Für jede Familie wird die letzte Zuweisung aus 24/25 gespeichert

### 2. Neues Kita-Jahr erstellen (01.09.2025):

- Admin-Interface: "Neues Kita-Jahr erstellen"
- Start: 01.09.2025
- Ende: 31.08.2026

### 3. Plan generieren:

**Jetzt nutzt der Algorithmus LastYearCooking:**

```
Familie Müller:
  Letzte Zuweisung 24/25: 25.08.2025
  Erste Zuweisung 25/26: Frühestens 22.09.2025 (28 Tage)
                         Bevorzugt: 06.10.2025 (42 Tage)
```

## Test-Szenario

### Szenario 1: Innerhalb Jahr 24/25 (aktuell)

**Ohne Vorjahr:**
```
02.09.2024 → Familie A zugewiesen
03.09.2024 → Familie B zugewiesen
04.09.2024 → Familie C zugewiesen
05.09.2024 → Familie D zugewiesen
06.09.2024 → Familie E zugewiesen
09.09.2024 → Familie F zugewiesen
10.09.2024 → Familie A? ❌ NEIN! Nur 8 Tage Abstand
             → Andere Familie wird bevorzugt
...
30.09.2024 → Familie A? ⚠️ Möglich (28 Tage = 4 Wochen)
14.10.2024 → Familie A? ✅ Optimal (42 Tage = 6 Wochen)
```

### Szenario 2: Übergang 24/25 → 25/26 (zukünftig)

**Mit LastYearCooking:**
```
Jahr 24/25:
  Familie A: Letzte Zuweisung 28.08.2025

Jahr 25/26 (Start: 01.09.2025):
  01.09.2025 → Familie A? ❌ NEIN! Nur 4 Tage
  02.09.2025 → Familie A? ❌ NEIN! Nur 5 Tage
  25.09.2025 → Familie A? ⚠️ Möglich (28 Tage)
  09.10.2025 → Familie A? ✅ Optimal (42 Tage)
```

## Wichtig für Admins

### Erste Installation (jetzt):
- ✅ Kein Action nötig
- ✅ Algorithmus funktioniert ohne LastYearCooking
- ✅ Innerhalb 24/25 werden Abstände eingehalten

### Ende von 24/25 (August 2025):
- ⚠️ **VOR** Erstellung von Jahr 25/26:
  ```bash
  php bin/create_last_year_cooking.php
  ```
- ⚠️ Danach erst neues Jahr erstellen
- ✅ Plan für 25/26 generieren

### Jeden weiteren Jahr-Übergang:
- Gleicher Prozess wie oben
- LastYearCooking wird überschrieben/aktualisiert

## Zusammenfassung

**Aktuell (Jahr 24/25 - erstes Jahr):**
- ✅ Algorithmus funktioniert korrekt
- ✅ Mindestabstände werden eingehalten (4-6 Wochen)
- ❌ Keine LastYearCooking Daten nötig

**Zukünftig (ab Jahr 25/26):**
- ⚠️ LastYearCooking muss vor neuem Jahr erstellt werden
- ✅ Script automatisiert den Prozess
- ✅ Verhindert zu kurze Abstände beim Jahr-Übergang

**Problem gelöst:**
- ✅ Innerhalb eines Jahres: Mindestabstände durch Tracking
- ✅ Zwischen Jahren: Mindestabstände durch LastYearCooking
- ✅ Beide Fälle abgedeckt durch aktuellen Code
