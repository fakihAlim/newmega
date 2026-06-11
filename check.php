<?php
$pdo = new PDO('mysql:host=localhost;dbname=procurementDB', 'root', '');
$stmt = $pdo->query("SELECT id, abbreviation FROM customers LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
