# UI-Anpassungen für Party-Entity Migration

## Datum: 2025-10-05

## Durchgeführte Änderungen

### 1. Controller ✅

**DashboardController.php**:
- 4 Stellen angepasst: `getChildName()` → `getChildrenNames()`
- Betrifft: Erfolgsmeldungen für Zuweisung bearbeiten/erstellen/löschen

### 2. Templates ✅

**Automatische Ersetzung**:
```bash
find templates -name "*.twig" -type f ! -name "new.html.twig" ! -name "edit.html.twig" \
  -exec sed -i 's/\.childName/.childrenNames/g' {} \;
```
- Alle `.childName` durch `.childrenNames` ersetzt (außer in edit/new Forms)

**Manuelle Anpassungen**:

#### `templates/admin/party/index.html.twig`
- **Kinder-Spalte**: Zeigt alle Kindernamen + Hinweis "(X Kinder)" bei mehreren
- **Geburtsjahr-Spalte**: Zeigt alle Geburtsjahre (komma-getrennt)
- **"Neue Familie"-Button**: Deaktiviert (Form noch nicht angepasst)
- **"Bearbeiten"-Button**: Deaktiviert (Form noch nicht angepasst)
- **Hinweis**: "ℹ️ Neue Familien werden über Test-Fixtures angelegt"

#### `templates/admin/party/show.html.twig`
- **Kinder-Anzeige**: Liste aller Kinder mit Namen und Geburtsjahr
  ```
  Kinder:  Max (geboren 2020)
           Sophie (geboren 2021)
  ```

### 3. Forms ⚠️ DEAKTIVIERT

**Nicht angepasst** (TODO für später):
- `templates/admin/party/new.html.twig` - Button ausgeblendet
- `templates/admin/party/edit.html.twig` - Button ausgeblendet
- `src/Form/PartyType.php` - Muss auf CollectionType umgebaut werden

**Grund**: Forms erfordern komplexe Änderung (CollectionType für Kinder-Array)

## Getestete Bereiche

✅ **Funktioniert**:
- Admin: Familien-Liste (`/admin/parties`)
- Admin: Familie anzeigen (`/admin/parties/{id}`)
- Admin: Dashboard mit Zuweisungen
- Admin: Kalender
- Fehlerbehandlung (Flash-Messages)

⚠️ **Nicht getestet** (sollte aber funktionieren):
- Eltern-Login (Passwort-Generierung mit ältestem Kind)
- PDF-Export (verwendet childrenNames)
- Kochplan-Generierung

❌ **Nicht funktionsfähig** (Forms deaktiviert):
- Admin: Neue Familie anlegen
- Admin: Familie bearbeiten

## Verbleibende Aufgaben

### Für Produktion (später):

1. **PartyType Form umbauen**:
   ```php
   ->add('children', CollectionType::class, [
       'entry_type' => ChildType::class,
       'allow_add' => true,
       'allow_delete' => true,
       'by_reference' => false,
   ])
   ```

2. **ChildType Form erstellen**:
   ```php
   class ChildType extends AbstractType {
       ->add('name', TextType::class)
       ->add('birthYear', IntegerType::class)
   }
   ```

3. **JavaScript** für dynamisches Hinzufügen/Entfernen von Kindern

4. **Scripts anpassen** (optional):
   - `bin/analyze_missing_dates.php`
   - `bin/create_last_year_cooking.php`
   - `bin/show_intervals.php`
   - `test_plan_generation.php`
   - `create_availabilities.php`

## Test-Anleitung

### 1. Server starten
```bash
symfony server:start
```

### 2. Im Browser öffnen
```
http://localhost:8000
```

### 3. Als Admin einloggen
- Email: `admin@kita.local`
- Passwort: `admin123`

### 4. Test-Szenarien

#### A) Familien-Liste anschauen
1. Navigation → "Familien"
2. Prüfen:
   - ✅ Familie Müller zeigt "Max, Sophie (2 Kinder)"
   - ✅ Geburtsjahre: "2020, 2021"
   - ✅ Familie Weber zeigt "Emma" (Leon ist weg!)

#### B) Familie im Detail anschauen
1. Auf "Anzeigen" bei Familie Müller klicken
2. Prüfen:
   - ✅ Zeigt beide Kinder mit Namen und Geburtsjahr
   - ✅ Login-Passwort basiert auf ältestem Kind (Max)

#### C) Kochplan für 25/26 generieren
1. Navigation → "Dashboard"
2. Button "Kochplan generieren" (falls noch nicht vorhanden)
3. Prüfen:
   - ✅ Wird Plan generiert?
   - ✅ Werden neue Familien berücksichtigt?
   - ✅ Werden LastYearCooking-Daten verwendet?
   - ✅ Nur verfügbare Termine zugewiesen?

#### D) Kalender anschauen
1. Navigation → "Kalender"
2. Prüfen:
   - ✅ Werden Familiennamen korrekt angezeigt?
   - ✅ Familie Müller: Zeigt "Max, Sophie"?

#### E) Statistik prüfen
1. Dashboard → "Statistik nach Familien"
2. Prüfen:
   - ✅ Werden Zuweisungen korrekt gezählt?
   - ✅ Sind neue Familien dabei?

### 5. Häufige Probleme

**Problem**: "Call to undefined method getChildName()"
**Lösung**: Cache leeren: `php bin/console cache:clear`

**Problem**: Kinder werden nicht angezeigt
**Lösung**: Prüfen ob Fixtures geladen: `php bin/console dbal:run-sql "SELECT * FROM parties LIMIT 1"`

**Problem**: Login funktioniert nicht
**Lösung**: Passwort-Generierung prüft ältestes Kind - sollte funktionieren

## Status

✅ **Bereit für UI-Test!**

Die Kern-Funktionalität ist vollständig angepasst:
- Entity ✅
- Migration ✅
- Test-Fixtures ✅
- Controller ✅
- Templates (Anzeige) ✅

**Nur deaktiviert** (für später):
- Neue Familie anlegen (Form)
- Familie bearbeiten (Form)

**Test-Daten bereit**:
- 44 Familien, 45 Kinder für Jahr 25/26
- Familie Müller: 2 Kinder
- Familie Weber: 1 Kind (Leon ausgeschieden)
- 6 neue Familien ohne Historie
