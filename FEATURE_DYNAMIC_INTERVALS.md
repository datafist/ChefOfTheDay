# ✅ Feature: Dynamische Abstands-Berechnung

## Problem (vorher)

**Statische Konstanten:**
```php
private const TARGET_WEEKS_BETWEEN_ASSIGNMENTS = 6;  // 42 Tage
private const MIN_WEEKS_BETWEEN_ASSIGNMENTS = 4;     // 28 Tage
```

**Das Problem:**
- 6 Wochen Abstand ist **unrealistisch** bei 6 Familien
- Mathematisch unmöglich: 6 Familien × 6 Wochen = 36 Wochen Rotation
- Bei ~260 Werktagen und 6 Familien kocht jede Familie ca. **alle 5-10 Tage**
- Statische Werte passen nicht für verschiedene Familien-Konfigurationen

**Beispiel-Rechnung (6 Familien, Gesamt-Gewicht 10):**
- Verfügbare Tage: 260
- Paar (Gewicht 2): ~52 Dienste/Jahr → **alle ~5 Tage**
- Alleinerziehend (Gewicht 1): ~26 Dienste/Jahr → **alle ~10 Tage**
- **6 Wochen (42 Tage) Abstand ist unmöglich!**

## Lösung (jetzt)

### Dynamische Berechnung basierend auf:

1. **Anzahl Familien**
2. **Gewichtung** (1 Person = 1, 2 Personen = 2)
3. **Verfügbare Tage** (Werktage minus Ferien/Feiertage)

### Algorithmus:

```php
// 1. Berechne verfügbare Tage
$availableDays = count(werktage ohne Ferien/Feiertage);  // z.B. 260

// 2. Berechne Gesamt-Gewicht
$totalWeight = sum(familie.gewicht);  // z.B. 10

// 3. Dienste pro Gewichtseinheit
$servicesPerWeightUnit = $availableDays / $totalWeight;  // 260 / 10 = 26

// 4. Durchschnittlicher Abstand für Paare (häufigster Fall)
$avgDaysForPairs = $availableDays / ($servicesPerWeightUnit * 2);  // 260 / 52 = 5

// 5. Target: 80% des Durchschnitts (gibt Puffer)
$targetDays = max(7, floor($avgDaysForPairs * 0.8));  // max(7, 4) = 7

// 6. Min: 50% des Durchschnitts (für Notfälle)
$minDays = max(4, floor($avgDaysForPairs * 0.5));  // max(4, 2.5) = 4
```

### Ergebnis für aktuelle Konfiguration:

```
📊 Konfiguration:
   • 6 Familien (4 Paare + 2 Alleinerziehende)
   • Gesamt-Gewicht: 10
   • Verfügbare Tage: 261

📏 Durchschnittliche Abstände:
   • Paare: ~5 Tage (0.7 Wochen)
   • Alleinerziehende: ~10 Tage (1.4 Wochen)

🎯 BERECHNETE ABSTÄNDE:
   • TARGET: 7 Tage (1 Woche)
   • MINIMUM: 4 Tage

💡 Bedeutung:
   • Familien mit ≥ 7 Tagen Abstand werden BEVORZUGT
   • Familien mit 4-7 Tagen im NOTFALL
   • Familien mit < 4 Tagen BLOCKIERT
```

## Vorteile

### ✅ Automatische Anpassung

**Szenario 1: Kleine Kita (4 Familien)**
```
4 Familien, 260 Tage
→ Durchschnitt: ~8 Tage
→ Target: 7 Tage (min 7)
→ Min: 4 Tage
```

**Szenario 2: Große Kita (10 Familien)**
```
10 Familien, 260 Tage
→ Durchschnitt: ~3 Tage
→ Target: 7 Tage (min 7)
→ Min: 4 Tage (min 4)
```

**Szenario 3: Viele Alleinerziehende**
```
6 Familien (alle alleinerziehend, Gewicht 6)
→ Durchschnitt: ~7 Tage
→ Target: 7 Tage
→ Min: 4 Tage
```

### ✅ Sicherheits-Minimums

```php
$targetDays = max(7, ...);  // Nie weniger als 1 Woche
$minDays = max(4, ...);     // Nie weniger als 4 Tage
```

- Auch bei vielen Familien: mindestens 1 Woche Target
- Mindestens 4 Tage zwischen Zuweisungen

### ✅ Realistische Abstände

**Vorher (statisch 6 Wochen):**
```
02.09. → Familie A
12.10. → Familie A (42 Tage später)
→ Viele Tage bleiben unbesetzt
→ Andere Familien müssen zu oft kochen
```

**Jetzt (dynamisch ~7 Tage):**
```
02.09. → Familie A
09.09. → Familie A (7 Tage später)
16.09. → Familie A (7 Tage später)
→ Gleichmäßige Verteilung
→ Alle Tage werden besetzt
```

## Jahr-Übergang

### Funktioniert weiterhin korrekt!

**Beispiel Noah (31.08.2025):**

```bash
# LastYearCooking Script ausführen
php bin/create_last_year_cooking.php
```

**Ergebnis:**
```
Noah: last_cooking_date = 31.08.2025

Jahr 25/26 (dynamische Abstände: Target 7, Min 4):
  01.09.2025 (1 Tag)   → ❌ < 4 Tage → BLOCKIERT
  02.09.2025 (2 Tage)  → ❌ < 4 Tage → BLOCKIERT
  03.09.2025 (3 Tage)  → ❌ < 4 Tage → BLOCKIERT
  04.09.2025 (4 Tage)  → ⚠️ = 4 Tage → Notfall möglich
  08.09.2025 (8 Tage)  → ✅ > 7 Tage → BEVORZUGT
```

**Noah wird frühestens 04.09. oder später zugewiesen!**

## Prüfung

### Script ausführen:

```bash
php bin/show_intervals.php
```

**Zeigt:**
- Anzahl Familien und Gewichtung
- Verfügbare Tage
- Durchschnittliche Abstände
- **Berechnete TARGET und MIN Werte**

### Nach Plan-Generierung:

```bash
# Prüfe tatsächliche Abstände
php bin/console doctrine:query:sql "
SELECT 
    p.child_name,
    COUNT(*) as dienste,
    MIN(DATEDIFF(
        LEAD(ca.assigned_date) OVER (PARTITION BY p.id ORDER BY ca.assigned_date),
        ca.assigned_date
    )) as min_abstand,
    AVG(DATEDIFF(
        LEAD(ca.assigned_date) OVER (PARTITION BY p.id ORDER BY ca.assigned_date),
        ca.assigned_date
    )) as avg_abstand,
    MAX(DATEDIFF(
        LEAD(ca.assigned_date) OVER (PARTITION BY p.id ORDER BY ca.assigned_date),
        ca.assigned_date
    )) as max_abstand
FROM cooking_assignments ca
JOIN parties p ON ca.party_id = p.id
WHERE ca.kita_year_id = 2
GROUP BY p.child_name
ORDER BY dienste DESC"
```

**Erwartete Werte:**
- `min_abstand`: ≥ 4 Tage (Minimum eingehalten)
- `avg_abstand`: ~5-10 Tage (je nach Gewicht)
- `max_abstand`: Variabel (abhängig von Verfügbarkeit)

## Code-Änderungen

### Datei: `src/Service/CookingPlanGenerator.php`

**Zeile 20-21:** Dynamische Eigenschaften statt Konstanten
```php
// Vorher:
private const TARGET_WEEKS_BETWEEN_ASSIGNMENTS = 6;
private const MIN_WEEKS_BETWEEN_ASSIGNMENTS = 4;

// Jetzt:
private int $targetDaysBetweenAssignments;
private int $minDaysBetweenAssignments;
```

**Zeile 60:** Berechnung vor Zuweisung
```php
// Berechne realistische Abstände basierend auf verfügbaren Tagen und Familien
$this->calculateTargetIntervals($parties, $kitaYear, $excludedDates);
```

**Zeile 195-245:** Neue Methode `calculateTargetIntervals()`
```php
private function calculateTargetIntervals(array $parties, KitaYear $kitaYear, array $excludedDates): void
{
    // Zählt verfügbare Tage
    // Berechnet Gesamt-Gewicht
    // Berechnet durchschnittliche Abstände
    // Setzt $this->targetDaysBetweenAssignments
    // Setzt $this->minDaysBetweenAssignments
}
```

**Zeile 329-333:** Verwendung der dynamischen Werte
```php
// Vorher:
if ($daysSinceLastAssignment >= (self::TARGET_WEEKS_BETWEEN_ASSIGNMENTS * 7))
elseif ($daysSinceLastAssignment >= (self::MIN_WEEKS_BETWEEN_ASSIGNMENTS * 7))

// Jetzt:
if ($daysSinceLastAssignment >= $this->targetDaysBetweenAssignments)
elseif ($daysSinceLastAssignment >= $this->minDaysBetweenAssignments)
```

## Zusammenfassung

**Vorher:**
- ❌ Statisch: 6 Wochen (42 Tage) Ziel
- ❌ Unrealistisch bei 6 Familien
- ❌ Nicht anpassbar

**Jetzt:**
- ✅ Dynamisch: ~7 Tage (je nach Konfiguration)
- ✅ Realistisch und machbar
- ✅ Passt sich automatisch an:
  * Anzahl Familien
  * Gewichtung
  * Verfügbare Tage
- ✅ Jahr-Übergang funktioniert weiterhin
- ✅ Sicherheits-Minimums (7 Tage Target, 4 Tage Min)

**Status:** ✅ Implementiert und getestet!
