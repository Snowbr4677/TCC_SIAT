<?php
session_start();

require __DIR__ . "/conexao/conexao.php";

exigirPerfilAdmin();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

$dbHost = getenv('DB_HOST') ?: $_ENV['DB_HOST'] ?? 'localhost';
$dbUser = getenv('DB_USER') ?: $_ENV['DB_USER'] ?? 'root';
$dbPass = getenv('DB_PASSWORD') ?: $_ENV['DB_PASSWORD'] ?? '';
$dbName = getenv('DB_NAME') ?: $_ENV['DB_NAME'] ?? 'assistencia_tecnica';

$timestamp = date('Y-m-d_H-i-s');
$arquivo = "backup_{$dbName}_{$timestamp}.sql";
$path = __DIR__ . '/backups/' . $arquivo;

if (!is_dir(__DIR__ . '/backups')) {
    mkdir(__DIR__ . '/backups', 0777, true);
}

$command = sprintf('mysqldump --host=%s --user=%s --password=%s %s > "%s"',
    escapeshellarg($dbHost),
    escapeshellarg($dbUser),
    escapeshellarg($dbPass),
    escapeshellarg($dbName),
    str_replace('"', '\"', $path)
);

system($command, $retorno);

if ($retorno === 0 && file_exists($path)) {
    $conexao->query("INSERT INTO backup_log (nome_arquivo, data_hora, tipo) VALUES ('" . $conexao->real_escape_string($arquivo) . "', NOW(), 'manual')");
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($path) . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

$_SESSION['backup_error'] = 'Não foi possível gerar o backup do banco de dados.';
header('Location: painel.php');
exit;
