# Test-Szenario: Realistische Verfügbarkeiten

## Übersicht

Die Testdaten wurden an die praktische Realität angepasst: **Viele Familien haben eingeschränkte Verfügbarkeiten** (z.B. nur Montag und Freitag durch Arbeitszeitmodelle).

## Testdaten-Setup

### Jahr 24/25 (Abgeschlossen)
- **45 Familien**
- **Kochplan generiert**: 176 Zuweisungen (von 216 möglichen Werktagen)
- **20 Tage ohne Zuweisung**: Keine Familie war verfügbar
- **LastYearCooking Einträge**: Basierend auf tatsächlichen Zuweisungen
- **Status**: `isActive = false`

### Jahr 25/26 (Aktiv - zum Testen)
- **49 Familien**: 45 bestehend + 4 neu
- **Verfügbarkeiten angelegt**: Realistische Szenarien
- **KEIN Plan generiert**: Bereit zum manuellen Testen in der UI
- **Status**: `isActive = true`

## Verfügbarkeits-Szenarien (realitätsnah)

Die Testdaten enthalten folgende Verteilung:

### 🔴 15% Sehr eingeschränkt
- **Nur 1-2 Tage pro Woche**
- Beispiele:
  - Nur Montag + Freitag (z.B. Homeoffice-Tage)
  - Nur Dienstag + Donnerstag
- Realität: Starre Arbeitszeitmodelle, Alleinerziehende mit wenig Flexibilität

### 🟠 20% Eingeschränkt
- **2-3 Tage pro Woche**
- Beispiele:
  - Montag, Mittwoch, Freitag
  - Dienstag, Donnerstag
- Realität: Teilzeit, feste Arbeitstage

### 🟡 35% Mittel flexibel
- **3-4 Tage pro Woche** (ein fester Tag ausgeschlossen)
- Beispiele:
  - Mo, Di, Mi, Do (kein Freitag)
  - Di, Mi, Do, Fr (kein Montag)
- Realität: Ein Tag fix mit Terminen/Meetings

### 🟢 25% Flexibel
- **80-90% der Tage verfügbar**
- Wenige zufällige Lücken (Urlaub, Termine)
- Realität: Flexible Arbeitszeiten, Homeoffice

### 🔵 5% Sehr flexibel
- **Alle Tage verfügbar**
- Realität: Vollzeit-Elternteil, Rentner als Großeltern, etc.

## Test-Durchführung

### Schritt 1: Server starten
```bash
symfony server:start
# oder
php -S localhost:8000 -t public/
```

### Schritt 2: Browser öffnen
```
http://localhost:8000
```

### Schritt 3: Admin-Login
- Email: `admin@kita.local`
- Passwort: `admin123`

### Schritt 4: Plan für 25/26 generieren
1. Navigation: **Admin-Dashboard** → **Kochplan generieren**
2. Jahr auswählen: **25/26**
3. Button: **"Plan generieren"** klicken
4. Warten auf Verarbeitung

### Schritt 5: Ergebnisse prüfen

#### ✅ Test 1: Verfügbarkeits-Prüfung
**Frage**: Werden nur verfügbare Termine zugewiesen?

**Prüfung**:
1. Kalenderansicht öffnen
2. Eine Familie mit eingeschränkter Verfügbarkeit auswählen (z.B. nur Mo+Fr)
3. Prüfen: Hat diese Familie nur an Mo oder Fr Zuweisungen?
4. Als Parent einloggen → Verfügbarkeit ansehen → mit Zuweisungen vergleichen

**Erwartung**: ✅ Alle Zuweisungen sind an verfügbaren Tagen

#### ✅ Test 2: LastYearCooking-Berücksichtigung
**Frage**: Werden Altdaten (letzte Zuweisung aus 24/25) berücksichtigt?

**Prüfung**:
1. Datenbank prüfen: `LastYearCooking`-Tabelle für Familie X
2. Erste Zuweisung in 25/26 für Familie X prüfen
3. Zeitlicher Abstand berechnen

**Erwartung**: ✅ Familien, die im August 24/25 gekocht haben, bekommen erst später wieder Termine

#### ✅ Test 3: Fairness mit eingeschränkten Verfügbarkeiten
**Frage**: Bekommen Familien mit weniger Verfügbarkeit trotzdem faire Zuteilung?

**Prüfung**:
1. Zuweisungen pro Familie zählen
2. Vergleich: Sehr eingeschränkte vs. flexible Familien
3. Anzahl Zuweisungen / Anzahl verfügbare Tage = Auslastung

**Erwartung**: 
- ✅ Flexible Familien haben mehr absolute Zuweisungen
- ✅ Alle Familien haben ähnliche **relative** Auslastung (% ihrer verfügbaren Tage)
- ⚠️ Sehr eingeschränkte Familien (nur Mo+Fr) haben evtl. höhere Auslastung, da weniger Ausweichmöglichkeiten

#### ⚠️ Test 4: Nicht zuweisbare Tage
**Frage**: Gibt es Tage, an denen keine Familie verfügbar ist?

**Prüfung**:
1. Konflikte-Meldungen beim Generieren lesen
2. Kalender durchsuchen nach Tagen ohne Zuweisung
3. Für diese Tage: Verfügbarkeiten aller Familien prüfen

**Erwartung**: 
- ⚠️ Es wird Tage geben, an denen keine oder zu wenige Familien verfügbar sind
- ℹ️ Dies ist **normal und gewollt** bei realistischen Daten
- 💡 Admin muss diese Tage manuell klären (Notfall-Lösung, externe Hilfe, etc.)

#### ✅ Test 5: Mindest-Abstände
**Frage**: Werden die Mindest-Abstände zwischen Zuweisungen eingehalten?

**Prüfung**:
1. Eine Familie auswählen
2. Alle Zuweisungen chronologisch auflisten
3. Abstände in Tagen berechnen

**Erwartung**: 
- ✅ Ideal: 6+ Wochen zwischen Zuweisungen (TARGET)
- ✅ Minimum: 4+ Wochen zwischen Zuweisungen (MIN)
- ⚠️ Bei sehr eingeschränkten Familien evtl. kürzere Abstände nötig

## Bekannte Szenarien / Edge Cases

### Szenario A: "Montag-Freitag-Problem"
**Situation**: Viele Familien nur Mo+Fr verfügbar

**Folge**: 
- Montage und Freitage sind "überfüllt"
- Mi bleibt oft leer
- Algorithmus verteilt auf Mo+Fr, aber mit kürzeren Abständen

**Realität**: ✅ Normal - entspricht der Praxis

### Szenario B: "Urlaubs-Cluster"
**Situation**: Im Juni/Juli haben viele Familien Urlaub

**Folge**:
- Weniger verfügbare Familien
- Keine Zuweisungen möglich
- Konflikte in den Sommermonaten

**Realität**: ✅ Normal - muss manuell gelöst werden

### Szenario C: "Neue Familien"
**Situation**: 4 neue Familien in 25/26

**Folge**:
- Keine `LastYearCooking` Einträge
- Werden als "noch nie zugewiesen" behandelt
- Erhalten Priorität bei Zuweisungen

**Realität**: ✅ Gewollt - neue Familien sollen schnell integriert werden

## Datenbank-Prüfungen (SQL)

### Verfügbarkeiten einer Familie anzeigen
```sql
SELECT 
    p.child_name,
    a.available_dates
FROM parties p
JOIN availabilities a ON a.party_id = p.id
JOIN kita_years k ON k.id = a.kita_year_id
WHERE k.is_active = true
AND p.child_name = 'Max';
```

### Zuweisungen einer Familie
```sql
SELECT 
    p.child_name,
    ca.assigned_date,
    ca.is_manually_assigned
FROM cooking_assignments ca
JOIN parties p ON p.id = ca.party_id
JOIN kita_years k ON k.id = ca.kita_year_id
WHERE k.is_active = true
AND p.child_name = 'Max'
ORDER BY ca.assigned_date;
```

### Nicht zugewiesene Tage finden
```sql
-- Alle Werktage im Jahr (ohne Feiertage/Ferien)
-- minus
-- Alle zugewiesenen Tage
-- = Fehlende Zuweisungen
```

### LastYearCooking prüfen
```sql
SELECT 
    p.child_name,
    lyc.last_cooking_date,
    k.start_date as year_start
FROM last_year_cookings lyc
JOIN parties p ON p.id = lyc.party_id
JOIN kita_years k ON k.id = lyc.kita_year_id
WHERE k.start_date = '2024-09-01'
ORDER BY lyc.last_cooking_date;
```

## Erwartete Erkenntnisse

Nach diesem Test sollten folgende Fragen beantwortet sein:

1. ✅ **Funktioniert die Verfügbarkeits-Prüfung korrekt?**
   - Ja, wenn alle Zuweisungen nur an verfügbaren Tagen sind

2. ⚠️ **Wie viele Tage bleiben unbesetzt bei realistischen Daten?**
   - Erwartung: 10-30 Tage bei 45 Familien mit eingeschränkten Verfügbarkeiten
   - Realität zeigt, wie viel manuelle Nacharbeit nötig ist

3. ✅ **Ist die Fairness trotz unterschiedlicher Verfügbarkeiten gewahrt?**
   - Ja, wenn relative Auslastung ähnlich ist
   - Flexible Familien kochen öfter (absolut), aber nicht überproportional

4. ✅ **Werden Altdaten korrekt berücksichtigt?**
   - Ja, wenn Familien mit kürzlichem Kochdienst später wieder dran sind

5. 💡 **Sind die Abstände realistisch?**
   - Bei eingeschränkten Verfügbarkeiten evtl. kürzere Abstände nötig
   - Algorithmus sollte pragmatisch sein (4 Wochen statt ideal 6 Wochen)

## Nächste Schritte nach dem Test

### Bei erfolgreichen Tests:
- ✅ System ist produktionsreif
- 📝 Dokumentation für Kita-Admin erstellen
- 🎓 Schulung für Admin durchführen

### Bei Problemen:
1. **Zu viele nicht zuweisbare Tage**: 
   - Eltern motivieren, mehr Verfügbarkeiten anzugeben
   - Notfall-Regelungen definieren (externe Hilfe, etc.)

2. **Unfaire Verteilung**:
   - Algorithmus-Parameter anpassen (min/target days)
   - Gewichtung für Alleinerziehende prüfen

3. **Performance-Probleme**:
   - Bei >50 Familien evtl. Optimierung nötig
   - Caching für Verfügbarkeiten

## Kontakt bei Fragen

- GitHub Issues erstellen
- Oder direkt im Code-Review ansprechen
