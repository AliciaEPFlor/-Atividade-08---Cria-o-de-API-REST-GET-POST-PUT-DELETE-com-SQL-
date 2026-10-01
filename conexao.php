
<?php

$host = "192.168.10.11";
$porta = "5432";
$banco = "manutencao";
$usuario = "postgres";
$senha = "Senai2822@";

try {
    $pdo = new PDO(
        "pgsql:host=$host;port=$porta;dbname=$banco",
        $usuario,
        $senha
    );

    $pdo->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    $pdo->setAttribute(
        PDO::ATTR_DEFAULT_FETCH_MODE,
        PDO::FETCH_ASSOC
    );

} catch (PDOException $e) {
    header("Content-Type: application/json; charset=utf-8");
    http_response_code(500);

    echo json_encode([
        "sucesso" => false,
        "mensagem" => "Erro ao conectar ao banco de dados."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}