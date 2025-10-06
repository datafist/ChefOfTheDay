# Symfony CLI Installation

## ✅ Installation abgeschlossen!

**Version:** Symfony CLI 5.15.1  
**Installiert am:** 4. Oktober 2025  
**Speicherort:** `/usr/local/bin/symfony`

### Installation prüfen
```bash
symfony version
# Output: Symfony CLI version 5.15.1 (c) 2021-2025 Fabien Potencier

symfony check:requirements
# Output: [OK] Your system is ready to run Symfony projects
```

---

## Option 1: Offizieller Installer (empfohlen)

### Linux/Mac
```bash
curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.deb.sh' | sudo -E bash
sudo apt install symfony-cli
```

### Alternative: Direkter Download ✅ (VERWENDET)
```bash
cd /tmp
wget https://github.com/symfony-cli/symfony-cli/releases/latest/download/symfony-cli_linux_amd64.tar.gz
tar -xzf symfony-cli_linux_amd64.tar.gz
sudo mv symfony /usr/local/bin/symfony
sudo chmod +x /usr/local/bin/symfony
rm symfony-cli_linux_amd64.tar.gz
```

## Option 2: Ohne Symfony CLI arbeiten

Die Symfony CLI ist **optional**. Sie können alle Befehle auch mit PHP direkt ausführen:

| Symfony CLI | PHP Alternative |
|-------------|----------------|
| `symfony server:start` | `php -S localhost:8000 -t public/` |
| `symfony console cache:clear` | `php bin/console cache:clear` |
| `symfony composer require package` | `composer require package` |
| `symfony check:requirements` | *Nicht verfügbar* |

## Wie verwende ich die Symfony CLI jetzt?

### Server starten

**Symfony CLI (empfohlen):**
```bash
cd /home/florian/Projekte/Privat/ChefOfTheDay_symfony_mysql
symfony server:start
# Oder im Hintergrund:
symfony server:start -d
```

**PHP Built-in Server (Alternative):**
```bash
php -S localhost:8000 -t public/
```

### Server stoppen
```bash
symfony server:stop
```

### Server-Status prüfen
```bash
symfony server:status
```

### Server-Logs anzeigen
```bash
symfony server:log
```

## Vorteile der Symfony CLI

1. **Automatisches HTTPS** mit selbst-signiertem Zertifikat
2. **HTTP/2 Support**
3. **Proxy für lokale Domains** (.test, .local, etc.)
4. **Projekt-Erkennung** (automatischer Port-Wechsel bei mehreren Projekten)
5. **Requirements Checker** (`symfony check:requirements`)
6. **PHP Version Management** (mit php-version switcher)

## Nachteile ohne Symfony CLI

- ❌ Kein automatisches HTTPS
- ❌ Kein HTTP/2
- ⚠️ Manuell Port wechseln bei mehreren Projekten
- ✅ **Aber:** Für Entwicklung mit HTTP völlig ausreichend!

## ✅ Jetzt verfügbar: Symfony CLI

**Status:** ✅ Installiert und einsatzbereit!

### Vorteile, die Sie jetzt haben:

✅ **Automatisches HTTPS** mit selbst-signiertem Zertifikat  
✅ **HTTP/2 Support** für bessere Performance  
✅ **Bessere Performance** als PHP Built-in Server  
✅ **Automatische Port-Verwaltung** bei mehreren Projekten  
✅ **PHP Version Detection** - nutzt die richtige PHP-Version  
✅ **Requirements Checker** (`symfony check:requirements`)  
✅ **Komfortable Server-Verwaltung** (start, stop, log, status)

### Häufig verwendete Befehle

```bash
# Server starten (Vordergrund)
symfony server:start

# Server starten (Hintergrund/Daemon)
symfony server:start -d

# Server stoppen
symfony server:stop

# Server-Status
symfony server:status

# Logs anzeigen
symfony server:log

# Console-Befehle ausführen
symfony console cache:clear
symfony console doctrine:migrations:migrate

# Composer-Befehle
symfony composer require package-name
symfony composer install

# PHP-Version prüfen
symfony php -v

# Requirements prüfen
symfony check:requirements

# Sicherheits-Check
symfony security:check
```

## Aktueller Server-Status

```bash
# Server läuft bereits auf:
http://localhost:8000

# Mailpit UI:
http://localhost:56256

# MySQL:
localhost:3306
```

Alles funktioniert! 🎉
