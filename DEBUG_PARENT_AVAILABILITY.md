# Debug-Anleitung: Elternkalender nach Login

## Problem
Nach dem Login wird der Verfügbarkeitskalender nicht korrekt initialisiert.

## Debugging-Schritte

### 1. Browser-Konsole öffnen BEVOR Login
1. Öffne Browser-Entwicklertools (F12)
2. Gehe zum "Console" Tab
3. **Aktiviere "Preserve log"** (sehr wichtig!)
4. Navigiere zu: http://localhost:8000/parent/login

### 2. Login durchführen
1. Familie auswählen
2. Passwort eingeben
3. Login klicken
4. **DIREKT nach Weiterleitung zur Verfügbarkeitsseite:**

### 3. Was sollte in der Konsole stehen?

#### ✅ Erwartete Logs (SOFORT nach Login-Weiterleitung):
```
Availability calendar initialized
Found X checkboxes
Updated hidden input with X dates
Found 5 weekday buttons
Select all button: <button>...
Deselect all button: <button>...
```

#### ❌ Problem-Indikatoren:
- **KEIN** "Availability calendar initialized" → Script wird nicht ausgeführt
- **"Form not found"** → DOM ist nicht bereit
- **Gar keine Logs** → Script wird nicht geladen

### 4. DOM-Inspektion
Öffne die "Elements" Tab in den Entwicklertools und suche nach:
- `<script>` Tag mit "Verfügbarkeits-Kalender JavaScript"
- `<form id="availability-form">`
- Checkboxen mit `data-date` Attribut

### 5. Netzwerk-Tab prüfen
1. Gehe zum "Network" Tab
2. Lade die Seite neu (F5)
3. Suche nach:
   - `/parent/availability` → Status sollte 200 sein
   - Alle Assets (CSS, JS) sollten geladen sein

### 6. JavaScript-Fehler prüfen
Gibt es **rote Fehlermeldungen** in der Konsole?
- Syntax-Fehler?
- Referenz-Fehler (z.B. "updateHiddenInput is not defined")?
- Andere Fehler?

## Test-Kommandos

```bash
# Cache leeren
php bin/console cache:clear

# Template Syntax prüfen
php bin/console lint:twig templates/parent/availability.html.twig

# Server neu starten
symfony server:stop
symfony server:start -d

# Oder mit PHP
pkill -f "php -S"
php -S localhost:8000 -t public/ &
```

## Was Sie mir sagen sollten:

1. **Was steht EXAKT in der Browser-Konsole nach Login?**
   - Kopieren Sie ALLE Zeilen (auch Warnungen)

2. **Gibt es JavaScript-Fehler (rot)?**
   - Komplette Fehlermeldung kopieren

3. **Ist das `<script>` Tag im HTML-Quellcode sichtbar?**
   - Rechtsklick → "Seitenquelltext anzeigen"
   - Suchen nach "Verfügbarkeits-Kalender JavaScript"

4. **Was passiert wenn Sie auf "Alle auswählen" klicken?**
   - Gar nichts?
   - Fehler in Konsole?
   - Button reagiert aber nichts passiert?

5. **Funktioniert es nach einem Browser-Reload (F5)?**
   - Ja → Timing-Problem
   - Nein → Anderes Problem
