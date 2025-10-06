# Test: Eltern-Verfügbarkeitskalender nach Login

## Problem (vor dem Fix)
Nach dem Login als Elternteil funktionierte der Verfügbarkeitskalender nicht:
- ❌ Speichern führte zu: "Keine Termine ausgewählt. JSON: " und "(0 Tage ausgewählt)"
- ❌ Schnellaktionen (Alle auswählen, Wochentage) funktionierten nicht
- ✅ **Nach Browser-Reload funktionierte plötzlich alles**

## Ursache
Das externe JavaScript in `public/js/availability.js` wurde nicht durch AssetMapper verwaltet und führte zu Race Conditions beim ersten Laden.

## Lösung
JavaScript wurde inline im Template eingebettet → funktioniert sofort beim ersten Laden!

## Testschritte

### 1. Abmelden (falls eingeloggt)
```
http://localhost:8000/parent/logout
oder
http://localhost:8000/admin/logout
```

### 2. Als Eltern einloggen
```
http://localhost:8000/parent/login
```
- Familie auswählen
- Passwort eingeben (z.B. aus der Datenbank oder admin@kita.local mit admin123)

### 3. Sofort nach Weiterleitung testen (OHNE Reload!)
Öffne Browser-Konsole (F12) und prüfe:

**✅ Erwartete Logs (sofort sichtbar):**
```
This log comes from assets/app.js - welcome to AssetMapper! 🎉
Availability calendar initialized
Found X checkboxes
Updated hidden input with X dates
Found 5 weekday buttons
Select all button: <button>...
Deselect all button: <button>...
```

**❌ NICHT mehr:** Leere Konsole oder fehlende Logs

### 4. Funktionstest (ohne Reload!)

#### Test 1: Einzelne Checkboxen
- [ ] Klicke auf einzelne Tage
- [ ] Konsole zeigt: "Checkbox changed: YYYY-MM-DD true/false"
- [ ] Counter aktualisiert sich

#### Test 2: Alle auswählen
- [ ] Klicke "✓ Alle auswählen"
- [ ] Konsole zeigt: "Selecting all" + "Selected X checkboxes"
- [ ] Alle nicht-ausgeschlossenen Tage sind markiert

#### Test 3: Alle abwählen
- [ ] Klicke "✗ Alle abwählen"
- [ ] Konsole zeigt: "Deselecting all" + "Deselected all checkboxes"
- [ ] Alle Tage sind demarkiert

#### Test 4: Wochentage togglen
- [ ] Klicke "✓ Montage"
- [ ] Konsole zeigt: "Toggled X checkboxes for weekday 1"
- [ ] Alle Montage sind jetzt markiert
- [ ] Klicke nochmal "✓ Montage"
- [ ] Alle Montage sind jetzt wieder demarkiert (Toggle!)

#### Test 5: Speichern
- [ ] Markiere mindestens 1 Tag
- [ ] Klicke "💾 Verfügbarkeit speichern"
- [ ] Konsole zeigt: "Form submitting with hidden input value: [...]"
- [ ] Erfolgsmeldung erscheint: "Ihre Verfügbarkeit wurde gespeichert! (X Tage ausgewählt)"
- [ ] **X ist NICHT 0!** ✅

#### Test 6: Persistenz
- [ ] Browser-Seite neu laden (F5)
- [ ] Alle zuvor markierten Tage sind noch markiert
- [ ] Counter zeigt korrekte Anzahl

## Erfolg
✅ Alle Tests bestanden = Bug ist behoben!
❌ Ein Test fehlgeschlagen = Prüfe Browser-Konsole auf Fehler

## Technische Details
- JavaScript ist jetzt inline im Template `templates/parent/availability.html.twig`
- Keine externe Datei mehr → keine Race Conditions
- Code wird nach `{{ parent() }}` geladen → AssetMapper ist bereits initialisiert
- `DOMContentLoaded` Event stellt sicher, dass alle Elemente verfügbar sind
