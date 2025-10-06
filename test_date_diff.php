#!/usr/bin/env php
<?php

// Test: DateInterval diff calculation
$lastDate = new DateTimeImmutable('2024-08-31');
$newDate = new DateTimeImmutable('2024-09-01');

$diff = $lastDate->diff($newDate);
echo "Von {$lastDate->format('Y-m-d')} bis {$newDate->format('Y-m-d')}:\n";
echo "Tage: {$diff->days}\n";
echo "6 Wochen = " . (6 * 7) . " Tage\n";
echo "4 Wochen = " . (4 * 7) . " Tage\n";
echo "\n";

if ($diff->days >= 42) {
    echo "✅ >= 6 Wochen (Target)\n";
} elseif ($diff->days >= 28) {
    echo "⚠️ >= 4 Wochen (Minimum)\n";
} else {
    echo "❌ < 4 Wochen (Blockiert)\n";
}

echo "\n\nTest für 6 Wochen später:\n";
$laterDate = new DateTimeImmutable('2024-10-12');
$diff2 = $lastDate->diff($laterDate);
echo "Von {$lastDate->format('Y-m-d')} bis {$laterDate->format('Y-m-d')}:\n";
echo "Tage: {$diff2->days}\n";

if ($diff2->days >= 42) {
    echo "✅ >= 6 Wochen (Target)\n";
} elseif ($diff2->days >= 28) {
    echo "⚠️ >= 4 Wochen (Minimum)\n";
} else {
    echo "❌ < 4 Wochen (Blockiert)\n";
}
