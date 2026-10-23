<?php
/**
 * ===================================================================
 * Sistema Integrado de Gestão Comercial — SIGC (Lee Market)
 * Exclusão / Desativação de Produto (modules/produtos/excluir.php)
 * ===================================================================
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../bd.php';
require_once __DIR__ . '/../../includes/audit.php';

require_permission('produtos_excluir');

$id = (int)($_GET['id'] ?? 0);
$csrf = $_GET['csrf_token'] ?? '';

if (!validate_csrf($csrf) || $id <= 0) {
    set_toast('error', 'Ação Inválida', 'Requisição não autorizada ou token de segurança inválido.');
    redirect('modules/produtos/index.php');
}

$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT * FROM produtos WHERE id = ?");
$stmt->execute([$id]);
$produto = $stmt->fetch();

if (!$produto) {
    set_toast('error', 'Não Encontrado', 'O produto selecionado não foi localizado.');
    redirect('modules/produtos/index.php');
}

try {
    // Desativação lógica para não quebrar integridade com vendas passadas
    $delStmt = $pdo->prepare("UPDATE produtos SET status = 'inativo' WHERE id = ?");
    $delStmt->execute([$id]);

    log_audit(
        'Produtos',
        'Exclusão de Produto',
        "Removeu o produto '{$produto['nome']}' (Cód: {$produto['codigo_barras']}) do catálogo ativo."
    );

    set_toast('success', 'Produto Removido', "O produto '{$produto['nome']}' foi removido do catálogo com sucesso.");
} catch (Throwable $e) {
    set_toast('error', 'Erro ao Remover', 'Não foi possível remover o produto: ' . $e->getMessage());
}

redirect('modules/produtos/index.php');
