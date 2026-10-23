<?php
/**
 * ===================================================================
 * Sistema Integrado de Gestão Comercial — SIGC (Lee Market)
 * Painel de Controle e Gestão de Estoque (modules/estoque/index.php)
 * ===================================================================
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../bd.php';

require_permission('estoque_consultar');

$pdo = getDBConnection();
$user = current_user();
$role = current_user_role();

$filtro = $_GET['filtro'] ?? 'todos';
$busca = trim($_GET['q'] ?? '');

$sql = "SELECT p.*, c.nome as categoria_nome, f.nome_fantasia as fornecedor_nome 
        FROM produtos p 
        LEFT JOIN categorias c ON p.categoria_id = c.id 
        LEFT JOIN fornecedores f ON p.fornecedor_id = f.id 
        WHERE p.status = 'ativo'";

$params = [];

if (!empty($busca)) {
    $sql .= " AND (p.nome LIKE ? OR p.codigo_barras LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

if ($filtro === 'baixo') {
    $sql .= " AND p.estoque_atual <= p.estoque_minimo AND p.estoque_atual > 0";
} elseif ($filtro === 'zerado') {
    $sql .= " AND p.estoque_atual <= 0";
}

$sql .= " ORDER BY p.estoque_atual ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$produtos = $stmt->fetchAll();

// Totais para os filtros
$totalGeral = (int)$pdo->query("SELECT count(*) FROM produtos WHERE status = 'ativo'")->fetchColumn();
$totalBaixo = (int)$pdo->query("SELECT count(*) FROM produtos WHERE estoque_atual <= estoque_minimo AND estoque_atual > 0 AND status = 'ativo'")->fetchColumn();
$totalZerado = (int)$pdo->query("SELECT count(*) FROM produtos WHERE estoque_atual <= 0 AND status = 'ativo'")->fetchColumn();

// Últimas movimentações de estoque
$stmtMov = $pdo->query("
    SELECT m.*, p.nome as produto_nome, p.unidade_medida, u.nome_completo as usuario_nome 
    FROM movimentacoes_estoque m 
    JOIN produtos p ON m.produto_id = p.id 
    JOIN usuarios u ON m.usuario_id = u.id 
    ORDER BY m.data_hora DESC LIMIT 8
");
$ultimasMov = $stmtMov->fetchAll();

$pageTitle = 'Controle de Estoque';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="space-y-6">

    <!-- Cabeçalho -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-5 sm:p-6 rounded-2xl border border-slate-200 shadow-sm">
        <div>
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-[#111184]"></span>
                <h1 class="text-xl sm:text-2xl font-black text-[#111184]">Gerenciamento de Estoque</h1>
            </div>
            <p class="text-xs text-slate-500 mt-1">Acompanhamento de níveis de estoque, reposição e registro de saídas de mercadorias.</p>
        </div>

        <div class="flex flex-wrap gap-2 w-full sm:w-auto">
            <?php if (can_access('estoque_saida')): ?>
                <a href="<?= base_url('modules/estoque/saida.php') ?>" class="btn-lee-accent text-sm w-full sm:w-auto">
                    <i data-lucide="package-minus" class="w-4 h-4 text-black"></i>
                    <span>Registrar Saída (Baixa)</span>
                </a>
            <?php endif; ?>

            <?php if (can_access('estoque_gerenciar')): ?>
                <a href="<?= base_url('modules/estoque/entrada.php') ?>" class="btn-lee-primary text-sm w-full sm:w-auto">
                    <i data-lucide="package-plus" class="w-4 h-4"></i>
                    <span>Entrada / Reabastecimento</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filtros de Status em Abas -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <a href="<?= base_url('modules/estoque/index.php?filtro=todos') ?>" class="p-4 rounded-2xl border transition flex items-center justify-between <?= $filtro === 'todos' ? 'border-[#111184] bg-white ring-2 ring-[#111184]/10 shadow-sm' : 'border-slate-200 bg-white hover:bg-slate-50' ?>">
            <div>
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block">Todos os Produtos</span>
                <span class="text-2xl font-black text-[#111184]"><?= $totalGeral ?></span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-[#111184]/10 text-[#111184] flex items-center justify-center">
                <i data-lucide="boxes" class="w-5 h-5"></i>
            </div>
        </a>

        <a href="<?= base_url('modules/estoque/index.php?filtro=baixo') ?>" class="p-4 rounded-2xl border transition flex items-center justify-between <?= $filtro === 'baixo' ? 'border-amber-400 bg-amber-50/40 ring-2 ring-amber-400/20 shadow-sm' : 'border-slate-200 bg-white hover:bg-slate-50' ?>">
            <div>
                <span class="text-xs font-bold text-amber-700 uppercase tracking-wider block">Estoque Baixo (Alerta)</span>
                <span class="text-2xl font-black text-amber-600"><?= $totalBaixo ?></span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center">
                <i data-lucide="alert-triangle" class="w-5 h-5"></i>
            </div>
        </a>

        <a href="<?= base_url('modules/estoque/index.php?filtro=zerado') ?>" class="p-4 rounded-2xl border transition flex items-center justify-between <?= $filtro === 'zerado' ? 'border-rose-400 bg-rose-50/40 ring-2 ring-rose-400/20 shadow-sm' : 'border-slate-200 bg-white hover:bg-slate-50' ?>">
            <div>
                <span class="text-xs font-bold text-rose-700 uppercase tracking-wider block">Estoque Esgotado</span>
                <span class="text-2xl font-black text-rose-600"><?= $totalZerado ?></span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center">
                <i data-lucide="x-circle" class="w-5 h-5"></i>
            </div>
        </a>
    </div>

    <!-- Tabela de Produtos em Estoque -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        
        <div class="p-4 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-center gap-3">
            <h2 class="font-extrabold text-[#111184] text-base">Posição Atual de Estoque</h2>
            
            <form method="GET" action="<?= base_url('modules/estoque/index.php') ?>" class="flex gap-2 w-full sm:w-auto">
                <input type="hidden" name="filtro" value="<?= htmlspecialchars($filtro) ?>">
                <div class="relative flex-1 sm:w-64">
                    <input type="text" name="q" value="<?= htmlspecialchars($busca) ?>" placeholder="Buscar produto..." class="w-full pl-8 pr-3 py-1.5 bg-slate-50 border border-slate-300 rounded-lg text-xs focus:ring-2 focus:ring-[#111184]">
                    <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5"></i>
                </div>
                <button type="submit" class="btn-lee-primary py-1.5 px-3 text-xs">Buscar</button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider border-b border-slate-200">
                        <th class="py-3.5 px-4 font-bold">Código</th>
                        <th class="py-3.5 px-4 font-bold">Produto</th>
                        <th class="py-3.5 px-4 font-bold">Categoria</th>
                        <th class="py-3.5 px-4 font-bold text-center">Mínimo</th>
                        <th class="py-3.5 px-4 font-bold text-center">Quantidade Atual</th>
                        <th class="py-3.5 px-4 font-bold text-center">Status</th>
                        <th class="py-3.5 px-4 font-bold text-right">Ação Rápida</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($produtos)): ?>
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-400 text-xs">
                                Nenhum produto encontrado para o filtro selecionado.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($produtos as $p): ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3.5 px-4 font-mono text-xs text-slate-500 font-semibold">
                                    <?= htmlspecialchars($p['codigo_barras']) ?>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="font-bold text-slate-900 block"><?= htmlspecialchars($p['nome']) ?></span>
                                    <span class="text-[11px] text-slate-400">Fornecedor: <?= htmlspecialchars($p['fornecedor_nome'] ?? 'Geral') ?></span>
                                </td>
                                <td class="py-3.5 px-4 text-xs text-slate-600">
                                    <?= htmlspecialchars($p['categoria_nome'] ?? '-') ?>
                                </td>
                                <td class="py-3.5 px-4 text-center font-bold text-slate-500">
                                    <?= $p['estoque_minimo'] ?> <?= $p['unidade_medida'] ?>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <span class="text-base font-black text-slate-900">
                                        <?= $p['estoque_atual'] ?>
                                    </span>
                                    <span class="text-xs text-slate-400"><?= $p['unidade_medida'] ?></span>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <?php if ($p['estoque_atual'] <= 0): ?>
                                        <span class="px-2.5 py-1 rounded-full text-xs font-black bg-rose-100 text-rose-700">
                                            Zerado
                                        </span>
                                    <?php elseif ($p['estoque_atual'] <= $p['estoque_minimo']): ?>
                                        <span class="px-2.5 py-1 rounded-full text-xs font-black bg-amber-100 text-amber-800">
                                            Crítico / Baixo
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                                            Regular
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <?php if (can_access('estoque_saida')): ?>
                                            <a href="<?= base_url('modules/estoque/saida.php?id=' . $p['id']) ?>" class="p-1.5 text-slate-500 hover:text-amber-600 hover:bg-slate-100 rounded-lg text-xs font-bold flex items-center gap-1" title="Registrar Saída">
                                                <i data-lucide="arrow-up-right" class="w-3.5 h-3.5"></i>
                                                <span>Baixa</span>
                                            </a>
                                        <?php endif; ?>

                                        <?php if (can_access('estoque_gerenciar')): ?>
                                            <a href="<?= base_url('modules/estoque/entrada.php?id=' . $p['id']) ?>" class="btn-lee-accent text-[11px] py-1 px-2.5">
                                                Reabastecer
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Histórico Recente de Movimentações de Estoque -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 sm:p-6">
        <div class="flex items-center gap-2 pb-4 border-b border-slate-100">
            <i data-lucide="activity" class="w-5 h-5 text-[#111184]"></i>
            <h2 class="text-base font-extrabold text-[#111184]">Histórico Recente de Movimentações de Estoque</h2>
        </div>

        <div class="divide-y divide-slate-100 mt-2">
            <?php if (empty($ultimasMov)): ?>
                <p class="py-6 text-center text-xs text-slate-400">Nenhuma movimentação registrada recentemente.</p>
            <?php else: ?>
                <?php foreach ($ultimasMov as $m): ?>
                    <div class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
                        <div class="flex items-center gap-3">
                            <span class="w-7 h-7 rounded-lg flex items-center justify-center font-bold text-xs shrink-0 <?= $m['tipo'] === 'entrada' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?>">
                                <?= $m['tipo'] === 'entrada' ? '+' : '-' ?>
                            </span>
                            <div>
                                <span class="font-bold text-slate-900"><?= htmlspecialchars($m['produto_nome']) ?></span>
                                <span class="text-slate-500 block text-[11px]">Motivo: <?= htmlspecialchars($m['motivo']) ?> &bull; Responsável: <?= htmlspecialchars($m['usuario_nome']) ?></span>
                            </div>
                        </div>

                        <div class="flex items-center gap-4 sm:text-right pl-10 sm:pl-0">
                            <div>
                                <span class="font-black text-sm <?= $m['tipo'] === 'entrada' ? 'text-emerald-700' : 'text-rose-700' ?>">
                                    <?= $m['tipo'] === 'entrada' ? '+' : '-' ?><?= $m['quantidade'] ?> <?= $m['unidade_medida'] ?>
                                </span>
                                <span class="block text-[10px] text-slate-400"><?= format_date($m['data_hora']) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
