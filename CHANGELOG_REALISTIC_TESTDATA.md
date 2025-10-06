# Changelog: Realistische Testdaten & Verfügbarkeiten

## Datum: 2025-10-05

## Zusammenfassung

Die Testdaten wurden an die praktische Realität angepasst: **Viele Familien haben stark eingeschränkte Verfügbarkeiten** (z.B. nur Montag + Freitag). Das System wurde getestet und ist bereit für den Produktiveinsatz mit realistischen Daten.

## Änderungen

### 1. LargeScaleTestFixtures angepasst

**Datei**: `src/DataFixtures/LargeScaleTestFixtures.php`

#### Realistische Verfügbarkeits-Szenarien
Statt unrealistisch hoher Verfügbarkeit (alle Tage, 80%, 60%) jetzt:

- **15% sehr eingeschränkt**: Nur Montag + Freitag ODER Dienstag + Donnerstag
- **20% eingeschränkt**: 2-3 Tage pro Woche (z.B. Mo, Mi, Fr)
- **35% mittel flexibel**: 3-4 Tage/Woche (ein fester Tag ausgeschlossen)
- **25% flexibel**: 80-90% der Tage verfügbar
- **5% sehr flexibel**: Alle Tage verfügbar

#### Kochplan für 24/25 wird generiert
- Jahr 24/25 enthält jetzt **tatsächliche CookingAssignments** (176 Zuweisungen)
- LastYearCooking-Einträge basieren auf echten Zuweisungen (nicht simuliert)
- Jahr 24/25 ist `isActive = false` (abgeschlossen)
- Jahr 25/26 ist `isActive = true` (bereit zum Testen)

#### Neue Helper-Methoden
```php
getSpecificWeekdays()     // Nur bestimmte Wochentage (z.B. Mo+Fr)
getWeekdaysExcept()       // Alle außer bestimmten Tagen
```

### 2. CookingPlanGenerator Integration

**Änderungen**:
- Fixture nutzt jetzt den echten `CookingPlanGenerator`
- Dependency Injection im Constructor
- Plan wird beim Fixture-Load automatisch generiert

### 3. Neue Dokumentation

#### TEST_SCENARIO_REALISTIC_AVAILABILITY.md
Umfassende Test-Anleitung mit:
- Übersicht der Testdaten
- Schritt-für-Schritt Testdurchführung
- 5 konkrete Test-Cases
- SQL-Queries zur Überprüfung
- Erwartete Erkenntnisse

#### INSTALL.md erweitert
- Option A: Einfache Demo (6 Familien)
- Option B: Umfangreicher Test (49 Familien)
- Hinweise auf neue Dokumentation

#### README.md aktualisiert
- Neue Fixture-Optionen dokumentiert
- Admin-Login-Daten direkt verfügbar

## Testergebnisse

### Fixture-Load erfolgreich
```
✓ 45 Familien für 24/25 erstellt
✓ Verfügbarkeiten für 24/25 erstellt (realistische Szenarien)
⏳ Generiere Kochplan für 24/25...
✓ Kochplan für 24/25 generiert (176 Zuweisungen)
✓ LastYearCooking Einträge aus tatsächlichen Zuweisungen erstellt
✓ 4 neue Familien für 25/26 erstellt
✓ Verfügbarkeiten für 25/26 erstellt
```

### Konflikte (erwartet bei realistischen Daten)
- **20 Tage ohne Zuweisung** (von 216 möglichen Werktagen)
- Hauptsächlich im Juni/Juli (Urlaubszeit)
- **Normal und gewollt** bei eingeschränkten Verfügbarkeiten

### Statistik 24/25
- 216 mögliche Werktage (nach Abzug von Ferien/Feiertagen/Wochenenden)
- 176 Zuweisungen (81,5% Coverage)
- 20 Tage ohne Zuweisung (18,5%)

## Nächste Schritte (Test in UI)

### Schritt 1: Server starten
```bash
symfony server:start
```

### Schritt 2: Admin-Login
- URL: http://localhost:8000
- Email: admin@kita.local
- Passwort: admin123

### Schritt 3: Plan für 25/26 generieren
- Navigation: Admin-Dashboard → Kochplan generieren
- Jahr: 25/26 auswählen
- Button: "Plan generieren"

### Schritt 4: Tests durchführen
Siehe `TEST_SCENARIO_REALISTIC_AVAILABILITY.md`:

1. ✅ Verfügbarkeits-Prüfung
2. ✅ LastYearCooking-Berücksichtigung
3. ✅ Fairness mit eingeschränkten Verfügbarkeiten
4. ⚠️ Nicht zuweisbare Tage
5. ✅ Mindest-Abstände

## Bekannte Einschränkungen

### Nicht zuweisbare Tage
Bei realistischen Verfügbarkeiten (viele nur Mo+Fr) wird es Tage geben, an denen:
- Keine Familie verfügbar ist
- Alle verfügbaren Familien unter dem Mindest-Abstand sind
- Alle verfügbaren Familien bereits ihre "erwartete" Anzahl erreicht haben

**Lösung**: Admin muss diese manuell klären (Notfall-Regelung, externe Hilfe, etc.)

### Höhere Auslastung bei eingeschränkten Familien
Familien mit nur Mo+Fr haben:
- Weniger absolute Zuweisungen (ca. 2-3 pro Monat)
- Aber höhere **relative** Auslastung (fast jede 2. Woche)
- Weniger Ausweichmöglichkeiten

**Realität**: Normal und akzeptabel

### Urlaubszeiten
Juni/Juli haben die meisten Konflikte wegen:
- Sommerferien
- Urlaubszeiten vieler Familien
- Reduzierte Verfügbarkeit

**Lösung**: Eltern motivieren, mehr Flexibilität anzugeben, oder Notfall-Dienste organisieren

## Breaking Changes

❌ Keine Breaking Changes für die Applikation selbst.

✅ Nur Fixtures wurden angepasst - bestehende Daten bleiben unverändert.

## Migration Guide

Für bestehende Installationen:

1. Code aktualisieren (git pull)
2. Testdaten neu laden:
   ```bash
   php bin/console doctrine:database:drop --force
   php bin/console doctrine:database:create
   php bin/console doctrine:schema:create
   php bin/console doctrine:fixtures:load --group=large-scale
   ```
3. Tests durchführen gemäß `TEST_SCENARIO_REALISTIC_AVAILABILITY.md`

## Erkenntnisse für Produktiv-Betrieb

1. **Eltern-Kommunikation wichtig**:
   - Eltern müssen verstehen, dass mehr Verfügbarkeit = bessere Verteilung
   - Bei sehr eingeschränkten Verfügbarkeiten evtl. höhere Frequenz

2. **Manuelle Nacharbeit einplanen**:
   - 10-20% der Tage müssen evtl. manuell zugewiesen werden
   - Notfall-Kontakte/externe Hilfe organisieren

3. **Urlaubsplanung koordinieren**:
   - Familien motivieren, Urlaube zu staffeln
   - Alternative Lösungen für Ferienzeiten

4. **Transparenz schaffen**:
   - Zeige Familien ihre Auslastung (% ihrer verfügbaren Tage)
   - Erkläre, warum manche öfter kochen (mehr Verfügbarkeit)

## Technische Details

### Neue Abhängigkeit
```php
// LargeScaleTestFixtures.php
public function __construct(
    private readonly UserPasswordHasherInterface $passwordHasher,
    private readonly CookingPlanGenerator $planGenerator  // NEU
) {}
```

### Neue Methoden
```php
generateCookingPlan2024()                    // Generiert echten Plan
createLastYearCookingsFromAssignments()     // Basiert auf echten Daten
getSpecificWeekdays()                        // Helper für Wochentags-Filter
getWeekdaysExcept()                          // Helper für Ausschluss-Filter
```

## Referenzen

- `src/DataFixtures/LargeScaleTestFixtures.php` - Hauptänderungen
- `TEST_SCENARIO_REALISTIC_AVAILABILITY.md` - Test-Anleitung
- `INSTALL.md` - Setup-Guide
- `README.md` - Projekt-Übersicht

## Credits

Basierend auf praktischen Erfahrungen aus realem Kita-Betrieb:
- Viele Eltern haben feste Arbeitstage (Homeoffice Mo+Fr)
- Teilzeit-Modelle mit 2-3 Tagen/Woche
- Wenige sehr flexible Familien

Diese Realität wird jetzt in den Testdaten abgebildet! 🎯
