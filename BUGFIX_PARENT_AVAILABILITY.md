# Bugfix: Elternkalender Verfügbarkeitsauswahl

## Problem
Im Elternkalender-Verfügbarkeitsauswahl konnten folgende Funktionen nicht genutzt werden:
1. **Verfügbarkeit speichern und persistieren** - Checkboxen wurden nicht korrekt übermittelt
2. **Schnellaktionen** - Weder "Alle auswählen/abwählen" noch die einzelnen Wochentag-Buttons funktionierten

## Ursache
- Das hidden input field `available_dates` wurde nicht korrekt initialisiert (war leer beim Laden)
- Die JavaScript Event-Listener wurden nicht richtig angebunden
- Die Wochentag-Buttons hatten keine korrekte Toggle-Funktionalität
- Das Datum-Parsing für die Wochentag-Erkennung hatte Timezone-Probleme
- **⚠️ HAUPTPROBLEM**: Das JavaScript wurde als externe Datei in `public/js/` geladen, was zu Race Conditions mit AssetMapper führte. Beim ersten Laden nach dem Login wurde das Script nicht geladen!

## Lösung

### 1. Template-Änderungen (`templates/parent/availability.html.twig`)
- ✅ Hidden input initial value auf leer gesetzt (wird durch JavaScript befüllt)
- ✅ Checkboxen haben jetzt zusätzliche CSS-Klasse `day-checkbox` für bessere Selektion
- ✅ Wochentag-Buttons haben jetzt ✓-Symbol für klarere UX

### 2. JavaScript-Änderungen (jetzt inline im Template!)
- ✅ **JavaScript wird inline geladen**: Keine externe Datei mehr → keine Race Conditions!
- ✅ **Initialisierung verbessert**: Hidden input wird beim Laden mit dem aktuellen Checkbox-Status befüllt
- ✅ **Checkbox-Listener**: Jede Checkbox aktualisiert bei Änderung das hidden input und den Counter
- ✅ **Wochentag-Buttons**: 
  - Toggle-Funktionalität implementiert (nicht nur select)
  - Datum-Parsing mit Timezone-Fix (`T12:00:00` statt `T00:00:00`)
  - Besseres Logging für Debugging
- ✅ **Alle auswählen/abwählen**: Funktioniert jetzt korrekt mit Logging
- ✅ **Form Submit**: Vor dem Absenden wird das hidden input nochmal aktualisiert
- ✅ **Counter**: Zeigt immer die aktuelle Anzahl ausgewählter Tage an

### 3. Controller bleibt unverändert
Der Controller (`src/Controller/Parent/ParentController.php`) war bereits korrekt implementiert und musste nicht angepasst werden.

## Testen

### Voraussetzungen
```bash
# Server muss laufen
symfony server:start -d

# Oder mit PHP built-in Server
php -S localhost:8000 -t public/
```

### Testschritte

1. **Login als Eltern**
   - URL: http://localhost:8000/parent/login
   - Familie auswählen und mit generiertem Passwort einloggen

2. **Verfügbarkeit testen**
   - Browser-Konsole öffnen (F12)
   - Einzelne Tage anklicken → sollte in Konsole "Checkbox changed" zeigen
   - Counter sollte sich aktualisieren

3. **Schnellaktionen testen**
   - "✓ Alle auswählen" → sollte alle nicht-ausgeschlossenen Tage aktivieren
   - "✗ Alle abwählen" → sollte alle Tage deaktivieren
   - Wochentag-Buttons (z.B. "✓ Montage") → sollte alle Montage togglen

4. **Speichern testen**
   - Tage auswählen
   - "💾 Verfügbarkeit speichern" klicken
   - Erfolgsmeldung sollte erscheinen: "Ihre Verfügbarkeit wurde gespeichert! (X Tage ausgewählt)"
   - Seite neu laden → ausgewählte Tage sollten noch markiert sein

5. **Browser-Konsole prüfen**
   ```
   Erwartete Log-Meldungen:
   - "Availability calendar initialized"
   - "Found X checkboxes"
   - "Found 5 weekday buttons"
   - "Select all button: <button>"
   - "Deselect all button: <button>"
   - Bei Aktionen: "Updated hidden input with X dates"
   - Bei Submit: "Form submitting with hidden input value: [...]"
   ```

## Dateien geändert
- `templates/parent/availability.html.twig` - Template bereinigt, CSS-Klassen hinzugefügt, JavaScript jetzt inline!

## Dateien gelöscht
- `public/js/availability.js` - Wurde nach `templates/parent/availability.html.twig` verschoben (inline)
- `public/js/` - Verzeichnis gelöscht (war leer)

## Dateien NICHT geändert
- `src/Controller/Parent/ParentController.php` - War bereits korrekt
- `assets/controllers/availability_controller.js` - Wird nicht verwendet (Vanilla JS statt Stimulus)

## Zusätzliche Verbesserungen
- 🔍 Umfangreiches Console-Logging für einfacheres Debugging
- 🎯 Toggle-Funktion für Wochentag-Buttons (User-freundlicher)
- ⏰ Timezone-Fix beim Datum-Parsing
- 🎨 Klarere Button-Beschriftungen mit Symbolen
- ⚡ JavaScript inline → **funktioniert sofort beim ersten Laden!**

## Wichtig: Warum inline JavaScript?
Das externe JavaScript in `public/js/availability.js` wurde **NICHT** durch AssetMapper verwaltet und führte zu Race Conditions:
- Beim ersten Laden nach Login: JavaScript nicht verfügbar → Buttons funktionieren nicht
- Nach Browser-Reload: JavaScript verfügbar → alles funktioniert

**Lösung**: JavaScript direkt im Template einbetten. Dadurch wird es garantiert geladen, wenn die Seite angezeigt wird.

## Status
✅ **BEHOBEN** - Alle Funktionen arbeiten jetzt wie erwartet, auch beim ersten Laden!
