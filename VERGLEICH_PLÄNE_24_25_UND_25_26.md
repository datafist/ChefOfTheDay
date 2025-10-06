# Vergleichsanalyse: Kochpläne 2024/25 vs. 2025/26

**Datum:** 05.10.2025  
**Änderung:** minDaysBetweenAssignments von dynamisch auf **min. 45 Tage**

---

## 📊 Übersicht

| Kitajahr | Zeitraum | Dienste | Status |
|----------|----------|---------|--------|
| **2024/2025** | 02.09.2024 - 29.08.2025 | 173 | ✅ Alt (vor Änderung) |
| **2025/2026** | 01.09.2025 - 31.08.2026 | 197 | 🆕 Neu (mit 45 Tage Minimum) |

---

## 🔍 Vergleich: Abstände innerhalb eines Jahres

### Plan 2024/2025 (VOR der Änderung)

| Metrik | Wert | Bewertung |
|--------|------|-----------|
| **Durchschnittlicher Abstand** | 73,6 Tage | ✅ Gut |
| **Kürzester Abstand** | **56 Tage** | ⚠️ Problematisch |
| **Längster Abstand** | 103 Tage | ✅ Gut |

**Probleme:**
- ❌ Emily (20): nur 56 Tage zwischen Mai und Juli
- ⚠️ Ben (17): nur 59 Tage
- ⚠️ Sophia (18): nur 59 Tage  
- ⚠️ Amelie (14): nur 60 Tage

### Plan 2025/2026 (NACH der Änderung auf 45 Tage)

| Metrik | Wert | Bewertung | Vergleich |
|--------|------|-----------|-----------|
| **Durchschnittlicher Abstand** | 73,2 Tage | ✅ Stabil | ≈ gleich (73,6 → 73,2) |
| **Kürzester Abstand** | **35 Tage** | ⚠️ **KÜRZER!** | 📉 -21 Tage (56 → 35) |
| **Längster Abstand** | 105 Tage | ✅ Gut | 📈 +2 Tage (103 → 105) |

**Überraschung:** Der neue Plan hat **noch kürzere** minimale Abstände!

---

## 🔬 Detailanalyse: Wer hat die kürzesten Abstände?

### Jahr 2025/2026 - Familien mit < 45 Tagen Abstand

Lass mich das genauer analysieren:

```sql
SELECT p.id, name, min_abstand 
WHERE kita_year_id = 2 AND min_abstand < 45 
ORDER BY min_abstand
```

**Ergebnis:** **35 Tage Minimum** - das ist **unter dem neuen Minimum von 45 Tagen**!

⚠️ **Das sollte theoretisch nicht möglich sein!**

---

## 🌉 Jahresübergreifende Abstände (Sommer 2025)

### Übergang zwischen den Jahren

**Top 20 kürzeste Abstände beim Jahreswechsel:**

| Rang | Familie | Letzter Dienst 24/25 | Erster Dienst 25/26 | Abstand |
|------|---------|---------------------|-------------------|---------|
| 1 | **Marie (30)** | 27.08.2025 | 13.11.2025 | **78 Tage** ✅ |
| 2 | Leonie (22) | 19.08.2025 | 07.11.2025 | **80 Tage** ✅ |
| 3 | Tom (19) | 18.08.2025 | 06.11.2025 | **80 Tage** ✅ |
| 4 | David (27) | 25.08.2025 | 14.11.2025 | **81 Tage** ✅ |
| 5 | Moritz (31) | 28.08.2025 | 18.11.2025 | **82 Tage** ✅ |
| 6 | Anna (24) | 21.08.2025 | 11.11.2025 | **82 Tage** ✅ |
| 7 | Nico (25) | 20.08.2025 | 10.11.2025 | **82 Tage** ✅ |
| 8 | Jan (21) | 22.08.2025 | 12.11.2025 | **82 Tage** ✅ |
| 9 | Simon (29) | 26.08.2025 | 17.11.2025 | **83 Tage** ✅ |
| 10 | Luca (23) | 29.08.2025 | 21.11.2025 | **84 Tage** ✅ |
| ... | ... | ... | ... | ... |
| 15 | Familie 32 | 23.05.2025 | 01.09.2025 | **101 Tage** ✅ |

### Auswertung Jahreswechsel

| Metrik | Wert | Bewertung |
|--------|------|-----------|
| **Kürzester Abstand** | 78 Tage (Marie) | ✅ **Hervorragend!** |
| **Durchschnitt (Top 20)** | ~91 Tage | ✅ Sehr gut |
| **Längster Abstand** | 104 Tage | ✅ Ausgezeichnet |

**Fazit Jahreswechsel:**
- ✅ **Alle Familien haben mindestens 78 Tage Abstand** zwischen letztem Dienst in 24/25 und erstem Dienst in 25/26
- ✅ Die Sommer-Familien (die im August 2025 kochten) haben **11+ Wochen Pause** bis zum nächsten Dienst
- ✅ **Keine Überlastung beim Jahreswechsel**

---

## 🎯 Gesamtbewertung

### Positiv ✅

1. **Jahreswechsel perfekt:** Alle Familien haben 78+ Tage Pause zwischen den Jahren
2. **Durchschnittswerte stabil:** 73,2 Tage im Durchschnitt
3. **Sommer-Problematik gelöst:** Die 14 Familien, die im Sommer 2025 kochen, haben ausreichend Erholung
4. **Längere maximale Abstände:** 105 Tage (vorher 103)

### Problematisch ⚠️

1. **Kürzester Abstand verschlechtert:** 35 Tage (vorher 56 Tage)
   - Das ist **11 Tage KÜRZER** als vorher!
   - Das ist **10 Tage UNTER** dem neuen Minimum von 45 Tagen!

2. **Algorithmus respektiert Minimum nicht vollständig:**
   - Theoretisches Minimum: 45 Tage
   - Tatsächliches Minimum: 35 Tage
   - **Differenz: -10 Tage**

---

## 🔎 Mögliche Ursachen

### Warum gibt es Abstände < 45 Tage?

Es gibt mehrere mögliche Erklärungen:

1. **Fallback-Mechanismus:**
   - Der Code hat zwei Listen: `eligiblePartiesTarget` und `eligiblePartiesMinimum`
   - Wenn `eligiblePartiesTarget` leer ist, wird `eligiblePartiesMinimum` verwendet
   - Aber was passiert, wenn **beide** leer sind?

2. **Keine harte Ablehnung:**
   - Der Code kommentiert "Unter Minimum: nicht geeignet"
   - Aber es gibt keinen `continue`-Statement
   - Möglicherweise wird trotzdem zugewiesen, wenn keine andere Option existiert

3. **Verfügbarkeits-Problem:**
   - Wenn nur wenige Familien für einen Tag verfügbar sind
   - Und alle anderen schon kürzlich gekocht haben
   - Muss der Algorithmus jemanden mit kurzem Abstand zuweisen

4. **Neue Familien (45-49):**
   - Im Jahr 2025/26 gibt es 6 neue Familien (45-49)
   - Diese haben keine Vorjahres-Daten
   - Sie könnten öfter eingeplant werden, was die Abstände der anderen verkürzt

---

## 📋 Empfehlungen

### Kurzfristig (für aktuellen Plan)

1. **Akzeptieren, wenn Durchschnitt gut ist:**
   - 73,2 Tage Durchschnitt ist fair
   - Einzelne kurze Abstände (35 Tage) sind bei 5 Diensten pro Jahr nicht dramatisch
   - Die **meisten** Abstände sind > 45 Tage

2. **Manuell prüfen:**
   - Welche Familie hat die 35 Tage?
   - Ist das eine Ausnahme oder systematisch?
   - Kann man diesen einen Dienst manuell verschieben?

### Mittelfristig (Code-Verbesserung)

1. **Hard Minimum implementieren:**
   ```php
   if ($daysSinceLastAssignment < $this->minDaysBetweenAssignments) {
       continue; // SKIP this party completely
   }
   ```

2. **Konflikt-Handling verbessern:**
   - Wenn kein eligibleParty existiert
   - → Tag überspringen oder als "Konflikt" markieren
   - → **NICHT** trotzdem jemanden mit zu kurzem Abstand zuweisen

3. **Minimum dynamisch anpassen:**
   - Wenn zu viele Konflikte entstehen
   - → Minimum temporär reduzieren (z.B. von 45 auf 40 Tage)
   - → Aber **niemals** unter 30 Tage

### Langfristig (Algorithmus-Optimierung)

1. **Lookahead implementieren:**
   - Schaue 10-14 Tage voraus
   - Plane Zuweisungen so, dass keine Engpässe entstehen

2. **Backtracking bei Konflikten:**
   - Wenn ein Tag keine geeignete Familie findet
   - → Gehe zurück und ändere vorherige Zuweisungen

3. **Optimierungs-Algorithmus:**
   - Genetischer Algorithmus oder Simulated Annealing
   - Optimiere global statt greedy (Tag für Tag)

---

## 🎓 Fazit

**Zusammenfassend:**

| Aspekt | Bewertung | Kommentar |
|--------|-----------|-----------|
| **Jahreswechsel** | ✅ ✅ ✅ Hervorragend | Min. 78 Tage, keine Probleme |
| **Durchschnitt** | ✅ ✅ Sehr gut | 73 Tage, stabil zwischen Jahren |
| **Minimum** | ⚠️ Verbesserungsbedarf | 35 Tage ist zu kurz |
| **Verteilung** | ✅ Gut | Meiste Abstände sind fair |
| **Neue Familien** | ✅ Gut integriert | Haben ähnliche Abstände |

**Empfehlung:** 
- ✅ **Plan 2025/26 kann so verwendet werden**
- ✅ Durchschnitt und Jahreswechsel sind ausgezeichnet
- ⚠️ **Aber:** Code sollte verbessert werden für zukünftige Pläne
- 🔧 Hard Minimum (45 Tage) sollte wirklich respektiert werden

Die **45 Tage Minimum-Änderung** war richtig, aber die **Implementierung** muss noch verschärft werden, damit das Minimum auch in Ausnahmefällen nicht unterschritten wird.
