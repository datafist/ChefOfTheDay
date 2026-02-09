# Kochdienst-Verwaltungs-App für Kita

Eine Symfony 6.4 LTS Webanwendung zur gerechten Verteilung von Kochdiensten in einer Kindertagesstätte.

## 🎯 Features

- **Gerechte Verteilung**: Automatische, faire Verteilung der Kochdienste basierend auf Verfügbarkeit
- **Gewichtete Berücksichtigung**: Familien mit 1 Person erhalten automatisch weniger Dienste als Familien mit 2 Personen
- **Verfügbarkeitsangabe**: Eltern können ihre verfügbaren Tage markieren
- **Feiertage & Ferien**: Automatische Berücksichtigung von freien Tagen
- **Jahresübergreifende Fairness**: Mindestabstand zwischen Kochdiensten über Jahrsgrenzen hinweg
- **Flexible Anpassung**: Manuelles Zuweisen und Ändern von Diensten möglich
- **Inkrementelle Planänderungen**: Familien können nachträglich in bestehende Pläne aufgenommen oder entfernt werden
- **Security Headers**: CSP, X-Frame-Options, HSTS und weitere Schutzmaßnahmen
- **Audit Logging**: Protokollierung sicherheitsrelevanter Admin-Aktionen
- **Cron Automation**: Automatisierte Erinnerungen, Feiertags-Generierung und Jahresübergang

## 📋 Tech Stack

- **Backend**: Symfony 6.4 LTS, PHP 8.1+
- **Database**: MySQL 8.0 mit Doctrine ORM
- **Frontend**: Twig Templates, Stimulus JS, Symfony UX Components
- **Security**: Symfony Security Bundle
- **Production**: Docker, Traefik, Let's Encrypt

---

## 🚀 Lokale Entwicklung

### Voraussetzungen

- PHP 8.1 oder höher
- Composer
- Docker (für MySQL) oder lokale MySQL-Installation
- (Optional) Symfony CLI

### Quick Start

```bash
# 1. Repository klonen
git clone <repository-url>
cd ChefOfTheDay

# 2. Dependencies installieren
composer install

# 3. MySQL starten (via Docker)
docker compose -f docker-compose.dev.yaml up -d
# Warten bis MySQL bereit ist (ca. 10-30 Sekunden beim ersten Start)
sleep 10

# 4. Datenbank einrichten (Datenbank wird vom Container erstellt)
php bin/console doctrine:migrations:migrate --no-interaction

# 5. Admin-Benutzer erstellen
php bin/console app:setup-admin

# 6. Demo-Daten laden (optional, erstellt auch Admin)
php bin/console doctrine:fixtures:load

# 7. Assets installieren
php bin/console importmap:install

# 8. Server starten
symfony server:start
# oder: php -S localhost:8000 -t public/

# 9. Browser öffnen
open http://localhost:8000
```

### Datenbank-Konfiguration

Die `.env` Datei enthält bereits die Standard-Konfiguration für die lokale Entwicklung:

```env
DATABASE_URL="mysql://kochdienst:kochdienst@127.0.0.1:3306/kochdienst?serverVersion=8.0&charset=utf8mb4"
```

### MySQL stoppen/starten

```bash
# Starten
docker compose -f docker-compose.dev.yaml up -d

# Stoppen
docker compose -f docker-compose.dev.yaml down

# Stoppen und Daten löschen
docker compose -f docker-compose.dev.yaml down -v
```

### Demo-Daten

**Standard (6 Familien):**
```bash
php bin/console doctrine:fixtures:load
```

**Umfangreich (49 Familien):**
```bash
php bin/console doctrine:fixtures:load --group=large-scale
```

**Admin-Login:** `admin` / `admin123`

---

## 🐳 Production Deployment

Siehe [DEPLOYMENT.md](DEPLOYMENT.md) für die vollständige Anleitung zum Deployment auf einem VPS mit Docker und Traefik.

**Kurzversion:**
```bash
# Auf dem Server
git clone <repository-url> /opt/ChefOfTheDay
cd /opt/ChefOfTheDay
docker compose build
docker compose up -d
docker compose exec app php bin/console app:setup-admin
```

Optional: Demo-Daten in Produktion (nur wenn wirklich benoetigt):
```bash
docker compose exec -e APP_ALLOW_FIXTURES=1 app php bin/console doctrine:fixtures:load --no-interaction --env=prod
```

Hinweis: `APP_ALLOW_FIXTURES=1` aktiviert das Fixtures-Bundle nur fuer den Aufruf.

---

## 📚 Datenmodell

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

Die Anwendung hat eine umfassende Test-Suite mit **101 Tests** und **2412 Assertions**.

### Tests ausführen

```bash
# Alle Tests
php bin/phpunit

# Nur Unit Tests
php bin/phpunit tests/Unit/

# Nur Integration Tests
php bin/phpunit tests/Integration/

# Nur Functional Tests
php bin/phpunit tests/Functional/

# Nur Pre-existing Tests (Controller, DataPrivacy)
php bin/phpunit tests/Controller/ tests/DataPrivacy/
```

### Test-Datenbank einrichten

```bash
# Test-DB wird automatisch aus der Hauptkonfiguration abgeleitet (_test Suffix)
php bin/console doctrine:database:create --env=test
php bin/console doctrine:migrations:migrate --no-interaction --env=test
php bin/console doctrine:fixtures:load --no-interaction --env=test
```

### Test-Kategorien

| Kategorie | Dateien | Tests | Beschreibung |
|-----------|---------|-------|--------------|
| **Unit** | `tests/Unit/` | 44 | Entity-Logik, Feiertagsberechnung |
| **Integration** | `tests/Integration/` | 24 | CookingPlanGenerator, LastYearCookingService, DateExclusionService |
| **Functional** | `tests/Functional/` | 25 | Security Headers, Login (CSRF, Session), Admin-Zugangskontrolle |
| **Controller** | `tests/Controller/` | 5 | KitaYear-Löschschutz |
| **DataPrivacy** | `tests/DataPrivacy/` | 3 | DSGVO Hard-Delete-Verifikation |

## 📝 Fixtures (Demo-Daten)

```bash
php bin/console doctrine:fixtures:load
```

## 🗂 Projektstruktur

```
src/
├── Command/                # Console Commands (Cron-Jobs, Setup, Tests)
├── Controller/
│   ├── Admin/              # Admin-Controller (Dashboard, Familien, Kalender, KitaYear)
│   └── Parent/             # Eltern-Controller (Login, Verfügbarkeit)
├── Entity/                 # Doctrine Entities
├── EventSubscriber/        # Security Headers Subscriber
├── Form/                   # Symfony Forms
├── Repository/             # Doctrine Repositories
├── Security/               # Custom Authenticators
├── Service/
│   ├── AuditLogger.php     # Audit-Logging für Admin-Aktionen
│   ├── CookingPlanGenerator.php  # Algorithmus (inkl. add/remove Family)
│   ├── DateExclusionService.php  # Ferien/Feiertage/Wochenenden
│   ├── GermanHolidayService.php  # BW-Feiertagsberechnung
│   └── LastYearCookingService.php # Jahresübergang
└── Util/
templates/
├── admin/                  # Admin-Templates
├── emails/                 # E-Mail-Templates
├── parent/                 # Eltern-Templates
├── pdf/                    # PDF-Export-Templates
└── base.html.twig          # Base Layout
tests/
├── Unit/                   # Pure Unit Tests (44 Tests)
├── Integration/            # Service-Integration Tests (24 Tests)
├── Functional/             # HTTP-Level Tests (25 Tests)
├── Controller/             # Controller-Schutztests (5 Tests)
└── DataPrivacy/            # DSGVO Hard-Delete Tests (3 Tests)
config/
├── crontab                 # Crontab-Vorlage
├── systemd/                # Systemd Timer + Service Units
└── packages/               # Symfony-Konfiguration
```

## ✅ Vollständig implementierte Features

- [x] **Verfügbarkeits-Kalender**: Vollständige Kalender-UI mit Checkboxen für jeden Tag
  - Bulk-Aktionen (Alle Montage, Alle Dienstage, etc.)
  - Alle auswählen / Alle abwählen
  - Automatische Markierung von Feiertagen, Ferien, Wochenenden
  - Persistierung der Auswahl

- [x] **E-Mail-Benachrichtigungen**: Manuelles Benachrichtigungssystem
  - Email-Versand über Button "📧 E-Mails versenden" im Admin-Dashboard
  - Test-E-Mail-Funktion (Admin → E-Mail-Test)
  - Automatische Erinnerungen via Cron (3 Tage Vorlauf)

- [x] **PDF-Export**: Professioneller Kochplan-Export
  - Übersichtliche Monatsansicht
  - Alle Familien und Termine
  - Download-Link im Admin-Dashboard
  - Format: A4 Hochformat

- [x] **Inkrementelle Planänderungen**: Familie nachträglich aufnehmen/entfernen
  - "In Plan aufnehmen" transferiert Zuweisungen von überbelasteten Familien
  - "Aus Plan entfernen" verteilt zukünftige Dienste an andere um
  - Manuelle Zuweisungen bleiben bei Plangenerierung erhalten

- [x] **Security Headers**: HTTP-Sicherheitsheader auf allen Responses
  - Content-Security-Policy, X-Frame-Options, X-Content-Type-Options
  - Referrer-Policy, Permissions-Policy
  - HSTS nur in Produktion

- [x] **Audit Logging**: Dedizierter Log-Channel für Admin-Aktionen
  - Plan-Generierung/-Löschung, Zuweisungsänderungen
  - KitaYear-Verwaltung, Login-Versuche

- [x] **Cron Automation**: Automatisierte Hintergrundprozesse
  - Erinnerungs-E-Mails (Mo-Fr 8:00)
  - Feiertags-Generierung (jährlich 1. Juli)
  - Jahresübergang LastYearCooking (jährlich 1. August)
  - Siehe [CRON_SETUP.md](CRON_SETUP.md) für Setup-Anleitung

- [x] **Umfassende Tests**: 101 Tests mit 2412 Assertions
  - Unit Tests (Entities, Services)
  - Integration Tests (Plan-Generierung, Fairness, Jahresübergang)
  - Functional Tests (Security Headers, CSRF, Zugangskontrolle)
  - DSGVO Hard-Delete-Verifikation

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
