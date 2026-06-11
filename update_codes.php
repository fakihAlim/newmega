<?php
require __DIR__ . '/config.php';

$stmt = $pdo->query("SELECT id, abbreviation FROM customers ORDER BY id ASC");
$customers = $stmt->fetchAll();

$counters = [];
$updated = 0;

foreach ($customers as $c) {
    $currentAbbr = $c['abbreviation'];
    
    if (strpos($currentAbbr, '-') !== false) {
        $parts = explode('-', $currentAbbr);
        $base = strtoupper(substr($parts[0], 0, 3));
    } else {
        $base = strtoupper(substr($currentAbbr, 0, 3));
    }
    
    if (empty($base)) {
        $base = 'CUS';
    }

    if (!isset($counters[$base])) {
        $counters[$base] = 1;
    } else {
        $counters[$base]++;
    }

    $newAbbr = $base . '-' . str_pad($counters[$base], 3, '0', STR_PAD_LEFT);

    $upd = $pdo->prepare("UPDATE customers SET abbreviation = ? WHERE id = ?");
    $upd->execute([$newAbbr, $c['id']]);
    $updated++;
}

echo "Berhasil memperbarui $updated customer dengan format kode urut (contoh: PJA-001).";
