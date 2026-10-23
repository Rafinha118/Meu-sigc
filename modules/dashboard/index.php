<?php
/**
 * ===================================================================
 * Sistema Integrado de Gestão Comercial — SIGC (Lee Market)
 * Painel Principal / Dashboard (modules/dashboard/index.php)
 * ===================================================================
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../bd.php';

require_login();

$user = current_user();
$role = current_user_role();
$pdo = getDBConnection();
$driver = getDBDriver();

// Estatísticas Dinâmicas
// 1. Total de produtos cadastrados
$totalProdutos = (int)$pdo->query("SELECT count(*) FROM produtos WHERE status = 'ativo'")->fetchColumn();

// 2. Produtos com estoque baixo (estoque_atual <= estoque_minimo)
$totalEstoqueBaixo = (int)$pdo->query("SELECT count(*) FROM produtos WHERE estoque_atual <= estoque_minimo AND status = 'ativo'")->fetchColumn();

// 3. Vendas de Hoje
$hoje = date('Y-m-d');
$stmtVendasHoje = $pdo->prepare("SELECT count(*) as qtd, coalesce(sum(total), 0) as total FROM vendas WHERE strftime('%Y-%m-%d', data_venda) = ? AND status = 'concluida'");
if ($driver === 'mysql') {
    $stmtVendasHoje = $pdo->prepare("SELECT count(*) as qtd, coalesce(sum(total), 0) as total FROM vendas WHERE DATE(data_venda) = ? AND status = 'concluida'");
}
$stmtVendasHoje->execute([$hoje]);
$vendasHoje = $stmtVendasHoje->fetch();

// 4. Saldo em Caixa (Entradas - Saídas)
$totalEntradas = (float)$pdo->query("SELECT coalesce(sum(valor), 0) FROM fluxo_caixa WHERE tipo = 'entrada'")->fetchColumn();
$totalSaidas = (float)$pdo->query("SELECT coalesce(sum(valor), 0) FROM fluxo_caixa WHERE tipo = 'saida'")->fetchColumn();
$saldoCaixa = $totalEntradas - $totalSaidas;

// 5. Total de Colaboradores e Fornecedores
$totalColaboradores = (int)$pdo->query("SELECT count(*) FROM usuarios WHERE status = 'ativo'")->fetchColumn();
$totalFornecedores = (int)$pdo->query("SELECT count(*) FROM fornecedores WHERE status = 'ativo'")->fetchColumn();

// 6. Lista de Itens com Estoque Crítico
$stmtCriticos = $pdo->query("SELECT p.*, c.nome as categoria_nome FROM produtos p LEFT JOIN categorias c ON p.categoria_id = c.id WHERE p.estoque_atual <= p.estoque_minimo AND p.status = 'ativo' ORDER BY p.estoque_atual ASC LIMIT 5");
$produtosCriticos = $stmtCriticos->fetchAll();

// 7. Últimas Vendas
$stmtUltimasVendas = $pdo->query("SELECT v.*, u.nome_completo as operador_nome FROM vendas v JOIN usuarios u ON v.usuario_id = u.id ORDER BY v.data_venda DESC LIMIT 5");
$ultimasVendas = $stmtUltimasVendas->fetchAll();

// 8. Últimos Logs de Auditoria (para Dono)
$ultimosLogs = [];
if ($role === 'dono') {
    $stmtLogs = $pdo->query("SELECT * FROM auditoria_logs ORDER BY criado_em DESC LIMIT 6");
    $ultimosLogs = $stmtLogs->fetchAll();
}

$pageTitle = 'Dashboard ' . ucfirst($role);
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="space-y-6">

    <!-- Banner de Boas-Vindas com Identidade Lee Market -->
    <div class="bg-gradient-to-r from-[#111184] to-[#0c0c66] rounded-3xl p-6 sm:p-8 text-white shadow-lg relative overflow-hidden">
        <!-- Detalhe decorativo em amarelo Lee -->
        <div class="absolute -right-12 -bottom-12 w-48 h-48 bg-[#FFD32C]/10 rounded-full blur-2xl pointer-events-none"></div>
        <div class="absolute right-6 top-6 hidden md:block opacity-15">
            <i data-lucide="shopping-bag" class="w-32 h-32 text-white"></i>
        </div>

        <div class="relative z-10 max-w-2xl">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#FFD32C] text-black text-xs font-black mb-3">
                <i data-lucide="badge-check" class="w-3.5 h-3.5"></i>
                <span>PAINEL <?= strtoupper($role) ?> &bull; SUPERMERCADO LEE MARKET</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-black tracking-tight leading-tight">
                Olá, <?= htmlspecialchars($user['nome_completo']) ?>!
            </h1>
            <p class="text-slate-200 text-sm mt-1 leading-relaxed">
                Bem-vindo ao SIGC. Todas as operações de vendas, estoque e movimentações financeiras são auditadas em tempo real.
            </p>

            <div class="mt-5 flex flex-wrap gap-2.5">
                <?php if (can_access('vendas_pdv')): ?>
                    <a href="<?= base_url('modules/vendas/pdv.php') ?>" class="btn-lee-accent text-sm">
                        <i data-lucide="scan-barcode" class="w-4 h-4 text-black"></i>
                        <span>Abrir Frente de Caixa (PDV)</span>
                    </a>
                <?php endif; ?>

                <?php if (can_access('estoque_saida')): ?>
                    <a href="<?= base_url('modules/estoque/saida.php') ?>" class="bg-white/10 hover:bg-white/20 text-white font-bold px-4 py-2.5 rounded-lg text-sm transition backdrop-blur-sm flex items-center gap-2">
                        <i data-lucide="arrow-up-right" class="w-4 h-4 text-[#FFD32C]"></i>
                        <span>Registrar Saída</span>
                    </a>
                <?php endif; ?>

                <?php if (can_access('produtos_cadastrar')): ?>
                    <a href="<?= base_url('modules/produtos/cadastrar.php') ?>" class="bg-white/10 hover:bg-white/20 text-white font-bold px-4 py-2.5 rounded-lg text-sm transition backdrop-blur-sm flex items-center gap-2">
                        <i data-lucide="plus-circle" class="w-4 h-4 text-[#FFD32C]"></i>
                        <span>Novo Produto</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Cards de Métricas Principais -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        
        <!-- Vendas Hoje -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Vendas Hoje</p>
                <h3 class="text-2xl font-black text-[#111184] mt-1"><?= format_money($vendasHoje['total']) ?></h3>
                <span class="text-xs font-semibold text-emerald-600 flex items-center gap-1 mt-1">
                    <i data-lucide="shopping-cart" class="w-3.5 h-3.5"></i>
                    <?= (int)$vendasHoje['qtd'] ?> cupons emitidos
                </span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-[#111184]/10 text-[#111184] flex items-center justify-center">
                <i data-lucide="trending-up" class="w-6 h-6"></i>
            </div>
        </div>

        <!-- Alerta de Estoque Baixo -->
        <div class="bg-white p-5 rounded-2xl border <?= $totalEstoqueBaixo > 0 ? 'border-amber-300 bg-amber-50/30' : 'border-slate-200' ?> shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Estoque em Alerta</p>
                <h3 class="text-2xl font-black <?= $totalEstoqueBaixo > 0 ? 'text-amber-600' : 'text-slate-800' ?> mt-1">
                    <?= $totalEstoqueBaixo ?> <?= $totalEstoqueBaixo === 1 ? 'item' : 'itens' ?>
                </h3>
                <span class="text-xs font-medium text-slate-500 block mt-1">Abaixo do estoque mínimo</span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-amber-100 text-amber-700 flex items-center justify-center">
                <i data-lucide="alert-triangle" class="w-6 h-6"></i>
            </div>
        </div>

        <!-- Produtos Ativos -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Produtos Cadastrados</p>
                <h3 class="text-2xl font-black text-slate-900 mt-1"><?= $totalProdutos ?></h3>
                <span class="text-xs font-medium text-slate-500 block mt-1"><?= $totalFornecedores ?> fornecedores parceiros</span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-[#FFD32C]/20 text-[#111184] flex items-center justify-center">
                <i data-lucide="package" class="w-6 h-6"></i>
            </div>
        </div>

        <!-- Saldo de Caixa (Visível para Dono) ou Atendimentos (para outros) -->
        <?php if ($role === 'dono'): ?>
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Saldo Operacional</p>
                    <h3 class="text-2xl font-black <?= $saldoCaixa >= 0 ? 'text-emerald-600' : 'text-rose-600' ?> mt-1">
                        <?= format_money($saldoCaixa) ?>
                    </h3>
                    <span class="text-xs font-medium text-slate-500 block mt-1">Fluxo total consolidado</span>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center">
                    <i data-lucide="wallet" class="w-6 h-6"></i>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Status do Caixa</p>
                    <h3 class="text-2xl font-black text-emerald-600 mt-1">Operando</h3>
                    <span class="text-xs font-medium text-slate-500 block mt-1">PDV Pronto para Vendas</span>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center">
                    <i data-lucide="check-circle" class="w-6 h-6"></i>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <!-- Seções Centrais -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Itens com Estoque Baixo (2 colunas no desktop) -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm p-5 sm:p-6">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full bg-amber-500"></div>
                    <h2 class="text-base font-extrabold text-[#111184]">Alerta de Reposição de Estoque</h2>
                </div>
                <a href="<?= base_url('modules/estoque/index.php') ?>" class="text-xs font-bold text-[#111184] hover:underline flex items-center gap-1">
                    Ver todos <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                </a>
            </div>

            <?php if (empty($produtosCriticos)): ?>
                <div class="py-12 text-center text-slate-400">
                    <i data-lucide="check-circle-2" class="w-12 h-12 mx-auto text-emerald-500 mb-2"></i>
                    <p class="font-bold text-slate-700">Tudo em dia com o estoque!</p>
                    <p class="text-xs text-slate-400 mt-0.5">Nenhum produto está abaixo do limite mínimo de segurança.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto mt-4">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-slate-400 uppercase tracking-wider border-b border-slate-100">
                                <th class="pb-3 font-bold">Produto</th>
                                <th class="pb-3 font-bold">Categoria</th>
                                <th class="pb-3 font-bold text-center">Atual</th>
                                <th class="pb-3 font-bold text-center">Mínimo</th>
                                <th class="pb-3 font-bold text-right">Ação</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($produtosCriticos as $prod): ?>
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="py-3 font-bold text-slate-900">
                                        <?= htmlspecialchars($prod['nome']) ?>
                                        <span class="block text-[10px] text-slate-400 font-normal">Cód: <?= htmlspecialchars($prod['codigo_barras']) ?></span>
                                    </td>
                                    <td class="py-3 text-slate-600"><?= htmlspecialchars($prod['categoria_nome'] ?? 'Geral') ?></td>
                                    <td class="py-3 text-center">
                                        <span class="inline-block px-2.5 py-1 rounded-full font-black text-xs <?= $prod['estoque_atual'] <= 0 ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-800' ?>">
                                            <?= $prod['estoque_atual'] ?> <?= $prod['unidade_medida'] ?>
                                        </span>
                                    </td>
                                    <td class="py-3 text-center font-bold text-slate-600"><?= $prod['estoque_minimo'] ?> <?= $prod['unidade_medida'] ?></td>
                                    <td class="py-3 text-right">
                                        <?php if (can_access('estoque_gerenciar')): ?>
                                            <a href="<?= base_url('modules/estoque/entrada.php?id=' . $prod['id']) ?>" class="btn-lee-accent text-[11px] py-1 px-2.5">
                                                Reabastecer
                                            </a>
                                        <?php else: ?>
                                            <span class="text-slate-400 text-[11px]">Consultar</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Acesso Rápido / Atalhos -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 sm:p-6 flex flex-col justify-between">
            <div>
                <h2 class="text-base font-extrabold text-[#111184] pb-4 border-b border-slate-100">Atalhos Operacionais</h2>
                
                <div class="grid grid-cols-1 gap-2.5 mt-4">
                    <?php if (can_access('vendas_pdv')): ?>
                        <a href="<?= base_url('modules/vendas/pdv.php') ?>" class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 hover:border-[#111184] hover:bg-slate-50 transition group">
                            <div class="w-10 h-10 rounded-lg bg-[#FFD32C] text-black flex items-center justify-center font-bold group-hover:scale-105 transition-transform">
                                <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <span class="block text-xs font-bold text-slate-900">Frente de Caixa (PDV)</span>
                                <span class="block text-[11px] text-slate-500">Lançamento de compras e código de barras</span>
                            </div>
                        </a>
                    <?php endif; ?>

                    <?php if (can_access('produtos_consultar')): ?>
                        <a href="<?= base_url('modules/produtos/index.php') ?>" class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 hover:border-[#111184] hover:bg-slate-50 transition group">
                            <div class="w-10 h-10 rounded-lg bg-[#111184]/10 text-[#111184] flex items-center justify-center font-bold group-hover:scale-105 transition-transform">
                                <i data-lucide="search" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <span class="block text-xs font-bold text-slate-900">Consulta de Produtos</span>
                                <span class="block text-[11px] text-slate-500">Preços, códigos e quantidade em estoque</span>
                            </div>
                        </a>
                    <?php endif; ?>

                    <?php if (can_access('estoque_saida')): ?>
                        <a href="<?= base_url('modules/estoque/saida.php') ?>" class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 hover:border-[#111184] hover:bg-slate-50 transition group">
                            <div class="w-10 h-10 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center font-bold group-hover:scale-105 transition-transform">
                                <i data-lucide="package-minus" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <span class="block text-xs font-bold text-slate-900">Registrar Saída de Estoque</span>
                                <span class="block text-[11px] text-slate-500">Baixa por avarias, vencimentos ou consumo</span>
                            </div>
                        </a>
                    <?php endif; ?>

                    <?php if (can_access('produtos_alterar_preco')): ?>
                        <a href="<?= base_url('modules/produtos/alterar_preco.php') ?>" class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 hover:border-[#111184] hover:bg-slate-50 transition group">
                            <div class="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold group-hover:scale-105 transition-transform">
                                <i data-lucide="tag" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <span class="block text-xs font-bold text-slate-900">Modificação de Preços</span>
                                <span class="block text-[11px] text-slate-500">Exclusivo do Dono com histórico auditado</span>
                            </div>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Identidade visual Lee Market -->
            <div class="mt-5 pt-4 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-400">
                <span>Lee Market &bull; SIGC v1.0</span>
                <span class="text-[#111184] font-black">100% Auditado</span>
            </div>
        </div>

    </div>

    <!-- Últimas Vendas e Auditoria Recente -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Histórico Recente de Vendas -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 sm:p-6">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <i data-lucide="receipt" class="w-5 h-5 text-[#111184]"></i>
                    <h2 class="text-base font-extrabold text-[#111184]">Últimas Vendas no Caixa</h2>
                </div>
                <a href="<?= base_url('modules/vendas/historico.php') ?>" class="text-xs font-bold text-[#111184] hover:underline flex items-center gap-1">
                    Histórico <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                </a>
            </div>

            <?php if (empty($ultimasVendas)): ?>
                <div class="py-10 text-center text-slate-400">
                    <p class="text-xs">Nenhuma venda realizada até o momento.</p>
                </div>
            <?php else: ?>
                <div class="divide-y divide-slate-100 mt-3">
                    <?php foreach ($ultimasVendas as $v): ?>
                        <div class="py-3 flex items-center justify-between">
                            <div>
                                <span class="font-extrabold text-sm text-slate-900">#<?= htmlspecialchars($v['codigo_venda']) ?></span>
                                <span class="block text-xs text-slate-500">Operador: <?= htmlspecialchars($v['operador_nome']) ?> &bull; <?= format_date($v['data_venda']) ?></span>
                            </div>
                            <div class="text-right">
                                <span class="font-black text-sm text-[#111184]"><?= format_money($v['total']) ?></span>
                                <span class="block text-[10px] uppercase font-bold text-slate-400"><?= htmlspecialchars(str_replace('_', ' ', $v['forma_pagamento'])) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Histórico de Auditoria (Para Dono) ou Informações Institucionais -->
        <?php if ($role === 'dono'): ?>
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 sm:p-6">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <i data-lucide="shield-check" class="w-5 h-5 text-[#111184]"></i>
                        <h2 class="text-base font-extrabold text-[#111184]">Auditoria Recente</h2>
                    </div>
                    <a href="<?= base_url('modules/auditoria/index.php') ?>" class="text-xs font-bold text-[#111184] hover:underline flex items-center gap-1">
                        Ver logs <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>

                <div class="divide-y divide-slate-100 mt-3">
                    <?php foreach ($ultimosLogs as $log): ?>
                        <div class="py-2.5 flex items-start justify-between gap-3 text-xs">
                            <div>
                                <span class="font-bold text-slate-900"><?= htmlspecialchars($log['acao']) ?></span>
                                <span class="text-slate-400 block text-[11px]"><?= htmlspecialchars($log['usuario_nome']) ?> (<?= htmlspecialchars($log['setor']) ?>)</span>
                            </div>
                            <span class="text-[10px] text-slate-400 font-mono shrink-0"><?= format_date($log['criado_em']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 sm:p-6 flex flex-col justify-between">
                <div>
                    <div class="flex items-center gap-2 pb-4 border-b border-slate-100">
                        <i data-lucide="info" class="w-5 h-5 text-[#111184]"></i>
                        <h2 class="text-base font-extrabold text-[#111184]">Diretrizes Operacionais</h2>
                    </div>
                    <div class="mt-4 space-y-3 text-xs text-slate-600">
                        <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                            <strong class="text-slate-900 block mb-0.5">Segurança e Rastreabilidade</strong>
                            Todas as operações de caixa e baixas de estoque são registradas com data, hora e operador.
                        </div>
                        <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                            <strong class="text-slate-900 block mb-0.5">Controle de Saídas de Mercadoria</strong>
                            Sempre justifique saídas por motivo de avaria, vencimento ou consumo interno no menu apropriado.
                        </div>
                    </div>
                </div>
                <p class="text-[11px] text-slate-400 mt-4 text-center">Lee Market Supermercados &bull; Gestão Integrada</p>
            </div>
        <?php endif; ?>

    </div>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
