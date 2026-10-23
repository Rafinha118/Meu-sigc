<?php
/**
 * ===================================================================
 * Sistema Integrado de Gestão Comercial — SIGC (Lee Market)
 * Relatório Gerencial de Vendas (modules/relatorios/vendas.php)
 * ===================================================================
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../bd.php';

require_permission('relatorios_vendas');

$pdo = getDBConnection();
$user = current_user();
$driver = getDBDriver();

$dataInicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-15 days'));
$dataFim = $_GET['data_fim'] ?? date('Y-m-d');
$usuarioId = (int)($_GET['usuario_id'] ?? 0);
$forma = $_GET['forma'] ?? '';

$sql = "SELECT v.*, u.nome_completo as operador_nome, 
        (SELECT count(*) FROM itens_venda iv WHERE iv.venda_id = v.id) as total_itens 
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

if ($usuarioId > 0) {
    $sql .= " AND v.usuario_id = ?";
    $params[] = $usuarioId;
}

if (!empty($forma)) {
    $sql .= " AND v.forma_pagamento = ?";
    $params[] = $forma;
}

$sql .= " ORDER BY v.data_venda DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vendas = $stmt->fetchAll();

// Operadores para filtro
$operadores = $pdo->query("SELECT id, nome_completo, cargo FROM usuarios ORDER BY nome_completo ASC")->fetchAll();

// Totais
$totalVendas = count($vendas);
$valorTotal = array_sum(array_column($vendas, 'total'));
$ticketMedio = $totalVendas > 0 ? ($valorTotal / $totalVendas) : 0;

$pageTitle = 'Relatório de Vendas por Período';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="space-y-6">

    <!-- Cabeçalho -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-5 sm:p-6 rounded-2xl border border-slate-200 shadow-sm">
        <div>
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-[#111184]"></span>
                <h1 class="text-xl sm:text-2xl font-black text-[#111184]">Relatório Consolidado de Vendas</h1>
            </div>
            <p class="text-xs text-slate-500 mt-1">Análise de vendas realizadas, filtros por operador e forma de pagamento.</p>
        </div>

        <button type="button" onclick="window.print()" class="btn-lee-secondary text-xs w-full sm:w-auto">
            <i data-lucide="printer" class="w-4 h-4"></i>
            <span>Imprimir Relatório</span>
        </button>
    </div>

    <!-- Filtros de Análise -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm no-print">
        <form method="GET" action="<?= base_url('modules/relatorios/vendas.php') ?>" class="grid grid-cols-1 sm:grid-cols-12 gap-3 text-xs">
            
            <div class="sm:col-span-3">
                <label for="data_inicio" class="block font-bold text-slate-500 uppercase tracking-wider mb-1">Data Inicial</label>
                <input type="date" id="data_inicio" name="data_inicio" value="<?= htmlspecialchars($dataInicio) ?>" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl font-semibold">
            </div>

            <div class="sm:col-span-3">
                <label for="data_fim" class="block font-bold text-slate-500 uppercase tracking-wider mb-1">Data Final</label>
                <input type="date" id="data_fim" name="data_fim" value="<?= htmlspecialchars($dataFim) ?>" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl font-semibold">
            </div>

            <div class="sm:col-span-3">
                <label for="usuario_id" class="block font-bold text-slate-500 uppercase tracking-wider mb-1">Operador de Caixa</label>
                <select id="usuario_id" name="usuario_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl font-semibold">
                    <option value="0">Todos os Colaboradores</option>
                    <?php foreach ($operadores as $op): ?>
                        <option value="<?= $op['id'] ?>" <?= $usuarioId === (int)$op['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($op['nome_completo']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sm:col-span-3">
                <label for="forma" class="block font-bold text-slate-500 uppercase tracking-wider mb-1">Forma de Pagamento</label>
                <div class="flex gap-2">
                    <select id="forma" name="forma" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl font-semibold">
                        <option value="">Todas</option>
                        <option value="dinheiro" <?= $forma === 'dinheiro' ? 'selected' : '' ?>>Dinheiro</option>
                        <option value="pix" <?= $forma === 'pix' ? 'selected' : '' ?>>PIX</option>
                        <option value="cartao_debito" <?= $forma === 'cartao_debito' ? 'selected' : '' ?>>Débito</option>
                        <option value="cartao_credito" <?= $forma === 'cartao_credito' ? 'selected' : '' ?>>Crédito</option>
                    </select>
                    <button type="submit" class="btn-lee-primary px-4 py-2 font-bold shrink-0">
                        Processar
                    </button>
                </div>
            </div>

        </form>
    </div>

    <!-- Indicadores -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block">Volume de Vendas</span>
            <span class="text-2xl font-black text-[#111184] mt-1 block"><?= $totalVendas ?></span>
            <span class="text-xs text-slate-400 mt-0.5 block">cupons emitidos</span>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block">Receita Total do Período</span>
            <span class="text-2xl font-black text-emerald-600 mt-1 block"><?= format_money($valorTotal) ?></span>
            <span class="text-xs text-slate-400 mt-0.5 block">faturamento acumulado</span>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block">Ticket Médio</span>
            <span class="text-2xl font-black text-slate-900 mt-1 block"><?= format_money($ticketMedio) ?></span>
            <span class="text-xs text-slate-400 mt-0.5 block">por compra finalizada</span>
        </div>
    </div>

    <!-- Tabela Detalhada -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex justify-between items-center text-xs">
            <span class="font-extrabold text-[#111184] text-sm">Vendas Processadas</span>
            <span class="text-slate-500">De <?= date('d/m/Y', strtotime($dataInicio)) ?> até <?= date('d/m/Y', strtotime($dataFim)) ?></span>
        </div>

        <?php if (empty($vendas)): ?>
            <div class="py-12 text-center text-slate-400 text-xs">
                <p class="font-bold text-slate-700">Nenhuma venda encontrada para os critérios informados.</p>
                <p class="text-slate-400 mt-1">Altere o período de datas para pesquisar.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider border-b border-slate-200">
                            <th class="py-3 px-4 font-bold">Cupom</th>
                            <th class="py-3 px-4 font-bold">Data/Hora</th>
                            <th class="py-3 px-4 font-bold">Operador</th>
                            <th class="py-3 px-4 font-bold">Cliente</th>
                            <th class="py-3 px-4 font-bold text-center">Itens</th>
                            <th class="py-3 px-4 font-bold">Pagamento</th>
                            <th class="py-3 px-4 font-bold text-right">Total</th>
                            <th class="py-3 px-4 font-bold text-right no-print">Ver</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($vendas as $v): ?>
                            <tr class="hover:bg-slate-50/80 transition text-xs">
                                <td class="py-3 px-4 font-mono font-bold text-[#111184]">
                                    #<?= htmlspecialchars($v['codigo_venda']) ?>
                                </td>
                                <td class="py-3 px-4 font-mono text-slate-600">
                                    <?= format_date($v['data_venda']) ?>
                                </td>
                                <td class="py-3 px-4 font-semibold text-slate-800">
                                    <?= htmlspecialchars($v['operador_nome']) ?>
                                </td>
                                <td class="py-3 px-4 text-slate-600">
                                    <?= htmlspecialchars($v['cliente_nome']) ?>
                                </td>
                                <td class="py-3 px-4 text-center font-bold text-slate-600">
                                    <?= $v['total_itens'] ?>
                                </td>
                                <td class="py-3 px-4 uppercase text-[10px] font-bold">
                                    <?= htmlspecialchars(str_replace('_', ' ', $v['forma_pagamento'])) ?>
                                </td>
                                <td class="py-3 px-4 text-right font-black text-slate-900 text-sm">
                                    <?= format_money($v['total']) ?>
                                </td>
                                <td class="py-3 px-4 text-right no-print">
                                    <a href="<?= base_url('modules/vendas/comprovante.php?id=' . $v['id']) ?>" class="text-[#111184] hover:underline font-bold">
                                        Cupom
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
