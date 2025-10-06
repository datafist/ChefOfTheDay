# Feature: Jahresübergreifende Fairness im Kochdienst-Algorithmus

## Problem

**Vorher:**
- Familien, die im Vorjahr 5 Dienste hatten, bekamen auch im Folgejahr wieder 5 Dienste
- Familien, die im Vorjahr 4 Dienste hatten, bekamen auch im Folgejahr wieder 4 Dienste
- **Resultat:** Immer die gleichen Familien hatten jährlich die "Mehr-Last"

**Beispiel aus der Praxis (2024/2025):**
```
Gruppe A (Singles):  4 Dienste pro Jahr → jedes Jahr
Gruppe B (Paare):    4 Dienste pro Jahr → jedes Jahr  
Gruppe C (Paare):    5 Dienste pro Jahr → jedes Jahr (unfair!)
```

## Lösung: Ausgleichende Gerechtigkeit über Jahre hinweg

Familien mit **mehr Diensten im Vorjahr** werden bei der Zuweisung **niedriger priorisiert**, sodass die "Mehr-Last" zwischen den Jahren rotiert.

**Nachher:**
```
Jahr 2024/2025:
  Familie Müller:  5 Dienste
  Familie Schmidt: 4 Dienste
  
Jahr 2025/2026:
  Familie Müller:  4 Dienste ← Reduziert (hatte 5 im Vorjahr)
  Familie Schmidt: 5 Dienste ← Erhöht (hatte 4 im Vorjahr)
  
Jahr 2026/2027:
  Familie Müller:  5 Dienste ← Wieder mehr (hatte 4 im Vorjahr)
  Familie Schmidt: 4 Dienste ← Wieder weniger (hatte 5 im Vorjahr)
```

## Implementierung

### Neue Priorisierungslogik

Die **Gesamtbelastung** über zwei Jahre wird berechnet:

```php
$lastYearCount = Anzahl Dienste im Vorjahr (aus LastYearCooking)
$currentYearCount = Anzahl Dienste bisher im aktuellen Jahr

$totalLoad = $lastYearCount + $currentYearCount
```

**⚠️ WICHTIG: Behandlung neuer Familien**

Neue Familien (ohne LastYearCooking-Eintrag) werden **neutral** behandelt:
- Sie erhalten den **berechneten Erwartungswert** als "virtuellen" Vorjahres-Count
- Der Erwartungswert wird **dynamisch** berechnet basierend auf:
  - Anzahl der Familien im aktuellen Jahr
  - Verfügbare Kochdienst-Tage (ohne Wochenenden/Ferien/Feiertage)
  - Status (Alleinerziehend oder Paar)
- **Formel:** `$defaultLastYearCount = $cookingRequirements[$partyId]`
- **Beispiele:**
  - 40 Familien, 200 Tage → Paare: 5, Singles: 3
  - 50 Familien, 200 Tage → Paare: 4, Singles: 2-3
- **Verhindert:** Überproportionale Belastung neuer Familien (z.B. 8 Dienste)
- **Verhindert:** Bevorzugung neuer Familien in späteren Jahren
- **Garantiert:** Neue Familien starten mit fairer, kontextabhängiger Basis

### Sortier-Hierarchie (aktualisiert)

Bei der Zuweisung werden Familien in dieser Reihenfolge priorisiert:

#### 1. Priorität: Schutz für Alleinerziehende
- Alleinerziehende, die ihr Limit erreicht haben, werden zurückgestellt
- Paare werden bevorzugt, wenn Alleinerziehende genug Dienste haben

#### 2. Priorität: ⭐ Jahresübergreifende Fairness (NEU!)
```php
// Niedrigere Gesamtbelastung = Höhere Priorität
$totalLoadA = $lastYearCountA + $assignedCountA;
$totalLoadB = $lastYearCountB + $assignedCountB;

if ($totalLoadA < $totalLoadB) {
    return -1; // A wird bevorzugt
}
```

**Effekt:**
- Familie mit 5+2 = **7 gesamt** wird **nach** Familie mit 4+3 = **7 gesamt** behandelt (gleich)
- Familie mit 5+1 = **6 gesamt** wird **vor** Familie mit 4+3 = **7 gesamt** behandelt

#### 3. Priorität: Zeitlicher Abstand
- Längerer Abstand seit letztem Dienst = Höhere Priorität
- Verhindert, dass eine Familie zu oft kurz hintereinander kocht

#### 4. Priorität: Erwartungswert
- Familien unter ihrem erwarteten Wert werden bevorzugt
- Sichert faire Mindest-Verteilung

#### 5. Priorität: Aktuelle Zuweisungen
- Wenigste Dienste im aktuellen Jahr = Höhere Priorität
- Fine-Tuning bei gleichen anderen Kriterien

## Beispiel-Szenarien

### Szenario 1: Rotation bei Paaren

**Ausgangslage:**
- 10 Paare, alle haben gleiche Verfügbarkeit
- 200 Kochdienst-Tage pro Jahr
- Ideale Verteilung: Alle 20 Dienste (aber praktisch: 5×20 + 5×21 = 205)

**Jahr 2024/2025:**
```
Paare 1-5:  20 Dienste (Glück gehabt)
Paare 6-10: 21 Dienste (etwas mehr)
```

**Jahr 2025/2026 mit neuer Logik:**
```
Start der Zuweisung:

Tag 1 - Kandidaten:
  Paar 6 (Vorjahr: 21, Aktuell: 0, Total: 21)
  Paar 1 (Vorjahr: 20, Aktuell: 0, Total: 20) ← Gewinner!
  
Tag 2 - Kandidaten:
  Paar 6 (Vorjahr: 21, Aktuell: 0, Total: 21)
  Paar 2 (Vorjahr: 20, Aktuell: 0, Total: 20) ← Gewinner!
  
...

Resultat:
  Paare 1-5:  21 Dienste (dieses Jahr mehr)
  Paare 6-10: 20 Dienste (dieses Jahr weniger) ✅ Gerechtigkeit!
```

### Szenario 2: Singles vs. Paare mit Rotation

**Jahr 2024/2025:**
```
5 Singles: je 4 Dienste = 20 Dienste
10 Paare:  5×4 + 5×5 = 45 Dienste
Gesamt: 65 Dienste
```

**Jahr 2025/2026 mit neuer Logik:**
```
Die 5 Paare mit 5 Diensten im Vorjahr werden niedriger priorisiert:

Zuweisung:
1. Singles mit 4 Diensten letztes Jahr (Total: 4+0 = 4)
2. Paare mit 4 Diensten letztes Jahr (Total: 4+0 = 4)
3. Paare mit 5 Diensten letztes Jahr (Total: 5+0 = 5) ← Später dran!

Resultat:
  5 Singles: je 4 Dienste (wie immer)
  5 Paare (neu): je 5 Dienste (dieses Jahr mehr)
  5 Paare (alt): je 4 Dienste (dieses Jahr weniger) ✅ Rotation!
```

### Szenario 3: Neue Familie kommt hinzu

**Situation:**
- Neue Familie hat keine Vorjahr-Daten (`lastYearCount = 0`)

**Verhalten:**
```php
$totalLoadNeueFamilie = 0 + 0 = 0
$totalLoadAlteFamilie = 5 + 0 = 5

→ Neue Familie wird STARK bevorzugt (niedrigste Gesamtlast)
```

**Resultat:**
- Neue Familien bekommen schnell ihren fairen Anteil
- Etablierte Familien mit vielen Vorjahr-Diensten werden entlastet

## Code-Änderungen

### Datei: `src/Service/CookingPlanGenerator.php`

#### Funktion: `assignCookingDays()`

**Neue Sortier-Logik:**
```php
usort($eligibleParties, function($a, $b) use (
    $assignedCount, 
    $lastAssignmentDate, 
    $date, 
    $cookingRequirements, 
    $lastYearCookings  // ← NEU: Vorjahr-Daten verfügbar
) {
    // ... Schutz für Alleinerziehende ...
    
    // NEU: Jahresübergreifende Fairness
    $lastYearCountA = $lastYearCookings[$partyIdA]?->getCookingCount() ?? 0;
    $lastYearCountB = $lastYearCookings[$partyIdB]?->getCookingCount() ?? 0;
    
    $totalLoadA = $lastYearCountA + $assignedCount[$partyIdA];
    $totalLoadB = $lastYearCountB + $assignedCount[$partyIdB];
    
    $loadDiff = $totalLoadA <=> $totalLoadB;
    if ($loadDiff !== 0) {
        return $loadDiff; // Niedrigere Last gewinnt
    }
    
    // ... Rest der Priorisierung ...
});
```

## Vorteile

✅ **Langfristige Gerechtigkeit**: Keine Familie hat dauerhaft mehr Last  
✅ **Automatische Rotation**: Algorithmus verteilt die "Mehr-Arbeit" fair über Jahre  
✅ **Motivierend**: Familien mit vielen Diensten wissen, dass sie nächstes Jahr weniger bekommen  
✅ **Transparent**: Logik ist nachvollziehbar und mathematisch fair  
✅ **Neue Familien**: Werden bevorzugt behandelt (da keine Vorjahr-Last)  

## Mathematische Fairness

### Fairness-Index über 2 Jahre

**Ohne jahresübergreifende Fairness:**
```
Jahr 1: Familie A = 5, Familie B = 4
Jahr 2: Familie A = 5, Familie B = 4
Summe:  Familie A = 10, Familie B = 8
Differenz: 2 Dienste Unterschied über 2 Jahre
```

**Mit jahresübergreifender Fairness:**
```
Jahr 1: Familie A = 5, Familie B = 4
Jahr 2: Familie A = 4, Familie B = 5
Summe:  Familie A = 9, Familie B = 9
Differenz: 0 Dienste Unterschied über 2 Jahre ✅
```

### Über längere Zeiträume (5 Jahre)

**Vorher:**
```
Familie "Viel":   5 + 5 + 5 + 5 + 5 = 25 Dienste
Familie "Wenig": 4 + 4 + 4 + 4 + 4 = 20 Dienste
Differenz: 5 Dienste (20% mehr Arbeit!)
```

**Nachher:**
```
Familie "Viel":   5 + 4 + 5 + 4 + 5 = 23 Dienste
Familie "Wenig": 4 + 5 + 4 + 5 + 4 = 22 Dienste
Differenz: 1 Dienst (4% Unterschied) ✅
```

## Testen

### Manueller Test

1. **Vorjahr ansehen:**
   ```sql
   SELECT p.name, lyc.cooking_count 
   FROM last_year_cooking lyc
   JOIN party p ON p.id = lyc.party_id
   ORDER BY lyc.cooking_count DESC;
   ```

2. **Plan für neues Jahr generieren** (Admin-Dashboard)

3. **Neues Jahr überprüfen:**
   ```sql
   SELECT p.name, COUNT(*) as dienste
   FROM cooking_assignment ca
   JOIN party p ON p.id = ca.party_id
   WHERE ca.kita_year_id = [AKTUELLES_JAHR]
   GROUP BY p.id
   ORDER BY dienste DESC;
   ```

4. **Vergleichen:**
   - Familien mit 5 Diensten im Vorjahr sollten jetzt ~4 haben
   - Familien mit 4 Diensten im Vorjahr sollten jetzt ~5 haben

### Erwartetes Ergebnis

Bei 44 Familien (43 Paare + 1 Single) mit ~220 Tagen:

**Vorjahr 2024/2025:**
```
1 Single:  4 Dienste
21 Paare:  5 Dienste (Gruppe A)
22 Paare:  5 Dienste (Gruppe B)
```

**Neues Jahr 2025/2026 (mit Rotation):**
```
1 Single:  4 Dienste (wie immer)
21 Paare:  5 Dienste (vorher 5, jetzt wieder 5 - aber ANDERE Paare!)
22 Paare:  5 Dienste (vorher 5, jetzt 4 - Entlastung!)
```

Die **konkreten Familien**, die 5 Dienste bekommen, sollten sich ändern!

## Hinweise

### Erste Jahr-Übergang
Beim allerersten Einsatz (kein Vorjahr vorhanden):
- Alle Familien haben `lastYearCount = 0`
- Algorithmus funktioniert wie bisher
- Ab dem zweiten Jahr greift die Rotation

### Lange Pausen
Familie war 2 Jahre nicht in der Kita:
- Alte Vorjahr-Daten werden nicht gelöscht
- Familie startet mit `lastYearCount` von vor 2 Jahren
- Wird stark bevorzugt (niedrige Gesamtlast)
- **Vorteil:** Automatischer Ausgleich nach Pause

## Änderungsverlauf

**5. Oktober 2025** - Jahresübergreifende Fairness implementiert
- Gesamtbelastung über 2 Jahre wird berechnet
- Priorisierung basiert auf `lastYearCount + currentYearCount`
- Rotation der "Mehr-Last" zwischen Jahren
- Dokumentation erstellt
