<?php
/**
 * ===================================================================
 * Sistema Integrado de Gestão Comercial — SIGC (Lee Market)
 * Comprovante / Cupom de Venda (modules/vendas/comprovante.php)
 * ===================================================================
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../bd.php';

require_permission('vendas_historico');

$id = (int)($_GET['id'] ?? 0);
$pdo = getDBConnection();

$stmt = $pdo->prepare("
    SELECT v.*, u.nome_completo as operador_nome 
    FROM vendas v 
    JOIN usuarios u ON v.usuario_id = u.id 
    WHERE v.id = ? LIMIT 1
");
$stmt->execute([$id]);
$venda = $stmt->fetch();

if (!$venda) {
    set_toast('error', 'Não Encontrado', 'A venda solicitada não foi localizada.');
    redirect('modules/vendas/historico.php');
}

$stmtItens = $pdo->prepare("
    SELECT iv.*, p.nome as produto_nome, p.codigo_barras, p.unidade_medida 
    FROM itens_venda iv 
    JOIN produtos p ON iv.produto_id = p.id 
    WHERE iv.venda_id = ?
");
$stmtItens->execute([$id]);
$itens = $stmtItens->fetchAll();

$pageTitle = 'Comprovante da Venda #' . $venda['codigo_venda'];
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="max-w-xl mx-auto space-y-6">

    <!-- Ações Superiores (Não impressas) -->
    <div class="no-print flex justify-between items-center bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-sm">
        <a href="<?= base_url('modules/vendas/historico.php') ?>" class="text-xs font-bold text-slate-500 hover:text-black flex items-center gap-1.5">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Histórico de Vendas</span>
        </a>

        <div class="flex gap-2">
            <button type="button" onclick="window.print()" class="btn-lee-primary text-xs py-2 px-3.5">
                <i data-lucide="printer" class="w-4 h-4"></i>
                <span>Imprimir Cupom</span>
            </button>
            <?php if (can_access('vendas_pdv')): ?>
                <a href="<?= base_url('modules/vendas/pdv.php') ?>" class="btn-lee-accent text-xs py-2 px-3.5">
                    <i data-lucide="plus" class="w-4 h-4 text-black"></i>
                    <span>Nova Venda</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Cupom Fiscal Estilizado (Imprimível) -->
    <div id="cupom-impressao" class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-lg text-slate-800 font-mono text-xs">
        
        <!-- Cabeçalho do Supermercado -->
        <div class="text-center pb-4 border-b-2 border-dashed border-slate-300">
            <div class="flex items-center justify-center gap-1 mb-1">
                <span class="text-xl font-black text-[#111184]">LEE</span>
                <span class="text-xl font-black text-[#FFD32C] bg-[#111184] px-1 rounded">MARKET</span>
            </div>
            <p class="font-bold text-slate-900 text-sm">SUPERMERCADOS LEE MARKET LTDA</p>
            <p class="text-[11px] text-slate-500">CNPJ: 12.345.678/0001-99 &bull; I.E.: 123.456.789.111</p>
            <p class="text-[11px] text-slate-500">Av. Central do Comércio, 1000 - Centro</p>
            <p class="text-[11px] text-slate-500">Tel: (11) 3333-4444</p>
        </div>

        <!-- Dados da Venda -->
        <div class="py-3 border-b border-dashed border-slate-200 space-y-1 text-[11px]">
            <div class="flex justify-between">
                <span>CUPOM NÃO FISCAL:</span>
                <strong class="font-bold text-slate-900">#<?= htmlspecialchars($venda['codigo_venda']) ?></strong>
            </div>
            <div class="flex justify-between">
                <span>DATA / HORA:</span>
                <span><?= format_date($venda['data_venda']) ?></span>
            </div>
            <div class="flex justify-between">
                <span>OPERADOR(A):</span>
                <span><?= htmlspecialchars($venda['operador_nome']) ?></span>
            </div>
            <div class="flex justify-between">
                <span>CLIENTE:</span>
                <span><?= htmlspecialchars($venda['cliente_nome']) ?></span>
            </div>
            <?php if (!empty($venda['cliente_cpf'])): ?>
                <div class="flex justify-between">
                    <span>CPF NA NOTA:</span>
                    <span><?= format_cpf($venda['cliente_cpf']) ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tabela de Itens -->
        <div class="py-3 border-b-2 border-dashed border-slate-300">
            <div class="grid grid-cols-12 text-[10px] font-bold uppercase text-slate-500 pb-1.5 border-b border-slate-200">
                <div class="col-span-6">ITEM / PRODUTO</div>
                <div class="col-span-2 text-center">QTD</div>
                <div class="col-span-2 text-right">VL UN</div>
                <div class="col-span-2 text-right">TOTAL</div>
            </div>

            <div class="divide-y divide-slate-100 py-1">
                <?php foreach ($itens as $i => $item): ?>
                    <div class="grid grid-cols-12 py-1.5 text-[11px] items-center">
                        <div class="col-span-6 pr-1">
                            <span class="font-bold block truncate"><?= htmlspecialchars($item['produto_nome']) ?></span>
                            <span class="text-[9px] text-slate-400 font-mono"><?= htmlspecialchars($item['codigo_barras']) ?></span>
                        </div>
                        <div class="col-span-2 text-center font-bold">
                            <?= $item['quantidade'] ?> <?= $item['unidade_medida'] ?>
                        </div>
                        <div class="col-span-2 text-right text-slate-600">
                            <?= number_format($item['preco_unitario'], 2, ',', '.') ?>
                        </div>
                        <div class="col-span-2 text-right font-black text-slate-900">
                            <?= number_format($item['subtotal'], 2, ',', '.') ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Totais da Venda -->
        <div class="py-3 border-b border-dashed border-slate-200 space-y-1.5 text-[11px]">
            <div class="flex justify-between">
                <span>SUBTOTAL:</span>
                <span><?= format_money($venda['subtotal']) ?></span>
            </div>
            <?php if ((float)$venda['desconto'] > 0): ?>
                <div class="flex justify-between text-emerald-600">
                    <span>DESCONTO:</span>
                    <span>- <?= format_money($venda['desconto']) ?></span>
                </div>
            <?php endif; ?>
            <div class="flex justify-between text-base font-black text-[#111184] pt-1 border-t border-slate-200">
                <span>TOTAL A PAGAR:</span>
                <span><?= format_money($venda['total']) ?></span>
            </div>
        </div>

        <!-- Forma de Pagamento e Troco -->
        <div class="py-3 border-b-2 border-dashed border-slate-300 space-y-1 text-[11px]">
            <div class="flex justify-between">
                <span>FORMA PAGAMENTO:</span>
                <strong class="uppercase"><?= htmlspecialchars(str_replace('_', ' ', $venda['forma_pagamento'])) ?></strong>
            </div>
            <div class="flex justify-between">
                <span>VALOR PAGO:</span>
                <span><?= format_money($venda['valor_pago']) ?></span>
            </div>
            <?php if ((float)$venda['troco'] > 0): ?>
                <div class="flex justify-between font-bold text-emerald-700">
                    <span>TROCO:</span>
                    <span><?= format_money($venda['troco']) ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Rodapé do Cupom -->
        <div class="text-center pt-4 space-y-1 text-[10px] text-slate-500">
            <p class="font-bold text-slate-700">OBRIGADO PELA PREFERÊNCIA!</p>
            <p>Volte sempre ao Lee Market!</p>
            <p class="text-[9px] text-slate-400 pt-2 font-mono">SIGC &bull; Sistema Integrado de Gestão Comercial</p>
        </div>

    </div>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
