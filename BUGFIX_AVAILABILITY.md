# Fix für Verfügbarkeits-Kalender - Changelog

## 🐛 Behobene Probleme

### 1. Schnellauswahl-Buttons funktionierten nicht
**Problem:** Die Buttons "Alle Montage", "Alle Dienstage", etc. führten keine Aktion aus.

**Ursache:** 
- Wochentag-Berechnung war falsch (JavaScript `getDay()` vs ISO-Format)
- `event.preventDefault()` fehlte
- Controller war nicht korrekt registriert

**Lösung:**
- ✅ Wochentag-Konvertierung korrigiert (Sonntag = 7 in ISO)
- ✅ `event.preventDefault()` zu allen Button-Handlern hinzugefügt
- ✅ Controller in `assets/bootstrap.js` registriert
- ✅ Debug-Logging hinzugefügt

### 2. Ausgewählte Termine wurden nicht gespeichert
**Problem:** Nach dem Klick auf "Speichern" waren die Checkboxen beim erneuten Laden leer.

**Ursache:**
- CSRF-Token fehlte im Formular
- Hidden Input wurde nicht mit gespeicherten Daten initialisiert
- Keine Fehlerbehandlung im Backend

**Lösung:**
- ✅ CSRF-Token hinzugefügt: `{{ csrf_token('availability') }}`
- ✅ Hidden Input mit gespeicherten Daten vorbelegt
- ✅ CSRF-Validierung im PHP-Controller
- ✅ Bessere Fehlermeldungen und Debug-Ausgaben
- ✅ Success-Message zeigt Anzahl der gespeicherten Tage

### 3. Fehlende visuelle Rückmeldung
**Problem:** Benutzer sah nicht, wie viele Tage ausgewählt waren.

**Lösung:**
- ✅ Counter hinzugefügt: "X Tage ausgewählt"
- ✅ Counter aktualisiert sich live bei jeder Änderung
- ✅ Console-Logs für Debugging (können später entfernt werden)

## 📝 Geänderte Dateien

1. **assets/controllers/availability_controller.js**
   - Wochentag-Logik korrigiert
   - `event.preventDefault()` hinzugefügt
   - Counter-Target und Update-Logik
   - Debug-Logging

2. **assets/bootstrap.js**
   - AvailabilityController registriert

3. **templates/parent/availability.html.twig**
   - CSRF-Token hinzugefügt
   - Hidden Input mit gespeicherten Daten initialisiert
   - Counter für ausgewählte Tage

4. **src/Controller/Parent/ParentController.php**
   - CSRF-Token-Validierung
   - Bessere Debug-Ausgaben
   - Verbesserte Flash-Messages

## 🧪 Testen

### Schritt-für-Schritt Test

1. **Browser öffnen (F12 für Console)**
   ```
   http://localhost:8000/parent/login
   ```

2. **Als Eltern einloggen**
   - Familie: Max Müller
   - Passwort: M2019

3. **Console überprüfen**
   Sollte zeigen:
   ```
   Availability controller connected
   Checkboxes found: XXX
   ```

4. **Schnellauswahl testen**
   - Klicke "Alle Montage" → Alle Montage sollten markiert werden
   - Console zeigt: `Select weekday: 1` und `Selected dates: XX`
   - Counter aktualisiert sich

5. **Einzelne Tage testen**
   - Klicke auf einzelne Checkboxen
   - Console zeigt: `Toggle day: 2024-09-XX`
   - Counter aktualisiert sich

6. **Speichern testen**
   - Klicke "Verfügbarkeit speichern"
   - Success-Message sollte erscheinen: "Ihre Verfügbarkeit wurde gespeichert! (XX Tage ausgewählt)"

7. **Persistierung prüfen**
   - Seite neu laden (F5)
   - Vorher ausgewählte Tage sollten noch markiert sein
   - Counter zeigt korrekte Anzahl

8. **Alle Funktionen testen**
   - ✓ Alle auswählen
   - ✗ Alle abwählen
   - Einzelne Wochentage
   - Kombination aus allem

## 🔍 Debugging

Falls es immer noch nicht funktioniert:

### Im Browser (F12 Console)
```javascript
// Prüfe ob Controller geladen wurde
console.log(document.querySelector('[data-controller="availability"]'));

// Prüfe Checkboxen
console.log(document.querySelectorAll('[data-availability-target="checkbox"]').length);

// Prüfe Hidden Input
console.log(document.querySelector('[data-availability-target="hiddenInput"]').value);
```

### Im PHP Backend
Temporär in `ParentController.php` nach dem POST:
```php
dump($availableDatesJson);
dump($availableDates);
die();
```

## 📊 Erwartetes Verhalten

| Aktion | Erwartetes Ergebnis |
|--------|---------------------|
| Seite laden | Console zeigt Controller-Verbindung |
| "Alle Montage" klicken | Alle Montage werden markiert, Counter erhöht sich |
| Einzelne Checkbox | Tag wird markiert/demarkiert, Counter ändert sich |
| "Alle auswählen" | Alle verfügbaren Tage markiert |
| "Alle abwählen" | Alle Markierungen entfernt |
| "Speichern" klicken | Success-Message mit Anzahl, Redirect |
| Seite neu laden | Vorherige Auswahl ist noch vorhanden |

## 🚀 Nächste Schritte

Nach erfolgreichem Test:
- [ ] Debug-Logs aus JavaScript entfernen (optional)
- [ ] Console.logs in Production deaktivieren
- [ ] Browser-Kompatibilität testen (Chrome, Firefox, Safari)
- [ ] Mobile Ansicht testen

## 💡 Hinweise

- Die Wochentag-Buttons arbeiten mit ISO-Format (1=Montag, 7=Sonntag)
- Feiertage, Ferien und Wochenenden sind automatisch ausgegraut
- CSRF-Token wird bei jedem Seitenaufruf neu generiert
- Daten werden als JSON-Array gespeichert
