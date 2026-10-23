<?php
/**
 * ===================================================================
 * Sistema Integrado de Gestão Comercial — SIGC (Lee Market)
 * Fluxo de Caixa: Entradas, Saídas e Saldo (modules/financeiro/fluxo_caixa.php)
 * ===================================================================
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../bd.php';
require_once __DIR__ . '/../../includes/audit.php';

require_permission('fluxo_caixa');

$pdo = getDBConnection();
$user = current_user();
$driver = getDBDriver();

// Filtro de Datas
$periodo = $_GET['periodo'] ?? 'mes';
$dataInicio = $_GET['data_inicio'] ?? '';
$dataFim = $_GET['data_fim'] ?? '';

if ($periodo === 'hoje') {
    $dataInicio = date('Y-m-d');
    $dataFim = date('Y-m-d');
} elseif ($periodo === '7dias') {
    $dataInicio = date('Y-m-d', strtotime('-7 days'));
    $dataFim = date('Y-m-d');
} elseif ($periodo === 'mes' || empty($dataInicio)) {
    $dataInicio = date('Y-m-01');
    $dataFim = date('Y-m-t');
}

// Inserção Manual de Movimentação (Sangria / Suprimento / Despesa)
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nova_movimentacao'])) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validate_csrf($csrf)) {
        $errors[] = 'Token expirado. Tente novamente.';
    }

    $tipo = $_POST['tipo'] ?? 'saida';
    $categoria = trim($_POST['categoria'] ?? 'Despesa Geral');
    $descricao = trim($_POST['descricao'] ?? '');
    $valor = floatval(str_replace(['.', ','], ['', '.'], $_POST['valor'] ?? '0'));
    $formaPagamento = $_POST['forma_pagamento'] ?? 'Dinheiro';
    $dataMovimento = $_POST['data_movimento'] ?? date('Y-m-d');

    if ($valor <= 0) $errors[] = 'O valor da movimentação deve ser maior que zero.';
    if (empty($descricao)) $errors[] = 'Informe a descrição da movimentação.';

    if (empty($errors)) {
        try {
            $stmtInsert = $pdo->prepare("
                INSERT INTO fluxo_caixa (tipo, categoria, descricao, valor, data_movimento, forma_pagamento, usuario_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtInsert->execute([$tipo, $categoria, $descricao, $valor, $dataMovimento, $formaPagamento, $user['id']]);

            log_audit(
                'Financeiro',
                'Lançamento no Fluxo de Caixa',
                "Lançamento de {$tipo} no valor de " . format_money($valor) . " ({$categoria} - {$descricao})."
            );

            set_toast('success', 'Movimentação Registrada!', "Lançamento de " . format_money($valor) . " adicionado ao fluxo de caixa.");
            redirect('modules/financeiro/fluxo_caixa.php?periodo=' . $periodo . '&data_inicio=' . $dataInicio . '&data_fim=' . $dataFim);

        } catch (Throwable $e) {
            $errors[] = 'Erro ao registrar movimentação: ' . $e->getMessage();
        }
    }
}

// Buscar movimentações no período
$sql = "SELECT fc.*, u.nome_completo as usuario_nome 
        FROM fluxo_caixa fc 
        JOIN usuarios u ON fc.usuario_id = u.id 
        WHERE fc.data_movimento >= ? AND fc.data_movimento <= ?
        ORDER BY fc.data_movimento DESC, fc.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$dataInicio, $dataFim]);
$movimentacoes = $stmt->fetchAll();

// Calcular Totais
$totalEntradas = 0.00;
$totalSaidas = 0.00;
foreach ($movimentacoes as $m) {
    if ($m['tipo'] === 'entrada') {
        $totalEntradas += (float)$m['valor'];
    } else {
        $totalSaidas += (float)$m['valor'];
    }
}
$saldoPeriodo = $totalEntradas - $totalSaidas;

$pageTitle = 'Fluxo de Caixa';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="space-y-6">

    <!-- Cabeçalho -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-5 sm:p-6 rounded-2xl border border-slate-200 shadow-sm">
        <div>
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-[#111184]"></span>
                <h1 class="text-xl sm:text-2xl font-black text-[#111184]">Fluxo de Caixa (Financeiro)</h1>
            </div>
            <p class="text-xs text-slate-500 mt-1">Acompanhe todas as entradas de vendas e saídas de despesas com cálculo automático de saldo.</p>
        </div>

        <button type="button" onclick="openModal('modal-novo-lancamento')" class="btn-lee-accent text-sm w-full sm:w-auto">
            <i data-lucide="plus-circle" class="w-4 h-4 text-black"></i>
            <span>Novo Lançamento Manual</span>
        </button>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-800 space-y-1">
            <?php foreach ($errors as $err): ?>
                <p>&bull; <?= htmlspecialchars($err) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Filtros de Período -->
    <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row justify-between items-center gap-4">
        
        <div class="flex flex-wrap gap-2 w-full sm:w-auto text-xs font-bold">
            <a href="<?= base_url('modules/financeiro/fluxo_caixa.php?periodo=hoje') ?>" class="px-3 py-1.5 rounded-lg border transition <?= $periodo === 'hoje' ? 'bg-[#111184] text-white border-[#111184]' : 'bg-slate-50 text-slate-700 hover:bg-slate-100 border-slate-200' ?>">
                Hoje
            </a>
            <a href="<?= base_url('modules/financeiro/fluxo_caixa.php?periodo=7dias') ?>" class="px-3 py-1.5 rounded-lg border transition <?= $periodo === '7dias' ? 'bg-[#111184] text-white border-[#111184]' : 'bg-slate-50 text-slate-700 hover:bg-slate-100 border-slate-200' ?>">
                Últimos 7 Dias
            </a>
            <a href="<?= base_url('modules/financeiro/fluxo_caixa.php?periodo=mes') ?>" class="px-3 py-1.5 rounded-lg border transition <?= $periodo === 'mes' ? 'bg-[#111184] text-white border-[#111184]' : 'bg-slate-50 text-slate-700 hover:bg-slate-100 border-slate-200' ?>">
                Este Mês
            </a>
        </div>

        <form method="GET" action="<?= base_url('modules/financeiro/fluxo_caixa.php') ?>" class="flex items-center gap-2 w-full sm:w-auto text-xs">
            <input type="hidden" name="periodo" value="custom">
            <input type="date" name="data_inicio" value="<?= htmlspecialchars($dataInicio) ?>" class="px-2.5 py-1.5 bg-slate-50 border border-slate-300 rounded-lg">
            <span class="text-slate-400">até</span>
            <input type="date" name="data_fim" value="<?= htmlspecialchars($dataFim) ?>" class="px-2.5 py-1.5 bg-slate-50 border border-slate-300 rounded-lg">
            <button type="submit" class="btn-lee-primary py-1.5 px-3 text-xs">
                Filtrar
            </button>
        </form>

    </div>

    <!-- Cards de Resumo Financeiro -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        
        <!-- Total Entradas -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block">Total de Entradas (+)</span>
                <h3 class="text-2xl font-black text-emerald-600 mt-1"><?= format_money($totalEntradas) ?></h3>
                <span class="text-[11px] text-slate-400 block mt-0.5">Vendas e suprimentos</span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center">
                <i data-lucide="arrow-down-left" class="w-6 h-6"></i>
            </div>
        </div>

        <!-- Total Saídas -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block">Total de Saídas (-)</span>
                <h3 class="text-2xl font-black text-rose-600 mt-1"><?= format_money($totalSaidas) ?></h3>
                <span class="text-[11px] text-slate-400 block mt-0.5">Despesas, sangrias e faturas</span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-rose-100 text-rose-700 flex items-center justify-center">
                <i data-lucide="arrow-up-right" class="w-6 h-6"></i>
            </div>
        </div>

        <!-- Saldo Líquido do Período -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block">Saldo do Período</span>
                <h3 class="text-2xl font-black <?= $saldoPeriodo >= 0 ? 'text-[#111184]' : 'text-rose-600' ?> mt-1">
                    <?= format_money($saldoPeriodo) ?>
                </h3>
                <span class="text-[11px] font-bold <?= $saldoPeriodo >= 0 ? 'text-emerald-600' : 'text-rose-600' ?> block mt-0.5">
                    <?= $saldoPeriodo >= 0 ? 'Superávit Operacional' : 'Déficit no Período' ?>
                </span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-[#111184]/10 text-[#111184] flex items-center justify-center">
                <i data-lucide="wallet" class="w-6 h-6"></i>
            </div>
        </div>

    </div>

    <!-- Tabela de Movimentações -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex justify-between items-center">
            <h2 class="font-extrabold text-[#111184] text-base">Extrato de Movimentações Financeiras</h2>
            <span class="text-xs text-slate-500">Período: <?= date('d/m/Y', strtotime($dataInicio)) ?> a <?= date('d/m/Y', strtotime($dataFim)) ?></span>
        </div>

        <?php if (empty($movimentacoes)): ?>
            <div class="py-12 text-center text-slate-400 text-xs">
                <i data-lucide="inbox" class="w-12 h-12 mx-auto text-slate-300 mb-2"></i>
                <p class="font-bold text-slate-700">Nenhuma movimentação registrada no período</p>
                <p class="text-slate-400 mt-0.5">Altere o filtro de datas ou registre um novo lançamento.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider border-b border-slate-200">
                            <th class="py-3 px-4 font-bold">Tipo</th>
                            <th class="py-3 px-4 font-bold">Data</th>
                            <th class="py-3 px-4 font-bold">Categoria</th>
                            <th class="py-3 px-4 font-bold">Descrição</th>
                            <th class="py-3 px-4 font-bold">Forma Pagto</th>
                            <th class="py-3 px-4 font-bold">Responsável</th>
                            <th class="py-3 px-4 font-bold text-right">Valor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($movimentacoes as $m): ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3.5 px-4">
                                    <?php if ($m['tipo'] === 'entrada'): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                                            <i data-lucide="arrow-down-left" class="w-3 h-3"></i> Entrada
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800">
                                            <i data-lucide="arrow-up-right" class="w-3 h-3"></i> Saída
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 text-xs font-mono text-slate-600">
                                    <?= date('d/m/Y', strtotime($m['data_movimento'])) ?>
                                </td>
                                <td class="py-3.5 px-4 text-xs font-bold text-slate-800">
                                    <?= htmlspecialchars($m['categoria']) ?>
                                </td>
                                <td class="py-3.5 px-4 text-xs text-slate-600">
                                    <?= htmlspecialchars($m['descricao']) ?>
                                </td>
                                <td class="py-3.5 px-4 text-xs text-slate-500">
                                    <?= htmlspecialchars($m['forma_pagamento'] ?: '-') ?>
                                </td>
                                <td class="py-3.5 px-4 text-xs text-slate-600">
                                    <?= htmlspecialchars($m['usuario_nome']) ?>
                                </td>
                                <td class="py-3.5 px-4 text-right font-black text-sm <?= $m['tipo'] === 'entrada' ? 'text-emerald-600' : 'text-rose-600' ?>">
                                    <?= $m['tipo'] === 'entrada' ? '+' : '-' ?> <?= format_money($m['valor']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>

</div>

<!-- Modal para Novo Lançamento Manual -->
<div id="modal-novo-lancamento" class="lee-modal-container fixed inset-0 z-50 hidden items-center justify-center p-4 modal-backdrop">
    <div class="bg-white rounded-3xl max-w-lg w-full shadow-2xl border border-slate-200 overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        <div class="h-2 bg-[#111184] w-full"></div>
        <div class="p-6">
            <div class="flex justify-between items-center pb-3 border-b border-slate-100">
                <h3 class="text-lg font-black text-[#111184]">Novo Lançamento de Caixa</h3>
                <button type="button" onclick="closeModal('modal-novo-lancamento')" class="p-1 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-black">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form action="<?= base_url('modules/financeiro/fluxo_caixa.php') ?>" method="POST" class="space-y-4 mt-4 text-xs">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="nova_movimentacao" value="1">

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 uppercase mb-1">Tipo de Movimento</label>
                        <select name="tipo" required class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl font-bold">
                            <option value="saida">Saída (Despesa / Sangria)</option>
                            <option value="entrada">Entrada (Suprimento / Aporte)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 uppercase mb-1">Data</label>
                        <input type="date" name="data_movimento" value="<?= date('Y-m-d') ?>" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl font-semibold">
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 uppercase mb-1">Categoria</label>
                    <input type="text" name="categoria" required placeholder="Ex: Pagamento Fornecedor, Sangria de Caixa, Material de Limpeza" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl font-semibold">
                </div>

                <div>
                    <label class="block font-bold text-slate-700 uppercase mb-1">Descrição Detalhada</label>
                    <input type="text" name="descricao" required placeholder="Ex: Pagamento referente fatura de hortifrúti" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl font-semibold">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 uppercase mb-1">Valor (R$)</label>
                        <input type="number" step="0.01" min="0.01" name="valor" required placeholder="0.00" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl font-black text-sm text-[#111184]">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 uppercase mb-1">Forma de Pagamento</label>
                        <select name="forma_pagamento" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl font-semibold">
                            <option value="Dinheiro">Dinheiro</option>
                            <option value="PIX">PIX</option>
                            <option value="Transferência Bancária">Transferência Bancária</option>
                            <option value="Cartão de Crédito">Cartão de Crédito</option>
                            <option value="Boleto Bancário">Boleto Bancário</option>
                        </select>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-100 flex justify-end gap-2">
                    <button type="button" onclick="closeModal('modal-novo-lancamento')" class="px-4 py-2 rounded-lg border border-slate-300 text-slate-600 font-bold hover:bg-slate-50">
                        Cancelar
                    </button>
                    <button type="submit" class="btn-lee-accent text-xs">
                        Confirmar Lançamento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
