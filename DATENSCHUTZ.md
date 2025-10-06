# Datenschutz-Konzept

## Grundprinzip: Datensparsamkeit

Gemäß DSGVO werden personenbezogene Daten **nur so lange gespeichert, wie sie für den Zweck erforderlich sind**.

## Löschpflicht bei Kita-Austritt

### 1. **Wann werden Daten gelöscht?**

Wenn ein Kind die Kita verlässt (z.B. am Ende des Kita-Jahres zur Einschulung), müssen **alle personenbezogenen Daten der Familie gelöscht werden**:

- ✅ Kindname und Geburtsjahr
- ✅ Namen der Erziehungsberechtigten
- ✅ E-Mail-Adresse
- ✅ Login-Passwort
- ✅ Verfügbarkeitseinträge
- ✅ Kochplan-Zuweisungen (vergangene Jahre)
- ✅ LastYearCooking-Einträge

### 2. **Wie werden Daten gelöscht?**

#### Option A: Manuelle Löschung durch Admin

1. **Admin-Bereich** öffnen → **Familien**
2. Familie auswählen → **Löschen-Button** klicken
3. Bestätigung → Familie wird vollständig gelöscht

Die Löschfunktion ist unter folgender Route verfügbar:
- Route: `admin_party_delete`
- Controller: `PartyController::delete()`
- Methode: POST mit CSRF-Token

#### Option B: Automatisierte Jahres-Bereinigung (empfohlen)

Für größere Kitas mit vielen Abgängen empfiehlt sich ein **Bereinigungsskript**:

```bash
# Beispiel: Automatische Löschung beim Jahreswechsel
php bin/console app:cleanup-old-families
```

**Hinweis:** Diese Funktion muss noch implementiert werden (siehe Roadmap unten).

### 3. **Was passiert mit historischen Daten?**

#### Kochpläne vergangener Jahre
- **Problem:** Gelöschte Familien hinterlassen "Lücken" in historischen Kochplänen
- **Lösung:** Historische Pläne werden **mit anonymisierten Platzhaltern** angezeigt
  - Beispiel: `[Familie gelöscht]` statt "Familie Müller"

#### Statistiken
- **Problem:** Statistiken benötigen aggregierte Daten
- **Lösung:** Nur **anonymisierte Aggregatdaten** werden gespeichert
  - Beispiel: "Anzahl Familien: 45" (ohne Namen)
  - Keine personenbezogenen Daten in Statistiken

### 4. **Test-Szenario**

Die `LargeScaleTestFixtures` simulieren einen realistischen Jahreswechsel:

**Jahr 24/25:**
- Start: 45 Familien
- Simulierter Kochplan mit LastYearCooking-Einträgen

**Jahreswechsel (Datenschutz):**
- 🗑️ **4 Familien werden gelöscht** (Kinder verlassen Kita)
- ➕ **4 neue Familien** werden hinzugefügt

**Jahr 25/26:**
- 45 Familien (41 verbleibende + 4 neue)
- Keine Daten der ausgeschiedenen Familien mehr vorhanden

### 5. **Technische Umsetzung**

#### Cascade-Löschung

Die `Party`-Entität ist so konfiguriert, dass beim Löschen **alle zugehörigen Daten automatisch gelöscht werden**:

```php
#[ORM\OneToMany(mappedBy: 'party', targetEntity: Availability::class, cascade: ['remove'], orphanRemoval: true)]
private Collection $availabilities;

#[ORM\OneToMany(mappedBy: 'party', targetEntity: CookingAssignment::class, cascade: ['remove'], orphanRemoval: true)]
private Collection $cookingAssignments;

#[ORM\OneToMany(mappedBy: 'party', targetEntity: LastYearCooking::class, cascade: ['remove'], orphanRemoval: true)]
private Collection $lastYearCookings;
```

#### Soft-Delete vs. Hard-Delete

**Aktuell:** Hard-Delete (vollständige Löschung aus Datenbank)
- ✅ DSGVO-konform
- ✅ Datensparsamkeit
- ❌ Keine Wiederherstellung möglich

**Alternative:** Soft-Delete (Markierung als gelöscht)
- ⚠️ Nur mit Anonymisierung DSGVO-konform
- ⚠️ Erfordert zusätzliche Löschfristen-Logik

**Empfehlung:** Hard-Delete beibehalten (einfacher und datenschutzkonformer)

## Roadmap: Zu implementierende Features

### Priorität 1: Notwendig für Produktivbetrieb

- [ ] **Admin-Dashboard:** Warnung bei auslaufenden Kita-Jahren
  - "In 30 Tagen endet das Kita-Jahr. Bitte überprüfen Sie, welche Familien die Kita verlassen."

- [ ] **Bulk-Löschung:** Mehrere Familien gleichzeitig löschen
  - Checkbox-Auswahl in Familien-Übersicht
  - "Ausgewählte löschen"-Button

### Priorität 2: Komfort-Features

- [ ] **Console-Command:** `php bin/console app:cleanup-old-families`
  - Interaktive Auswahl: Welche Familien verlassen die Kita?
  - Sicherheitsabfrage vor Löschung

- [ ] **Historische Pläne:** Anonymisierung gelöschter Familien
  - Platzhalter: `[Familie gelöscht am DD.MM.YYYY]`
  - Nur in Ansichten vergangener Jahre

### Priorität 3: Optional

- [ ] **Export vor Löschung:** Archivierungs-Funktion
  - Datenschutz-konformer Export (nur für gesetzliche Aufbewahrungsfristen)
  - Verschlüsselter Export als PDF/CSV

- [ ] **Lösch-Protokoll:** Logging aller Löschvorgänge
  - Admin-Name, Zeitstempel, gelöschte Familie
  - Für Nachweispflicht bei Datenschutz-Audits

## Zusammenfassung

✅ **Aktuelle Umsetzung:**
- Manuelle Löschung pro Familie möglich
- Cascade-Delete löscht alle zugehörigen Daten
- Testdaten simulieren Datenschutz-konformen Jahreswechsel

⚠️ **Noch zu implementieren:**
- Bulk-Löschung für Jahreswechsel
- Warnung bei auslaufendem Kita-Jahr
- Console-Command für automatisierte Bereinigung

📋 **Best Practice:**
Am Ende jedes Kita-Jahres (ca. im Juli/August):
1. Liste aller ausscheidenden Kinder erstellen
2. Eltern über Löschung informieren (Transparenz)
3. Daten löschen (manuell oder per Script)
4. Neues Kita-Jahr aktivieren
5. Neue Familien anlegen
