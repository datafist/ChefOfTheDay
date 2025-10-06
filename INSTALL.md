# Schnellstart-Anleitung

## 🚀 Schnellstart für lokale Entwicklung

### 1. MySQL Datenbank starten (mit Docker)

Wenn Sie Docker installiert haben, können Sie die Datenbank mit dem mitgelieferten `compose.yaml` starten:

```bash
docker compose up -d
```

Oder manuell MySQL starten und eine Datenbank erstellen:

```bash
mysql -u root -p
CREATE DATABASE kochdienst CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;
```

### 2. Datenbank-Schema erstellen

```bash
# Migrations ausführen
php bin/console doctrine:migrations:migrate

# Oder direkt Schema erstellen (wenn keine Migrations vorhanden)
php bin/console doctrine:schema:create
```

### 3. Demo-Daten laden

**Option A: Einfache Demo (6 Familien)**
```bash
php bin/console doctrine:fixtures:load
```

**Dies erstellt:**
- Admin-User: `admin@kita.local` / Passwort: `admin123`
- 6 Beispiel-Familien (inkl. 2 Alleinerziehende)
- Kita-Jahr 2024/2025
- Feiertage und Ferienzeiten

**Option B: Umfangreicher Test mit realistischen Daten (49 Familien)**
```bash
php bin/console doctrine:fixtures:load --group=large-scale
```

**Dies erstellt:**
- Admin-User: `admin@kita.local` / Passwort: `admin123`
- **Jahr 24/25**: 45 Familien mit generiertem Plan (Altdaten)
- **Jahr 25/26**: 49 Familien (45 + 4 neue) - **AKTIV**
- Realistische Verfügbarkeiten (15% nur Mo+Fr, 20% 2-3 Tage/Woche, etc.)
- Feiertage und Ferienzeiten für beide Jahre
- LastYearCooking-Einträge aus 24/25

⚠️ **Empfohlen für realistische Tests!** Siehe `TEST_SCENARIO_REALISTIC_AVAILABILITY.md`

### 4. Server starten

**Option A: Mit Symfony CLI (empfohlen)**
```bash
symfony server:start
```

**Option B: Mit PHP Built-in Server**
```bash
php -S localhost:8000 -t public/
```

### 5. E-Mail-Versand konfigurieren (optional)

Für den E-Mail-Versand an Eltern:

```bash
# Kopiere .env.local.example nach .env.local
cp .env.local.example .env.local

# Bearbeite .env.local und trage deine SMTP-Credentials ein
nano .env.local
```

**Beispiel für Gmail:**
```env
MAILER_DSN=gmail+smtp://deine-email@gmail.com:app-passwort@default
```

📖 **Detaillierte Anleitung**: Siehe `SMTP_CONFIGURATION.md`

### 6. Anwendung öffnen

Öffne in deinem Browser: **http://localhost:8000**

## 📝 Login-Credentials

### Admin-Bereich
- **URL**: http://localhost:8000/login
- **Email**: admin@kita.local
- **Passwort**: admin123

### Eltern-Login
Beispiel-Passwörter für die Demo-Familien:
- Max (Müller): `M2019`
- Sophie (Schmidt): `S2020`
- Leon (Weber): `L2018`
- Emma (Meier): `E2021`
- Noah (Schulz): `N2019`
- Mia (Fischer): `M2020`

## 🛠 Wichtige Kommandos

### Datenbank zurücksetzen
```bash
php bin/console doctrine:database:drop --force
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load
```

### Cache leeren
```bash
php bin/console cache:clear
```

### Neue Entity erstellen
```bash
php bin/console make:entity
```

### Neue Controller erstellen
```bash
php bin/console make:controller
```

### Migration erstellen
```bash
php bin/console make:migration
```

## 🐛 Troubleshooting

### "Connection refused" Fehler
- Stelle sicher, dass MySQL läuft
- Prüfe die DATABASE_URL in `.env`
- Bei Docker: `docker compose ps` um Status zu checken

### "Class not found" Fehler
```bash
composer dump-autoload
php bin/console cache:clear
```

### Assets werden nicht geladen
```bash
php bin/console importmap:install
php bin/console asset-map:compile
```

## 📚 Nächste Schritte

1. **Familien anlegen**: Im Admin-Bereich unter "Familien"
2. **Kita-Jahr konfigurieren**: Feiertage und Ferien hinzufügen
3. **Verfügbarkeiten eingeben**: Als Eltern einloggen und Tage markieren
4. **Kochplan generieren**: Im Admin-Bereich

## 🔐 Produktions-Hinweise

Vor dem Deployment:

1. Ändere `APP_SECRET` in `.env.prod` oder `.env.local`
2. Setze `APP_ENV=prod`
3. Ändere Datenbank-Credentials in `.env.local`
4. Konfiguriere SMTP-Server in `.env.local` (siehe `SMTP_CONFIGURATION.md`)
5. Aktiviere HTTPS
6. Admin-Passwort ändern nach erstem Login

```bash
# Produktions-Assets kompilieren
composer install --no-dev --optimize-autoloader
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
php bin/console asset-map:compile
```

**SMTP für Produktion:**
```bash
# Auf dem Server .env.local erstellen
nano .env.local

# SMTP-Credentials eintragen
MAILER_DSN=smtp://benutzer:passwort@smtp.server.de:587?encryption=tls

# Cache neu laden
php bin/console cache:clear --env=prod
```
