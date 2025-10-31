<?php
// Run migration to create super_admins and seed initial user
require_once __DIR__ . '/../db_connection.php';

header('Content-Type: application/json');

try {
    // 1) Create table
    $sql = file_get_contents(__DIR__ . '/003_create_super_admins.sql');
    $pdo->exec($sql);

    // 2) Seed initial superadmin if none exists
    $check = $pdo->prepare('SELECT COUNT(*) AS c FROM super_admins');
    $check->execute();
    $count = (int)($check->fetch()['c'] ?? 0);

    if ($count === 0) {
        $username = 'superadmin';
        $email = 'superadmin@example.com';
        $password = 'ChangeMe@123';
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $ins = $pdo->prepare('INSERT INTO super_admins (username, email, password_hash) VALUES (:u, :e, :p)');
        $ins->execute([':u' => $username, ':e' => $email, ':p' => $hash]);
        echo json_encode(['success' => true, 'message' => 'Migration created super_admins and seeded initial account', 'seed' => ['username' => $username, 'password' => $password]]);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Migration executed. super_admins already seeded.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>


