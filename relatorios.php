<?php
session_start();
require __DIR__ . '/conexao/conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

if (perfilUsuarioAtual() !== 'admin') {
    header('Location: painel.php');
    exit;
}

header('Location: relatorios_financeiros.php');
exit;
