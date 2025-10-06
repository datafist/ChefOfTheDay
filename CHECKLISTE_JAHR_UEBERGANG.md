# ✅ Checkliste: Jahr-Übergang 24/25 → 25/26

## Problem
Eine Familie mit Zuweisung am **31.08.2025** (letzter Tag) würde ohne Vorbereitung am **01.09.2025** (erster Tag des neuen Jahres) wieder zugewiesen werden.

## Lösung
Zweistufiger Prozess mit automatischem Script.

---

## 📅 Timeline

### Ende August 2025 (z.B. 25.08.2025 - 31.08.2025)

#### ☑️ Schritt 1: LastYearCooking Einträge erstellen

**Kommando:**
```bash
cd /home/florian/Projekte/Privat/ChefOfTheDay_symfony_mysql
php bin/create_last_year_cooking.php
```

**Was passiert:**
- Script liest alle CookingAssignments aus Jahr 24/25
- Findet für jede Familie die **letzte Zuweisung**
- Speichert diese in `last_year_cookings` Tabelle

**Erwartete Ausgabe:**
```
╔══════════════════════════════════════════════════════════════╗
║  LastYearCooking Generator                                   ║
║  Bereitet Daten für Jahr-Übergang vor                        ║
╚══════════════════════════════════════════════════════════════╝

📅 Aktives Kita-Jahr: 01.09.2024 - 31.08.2025

👨‍👩‍👧‍👦 Gefundene Familien: 6
──────────────────────────────────────────────────────────────

✅ Max: Erstellt (28.08.2025)
✅ Sophie: Erstellt (25.08.2025)
✅ Leon: Erstellt (22.08.2025)
✅ Emma: Erstellt (20.08.2025)
✅ Noah: Erstellt (18.08.2025)
✅ Mia: Erstellt (15.08.2025)

──────────────────────────────────────────────────────────────
📊 Zusammenfassung:
   • Neu erstellt:      6
   • Aktualisiert:      0
   • Bereits vorhanden: 0
   • Keine Zuweisung:   0
──────────────────────────────────────────────────────────────

✅ Erfolgreich! Die LastYearCooking Einträge wurden gespeichert.

📌 Nächste Schritte:
   1. Neues Kita-Jahr erstellen (Admin-Interface)
   2. Neuen Kochplan generieren
   3. Die letzten Zuweisungen aus diesem Jahr werden automatisch
      berücksichtigt, um zu kurze Abstände zu vermeiden.
```

**Prüfung (optional):**
```bash
php bin/console doctrine:query:sql "SELECT p.child_name, lyc.last_cooking_date FROM last_year_cookings lyc JOIN parties p ON lyc.party_id = p.id ORDER BY lyc.last_cooking_date DESC"
```

---

### September 2025 (ab 01.09.2025)

#### ☑️ Schritt 2: Neues Kita-Jahr erstellen

**Im Browser:**
1. Login: http://127.0.0.1:8000/admin
2. Navigation: "Kita-Jahre" → "Neues Kita-Jahr erstellen"
3. Eingaben:
   - **Start-Datum:** 01.09.2025
   - **End-Datum:** 31.08.2026
4. **Speichern**

**Wichtig:**
- Das alte Jahr 24/25 wird automatisch auf "inaktiv" gesetzt
- Das neue Jahr 25/26 wird als "aktiv" markiert

#### ☑️ Schritt 3: Verfügbarkeiten eintragen

**Option A: Manuell (Eltern)**
- Eltern loggen sich ein
- Tragen Verfügbarkeiten für 25/26 ein

**Option B: Automatisch (Admin-Script)**
```bash
# Falls alle Familien erstmal an allen Tagen verfügbar sein sollen:
php create_availabilities.php
```

#### ☑️ Schritt 4: Neuen Plan generieren

**Im Browser:**
1. Admin-Dashboard: http://127.0.0.1:8000/admin
2. Button: **"Plan generieren"**
3. Warten auf Erfolgsmeldung

**Der Algorithmus nutzt jetzt LastYearCooking:**
```
Familie Max (letzte Zuweisung 24/25: 28.08.2025):
  ❌ 01.09.2025 - Blockiert (nur 4 Tage Abstand)
  ❌ 05.09.2025 - Blockiert (nur 8 Tage Abstand)
  ❌ 15.09.2025 - Blockiert (nur 18 Tage Abstand)
  ⚠️ 25.09.2025 - Möglich im Notfall (28 Tage = 4 Wochen)
  ✅ 09.10.2025 - Bevorzugt (42 Tage = 6 Wochen)
```

#### ☑️ Schritt 5: Prüfung

**Kalender-Ansicht:**
- "Kalender-Ansicht" öffnen
- Durch September 2025 scrollen
- Prüfen: Familien mit später Zuweisung in 24/25 sollten NICHT Anfang September erscheinen

**SQL-Prüfung (detailliert):**
```bash
php bin/console doctrine:query:sql "
SELECT 
    p.child_name,
    lyc.last_cooking_date as last_in_2024,
    MIN(ca.assigned_date) as first_in_2025,
    DATEDIFF(MIN(ca.assigned_date), lyc.last_cooking_date) as days_between
FROM last_year_cookings lyc
JOIN parties p ON lyc.party_id = p.id
LEFT JOIN cooking_assignments ca ON ca.party_id = p.id
WHERE YEAR(ca.assigned_date) = 2025
GROUP BY p.child_name, lyc.last_cooking_date
ORDER BY days_between"
```

**Erwartete Ausgabe:**
```
 child_name   last_in_2024   first_in_2025   days_between  
 Max          2025-08-28     2025-09-25      28            (Mindestens 28!)
 Sophie       2025-08-25     2025-09-30      36
 Leon         2025-08-22     2025-10-05      44
 ...
```

**✅ Erfolgskriterium:** `days_between` für ALLE Familien **≥ 28 Tage**

---

## 🔄 Wiederholung jedes Jahr

### Jahr-Übergang 25/26 → 26/27 (Ende August 2026):

1. `php bin/create_last_year_cooking.php` ausführen
2. Neues Jahr 26/27 erstellen (01.09.2026 - 31.08.2027)
3. Verfügbarkeiten eintragen
4. Plan generieren
5. Prüfen

**Das Script überschreibt/aktualisiert die LastYearCooking Einträge automatisch!**

---

## ⚠️ Wichtige Hinweise

### ❌ Fehler vermeiden:

**FALSCH:**
```
1. Neues Jahr 25/26 erstellen
2. Plan generieren
3. LastYearCooking erstellen ❌ Zu spät!
```

**RICHTIG:**
```
1. LastYearCooking erstellen ✅
2. Neues Jahr 25/26 erstellen
3. Plan generieren ✅
```

### 🔍 Was wenn ich es vergesse?

Falls Sie das Script **nach** der Plan-Generierung ausführen:

**Lösung:**
1. Script ausführen (erstellt LastYearCooking nachträglich)
2. Plan neu generieren:
   - Admin-Dashboard
   - "Plan löschen" (oder alte Zuweisungen manuell löschen)
   - "Plan generieren" (nutzt jetzt LastYearCooking)

### 💾 Backup

**Empfehlung vor Jahr-Übergang:**
```bash
# Datenbank-Backup erstellen
mysqldump -u kochdienst -p kochdienst > backup_2025_08_31.sql

# Oder mit Docker:
docker exec chefoftheday_mysql mysqldump -u kochdienst -pkochdienst kochdienst > backup_2025_08_31.sql
```

---

## 📱 Erinnerung einrichten

**Google Calendar / Outlook:**
- Titel: "Kita Kochplan: LastYearCooking erstellen"
- Datum: 25.08.2025 (ca. 1 Woche vor Jahresende)
- Wiederholung: Jährlich
- Notiz: `cd ~/Projekte/.../ChefOfTheDay && php bin/create_last_year_cooking.php`

---

## ✅ Checkliste Kurzfassung

Ende August (z.B. 25.08.):
- [ ] `php bin/create_last_year_cooking.php` ausführen
- [ ] Ausgabe prüfen: Alle Familien haben Einträge

Anfang September (ab 01.09.):
- [ ] Neues Kita-Jahr erstellen (Admin-Interface)
- [ ] Verfügbarkeiten eintragen (Eltern oder Script)
- [ ] Plan generieren (Admin-Interface)
- [ ] Abstände prüfen (Kalender oder SQL)

Ergebnis:
- [ ] Keine Familie mit < 28 Tage Abstand zum Vorjahr
- [ ] Möglichst viele Familien mit ~42 Tage Abstand

---

## 🎯 Zusammenfassung

**Problem:** Familie am 31.08.2025 → 01.09.2025 (1 Tag Abstand) ❌

**Lösung:** 
1. Script erstellt LastYearCooking aus 24/25
2. Algorithmus prüft Abstand beim Generieren von 25/26
3. Familie am 31.08.2025 → frühestens 28.09.2025 (28 Tage) ✅

**Status:** ✅ Vollständig implementiert und dokumentiert!
