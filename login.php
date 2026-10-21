<?php
session_start();
require __DIR__ . "/conexao/conexao.php";

$erro = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = isset($_POST["email"]) ? trim($_POST["email"]) : "";
    $senha = isset($_POST["senha"]) ? $_POST["senha"] : "";

    if ($email === "" || $senha === "") {
        $erro = "Preencha e-mail e senha.";
    } else {
        $stmt = $conexao->prepare("SELECT id, nome, email, senha, foto_perfil, perfil, status FROM usuarios WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado && $resultado->num_rows === 1) {
            $dadosUsuario = $resultado->fetch_assoc();
            $senhaValida = password_verify($senha, $dadosUsuario["senha"]) || $senha === $dadosUsuario["senha"];

            if ($senhaValida) {
                if (strtolower($dadosUsuario["status"] ?? 'ativo') !== 'ativo') {
                    $erro = "Usuário inativo. Entre em contato com o administrador.";
                } else {
                    $_SESSION["usuario_id"] = (int)$dadosUsuario["id"];
                    $_SESSION["usuario_nome"] = $dadosUsuario["nome"];
                    $_SESSION["usuario_login"] = $dadosUsuario["email"];
                    $_SESSION["usuario_foto"] = $dadosUsuario["foto_perfil"] ?? "default.svg";
                    $_SESSION["usuario_perfil"] = strtolower($dadosUsuario["perfil"] ?? 'admin');
                    $_SESSION["usuario_status"] = strtolower($dadosUsuario["status"] ?? 'ativo');

                    registrarAuditoria($conexao, (int)$dadosUsuario["id"], 'login', 'acesso', 'Usuário logou no sistema');

                    header("Location: painel.php");
                    exit;
                }
            } else {
                $erro = "Senha incorreta.";
            }
        } else {
            $erro = "E-mail não encontrado.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="css/estilo.css?v=20260909">
</head>
<body class="login-page">
    <header>
        <div class="container">
            <div class="brand">Assistência Técnica</div>
            <nav class="nav">
                <a href="index.php">Início</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <div class="card">
            <h2 class="page-title" style="margin-bottom: 10px;">Acesse sua conta</h2>
            <p class="login-subtitle">Gerencie sua assistência com praticidade e segurança.</p>

            <?php if ($erro !== "") : ?>
                <div class="alert error"><?php echo htmlspecialchars($erro); ?></div>
            <?php endif; ?>

            <form method="post" action="login.php">
                <div>
                    <label for="email">E-mail:</label>
                    <input id="email" type="email" name="email" value="<?php echo htmlspecialchars($_POST["email"] ?? ""); ?>" required>
                </div>

                <div>
                    <label for="senha">Senha:</label>
                    <input id="senha" type="password" name="senha" required>
                </div>

                <button type="submit" class="btn">Entrar</button>
            </form>
        </div>
    </main>
</body>
</html>
