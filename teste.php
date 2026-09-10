<?php

require __DIR__ . "/conexao/conexao.php";

if ($conexao) {
    echo "<h1>Conexão realizada com sucesso!</h1>";
    echo "<h1><br>Banco: " . $dbName;
    mysqli_close($conexao);
} else {
    echo "Não foi possível conectar ao banco de dados.<br>";
    echo $mensagemErroConexao ?? "Verifique se o MySQL está em execução e se o banco 'assistencia_tecnica' foi criado.";
    echo "<br>Crie o banco 'assistencia_tecnica' no MySQL e tente novamente.";
}

?>