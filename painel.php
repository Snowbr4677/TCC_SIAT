<?php
session_start();
require __DIR__ . "/conexao/conexao.php";

exigirPerfilOperacional();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

$usuarioId = (int)($_SESSION["usuario_id"] ?? 0);
$usuarioFoto = $_SESSION["usuario_foto"] ?? "default.svg";

if ($usuarioId > 0) {
    $dadosUsuario = $conexao->query("SELECT foto_perfil FROM usuarios WHERE id = $usuarioId LIMIT 1")->fetch_assoc();
    if ($dadosUsuario && !empty($dadosUsuario["foto_perfil"])) {
        $usuarioFoto = $dadosUsuario["foto_perfil"];
        $_SESSION["usuario_foto"] = $usuarioFoto;
    }
}

$totalClientes = (int) $conexao->query("SELECT COUNT(*) as total FROM clientes")->fetch_assoc()["total"];
$totalOrdens = (int) $conexao->query("SELECT COUNT(*) as total FROM ordens_servico")->fetch_assoc()["total"];
$totalConcluidas = (int) $conexao->query("SELECT COUNT(*) as total FROM ordens_servico WHERE status_os = 'Concluído'")->fetch_assoc()["total"];
$totalEmAnalise = (int) $conexao->query("SELECT COUNT(*) as total FROM ordens_servico WHERE status_os = 'Em análise'")->fetch_assoc()["total"];
$totalAguardando = (int) $conexao->query("SELECT COUNT(*) as total FROM ordens_servico WHERE status_os = 'Aguardando peça'")->fetch_assoc()["total"];
$totalRecebido = (int) $conexao->query("SELECT COUNT(*) as total FROM ordens_servico WHERE status_os = 'Recebido'")->fetch_assoc()["total"];
$valorTotal = (float) $conexao->query("SELECT COALESCE(SUM(valor), 0) as total FROM ordens_servico")->fetch_assoc()["total"];
$valorRecebido = (float) $conexao->query("SELECT COALESCE(SUM(valor_pago), 0) as total FROM pagamentos WHERE status_pagamento <> 'Pendente'")->fetch_assoc()["total"];
$valorDespesas = (float) $conexao->query("SELECT COALESCE(SUM(valor), 0) as total FROM despesas")->fetch_assoc()["total"];
$valorPendente = max($valorTotal - $valorRecebido, 0);
$pagamentosPendentes = (int) $conexao->query("SELECT COUNT(*) as total FROM pagamentos WHERE status_pagamento IN ('Pendente', 'Parcial')")->fetch_assoc()["total"];
$inicioMes = date('Y-m-01');
$fimMes = date('Y-m-t');
$recebidoMes = (float) $conexao->query("SELECT COALESCE(SUM(valor_pago), 0) as total FROM pagamentos WHERE status_pagamento <> 'Pendente' AND data_pagamento >= '$inicioMes 00:00:00' AND data_pagamento <= '$fimMes 23:59:59'")->fetch_assoc()["total"];
$despesasMes = (float) $conexao->query("SELECT COALESCE(SUM(valor), 0) as total FROM despesas WHERE data_despesa >= '$inicioMes' AND data_despesa <= '$fimMes'")->fetch_assoc()["total"];
$lucro = $recebidoMes - $despesasMes;

$ultimasOrdens = $conexao->query("SELECT os.id, c.nome AS cliente, os.status_os, os.valor, os.data_entrada FROM ordens_servico os LEFT JOIN clientes c ON c.id = os.cliente_id ORDER BY os.data_entrada DESC LIMIT 5");

$notificacoes = [];

if ($totalClientes > 0) {
    $notificacoes[] = [
        'titulo' => 'Clientes',
        'texto' => $totalClientes . ' cliente(s) cadastrado(s) no sistema.',
        'tipo' => 'info'
    ];
}

if ($valorPendente > 0) {
    $notificacoes[] = [
        'titulo' => 'Pagamentos pendentes',
        'texto' => 'Há R$ ' . number_format($valorPendente, 2, ',', '.') . ' em valores a receber.',
        'tipo' => 'warning'
    ];
}

if ($pagamentosPendentes > 0) {
    $notificacoes[] = [
        'titulo' => 'Baixas financeiras pendentes',
        'texto' => $pagamentosPendentes . ' pagamento(s) com status pendente ou parcial.',
        'tipo' => 'warning'
    ];
}

if ($despesasMes > 0) {
    $notificacoes[] = [
        'titulo' => 'Despesas do mês',
        'texto' => 'Foram lançados R$ ' . number_format($despesasMes, 2, ',', '.') . ' em despesas neste mês.',
        'tipo' => 'info'
    ];
}

if ($totalAguardando > 0) {
    $notificacoes[] = [
        'titulo' => 'Peças pendentes',
        'texto' => $totalAguardando . ' ordem(es) aguardando peça.',
        'tipo' => 'warning'
    ];
}

if ($totalEmAnalise > 0) {
    $notificacoes[] = [
        'titulo' => 'Em análise',
        'texto' => $totalEmAnalise . ' ordem(es) em avaliação.',
        'tipo' => 'info'
    ];
}

if ($totalRecebido > 0) {
    $notificacoes[] = [
        'titulo' => 'Recebimentos',
        'texto' => $totalRecebido . ' ordem(es) ainda recebidas.',
        'tipo' => 'success'
    ];
}

if (empty($notificacoes)) {
    $notificacoes[] = [
        'titulo' => 'Tudo em dia',
        'texto' => 'Nenhuma pendência no momento.',
        'tipo' => 'success'
    ];
}

$usuarioPerfil = perfilUsuarioAtual();
$usuarioEhAdmin = usuarioEhAdmin();

$dashboardVendas = (int)($conexao->query("SELECT COUNT(*) as total FROM vendas_marketplace")->fetch_assoc()["total"] ?? 0);
$dashboardFaturamentoMes = (float)($conexao->query("SELECT COALESCE(SUM(v.valor_venda * v.quantidade), 0) as total FROM vendas_marketplace v WHERE MONTH(v.data_venda) = MONTH(CURRENT_DATE()) AND YEAR(v.data_venda) = YEAR(CURRENT_DATE())")->fetch_assoc()["total"] ?? 0);
$dashboardEstoqueBaixo = (int)($conexao->query("SELECT COUNT(*) as total FROM produtos WHERE quantidade <= 5")->fetch_assoc()["total"] ?? 0);
$ultimasVendas = $conexao->query("SELECT v.*, p.nome AS produto_nome FROM vendas_marketplace v LEFT JOIN produtos p ON p.id = v.produto_id ORDER BY v.data_venda DESC LIMIT 5");

$periodoAtual = $_GET['period'] ?? 'mes';
if (!in_array($periodoAtual, ['hoje', '7d', '30d', 'mes', 'ano', 'custom'], true)) {
    $periodoAtual = 'mes';
}

$periodoInicio = $_GET['inicio'] ?? '';
$periodoFim = $_GET['fim'] ?? '';

if ($periodoAtual === 'hoje') {
    $periodoInicio = date('Y-m-d');
    $periodoFim = date('Y-m-d');
} elseif ($periodoAtual === '7d') {
    $periodoInicio = date('Y-m-d', strtotime('-6 days'));
    $periodoFim = date('Y-m-d');
} elseif ($periodoAtual === '30d') {
    $periodoInicio = date('Y-m-d', strtotime('-29 days'));
    $periodoFim = date('Y-m-d');
} elseif ($periodoAtual === 'mes') {
    $periodoInicio = date('Y-m-01');
    $periodoFim = date('Y-m-t');
} elseif ($periodoAtual === 'ano') {
    $periodoInicio = date('Y-01-01');
    $periodoFim = date('Y-12-31');
} elseif ($periodoAtual === 'custom' && $periodoInicio !== '' && $periodoFim !== '') {
    $periodoInicio = date('Y-m-d', strtotime($periodoInicio));
    $periodoFim = date('Y-m-d', strtotime($periodoFim));
} else {
    $periodoInicio = date('Y-m-01');
    $periodoFim = date('Y-m-t');
}

$filtroDataVendaSql = " AND v.data_venda >= '" . $conexao->real_escape_string($periodoInicio . ' 00:00:00') . "' AND v.data_venda <= '" . $conexao->real_escape_string($periodoFim . ' 23:59:59') . "'";

$totalProdutosVendidosPeriodo = (int)($conexao->query("SELECT COALESCE(SUM(v.quantidade), 0) as total FROM vendas_marketplace v WHERE 1=1" . $filtroDataVendaSql)->fetch_assoc()["total"] ?? 0);
$valorProdutosPeriodo = (float)($conexao->query("SELECT COALESCE(SUM(v.quantidade * v.valor_venda), 0) as total FROM vendas_marketplace v WHERE 1=1" . $filtroDataVendaSql)->fetch_assoc()["total"] ?? 0);
$totalVendasPeriodo = (int)($conexao->query("SELECT COUNT(*) as total FROM vendas_marketplace v WHERE 1=1" . $filtroDataVendaSql)->fetch_assoc()["total"] ?? 0);
$produtoMaisVendidoPeriodo = $conexao->query("SELECT p.nome, SUM(v.quantidade) as total_vendido FROM vendas_marketplace v LEFT JOIN produtos p ON p.id = v.produto_id WHERE 1=1" . $filtroDataVendaSql . " GROUP BY p.id, p.nome ORDER BY total_vendido DESC LIMIT 1")->fetch_assoc();
$ultimaVendaPeriodo = $conexao->query("SELECT v.data_venda, p.nome AS produto_nome, v.quantidade, v.valor_venda FROM vendas_marketplace v LEFT JOIN produtos p ON p.id = v.produto_id WHERE 1=1" . $filtroDataVendaSql . " ORDER BY v.data_venda DESC LIMIT 1")->fetch_assoc();
$estoqueBaixoPeriodo = (int)($conexao->query("SELECT COUNT(*) as total FROM produtos WHERE quantidade <= 5")->fetch_assoc()["total"] ?? 0);
$quantidadeEstoqueTotal = (int)($conexao->query("SELECT COALESCE(SUM(quantidade), 0) as total FROM produtos")->fetch_assoc()["total"] ?? 0);

$produtosMaisVendidos = $conexao->query("SELECT p.nome, SUM(v.quantidade) as total_vendido, SUM(v.quantidade * v.valor_venda) as faturamento FROM vendas_marketplace v LEFT JOIN produtos p ON p.id = v.produto_id WHERE 1=1" . $filtroDataVendaSql . " GROUP BY p.id, p.nome ORDER BY total_vendido DESC LIMIT 5");
$categoriasMaisVendidas = $conexao->query("SELECT p.categoria, SUM(v.quantidade) as total_vendido, SUM(v.quantidade * v.valor_venda) as faturamento FROM vendas_marketplace v LEFT JOIN produtos p ON p.id = v.produto_id WHERE 1=1" . $filtroDataVendaSql . " GROUP BY p.categoria ORDER BY total_vendido DESC LIMIT 5");

$labelsMeses = [];
$dadosMeses = [];
$dadosFaturamentoMes = [];
for ($i = 5; $i >= 0; $i--) {
    $mes = date('Y-m-01', strtotime("-{$i} month"));
    $fimMes = date('Y-m-t', strtotime($mes));
    $label = date('M/Y', strtotime($mes));
    $totalMesVendido = (float)($conexao->query("SELECT COALESCE(SUM(v.quantidade), 0) as total FROM vendas_marketplace v WHERE v.data_venda >= '" . $conexao->real_escape_string($mes . ' 00:00:00') . "' AND v.data_venda <= '" . $conexao->real_escape_string($fimMes . ' 23:59:59') . "'")->fetch_assoc()["total"] ?? 0);
    $faturamentoMes = (float)($conexao->query("SELECT COALESCE(SUM(v.quantidade * v.valor_venda), 0) as total FROM vendas_marketplace v WHERE v.data_venda >= '" . $conexao->real_escape_string($mes . ' 00:00:00') . "' AND v.data_venda <= '" . $conexao->real_escape_string($fimMes . ' 23:59:59') . "'")->fetch_assoc()["total"] ?? 0);
    $labelsMeses[] = $label;
    $dadosMeses[] = $totalMesVendido;
    $dadosFaturamentoMes[] = $faturamentoMes;
}

$anosLabels = [];
$anosValores = [];
for ($ano = date('Y') - 2; $ano <= date('Y'); $ano++) {
    $totalAno = (float)($conexao->query("SELECT COALESCE(SUM(v.quantidade * v.valor_venda), 0) as total FROM vendas_marketplace v WHERE YEAR(v.data_venda) = {$ano}")->fetch_assoc()["total"] ?? 0);
    $anosLabels[] = (string)$ano;
    $anosValores[] = $totalAno;
}

$valorFaturamentoHoje = (float)($conexao->query("SELECT COALESCE(SUM(v.quantidade * v.valor_venda), 0) as total FROM vendas_marketplace v WHERE DATE(v.data_venda) = CURDATE() ")->fetch_assoc()["total"] ?? 0);
$valorFaturamento30d = (float)($conexao->query("SELECT COALESCE(SUM(v.quantidade * v.valor_venda), 0) as total FROM vendas_marketplace v WHERE v.data_venda >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)")->fetch_assoc()["total"] ?? 0);
$produtoMaisVendido = $produtoMaisVendidoPeriodo['nome'] ?? 'Nenhum';
$ultimaVendaData = $ultimaVendaPeriodo ? date('d/m/Y', strtotime($ultimaVendaPeriodo['data_venda'])) : 'Sem venda';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel</title>
    <link rel="stylesheet" href="css/estilo.css?v=5">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="theme-light dashboard-page">
    <div class="app-shell">
        <aside class="sidebar">
            <div class="sidebar-toolbar">
                <button class="sidebar-toggle" type="button" aria-label="Minimizar menu" title="Minimizar menu">⟨</button>
            </div>

            <div class="brand-box">
                <div class="brand-mark">AT</div>
                <div>
                    <strong>Assistência</strong>
                    <small>Técnica</small>
                </div>
            </div>

            <nav class="side-nav">
                <a class="active" href="painel.php">Dashboard</a>
                <?php if ($usuarioEhAdmin) : ?>
                    <a href="clientes.php">Clientes</a>
                    <a href="ordens.php">Ordens</a>
                    <a href="produtos.php">Produtos e Acessórios</a>
                    <a href="relatorios_estoque.php">Estoque</a>
                    <a href="financeiro.php">Financeiro</a>
                    <a href="despesas.php">Despesas</a>
                    <a href="relatorios_financeiros.php">Relatórios Financeiros</a>
                    <a href="backup_banco.php">Backup do Banco</a>
                    <a href="usuarios.php">Funcionários</a>
                <?php else : ?>
                    <a href="clientes.php">Clientes</a>
                    <a href="ordens.php">Ordens</a>
                    <a href="produtos.php">Produtos e Acessórios</a>
                    <a href="relatorios_estoque.php">Estoque</a>
                <?php endif; ?>
                <a href="perfil.php">Perfil</a>
                <a href="logout.php">Sair</a>
            </nav>

            <div class="sidebar-card">
                <?php if ($usuarioEhAdmin) : ?>
                    <span>Resumo</span>
                    <strong>R$ <?php echo number_format($lucro, 2, ',', '.'); ?></strong>
                    <small>Lucro atual</small>
                <?php else : ?>
                    <span>Operação</span>
                    <strong><?php echo max($totalOrdens - $totalConcluidas, 0); ?></strong>
                    <small>Ordens em andamento</small>
                <?php endif; ?>
            </div>
        </aside>

        <div class="main-panel">
            <header class="topbar">
                <div>
                    <p class="eyebrow">Visão geral</p>
                    <h1><?php echo $usuarioEhAdmin ? 'Painel principal' : 'Operações do dia'; ?></h1>
                </div>

                <div class="topbar-actions">
                    <div class="notification-wrapper">
                        <button id="notificationToggle" class="header-notification" type="button" aria-label="Notificações" title="Ver notificações" aria-expanded="false" aria-controls="notificationDropdown">
                            🔔
                            <span class="notification-badge"><?php echo min(count($notificacoes), 9); ?></span>
                        </button>
                        <div id="notificationDropdown" class="notification-dropdown" aria-live="polite" aria-label="Central de notificações">
                            <div class="notification-header">
                                <strong>Notificações</strong>
                                <span><?php echo count($notificacoes); ?> aviso(s)</span>
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
                            <?php if (!empty($usuarioFoto) && $usuarioFoto !== 'default.svg' && file_exists(__DIR__ . '/uploads/perfil/' . $usuarioFoto)) : ?>
                                <img src="uploads/perfil/<?php echo htmlspecialchars($usuarioFoto); ?>" alt="Foto de perfil">
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
                <?php if ($usuarioEhAdmin) : ?>
                    <section class="summary-grid admin-summary-grid">
                        <div class="metric card">
                            <span>Clientes</span>
                            <strong><?php echo $totalClientes; ?></strong>
                            <small>Ativos</small>
                        </div>
                        <div class="metric card">
                            <span>Ordens</span>
                            <strong><?php echo $totalOrdens; ?></strong>
                            <small>Total</small>
                        </div>
                        <div class="metric card success">
                            <span>Vendas</span>
                            <strong><?php echo $dashboardVendas; ?></strong>
                            <small>Marketplace</small>
                        </div>
                        <div class="metric card warning">
                            <span>Estoque baixo</span>
                            <strong><?php echo $dashboardEstoqueBaixo; ?></strong>
                            <small>Itens críticos</small>
                        </div>
                        <div class="metric card purple">
                            <span>Aguardando peça</span>
                            <strong><?php echo $totalAguardando; ?></strong>
                            <small>Dependentes</small>
                        </div>
                        <div class="metric card cyan">
                            <span>Lucro do mês</span>
                            <strong>R$ <?php echo number_format($lucro, 2, ',', '.'); ?></strong>
                            <small>Recebimentos menos despesas</small>
                        </div>
                        <a class="metric card warning metric-link" href="financeiro.php#pagamentos-pendentes" aria-label="Ver pagamentos pendentes no painel financeiro">
                            <span>Valor pendente</span>
                            <strong>R$ <?php echo number_format($valorPendente, 2, ',', '.'); ?></strong>
                            <small>A receber</small>
                        </a>
                    </section>
                <?php else : ?>
                    <section class="summary-grid employee-summary-grid">
                        <div class="metric card">
                            <span>Ordens em andamento</span>
                            <strong><?php echo max($totalOrdens - $totalConcluidas, 0); ?></strong>
                            <small>Ativas</small>
                        </div>
                        <div class="metric card success">
                            <span>Ordens concluídas</span>
                            <strong><?php echo $totalConcluidas; ?></strong>
                            <small>Finalizadas</small>
                        </div>
                        <div class="metric card purple">
                            <span>Produtos em estoque</span>
                            <strong><?php echo (int)($conexao->query("SELECT COALESCE(SUM(quantidade), 0) as total FROM produtos")->fetch_assoc()["total"] ?? 0); ?></strong>
                            <small>Disponíveis</small>
                        </div>
                        <div class="metric card warning">
                            <span>Estoque baixo</span>
                            <strong><?php echo $dashboardEstoqueBaixo; ?></strong>
                            <small>Itens críticos</small>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ($usuarioEhAdmin) : ?>
                    <section class="card management-panel">
                        <div class="card-header-row compact-header-row">
                            <h3>Dashboard gerencial</h3>
                            <form class="inline-filter" method="get" action="painel.php">
                                <button type="submit" name="period" value="hoje" class="btn btn-small <?php echo $periodoAtual === 'hoje' ? 'btn-primary' : 'btn-secondary'; ?>">Hoje</button>
                                <button type="submit" name="period" value="7d" class="btn btn-small <?php echo $periodoAtual === '7d' ? 'btn-primary' : 'btn-secondary'; ?>">7 dias</button>
                                <button type="submit" name="period" value="30d" class="btn btn-small <?php echo $periodoAtual === '30d' ? 'btn-primary' : 'btn-secondary'; ?>">30 dias</button>
                                <button type="submit" name="period" value="mes" class="btn btn-small <?php echo $periodoAtual === 'mes' ? 'btn-primary' : 'btn-secondary'; ?>">Mês</button>
                                <button type="submit" name="period" value="ano" class="btn btn-small <?php echo $periodoAtual === 'ano' ? 'btn-primary' : 'btn-secondary'; ?>">Ano</button>
                            </form>
                        </div>

                        <div class="summary-grid admin-summary-grid compact-kpis">
                            <div class="metric card success">
                                <span>Produtos vendidos</span>
                                <strong><?php echo $totalProdutosVendidosPeriodo; ?></strong>
                                <small>no período</small>
                            </div>
                            <div class="metric card purple">
                                <span>Faturamento</span>
                                <strong>R$ <?php echo number_format($valorProdutosPeriodo, 2, ',', '.'); ?></strong>
                                <small>produtos e acessórios</small>
                            </div>
                            <div class="metric card warning">
                                <span>Estoque baixo</span>
                                <strong><?php echo $estoqueBaixoPeriodo; ?></strong>
                                <small>itens críticos</small>
                            </div>
                            <div class="metric card cyan">
                                <span>Itens em estoque</span>
                                <strong><?php echo $quantidadeEstoqueTotal; ?></strong>
                                <small>quantidade total</small>
                            </div>
                        </div>

                        <div class="chart-grid compact-grid">
                            <div class="card chart-card mini-chart">
                                <div class="card-header-row">
                                    <h3>Vendas por mês</h3>
                                </div>
                                <div class="chart-container" style="height: 230px;">
                                    <canvas id="salesMonthChart"></canvas>
                                </div>
                            </div>
                            <div class="card chart-card mini-chart">
                                <div class="card-header-row">
                                    <h3>Faturamento por ano</h3>
                                </div>
                                <div class="chart-container" style="height: 230px;">
                                    <canvas id="salesYearChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>

                <section class="content-grid">
                    <div class="card chart-card">
                        <div class="card-header-row">
                            <h3>Status das ordens</h3>
                            <span class="pill">Resumo</span>
                        </div>
                        <div class="chart-container">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header-row">
                            <h3>Atalhos rápidos</h3>
                            <button id="toggleShortcuts" class="btn btn-secondary btn-small" type="button" aria-label="Minimizar atalhos">Minimizar</button>
                        </div>
                        <div class="quick-actions" id="quickActions">
                            <a href="clientes.php" class="action-box">
                                <span class="icon">👤</span>
                                <div>
                                    <strong>Clientes</strong>
                                    <small>Cadastrar e buscar</small>
                                </div>
                            </a>
                            <a href="ordens.php" class="action-box">
                                <span class="icon">🧰</span>
                                <div>
                                    <strong>Ordens</strong>
                                    <small>Nova ordem e gestão</small>
                                </div>
                            </a>
                            <a href="produtos.php" class="action-box">
                                <span class="icon">📦</span>
                                <div>
                                    <strong>Produtos</strong>
                                    <small>Estoque e acessórios</small>
                                </div>
                            </a>
                            <?php if ($usuarioEhAdmin) : ?>
                                <a href="financeiro.php" class="action-box">
                                    <span class="icon">💰</span>
                                    <div>
                                        <strong>Financeiro</strong>
                                        <small>Pagamentos e lucros</small>
                                    </div>
                                </a>
                            <?php endif; ?>
                            <a href="perfil.php" class="action-box">
                                <span class="icon">⚙️</span>
                                <div>
                                    <strong>Perfil</strong>
                                    <small>Dados do usuário</small>
                                </div>
                            </a>
                            <a href="logout.php" class="action-box">
                                <span class="icon">🚪</span>
                                <div>
                                    <strong>Sair</strong>
                                    <small>Encerrar sessão</small>
                                </div>
                            </a>
                        </div>
                    </div>
                </section>

                <section class="card">
                    <div class="card-header-row">
                        <h3>Últimas ordens</h3>
                        <a href="ordens.php" class="link-button small">Ver todas</a>
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Cliente</th>
                                <th>Status</th>
                                <th>Valor</th>
                                <th>Entrada</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($ultimasOrdens && $ultimasOrdens->num_rows > 0) : ?>
                                <?php while ($ordem = $ultimasOrdens->fetch_assoc()) : ?>
                                    <tr>
                                        <td>#<?php echo (int)$ordem["id"]; ?></td>
                                        <td><?php echo htmlspecialchars($ordem["cliente"] ?? "-"); ?></td>
                                        <td><span class="status-badge <?php echo strtolower(str_replace(' ', '-', $ordem["status_os"] ?? "recebido")); ?>"><?php echo htmlspecialchars($ordem["status_os"] ?? "-"); ?></span></td>
                                        <td>R$ <?php echo number_format((float)($ordem["valor"] ?? 0), 2, ',', '.'); ?></td>
                                        <td><?php echo htmlspecialchars($ordem["data_entrada"] ?? "-"); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else : ?>
                                <tr><td colspan="5">Nenhuma ordem registrada.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </section>

                <?php if ($usuarioEhAdmin) : ?>
                    <section class="card">
                        <div class="card-header-row">
                            <h3>Últimas vendas</h3>
                            <a href="produtos.php" class="link-button small">Ver todas</a>
                        </div>

                        <table>
                            <thead>
                                <tr>
                                    <th>Produto</th>
                                    <th>Marketplace</th>
                                    <th>Qtd</th>
                                    <th>Valor</th>
                                    <th>Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($ultimasVendas && $ultimasVendas->num_rows > 0) : ?>
                                    <?php while ($venda = $ultimasVendas->fetch_assoc()) : ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($venda["produto_nome"] ?? "-"); ?></td>
                                            <td><?php echo htmlspecialchars($venda["marketplace"] ?? "-"); ?></td>
                                            <td><?php echo (int)($venda["quantidade"] ?? 0); ?></td>
                                            <td>R$ <?php echo number_format((float)($venda["valor_venda"] ?? 0), 2, ',', '.'); ?></td>
                                            <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($venda["data_venda"] ?? date('Y-m-d')))); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else : ?>
                                    <tr><td colspan="5">Nenhuma venda registrada.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </section>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script>
        const statusLabels = ['Recebido', 'Em análise', 'Aguardando peça', 'Concluído'];
        const statusData = [
            <?php echo (int)$totalRecebido; ?>,
            <?php echo (int)$totalEmAnalise; ?>,
            <?php echo (int)$totalAguardando; ?>,
            <?php echo (int)$totalConcluidas; ?>
        ];

        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusData,
                    backgroundColor: ['#3b82f6', '#f59e0b', '#8b5cf6', '#10b981'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        <?php if ($usuarioEhAdmin) : ?>
            const salesMonthChart = document.getElementById('salesMonthChart');
            const salesYearChart = document.getElementById('salesYearChart');
            const topProductsChart = document.getElementById('topProductsChart');
            const categoryChart = document.getElementById('categoryChart');

            if (salesMonthChart) {
                new Chart(salesMonthChart, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($labelsMeses); ?>,
                        datasets: [{
                            label: 'Quantidade vendida',
                            data: <?php echo json_encode($dadosMeses); ?>,
                            backgroundColor: '#2563eb'
                        }, {
                            label: 'Faturamento',
                            data: <?php echo json_encode($dadosFaturamentoMes); ?>,
                            backgroundColor: '#10b981'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }

            if (salesYearChart) {
                new Chart(salesYearChart, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode($anosLabels); ?>,
                        datasets: [{
                            label: 'Faturamento anual',
                            data: <?php echo json_encode($anosValores); ?>,
                            borderColor: '#8b5cf6',
                            backgroundColor: 'rgba(139, 92, 246, 0.2)',
                            fill: true,
                            tension: 0.3
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }

            const topProductsLabels = <?php echo json_encode(array_map(function ($item) { return $item['nome']; }, $produtosMaisVendidos ? $produtosMaisVendidos->fetch_all(MYSQLI_ASSOC) : [])); ?>;
            const topProductsValues = <?php echo json_encode(array_map(function ($item) { return (float)$item['total_vendido']; }, $produtosMaisVendidos ? $produtosMaisVendidos->fetch_all(MYSQLI_ASSOC) : [])); ?>;
            const categoryLabels = <?php echo json_encode(array_map(function ($item) { return $item['categoria']; }, $categoriasMaisVendidas ? $categoriasMaisVendidas->fetch_all(MYSQLI_ASSOC) : [])); ?>;
            const categoryValues = <?php echo json_encode(array_map(function ($item) { return (float)$item['total_vendido']; }, $categoriasMaisVendidas ? $categoriasMaisVendidas->fetch_all(MYSQLI_ASSOC) : [])); ?>;

            if (topProductsChart) {
                new Chart(topProductsChart, {
                    type: 'bar',
                    data: {
                        labels: topProductsLabels,
                        datasets: [{
                            label: 'Produtos mais vendidos',
                            data: topProductsValues,
                            backgroundColor: ['#f59e0b', '#ef4444', '#10b981', '#3b82f6', '#8b5cf6']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y'
                    }
                });
            }

            if (categoryChart) {
                new Chart(categoryChart, {
                    type: 'doughnut',
                    data: {
                        labels: categoryLabels,
                        datasets: [{
                            data: categoryValues,
                            backgroundColor: ['#0ea5e9', '#14b8a6', '#f59e0b', '#f97316', '#8b5cf6']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }
        <?php endif; ?>

        const notificationToggle = document.getElementById('notificationToggle');
        const notificationDropdown = document.getElementById('notificationDropdown');

        const notificationOriginalParent = notificationDropdown.parentElement;
        const notificationOriginalNextSibling = notificationDropdown.nextSibling;

        function posicionarNotificacoes() {
            const rect = notificationToggle.getBoundingClientRect();

            notificationDropdown.style.top = `${rect.bottom + 12}px`;
            notificationDropdown.style.right = `${window.innerWidth - rect.right}px`;
        }

        notificationToggle.addEventListener('click', function (event) {
            event.stopPropagation();

            const isOpen = notificationDropdown.classList.contains('open');

            if (!isOpen) {
                document.body.appendChild(notificationDropdown);
                posicionarNotificacoes();
                notificationDropdown.classList.add('open');
                notificationToggle.setAttribute('aria-expanded', 'true');
            } else {
                notificationDropdown.classList.remove('open');
                notificationToggle.setAttribute('aria-expanded', 'false');

                if (notificationOriginalNextSibling) {
                    notificationOriginalParent.insertBefore(notificationDropdown, notificationOriginalNextSibling);
                } else {
                    notificationOriginalParent.appendChild(notificationDropdown);
                }
            }
        });

        document.addEventListener('click', function (event) {
            if (!notificationDropdown.contains(event.target) && !notificationToggle.contains(event.target)) {
                notificationDropdown.classList.remove('open');
                notificationToggle.setAttribute('aria-expanded', 'false');

                if (notificationDropdown.parentElement === document.body) {
                    if (notificationOriginalNextSibling) {
                        notificationOriginalParent.insertBefore(notificationDropdown, notificationOriginalNextSibling);
                    } else {
                        notificationOriginalParent.appendChild(notificationDropdown);
                    }
                }
            }
        });

        window.addEventListener('resize', function () {
            if (notificationDropdown.classList.contains('open')) {
                posicionarNotificacoes();
            }
        });

        window.addEventListener('scroll', function () {
            if (notificationDropdown.classList.contains('open')) {
                posicionarNotificacoes();
            }
        });

        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;

        const toggleShortcuts = document.getElementById('toggleShortcuts');
        const quickActions = document.getElementById('quickActions');

        function setShortcutsMode(isCompact) {
            quickActions.classList.toggle('compact', isCompact);
            toggleShortcuts.textContent = isCompact ? 'Expandir' : 'Minimizar';
            toggleShortcuts.setAttribute('aria-label', isCompact ? 'Expandir atalhos' : 'Minimizar atalhos');
            localStorage.setItem('shortcutMode', isCompact ? 'compact' : 'expanded');
        }

        if (toggleShortcuts && quickActions) {
            const savedShortcutMode = localStorage.getItem('shortcutMode') || 'expanded';
            setShortcutsMode(savedShortcutMode === 'compact');
            toggleShortcuts.addEventListener('click', function () {
                const isCompact = !quickActions.classList.contains('compact');
                setShortcutsMode(isCompact);
            });
        }

        function setTheme(theme) {
            body.classList.remove('theme-light', 'theme-dark');
            body.classList.add(`theme-${theme}`);
            localStorage.setItem('theme', theme);
            themeToggle.textContent = theme === 'light' ? '☀️' : '🌙';
        }

        themeToggle.addEventListener('click', function () {
            const currentTheme = localStorage.getItem('theme') || 'light';
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            setTheme(newTheme);
        });

        // Inicializa o tema com base no armazenamento local
        const savedTheme = localStorage.getItem('theme') || 'light';
        setTheme(savedTheme);

        const sidebar = document.querySelector('.sidebar');
        const sidebarToggle = document.querySelector('.sidebar-toggle');

        if (sidebar && sidebarToggle) {
            const savedSidebarState = localStorage.getItem('sidebarCollapsed');

            if (savedSidebarState === 'true') {
                sidebar.classList.add('sidebar-collapsed');
                sidebarToggle.setAttribute('aria-label', 'Expandir menu');
                sidebarToggle.title = 'Expandir menu';
                sidebarToggle.textContent = '⟩';
            }

            sidebarToggle.addEventListener('click', function () {
                const collapsed = sidebar.classList.toggle('sidebar-collapsed');
                localStorage.setItem('sidebarCollapsed', String(collapsed));
                sidebarToggle.setAttribute('aria-label', collapsed ? 'Expandir menu' : 'Minimizar menu');
                sidebarToggle.title = collapsed ? 'Expandir menu' : 'Minimizar menu';
                sidebarToggle.textContent = collapsed ? '⟩' : '⟨';
            });
        }
    </script>
</body>
</html>