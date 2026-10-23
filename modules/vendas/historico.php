<?php
/**
 * ===================================================================
 * Sistema Integrado de Gestão Comercial — SIGC (Lee Market)
 * Histórico de Vendas Realizadas (modules/vendas/historico.php)
 * ===================================================================
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../bd.php';

require_permission('vendas_historico');

$pdo = getDBConnection();
$user = current_user();
$driver = getDBDriver();

$dataInicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-7 days'));
$dataFim = $_GET['data_fim'] ?? date('Y-m-d');
$forma = $_GET['forma'] ?? '';
$busca = trim($_GET['q'] ?? '');

$sql = "SELECT v.*, u.nome_completo as operador_nome 
        FROM vendas v 
        JOIN usuarios u ON v.usuario_id = u.id 
        WHERE 1=1";
$params = [];

if (!empty($dataInicio)) {
    if ($driver === 'sqlite') {
        $sql .= " AND strftime('%Y-%m-%d', v.data_venda) >= ?";
    } else {
        $sql .= " AND DATE(v.data_venda) >= ?";
    }
    $params[] = $dataInicio;
}

if (!empty($dataFim)) {
    if ($driver === 'sqlite') {
        $sql .= " AND strftime('%Y-%m-%d', v.data_venda) <= ?";
    } else {
        $sql .= " AND DATE(v.data_venda) <= ?";
    }
    $params[] = $dataFim;
}

if (!empty($forma)) {
    $sql .= " AND v.forma_pagamento = ?";
    $params[] = $forma;
}

if (!empty($busca)) {
    $sql .= " AND (v.codigo_venda LIKE ? OR v.cliente_nome LIKE ? OR v.cliente_cpf LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

$sql .= " ORDER BY v.data_venda DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vendas = $stmt->fetchAll();

// Métricas do Período
$totalVendas = count($vendas);
$valorTotal = array_sum(array_column($vendas, 'total'));
$ticketMedio = $totalVendas > 0 ? ($valorTotal / $totalVendas) : 0;

$pageTitle = 'Histórico de Vendas';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="space-y-6">

    <!-- Cabeçalho -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-5 sm:p-6 rounded-2xl border border-slate-200 shadow-sm">
        <div>
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-[#111184]"></span>
                <h1 class="text-xl sm:text-2xl font-black text-[#111184]">Histórico de Vendas</h1>
            </div>
            <p class="text-xs text-slate-500 mt-1">Consulte todos os cupons de vendas emitidos no supermercado Lee Market.</p>
        </div>

        <?php if (can_access('vendas_pdv')): ?>
            <a href="<?= base_url('modules/vendas/pdv.php') ?>" class="btn-lee-accent text-sm w-full sm:w-auto">
                <i data-lucide="scan-barcode" class="w-4 h-4 text-black"></i>
                <span>Frente de Caixa (PDV)</span>
            </a>
        <?php endif; ?>
    </div>

    <!-- Filtros de Busca -->
    <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-sm">
        <form method="GET" action="<?= base_url('modules/vendas/historico.php') ?>" class="grid grid-cols-1 sm:grid-cols-12 gap-3 text-xs">
            
            <div class="sm:col-span-3">
                <label for="data_inicio" class="block font-bold text-slate-500 uppercase tracking-wider mb-1">Data Início</label>
                <input type="date" id="data_inicio" name="data_inicio" value="<?= htmlspecialchars($dataInicio) ?>" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl font-semibold">
            </div>

            <div class="sm:col-span-3">
                <label for="data_fim" class="block font-bold text-slate-500 uppercase tracking-wider mb-1">Data Fim</label>
                <input type="date" id="data_fim" name="data_fim" value="<?= htmlspecialchars($dataFim) ?>" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl font-semibold">
            </div>

            <div class="sm:col-span-3">
                <label for="forma" class="block font-bold text-slate-500 uppercase tracking-wider mb-1">Pagamento</label>
                <select id="forma" name="forma" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl font-semibold">
                    <option value="">Todas as Formas</option>
                    <option value="dinheiro" <?= $forma === 'dinheiro' ? 'selected' : '' ?>>Dinheiro</option>
                    <option value="pix" <?= $forma === 'pix' ? 'selected' : '' ?>>PIX</option>
                    <option value="cartao_debito" <?= $forma === 'cartao_debito' ? 'selected' : '' ?>>Cartão Débito</option>
                    <option value="cartao_credito" <?= $forma === 'cartao_credito' ? 'selected' : '' ?>>Cartão Crédito</option>
                </select>
            </div>

            <div class="sm:col-span-3">
                <label for="q" class="block font-bold text-slate-500 uppercase tracking-wider mb-1">Buscar Cupom / Cliente</label>
                <div class="flex gap-2">
                    <input type="text" id="q" name="q" value="<?= htmlspecialchars($busca) ?>" placeholder="#VD... ou Nome" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl font-semibold">
                    <button type="submit" class="btn-lee-primary px-3 py-2">
                        <i data-lucide="filter" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>

        </form>
    </div>

    <!-- Indicadores Rápidos do Período -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block">Vendas Realizadas</span>
            <span class="text-2xl font-black text-[#111184]"><?= $totalVendas ?></span>
            <span class="text-xs text-slate-400 block mt-0.5">no período filtrado</span>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block">Total Arrecadado</span>
            <span class="text-2xl font-black text-emerald-600"><?= format_money($valorTotal) ?></span>
            <span class="text-xs text-slate-400 block mt-0.5">receita líquida de vendas</span>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block">Ticket Médio</span>
            <span class="text-2xl font-black text-slate-900"><?= format_money($ticketMedio) ?></span>
            <span class="text-xs text-slate-400 block mt-0.5">por cupom emitido</span>
        </div>
    </div>

    <!-- Tabela de Vendas -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        
        <?php if (empty($vendas)): ?>
            <div class="py-12 text-center text-slate-400">
                <i data-lucide="receipt" class="w-12 h-12 mx-auto text-slate-300 mb-2"></i>
                <h3 class="font-bold text-slate-700">Nenhuma venda encontrada</h3>
                <p class="text-xs text-slate-400 mt-1">Ajuste os filtros de data para expandir o histórico.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider border-b border-slate-200">
                            <th class="py-3.5 px-4 font-bold">Cupom</th>
                            <th class="py-3.5 px-4 font-bold">Data & Hora</th>
                            <th class="py-3.5 px-4 font-bold">Operador</th>
                            <th class="py-3.5 px-4 font-bold">Cliente</th>
                            <th class="py-3.5 px-4 font-bold">Pagamento</th>
                            <th class="py-3.5 px-4 font-bold text-right">Total</th>
                            <th class="py-3.5 px-4 font-bold text-right">Comprovante</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($vendas as $v): ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3.5 px-4 font-mono font-bold text-[#111184]">
                                    #<?= htmlspecialchars($v['codigo_venda']) ?>
                                </td>
                                <td class="py-3.5 px-4 text-xs text-slate-600">
                                    <?= format_date($v['data_venda']) ?>
                                </td>
                                <td class="py-3.5 px-4 text-xs font-semibold text-slate-800">
                                    <?= htmlspecialchars($v['operador_nome']) ?>
                                </td>
                                <td class="py-3.5 px-4 text-xs text-slate-600">
                                    <?= htmlspecialchars($v['cliente_nome']) ?>
                                </td>
                                <td class="py-3.5 px-4 text-xs">
                                    <span class="px-2 py-0.5 rounded-full font-bold uppercase text-[10px] bg-slate-100 text-slate-700">
                                        <?= htmlspecialchars(str_replace('_', ' ', $v['forma_pagamento'])) ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-right font-black text-slate-900 text-sm">
                                    <?= format_money($v['total']) ?>
                                </td>
                                <td class="py-3.5 px-4 text-right">
                                    <a href="<?= base_url('modules/vendas/comprovante.php?id=' . $v['id']) ?>" class="btn-lee-secondary text-xs py-1 px-2.5">
                                        <i data-lucide="file-text" class="w-3.5 h-3.5"></i>
                                        <span>Cupom</span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
