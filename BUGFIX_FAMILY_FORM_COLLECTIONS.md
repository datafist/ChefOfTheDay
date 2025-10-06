# Bugfix: Familie anlegen - Hinzufügen-Buttons funktionierten nicht

## Datum: 6. Oktober 2025

## Problem
In der Ansicht "Neue Familie anlegen" ließen sich weder Kinder noch Elternteile hinzufügen.

## Ursache
1. **Leeres Formular beim ersten Laden**: Beim Erstellen einer neuen Familie waren die Collections `children` und `parentNames` leer, was zu `data-index="0"` führte und keine initialen Formular-Felder anzeigte.

2. **HTML-Encoding des Prototypes**: Das Symfony-Form-Prototype wurde HTML-encoded im `data-prototype`-Attribut gespeichert, was beim Einfügen in den DOM zu Problemen führte.

3. **Fehlende Null-Checks**: Das JavaScript hatte keine Null-Checks für die Buttons, was zu stillen Fehlern führen konnte.

## Lösung

### 1. Controller-Änderung (`PartyController.php`)
**Vorausfüllen beim ersten Laden:**
```php
if (!$request->isMethod('POST')) {
    $party->setChildren([
        ['name' => '', 'birthYear' => (int)date('Y') - 5]
    ]);
    $party->setParentNames(['']);
}
```

**Effekt:**
- Beim Laden von `/admin/party/new` wird automatisch **1 leeres Kind** und **1 leerer Elternteil** vorausgefüllt
- Der Benutzer sieht sofort die Formularfelder
- `data-index` ist nun `1` statt `0`

### 2. JavaScript-Änderungen (beide Templates)

#### HTML-Decoding des Prototypes
```javascript
// Vorher:
const prototype = childrenCollection.dataset.prototype;

// Nachher:
const textarea = document.createElement('textarea');
textarea.innerHTML = childrenCollection.dataset.prototype;
const prototype = textarea.value;
```

**Warum?**
- Symfony escaped HTML im `data-prototype` Attribut
- Ohne Decoding wurden `&lt;` statt `<` eingefügt
- Der Textarea-Trick decoded automatisch HTML-Entities

#### Null-Checks für Buttons
```javascript
if (addChildButton) {
    addChildButton.addEventListener('click', function() {
        // ...
    });
} else {
    console.error('Add child button not found!');
}
```

**Warum?**
- Besseres Debugging
- Verhindert stille Fehler
- Zeigt klar, wenn ein Element fehlt

#### Debug-Logging
```javascript
console.log('Party form script loaded');
console.log('Children collection:', childrenCollection);
console.log('Child index:', childIndex);
console.log('Child prototype:', childrenCollection.dataset.prototype);
```

**Nutzen:**
- Einfaches Debugging im Browser
- Zeigt sofort, was geladen/initialisiert wird
- Hilft bei zukünftigen Problemen

#### Fallback für Index
```javascript
// Vorher:
let childIndex = parseInt(childrenCollection.dataset.index);

// Nachher:
let childIndex = parseInt(childrenCollection.dataset.index) || 0;
```

**Warum?**
- Falls `data-index` fehlt oder `NaN` ist, wird `0` verwendet
- Verhindert `NaN` Fehler beim Ersetzen von `__name__`

## Geänderte Dateien

### 1. `src/Controller/Admin/PartyController.php`
- Methode `new()`: Vorausfüllen mit leerem Kind und Elternteil

### 2. `templates/admin/party/new.html.twig`
- HTML-Decoding des Prototypes
- Null-Checks für Buttons
- Debug-Logging
- Fallback für Index

### 3. `templates/admin/party/edit.html.twig`
- Gleiche Änderungen wie `new.html.twig`
- Konsistenz zwischen beiden Formularen

## Testing

### Manuelle Tests durchführen:
1. ✅ Öffne `/admin/party/new`
2. ✅ Prüfe: **1 Kind** und **1 Elternteil** sind vorausgefüllt
3. ✅ Klicke "+ Kind hinzufügen" → 2. Kind erscheint
4. ✅ Klicke "+ Kind hinzufügen" → 3. Kind erscheint
5. ✅ Klicke "+ Kind hinzufügen" → Alert: "Maximal 3 Kinder"
6. ✅ Klicke "+ Elternteil hinzufügen" → 2. Elternteil erscheint
7. ✅ Klicke "+ Elternteil hinzufügen" → Alert: "Maximal 2 Elternteile"
8. ✅ Öffne Browser Console (F12) → Prüfe Logs
9. ✅ Fülle alle Felder aus → Speichern
10. ✅ Familie wird erfolgreich erstellt

### Browser Console prüfen:
Erwartete Logs:
```
Party form script loaded
Children collection: <div id="children-collection">...</div>
Child index: 1
Child prototype: <div>...</div>
Parent collection: <div id="parent-names-collection">...</div>
Parent index: 1
Parent prototype: <input...>
```

Bei Klick auf "+ Kind hinzufügen":
```
Add child button clicked
Current child count: 1
Decoded prototype: <div>...</div>
New form HTML: <div>...</div>
Child added, new index: 2
```

## Technische Details

### HTML-Decoding-Trick
```javascript
const textarea = document.createElement('textarea');
textarea.innerHTML = '&lt;div&gt;'; // HTML-encoded
const decoded = textarea.value;      // '<div>' - decoded!
```

**Warum ein Textarea?**
- Browser decoded automatisch HTML-Entities beim Setzen von `innerHTML`
- `value` gibt dann den decodierten Text zurück
- Sicherer als `unescape()` oder Regex
- Funktioniert mit allen HTML-Entities

### Symfony Form Collection Prototype
```twig
data-prototype="{{ form_widget(form.children.vars.prototype)|e('html_attr') }}"
```

**Was passiert hier?**
1. `form.children.vars.prototype` enthält das Template für ein neues Kind
2. `|e('html_attr')` escaped HTML für Verwendung in Attributen
3. `&lt;` statt `<`, `&quot;` statt `"`, etc.
4. JavaScript muss das decoden vor dem Einfügen

### Alternative Lösungen (nicht implementiert)

#### Option 1: Raw Filter (NICHT EMPFOHLEN)
```twig
data-prototype="{{ form_widget(form.children.vars.prototype)|raw }}"
```
**Problem:** XSS-Sicherheitsrisiko!

#### Option 2: JSON-Encoding
```twig
data-prototype="{{ form_widget(form.children.vars.prototype)|json_encode }}"
```
**Nachteil:** Komplexeres Parsing in JavaScript

#### Option 3: Script-Tag mit Template
```twig
<script type="text/template" id="child-prototype">
    {{ form_widget(form.children.vars.prototype) }}
</script>
```
**Nachteil:** Mehr HTML-Struktur

**Unsere Lösung (Textarea-Decode) ist die sauberste!**

## Lessons Learned

### 1. Symfony Form Collections brauchen Initialwerte
- Leere Collections = keine sichtbaren Felder
- Mindestens 1 Item vorausfüllen für bessere UX
- Alternative: Zeige "Noch keine Einträge" mit großem "+ Hinzufügen" Button

### 2. HTML-Encoding beachten
- Symfony escaped automatisch in Twig-Attributen
- JavaScript muss decoden vor DOM-Manipulation
- Textarea-Trick ist elegant und sicher

### 3. Immer Debug-Logging bei dynamischen Formularen
- Console.log ist dein Freund
- Zeigt sofort, wo es hakt
- Kann in Production einfach auskommentiert werden

### 4. Null-Checks sind wichtig
- Nicht davon ausgehen, dass Elemente existieren
- Bessere Fehlermeldungen mit `console.error()`
- Verhindert kryptische Browser-Fehler

## Zukünftige Verbesserungen (optional)

### 1. Benutzerfreundlichkeit
- **Placeholder-Texte** in Feldern ("z.B. Max Mustermann", "z.B. 2019")
- **Live-Passwort-Vorschau**: Zeige das generierte Passwort während der Eingabe
- **Validierung on-the-fly**: Zeige Fehler sofort, nicht erst beim Submit

### 2. Performance
- **Template-Caching**: Prototype nur einmal decoden und cachen
- **Event-Delegation**: Weniger Event-Listener

### 3. Accessibility
- **ARIA-Labels** für dynamisch hinzugefügte Felder
- **Focus-Management**: Fokus auf neues Feld nach Hinzufügen
- **Keyboard-Navigation**: Tab/Enter für Hinzufügen

### 4. Code-Qualität
- **JavaScript auslagern**: Separate `.js` Datei statt Inline-Script
- **Stimulus Controller**: Symfony UX Stimulus verwenden
- **TypeScript**: Für bessere Type-Safety

## Fazit

Das Problem wurde durch zwei einfache Änderungen gelöst:

1. ✅ **Controller**: Vorausfüllen mit Default-Werten beim ersten Laden
2. ✅ **JavaScript**: HTML-Decoding des Prototypes vor DOM-Manipulation

Die Familien-Verwaltung funktioniert jetzt vollständig! 🎉

**Test-URL:** http://localhost:8000/admin/party/new
