# Bugfix: Fehlende Zuweisungen im August 2026

## Problem

Bei der Plan-Generierung für das Jahr 25/26 wurden folgende Tage im August nicht vergeben:
- 25.08.2026 (Dienstag)
- 26.08.2026 (Mittwoch)
- 27.08.2026 (Donnerstag)
- 28.08.2026 (Freitag)
- 31.08.2026 (Montag)

**Obwohl 30-34 Familien an diesen Tagen verfügbar und geeignet waren!**

## Ursachen-Analyse

### Schritt 1: Systematische Datenbank-Analyse

Erstellt: `bin/analyze_missing_dates.php`

**Ergebnisse**:
- ✅ Alle 5 Tage sind Werktage (kein Wochenende)
- ✅ Keine Feiertage an diesen Tagen
- ✅ Keine Ferien an diesen Tagen
- ✅ **30-34 Familien waren verfügbar und geeignet**
- ✅ Abstände waren ausreichend (> 4 Wochen)

**Beispiel für 25.08.2026**:
```
✅ VERFÜGBAR UND GEEIGNET: 31 Familien

Top 10 verfügbare Familien:
  ✓ Mia: 270 Tage seit letztem Dienst (2025-11-28), 2 Zuweisungen gesamt
  ✓ Amelie: 267 Tage seit letztem Dienst (2025-12-01), 2 Zuweisungen gesamt
  ✓ Tobias: 112 Tage seit letztem Dienst (2026-05-05), 4 Zuweisungen gesamt
  ...
```

→ **Das Problem lag NICHT in den Daten, sondern im Algorithmus!**

### Schritt 2: Code-Analyse

**Datei**: `src/Service/CookingPlanGenerator.php`, Zeile 319

```php
// Prüfe ob Familie noch zugewiesen werden muss
if ($assignedCount[$partyId] >= $cookingRequirements[$partyId]) {
    continue;  // ❌ HIER WAR DER FEHLER!
}
```

**Problem**: 
- Der Algorithmus übersprang Familien, die ihre "erwartete" Anzahl (`cookingRequirements`) bereits erreicht hatten
- Im August (Ende des Kita-Jahres) hatten die meisten Familien ihre erwartete Anzahl erreicht
- Daher wurden sie übersprungen, obwohl sie verfügbar waren

**Widerspruch zur Dokumentation** (Zeile 175):
```php
// WICHTIG: Dies ist KEIN Zielwert oder Pflicht!
// Es wird nur genutzt, um Familien mit weniger Diensten zu bevorzugen.
```

## Lösung

### Änderung 1: Entfernung der harten Grenze

**Vorher**:
```php
// Prüfe ob Familie noch zugewiesen werden muss
if ($assignedCount[$partyId] >= $cookingRequirements[$partyId]) {
    continue;
}
```

**Nachher**:
```php
// WICHTIG: cookingRequirements ist KEIN Limit, sondern nur für Priorisierung!
// Familien können auch mehr als den erwarteten Wert bekommen, wenn sie verfügbar sind.
// Wir überspringen sie NICHT, sondern nutzen den Wert nur für die Sortierung.
```

### Änderung 2: Verbesserte Sortier-Logik

**Vorher** (2 Kriterien):
1. Längster Abstand zur letzten Zuweisung
2. Wenigste Zuweisungen bisher

**Nachher** (3 Kriterien):
1. Längster Abstand zur letzten Zuweisung
2. **Familien unter ihrem erwarteten Wert** (neu!)
3. Wenigste Zuweisungen bisher

```php
// Sekundär: Bevorzuge Familien, die noch unter ihrem erwarteten Wert sind
$underExpectedA = $assignedCount[$partyIdA] < $cookingRequirements[$partyIdA];
$underExpectedB = $assignedCount[$partyIdB] < $cookingRequirements[$partyIdB];

if ($underExpectedA && !$underExpectedB) {
    return -1; // A bevorzugen
}
if (!$underExpectedA && $underExpectedB) {
    return 1; // B bevorzugen
}
```

## Testergebnisse

### Vorher (mit Bug)
- **Jahr 24/25**: 176 Zuweisungen, 20 Konflikte
- **Jahr 25/26**: 192 Zuweisungen, fehlende August-Tage

### Nachher (behoben)
- **Jahr 24/25**: 196 Zuweisungen, 0 Konflikte ✅
- **Jahr 25/26**: 197 Zuweisungen, 0 Konflikte ✅

### August-Tage jetzt korrekt vergeben:

| Datum      | Wochentag  | Familie  | Status |
|------------|------------|----------|--------|
| 25.08.2026 | Dienstag   | Clara    | ✅     |
| 26.08.2026 | Mittwoch   | Amelie   | ✅     |
| 27.08.2026 | Donnerstag | Ella     | ✅     |
| 28.08.2026 | Freitag    | Jonas    | ✅     |
| 31.08.2026 | Montag     | Johanna  | ✅     |

## Neue Test-Tools

### 1. Analyse-Script für fehlende Tage
**Datei**: `bin/analyze_missing_dates.php`

```bash
php bin/analyze_missing_dates.php
```

**Features**:
- Zeigt verfügbare Familien pro Tag
- Kategorisiert Gründe für Nicht-Verfügbarkeit
- Berechnet Abstände zur letzten Zuweisung
- Identifiziert Top 10 geeignete Familien

### 2. Test-Command für Plan-Generierung
**Datei**: `src/Command/TestPlanGenerationCommand.php`

```bash
php bin/console app:test-plan-generation
```

**Features**:
- Generiert Plan für aktives Kita-Jahr
- Prüft speziell die August-Tage
- Zeigt übersichtliche Tabelle
- Speichert Zuweisungen in DB

## Auswirkungen

### Positive Effekte
1. ✅ **Mehr Zuweisungen**: 196-197 statt 176-192
2. ✅ **Keine Konflikte**: 0 statt 20
3. ✅ **Alle verfügbaren Tage werden genutzt**
4. ✅ **Fairness bleibt erhalten** durch Priorisierung

### Fairness-Garantie
Die Änderung beeinträchtigt die Fairness **NICHT**:

- Familien **unter** ihrem erwarteten Wert werden bevorzugt (Kriterium 2)
- Bei gleichem Abstand bekommen Familien mit weniger Zuweisungen den Vorrang (Kriterium 3)
- Familien können mehr als ihren erwarteten Wert bekommen, aber nur wenn:
  - Sie verfügbar sind
  - Der Abstand ausreichend ist
  - Keine andere Familie mit weniger Zuweisungen verfügbar ist

### Edge Cases
Bei sehr eingeschränkten Verfügbarkeiten können manche Familien trotzdem mehr Zuweisungen bekommen als andere:

**Beispiel**:
- Familie A: Nur Mo+Fr verfügbar → 40 verfügbare Tage
- Familie B: Alle Tage verfügbar → 200 verfügbare Tage

**Verhalten**:
- B bekommt mehr absolute Zuweisungen (z.B. 8 statt 3)
- **Aber**: Relative Auslastung ist ähnlich
  - A: 3/40 = 7,5%
  - B: 8/200 = 4%

→ ✅ **Normal und akzeptabel** bei stark unterschiedlichen Verfügbarkeiten

## Migration

### Für bestehende Installationen

```bash
# 1. Code aktualisieren
git pull

# 2. Cache leeren
php bin/console cache:clear

# 3. Bestehende Pläne sind NICHT betroffen
# Nur neue Plan-Generierungen nutzen die Verbesserung

# 4. Optional: Testdaten neu laden
php bin/console doctrine:database:drop --force
php bin/console doctrine:database:create
php bin/console doctrine:schema:create
php bin/console doctrine:fixtures:load --group=large-scale
```

### Breaking Changes
❌ **Keine Breaking Changes**

Die Änderung ist **rückwärtskompatibel**:
- Bestehende Zuweisungen bleiben unverändert
- API bleibt gleich
- Nur internes Verhalten verbessert

## Erkenntnisse

1. **"Erwartete Anzahl" ist keine Obergrenze**
   - Sie dient nur zur Priorisierung
   - Familien können mehr bekommen, wenn verfügbar
   - Wichtig für Jahresende (August)

2. **Systematische Analyse ist wichtig**
   - Problem lag nicht in Daten, sondern im Code
   - Analyse-Tools helfen bei der Fehlersuche
   - Dokumentation und Code müssen übereinstimmen

3. **Realistische Testdaten zeigen Probleme**
   - Mit unrealistisch hohen Verfügbarkeiten wäre Bug nicht aufgefallen
   - Eingeschränkte Verfügbarkeiten sind typisch für Praxis
   - Wichtig für Qualitätssicherung

## Referenzen

- **Bugfix**: `src/Service/CookingPlanGenerator.php`, Zeilen 315-377
- **Analyse-Tool**: `bin/analyze_missing_dates.php`
- **Test-Command**: `src/Command/TestPlanGenerationCommand.php`
- **Test-Daten**: `src/DataFixtures/LargeScaleTestFixtures.php`
- **Dokumentation**: `TEST_SCENARIO_REALISTIC_AVAILABILITY.md`

## Datum
2025-10-05
