<?php
session_start();
require __DIR__ . "/conexao/conexao.php";

exigirPerfilAdmin();

$mensagem = "";
$usuarioEditando = null;
$usuarioAtualId = (int)($_SESSION['usuario_id'] ?? 0);

if (isset($_GET['edit'])) {
    $id = (int)($_GET['edit'] ?? 0);
    if ($id > 0) {
        $resultado = $conexao->query("SELECT * FROM usuarios WHERE id = $id LIMIT 1");
        if ($resultado && $resultado->num_rows > 0) {
            $usuarioEditando = $resultado->fetch_assoc();
        }
    }
}

if (isset($_GET['delete'])) {
    $id = (int)($_GET['delete'] ?? 0);
    if ($id > 0 && $id !== $usuarioAtualId) {
        $conexao->query("DELETE FROM usuarios WHERE id = $id");
        registrarAuditoria($conexao, $usuarioAtualId, 'excluir_usuario', 'usuarios', 'Usuário excluído do sistema. ID: ' . $id);
        header('Location: usuarios.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['tipo_form'] ?? '') === 'usuario_status') {
    $id = (int)($_POST['usuario_id'] ?? 0);
    $novoStatus = strtolower(trim($_POST['status'] ?? 'ativo'));
    if ($id > 0 && in_array($novoStatus, ['ativo', 'inativo'], true) && $id !== $usuarioAtualId) {
        $stmt = $conexao->prepare('UPDATE usuarios SET status = ? WHERE id = ?');
        $stmt->bind_param('si', $novoStatus, $id);
        $stmt->execute();
        registrarAuditoria($conexao, $usuarioAtualId, 'alterar_status_usuario', 'usuarios', 'Status alterado para ' . $novoStatus . ' no usuário ID: ' . $id);
    }
    header('Location: usuarios.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['tipo_form'] ?? '') === 'usuario') {
    $id = (int)($_POST['usuario_id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $login = trim($_POST['login'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $perfil = strtolower(trim($_POST['perfil'] ?? 'funcionario'));
    $status = strtolower(trim($_POST['status'] ?? 'ativo'));

    if ($nome === '' || $email === '' || $login === '') {
        $mensagem = 'Nome, e-mail e usuário/login são obrigatórios.';
    } elseif (!in_array($perfil, ['admin', 'funcionario'], true)) {
        $mensagem = 'Perfil inválido.';
    } elseif (!in_array($status, ['ativo', 'inativo'], true)) {
        $mensagem = 'Status inválido.';
    } else {
        $loginExiste = $conexao->query("SELECT id FROM usuarios WHERE login = '" . $conexao->real_escape_string($login) . "'" . ($id > 0 ? " AND id <> $id" : '') . " LIMIT 1");
        if ($loginExiste && $loginExiste->num_rows > 0) {
            $mensagem = 'Já existe um usuário com este login.';
        } else {
            $emailExiste = $conexao->query("SELECT id FROM usuarios WHERE email = '" . $conexao->real_escape_string($email) . "'" . ($id > 0 ? " AND id <> $id" : '') . " LIMIT 1");
            if ($emailExiste && $emailExiste->num_rows > 0) {
                $mensagem = 'Já existe um usuário cadastrado com este e-mail.';
            } else {
                if ($id > 0) {
                    $sql = "UPDATE usuarios SET nome = ?, email = ?, login = ?, perfil = ?, status = ?";
                    $params = [$nome, $email, $login, $perfil, $status];
                    $types = 'sssss';
                    if ($senha !== '') {
                        $sql .= ', senha = ?';
                        $params[] = password_hash($senha, PASSWORD_DEFAULT);
                        $types .= 's';
                    }
                    $sql .= ' WHERE id = ?';
                    $params[] = $id;
                    $types .= 'i';
                    $stmt = $conexao->prepare($sql);
                    $stmt->bind_param($types, ...$params);
                    $stmt->execute();
                    $acao = 'editar_usuario';
                    $detalhe = 'Usuário atualizado: ' . $email;
                } else {
                    $stmt = $conexao->prepare("INSERT INTO usuarios (nome, email, login, senha, foto_perfil, perfil, status, criado_por) VALUES (?, ?, ?, ?, 'default.svg', ?, ?, ?)");
                    $hash = password_hash($senha === '' ? '123456' : $senha, PASSWORD_DEFAULT);
                    $stmt->bind_param('sssssii', $nome, $email, $login, $hash, $perfil, $status, $usuarioAtualId);
                    $stmt->execute();
                    $acao = 'criar_usuario';
                    $detalhe = 'Usuário criado: ' . $email;
                }

                registrarAuditoria($conexao, $usuarioAtualId, $acao, 'usuarios', $detalhe);
                header('Location: usuarios.php');
                exit;
            }
        }
    }
}

$usuarios = $conexao->query("SELECT u.*, c.nome AS criado_por_nome FROM usuarios u LEFT JOIN usuarios c ON c.id = u.criado_por ORDER BY u.id DESC");
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuários</title>
    <link rel="stylesheet" href="css/estilo.css?v=20260925">
</head>
<body class="theme-light">
    <header>
        <div class="container">
            <div class="brand">Assistência Técnica</div>
            <nav class="nav">
                <a href="painel.php">Painel</a>
                <a href="clientes.php">Clientes</a>
                <a href="ordens.php">Ordens</a>
                <a href="produtos.php">Produtos</a>
                <a href="logout.php">Sair</a>
            </nav>
            <div class="topbar-actions">
                <button id="themeToggle" class="btn btn-secondary theme-toggle" type="button" aria-label="Alternar tema" title="Alternar tema">☀️</button>
            </div>
        </div>
    </header>

    <main class="container">
        <div class="card page-header-card">
            <div>
                <p class="eyebrow">Administração</p>
                <h1 class="page-title">Funcionários</h1>
            </div>
            <div class="action-row">
                <a class="btn btn-secondary" href="painel.php">Voltar</a>
            </div>
        </div>

        <?php if ($mensagem !== '') : ?>
            <div class="alert error"><?php echo htmlspecialchars($mensagem); ?></div>
        <?php endif; ?>

        <section class="two-column-layout">
            <div class="card">
                <h3><?php echo $usuarioEditando ? 'Editar funcionário' : 'Cadastrar funcionário'; ?></h3>
                <form method="post" class="form-grid">
                    <input type="hidden" name="tipo_form" value="usuario">
                    <input type="hidden" name="usuario_id" value="<?php echo (int)($usuarioEditando['id'] ?? 0); ?>">

                    <div>
                        <label for="nome">Nome completo</label>
                        <input id="nome" name="nome" required value="<?php echo htmlspecialchars($usuarioEditando['nome'] ?? ''); ?>">
                    </div>
                    <div>
                        <label for="email">E-mail</label>
                        <input id="email" type="email" name="email" required value="<?php echo htmlspecialchars($usuarioEditando['email'] ?? ''); ?>">
                    </div>
                    <div>
                        <label for="login">Usuário/Login</label>
                        <input id="login" name="login" required value="<?php echo htmlspecialchars($usuarioEditando['login'] ?? ''); ?>" placeholder="Ex.: joao.silva">
                    </div>
                    <div>
                        <label for="senha">Senha</label>
                        <input id="senha" type="password" name="senha" placeholder="<?php echo $usuarioEditando ? 'Preencher apenas para trocar' : 'Informe a senha'; ?>">
                    </div>
                    <div>
                        <label for="perfil">Perfil de acesso</label>
                        <select id="perfil" name="perfil">
                            <option value="funcionario" <?php echo (($usuarioEditando['perfil'] ?? 'funcionario') === 'funcionario') ? 'selected' : ''; ?>>Funcionário</option>
                            <option value="admin" <?php echo (($usuarioEditando['perfil'] ?? 'funcionario') === 'admin') ? 'selected' : ''; ?>>Administrador</option>
                        </select>
                    </div>
                    <div>
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="ativo" <?php echo (($usuarioEditando['status'] ?? 'ativo') === 'ativo') ? 'selected' : ''; ?>>Ativo</option>
                            <option value="inativo" <?php echo (($usuarioEditando['status'] ?? 'ativo') === 'inativo') ? 'selected' : ''; ?>>Inativo</option>
                        </select>
                    </div>

                    <div class="full-width actions-row">
                        <button class="btn btn-primary" type="submit"><?php echo $usuarioEditando ? 'Salvar alterações' : 'Cadastrar funcionário'; ?></button>
                        <?php if ($usuarioEditando) : ?>
                            <a class="btn btn-secondary" href="usuarios.php">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Permissões do perfil</h3>
                <ul>
                    <li><strong>Administrador:</strong> acesso completo, relatórios financeiros, painel e gestão de usuários.</li>
                    <li><strong>Funcionário:</strong> clientes, ordens, produtos consultados, estoque e vendas operacionais.</li>
                </ul>
            </div>
        </section>

        <section class="card">
            <h3>Lista de funcionários</h3>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>Usuário</th>
                            <th>Perfil</th>
                            <th>Status</th>
                            <th>Criado por</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($usuarios && $usuarios->num_rows > 0) : ?>
                            <?php while ($usuario = $usuarios->fetch_assoc()) : ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($usuario['nome']); ?></td>
                                    <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                    <td><?php echo htmlspecialchars($usuario['login'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($usuario['perfil']); ?></td>
                                    <td>
                                        <form method="post" class="inline-status-form">
                                            <input type="hidden" name="tipo_form" value="usuario_status">
                                            <input type="hidden" name="usuario_id" value="<?php echo (int)$usuario['id']; ?>">
                                            <select name="status" onchange="this.form.submit()" <?php echo ((int)$usuario['id'] === $usuarioAtualId) ? 'disabled' : ''; ?>>
                                                <option value="ativo" <?php echo (($usuario['status'] ?? 'ativo') === 'ativo') ? 'selected' : ''; ?>>Ativo</option>
                                                <option value="inativo" <?php echo (($usuario['status'] ?? 'ativo') === 'inativo') ? 'selected' : ''; ?>>Inativo</option>
                                            </select>
                                        </form>
                                    </td>
                                    <td><?php echo htmlspecialchars($usuario['criado_por_nome'] ?? 'Sistema'); ?></td>
                                    <td class="table-actions">
                                        <a class="btn btn-small btn-secondary" href="usuarios.php?edit=<?php echo (int)$usuario['id']; ?>">Editar</a>
                                        <?php if ((int)$usuario['id'] !== $usuarioAtualId) : ?>
                                            <a class="btn btn-small btn-danger" href="usuarios.php?delete=<?php echo (int)$usuario['id']; ?>" onclick="return confirm('Deseja realmente excluir este funcionário?');">Excluir</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else : ?>
                            <tr><td colspan="7">Nenhum funcionário encontrado.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script>
        const body = document.body;
        const themeToggle = document.getElementById('themeToggle');

        function setTheme(theme) {
            body.classList.remove('theme-light', 'theme-dark');
            body.classList.add('theme-' + theme);
            localStorage.setItem('theme', theme);
            if (themeToggle) {
                themeToggle.textContent = theme === 'light' ? '☀️' : '🌙';
            }
        }

        const savedTheme = localStorage.getItem('theme') || 'light';
        setTheme(savedTheme);

        if (themeToggle) {
            themeToggle.addEventListener('click', function () {
                const nextTheme = localStorage.getItem('theme') === 'dark' ? 'light' : 'dark';
                setTheme(nextTheme);
            });
        }
    </script>
</body>
</html>
