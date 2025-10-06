# Symfony CLI - Quick Start Guide

## ✅ Installation erfolgreich!

**Version:** Symfony CLI 5.15.1  
**Datum:** 4. Oktober 2025  
**System:** WSL (Windows Subsystem for Linux) / Ubuntu

---

## 🚀 Server starten

### Methode 1: Vordergrund (mit Logs)
```bash
cd /home/florian/Projekte/Privat/ChefOfTheDay_symfony_mysql
symfony server:start
```

**Vorteile:**
- Sehen Sie Logs direkt im Terminal
- Strg+C zum Stoppen

### Methode 2: Hintergrund/Daemon (empfohlen) ✅
```bash
cd /home/florian/Projekte/Privat/ChefOfTheDay_symfony_mysql
symfony server:start -d
```

**Vorteile:**
- Server läuft im Hintergrund
- Terminal bleibt frei für andere Befehle
- Server läuft weiter nach Terminal-Schließung

**Aktueller Status:** ✅ Läuft auf http://127.0.0.1:8000

---

## 🛑 Server stoppen

```bash
cd /home/florian/Projekte/Privat/ChefOfTheDay_symfony_mysql
symfony server:stop
```

---

## 📊 Server-Status prüfen

```bash
symfony server:status
```

**Ausgabe:**
```
Local Web Server
    Listening on http://127.0.0.1:8000
    The Web server is using PHP FPM 8.3.24
```

---

## 📝 Logs anzeigen

```bash
symfony server:log
```

**Live-Logs verfolgen:**
```bash
symfony server:log -f
# oder
symfony server:log --follow
```

---

## 🔧 Häufige Befehle

### Console Commands
```bash
# Cache leeren
symfony console cache:clear

# Datenbank-Schema erstellen
symfony console doctrine:schema:create

# Fixtures laden
symfony console doctrine:fixtures:load

# Alle Console-Befehle anzeigen
symfony console list
```

### Composer
```bash
# Abhängigkeiten installieren
symfony composer install

# Package installieren
symfony composer require package/name

# Package entfernen
symfony composer remove package/name

# Updates anzeigen
symfony composer outdated
```

### PHP
```bash
# PHP-Version prüfen
symfony php -v

# PHP-Konfiguration anzeigen
symfony php -i

# Script ausführen
symfony php script.php
```

### Sicherheit
```bash
# Sicherheits-Check für Dependencies
symfony security:check

# Symfony Requirements prüfen
symfony check:requirements
```

---

## 🔐 HTTPS aktivieren (optional)

### 1. CA-Zertifikat installieren
```bash
symfony server:ca:install
```

### 2. Server mit HTTPS starten
```bash
symfony server:start -d
# Jetzt verfügbar auf: https://127.0.0.1:8000
```

**Hinweis:** Browser wird warnen, dass Zertifikat selbst-signiert ist. Das ist normal für lokale Entwicklung.

---

## 🌐 Von anderen Geräten zugreifen

### Alle IP-Adressen erlauben
```bash
symfony server:start -d --allow-all-ip
```

### Spezifische IP festlegen
```bash
symfony server:start -d --listen-ip=192.168.1.100
```

**Achtung:** Nur in vertrauenswürdigen Netzwerken verwenden!

---

## 🐛 Troubleshooting

### Problem: Port 8000 bereits belegt

**Lösung 1:** Anderen Port verwenden
```bash
symfony server:start -d --port=8001
```

**Lösung 2:** Bestehenden Server stoppen
```bash
symfony server:stop
# oder anderen PHP-Server beenden
pkill -f "php -S"
```

### Problem: Server startet nicht

**Prüfen Sie:**
```bash
# PHP-Version
symfony php -v

# Requirements
symfony check:requirements

# Logs für Fehler
symfony server:log
```

### Problem: 404 Fehler für alle Routes

**Prüfen Sie:**
```bash
# Ist public/ das Document Root?
ls -la public/

# Gibt es public/index.php?
cat public/index.php
```

---

## 📚 Weitere Informationen

### Offizielle Dokumentation
- https://symfony.com/doc/current/setup/symfony_server.html
- https://github.com/symfony-cli/symfony-cli

### Help-Befehl
```bash
symfony help server:start
symfony help console
symfony help
```

### Liste aller Befehle
```bash
symfony list
```

---

## 🎯 Empfohlener Workflow

### 1. Morgens: Server starten
```bash
cd ~/Projekte/Privat/ChefOfTheDay_symfony_mysql
symfony server:start -d
```

### 2. Entwickeln
- Browser: http://127.0.0.1:8000
- Code bearbeiten
- Änderungen werden sofort sichtbar

### 3. Bei Bedarf: Cache leeren
```bash
symfony console cache:clear
```

### 4. Abends: Server stoppen
```bash
symfony server:stop
```

**Tipp:** Server kann auch laufen bleiben - verbraucht kaum Ressourcen!

---

## ✨ Bonus-Features

### Mehrere Projekte gleichzeitig

Symfony CLI verwaltet automatisch verschiedene Ports:
- Projekt 1: http://127.0.0.1:8000
- Projekt 2: http://127.0.0.1:8001
- Projekt 3: http://127.0.0.1:8002

### Environment Variables aus Docker

Symfony CLI liest automatisch Docker-Container-Variablen:
- Datenbank-Credentials
- Service-URLs
- etc.

### Auto-Reload bei PHP-Konfiguration

Bei Änderungen an php.ini startet der Server automatisch neu.

---

## 🎉 Viel Erfolg!

Die Symfony CLI ist installiert und läuft. Ihr Kochdienst-Projekt ist unter http://127.0.0.1:8000 erreichbar!

**Nächste Schritte:**
1. ✅ Server läuft bereits
2. Browser öffnen: http://127.0.0.1:8000
3. Als Admin einloggen: admin@kita.local / admin123
4. Los geht's! 🚀
