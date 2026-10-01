
<?php

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/conexao.php";

// Função para retornar respostas em JSON
function responder($codigo, $dados)
{
    http_response_code($codigo);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

// Identifica o método HTTP
$metodo = $_SERVER["REQUEST_METHOD"];

// Recebe os dados enviados em JSON
$dados = [];

if ($metodo == "POST" || $metodo == "PUT") {
    $corpo = file_get_contents("php://input");
    $dados = json_decode($corpo, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($dados)) {
        responder(400, [
            "sucesso" => false,
            "mensagem" => "JSON inválido ou não informado."
        ]);
    }
}

// Valida os campos obrigatórios
function validarCampos($dados, $campos)
{
    foreach ($campos as $campo) {
        if (!isset($dados[$campo])) {
            responder(400, [
                "sucesso" => false,
                "mensagem" => "O campo $campo é obrigatório."
            ]);
        }

        if ($campo == "id") {
            if (
                !is_int($dados[$campo]) &&
                !is_string($dados[$campo])
            ) {
                responder(400, [
                    "sucesso" => false,
                    "mensagem" => "ID inválido."
                ]);
            }

            if (
                trim((string) $dados[$campo]) == "" ||
                filter_var($dados[$campo], FILTER_VALIDATE_INT) === false ||
                (int) $dados[$campo] <= 0
            ) {
                responder(400, [
                    "sucesso" => false,
                    "mensagem" => "ID inválido."
                ]);
            }
        } else {
            if (
                !is_string($dados[$campo]) ||
                trim($dados[$campo]) == ""
            ) {
                responder(400, [
                    "sucesso" => false,
                    "mensagem" => "O campo $campo é obrigatório."
                ]);
            }
        }
    }
}

// Valida prioridade e status
function validarOpcoes($prioridade, $status)
{
    $prioridades = ["baixa", "media", "alta"];

    $statusPermitidos = [
        "aberto",
        "em andamento",
        "concluido"
    ];

    if (!in_array($prioridade, $prioridades, true)) {
        responder(400, [
            "sucesso" => false,
            "mensagem" => "Prioridade inválida."
        ]);
    }

    if (!in_array($status, $statusPermitidos, true)) {
        responder(400, [
            "sucesso" => false,
            "mensagem" => "Status inválido."
        ]);
    }
}

// GET: listar todos os chamados
if ($metodo == "GET") {

    $consulta = $pdo->query(
        "SELECT id, equipamento, setor, descricao, prioridade, status
         FROM chamados
         ORDER BY id DESC"
    );

    responder(200, [
        "sucesso" => true,
        "mensagem" => "Chamados listados com sucesso.",
        "dados" => $consulta->fetchAll()
    ]);
}

// POST: cadastrar chamado
elseif ($metodo == "POST") {

    validarCampos($dados, [
        "equipamento",
        "setor",
        "descricao",
        "prioridade"
    ]);

    $status = $dados["status"] ?? "aberto";

    if (!is_string($status) || trim($status) == "") {
        responder(400, [
            "sucesso" => false,
            "mensagem" => "Status inválido."
        ]);
    }

    validarOpcoes($dados["prioridade"], $status);

    $sql = "INSERT INTO chamados
            (equipamento, setor, descricao, prioridade, status)
            VALUES
            (:equipamento, :setor, :descricao, :prioridade, :status)
            RETURNING id";

    $consulta = $pdo->prepare($sql);

    $consulta->execute([
        ":equipamento" => trim($dados["equipamento"]),
        ":setor" => trim($dados["setor"]),
        ":descricao" => trim($dados["descricao"]),
        ":prioridade" => $dados["prioridade"],
        ":status" => $status
    ]);

    $id = $consulta->fetchColumn();

    responder(201, [
        "sucesso" => true,
        "mensagem" => "Chamado cadastrado com sucesso.",
        "id" => (int) $id
    ]);
}

// PUT: atualizar chamado pelo ID
elseif ($metodo == "PUT") {

    validarCampos($dados, [
        "id",
        "equipamento",
        "setor",
        "descricao",
        "prioridade",
        "status"
    ]);

    validarOpcoes($dados["prioridade"], $dados["status"]);

    $sql = "UPDATE chamados SET
            equipamento = :equipamento,
            setor = :setor,
            descricao = :descricao,
            prioridade = :prioridade,
            status = :status
            WHERE id = :id";

    $consulta = $pdo->prepare($sql);

    $consulta->execute([
        ":equipamento" => trim($dados["equipamento"]),
        ":setor" => trim($dados["setor"]),
        ":descricao" => trim($dados["descricao"]),
        ":prioridade" => $dados["prioridade"],
        ":status" => $dados["status"],
        ":id" => (int) $dados["id"]
    ]);

    $verifica = $pdo->prepare(
        "SELECT id FROM chamados WHERE id = :id"
    );

    $verifica->execute([
        ":id" => (int) $dados["id"]
    ]);

    if (!$verifica->fetch()) {
        responder(404, [
            "sucesso" => false,
            "mensagem" => "Chamado não encontrado."
        ]);
    }

    responder(200, [
        "sucesso" => true,
        "mensagem" => "Chamado atualizado com sucesso."
    ]);
}

// DELETE: excluir chamado pelo ID
elseif ($metodo == "DELETE") {

    if (
        !isset($_GET["id"]) ||
        is_array($_GET["id"]) ||
        filter_var($_GET["id"], FILTER_VALIDATE_INT) === false ||
        (int) $_GET["id"] <= 0
    ) {
        responder(400, [
            "sucesso" => false,
            "mensagem" => "Informe um ID válido na URL."
        ]);
    }

    $id = (int) $_GET["id"];

    $consulta = $pdo->prepare(
        "DELETE FROM chamados WHERE id = :id"
    );

    $consulta->execute([":id" => $id]);

    if ($consulta->rowCount() == 0) {
        responder(404, [
            "sucesso" => false,
            "mensagem" => "Chamado não encontrado."
        ]);
    }

    responder(200, [
        "sucesso" => true,
        "mensagem" => "Chamado excluído com sucesso."
    ]);
}

// Método HTTP não permitido
else {

    header("Allow: GET, POST, PUT, DELETE");

    responder(405, [
        "sucesso" => false,
        "mensagem" => "Método HTTP não permitido."
    ]);
}