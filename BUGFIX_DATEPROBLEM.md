# Bugfix: DateTimeImmutable Fehler

## Problem

**Fehlermeldung:**
```
DateTimeImmutable::createFromMutable(): Argument #1 ($object) must be of type DateTime, DateTimeImmutable given
in src/Service/CookingPlanGenerator.php (line 221)
```

**Auslöser:** Beim Generieren des Kochplans im Admin-Bereich.

## Ursache

In PHP gibt `DatePeriod` automatisch Objekte vom gleichen Typ wie das Start-Datum zurück:
- Wenn Start-Datum `DateTime` ist → gibt `DateTime` zurück
- Wenn Start-Datum `DateTimeImmutable` ist → gibt `DateTimeImmutable` zurück

Da `KitaYear::getStartDate()` ein `DateTimeImmutable` zurückgibt, erzeugt `DatePeriod` auch `DateTimeImmutable` Objekte. Der Code versuchte dann fälschlicherweise, `createFromMutable()` auf bereits immutable Objekte anzuwenden.

## Lösung

**Datei:** `src/Service/CookingPlanGenerator.php` (Zeile 217-224)

**Vorher:**
```php
$availableDays = [];
foreach ($period as $date) {
    $dateStr = $date->format('Y-m-d');
    if (!isset($excludedDates[$dateStr])) {
        $availableDays[] = \DateTimeImmutable::createFromMutable($date);
    }
}
```

**Nachher:**
```php
$availableDays = [];
foreach ($period as $date) {
    $dateStr = $date->format('Y-m-d');
    if (!isset($excludedDates[$dateStr])) {
        // DatePeriod gibt bereits DateTimeImmutable zurück wenn Start-Datum DateTimeImmutable ist
        if ($date instanceof \DateTimeImmutable) {
            $availableDays[] = $date;
        } else {
            $availableDays[] = \DateTimeImmutable::createFromMutable($date);
        }
    }
}
```

## Test

1. Als Admin einloggen: `admin@kita.local` / `admin123`
2. Zum Dashboard navigieren: `/admin`
3. Button "Kochplan generieren" klicken
4. ✅ Plan wird erfolgreich erstellt
5. ✅ E-Mails werden versendet (siehe Mailpit: http://localhost:56256)

## Status

✅ **Behoben** am 4. Oktober 2025
