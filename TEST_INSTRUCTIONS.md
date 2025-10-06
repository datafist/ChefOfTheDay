# Test-Anleitung: Jahresübergreifender Mindestabstand

## Problem
Eine Familie, die am **31.08.2024** (letzter Tag Vorjahr) gekocht hat, wurde am **01.09.2024** (erster Tag neues Jahr) wieder zugewiesen.

##  Fix
Der Algorithmus wurde angepasst:
- **Ziel:** 6 Wochen (42 Tage) Abstand
- **Minimum:** 4 Wochen (28 Tage) im Notfall
- **Blockiert:** Unter 4 Wochen

## Test-Setup (bereits durchgeführt)

### 1. Datenbank zurückgesetzt ✅
```bash
php bin/console doctrine:database:drop --force --if-exists
php bin/console doctrine:database:create
php bin/console doctrine:schema:create
php bin/console doctrine:fixtures:load --no-interaction
```

### 2. LastYearCooking Eintrag erstellt ✅
```sql
INSERT INTO last_year_cookings (party_id, kita_year_id, last_cooking_date) 
VALUES (1, 1, '2024-08-31');
```

**Bedeutung:**
- Familie "Max" (ID=1) hatte ihren letzten Kochdienst am **31.08.2024**

### 3. Verfügbarkeiten erstellt ✅
Alle 6 Familien sind an allen Werktagen (Mo-Fr) verfügbar.

## 🧪 Test durchführen

### Schritt 1: Admin-Login
1. Öffne: http://127.0.0.1:8000/admin
2. Login: `admin@kita.local` / `admin123`

### Schritt 2: Plan generieren
1. Klick auf **"Plan generieren"** Button
2. Warte auf Erfolgs-Meldung
3. Klick auf **"Kalender-Ansicht"**

### Schritt 3: Prüfung

#### September 2024 prüfen:
- **02.09.2024 (Mo):** Familie Max sollte **NICHT** zugewiesen sein ❌
- **03.09.2024 (Di):**Familie Max sollte **NICHT** zugewiesen sein ❌
- **04.09.2024 (Mi):** Familie Max sollte **NICHT** zugewiesen sein ❌
- **... gesamter September:** Familie Max sollte **NICHT** vorkommen

#### Oktober 2024 prüfen:
- **Frühester Termin für Max:** 
  - 4 Wochen = 28 Tage = **28.09.2024** (Samstag, aber Wochenende → nächster Werktag **30.09.2024**)
  - 6 Wochen = 42 Tage = **12.10.2024** (Samstag, aber Wochenende → nächster Werktag **14.10.2024**)

**Erwartung:**
- Max erscheint frühestens Ende September / Anfang Oktober
- Andere Familien (Sophie, Leon, Emma, Noah, Mia) werden im September zugewiesen

## ✅ Erfolgs-Kriterien

### ❌ FALSCH (alter Algorithmus):
```
01.09.2024 → Max (NUR 1 TAG ABSTAND!)
03.09.2024 → Sophie
05.09.2024 → Leon
...
```

### ✅ RICHTIG (neuer Algorithmus):
```
02.09.2024 → Sophie
03.09.2024 → Leon
04.09.2024 → Emma
05.09.2024 → Noah
06.09.2024 → Mia
09.09.2024 → Sophie
10.09.2024 → Leon
...
30.09.2024 → Max (29 Tage = 4+ Wochen, Notfall OK)
oder besser:
14.10.2024 → Max (44 Tage = 6+ Wochen, optimal!)
```

## 🔍 Detaillierte Prüfung

### Option A: Kalender-Ansicht
1. Navigiere durch September 2024
2. Prüfe visuell: "Max" sollte nicht erscheinen
3. Gehe zu Oktober 2024
4. Finde ersten Eintrag für "Max"
5. Prüfe Datum

### Option B: Listen-Ansicht
1. Auf Admin-Dashboard bleiben
2. Scrolle durch die Liste
3. Finde ersten Eintrag für "Familie Müller (Max)"
4. Prüfe Datum

### Option C: SQL-Abfrage
```bash
php bin/console doctrine:query:sql "
SELECT ca.assigned_date, p.child_name, 
       DATEDIFF(ca.assigned_date, '2024-08-31') as days_since_last
FROM cooking_assignments ca 
JOIN parties p ON ca.party_id = p.id 
WHERE p.child_name = 'Max'
ORDER BY ca.assigned_date 
LIMIT 3"
```

**Erwartete Ausgabe:**
```
 assigned_date   child_name   days_since_last  
 2024-09-30      Max          30               (⚠️ Notfall, aber OK)
 oder
 2024-10-14      Max          44               (✅ Optimal!)
```

**NICHT erwartete Ausgabe:**
```
 assigned_date   child_name   days_since_last  
 2024-09-01      Max          1                (❌ FEHLER!)
 2024-09-02      Max          2                (❌ FEHLER!)
```

## 📊 Weitere Tests

### Test 1: Alle ersten Zuweisungen prüfen
```sql
SELECT 
    p.child_name,
    MIN(ca.assigned_date) as first_assignment,
    DATEDIFF(MIN(ca.assigned_date), '2024-09-01') as days_from_year_start
FROM cooking_assignments ca 
JOIN parties p ON ca.party_id = p.id 
GROUP BY p.child_name
ORDER BY first_assignment;
```

**Erwartung:**
- Max: **Nicht** am 01.09. oder 02.09.
- Andere Familien: Ab 02.09. möglich

### Test 2: Abstände zwischen Max' Zuweisungen
```sql
SELECT 
    ca.assigned_date,
    LAG(ca.assigned_date) OVER (ORDER BY ca.assigned_date) as previous_date,
    DATEDIFF(ca.assigned_date, LAG(ca.assigned_date) OVER (ORDER BY ca.assigned_date)) as days_between
FROM cooking_assignments ca 
JOIN parties p ON ca.party_id = p.id 
WHERE p.child_name = 'Max'
ORDER BY ca.assigned_date;
```

**Erwartung:**
- Erster Abstand von 31.08.2024: **Mindestens 28 Tage**
- Zwischen Zuweisungen: **Möglichst ~42 Tage**

## 🎯 Zusammenfassung

**Vor dem Fix:**
- Max (31.08.2024) → 01.09.2024 (1 Tag) ❌

**Nach dem Fix:**
- Max (31.08.2024) → ~30.09.2024 (29 Tage) oder ~14.10.2024 (44 Tage) ✅

**Logik:**
1. **Primär:** Familien mit 6+ Wochen Abstand werden bevorzugt
2. **Sekundär:** Falls keine vorhanden, Familien mit 4+ Wochen (Notfall)
3. **Blockiert:** Familien mit <4 Wochen werden ignoriert

---

## 📝 Hinweise

- Test-Daten sind bereits vorbereitet
- Einfach "Plan generieren" klicken
- Im Kalender visuell prüfen
- Max sollte im September **nicht** erscheinen

**Status:** ✅ Bereit zum Testen!
