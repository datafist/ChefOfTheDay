# Absender-E-Mail-Adresse konfigurieren

## Problem

GMX, Gmail und die meisten E-Mail-Provider erlauben nur, **E-Mails von der eigenen Adresse** zu versenden. Wenn Sie versuchen, mit einer anderen Absender-Adresse zu senden, erhalten Sie einen Fehler:

```
550 Sender address is not allowed
```

## Lösung

Die Absender-Adresse muss **mit Ihrem SMTP-Login übereinstimmen**.

### Konfiguration

**Datei:** `config/packages/mailer.yaml`

```yaml
parameters:
    mailer.from_email: 'kopfnicker@gmx.net'  # Ihre echte E-Mail!
    mailer.from_name: 'Kita Kochdienst'
```

### Für Entwicklung/Tests

Verwenden Sie Ihre **persönliche E-Mail-Adresse**:

```yaml
# Entwicklung mit GMX
mailer.from_email: 'ihre-email@gmx.net'
mailer.from_name: 'Kita Test'
```

### Für Produktion

Verwenden Sie die **offizielle Kita-E-Mail**:

```yaml
# Produktion mit eigener Domain
mailer.from_email: 'kita@sonnenschein-kita.de'
mailer.from_name: 'Kita Sonnenschein'
```

**Wichtig:** Der `MAILER_DSN` muss dann auch mit dieser Adresse konfiguriert sein!

```bash
# In .env.local:
MAILER_DSN=smtp://kita@sonnenschein-kita.de:passwort@smtp.ihre-domain.de:587?encryption=tls
```

## Wo wird die Absender-Adresse verwendet?

Die Konfiguration aus `mailer.yaml` wird automatisch in allen Services verwendet:

1. **NotificationService** - Kochplan-Benachrichtigungen
2. **NotificationService** - Erinnerungen
3. **EmailTestController** - Test-E-Mails (Web-Interface)
4. **TestEmailCommand** - Test-E-Mails (CLI)

## Verschiedene Absender für Test und Produktion

### Option 1: Manuelle Änderung

**Entwicklung:**
```yaml
mailer.from_email: 'entwickler@gmx.net'
```

**Produktion:**
```yaml
mailer.from_email: 'kita@kita-domain.de'
```

### Option 2: Umgebungsvariable (empfohlen)

**mailer.yaml:**
```yaml
parameters:
    mailer.from_email: '%env(MAILER_FROM_EMAIL)%'
    mailer.from_name: '%env(MAILER_FROM_NAME)%'
```

**.env.local (Entwicklung):**
```bash
MAILER_FROM_EMAIL=entwickler@gmx.net
MAILER_FROM_NAME=Kita Test
```

**.env.local (Produktion):**
```bash
MAILER_FROM_EMAIL=kita@kita-domain.de
MAILER_FROM_NAME=Kita Sonnenschein
```

## Häufige Fehler

### Fehler 1: "550 Sender address is not allowed"

**Ursache:** Absender-Adresse stimmt nicht mit SMTP-Login überein

**Lösung:**
```yaml
# mailer.yaml
mailer.from_email: 'kopfnicker@gmx.net'  # ← Muss mit MAILER_DSN übereinstimmen!
```

```bash
# .env.local
MAILER_DSN=smtp://kopfnicker@gmx.net:passwort@smtp.gmx.net:587  # ← Gleiche Adresse!
```

### Fehler 2: "553 Relaying denied"

**Ursache:** Sie versuchen, über einen SMTP-Server zu senden, der Ihre Domain nicht verwaltet

**Lösung:** Verwenden Sie den SMTP-Server Ihrer eigenen Domain oder E-Mail-Provider

### Fehler 3: E-Mails landen im Spam

**Ursache:** Absender-Domain hat keine SPF/DKIM-Einträge

**Lösung (Produktion):**
1. Eigene Domain verwenden (z.B. `kita@ihre-kita.de`)
2. SPF-Record im DNS setzen
3. DKIM aktivieren
4. DMARC konfigurieren

**Für Entwicklung:** Akzeptabel, dass Test-E-Mails im Spam landen

## Checkliste

- [ ] `mailer.from_email` in `config/packages/mailer.yaml` gesetzt
- [ ] Absender-Adresse stimmt mit `MAILER_DSN` überein
- [ ] Test-E-Mail erfolgreich versendet
- [ ] E-Mail kommt an (auch Spam-Ordner prüfen!)
- [ ] Für Produktion: Eigene Domain konfiguriert

## Beispiel-Workflow

### Entwicklung mit GMX

**1. mailer.yaml:**
```yaml
parameters:
    mailer.from_email: 'entwickler@gmx.net'
    mailer.from_name: 'Kita Test'
```

**2. .env.local:**
```bash
MAILER_DSN=smtp://entwickler@gmx.net:passwort@smtp.gmx.net:587?encryption=tls
```

**3. Test:**
```bash
php bin/console app:test-email test@example.com
```

### Produktion mit eigener Domain

**1. mailer.yaml:**
```yaml
parameters:
    mailer.from_email: 'noreply@kita-sonnenschein.de'
    mailer.from_name: 'Kita Sonnenschein'
```

**2. .env.local:**
```bash
MAILER_DSN=smtp://noreply@kita-sonnenschein.de:passwort@smtp.ihre-domain.de:587?encryption=tls
```

**3. DNS konfigurieren:**
- SPF-Record: `v=spf1 a mx ip4:SERVER_IP ~all`
- DKIM aktivieren beim Provider
- DMARC: `v=DMARC1; p=none; rua=mailto:admin@kita-sonnenschein.de`

**4. Test:**
```bash
php bin/console app:test-email familie@example.com
```

---

*Aktualisiert: 6. Oktober 2025*
