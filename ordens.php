<?php
session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

require __DIR__ . "/conexao/conexao.php";

exigirPerfilOperacional();

$mensagem = "";
$ordemEditando = null;

$filtroStatus = $_GET["status"] ?? "";
$filtroCliente = (int)($_GET["cliente"] ?? 0);
$page = max((int)($_GET["page"] ?? 1), 1);
$perPage = 10;

$clientes = $conexao->query("SELECT id, nome FROM clientes ORDER BY nome ASC");
$clientesLista = $conexao->query("SELECT id, nome FROM clientes ORDER BY nome ASC");

$baseQuery = "
    FROM ordens_servico os
    LEFT JOIN clientes c ON c.id = os.cliente_id
    LEFT JOIN aparelhos a ON a.id = os.aparelho_id
    WHERE 1 = 1";

$params = "";
if ($filtroCliente > 0) {
    $params .= " AND os.cliente_id = $filtroCliente";
}

if ($filtroStatus !== "") {
    $params .= " AND os.status_os = '" . $conexao->real_escape_string($filtroStatus) . "'";
}

$totalRegistros = (int) $conexao->query("SELECT COUNT(*) AS total " . $baseQuery . $params)->fetch_assoc()["total"];
$totalPaginas = max((int) ceil($totalRegistros / $perPage), 1);
$offset = ($page - 1) * $perPage;

$consultaOrdens = "
    SELECT os.id, os.status_os, os.valor, os.data_entrada, c.nome AS cliente, a.marca, a.modelo, a.defeito
    " . $baseQuery . $params . "
    ORDER BY os.data_entrada DESC
    LIMIT $offset, $perPage";
$listaOrdens = $conexao->query($consultaOrdens);

if (isset($_GET["delete"])) {
    $id = (int)($_GET["delete"] ?? 0);

    if ($id > 0) {
        $conexao->query("DELETE FROM historico_status WHERE ordem_id = $id");

        $ordem = $conexao->query("SELECT aparelho_id FROM ordens_servico WHERE id = $id LIMIT 1");
        if ($ordem && $ordem->num_rows > 0) {
            $dados = $ordem->fetch_assoc();
            if (!empty($dados["aparelho_id"])) {
                $conexao->query("DELETE FROM aparelhos WHERE id = " . (int)$dados["aparelho_id"]);
            }
        }

        $conexao->query("DELETE FROM ordens_servico WHERE id = $id");
        header("Location: ordens.php");
        exit;
    }
}

if (isset($_GET["id"])) {
    $id = (int)$_GET["id"];
    $resultado = $conexao->query("SELECT os.*, a.marca, a.modelo, a.imei, a.defeito FROM ordens_servico os LEFT JOIN aparelhos a ON a.id = os.aparelho_id WHERE os.id = $id LIMIT 1");
    if ($resultado && $resultado->num_rows > 0) {
        $ordemEditando = $resultado->fetch_assoc();
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id = (int)($_POST["id"] ?? 0);
    $clienteId = (int)($_POST["cliente_id"] ?? 0);
    $marca = trim($_POST["marca"] ?? "");
    $modelo = trim($_POST["modelo"] ?? "");
    $imei = trim($_POST["imei"] ?? "");
    $defeito = trim($_POST["defeito"] ?? "");
    $statusOs = trim($_POST["status_os"] ?? "Recebido");
    $valor = trim($_POST["valor"] ?? "0");
    $dataEntrada = trim($_POST["data_entrada"] ?? "");
    $dataEntrada = $dataEntrada !== "" ? $dataEntrada : date("Y-m-d");

    if ($clienteId <= 0) {
        $mensagem = "Selecione um cliente.";
    } else {
        if ($id > 0) {
            $ordemAtual = $conexao->query("SELECT aparelho_id FROM ordens_servico WHERE id = $id LIMIT 1");
            $dadosAtual = $ordemAtual->fetch_assoc();
            $aparelhoId = $dadosAtual["aparelho_id"] ?? null;

            if ($aparelhoId) {
                $stmtAparelho = $conexao->prepare("UPDATE aparelhos SET marca = ?, modelo = ?, imei = ?, defeito = ? WHERE id = ?");
                $stmtAparelho->bind_param("ssssi", $marca, $modelo, $imei, $defeito, $aparelhoId);
                $stmtAparelho->execute();
            }

            $stmt = $conexao->prepare("UPDATE ordens_servico SET cliente_id = ?, status_os = ?, valor = ?, data_entrada = ? WHERE id = ?");
            $dataEntradaMysql = date("Y-m-d H:i:s", strtotime($dataEntrada));
            $stmt->bind_param("isdsi", $clienteId, $statusOs, $valor, $dataEntradaMysql, $id);
        } else {
            $stmtAparelho = $conexao->prepare("INSERT INTO aparelhos (cliente_id, marca, modelo, imei, defeito) VALUES (?, ?, ?, ?, ?)");
            $stmtAparelho->bind_param("issss", $clienteId, $marca, $modelo, $imei, $defeito);
            $stmtAparelho->execute();
            $aparelhoId = $stmtAparelho->insert_id;

            $stmt = $conexao->prepare("INSERT INTO ordens_servico (cliente_id, aparelho_id, status_os, valor, data_entrada) VALUES (?, ?, ?, ?, ?)");
            $dataEntradaMysql = date("Y-m-d H:i:s", strtotime($dataEntrada));
            $stmt->bind_param("iisds", $clienteId, $aparelhoId, $statusOs, $valor, $dataEntradaMysql);
        }

        if ($stmt->execute()) {
            $ordemId = $id > 0 ? $id : $conexao->insert_id;
            $histStmt = $conexao->prepare("INSERT INTO historico_status (ordem_id, status, descricao, data_hora) VALUES (?, ?, ?, NOW())");
            $descricao = "Status inicial registrado como {$statusOs}.";
            $histStmt->bind_param("iss", $ordemId, $statusOs, $descricao);
            $histStmt->execute();

            header("Location: ordens.php");
            exit;
        } else {
            $mensagem = "Erro ao salvar ordem de serviço: " . $stmt->error;
        }
    }
}

$notificacoes = [];

if ($totalRegistros > 0) {
    $notificacoes[] = [
        'titulo' => 'Ordens no sistema',
        'texto' => $totalRegistros . ' registro(s) cadastrados.',
        'tipo' => 'info'
    ];
}

if ($filtroStatus === 'Aguardando peça' || $totalAguardando > 0) {
    $notificacoes[] = [
        'titulo' => 'Aguardando peça',
        'texto' => 'Revise a fila de itens pendentes.',
        'tipo' => 'warning'
    ];
}

if ($filtroStatus === 'Em análise' || $totalEmAnalise > 0) {
    $notificacoes[] = [
        'titulo' => 'Em análise',
        'texto' => 'Há ordens em avaliação no momento.',
        'tipo' => 'info'
    ];
}

if (empty($notificacoes)) {
    $notificacoes[] = [
        'titulo' => 'Tudo limpo',
        'texto' => 'Nenhuma pendência nesta área.',
        'tipo' => 'success'
    ];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ordens de serviço</title>
    <link rel="stylesheet" href="css/estilo.css?v=20260909">
</head>
<body class="theme-light">
    <header>
        <div class="container">
            <div class="brand">Assistência Técnica</div>
            <nav class="nav">
                <a href="painel.php">Painel</a>
                <a class="active" href="ordens.php">Ordens</a>
                <a href="logout.php">Sair</a>
            </nav>
        </div>
    </header>
    <div class="app-shell">
        <div class="main-panel">
            <header class="topbar">
                <div>
                    <p class="eyebrow">Operações</p>
                    <h1>Ordens de serviço</h1>
                </div>
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
                            <?php
                                $fotoPerfilAtiva = $_SESSION['usuario_foto'] ?? 'default.svg';
                                $temFotoPerfil = !empty($fotoPerfilAtiva) && $fotoPerfilAtiva !== 'default.svg' && file_exists(__DIR__ . '/uploads/perfil/' . $fotoPerfilAtiva);
                            ?>
                            <?php if ($temFotoPerfil) : ?>
                                <img src="uploads/perfil/<?php echo htmlspecialchars($fotoPerfilAtiva); ?>" alt="Foto de perfil">
                            <?php else : ?>
                                <?php echo htmlspecialchars(strtoupper(substr($_SESSION["usuario_nome"] ?? "U", 0, 1))); ?>
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
                <div class="card">
                    <div class="card-header-row">
                        <h3><?php echo $ordemEditando ? "Editar ordem" : "Cadastrar ordem"; ?></h3>
                        <a class="link-button small" href="painel.php">Voltar</a>
                    </div>

                    <?php if ($mensagem !== "") : ?>
                        <div class="alert error"><?php echo htmlspecialchars($mensagem); ?></div>
                    <?php endif; ?>

                    <form method="post" action="ordens.php">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars((string)($ordemEditando["id"] ?? 0)); ?>">

                        <div class="form-grid">
                            <div>
                                <label>Cliente:</label>
                                <select name="cliente_id" required>
                                    <option value="">Selecione</option>
                                    <?php while ($cliente = $clientes->fetch_assoc()) : ?>
                                        <option value="<?php echo (int)$cliente["id"]; ?>" <?php echo (($ordemEditando["cliente_id"] ?? 0) == (int)$cliente["id"]) ? "selected" : ""; ?>>
                                            <?php echo htmlspecialchars($cliente["nome"]); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div>
                                <label>Marca:</label>
                                <input type="text" name="marca" value="<?php echo htmlspecialchars($ordemEditando["marca"] ?? ""); ?>">
                            </div>

                            <div>
                                <label>Modelo:</label>
                                <input type="text" name="modelo" value="<?php echo htmlspecialchars($ordemEditando["modelo"] ?? ""); ?>">
                            </div>

                            <div>
                                <label>IMEI:</label>
                                <input type="text" name="imei" value="<?php echo htmlspecialchars($ordemEditando["imei"] ?? ""); ?>">
                            </div>

                            <div class="full-width">
                                <label>Defeito:</label>
                                <textarea name="defeito"><?php echo htmlspecialchars($ordemEditando["defeito"] ?? ""); ?></textarea>
                            </div>

                            <div>
                                <label>Status:</label>
                                <select name="status_os">
                                    <?php $statusAtual = $ordemEditando["status_os"] ?? "Recebido"; ?>
                                    <?php foreach (["Recebido", "Em análise", "Aguardando peça", "Concluído"] as $status) : ?>
                                        <option value="<?php echo $status; ?>" <?php echo $statusAtual === $status ? "selected" : ""; ?>>
                                            <?php echo $status; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label>Valor:</label>
                                <input type="text" name="valor" value="<?php echo htmlspecialchars((string)($ordemEditando["valor"] ?? "0")); ?>">
                            </div>

                            <div>
                                <label>Data de entrada:</label>
                                <input type="date" name="data_entrada" value="<?php echo htmlspecialchars(date('Y-m-d', strtotime($ordemEditando['data_entrada'] ?? 'now'))); ?>" required>
                            </div>
                        </div>

                        <button type="submit" class="large-btn"><?php echo $ordemEditando ? "Salvar alterações" : "Cadastrar ordem"; ?></button>
                    </form>
                </div>

                <div class="card">
                    <div class="card-header-row">
                        <h3>Filtros</h3>
                    </div>

                    <form method="get" action="ordens.php" class="filter-form">
                        <div>
                            <label>Cliente:</label>
                            <select name="cliente">
                                <option value="">Todos</option>
                                <?php while ($cliente = $clientesLista->fetch_assoc()) : ?>
                                    <option value="<?php echo (int)$cliente["id"]; ?>" <?php echo $filtroCliente === (int)$cliente["id"] ? "selected" : ""; ?>>
                                        <?php echo htmlspecialchars($cliente["nome"]); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div>
                            <label>Status:</label>
                            <select name="status">
                                <option value="">Todos</option>
                                <?php foreach (["Recebido", "Em análise", "Aguardando peça", "Concluído"] as $status) : ?>
                                    <option value="<?php echo $status; ?>" <?php echo $filtroStatus === $status ? "selected" : ""; ?>>
                                        <?php echo $status; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-actions">
                            <button type="submit" class="btn">Filtrar</button>
                            <a class="btn btn-secondary" href="ordens.php">Limpar</a>
                            <a class="btn" href="exportar_csv.php?cliente=<?php echo (int)$filtroCliente; ?>&status=<?php echo urlencode((string)$filtroStatus); ?>">Exportar CSV</a>
                        </div>
                    </form>
                </div>

                <div class="card">
                    <div class="card-header-row">
                        <h3>Ordens cadastradas</h3>
                        <span class="pill"><?php echo $totalRegistros; ?> registros</span>
                    </div>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Cliente</th>
                                    <th>Marca</th>
                                    <th>Modelo</th>
                                    <th>Status</th>
                                    <th>Valor</th>
                                    <th>Entrada</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($listaOrdens && $listaOrdens->num_rows > 0) : ?>
                                    <?php while ($ordem = $listaOrdens->fetch_assoc()) : ?>
                                        <tr>
                                            <td>#<?php echo (int)$ordem["id"]; ?></td>
                                            <td><?php echo htmlspecialchars($ordem["cliente"] ?? "-"); ?></td>
                                            <td><?php echo htmlspecialchars($ordem["marca"] ?? "-"); ?></td>
                                            <td><?php echo htmlspecialchars($ordem["modelo"] ?? "-"); ?></td>
                                            <td><span class="status-badge <?php echo strtolower(str_replace(' ', '-', $ordem["status_os"] ?? "recebido")); ?>"><?php echo htmlspecialchars($ordem["status_os"] ?? "-"); ?></span></td>
                                            <td>R$ <?php echo number_format((float)($ordem["valor"] ?? 0), 2, ',', '.'); ?></td>
                                            <td><?php echo htmlspecialchars($ordem["data_entrada"] ?? "-"); ?></td>
                                            <td class="inline-actions">
                                                <a class="btn" href="ordem_detalhes.php?id=<?php echo (int)$ordem["id"]; ?>">Detalhes</a>
                                                <a class="btn btn-secondary" href="ordens.php?id=<?php echo (int)$ordem["id"]; ?>">Editar</a>
                                                <a class="btn btn-danger" href="ordens.php?delete=<?php echo (int)$ordem["id"]; ?>" onclick="return confirm('Deseja excluir esta ordem de serviço?');">Excluir</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="8">Nenhuma ordem cadastrada.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($totalPaginas > 1) : ?>
                        <div class="pagination">
                            <?php
                                $queryParams = http_build_query([
                                    'cliente' => $filtroCliente,
                                    'status' => $filtroStatus,
                                ]);
                            ?>
                            <?php if ($page > 1) : ?>
                                <a href="ordens.php?<?php echo $queryParams; ?>&page=<?php echo $page - 1; ?>">Anterior</a>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $totalPaginas; $i++) : ?>
                                <a href="ordens.php?<?php echo $queryParams; ?>&page=<?php echo $i; ?>" class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>

                            <?php if ($page < $totalPaginas) : ?>
                                <a href="ordens.php?<?php echo $queryParams; ?>&page=<?php echo $page + 1; ?>">Próxima</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
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
            notificationDropdown.style.display = isOpen ? 'none' : 'block';
        });

        document.addEventListener('click', function (event) {
            if (!notificationDropdown.contains(event.target) && !notificationToggle.contains(event.target)) {
                notificationDropdown.classList.remove('open');
                notificationDropdown.style.display = 'none';
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
