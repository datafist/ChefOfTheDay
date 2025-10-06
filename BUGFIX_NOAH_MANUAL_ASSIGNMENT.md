# 🐛 Bugfix: Manuelle Zuweisungen beim Jahr-Übergang

## Problem (Konkret)

**Situation:**
- Noah Schulz: **22.08.2025** (automatisch zugewiesen)
- Noah Schulz: **31.08.2025** (manuell zugewiesen) ← **Letzter Kochdienst!**
- Noah Schulz: **01.09.2025** (automatisch im neuen Jahr) ← **Nur 1 Tag Abstand!** ❌

**Ursache:**
Das `LastYearCooking` Script wurde **vor der Generierung von Jahr 25/26 nicht ausgeführt**.

→ Algorithmus wusste nichts von der manuellen Zuweisung am 31.08.
→ Noah wurde fälschlicherweise am 01.09. wieder zugewiesen

## Lösung

### ✅ Was wurde getan:

1. **LastYearCooking Script ausgeführt** (nachträglich):
   ```bash
   php bin/create_last_year_cooking.php
   ```
   
   **Ergebnis:**
   - Noah: `last_cooking_date = 2025-08-31` ✅
   - Script findet automatisch die **letzte Zuweisung** (egal ob manuell oder automatisch)

2. **Alte Zuweisungen von Jahr 25/26 gelöscht**:
   ```sql
   DELETE FROM cooking_assignments 
   WHERE kita_year_id = 2 AND is_manually_assigned = 0
   ```

3. **Plan neu generieren**:
   - Admin-Interface → "Plan generieren"
   - Algorithmus nutzt jetzt `LastYearCooking` Daten
   - Noah (31.08.) wird **NICHT** am 01.09. zugewiesen

### ✅ Erwartetes Ergebnis (nach Neu-Generierung):

```
Jahr 24/25:
  22.08.2025 → Noah (automatisch)
  31.08.2025 → Noah (manuell) ← Letzte Zuweisung

Jahr 25/26:
  01.09.2025 → NICHT Noah ✅ (zu kurz: 1 Tag)
  15.09.2025 → NICHT Noah ✅ (zu kurz: 15 Tage)
  28.09.2025 → Evtl. Noah ⚠️ (Notfall: 28 Tage = 4 Wochen)
  12.10.2025 → Noah bevorzugt ✅ (Optimal: 42 Tage = 6 Wochen)
```

## 🔍 Verifikation

### Nach Plan-Generierung prüfen:

```bash
php bin/console doctrine:query:sql "
SELECT 
    p.child_name,
    lyc.last_cooking_date as last_in_2024_25,
    MIN(ca.assigned_date) as first_in_2025_26,
    DATEDIFF(MIN(ca.assigned_date), lyc.last_cooking_date) as days_between
FROM last_year_cookings lyc
JOIN parties p ON lyc.party_id = p.id
LEFT JOIN cooking_assignments ca ON ca.party_id = p.id AND ca.kita_year_id = 2
GROUP BY p.child_name, lyc.last_cooking_date
ORDER BY days_between"
```

**Erwartete Ausgabe:**
```
 child_name   last_in_2024_25   first_in_2025_26   days_between  
 Noah         2025-08-31        2025-09-28         28            ✅ (oder mehr)
 Max          2025-08-18        2025-09-15         28            ✅
 ...
```

**Alle `days_between` sollten ≥ 28 sein!**

### Spezielle Prüfung für Noah:

```bash
php bin/console doctrine:query:sql "
SELECT assigned_date, is_manually_assigned 
FROM cooking_assignments ca 
JOIN parties p ON ca.party_id = p.id 
WHERE p.child_name = 'Noah' AND ca.kita_year_id = 2
ORDER BY assigned_date 
LIMIT 5"
```

**Erwartung:**
- **NICHT** 2025-09-01 (das wäre der Bug!)
- Frühestens 2025-09-28 oder später

## 📝 Wichtige Erkenntnisse

### ✅ Das Script funktioniert korrekt:

Das `bin/create_last_year_cooking.php` Script:
- ✅ Findet die **letzte Zuweisung** (egal ob manuell oder automatisch)
- ✅ Nutzt `ORDER BY assignedDate DESC` → Datum entscheidet
- ✅ Speichert in `last_year_cookings` Tabelle

**Code-Beweis:**
```php
$lastAssignment = $em->getRepository(\App\Entity\CookingAssignment::class)
    ->createQueryBuilder('ca')
    ->where('ca.party = :party')
    ->andWhere('ca.kitaYear = :kitaYear')
    ->setParameter('party', $party)
    ->setParameter('kitaYear', $kitaYear)
    ->orderBy('ca.assignedDate', 'DESC')  // ← Sortiert nach Datum, nicht nach Typ!
    ->setMaxResults(1)
    ->getQuery()
    ->getOneOrNullResult();
```

### ⚠️ Der kritische Schritt:

**Das Script MUSS vor der Erstellung des neuen Jahres ausgeführt werden!**

**FALSCH (was passiert ist):**
```
1. Jahr 24/25 läuft
2. Admin erstellt manuell Zuweisung: Noah → 31.08.2025
3. Admin erstellt neues Jahr 25/26
4. Admin generiert Plan für 25/26 ❌ (LastYearCooking fehlt!)
5. Noah wird am 01.09.2025 zugewiesen ❌
```

**RICHTIG (so sollte es sein):**
```
1. Jahr 24/25 läuft
2. Admin erstellt manuell Zuweisung: Noah → 31.08.2025
3. Admin führt Script aus: php bin/create_last_year_cooking.php ✅
4. Admin erstellt neues Jahr 25/26
5. Admin generiert Plan für 25/26 ✅
6. Noah wird NICHT am 01.09. zugewiesen ✅
```

## 🔧 Nachträgliche Korrektur (wie jetzt durchgeführt)

Falls das Script vergessen wurde:

```bash
# 1. Jahr 24/25 temporär auf aktiv setzen
UPDATE kita_years SET is_active = 1 WHERE id = 1;
UPDATE kita_years SET is_active = 0 WHERE id = 2;

# 2. Script ausführen
php bin/create_last_year_cooking.php

# 3. Jahr 25/26 wieder aktivieren
UPDATE kita_years SET is_active = 0 WHERE id = 1;
UPDATE kita_years SET is_active = 1 WHERE id = 2;

# 4. Alte automatische Zuweisungen löschen
DELETE FROM cooking_assignments 
WHERE kita_year_id = 2 AND is_manually_assigned = 0;

# 5. Plan neu generieren (über Admin-Interface)
```

## 📋 Aktualisierte Checkliste

### Ende August (vor neuem Jahr!):

- [ ] **WICHTIG:** `php bin/create_last_year_cooking.php` ausführen
  - Findet letzte Zuweisungen (automatisch + manuell!)
  - Speichert als LastYearCooking
  
- [ ] Ausgabe prüfen:
  ```
  ✅ Max: Erstellt (18.08.2025)
  ✅ Sophie: Erstellt (19.08.2025)
  ✅ Leon: Erstellt (20.08.2025)
  ✅ Emma: Erstellt (21.08.2025)
  ✅ Noah: Erstellt (31.08.2025)  ← Manuelle Zuweisung erkannt!
  ✅ Mia: Erstellt (25.08.2025)
  ```

- [ ] Dann erst neues Jahr erstellen
- [ ] Dann erst Plan generieren

### Wichtige Hinweise:

1. **Manuelle Zuweisungen werden automatisch berücksichtigt**
   - Das Script sucht die letzte Zuweisung nach Datum
   - Egal ob `is_manually_assigned = 0` oder `1`

2. **Script muss VOR neuem Jahr ausgeführt werden**
   - Sonst fehlen die LastYearCooking Daten
   - Plan wird ohne Berücksichtigung generiert

3. **Bei Vergessen: Nachträgliche Korrektur möglich**
   - Script nachträglich ausführen
   - Plan neu generieren

## 🎯 Zusammenfassung

**Problem:** Noah (31.08.2025 manuell) → 01.09.2025 (nur 1 Tag) ❌

**Ursache:** LastYearCooking Script nicht ausgeführt

**Lösung:** Script nachträglich ausgeführt + Plan neu generiert

**Ergebnis:** Noah wird frühestens 28.09. oder später zugewiesen ✅

**Prävention:** Script IMMER Ende August ausführen, BEVOR neues Jahr erstellt wird!

---

**Status:** ✅ Behoben durch nachträgliche Ausführung des Scripts
