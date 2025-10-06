# Kochdienst-Verwaltungs-App für Kita

Eine Symfony 6.4 LTS Webanwendung zur gerechten Verteilung von Kochdiensten in einer Kindertagesstätte.

## 🎯 Features

- **Gerechte Verteilung**: Automatische, faire Verteilung der Kochdienste basierend auf Verfügbarkeit
- **Gewichtete Berücksichtigung**: Familien mit 1 Person erhalten automatisch weniger Dienste als Familien mit 2 Personen
- **Verfügbarkeitsangabe**: Eltern können ihre verfügbaren Tage markieren
- **Feiertage & Ferien**: Automatische Berücksichtigung von freien Tagen
- **Jahresübergreifende Fairness**: Mindestabstand zwischen Kochdiensten über Jahrsgrenzen hinweg
- **Flexible Anpassung**: Manuelles Zuweisen und Ändern von Diensten möglich

## 📋 Tech Stack

- **Backend**: Symfony 6.4 LTS, PHP 8.1+
- **Database**: MySQL 8.0 mit Doctrine ORM
- **Frontend**: Twig Templates, Stimulus JS, Symfony UX Components
- **Security**: Symfony Security Bundle

## 🚀 Installation

### Voraussetzungen

- PHP 8.1 oder höher
- Composer
- MySQL 8.0
- (Optional) Symfony CLI

### Setup

1. **Repository klonen**
   ```bash
   git clone <repository-url>
   cd ChefOfTheDay_symfony_mysql
   ```

2. **Dependencies installieren**
   ```bash
   composer install
   ```

3. **Datenbank konfigurieren**
   
   Bearbeite `.env` und setze deine Datenbankverbindung:
   ```
   DATABASE_URL="mysql://user:password@127.0.0.1:3306/kochdienst?serverVersion=8.0&charset=utf8mb4"
   ```

4. **Datenbank erstellen und Migrations ausführen**
   ```bash
   php bin/console doctrine:database:create
   php bin/console doctrine:migrations:migrate
   ```

5. **Demo-Daten laden**
   
   **Option A: Einfache Demo (6 Familien)**
   ```bash
   php bin/console doctrine:fixtures:load
   ```
   
   **Option B: Umfangreicher Test (49 Familien, realistische Verfügbarkeiten)**
   ```bash
   php bin/console doctrine:fixtures:load --group=large-scale
   ```
   
   ℹ️ Admin-Login: `admin@kita.local` / `admin123`

6. **Asset Mapper kompilieren**
   ```bash
   php bin/console importmap:install
   ```

7. **Development Server starten**
   ```bash
   symfony server:start
   # oder
   php -S localhost:8000 -t public/
   ```

8. **Öffne im Browser**
   ```
   http://localhost:8000
   ```

## � Email-System

Das Email-System ist vollständig konfiguriert und verwendet **Mailpit** für lokale Entwicklung:

### Mailpit (Development)
- Web-Interface: http://localhost:56257
- SMTP: localhost:56256
- Alle Emails werden abgefangen und im Web-Interface angezeigt

### Email-Features
1. **Kochplan-Benachrichtigung**: Automatisch beim Generieren des Plans
2. **Erinnerungen**: 
   ```bash
   # 3 Tage vorher (Standard)
   php bin/console app:send-reminders
   
   # 7 Tage vorher
   php bin/console app:send-reminders 7
   ```

### Cronjob einrichten (Production)
```bash
# Täglich um 9:00 Uhr Erinnerungen für Kochdienste in 3 Tagen
0 9 * * * cd /path/to/project && php bin/console app:send-reminders 3
```

## �📚 Datenmodell

### Entities

- **Party** (Familie): Kindname, Geburtsjahr, Elternteile (1-2 Personen), Email
- **KitaYear**: Kita-Jahr Zeitraum (1. September - 31. August)
- **Availability**: Verfügbarkeitsangaben der Familien
- **CookingAssignment**: Zugewiesene Kochdienste
- **Holiday**: Feiertage
- **Vacation**: Ferienzeiten
- **LastYearCooking**: Letzte Kochdienste aus Vorjahr
- **User**: Admin-Benutzer

## 👨‍💼 Admin-Bereich

Der Admin-Bereich bietet:

- CRUD für Familien-Verwaltung
- Feiertage und Ferien definieren
- Kita-Jahre anlegen und verwalten
- Kochplan generieren
- Konflikte manuell auflösen
- Kochplan-Export (PDF/Excel)

**Login**: `/login` mit Admin-Credentials

## 👨‍👩‍👧 Eltern-Bereich

Eltern-Login funktioniert über:
1. Auswahl der eigenen Familie aus Liste
2. Passwort: **Erster Buchstabe des Kindnamens + Geburtsjahr**
   - Beispiel: Kind "Max", geboren 2019 → Passwort: `M2019`

**Funktionen:**
- Verfügbarkeiten angeben (Checkbox-Kalender)
- Bulk-Aktionen für Wochentage
- Eigene Kochdienste ansehen

## 🔧 Algorithmus

Der Kochplan-Generierungs-Algorithmus berücksichtigt:

1. **Gerechte Verteilung nach Verfügbarkeit**: 
   - Familien mit 2 Personen: 2x Gewichtung
   - Familien mit 1 Person: 1x Gewichtung
   - Anzahl der Dienste richtet sich nach verfügbaren Tagen
   
2. **Verfügbarkeits-Constraints**:
   - Nur an verfügbaren Tagen werden Dienste zugewiesen
   - Feiertage und Ferien automatisch ausgeschlossen
   - Wochenenden ausgeschlossen
   
3. **Dynamische Abstände**:
   - Mindestabstand zwischen Diensten passt sich der Familienanzahl an
   - Berücksichtigung des Vorjahres bei Jahresübergang
   
4. **Flexible Anpassung**:
   - Manuelle Zuweisung bei Bedarf möglich
   - Änderungen und Löschungen über Kalenderansicht
   - Automatische Konfliktidentifikation

## 🧪 Testing

```bash
php bin/phpunit
```

## 📝 Fixtures (Demo-Daten)

```bash
php bin/console doctrine:fixtures:load
```

## 🗂 Projektstruktur

```
src/
├── Controller/
│   ├── Admin/          # Admin-Controller
│   └── Parent/         # Eltern-Controller
├── Entity/             # Doctrine Entities
├── Form/               # Symfony Forms
├── Repository/         # Doctrine Repositories
├── Security/           # Custom Authenticators
└── Service/
    └── CookingPlanGenerator.php  # Algorithmus
templates/
├── admin/              # Admin-Templates
├── parent/             # Eltern-Templates
└── base.html.twig      # Base Layout
```

## ✅ Vollständig implementierte Features

- [x] **Verfügbarkeits-Kalender**: Vollständige Kalender-UI mit Checkboxen für jeden Tag
  - Bulk-Aktionen (Alle Montage, Alle Dienstage, etc.)
  - Alle auswählen / Alle abwählen
  - Automatische Markierung von Feiertagen, Ferien, Wochenenden
  - Persistierung der Auswahl

- [x] **E-Mail-Benachrichtigungen**: Automatisches Benachrichtigungssystem
  - Email beim Generieren des Kochplans
  - Erinnerungen X Tage vor dem Kochdienst
  - Console-Command: `php bin/console app:send-reminders [days]`
  - Test mit Mailpit (http://localhost:56257)

- [x] **PDF-Export**: Professioneller Kochplan-Export
  - Übersichtliche Monatsansicht
  - Alle Familien und Termine
  - Download-Link im Admin-Dashboard
  - Format: A4 Hochformat

## 📈 Weitere mögliche Erweiterungen

- [ ] Mehrsprachigkeit (DE/EN)
- [ ] Mobile App
- [ ] API für externe Integrationen
- [ ] Statistiken und Auswertungen
- [ ] Tauschfunktion zwischen Familien

## 🤝 Contributing

Contributions sind willkommen! Bitte erstelle einen Pull Request oder öffne ein Issue.

## 📄 Lizenz

Dieses Projekt steht unter der MIT-Lizenz.

## 👤 Author

Erstellt für die Kita-Gemeinschaft 🏫

## 🆘 Support

Bei Fragen oder Problemen öffne ein Issue auf GitHub.
