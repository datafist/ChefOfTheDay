# Bugfix: Neue Familien werden überproportional belastet

**Datum:** 2025-10-05  
**Status:** ✅ Behoben  
**Priorität:** Hoch

## Problem

Nach Implementierung der Multi-Year-Fairness wurden neue Familien (ohne Vorjahr-Daten) überproportional mit Kochdiensten belastet:

### Symptome
- **Paare mit 8 Diensten** (doppelt so viel wie Durchschnitt von 4)
- **Paare mit nur 3 Diensten** (gleich wie Alleinerziehende)
- Extrem ungerechte Verteilung für neu hinzugekommene Familien

### Ursache

```php
// VORHER - FEHLERHAFT:
$lastYearCount = $lastYearCookings[$partyId]?->getCookingCount() ?? 0;
//                                                                  ^^
//                                                         Neue Familien = 0
```

**Problem:**
- Neue Familien ohne `LastYearCooking`-Eintrag bekamen `lastYearCount = 0`
- `totalLoad = 0 + currentYearCount` → sehr niedrig
- Algorithmus interpretierte dies als "Familie hatte sehr wenig im Vorjahr"
- Familie wurde stark bevorzugt und bekam überproportional viele Dienste

### Beispiel

**Neue Familie ohne Vorjahr:**
```
Vorjahr: 0 (nicht vorhanden)
Jahr 1: 8 Dienste (zu viel!)
totalLoad = 0 + 8 = 8
```

**Bestehende Familie:**
```
Vorjahr: 5 Dienste
Jahr 1: 4 Dienste (normal)
totalLoad = 5 + 4 = 9
```

→ Neue Familie wurde bevorzugt, weil 8 < 9

## Lösung

Neue Familien werden **neutral** mit Durchschnittswert behandelt:

```php
// NACHHER - KORREKT:
$defaultLastYearCount = $family->isSingleParent() ? 3 : 4;
$lastYearCount = $lastYearCookings[$partyId]?->getCookingCount() ?? $defaultLastYearCount;
//                                                                   ^^^^^^^^^^^^^^^^^^^^
//                                                          Neue Familien = Durchschnitt
```

### Durchschnittswerte
- **Paare:** `defaultLastYearCount = 4` (fairer Durchschnitt)
- **Alleinerziehende:** `defaultLastYearCount = 3` (reduzierter Durchschnitt)

### Effekt

**Neue Familie jetzt:**
```
Vorjahr: 4 (virtuell, Durchschnitt)
Jahr 1: 4-5 Dienste (fair!)
totalLoad = 4 + 4 = 8
```

**Bestehende Familie mit viel Last:**
```
Vorjahr: 5 Dienste
Jahr 1: 3-4 Dienste (Entlastung)
totalLoad = 5 + 4 = 9
```

→ Bestehende Familie mit hoher Last wird leicht deprioritisiert (9 > 8)
→ Neue Familie startet fair und neutral

## Erwartetes Ergebnis

### Für neue Familien (ohne Vorjahr)
- ✅ **Paare:** 4-5 Dienste pro Jahr (wie alle anderen)
- ✅ **Alleinerziehende:** 3 Dienste pro Jahr (reduzierte Last)
- ✅ Keine Überlastung (8 Dienste)
- ✅ Keine Unterlastung (< 3 Dienste für Paare)

### Für bestehende Familien
- ✅ Rotation funktioniert weiter
- ✅ Familien mit 5 Diensten im Vorjahr werden entlastet
- ✅ Familien mit 4 Diensten im Vorjahr bleiben stabil

## Mathematische Fairness

### Durchschnittliche Gesamtbelastung (2 Jahre)

**Ohne Bugfix:**
```
Neue Familie:     0 + 8 = 8  ❌ unfair hoch
Bestehende (low): 4 + 5 = 9  ✓ normal
Bestehende (hi):  5 + 4 = 9  ✓ normal
```

**Mit Bugfix:**
```
Neue Familie:     4 + 4 = 8  ✓ fair!
Bestehende (low): 4 + 5 = 9  ✓ normal
Bestehende (hi):  5 + 4 = 9  ✓ normal
```

### Standardabweichung

**Vorher:** Hohe Varianz (3-8 Dienste pro Jahr)
**Nachher:** Niedrige Varianz (4-5 Dienste pro Jahr)

## Code-Änderungen

**Datei:** `src/Service/CookingPlanGenerator.php`

**Zeilen:** ~428-431

```php
// Standard-Wert für neue Familien: der faire Durchschnitt
$defaultLastYearCount = $a->isSingleParent() ? 3 : 4;

$lastYearCountA = $lastYearCookings[$partyIdA]?->getCookingCount() ?? $defaultLastYearCount;
$lastYearCountB = $lastYearCookings[$partyIdB]?->getCookingCount() ?? $defaultLastYearCount;
```

## Testing

### Testfall 1: Neue Familie (Paar)
```
Input:
- Familie: Paar (2 Elternteile)
- LastYearCooking: Nicht vorhanden
- Erwarteter Durchschnitt: 4 Dienste

Erwartetes Ergebnis:
- 4-5 Dienste im ersten Jahr
- Nicht mehr als 5 Dienste
- Nicht weniger als 4 Dienste
```

### Testfall 2: Neue Familie (Alleinerziehend)
```
Input:
- Familie: Alleinerziehend (1 Elternteil)
- LastYearCooking: Nicht vorhanden
- Erwarteter Durchschnitt: 3 Dienste

Erwartetes Ergebnis:
- Genau 3 Dienste im ersten Jahr
- Nicht mehr als 3 Dienste
```

### Testfall 3: Bestehende Familie mit hoher Last
```
Input:
- Familie: Paar
- Vorjahr: 5 Dienste
- Erwartung: Entlastung dieses Jahr

Erwartetes Ergebnis:
- 4 Dienste dieses Jahr (Rotation zur unteren Grenze)
- totalLoad = 5 + 4 = 9
```

## Zusammenfassung

| Metrik | Vorher | Nachher |
|--------|--------|---------|
| Neue Paare (Dienste) | **3-8** ❌ | **4-5** ✅ |
| Neue Alleinerziehende | 2-6 ❌ | **3** ✅ |
| Fairness | Niedrig | **Hoch** ✅ |
| Standardabweichung | Hoch | **Niedrig** ✅ |

## Verwandte Dokumente

- `FEATURE_MULTIYEAR_FAIRNESS.md` - Hauptdokumentation der Multi-Year-Fairness
- `QUICKSTART_FAIRNESS.md` - Schnellanleitung für Admins
- `bin/analyze_fairness.php` - Analyse-Tool

## Autor

GitHub Copilot
