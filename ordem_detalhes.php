<?php
session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

require __DIR__ . "/conexao/conexao.php";

$conexao->query("CREATE TABLE IF NOT EXISTS historico_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ordem_id INT NOT NULL,
    status VARCHAR(50) NOT NULL,
    descricao TEXT,
    data_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ordem_id) REFERENCES ordens_servico(id) ON DELETE CASCADE
)");

$id = (int)($_GET["id"] ?? 0);
$mensagem = "";
$ordem = null;
$historico = [];

if ($id > 0) {
    $ordemResult = $conexao->query(
        "SELECT os.*, c.nome AS cliente_nome, c.telefone AS cliente_telefone, c.email AS cliente_email, a.marca, a.modelo, a.imei, a.defeito
         FROM ordens_servico os
         LEFT JOIN clientes c ON c.id = os.cliente_id
         LEFT JOIN aparelhos a ON a.id = os.aparelho_id
         WHERE os.id = $id LIMIT 1"
    );

    if ($ordemResult && $ordemResult->num_rows > 0) {
        $ordem = $ordemResult->fetch_assoc();
    }

    $historicoResult = $conexao->query(
        "SELECT hs.*, os.status_os
         FROM historico_status hs
         LEFT JOIN ordens_servico os ON os.id = hs.ordem_id
         WHERE hs.ordem_id = $id
         ORDER BY hs.data_hora DESC"
    );

    if ($historicoResult) {
        while ($item = $historicoResult->fetch_assoc()) {
            $historico[] = $item;
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $id > 0) {
    $novoStatus = trim($_POST["status_os"] ?? "");
    $observacao = trim($_POST["observacao"] ?? "");

    if ($novoStatus !== "") {
        $stmt = $conexao->prepare("UPDATE ordens_servico SET status_os = ? WHERE id = ?");
        $stmt->bind_param("si", $novoStatus, $id);
        $stmt->execute();

        $descricao = $observacao !== "" ? $observacao : "Status atualizado para {$novoStatus}.";
        $histStmt = $conexao->prepare("INSERT INTO historico_status (ordem_id, status, descricao, data_hora) VALUES (?, ?, ?, NOW())");
        $histStmt->bind_param("iss", $id, $novoStatus, $descricao);
        $histStmt->execute();

        $mensagem = "Status atualizado com sucesso.";
        header("Location: ordem_detalhes.php?id=$id");
        exit;
    } else {
        $mensagem = "Informe o novo status.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes da Ordem</title>
    <link rel="stylesheet" href="css/estilo.css?v=20260909">
</head>
<body>
    <header>
        <div class="container">
            <div class="brand">Assistência Técnica</div>
            <nav class="nav">
                <a href="painel.php">Painel</a>
                <a href="ordens.php">Ordens</a>
                <a href="logout.php">Sair</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <?php if ($mensagem !== "") : ?>
            <div class="alert success"><?php echo htmlspecialchars($mensagem); ?></div>
        <?php endif; ?>

        <?php if (!$ordem) : ?>
            <div class="card">
                <h2>Ordem não encontrada.</h2>
                <a class="link-button" href="ordens.php">Voltar</a>
            </div>
        <?php else : ?>
            <div class="card">
                <h2 class="page-title">Detalhes da Ordem #<?php echo (int)$ordem["id"]; ?></h2>
                <div class="inline-actions">
                    <span class="status-badge <?php echo strtolower(str_replace(' ', '-', $ordem["status_os"])); ?>"><?php echo htmlspecialchars($ordem["status_os"] ?? "Recebido"); ?></span>
                    <a class="link-button" href="ordens.php?id=<?php echo (int)$ordem["id"]; ?>">Editar</a>
                    <a class="link-button" href="ordens.php">Voltar</a>
                </div>
            </div>

            <div class="grid">
                <div class="card">
                    <h3>Cliente</h3>
                    <p><strong>Nome:</strong> <?php echo htmlspecialchars($ordem["cliente_nome"] ?? "-"); ?></p>
                    <p><strong>Telefone:</strong> <?php echo htmlspecialchars($ordem["cliente_telefone"] ?? "-"); ?></p>
                    <p><strong>E-mail:</strong> <?php echo htmlspecialchars($ordem["cliente_email"] ?? "-"); ?></p>
                </div>

                <div class="card">
                    <h3>Aparelho</h3>
                    <p><strong>Marca:</strong> <?php echo htmlspecialchars($ordem["marca"] ?? "-"); ?></p>
                    <p><strong>Modelo:</strong> <?php echo htmlspecialchars($ordem["modelo"] ?? "-"); ?></p>
                    <p><strong>IMEI:</strong> <?php echo htmlspecialchars($ordem["imei"] ?? "-"); ?></p>
                </div>
            </div>

            <div class="card">
                <h3>Defeito</h3>
                <p><?php echo nl2br(htmlspecialchars($ordem["defeito"] ?? "-")); ?></p>
                <p><strong>Valor:</strong> R$ <?php echo htmlspecialchars((string)($ordem["valor"] ?? "0.00")); ?></p>
                <p><strong>Data de entrada:</strong> <?php echo htmlspecialchars($ordem["data_entrada"] ?? "-"); ?></p>
            </div>

            <div class="card">
                <h3>Atualizar status</h3>
                <form method="post" action="ordem_detalhes.php?id=<?php echo (int)$ordem["id"]; ?>">
                    <div>
                        <label>Nova situação:</label>
                        <select name="status_os">
                            <?php foreach (["Recebido", "Em análise", "Aguardando peça", "Concluído"] as $status) : ?>
                                <option value="<?php echo $status; ?>" <?php echo ($ordem["status_os"] ?? "Recebido") === $status ? "selected" : ""; ?>>
                                    <?php echo $status; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Observação:</label>
                        <textarea name="observacao" placeholder="Descreva a atualização do status..."></textarea>
                    </div>

                    <button type="submit" class="btn">Salvar status</button>
                </form>
            </div>

            <div class="card">
                <h3>Histórico de status</h3>
                <?php if (empty($historico)) : ?>
                    <p>Nenhuma atualização registrada.</p>
                <?php else : ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Status</th>
                                <th>Descrição</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historico as $item) : ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item["data_hora"] ?? "-"); ?></td>
                                    <td><?php echo htmlspecialchars($item["status"] ?? "-"); ?></td>
                                    <td><?php echo nl2br(htmlspecialchars($item["descricao"] ?? "-")); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
