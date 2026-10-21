<?php
session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

require __DIR__ . "/conexao/conexao.php";

exigirPerfilOperacional();

$usuarioEhAdmin = usuarioEhAdmin();

$mensagem = "";
$produtoEditando = null;
$filtro = trim($_GET["q"] ?? "");

if (isset($_GET["delete"])) {
    $id = (int)($_GET["delete"] ?? 0);
    if ($id > 0) {
        $conexao->query("DELETE FROM produtos WHERE id = $id");
        header("Location: produtos.php");
        exit;
    }
}

if (isset($_GET["edit"])) {
    $id = (int)($_GET["edit"] ?? 0);
    if ($id > 0) {
        $resultado = $conexao->query("SELECT * FROM produtos WHERE id = $id LIMIT 1");
        if ($resultado && $resultado->num_rows > 0) {
            $produtoEditando = $resultado->fetch_assoc();
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["tipo_form"])) {
        if ($_POST["tipo_form"] === "produto") {
            $id = (int)($_POST["produto_id"] ?? 0);
            $codigo = trim($_POST["codigo"] ?? "");
            $nome = trim($_POST["nome"] ?? "");
            $categoria = trim($_POST["categoria"] ?? "");
            $quantidade = max((int)($_POST["quantidade"] ?? 0), 0);
            $valorCusto = (float)($_POST["valor_custo"] ?? 0);
            $valorVenda = (float)($_POST["valor_venda"] ?? 0);
            $observacao = trim($_POST["observacao"] ?? "");

            if ($nome !== "" && $categoria !== "") {
                if ($id > 0) {
                    $stmt = $conexao->prepare("UPDATE produtos SET codigo = ?, nome = ?, categoria = ?, quantidade = ?, valor_custo = ?, valor_venda = ?, observacao = ? WHERE id = ?");
                    $stmt->bind_param("sssidssi", $codigo, $nome, $categoria, $quantidade, $valorCusto, $valorVenda, $observacao, $id);
                    $mensagem = "Produto atualizado com sucesso.";
                } else {
                    $stmt = $conexao->prepare("INSERT INTO produtos (codigo, nome, categoria, quantidade, valor_custo, valor_venda, observacao) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssidss", $codigo, $nome, $categoria, $quantidade, $valorCusto, $valorVenda, $observacao);
                    $mensagem = "Produto cadastrado com sucesso.";
                }
                $stmt->execute();
            } else {
                $mensagem = "Informe nome e categoria do produto.";
            }
        }

        if ($_POST["tipo_form"] === "venda") {
            $produtoId = (int)($_POST["produto_id_venda"] ?? 0);
            $quantidade = max((int)($_POST["quantidade_venda"] ?? 0), 0);
            $valorVenda = (float)($_POST["valor_venda_unitario"] ?? 0);
            $marketplace = trim($_POST["marketplace"] ?? "");
            $cliente = trim($_POST["cliente"] ?? "");
            $dataVenda = trim($_POST["data_venda"] ?? date("Y-m-d"));
            $observacao = trim($_POST["observacao_venda"] ?? "");

            if ($produtoId > 0 && $quantidade > 0 && $marketplace !== "" && $valorVenda > 0) {
                $resultadoProduto = $conexao->query("SELECT nome, quantidade, valor_venda FROM produtos WHERE id = $produtoId LIMIT 1");
                $produto = $resultadoProduto ? $resultadoProduto->fetch_assoc() : null;

                if ($produto && (int)$produto["quantidade"] >= $quantidade) {
                    $dataVendaMysql = date("Y-m-d H:i:s", strtotime($dataVenda));
                    $stmt = $conexao->prepare("INSERT INTO vendas_marketplace (marketplace, produto_id, quantidade, valor_venda, data_venda, cliente, observacao) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("siidsss", $marketplace, $produtoId, $quantidade, $valorVenda, $dataVendaMysql, $cliente, $observacao);
                    $stmt->execute();

                    $novoEstoque = (int)$produto["quantidade"] - $quantidade;
                    $update = $conexao->prepare("UPDATE produtos SET quantidade = ?, valor_venda = ? WHERE id = ?");
                    $update->bind_param("idi", $novoEstoque, $valorVenda, $produtoId);
                    $update->execute();

                    $mensagem = "Venda registrada e estoque atualizado.";
                } else {
                    $mensagem = "Estoque insuficiente para esse produto.";
                }
            } else {
                $mensagem = "Preencha corretamente os dados da venda.";
            }
        }
    }
}

$baseProdutos = "SELECT * FROM produtos";
if ($filtro !== "") {
    $filtroSql = $conexao->real_escape_string($filtro);
    $baseProdutos .= " WHERE nome LIKE '%{$filtroSql}%' OR categoria LIKE '%{$filtroSql}%' OR codigo LIKE '%{$filtroSql}%'";
}
$baseProdutos .= " ORDER BY nome ASC";

$produtos = $conexao->query($baseProdutos);
$produtosTabela = $conexao->query($baseProdutos);

$totalProdutos = (int)($conexao->query("SELECT COUNT(*) as total FROM produtos")->fetch_assoc()["total"] ?? 0);
$estoqueDisponivel = (int)($conexao->query("SELECT COALESCE(SUM(quantidade), 0) as total FROM produtos")->fetch_assoc()["total"] ?? 0);
$valorTotalEstoque = (float)($conexao->query("SELECT COALESCE(SUM(quantidade * valor_custo), 0) as total FROM produtos")->fetch_assoc()["total"] ?? 0);
$estoqueBaixo = (int)($conexao->query("SELECT COUNT(*) as total FROM produtos WHERE quantidade <= 5")->fetch_assoc()["total"] ?? 0);
$ultimasVendas = $conexao->query("SELECT v.*, p.nome AS produto_nome FROM vendas_marketplace v LEFT JOIN produtos p ON p.id = v.produto_id ORDER BY v.data_venda DESC LIMIT 10");
$marketplaces = ["Mercado Livre", "Shopee", "Amazon", "Magazine Luiza", "Outros"];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produtos e Acessórios</title>
    <link rel="stylesheet" href="css/estilo.css?v=20260925">
</head>
<body class="theme-light">
    <header>
        <div class="container">
            <div class="brand">Assistência Técnica</div>
            <nav class="nav">
                <a href="painel.php">Painel</a>
                <a href="ordens.php">Ordens</a>
                <a href="clientes.php">Clientes</a>
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
                <p class="eyebrow">Estoque e acessórios</p>
                <h1 class="page-title">Produtos e Acessórios</h1>
            </div>
            <div class="action-row">
                <form method="get" class="search-form" role="search">
                    <input type="search" name="q" value="<?php echo htmlspecialchars($filtro); ?>" placeholder="Buscar por nome, categoria ou código..." aria-label="Pesquisar produtos">
                    <button class="btn btn-primary" type="submit">🔎 Buscar</button>
                </form>
            </div>
        </div>

        <?php if ($mensagem !== "") : ?>
            <div class="alert success"><?php echo htmlspecialchars($mensagem); ?></div>
        <?php endif; ?>

        <section class="grid summary-grid">
            <div class="metric card">
                <span>Produtos</span>
                <strong><?php echo $totalProdutos; ?></strong>
                <small>Total cadastrados</small>
            </div>
            <div class="metric card success">
                <span>Estoque</span>
                <strong><?php echo $estoqueDisponivel; ?></strong>
                <small>Itens disponíveis</small>
            </div>
            <div class="metric card warning">
                <span>Baixo estoque</span>
                <strong><?php echo $estoqueBaixo; ?></strong>
                <small>Até 5 unidades</small>
            </div>
            <div class="metric card purple">
                <span>Valor do estoque</span>
                <strong>R$ <?php echo number_format($valorTotalEstoque, 2, ',', '.'); ?></strong>
                <small>Base em custo</small>
            </div>
        </section>

        <section class="two-column-layout">
            <div class="card">
                <h3><?php echo $produtoEditando ? 'Editar produto' : 'Cadastrar produto'; ?></h3>
                <form method="post" class="form-grid">
                    <input type="hidden" name="tipo_form" value="produto">
                    <input type="hidden" name="produto_id" value="<?php echo (int)($produtoEditando['id'] ?? 0); ?>">

                    <div>
                        <label for="codigo">Código</label>
                        <input id="codigo" name="codigo" value="<?php echo htmlspecialchars($produtoEditando['codigo'] ?? ''); ?>" placeholder="Ex.: CAP-001">
                    </div>
                    <div>
                        <label for="nome">Nome do produto</label>
                        <input id="nome" name="nome" required value="<?php echo htmlspecialchars($produtoEditando['nome'] ?? ''); ?>" placeholder="Carregador, capa, fone...">
                    </div>
                    <div>
                        <label for="categoria">Categoria</label>
                        <input id="categoria" name="categoria" required value="<?php echo htmlspecialchars($produtoEditando['categoria'] ?? ''); ?>" placeholder="Acessório, peça, etc.">
                    </div>
                    <div>
                        <label for="quantidade">Quantidade</label>
                        <input id="quantidade" type="number" min="0" name="quantidade" required value="<?php echo (int)($produtoEditando['quantidade'] ?? 0); ?>">
                    </div>
                    <div>
                        <label for="valor_custo">Valor de custo</label>
                        <input id="valor_custo" type="number" min="0" step="0.01" name="valor_custo" required value="<?php echo number_format((float)($produtoEditando['valor_custo'] ?? 0), 2, '.', ''); ?>">
                    </div>
                    <div>
                        <label for="valor_venda">Valor de venda</label>
                        <input id="valor_venda" type="number" min="0" step="0.01" name="valor_venda" required value="<?php echo number_format((float)($produtoEditando['valor_venda'] ?? 0), 2, '.', ''); ?>">
                    </div>
                    <div class="full-width">
                        <label for="observacao">Observação</label>
                        <textarea id="observacao" name="observacao" rows="3" placeholder="Detalhes do item, fornecedor ou observações..."><?php echo htmlspecialchars($produtoEditando['observacao'] ?? ''); ?></textarea>
                    </div>

                    <div class="full-width actions-row">
                        <button class="btn btn-primary" type="submit"><?php echo $produtoEditando ? 'Salvar alterações' : 'Cadastrar produto'; ?></button>
                        <?php if ($produtoEditando) : ?>
                            <a class="btn btn-secondary" href="produtos.php">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Registrar venda por marketplace</h3>
                <form method="post" class="form-grid">
                    <input type="hidden" name="tipo_form" value="venda">
                    <div>
                        <label for="produto_id_venda">Produto</label>
                        <select id="produto_id_venda" name="produto_id_venda" required>
                            <option value="">Selecione</option>
                            <?php while ($item = $produtosTabela->fetch_assoc()) : ?>
                                <option value="<?php echo (int)$item['id']; ?>"><?php echo htmlspecialchars($item['nome']); ?> (Estoque: <?php echo (int)$item['quantidade']; ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label for="marketplace">Marketplace</label>
                        <select id="marketplace" name="marketplace" required>
                            <option value="">Selecione</option>
                            <?php foreach ($marketplaces as $marketplace) : ?>
                                <option value="<?php echo htmlspecialchars($marketplace); ?>"><?php echo htmlspecialchars($marketplace); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="quantidade_venda">Quantidade</label>
                        <input id="quantidade_venda" type="number" min="1" name="quantidade_venda" required value="1">
                    </div>
                    <div>
                        <label for="valor_venda_unitario">Valor da venda</label>
                        <input id="valor_venda_unitario" type="number" min="0" step="0.01" name="valor_venda_unitario" required value="0.00">
                    </div>
                    <div>
                        <label for="cliente">Cliente (opcional)</label>
                        <input id="cliente" name="cliente" placeholder="Nome do cliente">
                    </div>
                    <div>
                        <label for="data_venda">Data da venda</label>
                        <input id="data_venda" type="date" name="data_venda" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="full-width">
                        <label for="observacao_venda">Observação</label>
                        <textarea id="observacao_venda" name="observacao_venda" rows="3" placeholder="Detalhes da venda..."></textarea>
                    </div>
                    <div class="full-width actions-row">
                        <button class="btn btn-primary" type="submit">Registrar venda</button>
                    </div>
                </form>
            </div>
        </section>

        <section class="card table-card">
            <div class="card-header-row">
                <h3>Produtos cadastrados</h3>
                <div class="export-links">
                    <a class="btn btn-secondary" href="produtos.php?export=estoque">Exportar Excel</a>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nome</th>
                            <th>Categoria</th>
                            <th>Estoque</th>
                            <th>Custo</th>
                            <th>Venda</th>
                            <th>Cadastro</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($produtos && $produtos->num_rows > 0) : ?>
                            <?php while ($produto = $produtos->fetch_assoc()) : ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($produto['codigo'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($produto['nome']); ?></td>
                                    <td><?php echo htmlspecialchars($produto['categoria']); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo ((int)$produto['quantidade'] <= 5) ? 'aguardando-peça' : 'concluído'; ?>">
                                            <?php echo (int)$produto['quantidade']; ?>
                                        </span>
                                    </td>
                                    <td>R$ <?php echo number_format((float)$produto['valor_custo'], 2, ',', '.'); ?></td>
                                    <td>R$ <?php echo number_format((float)$produto['valor_venda'], 2, ',', '.'); ?></td>
                                    <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($produto['data_cadastro']))); ?></td>
                                    <td class="table-actions">
                                        <a href="produtos.php?edit=<?php echo (int)$produto['id']; ?>" class="btn btn-small btn-secondary">Editar</a>
                                        <a href="produtos.php?delete=<?php echo (int)$produto['id']; ?>" class="btn btn-small btn-danger" onclick="return confirm('Deseja excluir este produto?')">Excluir</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else : ?>
                            <tr><td colspan="8">Nenhum produto cadastrado.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <div class="card-header-row">
                <h3>Últimas vendas por marketplace</h3>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Marketplace</th>
                        <th>Produto</th>
                        <th>Qtd</th>
                        <th>Valor</th>
                        <th>Data</th>
                        <th>Cliente</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($ultimasVendas && $ultimasVendas->num_rows > 0) : ?>
                        <?php while ($venda = $ultimasVendas->fetch_assoc()) : ?>
                            <tr>
                                <td><?php echo htmlspecialchars($venda['marketplace']); ?></td>
                                <td><?php echo htmlspecialchars($venda['produto_nome'] ?? '-'); ?></td>
                                <td><?php echo (int)$venda['quantidade']; ?></td>
                                <td>R$ <?php echo number_format((float)$venda['valor_venda'], 2, ',', '.'); ?></td>
                                <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($venda['data_venda']))); ?></td>
                                <td><?php echo htmlspecialchars($venda['cliente'] ?: '-'); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else : ?>
                        <tr><td colspan="6">Nenhuma venda registrada.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
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

    <?php
    if (isset($_GET['export']) && $_GET['export'] === 'estoque') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="estoque_produtos.csv"');
        echo "Codigo,Nome,Categoria,Quantidade,Valor_Custo,Valor_Venda,Data_Cadastro\n";

        $resultadoExport = $conexao->query($baseProdutos);
        while ($linha = $resultadoExport->fetch_assoc()) {
            echo sprintf(
                '"%s","%s","%s","%s","%s","%s","%s"\n',
                str_replace('"', '""', $linha['codigo'] ?? ''),
                str_replace('"', '""', $linha['nome'] ?? ''),
                str_replace('"', '""', $linha['categoria'] ?? ''),
                (int)($linha['quantidade'] ?? 0),
                number_format((float)($linha['valor_custo'] ?? 0), 2, '.', ''),
                number_format((float)($linha['valor_venda'] ?? 0), 2, '.', ''),
                date('d/m/Y', strtotime($linha['data_cadastro']))
            );
        }
        exit;
    }
    ?>
</body>
</html>
