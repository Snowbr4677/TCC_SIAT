<?php

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

ini_set('default_charset', 'UTF-8');

$dbHost = getenv('DB_HOST') ?: $_ENV['DB_HOST'] ?? 'localhost';
$dbUser = getenv('DB_USER') ?: $_ENV['DB_USER'] ?? 'root';
$dbPass = getenv('DB_PASSWORD') ?: $_ENV['DB_PASSWORD'] ?? '';
$dbName = getenv('DB_NAME') ?: $_ENV['DB_NAME'] ?? 'assistencia_tecnica';

$conexao = mysqli_connect($dbHost, $dbUser, $dbPass, $dbName);

if (!$conexao) {
    $mensagemErroConexao = "Erro na conexão com o banco de dados: " . mysqli_connect_error();
} else {
    $mensagemErroConexao = null;
    mysqli_set_charset($conexao, "utf8mb4");

    $resultadoColuna = $conexao->query("SHOW COLUMNS FROM usuarios LIKE 'foto_perfil'");
    if ($resultadoColuna && $resultadoColuna->num_rows === 0) {
        $conexao->query("ALTER TABLE usuarios ADD COLUMN foto_perfil VARCHAR(255) NOT NULL DEFAULT 'default.svg' AFTER senha");
    }

    $resultadoPerfil = $conexao->query("SHOW COLUMNS FROM usuarios LIKE 'perfil'");
    if ($resultadoPerfil && $resultadoPerfil->num_rows === 0) {
        $conexao->query("ALTER TABLE usuarios ADD COLUMN perfil VARCHAR(20) NOT NULL DEFAULT 'admin' AFTER foto_perfil");
    }

    $resultadoStatus = $conexao->query("SHOW COLUMNS FROM usuarios LIKE 'status'");
    if ($resultadoStatus && $resultadoStatus->num_rows === 0) {
        $conexao->query("ALTER TABLE usuarios ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'ativo' AFTER perfil");
    }

    $resultadoCriadoPor = $conexao->query("SHOW COLUMNS FROM usuarios LIKE 'criado_por'");
    if ($resultadoCriadoPor && $resultadoCriadoPor->num_rows === 0) {
        $conexao->query("ALTER TABLE usuarios ADD COLUMN criado_por INT NULL DEFAULT NULL AFTER status");
    }

    $resultadoLogin = $conexao->query("SHOW COLUMNS FROM usuarios LIKE 'login'");
    if ($resultadoLogin && $resultadoLogin->num_rows === 0) {
        $conexao->query("ALTER TABLE usuarios ADD COLUMN login VARCHAR(100) NULL AFTER email");
    }

    $resultadoLoginAtualizacao = $conexao->query("SELECT id, email, login FROM usuarios WHERE login IS NULL OR TRIM(login) = ''");
    if ($resultadoLoginAtualizacao && $resultadoLoginAtualizacao->num_rows > 0) {
        while ($usuario = $resultadoLoginAtualizacao->fetch_assoc()) {
            $loginPadrao = preg_replace('/[^a-zA-Z0-9._-]/', '', strtolower(strstr($usuario['email'], '@', true) ?: $usuario['email']));
            $loginPadrao = $loginPadrao !== '' ? $loginPadrao : 'usuario_' . (int)$usuario['id'];
            $loginFinal = $loginPadrao;
            $contador = 1;
            while (true) {
                $verifica = $conexao->query("SELECT id FROM usuarios WHERE login = '" . $conexao->real_escape_string($loginFinal) . "' AND id <> " . (int)$usuario['id'] . " LIMIT 1");
                if (!$verifica || $verifica->num_rows === 0) {
                    break;
                }
                $loginFinal = $loginPadrao . $contador;
                $contador++;
            }
            $conexao->query("UPDATE usuarios SET login = '" . $conexao->real_escape_string($loginFinal) . "' WHERE id = " . (int)$usuario['id']);
        }
    }

    $resultadoLoginUnico = $conexao->query("SELECT COUNT(*) as total FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'login'");
    if ($resultadoLoginUnico && $resultadoLoginUnico->fetch_assoc()['total'] > 0) {
       // $conexao->query("CREATE UNIQUE INDEX IF NOT EXISTS idx_usuarios_login ON usuarios(login)");
    }

    $conexao->query("CREATE TABLE IF NOT EXISTS produtos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        codigo VARCHAR(50) NULL,
        nome VARCHAR(255) NOT NULL,
        categoria VARCHAR(100) NOT NULL,
        quantidade INT NOT NULL DEFAULT 0,
        valor_custo DECIMAL(10,2) NOT NULL DEFAULT 0,
        valor_venda DECIMAL(10,2) NOT NULL DEFAULT 0,
        data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
        observacao TEXT NULL
    )");

    $conexao->query("CREATE TABLE IF NOT EXISTS vendas_marketplace (
        id INT AUTO_INCREMENT PRIMARY KEY,
        marketplace VARCHAR(100) NOT NULL,
        produto_id INT NOT NULL,
        quantidade INT NOT NULL DEFAULT 1,
        valor_venda DECIMAL(10,2) NOT NULL DEFAULT 0,
        data_venda DATETIME DEFAULT CURRENT_TIMESTAMP,
        cliente VARCHAR(150) NULL,
        observacao TEXT NULL,
        CONSTRAINT fk_venda_produto FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE
    )");

    $conexao->query("CREATE TABLE IF NOT EXISTS backup_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome_arquivo VARCHAR(255) NOT NULL,
        data_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
        tipo VARCHAR(50) DEFAULT 'manual'
    )");

    $conexao->query("CREATE TABLE IF NOT EXISTS configuracoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        chave VARCHAR(100) NOT NULL UNIQUE,
        valor TEXT NULL,
        atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    $conexao->query("CREATE TABLE IF NOT EXISTS auditoria (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        acao VARCHAR(100) NOT NULL,
        modulo VARCHAR(100) NOT NULL,
        detalhes TEXT NULL,
        ip_usuario VARCHAR(45) NULL,
        data_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_auditoria_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
    )");

    $resultadoAdminPadrao = $conexao->query("SELECT id, senha FROM usuarios WHERE email = 'admin@assistencia.local' LIMIT 1");
    if ($resultadoAdminPadrao && $resultadoAdminPadrao->num_rows > 0) {
        $admin = $resultadoAdminPadrao->fetch_assoc();
        if (!password_verify('admin123', $admin['senha'] ?? '')) {
            $hashAdmin = password_hash('admin123', PASSWORD_DEFAULT);
            $updateAdmin = $conexao->prepare("UPDATE usuarios SET senha = ?, perfil = 'admin', status = 'ativo' WHERE email = 'admin@assistencia.local'");
            $updateAdmin->bind_param('s', $hashAdmin);
            $updateAdmin->execute();
        }
    } else {
        $hashAdmin = password_hash('admin123', PASSWORD_DEFAULT);
        $insertAdmin = $conexao->prepare("INSERT INTO usuarios (nome, email, senha, foto_perfil, perfil, status) VALUES (?, ?, ?, 'default.svg', 'admin', 'ativo')");
        $nomeAdmin = 'Administrador';
        $emailAdmin = 'admin@assistencia.local';
        $insertAdmin->bind_param('sss', $nomeAdmin, $emailAdmin, $hashAdmin);
        $insertAdmin->execute();
    }

    $resultadoFuncionarioPadrao = $conexao->query("SELECT id FROM usuarios WHERE email = 'funcionario@assistencia.local' LIMIT 1");
    if ($resultadoFuncionarioPadrao && $resultadoFuncionarioPadrao->num_rows === 0) {
        $hashFuncionario = password_hash('funcionario123', PASSWORD_DEFAULT);
        $insertFuncionario = $conexao->prepare("INSERT INTO usuarios (nome, email, senha, foto_perfil, perfil, status) VALUES (?, ?, ?, 'default.svg', 'funcionario', 'ativo')");
        $nomeFuncionario = 'Funcionário';
        $emailFuncionario = 'funcionario@assistencia.local';
        $insertFuncionario->bind_param('sss', $nomeFuncionario, $emailFuncionario, $hashFuncionario);
        $insertFuncionario->execute();
    }
}

function perfilUsuarioAtual(): string {
    return strtolower($_SESSION['usuario_perfil'] ?? 'admin');
}

function statusUsuarioAtual(): string {
    return strtolower($_SESSION['usuario_status'] ?? 'ativo');
}

function usuarioEhAdmin(): bool {
    return perfilUsuarioAtual() === 'admin' && statusUsuarioAtual() === 'ativo';
}

function usuarioEhFuncionario(): bool {
    return perfilUsuarioAtual() === 'funcionario' && statusUsuarioAtual() === 'ativo';
}

function exigirLogin(): void {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: login.php');
        exit;
    }

    if (statusUsuarioAtual() !== 'ativo') {
        session_destroy();
        header('Location: login.php?erro=usuario_inativo');
        exit;
    }
}

function exigirPerfilAdmin(): void {
    exigirLogin();
    if (!usuarioEhAdmin()) {
        $_SESSION['erro_acesso'] = 'Acesso negado. Apenas administradores podem acessar esta área.';
        header('Location: painel.php');
        exit;
    }
}

function exigirAcessoOperacional(): void {
    exigirLogin();
    if (!in_array(perfilUsuarioAtual(), ['admin', 'funcionario'], true)) {
        $_SESSION['erro_acesso'] = 'Perfil inválido.';
        header('Location: login.php');
        exit;
    }
}

function exigirPerfilOperacional(): void {
    exigirLogin();
    $perfil = perfilUsuarioAtual();
    if (!in_array($perfil, ['admin', 'funcionario'], true)) {
        $_SESSION['erro_acesso'] = 'Perfil inválido.';
        header('Location: login.php');
        exit;
    }
}

function registrarAuditoria(mysqli $conexao, int $usuarioId, string $acao, string $modulo, string $detalhes = ''): void {
    if ($usuarioId <= 0) {
        return;
    }

    $stmt = $conexao->prepare("INSERT INTO auditoria (usuario_id, acao, modulo, detalhes, ip_usuario, data_hora) VALUES (?, ?, ?, ?, ?, NOW())");
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $stmt->bind_param('issss', $usuarioId, $acao, $modulo, $detalhes, $ip);
    $stmt->execute();
}

?>
