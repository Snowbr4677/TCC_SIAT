<?php
session_start();
require __DIR__ . '/conexao/conexao.php';

exigirPerfilAdmin();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$periodoAtual = $_GET['period'] ?? 'mes';
if (!in_array($periodoAtual, ['mes', 'ano', 'custom'], true)) {
    $periodoAtual = 'mes';
}

$inicioPeriodo = $_GET['inicio'] ?? '';
$fimPeriodo = $_GET['fim'] ?? '';

if ($periodoAtual === 'mes') {
    $inicioPeriodo = date('Y-m-01');
    $fimPeriodo = date('Y-m-t');
} elseif ($periodoAtual === 'ano') {
    $inicioPeriodo = date('Y-01-01');
    $fimPeriodo = date('Y-12-31');
} elseif ($inicioPeriodo !== '' && $fimPeriodo !== '') {
    $inicioPeriodo = date('Y-m-d', strtotime($inicioPeriodo));
    $fimPeriodo = date('Y-m-d', strtotime($fimPeriodo));
} else {
    $inicioPeriodo = date('Y-m-01');
    $fimPeriodo = date('Y-m-t');
}

$inicioSql = $conexao->real_escape_string($inicioPeriodo . ' 00:00:00');
$fimSql = $conexao->real_escape_string($fimPeriodo . ' 23:59:59');
$inicioDiaSql = $conexao->real_escape_string($inicioPeriodo);
$fimDiaSql = $conexao->real_escape_string($fimPeriodo);

$filtroVendas = " AND v.data_venda >= '{$inicioSql}' AND v.data_venda <= '{$fimSql}'";
$filtroOrdens = " AND os.data_entrada >= '{$inicioDiaSql} 00:00:00' AND os.data_entrada <= '{$fimDiaSql} 23:59:59'";
$filtroDespesas = " AND d.data_despesa >= '{$inicioDiaSql}' AND d.data_despesa <= '{$fimDiaSql}'";

$produtosResumo = $conexao->query("SELECT
    COALESCE(SUM(v.quantidade), 0) AS quantidade_vendida,
    COALESCE(SUM(v.quantidade * v.valor_venda), 0) AS faturamento,
    COALESCE(SUM((v.valor_venda - p.valor_custo) * v.quantidade), 0) AS lucro
    FROM vendas_marketplace v
    LEFT JOIN produtos p ON p.id = v.produto_id
    WHERE 1=1 {$filtroVendas}")->fetch_assoc();

$produtosTop = $conexao->query("SELECT
    p.nome,
    SUM(v.quantidade) AS quantidade_vendida,
    SUM(v.quantidade * v.valor_venda) AS faturamento,
    SUM((v.valor_venda - p.valor_custo) * v.quantidade) AS lucro
    FROM vendas_marketplace v
    LEFT JOIN produtos p ON p.id = v.produto_id
    WHERE 1=1 {$filtroVendas}
    GROUP BY p.id, p.nome
    ORDER BY quantidade_vendida DESC, faturamento DESC
    LIMIT 10");

$categoriasResumo = $conexao->query("SELECT
    p.categoria,
    SUM(v.quantidade) AS quantidade_vendida,
    SUM(v.quantidade * v.valor_venda) AS faturamento
    FROM vendas_marketplace v
    LEFT JOIN produtos p ON p.id = v.produto_id
    WHERE 1=1 {$filtroVendas}
    GROUP BY p.categoria
    ORDER BY faturamento DESC
    LIMIT 8");

$ordensResumo = $conexao->query("SELECT
    COUNT(*) AS total_ordens,
    COALESCE(SUM(CASE WHEN os.status_os IN ('Concluído', 'Recebido') THEN os.valor ELSE 0 END), 0) AS faturamento,
    COALESCE(SUM(CASE WHEN os.status_os = 'Concluído' THEN 1 ELSE 0 END), 0) AS finalizadas,
    COALESCE(SUM(CASE WHEN os.status_os IN ('Concluído', 'Recebido') THEN os.valor ELSE 0 END), 0) - COALESCE((SELECT SUM(d.valor) FROM despesas d WHERE 1=1 {$filtroDespesas}), 0) AS lucro
    FROM ordens_servico os
    WHERE 1=1 {$filtroOrdens}")->fetch_assoc();

$ultimoBackup = $conexao->query("SELECT nome_arquivo, data_hora FROM backup_log ORDER BY data_hora DESC LIMIT 1")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios Financeiros</title>
    <link rel="stylesheet" href="css/estilo.css?v=20260925">
</head>
<body class="theme-light">
    <header>
        <div class="container">
            <div class="brand">Assistência Técnica</div>
            <nav class="nav">
                <a href="painel.php">Painel</a>
                <a href="produtos.php">Produtos</a>
                <a href="financeiro.php">Financeiro</a>
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
                <p class="eyebrow">Gestão financeira</p>
                <h1 class="page-title">Relatórios Financeiros</h1>
            </div>
            <div class="action-row">
                <a class="btn btn-secondary" href="painel.php">Voltar</a>
                <a class="btn btn-primary" href="exportar_financeiro.php?tipo=xlsx">📊 Exportar Excel</a>
            </div>
        </div>

        <section class="card">
            <div class="card-header-row">
                <h3>Filtros por período</h3>
            </div>
            <form method="get" class="inline-filter" action="relatorios_financeiros.php">
                <button type="submit" name="period" value="mes" class="btn btn-small <?php echo $periodoAtual === 'mes' ? 'btn-primary' : 'btn-secondary'; ?>">Mensal</button>
                <button type="submit" name="period" value="ano" class="btn btn-small <?php echo $periodoAtual === 'ano' ? 'btn-primary' : 'btn-secondary'; ?>">Anual</button>
                <input type="date" name="inicio" value="<?php echo htmlspecialchars($inicioPeriodo); ?>">
                <input type="date" name="fim" value="<?php echo htmlspecialchars($fimPeriodo); ?>">
                <button type="submit" name="period" value="custom" class="btn btn-small btn-primary">Personalizado</button>
            </form>
        </section>

        <section class="card">
            <div class="card-header-row">
                <h3>Relatório de Produtos e Acessórios</h3>
            </div>
            <div class="grid summary-grid">
                <div class="metric card">
                    <span>Itens vendidos</span>
                    <strong><?php echo (int)($produtosResumo['quantidade_vendida'] ?? 0); ?></strong>
                    <small>quantidade</small>
                </div>
                <div class="metric card success">
                    <span>Faturamento</span>
                    <strong>R$ <?php echo number_format((float)($produtosResumo['faturamento'] ?? 0), 2, ',', '.'); ?></strong>
                    <small>vendas de produtos</small>
                </div>
                <div class="metric card warning">
                    <span>Lucro líquido</span>
                    <strong>R$ <?php echo number_format((float)($produtosResumo['lucro'] ?? 0), 2, ',', '.'); ?></strong>
                    <small>base em custo</small>
                </div>
                <div class="metric card purple">
                    <span>Produtos mais vendidos</span>
                    <strong><?php echo $produtosTop && $produtosTop->num_rows > 0 ? htmlspecialchars($produtosTop->fetch_assoc()['nome']) : 'Nenhum'; ?></strong>
                    <small>top item</small>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>Itens vendidos</th>
                            <th>Faturamento</th>
                            <th>Lucro</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($produtosTop && $produtosTop->num_rows > 0) : ?>
                            <?php $produtosTop->data_seek(0); ?>
                            <?php while ($produto = $produtosTop->fetch_assoc()) : ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($produto['nome']); ?></td>
                                    <td><?php echo (int)$produto['quantidade_vendida']; ?></td>
                                    <td>R$ <?php echo number_format((float)$produto['faturamento'], 2, ',', '.'); ?></td>
                                    <td>R$ <?php echo number_format((float)$produto['lucro'], 2, ',', '.'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else : ?>
                            <tr><td colspan="4">Nenhum produto vendido no período informado.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <div class="card-header-row">
                <h3>Relatório de Ordens de Serviço</h3>
            </div>
            <div class="grid summary-grid">
                <div class="metric card">
                    <span>Ordens finalizadas</span>
                    <strong><?php echo (int)($ordensResumo['finalizadas'] ?? 0); ?></strong>
                    <small>serviços concluídos</small>
                </div>
                <div class="metric card success">
                    <span>Faturamento</span>
                    <strong>R$ <?php echo number_format((float)($ordensResumo['faturamento'] ?? 0), 2, ',', '.'); ?></strong>
                    <small>serviços prestados</small>
                </div>
                <div class="metric card warning">
                    <span>Lucro obtido</span>
                    <strong>R$ <?php echo number_format((float)($ordensResumo['lucro'] ?? 0), 2, ',', '.'); ?></strong>
                    <small>receita líquida</small>
                </div>
                <div class="metric card purple">
                    <span>Total de ordens</span>
                    <strong><?php echo (int)($ordensResumo['total_ordens'] ?? 0); ?></strong>
                    <small>no período</small>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Categoria</th>
                            <th>Itens vendidos</th>
                            <th>Faturamento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($categoriasResumo && $categoriasResumo->num_rows > 0) : ?>
                            <?php while ($categoria = $categoriasResumo->fetch_assoc()) : ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($categoria['categoria']); ?></td>
                                    <td><?php echo (int)$categoria['quantidade_vendida']; ?></td>
                                    <td>R$ <?php echo number_format((float)$categoria['faturamento'], 2, ',', '.'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else : ?>
                            <tr><td colspan="3">Nenhuma categoria com venda registrada no período.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <div class="card-header-row">
                <h3>Último backup</h3>
            </div>
            <p><?php echo $ultimoBackup ? htmlspecialchars($ultimoBackup['nome_arquivo']) . ' em ' . htmlspecialchars(date('d/m/Y H:i:s', strtotime($ultimoBackup['data_hora']))) : 'Nenhum backup realizado ainda.'; ?></p>
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
