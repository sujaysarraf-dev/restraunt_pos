<?php
// Suppress error display for CLI
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (!headers_sent()) {
  header('Content-Type: application/json');
}

require_once __DIR__ . '/../db_connection.php';

try {
  $sql = file_get_contents(__DIR__ . '/008_create_website_banners_table.sql');
  $pdo->exec($sql);
  echo json_encode(['success' => true, 'message' => 'Website banners table created successfully']);
} catch (Exception $e) {
  if (!headers_sent()) {
    http_response_code(500);
  }
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

