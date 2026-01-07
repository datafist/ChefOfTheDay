# 🔒 Sicherheitshinweis - Git History Bereinigung

**Datum:** 07. Januar 2026  
**Durchgeführte Aktion:** Vollständige Git-History-Bereinigung

## ⚠️ Hintergrund

Die `.env` Datei mit produktiven Credentials war vom ersten Commit (06.10.2025) bis 06.01.2026 im Git-Repository committed und wurde erst dann aus dem Git-Index entfernt. Die sensiblen Daten waren jedoch weiterhin in der Git-History verfügbar.

### Kompromittierte Credentials (ALT - NICHT MEHR VERWENDEN)

Die folgenden Credentials waren in der Git-History sichtbar und wurden **als kompromittiert eingestuft**:

```
# ALTE WERTE (KOMPROMITTIERT):
APP_SECRET=d1fe1fb898462381e0b42d295d3960fd
MYSQL_ROOT_PASSWORD=root
MYSQL_PASSWORD=kochdienst
DATABASE_URL="mysql://kochdienst:kochdienst@..."
```

## ✅ Durchgeführte Maßnahmen

### 1. Git-History Bereinigung (07.01.2026)

```bash
# Backup-Tag erstellt
git tag backup-before-cleanup-20260107-193058

# .env aus kompletter Historie entfernt
git filter-branch --force --index-filter \
  "git rm --cached --ignore-unmatch .env" \
  --prune-empty --tag-name-filter cat -- --all

# Garbage Collection durchgeführt
git reflog expire --expire=now --all
git gc --prune=now --aggressive
```

**Ergebnis:** Die .env Datei und alle sensiblen Credentials wurden vollständig aus der Git-History entfernt.

### 2. Neue sichere Credentials generiert

Alle Credentials wurden durch kryptographisch sichere Zufallswerte ersetzt:

```bash
APP_SECRET=$(openssl rand -hex 32)
MYSQL_ROOT_PASSWORD=$(openssl rand -base64 24)
MYSQL_PASSWORD=$(openssl rand -base64 24)
```

### 3. Sicherheitswarnungen hinzugefügt

- README.md: Warnung zum Admin-Passwort `admin123`
- DataFixtures: Hinweis dass Demo-Passwort geändert werden muss
- .env: Kommentare mit Hinweis auf Security-Update

### 4. Persönliche Daten anonymisiert

- E-Mail-Adresse aus Beispiel-Dokumentation durch Platzhalter ersetzt
- Git-Commit-Autoren-Emails bleiben (Standard bei Git)

## 🚨 Wichtige Hinweise für Team-Mitglieder

### Falls Sie das Repository bereits gecloned haben:

**Ihr lokaler Clone enthält noch die alte, kompromittierte History!**

**WICHTIG - Bitte befolgen Sie diese Schritte:**

```bash
# 1. Sichern Sie Ihre lokalen Änderungen (falls vorhanden)
git stash

# 2. Löschen Sie Ihr lokales Repository
cd ..
rm -rf ChefOfTheDay

# 3. Clonen Sie das Repository neu
git clone <repository-url> ChefOfTheDay
cd ChefOfTheDay

# 4. Stellen Sie Ihre Änderungen wieder her
git stash pop  # falls in Schritt 1 gesichert
```

**ODER** (wenn Sie Force-Pull bevorzugen):

```bash
cd ChefOfTheDay
git fetch origin
git reset --hard origin/main
git clean -fdx
```

### Force-Push wurde durchgeführt

Die bereinigte History wurde mit `git push --force` auf den Remote-Server übertragen. Dies bedeutet:

- ✅ Die kompromittierten Credentials sind nicht mehr in der öffentlichen History
- ⚠️ Lokale Clones haben eine divergierende History
- 🔄 Alle Collaborators müssen das Repo neu clonen oder hard reset durchführen

## 🔐 Neue Sicherheits-Best-Practices

### 1. .env Datei

- ✅ `.env` ist jetzt in `.gitignore`
- ✅ `.env.example` enthält nur Platzhalter
- ⚠️ `.env` muss lokal erstellt werden (siehe README.md)

### 2. Produktions-Credentials

Für Production-Deployment (siehe DEPLOYMENT.md):

```bash
# Neue Credentials generieren
echo "APP_SECRET=$(openssl rand -hex 32)" >> .env
echo "MYSQL_ROOT_PASSWORD=$(openssl rand -base64 24)" >> .env
echo "MYSQL_PASSWORD=$(openssl rand -base64 24)" >> .env
```

### 3. Admin-Passwort

Das Demo-Passwort `admin123` (aus Fixtures) **muss** sofort nach dem ersten Login geändert werden:

```bash
php bin/console app:setup-admin
```

## 📋 Checkliste für neue Deployments

- [ ] Neue sichere Credentials in `.env` generieren
- [ ] Admin-Passwort nach erstem Login ändern
- [ ] SMTP-Credentials in `.env.local` eintragen (nicht committen!)
- [ ] Niemals `.env` oder `.env.local` in Git committen
- [ ] Bei jedem `git status` prüfen dass keine .env-Dateien staged sind

## 🔍 Verifikation

Um zu prüfen ob die Bereinigung erfolgreich war:

```bash
# Sollte KEINE Ergebnisse liefern:
git log --all --full-history -- .env

# Sollte KEINE Ergebnisse liefern:
git log --all -S "d1fe1fb898462381e0b42d295d3960fd"

# Prüfen ob .env ignoriert wird:
git check-ignore .env  # Sollte ".env" ausgeben
```

## 📞 Fragen?

Bei Fragen zur Security-Bereinigung wenden Sie sich an den Repository-Admin.

---

**Zusammenfassung:** Die alte Git-History mit sensiblen Credentials wurde vollständig entfernt. Alle Credentials wurden rotiert. Das Repository ist jetzt sicher für die weitere Verwendung.
