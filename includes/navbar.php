<?php
/**
 * ===================================================================
 * Sistema Integrado de Gestão Comercial — SIGC (Lee Market)
 * Barra de Navegação e Menu Lateral Dinâmico (includes/navbar.php)
 * ===================================================================
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

$user = current_user();
$role = current_user_role();

// Cores e rótulo do perfil
$roleLabels = [
    'dono'     => ['label' => 'Dono / Administrador', 'bg' => 'bg-[#FFD32C] text-black font-extrabold'],
    'gerente'  => ['label' => 'Gerente Operacional',   'bg' => 'bg-blue-100 text-[#111184] font-bold'],
    'operador' => ['label' => 'Operador de Caixa',    'bg' => 'bg-emerald-100 text-emerald-800 font-bold'],
];
$badge = $roleLabels[$role] ?? ['label' => ucfirst($role ?? 'Usuário'), 'bg' => 'bg-slate-200 text-slate-800'];

// Detectar módulo ativo para destacar no menu
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
function is_active(string $needle): string {
    global $currentUri;
    return str_contains($currentUri, $needle) ? 'bg-[#111184] text-white shadow-sm' : 'text-slate-700 hover:bg-slate-100 hover:text-[#111184]';
}
?>

<!-- Topbar Principal -->
<header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16 items-center">
            
            <!-- Esquerda: Botão Menu Mobile + Logo -->
            <div class="flex items-center gap-3">
                <button type="button" id="mobile-menu-btn" class="lg:hidden p-2 rounded-lg text-slate-600 hover:text-black hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-[#111184]" aria-label="Abrir Menu">
                    <i data-lucide="menu" class="w-6 h-6"></i>
                </button>

                <a href="<?= base_url('modules/dashboard/index.php') ?>" class="flex items-center gap-2.5 group">
                    <div class="w-10 h-10 rounded-xl bg-[#111184] flex items-center justify-center text-white shadow-md group-hover:scale-105 transition-transform">
                        <i data-lucide="shopping-cart" class="w-6 h-6 text-[#FFD32C]"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-xl font-black tracking-tight text-[#111184]">LEE</span>
                            <span class="text-xl font-black tracking-tight text-[#FFD32C] drop-shadow-sm">MARKET</span>
                        </div>
                        <span class="text-[10px] uppercase font-bold tracking-wider text-slate-500 block -mt-1">SIGC Supermercados</span>
                    </div>
                </a>
            </div>

            <!-- Direita: Status do Usuário + Ações Rápidas -->
            <div class="flex items-center gap-2 sm:gap-4">
                
                <?php if (can_access('vendas_pdv')): ?>
                    <a href="<?= base_url('modules/vendas/pdv.php') ?>" class="hidden sm:inline-flex items-center gap-2 bg-[#FFD32C] hover:bg-[#e6bd1f] text-black font-bold px-3.5 py-1.5 rounded-lg text-sm transition shadow-sm hover:scale-105">
                        <i data-lucide="scan-barcode" class="w-4 h-4 text-black"></i>
                        <span>Frente de Caixa (PDV)</span>
                    </a>
                <?php endif; ?>

                <!-- Informações do Usuário -->
                <div class="flex items-center gap-2.5 pl-2 sm:pl-4 border-l border-slate-200">
                    <div class="text-right hidden sm:block">
                        <p class="text-sm font-bold text-slate-900 leading-tight"><?= htmlspecialchars($user['nome_completo'] ?? 'Colaborador') ?></p>
                        <span class="inline-block text-[10px] px-2 py-0.5 rounded-full mt-0.5 <?= $badge['bg'] ?>">
                            <?= $badge['label'] ?>
                        </span>
                    </div>
                    
                    <div class="w-9 h-9 rounded-full bg-[#111184] text-[#FFD32C] font-bold flex items-center justify-center text-sm shadow">
                        <?= strtoupper(substr($user['nome_completo'] ?? 'U', 0, 1)) ?>
                    </div>

                    <a href="<?= base_url('modules/auth/logout.php') ?>" title="Sair do Sistema" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition" onclick="return confirmAction({ title: 'Encerrar Sessão', message: 'Deseja realmente sair do sistema SIGC Lee Market?', confirmText: 'Sim, Sair', cancelText: 'Continuar Conectado', isDanger: true, onConfirm: () => { window.location.href = this.href; } }), false;">
                        <i data-lucide="log-out" class="w-5 h-5"></i>
                    </a>
                </div>

            </div>

        </div>
    </div>
</header>

<!-- Estrutura Principal com Sidebar e Área de Conteúdo -->
<div class="flex-1 flex max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 gap-6">

    <!-- Backdrop escuro para Mobile -->
    <div id="sidebar-backdrop" class="fixed inset-0 bg-slate-900/50 z-40 lg:hidden hidden transition-opacity"></div>

    <!-- Sidebar de Navegação (Desktop estático, Mobile gaveta) -->
    <aside id="sidebar-drawer" class="fixed inset-y-0 left-0 z-50 w-72 bg-white border-r border-slate-200 transform -translate-x-full transition-transform duration-300 ease-in-out lg:static lg:translate-x-0 lg:w-64 lg:z-10 lg:bg-transparent lg:border-none flex flex-col shrink-0">
        
        <!-- Cabeçalho do menu no mobile -->
        <div class="p-4 border-b border-slate-200 flex justify-between items-center lg:hidden bg-slate-50">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-[#111184] flex items-center justify-center text-white">
                    <i data-lucide="shopping-cart" class="w-5 h-5 text-[#FFD32C]"></i>
                </div>
                <span class="font-extrabold text-[#111184]">Menu do Sistema</span>
            </div>
            <button type="button" id="close-sidebar-btn" class="p-2 text-slate-500 hover:text-black">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1.5 bg-white lg:bg-white lg:rounded-2xl lg:border lg:border-slate-200 lg:p-4 lg:shadow-sm">
            
            <p class="px-3 text-[11px] font-extrabold uppercase tracking-wider text-slate-400 mb-2">Visão Geral</p>

            <a href="<?= base_url('modules/dashboard/index.php') ?>" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-semibold text-sm transition <?= is_active('modules/dashboard') ?>">
                <i data-lucide="layout-dashboard" class="w-4 h-4 shrink-0"></i>
                <span>Dashboard</span>
            </a>

            <!-- Módulo Vendas -->
            <?php if (can_access('vendas_pdv') || can_access('vendas_historico')): ?>
                <p class="px-3 text-[11px] font-extrabold uppercase tracking-wider text-slate-400 mt-5 mb-2">Vendas & Caixa</p>
                
                <?php if (can_access('vendas_pdv')): ?>
                    <a href="<?= base_url('modules/vendas/pdv.php') ?>" class="flex items-center justify-between px-3.5 py-2.5 rounded-xl font-semibold text-sm transition <?= is_active('modules/vendas/pdv.php') ?>">
                        <div class="flex items-center gap-3">
                            <i data-lucide="store" class="w-4 h-4 shrink-0"></i>
                            <span>Frente de Caixa</span>
                        </div>
                        <span class="bg-[#FFD32C] text-black text-[10px] font-black px-1.5 py-0.5 rounded">PDV</span>
                    </a>
                <?php endif; ?>

                <?php if (can_access('vendas_historico')): ?>
                    <a href="<?= base_url('modules/vendas/historico.php') ?>" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-semibold text-sm transition <?= is_active('modules/vendas/historico.php') ?>">
                        <i data-lucide="receipt" class="w-4 h-4 shrink-0"></i>
                        <span>Histórico de Vendas</span>
                    </a>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Módulo Produtos e Estoque -->
            <p class="px-3 text-[11px] font-extrabold uppercase tracking-wider text-slate-400 mt-5 mb-2">Produtos & Estoque</p>

            <a href="<?= base_url('modules/produtos/index.php') ?>" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-semibold text-sm transition <?= is_active('modules/produtos') ?>">
                <i data-lucide="package" class="w-4 h-4 shrink-0"></i>
                <span>Catálogo de Produtos</span>
            </a>

            <a href="<?= base_url('modules/estoque/index.php') ?>" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-semibold text-sm transition <?= is_active('modules/estoque/index.php') ?>">
                <i data-lucide="boxes" class="w-4 h-4 shrink-0"></i>
                <span>Controle de Estoque</span>
            </a>

            <?php if (can_access('estoque_saida')): ?>
                <a href="<?= base_url('modules/estoque/saida.php') ?>" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-semibold text-sm transition <?= is_active('modules/estoque/saida.php') ?>">
                    <i data-lucide="arrow-up-right" class="w-4 h-4 shrink-0 text-amber-500"></i>
                    <span>Registrar Saída</span>
                </a>
            <?php endif; ?>

            <!-- Fornecedores -->
            <?php if (can_access('fornecedores_consultar')): ?>
                <a href="<?= base_url('modules/fornecedores/index.php') ?>" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-semibold text-sm transition <?= is_active('modules/fornecedores') ?>">
                    <i data-lucide="truck" class="w-4 h-4 shrink-0"></i>
                    <span>Fornecedores</span>
                </a>
            <?php endif; ?>

            <!-- Módulo Financeiro & Gestão (Dono & Gerente) -->
            <?php if (can_access('fluxo_caixa') || can_access('faturamento') || can_access('relatorios_vendas')): ?>
                <p class="px-3 text-[11px] font-extrabold uppercase tracking-wider text-slate-400 mt-5 mb-2">Financeiro & Relatórios</p>

                <?php if (can_access('fluxo_caixa')): ?>
                    <a href="<?= base_url('modules/financeiro/fluxo_caixa.php') ?>" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-semibold text-sm transition <?= is_active('modules/financeiro') ?>">
                        <i data-lucide="wallet" class="w-4 h-4 shrink-0"></i>
                        <span>Fluxo de Caixa</span>
                    </a>
                <?php endif; ?>

                <?php if (can_access('faturamento')): ?>
                    <a href="<?= base_url('modules/relatorios/faturamento.php') ?>" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-semibold text-sm transition <?= is_active('modules/relatorios/faturamento.php') ?>">
                        <i data-lucide="line-chart" class="w-4 h-4 shrink-0"></i>
                        <span>Faturamento</span>
                    </a>
                <?php endif; ?>

                <?php if (can_access('relatorios_vendas')): ?>
                    <a href="<?= base_url('modules/relatorios/vendas.php') ?>" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-semibold text-sm transition <?= is_active('modules/relatorios/vendas.php') ?>">
                        <i data-lucide="bar-chart-3" class="w-4 h-4 shrink-0"></i>
                        <span>Relatório de Vendas</span>
                    </a>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Módulo RH & Auditoria (Acesso Dono) -->
            <?php if (can_access('rh_colaboradores') || can_access('auditoria')): ?>
                <p class="px-3 text-[11px] font-extrabold uppercase tracking-wider text-slate-400 mt-5 mb-2">Administração</p>

                <?php if (can_access('rh_colaboradores')): ?>
                    <a href="<?= base_url('modules/rh/index.php') ?>" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-semibold text-sm transition <?= is_active('modules/rh') ?>">
                        <i data-lucide="users" class="w-4 h-4 shrink-0"></i>
                        <span>Gestão de RH</span>
                    </a>
                <?php endif; ?>

                <?php if (can_access('auditoria')): ?>
                    <a href="<?= base_url('modules/auditoria/index.php') ?>" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-semibold text-sm transition <?= is_active('modules/auditoria') ?>">
                        <i data-lucide="shield-alert" class="w-4 h-4 shrink-0"></i>
                        <span>Histórico de Auditoria</span>
                    </a>
                <?php endif; ?>
            <?php endif; ?>

        </nav>

        <!-- Card de Conexão no Rodapé da Sidebar -->
        <div class="p-3 m-3 lg:m-0 lg:mt-4 bg-slate-100 rounded-xl text-[11px] text-slate-500 border border-slate-200">
            <div class="flex items-center justify-between">
                <span class="font-bold text-slate-700">Banco de Dados</span>
                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold <?= getDBDriver() === 'mysql' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>">
                    PDO (<?= strtoupper(getDBDriver()) ?>)
                </span>
            </div>
            <p class="mt-1 text-[10px]">Transações seguras com Prepared Statements</p>
        </div>

    </aside>

    <!-- Área de Conteúdo Central -->
    <main class="flex-1 min-w-0">
