<?php
/**
 * ===================================================================
 * Sistema Integrado de Gestão Comercial — SIGC (Lee Market)
 * Painel de Faturamento e Receita (modules/relatorios/faturamento.php)
 * ===================================================================
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../bd.php';

require_permission('faturamento');

$pdo = getDBConnection();
$user = current_user();
$driver = getDBDriver();

$periodo = $_GET['periodo'] ?? 'mes';
$dataInicio = $_GET['data_inicio'] ?? '';
$dataFim = $_GET['data_fim'] ?? '';

if ($periodo === 'hoje') {
    $dataInicio = date('Y-m-d');
    $dataFim = date('Y-m-d');
} elseif ($periodo === '7dias') {
    $dataInicio = date('Y-m-d', strtotime('-7 days'));
    $dataFim = date('Y-m-d');
} elseif ($periodo === '30dias') {
    $dataInicio = date('Y-m-d', strtotime('-30 days'));
    $dataFim = date('Y-m-d');
} elseif ($periodo === 'ano') {
    $dataInicio = date('Y-01-01');
    $dataFim = date('Y-12-31');
} elseif ($periodo === 'mes' || empty($dataInicio)) {
    $dataInicio = date('Y-m-01');
    $dataFim = date('Y-m-t');
}

// 1. Métricas Globais de Faturamento
$sqlMetricas = "SELECT 
    count(*) as total_vendas,
    coalesce(sum(subtotal), 0) as faturamento_bruto,
    coalesce(sum(desconto), 0) as total_descontos,
    coalesce(sum(total), 0) as faturamento_liquido
FROM vendas 
WHERE " . ($driver === 'sqlite' ? "strftime('%Y-%m-%d', data_venda)" : "DATE(data_venda)") . " >= ? 
  AND " . ($driver === 'sqlite' ? "strftime('%Y-%m-%d', data_venda)" : "DATE(data_venda)") . " <= ?
  AND status = 'concluida'";

$stmtMetricas = $pdo->prepare($sqlMetricas);
$stmtMetricas->execute([$dataInicio, $dataFim]);
$metricas = $stmtMetricas->fetch();

$totalVendas = (int)$metricas['total_vendas'];
$fatBruto = (float)$metricas['faturamento_bruto'];
$descontos = (float)$metricas['total_descontos'];
$fatLiquido = (float)$metricas['faturamento_liquido'];
$ticketMedio = $totalVendas > 0 ? ($fatLiquido / $totalVendas) : 0.00;

// 2. Faturamento por Forma de Pagamento
$sqlFormas = "SELECT 
    forma_pagamento, 
    count(*) as qtd, 
    coalesce(sum(total), 0) as total 
FROM vendas 
WHERE " . ($driver === 'sqlite' ? "strftime('%Y-%m-%d', data_venda)" : "DATE(data_venda)") . " >= ? 
  AND " . ($driver === 'sqlite' ? "strftime('%Y-%m-%d', data_venda)" : "DATE(data_venda)") . " <= ?
  AND status = 'concluida'
GROUP BY forma_pagamento
ORDER BY total DESC";
$stmtFormas = $pdo->prepare($sqlFormas);
$stmtFormas->execute([$dataInicio, $dataFim]);
$formasPagamento = $stmtFormas->fetchAll();

// 3. Top 5 Produtos mais vendidos em receita
$sqlTop = "SELECT 
    p.nome, 
    p.codigo_barras, 
    c.nome as categoria_nome, 
    sum(iv.quantidade) as qtd_vendida, 
    sum(iv.subtotal) as total_gerado
FROM itens_venda iv 
JOIN vendas v ON iv.venda_id = v.id 
JOIN produtos p ON iv.produto_id = p.id 
LEFT JOIN categorias c ON p.categoria_id = c.id 
WHERE " . ($driver === 'sqlite' ? "strftime('%Y-%m-%d', v.data_venda)" : "DATE(v.data_venda)") . " >= ? 
  AND " . ($driver === 'sqlite' ? "strftime('%Y-%m-%d', v.data_venda)" : "DATE(v.data_venda)") . " <= ?
  AND v.status = 'concluida'
GROUP BY p.id, p.nome, p.codigo_barras, c.nome
ORDER BY total_gerado DESC 
LIMIT 5";
$stmtTop = $pdo->prepare($sqlTop);
$stmtTop->execute([$dataInicio, $dataFim]);
$topProdutos = $stmtTop->fetchAll();

$pageTitle = 'Faturamento Comercial';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="space-y-6">

    <!-- Cabeçalho -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-5 sm:p-6 rounded-2xl border border-slate-200 shadow-sm">
        <div>
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-[#111184]"></span>
                <h1 class="text-xl sm:text-2xl font-black text-[#111184]">Painel de Faturamento</h1>
            </div>
            <p class="text-xs text-slate-500 mt-1">Acompanhamento executivo de receita, ticket médio e desempenho por forma de pagamento.</p>
        </div>

        <span class="px-3 py-1 bg-[#FFD32C] text-black font-extrabold text-xs rounded-full">
            Exclusivo Dono
        </span>
    </div>

    <!-- Seletor de Período -->
    <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row justify-between items-center gap-4">
        
        <div class="flex flex-wrap gap-2 w-full sm:w-auto text-xs font-bold">
            <a href="<?= base_url('modules/relatorios/faturamento.php?periodo=hoje') ?>" class="px-3 py-1.5 rounded-lg border transition <?= $periodo === 'hoje' ? 'bg-[#111184] text-white border-[#111184]' : 'bg-slate-50 text-slate-700 hover:bg-slate-100 border-slate-200' ?>">
                Hoje
            </a>
            <a href="<?= base_url('modules/relatorios/faturamento.php?periodo=7dias') ?>" class="px-3 py-1.5 rounded-lg border transition <?= $periodo === '7dias' ? 'bg-[#111184] text-white border-[#111184]' : 'bg-slate-50 text-slate-700 hover:bg-slate-100 border-slate-200' ?>">
                7 Dias
            </a>
            <a href="<?= base_url('modules/relatorios/faturamento.php?periodo=mes') ?>" class="px-3 py-1.5 rounded-lg border transition <?= $periodo === 'mes' ? 'bg-[#111184] text-white border-[#111184]' : 'bg-slate-50 text-slate-700 hover:bg-slate-100 border-slate-200' ?>">
                Mês Atual
            </a>
            <a href="<?= base_url('modules/relatorios/faturamento.php?periodo=ano') ?>" class="px-3 py-1.5 rounded-lg border transition <?= $periodo === 'ano' ? 'bg-[#111184] text-white border-[#111184]' : 'bg-slate-50 text-slate-700 hover:bg-slate-100 border-slate-200' ?>">
                Ano Inteiro
            </a>
        </div>

        <form method="GET" action="<?= base_url('modules/relatorios/faturamento.php') ?>" class="flex items-center gap-2 w-full sm:w-auto text-xs">
            <input type="hidden" name="periodo" value="custom">
            <input type="date" name="data_inicio" value="<?= htmlspecialchars($dataInicio) ?>" class="px-2.5 py-1.5 bg-slate-50 border border-slate-300 rounded-lg">
            <span class="text-slate-400">até</span>
            <input type="date" name="data_fim" value="<?= htmlspecialchars($dataFim) ?>" class="px-2.5 py-1.5 bg-slate-50 border border-slate-300 rounded-lg">
            <button type="submit" class="btn-lee-primary py-1.5 px-3 text-xs">
                Filtrar
            </button>
        </form>

    </div>

    <!-- Cards de Faturamento -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block">Faturamento Líquido</span>
                <h3 class="text-2xl font-black text-[#111184] mt-1"><?= format_money($fatLiquido) ?></h3>
                <span class="text-[11px] text-slate-400 block mt-0.5">receita líquida apurada</span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-[#111184]/10 text-[#111184] flex items-center justify-center">
                <i data-lucide="dollar-sign" class="w-6 h-6"></i>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block">Vendas Concluídas</span>
                <h3 class="text-2xl font-black text-slate-900 mt-1"><?= $totalVendas ?></h3>
                <span class="text-[11px] text-slate-400 block mt-0.5">cupons de venda no período</span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-[#FFD32C]/20 text-[#111184] flex items-center justify-center">
                <i data-lucide="shopping-cart" class="w-6 h-6"></i>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block">Ticket Médio</span>
                <h3 class="text-2xl font-black text-emerald-600 mt-1"><?= format_money($ticketMedio) ?></h3>
                <span class="text-[11px] text-slate-400 block mt-0.5">média por cliente atendido</span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center">
                <i data-lucide="trending-up" class="w-6 h-6"></i>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block">Descontos Concedidos</span>
                <h3 class="text-2xl font-black text-amber-600 mt-1"><?= format_money($descontos) ?></h3>
                <span class="text-[11px] text-slate-400 block mt-0.5">Bruto: <?= format_money($fatBruto) ?></span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-amber-100 text-amber-700 flex items-center justify-center">
                <i data-lucide="percent" class="w-6 h-6"></i>
            </div>
        </div>

    </div>

    <!-- Seções: Formas de Pagamento e Melhores Produtos -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- Faturamento por Forma de Pagamento -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 sm:p-6">
            <h2 class="text-base font-extrabold text-[#111184] pb-4 border-b border-slate-100 flex items-center gap-2">
                <i data-lucide="credit-card" class="w-5 h-5 text-[#111184]"></i>
                Faturamento por Forma de Pagamento
            </h2>

            <?php if (empty($formasPagamento)): ?>
                <div class="py-10 text-center text-slate-400 text-xs">
                    Nenhum faturamento registrado no período selecionado.
                </div>
            <?php else: ?>
                <div class="space-y-4 mt-4">
                    <?php foreach ($formasPagamento as $fp): 
                        $pct = $fatLiquido > 0 ? (($fp['total'] / $fatLiquido) * 100) : 0;
                    ?>
                        <div>
                            <div class="flex justify-between items-center text-xs mb-1">
                                <span class="font-bold text-slate-800 uppercase"><?= htmlspecialchars(str_replace('_', ' ', $fp['forma_pagamento'])) ?> (<?= $fp['qtd'] ?>)</span>
                                <span class="font-black text-[#111184]"><?= format_money($fp['total']) ?> (<?= number_format($pct, 1, ',', '.') ?>%)</span>
                            </div>
                            <div class="w-full bg-slate-100 h-2.5 rounded-full overflow-hidden">
                                <div class="bg-[#111184] h-full rounded-full transition-all duration-500" style="width: <?= min(100, $pct) ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Top 5 Produtos com Maior Faturamento -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 sm:p-6">
            <h2 class="text-base font-extrabold text-[#111184] pb-4 border-b border-slate-100 flex items-center gap-2">
                <i data-lucide="award" class="w-5 h-5 text-[#111184]"></i>
                Top Produtos por Receita Gerada
            </h2>

            <?php if (empty($topProdutos)): ?>
                <div class="py-10 text-center text-slate-400 text-xs">
                    Nenhum dado de vendas disponível no período selecionado.
                </div>
            <?php else: ?>
                <div class="divide-y divide-slate-100 mt-2">
                    <?php foreach ($topProdutos as $idx => $tp): ?>
                        <div class="py-3 flex items-center justify-between text-xs">
                            <div class="flex items-center gap-3">
                                <span class="w-6 h-6 rounded-full bg-[#FFD32C] text-black font-black flex items-center justify-center text-xs shrink-0">
                                    <?= $idx + 1 ?>
                                </span>
                                <div>
                                    <span class="font-bold text-slate-900 block truncate max-w-xs"><?= htmlspecialchars($tp['nome']) ?></span>
                                    <span class="text-[10px] text-slate-400"><?= $tp['qtd_vendida'] ?> unidades vendidas</span>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="font-black text-sm text-[#111184]"><?= format_money($tp['total_gerado']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
