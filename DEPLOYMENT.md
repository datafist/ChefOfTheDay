# Deployment auf Hostinger VPS mit Traefik

> **💡 Lokale Entwicklung?** Siehe [README.md](README.md#-lokale-entwicklung) für die Anleitung zur lokalen Entwicklung mit PHP und MySQL.

Diese Anleitung beschreibt das Production-Deployment auf einem VPS mit Docker und Traefik als Reverse Proxy für SSL/Let's Encrypt.

## Übersicht

| Komponente | Details |
|------------|---------|
| **Domain** | `kochdienst.florianbirkenberger.de` |
| **Reverse Proxy** | Traefik v2.11 |
| **SSL** | Let's Encrypt (automatisch via Traefik) |
| **Container** | PHP 8.2 FPM + Nginx + Supervisor |
| **Datenbank** | MySQL 8.0 |

## Voraussetzungen

- VPS mit Docker und Docker Compose installiert
- Traefik läuft bereits als Reverse Proxy
- DNS-Eintrag für `kochdienst.florianbirkenberger.de` zeigt auf den VPS
- Das externe Docker-Netzwerk `web` existiert (wird von Traefik erstellt)

## Schritt 1: Repository auf den Server klonen

```bash
ssh user@your-vps-ip
cd /opt/apps  # oder ein anderer Ordner für deine Apps
git clone <repository-url> kochdienst
cd kochdienst
```

## Schritt 2: Environment-Variablen konfigurieren

```bash
# Beispiel-Datei kopieren
cp .env.example .env

# Sichere Passwörter generieren und eintragen
echo "APP_SECRET=$(openssl rand -hex 32)"
echo "MYSQL_ROOT_PASSWORD=$(openssl rand -base64 24)"
echo "MYSQL_PASSWORD=$(openssl rand -base64 24)"

# .env mit den generierten Werten bearbeiten
nano .env
```

> ⚠️ **Wichtig:** Die `.env` Datei ist in `.gitignore` und wird NICHT ins Repository committed!

## Schritt 3: Container bauen und starten

```bash
# Image bauen
docker compose build

# Container starten
docker compose up -d

# Logs überprüfen
docker compose logs -f
```

## Schritt 4: Initiale Einrichtung

Nach dem ersten Start werden die Migrationen automatisch ausgeführt.

### Admin-Benutzer erstellen

```bash
docker compose exec app php bin/console app:setup-admin
```

### Optional: Demo-Daten laden

```bash
docker compose exec app php bin/console doctrine:fixtures:load --no-interaction
```

## Wichtige Befehle

### Container Status
```bash
docker compose ps
```

### Logs anzeigen
```bash
# Alle Logs
docker compose logs -f

# Nur App-Logs
docker compose logs -f app
```

### Container neu starten
```bash
docker compose restart app
```

### Cache leeren
```bash
docker compose exec app php bin/console cache:clear --env=prod
```

### Migrationen manuell ausführen
```bash
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
```

### In Container einloggen
```bash
docker compose exec app sh
```

## Updates deployen

```bash
cd /opt/apps/kochdienst

# Neueste Version holen
git pull

# Container neu bauen und starten
docker compose build
docker compose up -d

# Migrationen ausführen (falls nötig)
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction

# Cache leeren
docker compose exec app php bin/console cache:clear --env=prod
```

## Backup

### Datenbank-Backup erstellen
```bash
docker compose exec database \
  mysqldump -u root -p${MYSQL_ROOT_PASSWORD} ${MYSQL_DATABASE} > backup_$(date +%Y%m%d).sql
```

### Datenbank wiederherstellen
```bash
docker compose exec -T database \
  mysql -u root -p${MYSQL_ROOT_PASSWORD} ${MYSQL_DATABASE} < backup.sql
```

## Troubleshooting

### Container startet nicht
```bash
# Detaillierte Logs anzeigen
docker compose logs app

# Container-Status prüfen
docker compose ps
```

### Datenbank-Verbindungsfehler
```bash
# Prüfen ob DB läuft
docker compose exec database mysqladmin ping -h localhost -u root -p

# Netzwerk-Verbindung testen
docker compose exec app ping database
```

### SSL/Traefik Probleme
```bash
# Traefik-Logs prüfen
docker logs traefik

# Prüfen ob Container im web-Netzwerk ist
docker network inspect web
```

## Sicherheitshinweise

1. **Die `.env` Datei ist in `.gitignore`** - niemals manuell committen!
2. Regelmäßige Datenbank-Backups erstellen
3. Docker und alle Images regelmäßig updaten
4. Firewall auf dem VPS konfigurieren (nur 80/443 von außen)
