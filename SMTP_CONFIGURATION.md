# SMTP-Konfiguration für E-Mail-Versand

## 🔐 Sichere Konfiguration mit .env.local

## 5. Testen der E-Mail-Konfiguration

### Option 1: Test-Kommando (empfohlen)

Das schnellste Verfahren zum Testen der SMTP-Konfiguration:

```bash
# Cache leeren nach .env.local Änderungen
php bin/console cache:clear

# Test-E-Mail senden
php bin/console app:test-email deine-email@example.com
```

Das Kommando gibt detailliertes Feedback:
- ✅ Erfolg: E-Mail wurde versendet
- ❌ Fehler: Zeigt Fehlermeldung und mögliche Ursachen

### Option 2: Über das Admin-Dashboard

1. **Cache leeren:**
   ```bash
   php bin/console cache:clear
   ```

2. **Plan generieren:**
   - Melden Sie sich als Admin an (admin@kita.local / admin123)
   - Gehen Sie zum Dashboard
   - Klicken Sie auf "📅 Plan generieren"

3. **Test-E-Mails senden:**
   - Klicken Sie auf "📧 E-Mails versenden"
   - Prüfen Sie die E-Mail-Postfächer der Test-Familiente und empfohlene Methode ist die Verwendung von `.env.local` Datei.

### 1️⃣ .env.local Datei erstellen

Erstelle eine neue Datei `.env.local` im Projekt-Root:

```bash
touch .env.local
```

**Wichtig:** Diese Datei ist bereits in `.gitignore` und wird NICHT committet!

### 2️⃣ SMTP-Credentials eintragen

Füge in `.env.local` deine SMTP-Konfiguration ein:

#### Option A: Gmail/Google Workspace

```env
###> symfony/mailer ###
# Gmail mit App-Passwort (empfohlen)
MAILER_DSN=gmail+smtp://deine-email@gmail.com:app-passwort@default

# Oder mit normalem Passwort (weniger sicher)
MAILER_DSN=smtp://deine-email@gmail.com:passwort@smtp.gmail.com:587
###< symfony/mailer ###
```

**Gmail App-Passwort erstellen:**
1. Google-Konto → Sicherheit
2. 2-Faktor-Authentifizierung aktivieren
3. App-Passwörter → Neues Passwort generieren
4. Passwort kopieren und in MAILER_DSN eintragen

#### Option B: Standard SMTP (z.B. Office365, eigener Server)

```env
###> symfony/mailer ###
# Office 365
MAILER_DSN=smtp://benutzer@domain.de:passwort@smtp.office365.com:587?encryption=tls

# Eigener SMTP-Server
MAILER_DSN=smtp://benutzer:passwort@mail.domain.de:587?encryption=tls

# Mit SSL statt TLS (Port 465)
MAILER_DSN=smtp://benutzer:passwort@mail.domain.de:465?encryption=ssl
###< symfony/mailer ###
```

#### Option C: Andere Anbieter

```env
###> symfony/mailer ###
# Postmark
MAILER_DSN=postmark://TOKEN@default

# SendGrid
MAILER_DSN=sendgrid://API_KEY@default

# Mailgun
MAILER_DSN=mailgun://API_KEY:DOMAIN@default

# Amazon SES
MAILER_DSN=ses://ACCESS_KEY:SECRET_KEY@default?region=eu-central-1
###< symfony/mailer ###
```

### 3️⃣ Absender-Adresse konfigurieren (optional)

In `config/packages/mailer.yaml`:

```yaml
framework:
    mailer:
        dsn: '%env(MAILER_DSN)%'
        envelope:
            sender: 'kita@example.com'
        headers:
            From: 'Kita Kochdienst <kita@example.com>'
```

### 4️⃣ Test-Versand

Nach der Konfiguration kannst du den E-Mail-Versand testen:

1. Plan im Admin-Dashboard generieren
2. Button "📧 E-Mails versenden" klicken
3. Prüfe ob E-Mails ankommen

**Debug-Modus:**
- In `dev` Environment werden E-Mails in der Symfony Toolbar angezeigt
- Keine echten E-Mails werden versendet (außer `MAILER_DSN` ist konfiguriert)

### 5️⃣ Produktiv-Umgebung

Für Produktiv-Server:

**Option A: .env.local auf Server**
```bash
# Auf dem Server .env.local erstellen
nano .env.local

# MAILER_DSN eintragen
MAILER_DSN=smtp://...

# Cache leeren
php bin/console cache:clear --env=prod
```

**Option B: Umgebungsvariablen**
```bash
# In Server-Konfiguration (Apache/Nginx)
SetEnv MAILER_DSN "smtp://benutzer:passwort@smtp.server.de:587"

# Oder in systemd Service
Environment="MAILER_DSN=smtp://..."
```

## 🔒 Sicherheits-Checkliste

- ✅ `.env.local` wird NICHT ins Git committet
- ✅ Passwörter werden NICHT in `.env` (committed) gespeichert
- ✅ Produktiv-Server verwendet eigene `.env.local`
- ✅ App-Passwörter statt echte Passwörter verwenden
- ✅ TLS/SSL Verschlüsselung aktiviert
- ⚠️ Keine Credentials im Code oder öffentlichen Repositories

## 📋 DSN Format

Das DSN (Data Source Name) Format:

```
PROTOKOLL://BENUTZER:PASSWORT@HOST:PORT?OPTIONEN
```

**Beispiele:**
```
smtp://user:pass@smtp.gmail.com:587?encryption=tls
gmail+smtp://user:app-password@default
smtp://user:pass@localhost:25
```

**Sonderzeichen escapen:**
```bash
# Passwort: p@ss:word!
# Escaped: p%40ss%3Aword%21
MAILER_DSN=smtp://user:p%40ss%3Aword%21@smtp.server.de:587
```

## 🧪 Testen ohne echten SMTP

Für Entwicklung/Tests:

```env
# E-Mails werden nicht versendet, aber geloggt
MAILER_DSN=null://null

# Alle E-Mails an eine Test-Adresse
MAILER_DSN=smtp://user:pass@smtp.server.de:587?envelope_to=test@example.com
```

## 🔧 Alternative: Datenbank-Konfiguration

Falls gewünscht, kann ich auch eine UI-Lösung mit Datenbank-Speicherung implementieren.

**Vorteile:**
- ✅ Konfiguration über Admin-Interface
- ✅ Keine Server-Zugriff nötig

**Nachteile:**
- ⚠️ Credentials in Datenbank (verschlüsselt)
- ⚠️ Komplexer zu implementieren
- ⚠️ Symfony-Mailer muss zur Laufzeit konfiguriert werden

Soll ich das implementieren?

## 📞 Hilfe

Bei Problemen:
1. `php bin/console debug:mailer` - Zeigt Mailer-Konfiguration
2. Logs prüfen: `var/log/dev.log` oder `var/log/prod.log`
3. SMTP-Credentials beim Provider prüfen
4. Firewall-Regeln prüfen (Ports 25, 465, 587)
