# Bugfix: Neue Familien in späteren Jahren werden fair behandelt

**Datum:** 2025-10-05  
**Status:** ✅ Behoben  
**Priorität:** Hoch  
**Bezug:** Erweiterung von `BUGFIX_NEW_FAMILIES_OVERLOAD.md`

## Problem

Die erste Lösung für neue Familien verwendete einen **fixen Default-Wert**:

```php
// ALTE LÖSUNG - UNVOLLSTÄNDIG:
$defaultLastYearCount = $family->isSingleParent() ? 3 : 4;
$lastYearCount = $lastYearCookings[$partyId]?->getCookingCount() ?? $defaultLastYearCount;
```

**Problem:** Der fixe Default-Wert (4 für Paare) funktioniert nur im **ersten Jahr** gut, aber nicht in späteren Jahren!

## Szenario-Analyse

### Jahr 1-2: Funktioniert ✅
```
Jahr 1:
- Neue Familie: default = 4, totalLoad = 4 + 0 = 4
- Bestehende:   last = 5,    totalLoad = 5 + 0 = 5
→ Neue Familie fair behandelt ✅

Jahr 2:
- Neue Familie hat jetzt LastYearCooking (5 Dienste)
- Funktioniert normal ✅
```

### Jahr 3+: Problem! ❌

```
Jahr 3 - Neue Familie kommt dazu:
- Neue Familie: default = 4,  totalLoad = 4 + 0 = 4
- Familie A:    last = 5,     totalLoad = 5 + 0 = 5
- Familie B:    last = 4,     totalLoad = 4 + 0 = 4

→ Neue Familie startet gleich wie Familie B
→ ABER: Familie B hat bereits ein Jahr gekocht!
```

### Jahr 10: Problem wird größer! ❌❌

```
Jahr 10 - Neue Familie:
- default = 4, totalLoad = 4

Jahr 10 - Etablierte Familien:
- Alle haben 5-10 Jahre Geschichte
- Durchschnitt letztes Jahr: 4-5
- totalLoad = 4-5

→ Neue Familie wird BEVORZUGT!
→ Etablierte Familien werden benachteiligt!
```

## Ursache

Der **fixe Default-Wert (4)** ignoriert:
1. Die tatsächliche Anzahl der Familien
2. Die verfügbaren Kochdienst-Tage
3. Die aktuelle Verteilungs-Erwartung

**Beispiel:**
- Bei 40 Familien und 200 Tagen: Erwartung = 5 Dienste
- Bei 50 Familien und 200 Tagen: Erwartung = 4 Dienste
- Fixer Default = 4 ist nur in einem Fall fair!

## Lösung ✅

Verwende den **berechneten Erwartungswert** (`$cookingRequirements`) als Default:

```php
// NEUE LÖSUNG - KORREKT:
$defaultLastYearCountA = $cookingRequirements[$partyIdA];
$defaultLastYearCountB = $cookingRequirements[$partyIdB];

$lastYearCountA = $lastYearCookings[$partyIdA]?->getCookingCount() ?? $defaultLastYearCountA;
$lastYearCountB = $lastYearCookings[$partyIdB]?->getCookingCount() ?? $defaultLastYearCountB;
```

### Was ist `$cookingRequirements`?

```php
$cookingRequirements[$partyId] = berechneter fairer Erwartungswert
```

Dieser Wert wird **dynamisch berechnet** basierend auf:
- Anzahl der Familien
- Verfügbare Kochdienst-Tage (ohne Wochenenden/Ferien/Feiertage)
- Status (Alleinerziehend: ~3, Paare: ~4-5)

**Beispiele:**
```php
40 Familien, 200 Tage → Paare: 5 Dienste, Singles: 3 Dienste
50 Familien, 200 Tage → Paare: 4 Dienste, Singles: 2-3 Dienste
44 Familien, 220 Tage → Paare: 5 Dienste, Singles: 3 Dienste
```

## Effekt der Lösung

### Jahr 3 - Jetzt fair! ✅

```
Jahr 3 - Neue Familie:
- default = 5 (erwarteter Wert für Paare dieses Jahr)
- totalLoad = 5 + 0 = 5

Jahr 3 - Familie A (hatte viel letztes Jahr):
- last = 5, totalLoad = 5 + 0 = 5

Jahr 3 - Familie B (hatte wenig letztes Jahr):
- last = 4, totalLoad = 4 + 0 = 4

→ Familie B wird bevorzugt (Rotation!) ✅
→ Neue Familie startet neutral (wie A) ✅
→ Keine Bevorzugung neuer Familien ✅
```

### Jahr 10 - Konsistent fair! ✅

```
Jahr 10 - Neue Familie:
- default = 5 (erwarteter Wert)
- totalLoad = 5 + 0 = 5

Jahr 10 - Etablierte Familien:
- Durchschnitt last = 4-5
- totalLoad = 4-5 + 0 = 4-5

→ Neue Familie startet mit gleichem Erwartungswert ✅
→ Keine systematische Bevorzugung ✅
→ Rotation funktioniert über alle Jahre ✅
```

## Mathematische Fairness

### Alte Lösung (fixer Default = 4)

```
Bei 40 Familien, 200 Tagen:
- Erwartung: 5 Dienste pro Paar
- Neue Familie: default = 4 → BEVORZUGT! ❌
- Etablierte: last = 5 → BENACHTEILIGT! ❌
```

### Neue Lösung (dynamischer Default = Erwartung)

```
Bei 40 Familien, 200 Tagen:
- Erwartung: 5 Dienste pro Paar
- Neue Familie: default = 5 → NEUTRAL ✅
- Etablierte: last = 5 → NEUTRAL ✅
- Rotation basiert auf vorherigen Jahren ✅
```

## Code-Änderung

**Datei:** `src/Service/CookingPlanGenerator.php`  
**Zeilen:** ~427-434

```php
// Neue Familien starten mit ihrem fairen Erwartungswert
// Dies ist der gleiche Wert wie etablierte Familien bekommen sollten
$defaultLastYearCountA = $cookingRequirements[$partyIdA];
$defaultLastYearCountB = $cookingRequirements[$partyIdB];

$lastYearCountA = $lastYearCookings[$partyIdA]?->getCookingCount() ?? $defaultLastYearCountA;
$lastYearCountB = $lastYearCookings[$partyIdB]?->getCookingCount() ?? $defaultLastYearCountB;
```

## Testing

### Testfall 1: Neue Familie in Jahr 1
```
Kontext:
- 44 Familien, 220 Tage
- Erwartung: 5 Dienste pro Paar

Input:
- Familie: Paar, neu in Jahr 1
- LastYearCooking: Nicht vorhanden

Erwartetes Ergebnis:
- default = 5 (Erwartungswert)
- Bekommt 4-5 Dienste ✅
```

### Testfall 2: Neue Familie in Jahr 5
```
Kontext:
- 44 Familien, 220 Tage
- Erwartung: 5 Dienste pro Paar
- Etablierte Familien haben 4-5 Jahre Geschichte

Input:
- Familie: Paar, neu in Jahr 5
- LastYearCooking: Nicht vorhanden

Erwartetes Ergebnis:
- default = 5 (Erwartungswert)
- Bekommt 4-5 Dienste ✅
- KEINE Bevorzugung gegenüber etablierten Familien ✅
```

### Testfall 3: Neue Familie bei veränderter Familienzahl
```
Kontext:
- Jahr 1: 44 Familien → Erwartung: 5 Dienste
- Jahr 2: 50 Familien → Erwartung: 4 Dienste
- Neue Familie kommt in Jahr 2 dazu

Input:
- Familie: Paar, neu in Jahr 2
- LastYearCooking: Nicht vorhanden

Erwartetes Ergebnis:
- default = 4 (aktueller Erwartungswert für Jahr 2!)
- Bekommt 4 Dienste ✅
- Passt sich automatisch an veränderte Bedingungen an ✅
```

### Testfall 4: Alleinerziehende neue Familie
```
Kontext:
- 44 Familien, 220 Tage
- Erwartung: 3 Dienste für Alleinerziehende

Input:
- Familie: Alleinerziehend, neu in Jahr 3
- LastYearCooking: Nicht vorhanden

Erwartetes Ergebnis:
- default = 3 (Erwartungswert für Alleinerziehende)
- Bekommt genau 3 Dienste ✅
```

## Vorteile der dynamischen Lösung

| Aspekt | Fixer Default (4) | Dynamischer Default (Erwartung) |
|--------|-------------------|----------------------------------|
| Jahr 1 | ✅ Funktioniert | ✅ Funktioniert |
| Jahr 5+ | ❌ Bevorzugt neue Familien | ✅ Neutral |
| Bei 40 Familien | ❌ Default zu niedrig | ✅ Passt sich an (5) |
| Bei 50 Familien | ✅ Default passt (4) | ✅ Passt sich an (4) |
| Alleinerziehende | ✅ Fest (3) | ✅ Dynamisch (2-3) |
| Langzeit-Fairness | ❌ Driftet ab | ✅ Stabil |

## Zusammenfassung

### Vorher (fixer Default = 4)
```
Jahr 1:  Neue Familie = 4 → OK ✅
Jahr 5:  Neue Familie = 4 → ZU NIEDRIG bei 40 Familien ❌
Jahr 10: Neue Familie = 4 → BEVORZUGT gegenüber Etablierten ❌
```

### Nachher (dynamischer Default = Erwartung)
```
Jahr 1:  Neue Familie = 5 (Erwartung) → Fair ✅
Jahr 5:  Neue Familie = 5 (Erwartung) → Fair ✅
Jahr 10: Neue Familie = 5 (Erwartung) → Fair ✅
Bei Änderung auf 50 Familien: = 4 (neue Erwartung) → Angepasst ✅
```

## Verwandte Dokumente

- `BUGFIX_NEW_FAMILIES_OVERLOAD.md` - Ursprüngliches Problem
- `FEATURE_MULTIYEAR_FAIRNESS.md` - Hauptdokumentation
- `QUICKSTART_FAIRNESS.md` - Admin-Anleitung

## Autor

GitHub Copilot
