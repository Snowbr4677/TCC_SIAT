<?php
session_start();

require __DIR__ . "/conexao/conexao.php";

exigirPerfilAdmin();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

if (isset($_GET["delete"])) {
    $id = (int)($_GET["delete"] ?? 0);
    if ($id > 0) {
        $conexao->query("DELETE FROM despesas WHERE id = $id");
        header("Location: despesas.php");
        exit;
    }
}

$conexao->query("CREATE TABLE IF NOT EXISTS despesas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    descricao VARCHAR(255) NOT NULL,
    categoria VARCHAR(100) NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    data_despesa DATE NOT NULL,
    observacao TEXT
)");

$mensagem = "";
$despesaEditando = null;
$categorias = [
    "Peça",
    "Mão de obra",
    "Frete",
    "Material",
    "Ferramenta",
    "Compra de componente",
    "Reparo externo",
    "Software",
    "Energia/telefone",
    "Transporte",
    "Outros"
];

if (isset($_GET["edit"])) {
    $id = (int)($_GET["edit"] ?? 0);
    if ($id > 0) {
        $resultado = $conexao->query("SELECT * FROM despesas WHERE id = $id LIMIT 1");
        if ($resultado && $resultado->num_rows > 0) {
            $despesaEditando = $resultado->fetch_assoc();
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id = (int)($_POST["id"] ?? 0);
    $descricao = trim($_POST["descricao"] ?? "");
    $categoria = trim($_POST["categoria"] ?? "");
    $valor = (float)($_POST["valor"] ?? 0);
    $data = $_POST["data_despesa"] ?? date("Y-m-d");
    $observacao = trim($_POST["observacao"] ?? "");

    if ($descricao !== "" && $categoria !== "" && $valor > 0) {
        if ($id > 0) {
            $stmt = $conexao->prepare("UPDATE despesas SET descricao = ?, categoria = ?, valor = ?, data_despesa = ?, observacao = ? WHERE id = ?");
            $stmt->bind_param("ssdssi", $descricao, $categoria, $valor, $data, $observacao, $id);
            $mensagem = "Despesa atualizada com sucesso.";
        } else {
            $stmt = $conexao->prepare("INSERT INTO despesas (descricao, categoria, valor, data_despesa, observacao) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssdss", $descricao, $categoria, $valor, $data, $observacao);
            $mensagem = "Despesa salva com sucesso.";
        }
        $stmt->execute();
    } else {
        $mensagem = "Preencha todos os campos da despesa.";
    }
}

$despesas = $conexao->query("SELECT * FROM despesas ORDER BY data_despesa DESC");

$notificacoes = [
    [
        'titulo' => 'Despesas do mês',
        'texto' => 'Verifique os gastos recentes e pendentes.',
        'tipo' => 'warning'
    ],
    [
        'titulo' => 'Controle financeiro',
        'texto' => 'Acompanhe categoria por categoria.',
        'tipo' => 'info'
    ],
    [
        'titulo' => 'Resumo',
        'texto' => 'Mantenha a organização do fluxo de caixa.',
        'tipo' => 'success'
    ]
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Despesas</title>
    <link rel="stylesheet" href="css/estilo.css?v=20260909">
</head>
<body class="theme-light">
    <header>
        <div class="container">
            <div class="brand">Assistência Técnica</div>
            <nav class="nav">
                <a href="painel.php">Painel</a>
                <a href="financeiro.php">Financeiro</a>
                <a href="logout.php">Sair</a>
            </nav>

            <div class="topbar-actions">
                <div class="notification-wrapper">
                    <button id="notificationToggle" class="header-notification" type="button" aria-label="Notificações" title="Notificações">
                        🔔
                        <span class="notification-badge"><?php echo min(count($notificacoes), 9); ?></span>
                    </button>
                    <div id="notificationDropdown" class="notification-dropdown" aria-live="polite" style="display: none;">
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
                        <?php $avatar = $_SESSION['usuario_foto'] ?? 'default.svg'; ?>
                        <?php if (!empty($avatar) && $avatar !== 'default.svg' && file_exists(__DIR__ . '/uploads/perfil/' . $avatar)) : ?>
                            <img src="uploads/perfil/<?php echo htmlspecialchars($avatar); ?>" alt="Foto de perfil">
                        <?php else : ?>
                            <?php echo htmlspecialchars(strtoupper(substr($_SESSION['usuario_nome'] ?? 'U', 0, 1))); ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <span>Olá,</span>
                        <strong><?php echo htmlspecialchars($_SESSION['usuario_nome'] ?? 'Usuário'); ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="container">
        <div class="card">
            <h1 class="page-title">Despesas</h1>
        </div>

        <?php if ($mensagem !== "") : ?>
            <div class="alert success"><?php echo htmlspecialchars($mensagem); ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="post" action="despesas.php">
                <input type="hidden" name="id" value="<?php echo (int)($despesaEditando["id"] ?? 0); ?>">
                <div>
                    <label>Descrição:</label>
                    <input type="text" name="descricao" value="<?php echo htmlspecialchars($despesaEditando["descricao"] ?? ""); ?>" required>
                </div>
                <div>
                    <label>Categoria:</label>
                    <select name="categoria" required>
                        <option value="">Selecione</option>
                        <?php foreach ($categorias as $categoriaOpcao) : ?>
                            <option value="<?php echo htmlspecialchars($categoriaOpcao); ?>" <?php echo (($despesaEditando["categoria"] ?? "") === $categoriaOpcao) ? "selected" : ""; ?>>
                                <?php echo htmlspecialchars($categoriaOpcao); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Valor:</label>
                    <input type="number" step="0.01" name="valor" value="<?php echo htmlspecialchars((string)($despesaEditando["valor"] ?? "")); ?>" required>
                </div>
                <div>
                    <label>Data:</label>
                    <input type="date" name="data_despesa" value="<?php echo htmlspecialchars($despesaEditando["data_despesa"] ?? date('Y-m-d')); ?>" required>
                </div>
                <div>
                    <label>Observação:</label>
                    <textarea name="observacao"><?php echo htmlspecialchars($despesaEditando["observacao"] ?? ""); ?></textarea>
                </div>
                <button type="submit"><?php echo $despesaEditando ? "Salvar alterações" : "Salvar despesa"; ?></button>
            </form>
        </div>

        <div class="card">
            <h3>Histórico</h3>
            <table>
                <thead>
                    <tr>
                        <th>Descrição</th>
                        <th>Categoria</th>
                        <th>Valor</th>
                        <th>Data</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($despesas && $despesas->num_rows > 0) : ?>
                        <?php while ($despesa = $despesas->fetch_assoc()) : ?>
                            <tr>
                                <td><?php echo htmlspecialchars($despesa["descricao"] ?? "-"); ?></td>
                                <td><?php echo htmlspecialchars($despesa["categoria"] ?? "-"); ?></td>
                                <td>R$ <?php echo number_format((float)($despesa["valor"] ?? 0), 2, ',', '.'); ?></td>
                                <td><?php echo htmlspecialchars($despesa["data_despesa"] ?? "-"); ?></td>
                                <td class="inline-actions">
                                    <a class="btn btn-secondary" href="despesas.php?edit=<?php echo (int)$despesa["id"]; ?>">Editar</a>
                                    <a class="btn btn-danger" href="despesas.php?delete=<?php echo (int)$despesa["id"]; ?>" onclick="return confirm('Deseja excluir esta despesa?');">Excluir</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else : ?>
                        <tr><td colspan="5">Nenhuma despesa cadastrada.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        const notificationToggle = document.getElementById('notificationToggle');
        const notificationDropdown = document.getElementById('notificationDropdown');

        if (notificationToggle && notificationDropdown) {
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
        }

        const body = document.body;
        const themeToggle = document.getElementById('themeToggle');
        const savedTheme = localStorage.getItem('theme');

        if (savedTheme === 'dark') {
            body.classList.add('theme-dark');
            if (themeToggle) themeToggle.textContent = '🌙';
        }

        if (themeToggle) {
            themeToggle.addEventListener('click', function () {
                body.classList.toggle('theme-dark');
                const darkMode = body.classList.contains('theme-dark');
                localStorage.setItem('theme', darkMode ? 'dark' : 'light');
                themeToggle.textContent = darkMode ? '🌙' : '☀️';
            });
        }
    </script>
</body>
</html>
