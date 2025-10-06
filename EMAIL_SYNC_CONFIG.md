# E-Mail Synchrone Konfiguration

## Übersicht

Die Anwendung wurde für **sofortigen, synchronen E-Mail-Versand** konfiguriert. Es ist **kein Messenger Worker** und **kein Cronjob** erforderlich.

## ✅ Was funktioniert

### 1. E-Mail-Test (Admin → E-Mail-Test)
- Sendet Test-Email direkt über GMX SMTP
- Sofortige Rückmeldung über Erfolg/Fehler
- Prüft SMTP-Konfiguration

### 2. Kochplan-Benachrichtigungen
Emails werden **MANUELL** über einen Button im Admin-Dashboard versendet:

1. Admin-Dashboard öffnen
2. Kochplan generieren
3. Button **"📧 E-Mails versenden"** klicken
4. Bestätigung: "Möchten Sie wirklich E-Mail-Benachrichtigungen an alle Familien versenden?"

- **Versand**: Nur wenn explizit auf Button geklickt wird
- **Empfänger**: Jede Familie erhält EINE E-Mail mit allen ihren Terminen
- **Kein Worker erforderlich**: Dank `messenger.yaml` Routing auf `sync`

## 🔧 Konfiguration

### 1. SMTP-Zugangsdaten (`.env.local`)
```bash
MAILER_DSN=smtp://kopfnicker@gmx.net:passwort@smtp.gmx.net:587?encryption=tls
```

### 2. Absender-Konfiguration (`config/packages/mailer.yaml`)
```yaml
parameters:
    mailer.from_email: 'kopfnicker@gmx.net'
    mailer.from_name: 'Kita Kochdienst'
```

⚠️ **Wichtig**: Bei GMX/GMX muss die Absender-Email mit der SMTP-Login-Email übereinstimmen!

### 3. Messenger-Routing (`config/packages/messenger.yaml`)
```yaml
routing:
    # Emails werden SYNCHRON versendet (kein Worker erforderlich)
    Symfony\Component\Mailer\Messenger\SendEmailMessage: sync
```

## ❌ Was NICHT verwendet wird

### Keine Mailpit (Docker)
- Mailpit-Container wurde entfernt
- Alle Emails gehen direkt an echte SMTP-Server (GMX)

### Keine Erinnerungs-Cronjobs
- Feature `app:send-reminders` existiert noch im Code
- Wird aber **nicht aktiv genutzt** (kein Cronjob eingerichtet)
- Falls später gewünscht: Manueller Aufruf via CLI möglich

### Kein Messenger Worker
- Früher: Emails landeten in Queue, Worker verarbeitet asynchron
- Jetzt: Sofortiger Versand beim Generieren des Plans
- **Vorteil**: Einfacher, keine Hintergrundprozesse nötig
- **Nachteil**: Admin wartet paar Sekunden beim Generieren (bei 20 Familien ca. 5-10 Sekunden)

## 🧪 Testen

### 1. Test-Email senden
**Admin-UI**: http://127.0.0.1:8000/admin/email-test

**CLI**:
```bash
php bin/console app:test-email ihre-email@example.com
```

### 2. Kochplan generieren und Benachrichtigungen senden
1. Admin-Dashboard öffnen
2. "Plan generieren" klicken → Kochplan wird erstellt
3. **"📧 E-Mails versenden"** klicken → Benachrichtigungen werden versendet
4. ⚠️ **ACHTUNG**: Alle Familien mit E-Mail-Adresse erhalten dann SOFORT eine E-Mail!

### 3. Demo-Modus testen
Um ohne echte Emails zu testen:

1. Temporär `MAILER_DSN=null://null` in `.env.local` setzen
2. Cache leeren: `php bin/console cache:clear`
3. Server neu starten: `symfony server:stop && symfony server:start -d`
4. Kochplan generieren (Emails werden "versendet" aber nicht ausgeliefert)
5. GMX-Credentials wieder aktivieren

## 🔍 Fehlersuche

### Problem: "Email '127.0.0.1' does not comply with RFC 2822"
- **Ursache**: Mailpit-Container läuft noch und fängt Emails ab
- **Lösung**: 
  ```bash
  docker stop chefoftheday_symfony_mysql-mailer-1
  docker rm chefoftheday_symfony_mysql-mailer-1
  ```

### Problem: "Authentication failed (535)"
- **Ursache**: Falsche SMTP-Credentials oder POP3/SMTP nicht aktiviert
- **Lösung**: 
  - GMX-Einstellungen prüfen: POP3/SMTP aktivieren
  - Passwort in `.env.local` prüfen

### Problem: "Connection timeout"
- **Ursache**: Falscher SMTP-Server oder Port
- **Lösung**: GMX = `smtp.gmx.net:587` (nicht `mail.gmx.net`!)

### Problem: Emails werden automatisch versendet
- **Ursache**: Alte Konfiguration hatte automatischen Versand beim Generieren
- **Lösung**: Aktuell wird NICHT automatisch versendet - nur über Button "📧 E-Mails versenden"

### Problem: Emails werden als Spam markiert
- **Ursache**: GMX als Absender kann von einigen Providern als Spam eingestuft werden
- **Lösung**:
  - Empfänger sollen Absender als "Kein Spam" markieren
  - Eigene Domain mit SPF/DKIM verwenden (siehe Produktions-Setup)

## 🚀 Produktions-Setup (Optional)

Für professionellen E-Mail-Versand:

1. **Eigene Domain verwenden**:
   ```bash
   MAILER_DSN=smtp://noreply@ihre-kita-domain.de:passwort@mail.ihre-domain.de:587?encryption=tls
   ```

2. **SPF/DKIM konfigurieren** (bei Domain-Provider)

3. **Monitoring einrichten**: Log-Analyse für fehlgeschlagene Emails

4. **Optional: Async-Queue mit Worker** (bei sehr vielen Familien):
   - `messenger.yaml`: Routing zurück auf `async`
   - Systemd-Service für Worker einrichten
   - Supervisor oder PM2 für Prozess-Management

## 📝 Änderungshistorie

### 2025-10-06: Synchroner Versand aktiviert
- Mailpit-Container entfernt
- `.env` bereinigt (MAILER_DSN auskommentiert)
- Messenger-Routing auf `sync` umgestellt
- Autowired Parameter für Absender-Konfiguration
- Test-Email-Controller mit echtem SMTP
- **Manueller Versand**: Button "📧 E-Mails versenden" im Admin-Dashboard (NICHT automatisch beim Generieren)

**Vorher**: Emails landeten in Queue → niemals versendet (kein Worker)  
**Nachher**: Emails werden sofort versendet (bei Button-Klick) → funktioniert zuverlässig
