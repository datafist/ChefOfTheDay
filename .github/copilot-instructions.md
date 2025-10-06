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
- [x] Dokumentation vervollständigen (README.md, INSTALL.md, PROJECT_SUMMARY.md)

## Nächste Schritte (manuell durchführen)

1. MySQL-Datenbank starten: `docker compose up -d`
2. Datenbank erstellen: `php bin/console doctrine:database:create`
3. Schema erstellen: `php bin/console doctrine:schema:create`
4. Demo-Daten laden: `php bin/console doctrine:fixtures:load`
5. Server starten: `symfony server:start` oder `php -S localhost:8000 -t public/`
6. Im Browser öffnen: http://localhost:8000
7. Admin-Login: admin@kita.local / admin123
