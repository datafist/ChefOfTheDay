# Bugfix: Vorjahr-Löschung auch mit Verfügbarkeiten erlauben

## Problem
Ursprünglich konnte ein Kita-Jahr nicht gelöscht werden, sobald Eltern Verfügbarkeiten eingetragen hatten - auch nicht das Vorjahr. Dies verhinderte das Aufräumen alter Daten.

## Lösung
Differenzierte Lösch-Logik basierend auf Jahr-Typ:

### Vorjahr (Jahr < Aktives Jahr)
- ✅ **Kann gelöscht werden** sobald Folgeplan existiert
- **Auch wenn**: Verfügbarkeiten + Assignments vorhanden
- **Grund**: Daten werden nur bis zur Plan-Generierung benötigt
- **Vorteil**: Datenbank kann aufgeräumt werden

### Aktuelles/Zukünftiges Jahr
- ❌ **Kann NICHT gelöscht werden** wenn Verfügbarkeiten existieren
- **Grund**: Datenschutz - Eltern haben bereits Zeit investiert
- **Vorteil**: Verhindert versehentlichen Datenverlust

## Implementierung

### Hierarchie der Prüfungen

```
1. Ist es das aktive Jahr?
   └─→ ❌ Nicht löschbar

2. Ist es ein Vorjahr?
   ├─→ Hat das aktive Jahr einen Plan?
   │   ├─→ Ja: ✅ Löschbar (auch mit Verfügbarkeiten!)
   │   └─→ Nein: ❌ Nicht löschbar
   
3. Ist es ein zukünftiges/anderes Jahr?
   └─→ Haben Eltern Verfügbarkeiten eingetragen?
       ├─→ Ja: ❌ Nicht löschbar
       └─→ Nein: ✅ Löschbar
```

## Änderungen

### Dateien

1. **src/Controller/Admin/KitaYearController.php**
   - `index()`: Angepasste Prüflogik mit Jahr-Typ-Unterscheidung
   - `delete()`: Vorjahr-Sonderbehandlung (keine Verfügbarkeits-Prüfung)

2. **FEATURE_YEAR_DELETION_PROTECTION.md**
   - Aktualisierte Dokumentation
   - Neue Szenarien für Vorjahr-Löschung
   - Erweiterte Code-Beispiele

3. **tests/Controller/Admin/KitaYearDeletionProtectionTest.php**
   - Neuer Test: `testPastYearWithAvailabilitiesCanBeDeletedIfCurrentPlanExists()`
   - Umbenannter Test: `testFutureYearWithAvailabilitiesCannotBeDeleted()`

## Beispiel-Workflow

### September 2024
```
Jahr 2023/2024 (Vorjahr):
  - 44 Verfügbarkeiten
  - 220 Assignments
  - Status: ❌ Nicht löschbar (kein Plan für 2024/2025)

Jahr 2024/2025 (aktiv):
  - Eltern tragen Verfügbarkeiten ein
  - Status: ❌ Nicht löschbar (aktiv)
```

### Oktober 2024 - Nach Plan-Generierung
```
Jahr 2023/2024 (Vorjahr):
  - 44 Verfügbarkeiten
  - 220 Assignments
  - Status: ✅ Löschbar! (Plan für 2024/2025 existiert)

Jahr 2024/2025 (aktiv):
  - 44 Verfügbarkeiten
  - 220 Assignments (Plan generiert)
  - Status: ❌ Nicht löschbar (aktiv)
```

### Januar 2025 - Nach Aufräumen
```
Jahr 2023/2024:
  - ✅ Gelöscht (Datenbank aufgeräumt)

Jahr 2024/2025 (aktiv):
  - 44 Verfügbarkeiten
  - 220 Assignments
  - Status: ❌ Nicht löschbar (aktiv)
```

## UI-Anzeige

### Vorjahr MIT Plan im Folgejahr
```
[Löschen] ← Klickbar, trotz vorhandener Daten
```

### Vorjahr OHNE Plan im Folgejahr
```
[🔒 Gesperrt]
Plan für 2025/2026 muss erst generiert werden
```

### Zukünftiges Jahr mit Verfügbarkeiten
```
[🔒 Gesperrt]
Eltern haben bereits Verfügbarkeiten eingetragen (5 Einträge)
```

## Vorteile

✅ **Datenbank-Hygiene**: Alte Daten können aufgeräumt werden  
✅ **Datenschutz**: Aktuelle Daten bleiben geschützt  
✅ **Fairness-Algorithmus**: Vorjahr wird benötigt bis Folgeplan existiert  
✅ **Logische Trennung**: Unterschiedliche Regeln für verschiedene Jahr-Typen  
✅ **Benutzerfreundlich**: Klare Kommunikation warum Löschung möglich/nicht möglich

## Testing

```bash
# Alle Tests ausführen
php bin/phpunit tests/Controller/Admin/KitaYearDeletionProtectionTest.php

# Einzelner Test
php bin/phpunit tests/Controller/Admin/KitaYearDeletionProtectionTest.php --filter testPastYearWithAvailabilitiesCanBeDeletedIfCurrentPlanExists
```

## Datum
5. Oktober 2025
