# Cron & Scheduler Automation

## Übersicht

ChefOfTheDay verwendet automatisierte Aufgaben für:

| Job | Frequenz | Command | Beschreibung |
|-----|----------|---------|--------------|
| **Erinnerungen** | Täglich Mo-Fr 8:00 | `app:send-reminders 3` | E-Mail an Familien mit Kochdienst in 3 Tagen |
| **Feiertage** | Jährlich 1. Juli | `app:generate-holidays --force` | Feiertage für neue Kita-Jahre generieren |
| **Jahresübergang** | Jährlich 1. August | `app:create-last-year-cooking` | Kochzählungen für Fairness im Folgejahr sichern |

## Setup

### Option A: Crontab (empfohlen für einfache Setups)

```bash
# Crontab des Web-Users bearbeiten
sudo -u www-data crontab -e

# Einträge einfügen (Pfade anpassen!):
PROJECT_DIR=/pfad/zu/ChefOfTheDay

0 8 * * 1-5  cd $PROJECT_DIR && bin/cron-wrapper.sh app:send-reminders 3
0 6 1 7 *    cd $PROJECT_DIR && bin/cron-wrapper.sh app:generate-holidays --force
0 6 1 8 *    cd $PROJECT_DIR && bin/cron-wrapper.sh app:create-last-year-cooking
```

Die vollständige Crontab-Vorlage liegt unter `config/crontab`.

### Option B: Systemd Timer (empfohlen für Produktionsserver)

```bash
# Service- und Timer-Dateien kopieren
sudo cp config/systemd/*.service /etc/systemd/system/
sudo cp config/systemd/*.timer /etc/systemd/system/

# Pfade in den .service-Dateien anpassen:
sudo nano /etc/systemd/system/chefoftheday-reminders.service
# → WorkingDirectory und ExecStart anpassen
# → User auf den Web-User setzen (www-data, nginx, etc.)

# Timer aktivieren und starten
sudo systemctl daemon-reload
sudo systemctl enable --now chefoftheday-reminders.timer
sudo systemctl enable --now chefoftheday-holidays.timer
sudo systemctl enable --now chefoftheday-year-transition.timer

# Status prüfen
sudo systemctl list-timers | grep chefoftheday
```

## Cron-Wrapper

Der Wrapper-Script `bin/cron-wrapper.sh` bietet:

- **Lock-Mechanismus**: Verhindert parallele Ausführung desselben Commands
- **Logging**: Alle Ausführungen werden in `var/log/cron.log` protokolliert
- **Fehlerbehandlung**: Exit-Codes werden weitergeleitet und geloggt
- **Automatische Cleanup**: Lock-Files werden bei Beendigung entfernt

### Manuelle Ausführung testen

```bash
# Reminder trocken testen
php bin/console app:send-reminders 3 --env=prod --no-interaction

# Über den Wrapper (wie Cron es tut)
bin/cron-wrapper.sh app:send-reminders 3

# Log prüfen
tail -f var/log/cron.log
```

## Monitoring

### Cron-Log prüfen

```bash
# Letzte Einträge
tail -20 var/log/cron.log

# Nur Fehler
grep "FEHLER" var/log/cron.log

# Alle Reminder-Läufe
grep "send-reminders" var/log/cron.log
```

### Systemd Timer Status

```bash
# Alle Timer anzeigen
systemctl list-timers --all | grep chefoftheday

# Letzten Lauf eines Services prüfen
journalctl -u chefoftheday-reminders.service --since today

# Timer-Logs
journalctl -u chefoftheday-reminders.timer --since "1 week ago"
```

## Zeitplan-Logik

### Erinnerungen (täglich)
- Läuft Mo-Fr um 8:00
- Sendet E-Mails an alle Familien mit Kochdienst in den nächsten N Tagen
- Standard: 3 Tage Vorlauf
- Idempotent: Mehrfachausführung sendet keine Doppel-Mails (geprüft per Datum)

### Feiertage (jährlich, Juli)
- Generiert BW-Feiertage für alle Kita-Jahre ohne Feiertage
- `--force` überschreibt existierende (z.B. nach Gesetzesänderung)
- Sollte VOR dem Verfügbarkeits-Zeitraum laufen (Familien sehen keine Feiertage als Termine)

### Jahresübergang (jährlich, August)
- Erstellt `LastYearCooking`-Einträge aus den Zuweisungen des aktiven Jahres
- Ermöglicht faire Verteilung über Jahresgrenzen hinweg
- Bereinigt verwaiste Einträge (Familien die nicht mehr teilnehmen)
- Idempotent: Aktualisiert existierende Einträge statt Duplikate zu erzeugen

## Troubleshooting

| Problem | Lösung |
|---------|--------|
| Keine E-Mails gesendet | `MAILER_DSN` in `.env.local` prüfen, `app:test-email` nutzen |
| Lock-File blockiert | `rm var/lock/app_send-reminders.lock` |
| Cron läuft nicht | `grep CRON /var/log/syslog` prüfen |
| Falscher PHP-Pfad | `which php` prüfen, ggf. in Crontab `PATH` setzen |
| Permission-Fehler | Cron unter dem gleichen User wie der Webserver ausführen |
