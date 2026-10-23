<?php
/**
 * ===================================================================
 * Sistema Integrado de Gestão Comercial — SIGC (Lee Market)
 * Processador de Vendas do PDV (modules/vendas/processar.php)
 * ===================================================================
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../bd.php';
require_once __DIR__ . '/../../includes/audit.php';

require_permission('vendas_pdv');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/vendas/pdv.php');
}

$csrf = $_POST['csrf_token'] ?? '';
if (!validate_csrf($csrf)) {
    set_toast('error', 'Sessão Expirada', 'A sessão do PDV expirou. Tente novamente.');
    redirect('modules/vendas/pdv.php');
}

$carrinhoJson = $_POST['carrinho_json'] ?? '[]';
$carrinho = json_decode($carrinhoJson, true);

if (empty($carrinho) || !is_array($carrinho)) {
    set_toast('error', 'Carrinho Vazio', 'Nenhum produto foi informado para a venda.');
    redirect('modules/vendas/pdv.php');
}

$pdo = getDBConnection();
$user = current_user();

$desconto = max(0, floatval(str_replace(['.', ','], ['', '.'], $_POST['desconto'] ?? '0')));
$formaPagamento = $_POST['forma_pagamento'] ?? 'dinheiro';
$clienteNome = trim($_POST['cliente_nome'] ?? 'Consumidor Final') ?: 'Consumidor Final';
$clienteCpf = trim($_POST['cliente_cpf'] ?? '') ?: null;

try {
    $pdo->beginTransaction();

    $subtotal = 0.00;
    $itensValidados = [];

    // Validar itens e disponibilidade de estoque
    foreach ($carrinho as $item) {
        $prodId = (int)($item['id'] ?? 0);
        $qtd = (int)($item['quantidade'] ?? 0);

        if ($prodId <= 0 || $qtd <= 0) {
            throw new Exception("Item inválido no carrinho de compras.");
        }

        $stmtP = $pdo->prepare("SELECT * FROM produtos WHERE id = ? AND status = 'ativo'");
        $stmtP->execute([$prodId]);
        $prod = $stmtP->fetch();

        if (!$prod) {
            throw new Exception("O produto '{$item['nome']}' não está mais ativo no sistema.");
        }

        if ($prod['estoque_atual'] < $qtd) {
            throw new Exception("Estoque insuficiente para '{$prod['nome']}'. Disponível: {$prod['estoque_atual']}, Solicitado: {$qtd}.");
        }

        $precoUnit = (float)$prod['preco_venda'];
        $itemSubtotal = $precoUnit * $qtd;
        $subtotal += $itemSubtotal;

        $itensValidados[] = [
            'produto'        => $prod,
            'quantidade'     => $qtd,
            'preco_unitario' => $precoUnit,
            'subtotal'       => $itemSubtotal
        ];
    }

    $total = max(0.00, $subtotal - $desconto);
    $valorPago = floatval($_POST['valor_pago'] ?? $total);
    if ($formaPagamento !== 'dinheiro' || $valorPago < $total) {
        $valorPago = $total;
    }
    $troco = max(0.00, $valorPago - $total);

    // Gerar Código da Venda Ex: VD-20261023-A4B9
    $codigoVenda = 'VD-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));

    // 1. Inserir Registro da Venda
    $stmtVenda = $pdo->prepare("
        INSERT INTO vendas 
        (codigo_venda, usuario_id, cliente_nome, cliente_cpf, subtotal, desconto, total, forma_pagamento, valor_pago, troco, status, data_venda)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'concluida', " . (getDBDriver() === 'sqlite' ? "datetime('now')" : "NOW()") . ")
    ");
    $stmtVenda->execute([
        $codigoVenda,
        $user['id'],
        $clienteNome,
        $clienteCpf,
        $subtotal,
        $desconto,
        $total,
        $formaPagamento,
        $valorPago,
        $troco
    ]);
    $vendaId = (int)$pdo->lastInsertId();

    // 2. Inserir Itens da Venda e Abater Estoque
    $stmtItem = $pdo->prepare("
        INSERT INTO itens_venda (venda_id, produto_id, quantidade, preco_unitario, subtotal)
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmtBaixa = $pdo->prepare("
        UPDATE produtos SET estoque_atual = estoque_atual - ? WHERE id = ?
    ");

    $stmtMov = $pdo->prepare("
        INSERT INTO movimentacoes_estoque (produto_id, usuario_id, tipo, quantidade, motivo, observacao)
        VALUES (?, ?, 'saida', ?, 'Venda PDV', ?)
    ");

    foreach ($itensValidados as $iv) {
        $stmtItem->execute([
            $vendaId,
            $iv['produto']['id'],
            $iv['quantidade'],
            $iv['preco_unitario'],
            $iv['subtotal']
        ]);

        $stmtBaixa->execute([$iv['quantidade'], $iv['produto']['id']]);

        $stmtMov->execute([
            $iv['produto']['id'],
            $user['id'],
            $iv['quantidade'],
            "Cupom Fiscal #{$codigoVenda}"
        ]);
    }

    // 3. Registrar Entrada no Fluxo de Caixa Financeiro
    $stmtCaixa = $pdo->prepare("
        INSERT INTO fluxo_caixa 
        (tipo, categoria, descricao, valor, data_movimento, forma_pagamento, referencia_venda_id, usuario_id)
        VALUES ('entrada', 'Venda PDV', ?, ?, " . (getDBDriver() === 'sqlite' ? "date('now')" : "CURDATE()") . ", ?, ?, ?)
    ");
    $stmtCaixa->execute([
        "Venda PDV Cupom #{$codigoVenda}",
        $total,
        $formaPagamento,
        $vendaId,
        $user['id']
    ]);

    // 4. Log de Auditoria
    log_audit(
        'Vendas',
        'Finalização de Venda',
        "Venda #{$codigoVenda} realizada com sucesso. Total: " . format_money($total) . " via " . strtoupper($formaPagamento) . ". Itens: " . count($itensValidados) . "."
    );

    $pdo->commit();

    set_toast('success', 'Venda Concluída!', "Venda #{$codigoVenda} finalizada com sucesso no PDV.");
    redirect('modules/vendas/comprovante.php?id=' . $vendaId);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    set_toast('error', 'Falha na Venda', $e->getMessage());
    redirect('modules/vendas/pdv.php');
}
