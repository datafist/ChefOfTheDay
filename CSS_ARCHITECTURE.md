# CSS-Architektur Dokumentation

## Übersicht

Das Styling wurde aus den Templates in eine zentrale CSS-Datei ausgelagert. Dies reduziert Code-Duplikate und verbessert die Wartbarkeit erheblich.

## Struktur

### CSS-Datei
- **Pfad**: `assets/styles/app.css`
- **Import**: Automatisch via AssetMapper in `assets/app.js`

### Design-Prinzipien

1. **Minimal aber nicht brutalistisch**: Ausgewogenes, modernes Design
2. **CSS Custom Properties**: Zentrale Variablen für Farben, Abstände, etc.
3. **Mobile-First**: Responsive Design mit Media Queries
4. **Keine Frameworks**: Kein Bootstrap/Tailwind - nur eigenes CSS (~700 Zeilen)
5. **BEM-ähnlich**: Klare, beschreibende Klassennamen

## CSS-Variablen (Custom Properties)

```css
:root {
    /* Farben */
    --color-primary: #007bff;
    --color-success: #28a745;
    --color-danger: #dc3545;
    --color-warning: #ffc107;
    --color-secondary: #6c757d;
    
    /* Graustufen */
    --color-gray-100 bis --color-gray-900
    
    /* Abstände */
    --spacing-xs: 0.25rem;
    --spacing-sm: 0.5rem;
    --spacing-md: 0.75rem;
    --spacing-lg: 1rem;
    --spacing-xl: 1.5rem;
    --spacing-2xl: 2rem;
    
    /* Border & Schatten */
    --border-radius: 4px;
    --shadow-sm, --shadow-md, --shadow-lg
    
    /* Übergänge */
    --transition-fast: 0.15s ease;
}
```

## Komponenten-Klassen

### Layout
- `.container` - Haupt-Container mit max-width
- `header` - Fixierte Navigation
- `nav` - Mobile + Desktop Navigation

### Buttons
- `.btn` - Basis-Button
- `.btn-primary` - Blauer Button (Primär-Aktion)
- `.btn-success` - Grüner Button (Erfolg/Speichern)
- `.btn-danger` - Roter Button (Löschen/Blockieren)
- `.btn-secondary` - Grauer Button (Sekundär-Aktion)
- `.btn-outline` - Outline-Button

### Cards
- `.card` - Container mit Border und Schatten
- `.card-header` - Header-Bereich
- `.card-body` - Inhalt-Bereich

### Alerts
- `.alert` - Basis-Alert
- `.alert-success`, `.alert-error`, `.alert-warning`, `.alert-info`

### Forms
- `.form-group` - Form-Gruppe
- `label` - Label-Element
- `input`, `select`, `textarea` - Form-Controls mit Focus-Style

### Navigation Tabs
- `.nav-tabs` - Tab-Container
- Verwendet `.btn`-Klassen für die Tabs

### Schnellaktionen
- `.quickactions` - Container für Schnellaktionen
- `.quickactions-warning` - Warnung-Variante (gelb)
- `.quickactions-buttons` - Button-Gruppe mit Flex-Layout

### Kalender
- `.calendar-month` - Monats-Container
- `.calendar-month-primary` - Blauer Monat (verfügbare Tage)
- `.calendar-month-danger` - Roter Monat (blockierte Tage)
- `.calendar-grid` - 7-Spalten Grid
- `.calendar-header-{primary|danger|secondary}` - Wochentag-Header
- `.calendar-day-empty` - Leere Kalender-Zelle
- `.calendar-day-excluded` - Ausgeschlossener Tag (Feiertag/Ferien)
- `.calendar-day-label` - Klickbare Tag-Zelle
- `.calendar-day-label-danger` - Rot-Variante für Blockierungen

### Sticky Footer
- `.sticky-footer` - Fixierter Footer am Seitenbottom
- `.sticky-footer-primary` - Mit blauem Border
- `.sticky-footer-danger` - Mit rotem Border
- `.sticky-footer-counter` - Zähler-Anzeige
- `.sticky-footer-actions` - Button-Container

### Checkboxen
- `.checkbox-success` - Grüne Accent-Color
- `.checkbox-danger` - Rote Accent-Color

## Template-Integration

### Vorher (Inline-Styles)
```twig
<div style="background-color: #f8f9fa; padding: 1rem; border: 1px solid #dee2e6;">
    <button style="background-color: #007bff; color: white;">Button</button>
</div>
```

### Nachher (CSS-Klassen)
```twig
<div class="quickactions">
    <button class="btn btn-primary">Button</button>
</div>
```

## Vorteile der neuen Struktur

1. **Reduzierte Dateigröße**: ~90% weniger Code in Templates
2. **Keine Duplikate**: Jede Style-Definition nur einmal
3. **Bessere Wartbarkeit**: Zentrale Änderungen möglich
4. **Performance**: Browser-Caching der CSS-Datei
5. **Konsistenz**: Einheitliches Design durch Variablen
6. **Responsive**: Mobile-First mit Media Queries
7. **Moderne CSS**: Custom Properties, Grid, Flexbox

## Anpassungen

### Farben ändern
```css
:root {
    --color-primary: #007bff;  /* Hier anpassen */
}
```

### Abstände ändern
```css
:root {
    --spacing-lg: 1rem;  /* Hier anpassen */
}
```

### Neue Komponenten
Neue CSS-Klassen einfach in `assets/styles/app.css` hinzufügen.

## Browser-Support

- Moderne Browser (Chrome, Firefox, Safari, Edge)
- CSS Custom Properties werden verwendet
- Grid und Flexbox Layout
- Kein IE11-Support nötig
