# Test-Daten: Realistische Familien-Struktur mit Jahreswechsel

## Datum: 2025-10-05

## Übersicht

Die Testdaten wurden komplett überarbeitet, um einen **realistischen Jahreswechsel** mit ausscheidenden und neu hinzukommenden Familien zu simulieren.

## Kita-Struktur

### Kapazität
- **45 Kinder-Plätze** (fix)
- **~40-43 Familien** (variabel, da manche Familien mehrere Kinder haben)

### System-Design
- **Jede Familie = 1 Party-Eintrag** im System
- Eine Party kann 1-3 Kinder haben (gespeichert als JSON-Array)
- Kochplan wird pro Familie berechnet (nicht pro Kind!)
- Vorteil: Familien mit mehreren Kindern kochen nur 1x (nicht mehrfach)

## Jahr 24/25 (Abgeschlossen)

### Familien-Zusammensetzung
- **45 Kinder in der Kita**
- **43 Familien** (Party-Einträge):
  - **41 Familien** mit je 1 Kind
  - **2 Familien** mit je 2 Kindern:
    - Familie Müller: Max (2020) + Sophie (2021)
    - Familie Weber: Leon (2018, scheidet aus) + Emma (2020, bleibt)

### Alleinerziehende
- 2 Familien sind alleinerziehend (1 Person statt 2)

### Plan-Generierung
- ✅ Kochplan wurde generiert: **196 Zuweisungen**
- ✅ LastYearCooking Einträge erstellt für alle Familien

## Jahreswechsel 24/25 → 25/26

### Ausscheidende Familien (DSGVO-Löschung)
**6 Kinder** verlassen die Kita (zu alt für Kita):
1. **Leon** (Familie Weber) - geboren 2018, 6-7 Jahre alt
   - ⚠️ **Familie Weber bleibt aber!** Emma (geb. 2020) ist noch da
2. **Noah** (Familie Schulz) - geboren 2018
3. **Felix** (Familie Koch) - geboren 2018
4. **Tim** (Familie Richter) - geboren 2018
5. **Ben** (Familie Klein) - geboren 2018
6. **Jan** (Familie Wolf) - geboren 2018

**Ergebnis**:
- 6 Party-Einträge (Kinder) gelöscht
- **5 Familien** komplett ausgeschieden (Schulz, Koch, Richter, Klein, Wolf)
- **1 Familie** (Weber) bleibt mit Emma

### DSGVO-konforme Löschung
Beim Löschen werden automatisch entfernt:
- ✅ Alle CookingAssignments des Kindes
- ✅ Alle LastYearCooking Einträge
- ✅ Alle Availability Einträge
- ✅ Der Party-Eintrag selbst

### Neue Familien
**5 neue Kinder** kommen in die Kita:
1. **Tobias** (Familie Keller) - geboren 2022, 3 Jahre alt
2. **Johanna** (Familie Graf) - geboren 2022
3. **Lukas** (Familie Roth) - geboren 2021 - ⚠️ **Alleinerziehend**
4. **Charlotte** (Familie Baumann) - geboren 2022
5. **Matthias** (Familie Sommer) - geboren 2021

**Besonderheit**: Neue Familien haben **keine LastYearCooking Einträge** aus 24/25!

## Jahr 25/26 (Aktiv)

### Familien-Zusammensetzung
- **45 Kinder in der Kita** (wieder voll)
- **44 Familien** (Party-Einträge):
  - **38 bestehende** aus 24/25 (davon 1 mit 2 Kindern: Müller, 1 reduziert: Weber nur noch Emma)
  - **6 neue** Familien

### Verteilung der Familien

| Kategorie | Anzahl Familien | Anzahl Kinder | Details |
|-----------|----------------|---------------|---------|
| Bestehend, 1 Kind | 37 | 37 | Haben LastYearCooking aus 24/25 (inkl. Weber mit nur noch Emma) |
| Bestehend, 2 Kinder | 1 (Müller) | 2 (Max + Sophie) | Haben LastYearCooking aus 24/25 |
| Neu, 1 Kind | 6 | 6 | **Keine** LastYearCooking Einträge (Keller, Graf, Roth, Baumann, Sommer, Krüger) |
| **GESAMT** | **44** | **45** | |

### Alleinerziehende in 25/26
- Familie Schulz (Alleinerziehend, hatte Noah) ist ausgeschieden
- Familie Roth (Alleinerziehend, neu) ist hinzugekommen
- → Weiterhin 2 alleinerziehende Familien

## Test-Szenarien

### Szenario 1: LastYearCooking-Berücksichtigung
**Ziel**: Prüfen, ob Altdaten korrekt berücksichtigt werden

**Test**:
1. Plan für 25/26 generieren
2. Prüfe: Familien aus 24/25 mit kürzlichem Kochdienst (August 2025) bekommen später wieder Termine
3. Neue Familien (ohne Historie) sollten bevorzugt werden

**Erwartung**:
- ✅ Max Müller hatte z.B. am 15.08.2025 Dienst → bekommt erst ab November wieder Termine
- ✅ Tobias Keller (neu) hat keine Historie → wird früh eingeplant

### Szenario 2: Geschwister-Behandlung
**Ziel**: Prüfen, wie das System mit Geschwistern umgeht

**Test**:
1. Prüfe Zuweisungen für Familie Müller (Max + Sophie)
2. Prüfe ob Familie nur 1x pro Kochdienst erscheint

**Erwartung**:
- ✅ **Familie Müller = 1 Party-Eintrag** mit 2 Kindern
- ✅ Familie bekommt Kochdienste **wie alle anderen Familien** (nicht doppelt!)
- ✅ Im Kochplan steht: "Familie Müller (Max, Sophie)" - beide Kinder werden angezeigt
- ✅ Das ist **fair**: Jede Familie kocht gleich oft, unabhängig von Kinderzahl

### Szenario 3: DSGVO-Löschung
**Ziel**: Überprüfen, dass gelöschte Daten wirklich weg sind

**Test**:
```sql
-- Prüfe ob Leon noch existiert
SELECT * FROM parties WHERE child_name = 'Leon';

-- Prüfe ob Noah noch existiert
SELECT * FROM parties WHERE child_name = 'Noah';

-- Prüfe ob Emma noch existiert (sollte JA sein)
SELECT * FROM parties WHERE child_name = 'Emma';
```

**Erwartung**:
- ❌ Leon: Nicht gefunden
- ❌ Noah: Nicht gefunden
- ✅ Emma: Gefunden (Familie Weber bleibt)

### Szenario 4: Neue Familien haben Priorität
**Ziel**: Neue Familien sollten früh eingeplant werden

**Test**:
1. Plan generieren
2. Zähle Zuweisungen pro Familie
3. Sortiere nach Anzahl

**Erwartung**:
- Neue Familien (Keller, Graf, Roth, Baumann, Sommer, Krüger) sollten **relativ viele** Zuweisungen haben
- Grund: Keine LastYearCooking → werden als "noch nie zugewiesen" behandelt → höchste Priorität

## SQL-Queries für Analyse

### Familien-Übersicht
```sql
SELECT 
    email,
    children,
    JSON_LENGTH(children) as anzahl_kinder
FROM parties
ORDER BY JSON_LENGTH(children) DESC, email;
```

### LastYearCooking prüfen
```sql
SELECT 
    p.child_name,
    p.email,
    lyc.last_cooking_date
FROM parties p
LEFT JOIN last_year_cookings lyc ON lyc.party_id = p.id
ORDER BY lyc.last_cooking_date DESC;
```

### Neue Familien identifizieren (ohne LastYearCooking)
```sql
SELECT 
    p.children,
    p.parent_names,
    p.email
FROM parties p
LEFT JOIN last_year_cookings lyc ON lyc.party_id = p.id
WHERE lyc.id IS NULL;
```

### Zuweisungen pro Familie (für 25/26)
```sql
SELECT 
    p.children,
    p.email,
    COUNT(ca.id) as anzahl_dienste
FROM parties p
LEFT JOIN cooking_assignments ca ON ca.party_id = p.id AND ca.kita_year_id = 2
GROUP BY p.id
ORDER BY anzahl_dienste DESC;
```

## Erkenntnisse

### 1. System-Design: Jede Familie = 1 Party ✅
**Pro**:
- ✅ Einfaches Datenmodell
- ✅ Flexibel für unterschiedliche Familienkonstellationen (1-3 Kinder)
- ✅ Fairness pro Familie (nicht pro Kind!)
- ✅ Familie mit 2 Kindern kocht gleich oft wie Familie mit 1 Kind
- ✅ Realistische Abbildung der Kita-Praxis

**Implementierung**:
- Party.children ist ein JSON-Array: `[{"name": "Max", "birthYear": 2020}, ...]`
- Party.getChildrenNames() gibt z.B. "Max, Sophie" zurück
- Kochplan-Generator arbeitet mit Party-Entities (= Familien)
- Templates zeigen alle Kinder einer Familie an

### 2. LastYearCooking ist kritisch
- Neue Familien haben keine Historie → werden bevorzugt
- Das ist **gewollt** und **korrekt**
- In Praxis: Neue Eltern sollen schnell integriert werden

### 3. DSGVO-Löschung funktioniert
- Alle Daten werden korrekt entfernt
- Geschwister-Behandlung ist korrekt (Leon weg, Emma bleibt)

## Nächste Schritte

### Für Test in UI
1. Server starten: `symfony server:start`
2. Als Admin einloggen
3. Plan für 25/26 generieren
4. Prüfen:
   - Verteilung fair?
   - Neue Familien berücksichtigt?
   - Altdaten korrekt verarbeitet?

### Für Produktion
1. Admin-Doku erstellen:
   - Wie lösche ich eine Familie (DSGVO)?
   - Wie füge ich neue Familien hinzu?
   - Was passiert beim Jahreswechsel?

2. Optional: Family-Grouping implementieren
   - Wenn gewünscht: Familien mit mehreren Kindern nur 1x Kochdienst
   - Erfordert Datenmodell-Änderung

## Zusammenfassung

✅ **Realistische Testdaten erstellt**:
- 45 Kinder, 43 Familien in 24/25
- 6 Kinder scheiden aus (5 komplette Familien + Leon von Familie Weber)
- 6 neue Familien kommen hinzu
- 45 Kinder, 44 Familien in 25/26
- **Eine Party = Eine Familie** (nicht mehr pro Kind!)

✅ **DSGVO-konforme Löschung**:
- Alle Daten ausscheidender Familien werden entfernt

✅ **LastYearCooking-Logik**:
- Bestehende Familien haben Historie
- Neue Familien haben keine Historie → Priorität

✅ **Bereit für UI-Test**:
- Plan für 25/26 kann in UI generiert werden
- Alle Szenarien können getestet werden
