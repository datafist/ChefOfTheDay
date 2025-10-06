# Feature: Dynamische Kita-Jahr-Auswahl ab 2024/25

## Datum: 6. Oktober 2025

## Anforderung
Anlegbare Kita-Jahre müssen theoretisch bis ins Unendliche gehen, beginnend mit 24/25.

## Problem (vorher)
Die Auswahl war hardcoded von 2020 bis 2030:
```twig
{% for year in 2020..2030 %}
```

**Nachteile:**
- ❌ Fixe Range musste manuell angepasst werden
- ❌ Jahre vor 2024 waren wählbar (nicht relevant für die Anwendung)
- ❌ Range endet 2030 → danach musste Code geändert werden
- ❌ Keine automatische Anpassung an aktuelles Jahr

## Lösung (nachher)

### Template-Änderungen (`kita_year/new.html.twig`)

**Dynamische Range:**
```twig
{% set currentYear = 'now'|date('Y')|number_format(0, '', '') %}
{% set endYear = currentYear + 10 %}
{% for year in 2024..endYear %}
    <option value="{{ year }}" {% if year == currentYear %}selected{% endif %}>
        {{ year }}/{{ year + 1 }} (01.09.{{ year }} - 31.08.{{ year + 1 }})
    </option>
{% endfor %}
```

**Vorteile:**
- ✅ Beginnt immer bei **2024** (erstes relevantes Kita-Jahr)
- ✅ Endet **10 Jahre in der Zukunft** (dynamisch)
- ✅ Keine manuelle Anpassung mehr nötig
- ✅ Aktuelles Jahr ist vorausgewählt

**Beispiele:**
- **2025**: Auswahl 2024-2035 (12 Jahre)
- **2030**: Auswahl 2024-2040 (17 Jahre)
- **2040**: Auswahl 2024-2050 (27 Jahre)

### Controller-Validierung (`KitaYearController.php`)

**Neue Validierungen hinzugefügt:**

```php
// 1. Mindestens 2024
if ($startYear < 2024) {
    $this->addFlash('error', 'Das Kita-Jahr muss mindestens 2024/25 sein.');
    return $this->redirectToRoute('admin_kita_year_new');
}

// 2. Nicht zu weit in der Zukunft (max. 20 Jahre)
$currentYear = (int)date('Y');
if ($startYear > $currentYear + 20) {
    $this->addFlash('error', 'Das Kita-Jahr darf nicht mehr als 20 Jahre in der Zukunft liegen.');
    return $this->redirectToRoute('admin_kita_year_new');
}

// 3. Duplikate verhindern
$existingYear = $kitaYearRepository->findOneBy([
    'startDate' => new \DateTimeImmutable($startYear . '-09-01')
]);

if ($existingYear) {
    $this->addFlash('error', 'Das Kita-Jahr ' . $startYear . '/' . ($startYear + 1) . ' existiert bereits.');
    return $this->redirectToRoute('admin_kita_year_new');
}
```

**Warum 3 Validierungen?**

1. **Min 2024**: Verhindert historische Jahre, die nicht relevant sind
2. **Max +20 Jahre**: Verhindert versehentliche Eingabe (z.B. Tippfehler "2099")
3. **Duplikate**: Verhindert, dass dasselbe Jahr zweimal angelegt wird

## Technische Details

### Twig-Filter `number_format`
```twig
{% set currentYear = 'now'|date('Y')|number_format(0, '', '') %}
```

**Warum?**
- `date('Y')` gibt String zurück (z.B. "2025")
- `number_format(0, '', '')` konvertiert zu Integer
- Wichtig für Berechnungen: `currentYear + 10`

### Alternative ohne `number_format`
```twig
{% set currentYear = 'now'|date('Y')|trim|default(2024) %}
```

**Warum wir `number_format` bevorzugen:**
- Explizite Typ-Konvertierung
- Keine String-Konkatenation bei Addition
- Sauberer Code

### Range in Twig
```twig
{% for year in 2024..endYear %}
```

**Dynamische Range:**
- Start: **Fest 2024** (Requirement)
- Ende: **Dynamisch** (`currentYear + 10`)
- Automatische Inkrement-Schritte

## Verhalten im Laufe der Jahre

### Jahr 2025 (aktuell)
- **Auswahl**: 2024-2035 (12 Jahre)
- **Vorauswahl**: 2025/26
- **Ältestes**: 2024/25
- **Neustes**: 2035/36

### Jahr 2030
- **Auswahl**: 2024-2040 (17 Jahre)
- **Vorauswahl**: 2030/31
- **Ältestes**: 2024/25
- **Neustes**: 2040/41

### Jahr 2050
- **Auswahl**: 2024-2060 (37 Jahre)
- **Vorauswahl**: 2050/51
- **Ältestes**: 2024/25
- **Neustes**: 2060/61

**Fazit:** Die Auswahl wächst mit der Zeit, aber **2024/25 bleibt immer der Startpunkt**.

## Warum 10 Jahre Vorschau?

### Begründung für `currentYear + 10`:
- ✅ **Realistisch**: Kitas planen selten mehr als 5-10 Jahre voraus
- ✅ **Performance**: Weniger Options in der Select-Box
- ✅ **Benutzerfreundlichkeit**: Übersichtliche Auswahl
- ✅ **Flexibel**: Kann bei Bedarf auf +15 oder +20 erhöht werden

### Wenn mehr benötigt wird:
```twig
{% set endYear = currentYear + 20 %}  {# 20 Jahre statt 10 #}
```

**Aber Achtung:** Controller validiert max. +20 Jahre!

## Fehlermeldungen

### Mögliche Flash-Messages:

1. **Jahr < 2024:**
   ```
   ❌ Das Kita-Jahr muss mindestens 2024/25 sein.
   ```

2. **Jahr > currentYear + 20:**
   ```
   ❌ Das Kita-Jahr darf nicht mehr als 20 Jahre in der Zukunft liegen.
   ```

3. **Duplikat:**
   ```
   ❌ Das Kita-Jahr 2025/2026 existiert bereits.
   ```

4. **Erfolg:**
   ```
   ✅ Kita-Jahr 2025/2026 erfolgreich angelegt.
   ```

## Testing

### Manuelle Tests:

1. **Normaler Fall:**
   - Öffne `/admin/kita-year/new`
   - Wähle aktuelles Jahr (z.B. 2025/26)
   - Submit → Erfolg ✅

2. **Zukunfts-Jahr:**
   - Wähle Jahr in 5 Jahren (z.B. 2030/31)
   - Submit → Erfolg ✅

3. **Duplikat:**
   - Erstelle Jahr 2025/26
   - Versuche 2025/26 nochmal → Fehler ✅

4. **Backend-Manipulation:**
   - POST mit `start_year=2020` (via curl/Postman)
   - → Fehler: "Mindestens 2024/25" ✅

5. **Zu weit in Zukunft:**
   - POST mit `start_year=2050` (wenn aktuell 2025)
   - → Fehler: "Max. 20 Jahre" ✅

### Browser Console Check:
```javascript
// Prüfe Select-Optionen
document.querySelectorAll('#start_year option').forEach(opt => {
    console.log(opt.value, opt.text);
});

// Erwartete Ausgabe (Jahr 2025):
// 2024 "2024/2025 (01.09.2024 - 31.08.2025)"
// 2025 "2025/2026 (01.09.2025 - 31.08.2026)" [selected]
// ...
// 2035 "2035/2036 (01.09.2035 - 31.08.2036)"
```

## Geänderte Dateien

### 1. `templates/admin/kita_year/new.html.twig`
**Änderungen:**
- Dynamische Range statt hardcoded `2020..2030`
- Start bei `2024` (fest)
- Ende bei `currentYear + 10` (dynamisch)
- Verbesserte Hilfe-Text mit aktueller Anzahl Jahre

### 2. `src/Controller/Admin/KitaYearController.php`
**Änderungen:**
- Validierung: Min 2024
- Validierung: Max currentYear + 20
- Validierung: Duplikat-Check
- Verbesserte Fehler- und Erfolgs-Meldungen

## Zukünftige Erweiterungen (optional)

### 1. Konfigurierbare Limits
**Config-File** (`config/parameters.yaml`):
```yaml
parameters:
    kita_year:
        min_year: 2024
        future_years: 10
        max_future_years: 20
```

**Vorteile:**
- Zentrale Konfiguration
- Keine Code-Änderung nötig
- Einfach anpassbar

### 2. Automatische Jahr-Vorschläge
**Vorschlag nächster Jahre:**
```twig
<div class="quick-select">
    <p>Häufige Auswahlen:</p>
    <button type="button" onclick="selectYear({{ currentYear }})">
        Aktuelles Jahr ({{ currentYear }}/{{ currentYear + 1 }})
    </button>
    <button type="button" onclick="selectYear({{ currentYear + 1 }})">
        Nächstes Jahr ({{ currentYear + 1 }}/{{ currentYear + 2 }})
    </button>
</div>
```

### 3. Jahr-Erstellungs-Assistent
**Wizard für neue Jahre:**
1. Schritt: Jahr auswählen
2. Schritt: Feiertage übernehmen vom Vorjahr?
3. Schritt: Ferien übernehmen vom Vorjahr?
4. Schritt: Familien übernehmen?
5. Bestätigung

**Vorteile:**
- Schnellerer Setup
- Weniger manuelle Arbeit
- Konsistenz zwischen Jahren

## Lessons Learned

### 1. Dynamische Ranges sind besser als Hardcoded
**Vorher:**
```twig
{% for year in 2020..2030 %}  {# Muss jedes Jahr angepasst werden #}
```

**Nachher:**
```twig
{% for year in 2024..(currentYear + 10) %}  {# Passt sich automatisch an #}
```

### 2. Backend-Validierung ist Pflicht
- Nie nur Frontend-Validierung verlassen
- Immer auch Controller-seitig prüfen
- Verhindert Manipulation via curl/Postman

### 3. Klare Grenzen setzen
- Min: 2024 (sinnvoller Start für die App)
- Max: +20 Jahre (verhindert Tippfehler)
- Duplikat-Check (verhindert Inkonsistenzen)

### 4. Benutzerfreundliche Fehlermeldungen
**Schlecht:**
```
Error: Invalid year
```

**Gut:**
```
Das Kita-Jahr muss mindestens 2024/25 sein.
Das Kita-Jahr 2025/2026 existiert bereits.
```

## Fazit

Die Kita-Jahr-Auswahl ist jetzt **dynamisch und zukunftssicher**! 🎉

**Vorteile:**
- ✅ Beginnt bei **2024/25** (wie gewünscht)
- ✅ Wächst **automatisch** mit den Jahren
- ✅ Keine manuelle Anpassung mehr nötig
- ✅ **Validierung** verhindert ungültige Eingaben
- ✅ **Duplikat-Check** verhindert Fehler

**Test-URL:** http://localhost:8000/admin/kita-year/new
