# Kochdienst-Verwaltungs-App für Kita - Projektanweisungen

## Projekt-Übersicht
Symfony LTS Anwendung zur gerechten Verteilung von Kochdiensten in einer Kita.

## Tech Stack
- Symfony 6.4 LTS
- PHP 8.1+
- MySQL 8.0
- Doctrine ORM
- Twig Templates
- Stimulus JS
- Symfony UX Components

## Fortschritt

- [x] Copilot-Instructions erstellt
- [x] Projekt-Setup durchführen (Symfony 6.4 + alle Dependencies)
- [x] Projekt anpassen (Entities, Controller, Services, Forms, Templates)
- [x] Dokumentation vervollständigen (README.md)
- [x] Bug-Fixes (GermanHolidayService DateTimeImmutable, Login-Template CSRF/Passwort-Anzeige)
- [x] Inkrementelle Planänderungen (addFamilyToPlan, removeFamilyFromPlan)
- [x] Security Headers (SecurityHeadersSubscriber)
- [x] Audit Logging (AuditLogger Service mit dediziertem Log-Channel)
- [x] LastYearCooking Service + Command (Jahresübergang)
- [x] Migration: LastYearCooking kita_year_id nullable + SET NULL
- [x] Cron/Scheduler Automation (cron-wrapper, crontab, systemd timers)
- [x] Umfassende Tests (101 Tests, 2412 Assertions — Unit, Integration, Functional)

## Architektur-Hinweise

### Entity-Relationen (wichtig für Tests)
- `Party` → `CookingAssignment`, `Availability`, `LastYearCooking`: CASCADE DELETE
- `KitaYear` → `CookingAssignment`, `Availability`, `Holiday`, `Vacation`: CASCADE DELETE
- `KitaYear` → `LastYearCooking`: **SET NULL** (nicht CASCADE — Vorjahresdaten überleben Jahres-Löschung)

### Tabellen-Namen (für DBAL-Queries)
- `parties`, `kita_years`, `cooking_assignments`, `availabilities`, `holidays`, `vacations`, `last_year_cookings`, `users`

### Routen (Trail-Slash beachten!)
- Admin-Seiten: `/admin/`, `/admin/kita-year/`, `/admin/party/`, `/admin/calendar`
- Eltern: `/parent/login`, `/parent/availability`, `/parent/logout`
- Login: `/login`

### Test-Datenbank
- Automatisch `_test` Suffix via Doctrine `dbname_suffix`
- `.env.test` enthält DB-URL OHNE `_test` (wird automatisch angehängt)

## Quick Start

1. MySQL-Datenbank starten: `docker compose -f docker-compose.dev.yaml up -d`
2. Migrationen ausführen: `php bin/console doctrine:migrations:migrate`
3. Demo-Daten laden: `php bin/console doctrine:fixtures:load`
4. Server starten: `symfony server:start` oder `php -S localhost:8000 -t public/`
5. Im Browser öffnen: http://localhost:8000
6. Admin-Login: admin / admin123 (Username statt E-Mail!)
   ⚠️ **WICHTIG:** Passwort sofort nach erstem Login ändern: `php bin/console app:setup-admin`
