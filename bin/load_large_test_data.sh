#!/bin/bash

echo ""
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║  Umfangreiche Test-Daten laden (45 Familien)                ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo ""

echo "⚠️  WARNUNG: Dies löscht alle bestehenden Daten!"
echo ""
read -p "Fortfahren? (j/N) " -n 1 -r
echo ""

if [[ ! $REPLY =~ ^[Jj]$ ]]; then
    echo "Abgebrochen."
    exit 1
fi

echo ""
echo "🗄️  Datenbank wird zurückgesetzt..."
php bin/console doctrine:database:drop --force --if-exists
php bin/console doctrine:database:create

echo ""
echo "📋 Schema wird direkt aus Entities erstellt (umgeht Migration-Probleme)..."
php bin/console doctrine:schema:create

echo ""
echo "👤 Admin-User wird erstellt..."
php bin/console app:setup-admin admin admin123 --no-interaction

echo ""
echo "📦 Test-Daten werden geladen..."
php bin/console doctrine:fixtures:load --no-interaction --group=large-scale --append

echo ""
echo "✅ Test-Daten erfolgreich geladen!"
echo ""
echo "📊 Übersicht:"
echo "   • Admin-User: admin / admin123"
echo "     ⚠️  WICHTIG: Passwort nach erstem Login ändern!"
echo "     Befehl: php bin/console app:setup-admin"
echo "   • 45 Familien für Jahr 24/25"
echo "   • 45 Familien für Jahr 25/26 (41 alt + 4 neu)"
echo "   • Kochplan für 24/25 bereits generiert"
echo "   • LastYearCooking Einträge erstellt"
echo "   • Verfügbarkeiten für beide Jahre"
echo ""
echo "🎯 Login-Informationen:"
echo "   Admin-Login:"
echo "   • URL: http://127.0.0.1:8000/login"
echo "   • Username: admin"
echo "   • Passwort: admin123"
echo ""
echo "   Familien-Login:"
echo "   • URL: http://127.0.0.1:8000/family/login"
echo "   • Familie auswählen im Dropdown"
echo "   • Zugangscode eingeben (in Admin unter 'Familien' einsehbar)"
echo ""
echo "🚀 Server starten:"
echo "   symfony server:start"
echo "   http://127.0.0.1:8000"
echo ""