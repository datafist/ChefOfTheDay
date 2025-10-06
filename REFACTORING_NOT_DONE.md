# Bewusst NICHT refactorierte Code-Bereiche

## 1. buildCalendar() Methoden

### Vorkommen:
- `DashboardController::buildCalendarView()`
- `ParentController::buildCalendar()`

### Warum NICHT refactoriert?

**Unterschiedliche Zwecke:**

#### Admin-Kalender (`buildCalendarView`)
- **Zweck:** Management-Ansicht für Admins
- **Features:**
  - Zeigt bestehende Assignments pro Tag
  - Ermöglicht Drag & Drop von Zuweisungen
  - Wochenstruktur (Montag-Sonntag)
  - Integration mit Assignment-Entities
  - Kompakte Darstellung

#### Eltern-Kalender (`buildCalendar`)
- **Zweck:** Verfügbarkeits-Eingabe für Eltern
- **Features:**
  - Zeigt verfügbare/ausgeschlossene Tage
  - Checkbox-Auswahl für jeden Tag
  - Anzeige der Ausschluss-Gründe (Feiertag-Name, Ferien-Name)
  - Tag-für-Tag Struktur mit Exclusion-Reasons
  - Detaillierte Darstellung

### Empfehlung:
**NICHT abstraktion** - Die beiden Kalender haben fundamental unterschiedliche Anforderungen. Eine gemeinsame Abstraktion würde:
1. Mehr Komplexität schaffen als sie löst
2. Beide Kalender weniger flexibel machen
3. Die Wartbarkeit verschlechtern statt verbessern

---

## 2. CSRF-Validierung

### Pattern:
```php
if (!$this->isCsrfTokenValid('delete'.$entity->getId(), $request->request->get('_token'))) {
    $this->addFlash('error', 'Ungültiger Token');
    return $this->redirectToRoute(...);
}
```

### Vorkommen:
- `PartyController::delete()`
- `VacationController::delete()`
- `KitaYearController::delete()`
- `DashboardController::editAssignment()`
- `DashboardController::createAssignment()`
- `DashboardController::deleteAssignment()`

### Warum NICHT refactoriert?

**Gründe:**
1. **Geringe Duplikation:** Pattern ist sehr kurz (3-4 Zeilen)
2. **Symfony-Standard:** Wird in Symfony-Apps üblicherweise so gemacht
3. **Flexible Token-IDs:** Jeder Controller verwendet unterschiedliche Token-IDs
4. **Geringe Komplexität:** Code ist selbsterklärend
5. **Overhead nicht gerechtfertigt:** Ein Trait würde keinen signifikanten Vorteil bringen

### Mögliche Zukunfts-Lösung:
Falls CSRF-Validierung in Zukunft komplexer wird (z.B. zusätzliche Checks), könnte ein `CsrfValidationTrait` sinnvoll werden. Aktuell: **Nicht nötig**.

---

## 3. Flash-Messages

### Pattern:
```php
$this->addFlash('success', 'Operation erfolgreich');
$this->addFlash('error', 'Ein Fehler ist aufgetreten');
```

### Warum NICHT refactoriert?

**Gründe:**
1. **Symfony Built-in:** `addFlash()` ist Symfony-Standard
2. **Sehr einfach:** Kein Mehrwert durch Abstraktion
3. **Flexibel:** Jede Message ist anders formuliert
4. **I18n-Ready:** Könnte in Zukunft mit Übersetzungen arbeiten

### Empfehlung:
**Belassen wie es ist** - Standard Symfony-Pattern.

---

## 4. Entity Count Pattern

### Pattern:
```php
$count = $entityManager->getRepository(SomeEntity::class)
    ->count(['field' => $value]);
```

### Vorkommen:
- Mehrfach in verschiedenen Controllern
- Für unterschiedliche Entities (CookingAssignment, Availability, etc.)

### Warum NICHT refactoriert?

**Gründe:**
1. **Doctrine-Standard:** Eingebaute Doctrine-Methode
2. **Sehr einfach:** Ein Methodenaufruf
3. **Flexibel:** Unterschiedliche Kriterien pro Verwendung
4. **Performance:** Direkte Repository-Calls sind optimal

### Empfehlung:
**Belassen wie es ist** - Standard Doctrine-Pattern.

---

## 5. findOneBy(['isActive' => true]) Pattern

### Status:
✅ **TEILWEISE refactoriert**

**Was wurde gemacht:**
- Neue Methode `KitaYearRepository::findActiveYear()` erstellt
- Kann optional in Controllern verwendet werden

**Warum nicht überall ersetzt?**
- **Nicht kritisch:** Beide Varianten sind akzeptabel
- **Aufwand vs. Nutzen:** Viele kleine Änderungen für minimalen Gewinn
- **Optional:** Controller können graduell umgestellt werden

**Empfehlung:**
- Bei neuen Features: `findActiveYear()` verwenden
- Bestehender Code: Optional umstellen, aber keine Priorität

---

## Fazit

**Prinzip:** "Refactor nur, wenn es wirklich Mehrwert bringt"

- ✅ Große Code-Duplikate (40+ Zeilen): Refactoriert
- ✅ Wiederholte komplexe Logik: Refactoriert
- ❌ Kurze Standard-Patterns: Belassen
- ❌ Fundamental unterschiedliche Logik: Nicht abstrahiert
- ⚠️ Grenzfälle: Dokumentiert für zukünftige Entscheidungen

**Ergebnis:** Sauberer Code ohne Over-Engineering!
