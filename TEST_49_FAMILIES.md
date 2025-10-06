# 🧪 Test-Anleitung: 49 Familien (Realitätstest)

## Szenario

**Ausgangssituation:**
- **Jahr 24/25:** 45 Familien (43 Paare + 2 Alleinerziehende)
- **Jahr 25/26:** 49 Familien
  - 41 Familien bleiben (4 sind gegangen)
  - 4 neue Familien kommen hinzu
  - Gesamt: 45 Familien

**Ziel:** Testen, ob der Algorithmus mit vielen Familien funktioniert und die Jahr-Übergang-Logik korrekt ist.

---

## 📊 Geladene Test-Daten

### Jahr 24/25 (abgeschlossen)
- ✅ 45 Familien erstellt
- ✅ Unterschiedliche Verfügbarkeiten:
  - 20% haben 100% verfügbar
  - 30% haben 80% verfügbar  
  - 50% haben 60% verfügbar
- ✅ **LastYearCooking Einträge:** Alle 45 Familien haben letzte Zuweisung zwischen 01.08.2025 - 29.08.2025

### Jahr 25/26 (aktiv, kein Plan)
- ✅ 49 Familien total:
  - 41 alte Familien (mit LastYearCooking)
  - 4 neue Familien: **Tobias, Johanna, Lukas, Charlotte** (ohne LastYearCooking)
- ✅ Verfügbarkeiten für alle 49 Familien
- ⏳ **Kein Plan generiert** - wartet auf Admin-Aktion

### Berechnete Abstände

**Konfiguration:**
- 49 Familien (47 Paare + 2 Alleinerziehende)
- Gesamt-Gewicht: 96
- Verfügbare Tage: 197

**Dynamisch berechnet:**
```
TARGET: 38 Tage (~5.4 Wochen)
MIN:    24 Tage (~3.4 Wochen)
```

**Bedeutung:**
- Jede Familie kocht nur ~4x pro Jahr
- Durchschnittlicher Abstand: ~48 Tage (6.9 Wochen)
- Bei Jahr-Übergang: Mindestens 24 Tage zu letzter Zuweisung

---

## 🧪 Test durchführen

### Schritt 1: Login

**Browser öffnen:** http://127.0.0.1:8000/admin  
**Login:** admin@kita.local / admin123

### Schritt 2: Übersicht prüfen

Auf dem Dashboard sollten Sie sehen:
- Kita-Jahr 25/26 ist aktiv
- **Keine Zuweisungen** für 25/26

### Schritt 3: Plan generieren

**Klick auf "Plan generieren"**

**Erwartete Dauer:** 10-30 Sekunden (49 Familien, 197 Tage)

**Erwartete Ausgabe:**
```
✓ Plan erfolgreich generiert!
• ~197 Zuweisungen erstellt
• Mögliche Konflikte: 0-10 (bei vielen Familien normal)
```

### Schritt 4: Ergebnisse prüfen

#### A) Kalender-Ansicht

1. Klick auf "Kalender-Ansicht"
2. Navigiere zu **September 2025**
3. **Prüfe:** Familien mit LastYearCooking Ende August sollten NICHT Anfang September erscheinen

**Beispiel:**
- Familie "Max" hatte letzten Dienst 29.08.2025
- Max sollte **frühestens 22.09.2025** (24 Tage) erscheinen
- Optimal: Ab **06.10.2025** (38 Tage)

#### B) Listen-Ansicht

Scrolle durch die Liste und prüfe:
- Sind alle 49 Familien vertreten?
- Kocht jede Familie ~3-5x im Jahr?
- Gibt es große Lücken im Kalender?

#### C) Neue Familien

Die 4 neuen Familien (**Tobias, Johanna, Lukas, Charlotte**):
- ✅ Haben **kein** LastYearCooking
- ✅ Können ab **01.09.2025** sofort zugewiesen werden
- ✅ Sollten in den ersten Wochen erscheinen (höchste Priorität)

---

## 🔍 Detaillierte SQL-Prüfungen

### Prüfung 1: Jahr-Übergang (LastYearCooking)

```bash
php bin/console doctrine:query:sql "
SELECT 
    p.child_name,
    lyc.last_cooking_date as last_2024,
    MIN(ca.assigned_date) as first_2025,
    DATEDIFF(MIN(ca.assigned_date), lyc.last_cooking_date) as days_between
FROM last_year_cookings lyc
JOIN parties p ON lyc.party_id = p.id
LEFT JOIN cooking_assignments ca ON ca.party_id = p.id AND ca.kita_year_id = 2
GROUP BY p.child_name, lyc.last_cooking_date
HAVING first_2025 IS NOT NULL
ORDER BY days_between
LIMIT 10"
```

**Erwartung:**
- `days_between` für ALLE Familien **≥ 24 Tage**
- Idealerweise viele mit **≥ 38 Tagen**

### Prüfung 2: Neue Familien (ohne LastYearCooking)

```bash
php bin/console doctrine:query:sql "
SELECT 
    p.child_name,
    MIN(ca.assigned_date) as first_assignment,
    COUNT(ca.id) as total_assignments
FROM parties p
LEFT JOIN last_year_cookings lyc ON p.id = lyc.party_id
JOIN cooking_assignments ca ON ca.party_id = p.id AND ca.kita_year_id = 2
WHERE lyc.id IS NULL
GROUP BY p.child_name
ORDER BY first_assignment"
```

**Erwartung:**
- Tobias, Johanna, Lukas, Charlotte erscheinen
- `first_assignment` sollte früh im September sein (01.-15.09.)
- `total_assignments` ~3-5

### Prüfung 3: Verteilung überprüfen

```bash
php bin/console doctrine:query:sql "
SELECT 
    p.child_name,
    COUNT(ca.id) as dienste,
    MIN(ca.assigned_date) as erster_dienst,
    MAX(ca.assigned_date) as letzter_dienst
FROM parties p
JOIN cooking_assignments ca ON ca.party_id = p.id
WHERE ca.kita_year_id = 2
GROUP BY p.child_name
ORDER BY dienste DESC
LIMIT 20"
```

**Erwartung:**
- Paare: ~4-5 Dienste
- Alleinerziehende (Mia, Amelie): ~2 Dienste
- Relativ gleichmäßige Verteilung

### Prüfung 4: Abstände innerhalb des Jahres

```bash
php bin/console doctrine:query:sql "
SELECT 
    p.child_name,
    ca.assigned_date,
    LAG(ca.assigned_date) OVER (PARTITION BY p.id ORDER BY ca.assigned_date) as previous_date,
    DATEDIFF(ca.assigned_date, LAG(ca.assigned_date) OVER (PARTITION BY p.id ORDER BY ca.assigned_date)) as days_since_last
FROM cooking_assignments ca
JOIN parties p ON ca.party_id = p.id
WHERE ca.kita_year_id = 2
HAVING previous_date IS NOT NULL AND days_since_last < 24
ORDER BY days_since_last
LIMIT 20"
```

**Erwartung:**
- **Idealerweise keine Ergebnisse** (keine Abstände < 24 Tage)
- Falls Ergebnisse: Nur bei extremen Verfügbarkeits-Einschränkungen

### Prüfung 5: Konflikte anzeigen

Nach Plan-Generierung im Browser die Flash-Messages prüfen:

```
⚠️ Konflikt: Kein geeignete Familie für 15.10.2025 gefunden.
```

**Bedeutung:** An diesem Tag haben alle verfügbaren Familien entweder:
- Zu kurzen Abstand zur letzten Zuweisung (< 24 Tage), oder
- Sind nicht verfügbar (Verfügbarkeits-Angabe)

---

## ✅ Erfolgs-Kriterien

### ✅ PASS-Kriterien

1. **Plan vollständig generiert** (~197 Zuweisungen)
2. **Jahr-Übergang korrekt:**
   - Alle Familien mit LastYearCooking haben ≥ 24 Tage Abstand
   - Idealerweise viele mit ≥ 38 Tagen
3. **Neue Familien bevorzugt:**
   - Tobias, Johanna, Lukas, Charlotte erscheinen früh (September)
4. **Faire Verteilung:**
   - Paare: ~4-5 Dienste
   - Alleinerziehende: ~2 Dienste
5. **Wenig Konflikte:** < 10 unbeset Tage (bei 197 Tagen = < 5%)

### ⚠️ AKZEPTABEL

- Einige Abstände zwischen 24-38 Tagen (Notfall)
- 5-15 Konflikte (2-7%) bei schwierigen Verfügbarkeiten

### ❌ FEHLER

- Abstände < 24 Tage am Jahr-Übergang
- Neue Familien erscheinen nicht oder sehr spät
- Extreme Ungleichverteilung (manche Familien 0x, andere 10x)
- > 20 Konflikte (> 10%)

---

## 📊 Vergleich zur kleinen Konfiguration

| Metrik                  | 6 Familien    | 49 Familien   |
|-------------------------|---------------|---------------|
| Verfügbare Tage         | 261           | 197           |
| Gesamt-Gewicht          | 10            | 96            |
| Dienste pro Paar/Jahr   | ~52           | ~4            |
| Durchschnitt Abstand    | ~5 Tage       | ~48 Tage      |
| **TARGET**              | **7 Tage**    | **38 Tage**   |
| **MINIMUM**             | **4 Tage**    | **24 Tage**   |

→ **Algorithmus passt sich automatisch an!**

---

## 🐛 Problembehandlung

### Problem: Viele Konflikte (> 20)

**Ursache:** Zu restriktive Verfügbarkeiten + zu enge Abstände

**Lösung:** In realer Anwendung:
- Admins manuell zuweisen
- Familien um mehr Verfügbarkeit bitten

### Problem: Extreme Ungleichverteilung

**Ursache:** Bug im Algorithmus

**Lösung:** Prüfe Sortierungs-Logik in `CookingPlanGenerator.php` Zeile 350+

### Problem: Generation dauert > 60 Sekunden

**Ursache:** PHP Timeout oder Datenbank-Performance

**Lösung:**
```bash
# Erhöhe PHP Timeout
php -d max_execution_time=300 bin/console ...
```

---

## 📝 Test-Protokoll

Nach dem Test dokumentieren:

```
✅ Plan generiert in: ___ Sekunden
✅ Zuweisungen erstellt: ___
✅ Konflikte: ___
✅ Familien mit < 24 Tage Abstand: ___
✅ Neue Familien erste Zuweisung: ___
✅ Durchschnittlicher Abstand (tatsächlich): ___ Tage
```

---

## 🎯 Zusammenfassung

**Status:** ✅ Test-Daten geladen und bereit!

**Nächste Schritte:**
1. Browser öffnen: http://127.0.0.1:8000/admin
2. Plan generieren
3. Ergebnisse prüfen (Kalender + SQL)
4. Erfolgs-Kriterien validieren

**Erwartetes Ergebnis:** Der Algorithmus sollte:
- ✅ Dynamische Abstände korrekt berechnen (38/24 Tage)
- ✅ Jahr-Übergang respektieren (≥ 24 Tage)
- ✅ Neue Familien bevorzugen
- ✅ Faire Verteilung erreichen

**Viel Erfolg beim Test!** 🚀
