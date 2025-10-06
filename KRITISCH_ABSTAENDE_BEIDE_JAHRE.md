# 🚨 KRITISCHE ANALYSE: Abstände zwischen Kochdiensten (2024/25 + 2025/26)

**Datum der Analyse:** 05.10.2025  
**Analysierte Zeiträume:** 
- Kitajahr 2024/2025 (173 Dienste)
- Kitajahr 2025/2026 (197 Dienste)
- **Gesamt: 370 Dienste über beide Jahre**

---

## 🚨 SCHWERWIEGENDE VERSTÖSSE GEGEN MINDESTABSTAND

### ❌ Kritische Fälle: NUR 35 TAGE ABSTAND (Minimum: 60 Tage!)

**3 Familien haben katastrophal kurze Abstände zwischen September und Oktober 2025:**

| Familie ID | Name | Dienst 1 | Dienst 2 | Abstand | Verstoß |
|------------|------|----------|----------|---------|---------|
| **13** | - | 03.09.2025 | 08.10.2025 | **35 Tage** | ❌ -25 Tage unter Minimum! |
| **26** | - | 04.09.2025 | 09.10.2025 | **35 Tage** | ❌ -25 Tage unter Minimum! |
| **46** | - | 02.09.2025 | 07.10.2025 | **35 Tage** | ❌ -25 Tage unter Minimum! |

**Das sind nur 5 Wochen zwischen Diensten - völlig inakzeptabel!**

---

## ⚠️ WEITERE VERSTÖSSE: 56-59 TAGE (Unter/am Minimum)

**40 weitere Fälle** mit Abständen zwischen **56-59 Tagen** (knapp unter oder am Minimum):

### 56 Tage (8 Wochen genau):
- Familie 1: 06.01.2026 → 03.03.2026
- Familie 9: 05.02.2025 → 02.04.2025
- Familie 12: 14.01.2026 → 11.03.2026
- Familie 15: 14.02.2025 → 11.04.2025
- Familie 15: 08.01.2026 → 05.03.2026
- **Familie 20: 07.05.2025 → 02.07.2025** (Sommer-Problem!)
- Familie 27: 28.01.2026 → 25.03.2026
- Familie 43: 08.05.2026 → 03.07.2026

### 57 Tage:
- 11 Familien mit Abständen im Januar→März Zeitraum

### 58 Tage:
- 8 Familien mit Abständen zwischen Januar und März

### 59 Tage:
- 11 Familien, inkl. **Sommer-Probleme** (Mai→Juli)

---

## 📊 GESAMTSTATISTIK ÜBER BEIDE JAHRE

| Metrik | Wert | Bewertung |
|--------|------|-----------|
| **Familien gesamt** | 49 | - |
| **Dienste gesamt** | 370 | 173 (Jahr 1) + 197 (Jahr 2) |
| **Kritische Fälle (< 60 Tage)** | **43 Fälle** | ❌ 11,6% aller Übergänge |
| **Schwerwiegende Verstöße (< 40 Tage)** | **3 Fälle** | ❌ Extrem problematisch |
| **Minimaler Abstand** | **35 Tage** | ❌ Nur 5 Wochen! |
| **Durchschnittlicher Abstand** | ~77 Tage | ✅ Akzeptabel |
| **Maximaler Abstand** | 202 Tage | ✅ Gut |

---

## 🔍 MUSTERERKENNUNG: Wo treten Probleme auf?

### 1. **September → Oktober Problem (Jahresübergang)**
Die 3 schwersten Verstöße (35 Tage) treten **ALLE zwischen Anfang September und Anfang Oktober 2025** auf:
- 02./03./04. September 2025 → 07./08./09. Oktober 2025
- **Ursache:** Der Algorithmus plant den Jahresbeginn (September 2025) zu dicht nach dem Jahresende (August 2025)
- **Problem:** Familien, die Ende August 2024/2025 gekocht haben, sollten nicht Anfang September 2025/2026 wieder kochen

### 2. **Januar → März Problem (Winter-Frühling)**
- Viele Abstände von 56-59 Tagen zwischen Januar und März beider Jahre
- **Ursache:** Vermutlich viele Feiertage/Ferien im Dezember/Februar reduzieren verfügbare Tage
- Algorithmus komprimiert Dienste im Januar-März Zeitraum

### 3. **Mai → Juli Problem (Vor-Sommer)**
- Mehrere Fälle von 56-59 Tagen zwischen Mai und Juli
- **Bereits in vorheriger Analyse erkannt** (Emily: 56 Tage)
- Familien müssen kurz vor dem Sommer kochen, obwohl sie gerade erst im Frühjahr gekocht haben

---

## 🎯 URSACHENANALYSE

### Warum versagt der Algorithmus?

Der `CookingPlanGenerator` hat zwar eine Priorität für temporalen Abstand (Priority 3), aber:

1. **Jahresübergang wird nicht berücksichtigt:**
   - Der Algorithmus schaut nur auf Dienste **innerhalb des aktuellen Jahres**
   - Beim Planen von Jahr 2025/26 werden die Dienste von Ende August 2024/25 **nicht berücksichtigt**
   - Familien mit August-Diensten bekommen deshalb September-Dienste (nur 35 Tage später!)

2. **Multi-Jahr-Fairness greift zu spät:**
   - Die `LastYearCooking` Tabelle speichert nur die **Anzahl** der Dienste
   - Das **Datum des letzten Dienstes** wird nicht gespeichert!
   - Algorithmus kann nicht prüfen: "Wann hat diese Familie das letzte Mal gekocht?"

3. **Temporal Spacing nur innerhalb des Jahres:**
   - Code in `CookingPlanGenerator.php` Zeile ~450-460 berechnet `daysSinceLastAssignment`
   - Aber `$assignedCount[$partyId]` ist zu Beginn eines neuen Jahres **leer**!
   - Es gibt keine Verbindung zu Diensten aus dem Vorjahr

---

## 💡 LÖSUNGSANSÄTZE

### Sofortmaßnahme für aktuellen Plan:
```bash
# Manuelle Korrektur der 3 kritischen Fälle (35 Tage)
# Familie 13: 08.10.2025 verschieben auf November
# Familie 26: 09.10.2025 verschieben auf November  
# Familie 46: 07.10.2025 verschieben auf November
```

### Langfristige Code-Fixes:

#### Fix 1: LastYearCooking um Datum erweitern
```php
// In src/Entity/LastYearCooking.php
#[ORM\Column(type: Types::DATE_IMMUTABLE)]
private ?\DateTimeImmutable $lastCookingDate = null; // ✅ Bereits vorhanden!
```

#### Fix 2: Jahresübergang im Generator berücksichtigen
```php
// In src/Service/CookingPlanGenerator.php
// Bei der Berechnung von $daysSinceLastAssignment:

// NEU: Auch Vorjahr prüfen!
$lastYearCooking = $lastYearCookings[$partyId] ?? null;
if ($lastYearCooking && $lastYearCooking->getLastCookingDate()) {
    $daysSinceLast = $currentDate->diff($lastYearCooking->getLastCookingDate())->days;
    if ($daysSinceLast < 60) {
        // Zu nah am letzten Dienst aus Vorjahr - reduziere Priorität drastisch!
        $scores[$partyId] -= 100000;
        continue; // Überspringe diese Familie für dieses Datum
    }
}
```

#### Fix 3: Minimum-Abstand härter durchsetzen
```php
// Ersetze weiche Priorisierung durch harte Regel:
if ($daysSinceLastAssignment < 60) {
    continue; // SKIP - nicht erlaubt!
}
```

---

## 📋 DETAILLIERTE LISTE ALLER VERSTÖSSE

### Alle 43 Fälle mit < 60 Tagen Abstand:

| Familie | Dienst 1 | Dienst 2 | Tage | Problem-Typ |
|---------|----------|----------|------|-------------|
| 13 | 03.09.2025 | 08.10.2025 | **35** | ❌ Jahresübergang |
| 26 | 04.09.2025 | 09.10.2025 | **35** | ❌ Jahresübergang |
| 46 | 02.09.2025 | 07.10.2025 | **35** | ❌ Jahresübergang |
| 1 | 06.01.2026 | 03.03.2026 | 56 | ⚠️ Winter-Frühling |
| 9 | 05.02.2025 | 02.04.2025 | 56 | ⚠️ Winter-Frühling |
| 12 | 14.01.2026 | 11.03.2026 | 56 | ⚠️ Winter-Frühling |
| 15 | 14.02.2025 | 11.04.2025 | 56 | ⚠️ Winter-Frühling |
| 15 | 08.01.2026 | 05.03.2026 | 56 | ⚠️ Winter-Frühling |
| 20 | 07.05.2025 | 02.07.2025 | 56 | ⚠️ Vor-Sommer |
| 27 | 28.01.2026 | 25.03.2026 | 56 | ⚠️ Winter-Frühling |
| 43 | 08.05.2026 | 03.07.2026 | 56 | ⚠️ Vor-Sommer |
| 11 | 11.02.2025 | 09.04.2025 | 57 | ⚠️ Winter-Frühling |
| 14 | 15.01.2026 | 13.03.2026 | 57 | ⚠️ Winter-Frühling |
| 16 | 12.01.2026 | 10.03.2026 | 57 | ⚠️ Winter-Frühling |
| 17 | 20.01.2026 | 18.03.2026 | 57 | ⚠️ Winter-Frühling |
| 19 | 22.01.2026 | 20.03.2026 | 57 | ⚠️ Winter-Frühling |
| 20 | 19.01.2026 | 17.03.2026 | 57 | ⚠️ Winter-Frühling |
| 21 | 26.01.2026 | 24.03.2026 | 57 | ⚠️ Winter-Frühling |
| 22 | 21.01.2026 | 19.03.2026 | 57 | ⚠️ Winter-Frühling |
| 30 | 29.01.2026 | 27.03.2026 | 57 | ⚠️ Winter-Frühling |
| 2 | 05.01.2026 | 04.03.2026 | 58 | ⚠️ Winter-Frühling |
| 8 | 13.01.2026 | 12.03.2026 | 58 | ⚠️ Winter-Frühling |
| 9 | 07.01.2026 | 06.03.2026 | 58 | ⚠️ Winter-Frühling |
| 24 | 27.01.2026 | 26.03.2026 | 58 | ⚠️ Winter-Frühling |
| 26 | 07.01.2025 | 06.03.2025 | 58 | ⚠️ Winter-Frühling |
| 29 | 06.01.2025 | 05.03.2025 | 58 | ⚠️ Winter-Frühling |
| 38 | 20.01.2025 | 19.03.2025 | 58 | ⚠️ Winter-Frühling |
| 42 | 28.01.2025 | 27.03.2025 | 58 | ⚠️ Winter-Frühling |
| 8 | 10.02.2025 | 10.04.2025 | 59 | ⚠️ Winter-Frühling |
| 10 | 09.01.2026 | 09.03.2026 | 59 | ⚠️ Winter-Frühling |
| 10 | 28.04.2025 | 26.06.2025 | 59 | ⚠️ Vor-Sommer |
| 16 | 29.04.2025 | 27.06.2025 | 59 | ⚠️ Vor-Sommer |
| 17 | 05.05.2025 | 03.07.2025 | 59 | ⚠️ Vor-Sommer |
| 18 | 06.05.2025 | 04.07.2025 | 59 | ⚠️ Vor-Sommer |
| 18 | 16.01.2026 | 16.03.2026 | 59 | ⚠️ Winter-Frühling |
| 25 | 23.01.2026 | 23.03.2026 | 59 | ⚠️ Winter-Frühling |
| 32 | 13.01.2025 | 13.03.2025 | 59 | ⚠️ Winter-Frühling |
| 33 | 14.01.2025 | 14.03.2025 | 59 | ⚠️ Winter-Frühling |
| 37 | 21.01.2025 | 21.03.2025 | 59 | ⚠️ Winter-Frühling |
| 41 | 04.05.2026 | 02.07.2026 | 59 | ⚠️ Vor-Sommer |

---

## 🎯 DRINGLICHKEIT

### Priorität 1 (SOFORT):
- ❌ **Fix für die 3 September→Oktober Fälle** (35 Tage)
- Diese Familien müssen **jetzt** benachrichtigt und umgeplant werden

### Priorität 2 (Kurzfristig):
- ⚠️ **Code-Fix für Jahresübergang** implementieren
- `lastCookingDate` aus Vorjahr in Planung einbeziehen
- Minimum-Abstand härter durchsetzen

### Priorität 3 (Mittelfristig):
- 📊 **Analyse der Winter-Frühling Kompression**
- Warum so viele 56-59 Tage Abstände im Januar-März?
- Eventuell mehr verfügbare Tage in diesem Zeitraum generieren

---

## ✅ EMPFOHLENE NÄCHSTE SCHRITTE

1. **Sofortmaßnahme:** Die 3 kritischen Familien (13, 26, 46) kontaktieren und Oktober-Dienste verschieben
2. **Code Review:** `CookingPlanGenerator.php` überarbeiten mit Fokus auf Jahresübergang
3. **Migration:** Eventuell Datenstruktur erweitern falls `lastCookingDate` nicht richtig genutzt wird
4. **Re-Generation:** Plan 2025/26 neu generieren nach Code-Fix
5. **Testing:** Neue Analyse durchführen und sicherstellen dass keine < 60 Tage Abstände mehr existieren

**Status:** 🚨 DRINGEND - Kritische Fairness-Verstöße müssen behoben werden!
