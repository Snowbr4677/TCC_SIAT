<?php
session_start();

require __DIR__ . "/conexao/conexao.php";

exigirPerfilAdmin();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

$tipo = $_GET['tipo'] ?? 'xlsx';
$anoAtual = date('Y');

$sql = "SELECT
    DATE_FORMAT(v.data_venda, '%Y-%m') AS mes,
    SUM(v.quantidade * v.valor_venda) AS faturamento,
    COUNT(*) AS qtd_vendas,
    COALESCE(SUM((p.valor_custo * v.quantidade)), 0) AS custo_total,
    COALESCE(SUM((v.valor_venda * v.quantidade) - (p.valor_custo * v.quantidade)), 0) AS lucro
    FROM vendas_marketplace v
    LEFT JOIN produtos p ON p.id = v.produto_id
    WHERE YEAR(v.data_venda) = {$anoAtual}
    GROUP BY DATE_FORMAT(v.data_venda, '%Y-%m')
    ORDER BY DATE_FORMAT(v.data_venda, '%Y-%m') ASC";

$result = $conexao->query($sql);

if ($tipo === 'pdf') {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="relatorio_financeiro.pdf"');
    echo "Relatório financeiro\n\n";
    echo "Ano: {$anoAtual}\n\n";
    while ($linha = $result->fetch_assoc()) {
        echo $linha['mes'] . " | Vendas: " . $linha['qtd_vendas'] . " | Faturamento: R$ " . number_format((float)$linha['faturamento'], 2, ',', '.') . " | Lucro: R$ " . number_format((float)$linha['lucro'], 2, ',', '.') . "\n";
    }
    exit;
}

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="relatorio_financeiro.csv"');

$csv = "Mes,Qtd_Vendas,Faturamento,Custo_Total,Lucro\n";
while ($linha = $result->fetch_assoc()) {
    $csv .= $linha['mes'] . ',' . $linha['qtd_vendas'] . ',' . number_format((float)$linha['faturamento'], 2, '.', '') . ',' . number_format((float)$linha['custo_total'], 2, '.', '') . ',' . number_format((float)$linha['lucro'], 2, '.', '') . "\n";
}
echo $csv;
exit;
