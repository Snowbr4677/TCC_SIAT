<?php
session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

require __DIR__ . "/conexao/conexao.php";

$usuarioId = (int)($_SESSION["usuario_id"] ?? 0);
$mensagem = "";
$tipoMensagem = "success";

if ($usuarioId > 0) {
    $resultadoUsuario = $conexao->query("SELECT nome, email, foto_perfil FROM usuarios WHERE id = $usuarioId LIMIT 1");
    $usuario = $resultadoUsuario ? $resultadoUsuario->fetch_assoc() : null;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["tipo_form"] ?? "") === "nome") {
    $novoNome = trim($_POST["nome"] ?? "");

    if ($novoNome === "") {
        $mensagem = "Informe o nome do usuário.";
        $tipoMensagem = "error";
    } elseif (strlen($novoNome) > 100) {
        $mensagem = "O nome deve ter no máximo 100 caracteres.";
        $tipoMensagem = "error";
    } else {
        $stmt = $conexao->prepare("UPDATE usuarios SET nome = ? WHERE id = ?");
        $stmt->bind_param("si", $novoNome, $usuarioId);
        $stmt->execute();

        $usuario["nome"] = $novoNome;
        $_SESSION["usuario_nome"] = $novoNome;
        $mensagem = "Nome atualizado com sucesso!";
        $tipoMensagem = "success";
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["tipo_form"] ?? "") === "foto" && !isset($_POST["remover_foto"]) && isset($_FILES["foto_perfil"])) {
    $arquivo = $_FILES["foto_perfil"];

    if ($arquivo["error"] === UPLOAD_ERR_OK) {
        $permitidos = ["image/jpeg", "image/png", "image/webp", "image/jpg"];
        if (!in_array($arquivo["type"], $permitidos, true) && !in_array(mime_content_type($arquivo["tmp_name"]), $permitidos, true)) {
            $mensagem = "Formato inválido. Envie uma imagem JPG, PNG ou WEBP.";
            $tipoMensagem = "error";
        } elseif ($arquivo["size"] > 2 * 1024 * 1024) {
            $mensagem = "A imagem deve ter no máximo 2MB.";
            $tipoMensagem = "error";
        } else {
            $extensao = pathinfo($arquivo["name"], PATHINFO_EXTENSION);
            $nomeArquivo = "usuario_{$usuarioId}_" . time() . "." . strtolower($extensao);
            $destino = __DIR__ . "/uploads/perfil/" . $nomeArquivo;

            if (move_uploaded_file($arquivo["tmp_name"], $destino)) {
                $fotoAtual = $usuario["foto_perfil"] ?? "default.svg";
                if (!empty($fotoAtual) && $fotoAtual !== "default.svg" && file_exists(__DIR__ . "/uploads/perfil/" . $fotoAtual)) {
                    @unlink(__DIR__ . "/uploads/perfil/" . $fotoAtual);
                }

                $stmt = $conexao->prepare("UPDATE usuarios SET foto_perfil = ? WHERE id = ?");
                $stmt->bind_param("si", $nomeArquivo, $usuarioId);
                $stmt->execute();

                $_SESSION["usuario_foto"] = $nomeArquivo;
                $usuario["foto_perfil"] = $nomeArquivo;
                $mensagem = "Foto de perfil atualizada com sucesso!";
                $tipoMensagem = "success";
            } else {
                $mensagem = "Não foi possível salvar a imagem. Tente novamente.";
                $tipoMensagem = "error";
            }
        }
    } else {
        $mensagem = "Selecione uma imagem antes de enviar.";
        $tipoMensagem = "error";
    }
}

if (isset($_POST["remover_foto"])) {
    $fotoAtual = $usuario["foto_perfil"] ?? "default.svg";
    if (!empty($fotoAtual) && $fotoAtual !== "default.svg" && file_exists(__DIR__ . "/uploads/perfil/" . $fotoAtual)) {
        @unlink(__DIR__ . "/uploads/perfil/" . $fotoAtual);
    }

    $stmt = $conexao->prepare("UPDATE usuarios SET foto_perfil = 'default.svg' WHERE id = ?");
    $stmt->bind_param("i", $usuarioId);
    $stmt->execute();

    $_SESSION["usuario_foto"] = "default.svg";
    $usuario["foto_perfil"] = "default.svg";
    $mensagem = "Foto de perfil removida com sucesso.";
    $tipoMensagem = "success";
}

$fotoPerfil = $usuario["foto_perfil"] ?? "default.svg";
$temImagem = !empty($fotoPerfil) && $fotoPerfil !== "default.svg" && file_exists(__DIR__ . "/uploads/perfil/" . $fotoPerfil);

$notificacoes = [
    [
        'titulo' => 'Perfil atualizado',
        'texto' => 'Você pode trocar a foto de perfil sempre que quiser.',
        'tipo' => 'success'
    ],
    [
        'titulo' => 'Segurança',
        'texto' => 'Mantenha a imagem em um formato leve e seguro.',
        'tipo' => 'info'
    ],
    [
        'titulo' => 'Atenção',
        'texto' => 'Evite enviar imagens muito grandes para não afetar o desempenho.',
        'tipo' => 'warning'
    ]
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil</title>
    <link rel="stylesheet" href="css/estilo.css?v=20260909">
</head>
<body class="theme-light">
    <div class="app-shell">
        <div class="main-panel">
            <header class="topbar">
                <div>
                    <p class="eyebrow">Conta</p>
                    <h1>Perfil do usuário</h1>
                </div>
                <div class="topbar-actions">
                    <div class="notification-wrapper">
                        <button id="notificationToggle" class="header-notification" type="button" aria-label="Notificações" title="Notificações">
                            🔔
                            <span class="notification-badge"><?php echo min(count($notificacoes), 9); ?></span>
                        </button>
                        <div id="notificationDropdown" class="notification-dropdown" aria-live="polite">
                            <div class="notification-header">
                                <strong>Notificações</strong>
                            </div>
                            <?php foreach ($notificacoes as $notificacao) : ?>
                                <div class="notification-item type-<?php echo htmlspecialchars($notificacao['tipo']); ?>">
                                    <div class="notification-dot"></div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($notificacao['titulo']); ?></strong>
                                        <span><?php echo htmlspecialchars($notificacao['texto']); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <button id="themeToggle" class="btn btn-secondary theme-toggle" type="button" aria-label="Alternar tema" title="Alternar tema">☀️</button>
                    <div class="user-pill">
                        <div class="profile-avatar-mini">
                            <?php if ($temImagem) : ?>
                                <img src="uploads/perfil/<?php echo htmlspecialchars($fotoPerfil); ?>" alt="Foto de perfil">
                            <?php else : ?>
                                <?php echo htmlspecialchars(strtoupper(substr($usuario["nome"] ?? "U", 0, 1))); ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <span>Olá,</span>
                            <strong><?php echo htmlspecialchars($_SESSION["usuario_nome"] ?? "Usuário"); ?></strong>
                        </div>
                    </div>
                </div>
            </header>

            <main class="content">
                <div class="card profile-card">
                    <div class="profile-photo">
                        <?php if ($temImagem) : ?>
                            <img src="uploads/perfil/<?php echo htmlspecialchars($fotoPerfil); ?>" alt="Foto atual do perfil">
                        <?php else : ?>
                            <span class="profile-placeholder"><?php echo htmlspecialchars(strtoupper(substr($usuario["nome"] ?? "U", 0, 1))); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="profile-info">
                        <div>
                            <h2><?php echo htmlspecialchars($usuario["nome"] ?? "Usuário"); ?></h2>
                            <div class="profile-meta"><?php echo htmlspecialchars($usuario["email"] ?? "-" ); ?></div>
                        </div>

                        <?php if ($mensagem !== "") : ?>
                            <div class="alert <?php echo $tipoMensagem === 'success' ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($mensagem); ?></div>
                        <?php endif; ?>

                        <form method="post" action="perfil.php" class="profile-name-form">
                            <input type="hidden" name="tipo_form" value="nome">
                            <label for="nome">Nome do usuário</label>
                            <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($usuario["nome"] ?? ""); ?>" maxlength="100" required>
                            <div class="profile-actions">
                                <button type="submit" class="btn">Salvar nome</button>
                            </div>
                        </form>

                        <form method="post" action="perfil.php" enctype="multipart/form-data">
                            <input type="hidden" name="tipo_form" value="foto">
                            <label for="foto_perfil">Escolha uma imagem para o perfil</label>
                            <input type="file" id="foto_perfil" name="foto_perfil" accept="image/png, image/jpeg, image/webp, image/jpg">

                            <div class="profile-actions">
                                <button type="submit" class="btn">Salvar foto</button>
                                <button type="submit" name="remover_foto" value="1" class="btn btn-secondary">Remover foto</button>
                                <a href="painel.php" class="btn btn-danger">Voltar</a>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        const notificationToggle = document.getElementById('notificationToggle');
        const notificationDropdown = document.getElementById('notificationDropdown');

        notificationToggle.addEventListener('click', function (event) {
            event.stopPropagation();
            const isOpen = notificationDropdown.classList.contains('open');
            notificationDropdown.classList.toggle('open', !isOpen);
        });

        document.addEventListener('click', function (event) {
            if (!notificationDropdown.contains(event.target) && !notificationToggle.contains(event.target)) {
                notificationDropdown.classList.remove('open');
            }
        });

        const body = document.body;
        const toggleBtn = document.getElementById('themeToggle');
        const savedTheme = localStorage.getItem('theme');

        if (savedTheme === 'dark') {
            body.classList.add('theme-dark');
            toggleBtn.textContent = '🌙';
        }

        toggleBtn.addEventListener('click', function () {
            body.classList.toggle('theme-dark');
            const darkMode = body.classList.contains('theme-dark');
            localStorage.setItem('theme', darkMode ? 'dark' : 'light');
            toggleBtn.textContent = darkMode ? '🌙' : '☀️';
        });
    </script>
</body>
</html>
