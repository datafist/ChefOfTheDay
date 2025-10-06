# Feature: Anzeige der Dienstzuweisungen pro Familie

## Datum: 2025-10-05

## Zusammenfassung

Es wurden zwei wichtige Features implementiert:
1. **Anzeige der Anzahl zugewiesener Kochdienste** für das aktive Jahr in beiden Interfaces (Familien + Admin)
2. **Kita-Jahre Verwaltung** in die Admin-Navigationsleiste integriert

## Änderungen

### 1. Admin-Navigationsleiste erweitert

**Datei**: `templates/base.html.twig`

```twig
<a href="{{ path('admin_kita_year_index') }}">Kita-Jahre</a>
```

**Position**: Zwischen "Familien" und "Feiertage"

### 2. Eltern-Interface: Verfügbarkeitsseite

**Dateien**:
- `src/Controller/Parent/ParentController.php`
- `templates/parent/availability.html.twig`

**Änderungen im Controller**:
```php
// Zähle Zuweisungen für das aktive Jahr
$assignmentCount = $em->getRepository(\App\Entity\CookingAssignment::class)
    ->count([
        'party' => $party,
        'kitaYear' => $activeYear
    ]);
```

**Anzeige im Template**:
```twig
<div class="alert alert-info">
    <strong>📊 Ihre Kochdienste in diesem Jahr:</strong>
    Sie wurden <strong>{{ assignmentCount }} Mal</strong> für den Kochdienst eingeteilt.
</div>
```

**Position**: Direkt unter der Kita-Jahr-Info, vor dem Kalender

### 3. Admin-Interface: Familien-Liste

**Dateien**:
- `src/Controller/Admin/PartyController.php` - `index()` Methode
- `templates/admin/party/index.html.twig`

**Änderungen im Controller**:
```php
// Hole aktives Kita-Jahr
$activeYear = $entityManager->getRepository(\App\Entity\KitaYear::class)
    ->findOneBy(['isActive' => true]);

// Zähle Zuweisungen pro Familie
$assignmentCounts = [];
if ($activeYear) {
    foreach ($parties as $party) {
        $count = $entityManager->getRepository(\App\Entity\CookingAssignment::class)
            ->count([
                'party' => $party,
                'kitaYear' => $activeYear
            ]);
        $assignmentCounts[$party->getId()] = $count;
    }
}
```

**Neue Tabellen-Spalte**:
| Kindname | Geburtsjahr | Erziehungsberechtigte | Passwort | **Dienste 25/26** | Aktionen |
|----------|-------------|----------------------|----------|-------------------|----------|
| Max      | 2019        | Maria, Thomas Müller | M2019    | **4**             | ...      |

**Design**: Grüner Badge mit Anzahl

### 4. Admin-Interface: Familien-Detail

**Dateien**:
- `src/Controller/Admin/PartyController.php` - `show()` Methode
- `templates/admin/party/show.html.twig`

**Änderungen im Controller**:
```php
// Hole Zuweisungen für das aktive Jahr
$assignments = [];
$assignmentCount = 0;
if ($activeYear) {
    $assignments = $entityManager->getRepository(\App\Entity\CookingAssignment::class)
        ->findBy(
            [
                'party' => $party,
                'kitaYear' => $activeYear
            ],
            ['assignedDate' => 'ASC']
        );
    $assignmentCount = count($assignments);
}
```

**Neue Sektion im Template**:
```twig
<h3>📊 Kochdienste im Jahr {{ activeYear.yearString }}</h3>

<div class="alert alert-info">
    <strong>Anzahl Zuweisungen:</strong> {{ assignmentCount }}
</div>

<h4>Zugewiesene Termine:</h4>
<ul>
    <li>12.09.2025 (Monday) 🤖 Automatisch</li>
    <li>04.11.2025 (Tuesday) 🤖 Automatisch</li>
    <li>15.01.2026 (Thursday) ✏️ Manuell</li>
    ...
</ul>
```

**Features**:
- Liste aller zugewiesenen Termine
- Badge für manuelle vs. automatische Zuweisung
- Chronologische Sortierung
- Hinweis für Alleinerziehende

### 5. Admin-Interface: Dashboard

**Datei**: `templates/admin/dashboard/index.html.twig`

**Neue Sektion**: "Statistik nach Familien"

**Features**:
- Sortiert nach Anzahl Dienste (abstei gend)
- Zeigt Status (1 Person / 2 Personen)
- Verlinkt zur Detail-Ansicht
- Übersichtliche Tabelle

**Beispiel**:
| Familie | Erziehungsberechtigte | Status | Anzahl Dienste |
|---------|----------------------|--------|----------------|
| Max     | Maria, Thomas Müller | 2 Personen | 5 |
| Sophie  | Anna Schmidt         | 1 Person   | 3 |
| Leon    | Julia, Michael Weber | 2 Personen | 5 |

## Screenshots (Konzepte)

### Eltern-Interface
```
┌─────────────────────────────────────────────┐
│ Verfügbarkeit für Max                       │
├─────────────────────────────────────────────┤
│ Kita-Jahr: 25/26 (01.09.2025 - 31.08.2026) │
│                                             │
│ ┌─────────────────────────────────────────┐ │
│ │ 📊 Ihre Kochdienste in diesem Jahr:     │ │
│ │ Sie wurden 4 Mal für den Kochdienst     │ │
│ │ eingeteilt.                              │ │
│ └─────────────────────────────────────────┘ │
│                                             │
│ [Kalender...]                               │
└─────────────────────────────────────────────┘
```

### Admin - Familien-Liste
```
┌──────────────────────────────────────────────────────────────┐
│ Familien                            [Neue Familie]           │
├──────────────────────────────────────────────────────────────┤
│ 📅 Aktives Jahr: 25/26 (01.09.2025 - 31.08.2026)           │
├────────┬────────┬───────────┬────────┬──────────┬──────────┤
│ Name   │ Jahr   │ Eltern    │ PW     │ Dienste  │ Aktionen │
├────────┼────────┼───────────┼────────┼──────────┼──────────┤
│ Max    │ 2019   │ Maria,... │ M2019  │   [4]    │ [...]    │
│ Sophie │ 2020   │ Anna S.   │ S2020  │   [3]    │ [...]    │
│ Leon   │ 2018   │ Julia,... │ L2018  │   [5]    │ [...]    │
└────────┴────────┴───────────┴────────┴──────────┴──────────┘
```

### Admin - Dashboard Statistik
```
┌─────────────────────────────────────────────────────────┐
│ Statistik nach Familien                                 │
├─────────┬─────────────────┬──────────┬─────────────────┤
│ Familie │ Eltern          │ Status   │ Anzahl Dienste  │
├─────────┼─────────────────┼──────────┼─────────────────┤
│ Leon    │ Julia, Michael  │ 2 Pers.  │     [5]         │
│ Max     │ Maria, Thomas   │ 2 Pers.  │     [4]         │
│ Emma    │ Sandra, Frank   │ 2 Pers.  │     [4]         │
│ Sophie  │ Anna Schmidt    │ 1 Pers.  │     [3]         │
└─────────┴─────────────────┴──────────┴─────────────────┘
```

## Technische Details

### Performance
- Zählungen werden pro Request berechnet (kein Caching nötig bei kleinen Datenmengen)
- Bei >100 Familien evtl. Optimierung über aggregierte Queries nötig

### SQL-Queries
```sql
-- Zähle Zuweisungen pro Familie
SELECT COUNT(*) 
FROM cooking_assignments 
WHERE party_id = ? AND kita_year_id = ?

-- Hole alle Zuweisungen mit Sortierung
SELECT * 
FROM cooking_assignments 
WHERE party_id = ? AND kita_year_id = ?
ORDER BY assigned_date ASC
```

### Vorteile der Lösung
1. ✅ **Keine zusätzlichen Tabellen** - nutzt bestehende Daten
2. ✅ **Real-time Daten** - immer aktuell
3. ✅ **Einfache Wartung** - keine Denormalisierung
4. ✅ **Transparent** - Eltern und Admin sehen dieselben Zahlen

## Use Cases

### UC1: Eltern prüft eigene Zuweisung
**Akteur**: Elternteil
**Ziel**: Wissen, wie oft man schon dran war

**Ablauf**:
1. Eltern loggt sich ein
2. Sieht sofort: "Sie wurden 4 Mal eingeteilt"
3. Kann dies mit anderen Familien vergleichen (indirekt)

### UC2: Admin überprüft Fairness
**Akteur**: Kita-Admin
**Ziel**: Gerechte Verteilung kontrollieren

**Ablauf**:
1. Admin öffnet Dashboard
2. Sieht Statistik-Tabelle sortiert nach Anzahl
3. Kann auf einen Blick erkennen:
   - Wer hat viele Dienste?
   - Wer hat wenige Dienste?
   - Ist die Verteilung fair (unter Berücksichtigung von 1/2 Personen)?

### UC3: Admin prüft spezifische Familie
**Akteur**: Kita-Admin
**Ziel**: Details zu einer Familie sehen

**Ablauf**:
1. Admin öffnet Familien-Liste
2. Sieht Anzahl Dienste in Übersicht
3. Klickt auf "Anzeigen"
4. Sieht alle konkreten Termine mit Datum und Typ

## Testing

### Manueller Test
```bash
# 1. Cache leeren
php bin/console cache:clear

# 2. Server starten
symfony server:start

# 3. Tests durchführen:
# - Als Eltern einloggen → Verfügbarkeitsseite
# - Als Admin einloggen → Dashboard, Familien-Liste, Familie-Detail
```

### Erwartete Ergebnisse
- ✅ Eltern sehen ihre Anzahl Dienste
- ✅ Admin sieht Anzahl pro Familie in Liste
- ✅ Admin sieht Statistik im Dashboard
- ✅ Admin sieht Details mit allen Terminen
- ✅ "Kita-Jahre" ist in Navigation sichtbar

## Erweiterungsmöglichkeiten

### Kurzfristig
- [ ] Export der Statistik als CSV/Excel
- [ ] Filter nach Monat/Quartal
- [ ] Vergleich mit "erwarteter" Anzahl

### Mittelfristig
- [ ] Historische Daten (mehrere Jahre)
- [ ] Grafische Darstellung (Chart)
- [ ] Email-Benachrichtigung bei Ungleichverteilung

### Langfristig
- [ ] Dashboard für Eltern mit eigener Statistik
- [ ] Vergleichsansicht mit anderen Familien (anonymisiert)
- [ ] Prognose für Rest des Jahres

## Migration

### Für bestehende Installationen
```bash
# 1. Code aktualisieren
git pull

# 2. Cache leeren
php bin/console cache:clear

# 3. Keine DB-Änderungen nötig - nutzt bestehende Daten!
```

### Breaking Changes
❌ **Keine Breaking Changes**

Alle Änderungen sind additiv:
- Neue Template-Variablen mit Fallbacks
- Neue Spalten/Sektionen, alte bleiben
- Keine Änderungen an bestehenden APIs

## Dokumentation

### Für Admins
**Neue Funktionen**:
1. **Navigationsleiste**: "Kita-Jahre" ist jetzt direkt erreichbar
2. **Familien-Liste**: Zeigt Anzahl Dienste pro Familie für aktuelles Jahr
3. **Dashboard**: Neue Statistik-Tabelle sortiert nach Anzahl Dienste
4. **Familien-Detail**: Zeigt alle zugewiesenen Termine mit Datum und Typ

### Für Eltern
**Neue Funktionen**:
1. **Verfügbarkeitsseite**: Info-Box zeigt, wie oft man schon eingeteilt wurde

## Support

### Häufige Fragen

**F: Warum sehe ich "0 Dienste"?**
A: Der Plan wurde noch nicht generiert oder Sie haben keine Verfügbarkeiten angegeben.

**F: Zählt das auch manuelle Zuweisungen?**
A: Ja, alle Zuweisungen (automatisch + manuell) werden gezählt.

**F: Kann ich Dienste aus Vorjahren sehen?**
A: Aktuell nur für das aktive Jahr. Historische Daten in zukünftiger Version geplant.

**F: Wie wird die Fairness berechnet?**
A: Familien mit 2 Personen sollten etwa doppelt so viele Dienste haben wie Alleinerziehende.

## Referenzen

- **Controller**: 
  - `src/Controller/Parent/ParentController.php`
  - `src/Controller/Admin/PartyController.php`
- **Templates**:
  - `templates/base.html.twig`
  - `templates/parent/availability.html.twig`
  - `templates/admin/party/index.html.twig`
  - `templates/admin/party/show.html.twig`
  - `templates/admin/dashboard/index.html.twig`

## Credits

Feature-Request basierend auf praktischer Nutzung und Feedback.
