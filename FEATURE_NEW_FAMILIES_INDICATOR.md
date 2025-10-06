# Feature: Anzeige neuer Familien im Dashboard

**Datum:** 2025-10-05  
**Status:** ✅ Implementiert  
**Typ:** UI-Verbesserung

## Übersicht

Das Admin-Dashboard zeigt jetzt deutlich an, welche Familien **neu** im aktuellen Kita-Jahr sind (d.h. keine Vorjahres-Daten haben).

## Motivation

Im Zusammenhang mit der Multi-Year-Fairness ist es wichtig zu wissen:
- Welche Familien sind neu dabei?
- Wie viele Dienste bekommen neue Familien?
- Werden neue Familien fair behandelt?

## Implementierung

### Controller-Änderungen

**Datei:** `src/Controller/Admin/DashboardController.php`

```php
// Identifiziere neue Familien (ohne LastYearCooking-Eintrag)
$allLastYearCookings = $lastYearCookingRepository->findAll();
$familiesWithHistory = array_map(fn($lyc) => $lyc->getParty()->getId(), $allLastYearCookings);

// Bei Statistik-Berechnung
$isNewFamily = !in_array($partyId, $familiesWithHistory);
$statsMap[$partyId] = [
    'party' => $assignment->getParty(),
    'count' => 0,
    'isNew' => $isNewFamily
];
```

**Neue Variable an Template:**
```php
'newFamilies' => $newFamilies,  // Array aller neuen Familien
```

### Template-Änderungen

**Datei:** `templates/admin/dashboard/index.html.twig`

#### 1. Info-Box über der Statistik

Zeigt Anzahl und Namen der neuen Familien:

```twig
{% if newFamilies|length > 0 %}
    <div class="alert alert-success">
        <strong>🆕 Neue Familien in diesem Jahr:</strong> 
        {{ newFamilies|length }} Familie(n)
        (Familie Müller, Familie Schmidt)
    </div>
{% endif %}
```

#### 2. Markierung in der Tabelle

Neue Familien werden visuell hervorgehoben:

- **Zeilen-Hintergrund:** Hellgrün (`#f0fff4`)
- **🆕-Symbol:** Vor dem Familiennamen
- **Grüner Badge:** Bei Anzahl Dienste
- **"NEU"-Label:** Unter der Dienste-Anzahl

```twig
<tr{% if stat.isNew %} style="background-color: #f0fff4;"{% endif %}>
    <td>
        {% if stat.isNew %}
            <span style="font-size: 1.1rem;">🆕</span>
        {% endif %}
        {{ stat.party.childrenNames }}
    </td>
    ...
    <td>
        <span style="background: {% if stat.isNew %}#28a745{% endif %};">
            {{ stat.count }}
        </span>
        {% if stat.isNew %}
            <div style="font-size: 0.7rem; color: #28a745;">NEU</div>
        {% endif %}
    </td>
</tr>
```

## Visuelle Darstellung

### Info-Box Beispiel
```
┌─────────────────────────────────────────────────────────┐
│ 🆕 Neue Familien in diesem Jahr: 3 Familien             │
│ (Familie Müller, Familie Schmidt, Familie Weber)        │
└─────────────────────────────────────────────────────────┘
```

### Tabellen-Darstellung Beispiel
```
┌──────────────────┬──────────────┬────────┬──────────┐
│ Familie          │ Eltern       │ Status │ Dienste  │
├──────────────────┼──────────────┼────────┼──────────┤
│ 🆕 Müller, Anna  │ Müller, Alex │ 2 Pers │   5      │ ← Hellgrüner Hintergrund
│                  │              │        │  NEU     │
├──────────────────┼──────────────┼────────┼──────────┤
│ Schmidt, Ben     │ Schmidt, ...│ 2 Pers │   5      │ ← Normal
├──────────────────┼──────────────┼────────┼──────────┤
│ 🆕 Weber, Clara  │ Weber, David │ 2 Pers │   4      │ ← Hellgrüner Hintergrund
│                  │              │        │  NEU     │
└──────────────────┴──────────────┴────────┴──────────┘
```

## Farbschema

| Element | Farbe | Verwendung |
|---------|-------|------------|
| Info-Box Hintergrund | `#d4edda` (hellgrün) | Success-Alert |
| Info-Box Border | `#c3e6cb` / `#28a745` | Border + Left-Accent |
| Zeilen-Hintergrund | `#f0fff4` (sehr hellgrün) | Neue Familien-Zeilen |
| Badge Hintergrund | `#28a745` (grün) | Dienste-Anzahl für neue Familien |
| NEU-Label | `#28a745` (grün) | Text unter Dienste-Anzahl |

## Anwendungsfälle

### Fall 1: Keine neuen Familien
```
→ Keine Info-Box wird angezeigt
→ Tabelle zeigt nur etablierte Familien
→ Alles normal
```

### Fall 2: Eine neue Familie
```
→ Info-Box: "🆕 Neue Familien in diesem Jahr: 1 Familie (Familie Müller)"
→ Tabelle: Familie Müller mit grünem Hintergrund und 🆕-Symbol
→ Administrator sieht sofort die neue Familie
```

### Fall 3: Mehrere neue Familien
```
→ Info-Box: "🆕 Neue Familien in diesem Jahr: 5 Familien (Familie A, Familie B, ...)"
→ Tabelle: Alle 5 Familien mit grünem Hintergrund
→ Leicht erkennbar bei der Überprüfung der Verteilung
```

## Nutzen für Administratoren

1. **Schnelle Übersicht:** Sofort sichtbar, welche Familien neu sind
2. **Fairness-Kontrolle:** Einfache Überprüfung, ob neue Familien fair behandelt werden
3. **Dokumentation:** Namen der neuen Familien werden direkt angezeigt
4. **Visuelle Klarheit:** Farbliche Hervorhebung verhindert Übersehen

## Technische Details

### Erkennungslogik

Eine Familie gilt als "neu", wenn:
- Kein `LastYearCooking`-Eintrag in der Datenbank existiert
- Die Familie hat noch nie einen Kochdienst in einem vorherigen Jahr gehabt

```php
$allLastYearCookings = $lastYearCookingRepository->findAll();
$familiesWithHistory = array_map(fn($lyc) => $lyc->getParty()->getId(), $allLastYearCookings);
$isNewFamily = !in_array($partyId, $familiesWithHistory);
```

### Performance

- **Minimale Overhead:** Nur eine zusätzliche Abfrage (`findAll()` auf LastYearCooking)
- **In-Memory-Check:** `in_array()` auf vorbereitetes Array
- **Keine zusätzlichen Joins:** Effizient

## Testing

### Testfall 1: Dashboard ohne neue Familien
```
Vorbedingung:
- Alle Familien haben LastYearCooking-Einträge

Erwartetes Ergebnis:
- Keine Info-Box wird angezeigt
- Keine Familie hat 🆕-Symbol
- Keine grünen Hintergründe
```

### Testfall 2: Dashboard mit einer neuen Familie
```
Vorbedingung:
- 43 etablierte Familien
- 1 neue Familie (Familie Müller, keine LastYearCooking)

Erwartetes Ergebnis:
- Info-Box: "🆕 Neue Familien in diesem Jahr: 1 Familie (Familie Müller)"
- Familie Müller: Grüner Hintergrund, 🆕-Symbol, grüner Badge
- Dienste-Anzahl sollte 4-5 sein (fair!)
```

### Testfall 3: Dashboard mit mehreren neuen Familien
```
Vorbedingung:
- 40 etablierte Familien
- 4 neue Familien

Erwartetes Ergebnis:
- Info-Box: "🆕 Neue Familien in diesem Jahr: 4 Familien (A, B, C, D)"
- Alle 4 Familien: Grüner Hintergrund und Markierungen
- Dienste-Anzahl: 4-5 für Paare, 3 für Alleinerziehende
```

## Zusammenhang mit Multi-Year-Fairness

Diese Anzeige ist besonders wichtig im Kontext der Multi-Year-Fairness:

1. **Überprüfung der Fairness:** 
   - Administrator kann sofort sehen, ob neue Familien fair behandelt werden
   - Erwartung: 4-5 Dienste für neue Paare

2. **Dokumentation:** 
   - Welche Familien sind dieses Jahr neu?
   - Nützlich für Auswertungen und Berichte

3. **Transparenz:** 
   - Eltern können informiert werden
   - Neue Familien bekommen keine Sonderbehandlung (Bevorzugung oder Überlastung)

## Erweiterungsmöglichkeiten

### Mögliche zukünftige Features

1. **Jahr-Filter:** Zeige, welche Familien in welchem Jahr dazugekommen sind
2. **Statistik-Vergleich:** "Neue vs. Etablierte Familien" Durchschnitt
3. **Export:** Liste neuer Familien als CSV/PDF
4. **Kalender-Integration:** Markiere neue Familien auch im Kalender

## Verwandte Dokumente

- `FEATURE_MULTIYEAR_FAIRNESS.md` - Multi-Year-Fairness Feature
- `BUGFIX_NEW_FAMILIES_DYNAMIC_DEFAULT.md` - Behandlung neuer Familien
- `UI_IMPROVEMENTS.md` - Allgemeine UI-Verbesserungen

## Autor

GitHub Copilot
