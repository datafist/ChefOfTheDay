# Kochdienst-Verteilung: Konzept und Algorithmus

## Grundprinzip

Die Kochdienst-Verwaltung verteilt Dienste **gerecht nach Verfügbarkeit**. Es gibt **keine Zielwerte, keine Pflichten, keine Soll-Zahlen**.

Eltern markieren ihre **verfügbaren Tage** - alle anderen Tage sind automatisch nicht verfügbar.

## ❌ NICHT: Zielwerte oder Pflichten

Es gibt **KEINE**:
- ❌ Festen Zielwert ("Familie muss 5 mal kochen")
- ❌ Soll-Zahlen ("Familie sollte 6 Dienste haben")
- ❌ Pflicht-Dienste ("Mindestens X mal kochen")
- ❌ Garantien ("Jede Familie kocht gleich oft")

## ✅ STATTDESSEN: Gerechte Verteilung

### Prinzip:

**"Jede Familie bekommt so viele Dienste wie möglich - gerecht verteilt nach Verfügbarkeit und Gewichtung"**

1. **Verfügbarkeitsbasiert**
   - Familien geben ihre verfügbaren Tage an
   - Nur an verfügbaren Tagen werden Dienste zugewiesen
   - Wer mehr Tage angibt, bekommt tendenziell mehr Dienste

2. **Gewichtete Verteilung**
   - Familien mit 2 Personen: Gewicht = 2
   - Familien mit 1 Person: Gewicht = 1
   - **Bedeutung:** Familien mit 1 Person sollen etwa **halb so viele** Dienste bekommen
   - **ABER:** Keine Garantie! Hängt von Verfügbarkeiten ab

3. **Gleichmäßige Abstände**
   - Mindestabstand zwischen Diensten wird angestrebt
   - Richtet sich nach Anzahl der Familien
   - Bei zu wenig Verfügbarkeiten kann Abstand kürzer sein

## Beispiel: Wie funktioniert die Verteilung?

### Szenario: 45 Familien, 260 verfügbare Werktage

**Gewichtung:**
- 43 Familien mit 2 Personen = 86 Gewichtspunkte
- 2 Familien mit 1 Person = 2 Gewichtspunkte
- **Gesamt: 88 Gewichtspunkte**

**Was passiert:**

Der Algorithmus versucht, die 260 Tage gerecht zu verteilen:
- Alle verfügbaren Tage werden nach und nach zugeteilt
- Familien mit Gewicht 2 werden doppelt so oft bevorzugt
- Familie mit 1 Person wird bevorzugt, wenn sie lange keinen Dienst hatte
- **Resultat:** Je nach Verfügbarkeiten bekommt jede Familie unterschiedlich viele Dienste

**Wichtig: Alle Tage müssen verteilt werden!**

Der Algorithmus verteilt **alle 260 verfügbaren Tage**. Das bedeutet:
- Jede Familie bekommt ihren fairen Anteil
- Kleine Abweichungen (±1-2 Dienste) sind normal
- Familien mit 2 Personen: ca. doppelt so viele Dienste wie Familien mit 1 Person

**Realistische Ergebnisse:**

Bei 43 Familien mit 2 Personen und 2 Familien mit 1 Person:
- Durchschnitt Familie mit 2 Personen: ~6 Dienste
- Durchschnitt Familie mit 1 Person: ~3 Dienste

Mögliche individuelle Ergebnisse:
- Familie A (2 Personen): 7 Dienste (etwas mehr als Durchschnitt)
- Familie B (2 Personen): 5 Dienste ← **1 Dienst weniger = Glück!**
- Familie C (1 Person): 4 Dienste (etwas mehr als Durchschnitt)
- Familie D (1 Person): 3 Dienste (genau Durchschnitt)

### ✅ Kleine Unterschiede sind okay!

- ±1-2 Dienste vom Durchschnitt = normal
- Hängt von Verfügbarkeiten und Algorithmus ab
- Alle 260 Tage sind am Ende verteilt

## Meldungen nach Planerstellung

### ❌ Alte (falsche) Meldung:
```
Familie Johanna: Nur 4 von 5 erforderlichen Kochdiensten zugewiesen
```
**Problem:** Suggeriert Pflicht zu 5 Diensten

### ✅ Aktuell: Keine Warnungen mehr!

Der Algorithmus zeigt **keine Warnungen** mehr, weil:
- Es gibt keine "zu wenig" oder "zu viel"
- Jede Familie bekommt, was bei gerechter Verteilung rauskommt
- Weniger Dienste = Glück gehabt!
- Mehr Dienste = Pech gehabt, aber fair verteilt

### Bei extremen Unterschieden:

Nur wenn technisch etwas schief geht, wird gemeldet:
- "Familie konnte nicht zugeteilt werden (keine Verfügbarkeiten)"
- "Keine Familien vorhanden"

## Was bedeutet das für Admins?

### Nach Plan-Generierung:

✅ **Normalfall:** Einfach akzeptieren
- Unterschiede sind normal und okay
- Familie A hat 4 Dienste, Familie B hat 7? **Normal!**
- Keine Nachbearbeitung nötig

### Nur eingreifen bei:

1. **Extremen Unterschieden:**
   - Eine Familie hat 0 Dienste, andere haben 10+
   - → Verfügbarkeiten der Familie prüfen
   - → Evtl. Familie hat zu wenige Tage als verfügbar markiert
   - → **Neu:** Familie kann auch "nicht verfügbare Tage" markieren für Ausnahmen

2. **Beschwerden von Eltern:**
   - "Warum haben wir so viele Dienste?"
   - → Erklären: Verteilung nach Verfügbarkeit + Gewichtung
   - → Tipp: Mit "nicht verfügbaren Tagen" können Ausnahmen markiert werden
   - → Eventuell Verfügbarkeiten anpassen

3. **Wunsch nach Ausgleich:**
   - Familie will weniger → andere Tage angeben
   - Familie will mehr → kann manuell zugeteilt werden

### Wichtig:

❌ **Nicht versuchen**, alle auf "gleiche Anzahl" zu bringen
✅ **Akzeptieren**, dass Unterschiede zur fairen Verteilung gehören

## Technische Details

### Algorithmus-Schritte:

1. **Berechne verfügbare Tage** (Werktage ohne Ferien/Feiertage)
2. **Berechne Gesamt-Gewicht** (Summe aller Familien-Gewichte)
3. **Berechne Zielwerte** (Verfügbare Tage × Gewicht ÷ Gesamt-Gewicht)
4. **Sortiere Familien** nach Priorität (letzte Dienste, Verfügbarkeiten)
5. **Weise Dienste zu** unter Berücksichtigung von:
   - Verfügbarkeit der Familie
   - Mindestabstand zum letzten Dienst
   - Zielwert noch nicht erreicht
6. **Prüfe Ergebnis** und erzeuge Warnungen bei großen Abweichungen

### Code-Location:

**Datei:** `src/Service/CookingPlanGenerator.php`

**Wichtigste Methoden:**
- `generatePlan()` - Hauptmethode
- `calculateCookingRequirements()` - Berechnet Zielwerte
- `calculateTargetIntervals()` - Berechnet Mindestabstände
- `assignCookingDays()` - Führt Zuweisung durch

## FAQ

### "Familie X hat 5 Dienste, Familie Y hat 7 - ist das fair?"

✅ **Ja, völlig fair!** Wenn beide 2 Personen haben:
- Durchschnitt wäre z.B. 6 Dienste
- Familie X: 5 Dienste (1 weniger) ← kleines Glück
- Familie Y: 7 Dienste (1 mehr) ← kleines Pech
- Abweichung von ±1-2 Diensten ist normal

Wenn Familie X nur 1 Person hat:
- X sollte etwa halb so viele Dienste haben wie Y
- X: 3 Dienste, Y: 6 Dienste = gerecht

### "Muss ich als Admin nachbessern?"

**Nein!** Unterschiede sind normal und gewollt.

Nur eingreifen wenn:
- Eltern sich beschweren
- Eine Familie hat 0 Dienste (technischer Fehler)
- Extreme Unterschiede (0 vs. 15 Dienste)

### "Familie beschwert sich, sie hat zu viele Dienste?"

**Antwort:**
- "Sie haben viele Verfügbarkeiten angegeben → viele Dienste"
- "Wenn Sie weniger Dienste wollen: Weniger Tage als verfügbar markieren"
- **Wichtig:** Es gibt kein "zu viele" - nur das Ergebnis der fairen Verteilung

### "Familie will mehr Dienste übernehmen?"

**Lösung:**
- Mehr Verfügbarkeiten angeben beim nächsten Plan
- Oder: Admin weist manuell zusätzliche Dienste zu

### "Was wenn alle zu wenig Verfügbarkeiten angeben?"

**Problem:** Nicht alle Tage können besetzt werden

**Lösung:**
- Mit Familien sprechen: "Bitte mehr Tage angeben"
- Erklären: "Sonst gibt es Lücken im Kochplan"

## Zusammenfassung

### ✅ Was das System MACHT:

- **Gerecht verteilen** nach Verfügbarkeit und Gewichtung
- **Gewichten:** 1 Person ≈ halb so viele Dienste wie 2 Personen
- **Flexibel:** Jede Familie bekommt, was bei fairer Verteilung rauskommt
- **Keine Pflichten:** Weniger Dienste = Glück gehabt!
- **Anpassbar:** Manuelle Korrekturen möglich

### ❌ Was das System NICHT macht:

- Keine Zielwerte oder Soll-Zahlen
- Keine Pflicht zu X Diensten
- Keine Garantie für gleiche Anzahl
- Keine Warnungen bei "zu wenig" Diensten
- Keine "erforderliche Mindestanzahl"

### 💡 Philosophie:

**"Das System verteilt gerecht - das Ergebnis ist, was es ist. Unterschiede sind okay und gewollt!"**
