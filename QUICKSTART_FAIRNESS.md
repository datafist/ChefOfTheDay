# Zusammenfassung: Jahresübergreifende Fairness

## ✅ Was wurde implementiert

Die **jahresübergreifende Fairness** verhindert, dass immer die gleichen Familien jährlich die "Mehr-Last" tragen müssen.

## 🎯 Problem → Lösung

### Vorher
```
Jahr 2024/2025:
  Familie Müller:  5 Dienste
  Familie Schmidt: 4 Dienste

Jahr 2025/2026:
  Familie Müller:  5 Dienste  ← Wieder 5! Unfair!
  Familie Schmidt: 4 Dienste  ← Wieder 4!
```

### Nachher
```
Jahr 2024/2025:
  Familie Müller:  5 Dienste
  Familie Schmidt: 4 Dienste

Jahr 2025/2026:
  Familie Müller:  4 Dienste  ← Entlastet! ✅
  Familie Schmidt: 5 Dienste  ← Jetzt mehr ✅
```

## 🔧 Wie funktioniert es?

### Priorisierungs-Formel

```php
$totalLoad = $lastYearCount + $currentYearCount
```

**⚠️ WICHTIG: Neue Familien**

Neue Familien (ohne Vorjahr) erhalten einen **dynamisch berechneten Startwert**:
- **Startwert = Erwartungswert des aktuellen Jahres**
- Passt sich automatisch an:
  - Anzahl der Familien (mehr Familien → niedrigerer Wert)
  - Verfügbare Tage (mehr Tage → höherer Wert)
  - Status (Alleinerziehend: ~3, Paare: ~4-5)
- **Beispiele:**
  - 40 Familien → Paare: 5, Singles: 3
  - 50 Familien → Paare: 4, Singles: 2-3

→ **Verhindert Überlastung** neuer Familien (z.B. 8 Dienste)
→ **Verhindert Bevorzugung** neuer Familien in späteren Jahren
→ **Garantiert faire Startbasis** in jedem Jahr

**Beispiel bei Tag 1 der Zuweisung:**
```
Familie A (Vorjahr: 5, Aktuell: 0)          → Total: 5
Familie B (Vorjahr: 4, Aktuell: 0)          → Total: 4
Familie C (NEU, virtuell: 4, Aktuell: 0)    → Total: 4 (wie B)

→ Familie B oder C bekommt den Dienst (niedrigere Gesamtlast als A)
→ Neue Familie wird fair behandelt, nicht überpriorisiert!
```

**Beispiel bei Tag 50 der Zuweisung:**
```
Familie A (Vorjahr: 5, Aktuell: 2)          → Total: 7
Familie B (Vorjahr: 4, Aktuell: 3)          → Total: 7
Familie C (NEU, virtuell: 4, Aktuell: 3)    → Total: 7

→ Gleiche Gesamtlast, nächstes Kriterium entscheidet
```

## 📊 Erwartete Resultate

### Bei 44 Familien mit ~220 Tagen

**Ohne jahresübergreifende Fairness:**
```
Jahr 1: 22 Familien × 5 Dienste = 110 Dienste (Gruppe "Viel")
        22 Familien × 5 Dienste = 110 Dienste
        
Jahr 2: Gleiche 22 Familien × 5 Dienste = 110 Dienste (Gruppe "Viel")
        Gleiche 22 Familien × 5 Dienste = 110 Dienste

→ Immer die gleichen Familien haben mehr Arbeit!
```

**Mit jahresübergreifender Fairness:**
```
Jahr 1: 22 Familien × 5 Dienste = 110 Dienste (Gruppe A)
        22 Familien × 5 Dienste = 110 Dienste (Gruppe B)
        
Jahr 2: 11 von Gruppe A × 4 Dienste = 44 Dienste (Entlastung!)
        11 von Gruppe A × 5 Dienste = 55 Dienste
        11 von Gruppe B × 5 Dienste = 55 Dienste
        11 von Gruppe B × 6 Dienste = 66 Dienste (Ausgleich!)

→ Rotation! Verschiedene Familien haben mehr Arbeit!
```

## 🧪 Testen

### Option 1: Analyse-Skript ausführen

```bash
php bin/analyze_fairness.php
```

**Zeigt:**
- Top 20 Familien mit größten Veränderungen
- Statistiken (Durchschnitt, Min, Max)
- Rotation-Analyse (Wer wurde entlastet? Wer bekam mehr?)
- Fairness-Index

### Option 2: Manuell im Admin-Dashboard

1. **Aktuellen Plan ansehen:**
   - Admin → Dashboard → Kalender
   - Zähle Dienste pro Familie

2. **Mit Vorjahr vergleichen:**
   - Schau in die Datenbank (`last_year_cooking` Tabelle)
   - Vergleiche die Anzahlen

3. **Erwartung:**
   - Familien mit 5 Diensten im Vorjahr sollten jetzt ~4 haben
   - Familien mit 4 Diensten im Vorjahr sollten jetzt ~5 haben

## 📁 Geänderte Dateien

1. **src/Service/CookingPlanGenerator.php**
   - Neue Sortier-Logik mit `totalLoad = lastYearCount + currentYearCount`
   - Priorität 2: Jahresübergreifende Fairness

2. **FEATURE_MULTIYEAR_FAIRNESS.md** ⭐ NEU
   - Vollständige Dokumentation
   - Mathematische Beispiele
   - Szenarien und Tests

3. **bin/analyze_fairness.php** ⭐ NEU
   - Analyse-Skript für Rotation
   - Statistiken und Visualisierung

4. **QUICKSTART_FAIRNESS.md** ⭐ NEU (diese Datei)
   - Schneller Überblick
   - Praktische Beispiele

## ⚙️ Konfiguration

**Keine Konfiguration nötig!**

Die jahresübergreifende Fairness:
- ✅ Funktioniert automatisch
- ✅ Nutzt vorhandene `LastYearCooking` Daten
- ✅ Ist ab dem zweiten Jahr aktiv
- ✅ Keine Parameter zum Anpassen

## 🎯 Vorteile

| Vorteil | Beschreibung |
|---------|--------------|
| **Langfristige Gerechtigkeit** | Keine Familie trägt dauerhaft mehr Last |
| **Automatische Rotation** | System verteilt "Mehr-Arbeit" fair über Jahre |
| **Motivierend** | "Nächstes Jahr weniger" ist ein Trost |
| **Transparent** | Nachvollziehbare Logik |
| **Neue Familien** | Werden bevorzugt (da keine Vorjahr-Last) |
| **Keine manuelle Arbeit** | Admin muss nichts konfigurieren |

## 📈 Langzeit-Effekt (5 Jahre)

### Ohne Feature
```
Familie "Pech":  5 + 5 + 5 + 5 + 5 = 25 Dienste
Familie "Glück": 4 + 4 + 4 + 4 + 4 = 20 Dienste
Differenz: 5 Dienste = 25% mehr Arbeit für Familie "Pech"!
```

### Mit Feature
```
Familie "Pech":  5 + 4 + 5 + 4 + 5 = 23 Dienste
Familie "Glück": 4 + 5 + 4 + 5 + 4 = 22 Dienste
Differenz: 1 Dienst = 4,5% Unterschied ✅
```

**Fairness-Verbesserung: ~80%**

## ❓ FAQ

### Funktioniert es im ersten Jahr?
Ja, aber ohne Effekt. Alle Familien haben `lastYearCount = 0`, also greift die Rotation erst ab dem zweiten Jahr.

### Was ist mit neuen Familien?
Neue Familien haben `lastYearCount = 0` und werden daher **bevorzugt behandelt** (niedrigste Gesamtlast).

### Kann eine Familie immer 5 Dienste haben?
Ja, wenn ihre Verfügbarkeiten es nicht anders zulassen. Die Rotation funktioniert nur, wenn genug Flexibilität bei den Verfügbarkeiten besteht.

### Wird die Fairness über mehr als 2 Jahre berücksichtigt?
Aktuell nur über 2 Jahre (Vorjahr + aktuelles Jahr). Für längere Zeiträume könnte man einen gleitenden Durchschnitt implementieren.

## 🚀 Nächste Schritte

1. **Plan für 2025/2026 generieren** (falls noch nicht geschehen)
2. **Analyse ausführen:** `php bin/analyze_fairness.php`
3. **Rotation beobachten:** Vergleiche mit Vorjahr
4. **Im Jahr 2026/2027:** Erneut generieren und Rotation bestätigen

## 📅 Datum
5. Oktober 2025
