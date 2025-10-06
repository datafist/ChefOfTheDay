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
php bin/console doctrine:schema:create

echo ""
echo "📦 Test-Daten werden geladen..."
php bin/console doctrine:fixtures:load --no-interaction --group=large-scale

echo ""
echo "✅ Test-Daten erfolgreich geladen!"
echo ""
echo "📊 Übersicht:"
echo "   • 45 Familien für Jahr 24/25"
echo "   • 45 Familien für Jahr 25/26 (41 alt + 4 neu)"
echo "   • Kochplan für 24/25 bereits generiert"
echo "   • LastYearCooking Einträge erstellt"
echo "   • Verfügbarkeiten für beide Jahre"
echo ""
echo "🎯 Nächster Schritt:"
echo "   1. Server starten: symfony server:start"
echo "   2. Browser öffnen: http://127.0.0.1:8000/admin"
echo "   3. Login: admin@kita.local / admin123"
echo "   4. Plan für 25/26 generieren"
echo ""
