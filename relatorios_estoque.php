<?php
session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

require __DIR__ . "/conexao/conexao.php";

$mensagem = "";
$produtos = $conexao->query("SELECT * FROM produtos ORDER BY nome ASC");
$estoqueBaixo = $conexao->query("SELECT * FROM produtos WHERE quantidade <= 5 ORDER BY quantidade ASC");
$totalProdutos = (int)($conexao->query("SELECT COUNT(*) as total FROM produtos")->fetch_assoc()["total"] ?? 0);
$totalUnidades = (int)($conexao->query("SELECT COALESCE(SUM(quantidade), 0) as total FROM produtos")->fetch_assoc()["total"] ?? 0);
$valorEstoque = (float)($conexao->query("SELECT COALESCE(SUM(quantidade * valor_custo), 0) as total FROM produtos")->fetch_assoc()["total"] ?? 0);
$maisVendidos = $conexao->query("SELECT p.nome, SUM(v.quantidade) AS quantidade_total, SUM(v.quantidade * v.valor_venda) AS faturamento FROM vendas_marketplace v LEFT JOIN produtos p ON p.id = v.produto_id GROUP BY p.id, p.nome ORDER BY quantidade_total DESC LIMIT 8");

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="relatorio_estoque.csv"');
    echo "Produto,Categoria,Quantidade,Valor_Custo,Valor_Venda\n";
    $resultado = $conexao->query("SELECT * FROM produtos ORDER BY nome ASC");
    while ($linha = $resultado->fetch_assoc()) {
        echo '"' . str_replace('"', '""', $linha['nome']) . '",';
        echo '"' . str_replace('"', '""', $linha['categoria']) . '",';
        echo (int)$linha['quantidade'] . ',';
        echo number_format((float)$linha['valor_custo'], 2, '.', '') . ',';
        echo number_format((float)$linha['valor_venda'], 2, '.', '') . "\n";
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios de Estoque</title>
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
                <a href="relatorios_financeiros.php">Financeiro</a>
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
                <p class="eyebrow">Inventário</p>
                <h1 class="page-title">Relatórios de Estoque</h1>
            </div>
            <div class="action-row">
                <a class="btn btn-primary" href="relatorios_estoque.php?export=csv">📊 Excel</a>
            </div>
        </div>

        <?php if ($mensagem !== "") : ?>
            <div class="alert success"><?php echo htmlspecialchars($mensagem); ?></div>
        <?php endif; ?>

        <section class="grid summary-grid">
            <div class="metric card">
                <span>Produtos cadastrados</span>
                <strong><?php echo $totalProdutos; ?></strong>
                <small>Total</small>
            </div>
            <div class="metric card success">
                <span>Quantidade disponível</span>
                <strong><?php echo $totalUnidades; ?></strong>
                <small>Itens em estoque</small>
            </div>
            <div class="metric card warning">
                <span>Estoque baixo</span>
                <strong><?php echo $estoqueBaixo->num_rows; ?></strong>
                <small>≤ 5 unidades</small>
            </div>
            <div class="metric card purple">
                <span>Valor total do estoque</span>
                <strong>R$ <?php echo number_format($valorEstoque, 2, ',', '.'); ?></strong>
                <small>Base em custo</small>
            </div>
        </section>

        <section class="two-column-layout">
            <div class="card">
                <div class="card-header-row">
                    <h3>Produtos com estoque baixo</h3>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>Categoria</th>
                            <th>Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($estoqueBaixo && $estoqueBaixo->num_rows > 0) : ?>
                            <?php while ($item = $estoqueBaixo->fetch_assoc()) : ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['nome']); ?></td>
                                    <td><?php echo htmlspecialchars($item['categoria']); ?></td>
                                    <td><span class="status-badge aguardando-peça"><?php echo (int)$item['quantidade']; ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else : ?>
                            <tr><td colspan="3">Nenhum item com estoque baixo.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <div class="card-header-row">
                    <h3>Produtos mais vendidos</h3>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>Qtd vendida</th>
                            <th>Faturamento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($maisVendidos && $maisVendidos->num_rows > 0) : ?>
                            <?php while ($item = $maisVendidos->fetch_assoc()) : ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['nome']); ?></td>
                                    <td><?php echo (int)$item['quantidade_total']; ?></td>
                                    <td>R$ <?php echo number_format((float)$item['faturamento'], 2, ',', '.'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else : ?>
                            <tr><td colspan="3">Nenhuma venda registrada.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <div class="card-header-row">
                <h3>Produtos cadastrados</h3>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>Categoria</th>
                            <th>Estoque</th>
                            <th>Valor custo</th>
                            <th>Valor venda</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($produtos && $produtos->num_rows > 0) : ?>
                            <?php while ($item = $produtos->fetch_assoc()) : ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['nome']); ?></td>
                                    <td><?php echo htmlspecialchars($item['categoria']); ?></td>
                                    <td><?php echo (int)$item['quantidade']; ?></td>
                                    <td>R$ <?php echo number_format((float)$item['valor_custo'], 2, ',', '.'); ?></td>
                                    <td>R$ <?php echo number_format((float)$item['valor_venda'], 2, ',', '.'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else : ?>
                            <tr><td colspan="5">Nenhum produto cadastrado.</td></tr>
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
