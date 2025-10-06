# Feature: Familien-Verwaltung über UI

## Datum: 5. Oktober 2025

## Problem
Neue Familien konnten bisher nur über Test-Fixtures angelegt werden, nicht über die Admin-Oberfläche.

## Lösung
Vollständige UI-Implementierung für Familien-Verwaltung (CRUD) mit Unterstützung für:
- **1-3 Kinder** pro Familie
- **1-2 Elternteile** (alleinerziehend oder Paar)
- Automatische **Passwort-Generierung** basierend auf dem ältesten Kind

## Implementierte Änderungen

### 1. Neues Formular: `ChildType.php`
**Datei:** `src/Form/ChildType.php`

```php
- Felder: name, birthYear
- Validierung: NotBlank, Length, Range (2015 - aktuelles Jahr)
```

### 2. Aktualisiertes Formular: `PartyType.php`
**Datei:** `src/Form/PartyType.php`

**Vorher:**
```php
- childName (TextType)
- childBirthYear (IntegerType)
- email (EmailType)
- parentNames (CollectionType)
```

**Nachher:**
```php
- children (CollectionType mit ChildType) - 1-3 Kinder
- email (EmailType)
- parentNames (CollectionType) - 1-2 Elternteile
```

### 3. Templates

#### `admin/party/index.html.twig`
- ✅ "Neue Familie" Button aktiviert
- ✅ "Bearbeiten" Button aktiviert
- ❌ Entfernt: "Neue Familien werden über Test-Fixtures angelegt" Hinweis

#### `admin/party/new.html.twig`
**Vollständig neu gestaltet:**
- Dynamische Kinder-Collection (1-3 Kinder)
  - Felder: Name, Geburtsjahr
  - JavaScript: Hinzufügen/Entfernen von Kindern
  - Validation: Min 1, Max 3 Kinder
  
- Dynamische Eltern-Collection (1-2 Elternteile)
  - Felder: Name
  - JavaScript: Hinzufügen/Entfernen von Elternteilen
  - Validation: Min 1, Max 2 Elternteile

- Info-Box: Erklärt automatische Passwort-Generierung

#### `admin/party/edit.html.twig`
**Vollständig überarbeitet:**
- Gleiche Struktur wie `new.html.twig`
- Zusätzlich: "Gefahr-Zone" für Löschen
- Warnung bei Passwort-Änderung (wenn ältestes Kind geändert wird)

### 4. Controller
**Datei:** `src/Controller/Admin/PartyController.php`

Keine Änderungen erforderlich! Die Routes waren bereits implementiert:
- ✅ `admin_party_new` (GET, POST)
- ✅ `admin_party_edit` (GET, POST)
- ✅ `admin_party_delete` (POST)

## Funktionsweise

### Neue Familie anlegen
1. Admin klickt auf "Neue Familie" Button
2. Formular öffnet sich mit:
   - **Mindestens 1 Kind** vorausgefüllt (kann bis zu 3 haben)
   - **Mindestens 1 Elternteil** vorausgefüllt (kann 2 haben)
   - E-Mail-Feld (optional)
3. JavaScript ermöglicht dynamisches Hinzufügen/Entfernen
4. Bei Submit:
   - Validierung: 1-3 Kinder, 1-2 Elternteile
   - Automatische Passwort-Generierung: `[Erster Buchstabe ältestes Kind][Geburtsjahr]`
   - Beispiel: "Max 2019" → Passwort: "M2019"

### Familie bearbeiten
1. Admin klickt auf "Bearbeiten" in der Familien-Übersicht
2. Formular zeigt bestehende Daten
3. Kann Kinder hinzufügen/entfernen (aber min 1, max 3)
4. Kann Elternteile ändern (aber min 1, max 2)
5. Warnung: Passwort ändert sich, wenn ältestes Kind geändert wird

### Familie löschen
1. Am Ende des Bearbeitungs-Formulars: "Gefahr-Zone"
2. Warnung: Löscht auch alle Verfügbarkeiten und Zuweisungen
3. JavaScript Bestätigungsdialog vor dem Löschen

## Passwort-Generierung

**Regel:** Erster Buchstabe des **ältesten** Kindes + Geburtsjahr

**Beispiele:**
```
Max (2019), Sophie (2021) → Passwort: M2019 (Max ist älter)
Emma (2020)                → Passwort: E2020
Luca (2018), Noah (2019), Mia (2021) → Passwort: L2018 (Luca ist ältester)
```

**Wichtig:** Bei Geschwisterkindern wird das älteste Kind verwendet!

## UI Features

### Kinder-Collection
- Grauer Hintergrund zur visuellen Gruppierung
- Name und Geburtsjahr nebeneinander
- "Entfernen" Button (deaktiviert bei nur 1 Kind)
- "+ Kind hinzufügen" Button (versteckt bei 3 Kindern)
- Alert bei Limit-Erreichen

### Eltern-Collection
- Einfache Liste mit Textfeldern
- "Entfernen" Button (deaktiviert bei nur 1 Elternteil)
- "+ Elternteil hinzufügen" Button (versteckt bei 2 Elternteilen)
- Alert bei Limit-Erreichen
- Hinweis: Alleinerziehende = reduzierte Kochdienste

### Validierung
**Client-Side (JavaScript):**
- Min/Max Anzahl Kinder (1-3)
- Min/Max Anzahl Elternteile (1-2)
- Alerts bei ungültigen Aktionen

**Server-Side (Symfony):**
- `@Assert\Count` für children und parentNames
- `@Assert\NotBlank` für Name
- `@Assert\Range` für Geburtsjahr (2015 - aktuelles Jahr)
- `@Assert\Length` für Namen (2-100 Zeichen)

## Testing

### Manuelle Tests
1. ✅ Neue Familie mit 1 Kind, 1 Elternteil anlegen
2. ✅ Neue Familie mit 3 Kindern, 2 Elternteilen anlegen
3. ✅ Familie bearbeiten: Kind hinzufügen
4. ✅ Familie bearbeiten: Elternteil entfernen (alleinerziehend)
5. ✅ Validierung: Versuch 4. Kind hinzuzufügen → Alert
6. ✅ Validierung: Versuch letztes Kind zu entfernen → Alert
7. ✅ Familie löschen mit Bestätigung
8. ✅ Passwort-Generierung prüfen (ältestes Kind)

## Nächste Schritte (optional)

### Mögliche Erweiterungen:
1. **E-Mail-Benachrichtigungen** bei Passwort-Änderung
2. **Import/Export** von Familien (CSV)
3. **Archivierung** statt Löschen (Soft Delete)
4. **Mehrere E-Mail-Adressen** (eine pro Elternteil)
5. **Telefonnummer** hinzufügen
6. **Notizen-Feld** für besondere Hinweise

## Technische Details

### Symfony Form Collections
- Verwendet `CollectionType` für dynamische Arrays
- `prototype` für JavaScript Template
- `allow_add` und `allow_delete` aktiviert
- `by_reference => false` für korrekte Persistierung

### JavaScript Pattern
```javascript
// Collection Management
1. Prototype aus data-attribute holen
2. __name__ durch Index ersetzen
3. DOM-Element erstellen und anhängen
4. Index inkrementieren

// Validation
- Zähle aktuelle Items
- Prüfe gegen Min/Max
- Alert bei ungültiger Aktion
```

## Dateien geändert/erstellt

### Neu erstellt:
- `src/Form/ChildType.php`
- `FEATURE_FAMILY_MANAGEMENT.md` (diese Datei)

### Geändert:
- `src/Form/PartyType.php` (komplett überarbeitet)
- `templates/admin/party/index.html.twig` (Buttons aktiviert)
- `templates/admin/party/new.html.twig` (komplett neu)
- `templates/admin/party/edit.html.twig` (komplett neu)

### Unverändert:
- `src/Controller/Admin/PartyController.php` (war bereits korrekt)
- `src/Entity/Party.php` (Entity-Struktur passt bereits)

## Fazit

Die Familien-Verwaltung ist jetzt vollständig über die Admin-UI möglich! 🎉

**Vorteile:**
- ✅ Kein Terminal-Zugriff mehr nötig
- ✅ Intuitive Benutzeroberfläche
- ✅ Client- und Server-seitige Validierung
- ✅ Unterstützung für Geschwisterkinder (1-3)
- ✅ Unterstützung für Alleinerziehende
- ✅ Automatische Passwort-Generierung
- ✅ Responsive Design (mobile-friendly)
