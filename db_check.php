<?php
require_once __DIR__ . '/config.php';
try {
    $username = 'testuser' . time();
    $password = 'password123';
    $fullName = 'Test User';
    $gender = 'Laki-laki';
    $phone = '08123456789';
    $email = 'test@example.com';
    $address = 'Test Address';
    $photoFilename = null;
    $selectedRoles = [1]; // Assume role 1 exists

    $hashedPass = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, gender, phone, email, address, photo, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
    
    if ($stmt->execute([$username, $hashedPass, $fullName, $gender, $phone, $email, $address, $photoFilename])) {
        $userId = $pdo->lastInsertId();
        
        $stmtRole = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
        foreach ($selectedRoles as $roleId) {
            $stmtRole->execute([$userId, $roleId]);
        }
        echo "SUCCESS! User ID: $userId\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

