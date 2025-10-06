# Feature: Erweiterte Lösch-Schutz für Kita-Jahre

## Übersicht
Intelligenter Lösch-Schutz für Kita-Jahre basierend auf Status und vorhandenen Daten. Vorjahre können nach Plan-Generierung gelöscht werden, aktuelle/zukünftige Jahre nur wenn keine Eltern-Daten existieren.

## Implementierung

### Schutz-Mechanismen (Hierarchie)

Ein Kita-Jahr kann **nicht gelöscht** werden, wenn:

1. **Es das aktive Jahr ist**
   - Grund: Das aktive Jahr wird von allen Funktionen der App verwendet
   - Fehlermeldung: "Das aktive Kita-Jahr kann nicht gelöscht werden."

2. **Es ist ein Vorjahr und das aktive Jahr hat noch keinen Plan**
   - Grund: Vorjahr-Daten werden für faire Verteilung im neuen Jahr benötigt
   - Fehlermeldung: "Das Vorjahr kann erst gelöscht werden, nachdem der Kochplan für das aktuelle Jahr (YYYY/YYYY) generiert wurde."
   - ✅ **WICHTIG**: Vorjahr kann gelöscht werden, sobald der Folgeplan existiert - auch wenn Verfügbarkeiten vorhanden sind!

3. **Es ist kein Vorjahr und Eltern haben bereits Verfügbarkeiten eingetragen**
   - Grund: Datenverlust verhindern - Eltern haben bereits Zeit investiert
   - Fehlermeldung: "Das Kita-Jahr kann nicht gelöscht werden, da bereits X Verfügbarkeits-Einträge von Eltern vorhanden sind."
   - Prüfung: Anzahl der `Availability`-Einträge für dieses Jahr
   - Betrifft: Aktuelles inaktives Jahr oder zukünftige Jahre

## UI-Verhalten

### Kita-Jahre-Übersicht (`/admin/kita-year`)

**Wenn Löschung möglich:**
```
[Aktivieren]  [Löschen]
```

**Wenn Löschung nicht möglich:**
```
[🔒 Gesperrt]
Eltern haben bereits Verfügbarkeiten eingetragen (X Einträge)
```

Der Button ist:
- Deaktiviert (disabled)
- Ausgegraut (opacity: 0.5)
- Mit Tooltip (title-Attribut zeigt Grund)
- Nicht anklickbar (cursor: not-allowed)

## Anwendungsfälle

### Szenario 1: Neues Jahr erstellt, noch keine Einträge
```
Jahr 2026/2027: Neu erstellt, keine Einträge
Verfügbarkeiten: 0
Status: ✅ Kann gelöscht werden
```

### Szenario 2: Aktuelles Jahr - Eltern beginnen mit Eintragungen
```
Jahr 2025/2026: 5 Familien haben Verfügbarkeiten eingetragen
Verfügbarkeiten: 5
Status: ❌ Gesperrt - "Eltern haben bereits Verfügbarkeiten eingetragen (5 Einträge)"
```

### Szenario 3: Aktuelles Jahr - Plan generiert
```
Jahr 2025/2026: Plan generiert, 44 Familien mit Diensten
Verfügbarkeiten: 44
Assignments: 220
Status: ❌ Gesperrt - "Eltern haben bereits Verfügbarkeiten eingetragen (44 Einträge)"
```

### Szenario 4: Vorjahr mit Daten, aber Folgeplan existiert ⭐ NEU
```
Jahr 2024/2025: Vorjahr mit allen Daten
Aktives Jahr: 2025/2026 mit generiertem Plan
Verfügbarkeiten (2024/2025): 44
Assignments (2025/2026): 220
Status: ✅ Kann gelöscht werden - Plan für Folgejahr existiert
```

### Szenario 5: Vorjahr ohne Folgeplan
```
Jahr 2024/2025: Vorjahr mit allen Daten
Aktives Jahr: 2025/2026 OHNE Plan
Verfügbarkeiten (2024/2025): 44
Assignments (2025/2026): 0
Status: ❌ Gesperrt - "Plan für 2025/2026 muss erst generiert werden"
```

## Technische Details

### Controller-Prüfung (KitaYearController.php)

#### In `index()` - Anzeige

**Logik (hierarchisch):**
```php
// 1. Aktives Jahr → nicht löschbar
if ($year->isActive()) {
    $canDelete = false;
    $reason = 'Aktives Jahr kann nicht gelöscht werden';
}
// 2. Vorjahr → löschbar wenn Folgeplan existiert (Verfügbarkeiten egal!)
elseif ($activeYear && $year->getStartDate() < $activeYear->getStartDate()) {
    $activePlanExists = $entityManager->getRepository(\App\Entity\CookingAssignment::class)
        ->count(['kitaYear' => $activeYear]) > 0;
    
    if (!$activePlanExists) {
        $canDelete = false;
        $reason = 'Plan für ' . $activeYear->getYearString() . ' muss erst generiert werden';
    }
}
// 3. Zukünftiges Jahr → nicht löschbar wenn Verfügbarkeiten
elseif ($activeYear && $year->getStartDate() > $activeYear->getStartDate()) {
    $availabilityCount = $entityManager->getRepository(\App\Entity\Availability::class)
        ->count(['kitaYear' => $year]);
    
    if ($availabilityCount > 0) {
        $canDelete = false;
        $reason = 'Eltern haben bereits Verfügbarkeiten eingetragen (' . $availabilityCount . ' Einträge)';
    }
}
// 4. Sonstige Jahre → nicht löschbar wenn Verfügbarkeiten
else {
    $availabilityCount = $entityManager->getRepository(\App\Entity\Availability::class)
        ->count(['kitaYear' => $year]);
    
    if ($availabilityCount > 0) {
        $canDelete = false;
        $reason = 'Eltern haben bereits Verfügbarkeiten eingetragen (' . $availabilityCount . ' Einträge)';
    }
}
```

#### In `delete()` - Validierung

**Backend-Validierung (Sicherheit):**
```php
// 1. Aktives Jahr prüfen
if ($kitaYear->isActive()) {
    $this->addFlash('error', 'Das aktive Kita-Jahr kann nicht gelöscht werden.');
    return $this->redirectToRoute('admin_kita_year_index');
}

// 2. Vorjahr: Nur Plan-Prüfung, KEINE Verfügbarkeits-Prüfung!
if ($activeYear && $kitaYear->getStartDate() < $activeYear->getStartDate()) {
    $activePlanExists = $entityManager->getRepository(\App\Entity\CookingAssignment::class)
        ->count(['kitaYear' => $activeYear]) > 0;
    
    if (!$activePlanExists) {
        $this->addFlash('error', 
            'Das Vorjahr kann erst gelöscht werden, nachdem der Kochplan für das aktuelle Jahr (' 
            . $activeYear->getYearString() . ') generiert wurde.'
        );
        return $this->redirectToRoute('admin_kita_year_index');
    }
    // Vorjahr mit Plan kann gelöscht werden (auch mit Verfügbarkeiten)
}
// 3. Andere Jahre: Verfügbarkeits-Prüfung
else {
    $availabilityCount = $entityManager->getRepository(\App\Entity\Availability::class)
        ->count(['kitaYear' => $kitaYear]);
    
    if ($availabilityCount > 0) {
        $this->addFlash('error', 
            'Das Kita-Jahr kann nicht gelöscht werden, da bereits ' . $availabilityCount 
            . ' Verfügbarkeits-Einträge von Eltern vorhanden sind.'
        );
        return $this->redirectToRoute('admin_kita_year_index');
    }
}
```

### Datenbank-Entitäten

**Availability.php:**
- Verbindet `Party` (Familie) mit `KitaYear`
- Speichert verfügbare Termine (`availableDates`)
- Unique Constraint: Eine Verfügbarkeit pro Familie und Jahr
- Cascade: Bei Löschung des Jahres werden auch Verfügbarkeiten gelöscht

## Sicherheit

### Mehrfach-Validierung
Die Prüfung erfolgt an **zwei Stellen**:

1. **In der Übersicht** (`index()`):
   - Button wird gar nicht erst angezeigt
   - Benutzerfreundliche Fehlermeldung unter dem Button

2. **Bei der Löschung** (`delete()`):
   - Zusätzliche Server-seitige Validierung
   - Schutz vor manipulierten POST-Requests
   - CSRF-Token-Schutz

### CSRF-Schutz
Jede Lösch-Aktion ist durch einen CSRF-Token geschützt:
```php
if ($this->isCsrfTokenValid('delete'.$kitaYear->getId(), $request->request->get('_token')))
```

## Workflow

### Normaler Jahresübergang

1. **August 2025**: Admin erstellt Jahr 2025/2026
2. **September 2025**: Admin aktiviert Jahr 2025/2026
3. **September 2025**: Eltern tragen Verfügbarkeiten ein
   - ⚠️ Ab jetzt: Jahr 2025/2026 ist **nicht mehr löschbar**
   - ℹ️ Vorjahr 2024/2025 ist noch **nicht löschbar** (kein Plan für 2025/2026)
4. **Oktober 2025**: Admin generiert Plan für 2025/2026
5. **Oktober 2025**: Admin kann nun Vorjahr 2024/2025 löschen ✅
   - **Grund**: Plan für 2025/2026 existiert
   - **Auch wenn**: 2024/2025 noch 44 Verfügbarkeiten + 220 Assignments hat
   - **Zweck**: Datenbank aufräumen, alte Daten entfernen

### Korrektur eines Fehlers

**Szenario**: Versehentlich falsches Jahr erstellt

```
Admin erstellt: 2026/2027 (falsch, sollte 2025/2026 sein)
Eltern: Noch keine Einträge
Status: ✅ Kann sofort gelöscht werden
```

**Szenario**: Jahr mit Daten soll korrigiert werden

```
Admin erstellt: 2025/2026
Eltern: 3 Familien haben bereits eingetragen
Status: ❌ NICHT löschbar
Lösung: 
  1. Neues Jahr 2025/2026-korrigiert erstellen
  2. Eltern bitten, neu einzutragen
  3. Nach Abschluss altes Jahr manuell in DB löschen
```

## Migration bestehender Daten

Falls bereits Kita-Jahre mit Verfügbarkeiten existieren:
- ✅ Keine Migration nötig
- ✅ Automatische Prüfung funktioniert sofort
- ✅ Bestehende Jahre sind automatisch geschützt

## Testen

### Test-Szenario 1: Leeres Jahr
```bash
# Jahr ohne Verfügbarkeiten
1. Neues Jahr erstellen
2. Zur Übersicht gehen
3. Erwartet: Löschen-Button ist aktiv
```

### Test-Szenario 2: Jahr mit Verfügbarkeiten
```bash
# Jahr mit Eltern-Einträgen
1. Als Eltern anmelden
2. Verfügbarkeiten eintragen
3. Als Admin zur Jahres-Übersicht
4. Erwartet: Button "🔒 Gesperrt" + Meldung mit Anzahl
5. Versuch zu löschen (via manipuliertem POST)
6. Erwartet: Fehlermeldung + Umleitung
```

### Test-Szenario 3: Nach Plan-Generierung
```bash
# Jahr mit generiertem Plan
1. Plan generieren (erstellt Availabilities + Assignments)
2. Zur Jahres-Übersicht
3. Erwartet: Gesperrt wegen Verfügbarkeiten
4. Zählwert sollte Anzahl Familien entsprechen (ca. 44)
```

## Vorteile

✅ **Datenschutz**: Verhindert versehentlichen Verlust von aktuellen Eltern-Daten  
✅ **Benutzerfreundlich**: Klare Fehlermeldungen mit Grund und Anzahl  
✅ **Sicher**: Zweifache Validierung (UI + Server)  
✅ **Transparent**: Admin sieht sofort, warum Löschung möglich/nicht möglich ist  
✅ **Flexibel**: Leere Jahre können weiterhin problemlos gelöscht werden  
✅ **Aufräumen möglich**: Vorjahre können nach Plan-Generierung gelöscht werden  
✅ **Fairness-Schutz**: Vorjahr wird benötigt bis Folgeplan existiert (für faire Verteilung)

## Zusammenhang mit anderen Features

### Fairness-Algorithmus
- Benötigt Vorjahr-Daten (`LastYearCooking`)
- Vorjahr kann erst nach Plan-Generierung gelöscht werden
- Verhindert unfaire Verteilung

### Plan-Generierung
- Verwendet `Availability`-Daten
- Erstellt `CookingAssignment`-Einträge
- Sperrt Verfügbarkeitskalender für Eltern

### Eltern-Portal
- Eltern sehen ihre eingetragenen Verfügbarkeiten
- Nach Plan-Generierung: Nur noch zugewiesene Termine sichtbar
- Keine Bearbeitung mehr möglich

## Änderungsverlauf

**5. Oktober 2025 (v2)** - Anpassung für Vorjahr-Löschung
- ✅ Vorjahr kann gelöscht werden wenn Folgeplan existiert (auch mit Verfügbarkeiten)
- ✅ Nur aktuelle/zukünftige Jahre sind durch Verfügbarkeiten geschützt
- ✅ Dokumentation aktualisiert mit allen Szenarien

**5. Oktober 2025 (v1)** - Initiale Implementierung
- Schutz basierend auf `Availability`-Einträgen
- UI-Anzeige mit Grund und Anzahl
- Doppelte Validierung (UI + Server)
- Dokumentation erstellt
