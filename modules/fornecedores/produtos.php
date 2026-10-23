<?php
/**
 * ===================================================================
 * Sistema Integrado de Gestão Comercial — SIGC (Lee Market)
 * Produtos Fornecidos por um Fornecedor (modules/fornecedores/produtos.php)
 * ===================================================================
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../bd.php';

require_permission('fornecedores_consultar');

$id = (int)($_GET['id'] ?? 0);
$pdo = getDBConnection();

$stmt = $pdo->prepare("SELECT * FROM fornecedores WHERE id = ? AND status = 'ativo' LIMIT 1");
$stmt->execute([$id]);
$fornecedor = $stmt->fetch();

if (!$fornecedor) {
    set_toast('error', 'Não Encontrado', 'O fornecedor solicitado não foi localizado.');
    redirect('modules/fornecedores/index.php');
}

// Buscar produtos vinculados
$stmtP = $pdo->prepare("
    SELECT p.*, c.nome as categoria_nome 
    FROM produtos p 
    LEFT JOIN categorias c ON p.categoria_id = c.id 
    WHERE p.fornecedor_id = ? AND p.status = 'ativo'
    ORDER BY p.nome ASC
");
$stmtP->execute([$id]);
$produtos = $stmtP->fetchAll();

$pageTitle = 'Produtos de ' . ($fornecedor['nome_fantasia'] ?: $fornecedor['razao_social']);
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="space-y-6">

    <!-- Voltar e Cabeçalho -->
    <div class="flex items-center gap-3">
        <a href="<?= base_url('modules/fornecedores/index.php') ?>" class="p-2 rounded-xl bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 transition">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-[#111184]">
                <?= htmlspecialchars($fornecedor['nome_fantasia'] ?: $fornecedor['razao_social']) ?>
            </h1>
            <p class="text-xs text-slate-500">Detalhes cadastrais e catálogo de produtos fornecidos ao mercado.</p>
        </div>
    </div>

    <!-- Ficha Cadastral do Fornecedor -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
        <h2 class="text-xs font-black uppercase tracking-wider text-slate-400 mb-4 pb-2 border-b border-slate-100">
            Ficha de Contato do Fornecedor
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
            <div>
                <span class="font-bold text-slate-400 uppercase tracking-wider block text-[10px]">Razão Social</span>
                <span class="font-bold text-slate-900 text-sm mt-0.5 block"><?= htmlspecialchars($fornecedor['razao_social']) ?></span>
                <span class="font-mono text-slate-500 mt-0.5 block">CNPJ: <?= htmlspecialchars($fornecedor['cnpj']) ?></span>
            </div>

            <div>
                <span class="font-bold text-slate-400 uppercase tracking-wider block text-[10px]">Contato Comercial</span>
                <span class="font-bold text-slate-900 text-sm mt-0.5 block"><?= htmlspecialchars($fornecedor['contato_nome'] ?: 'Representante') ?></span>
                <span class="text-slate-500 mt-0.5 block">Setor de Atendimento</span>
            </div>

            <div>
                <span class="font-bold text-slate-400 uppercase tracking-wider block text-[10px]">Telefone de Pedidos</span>
                <span class="font-bold text-[#111184] text-sm mt-0.5 block"><?= htmlspecialchars($fornecedor['telefone']) ?></span>
                <span class="text-slate-500 mt-0.5 block">Horário comercial</span>
            </div>

            <div>
                <span class="font-bold text-slate-400 uppercase tracking-wider block text-[10px]">E-mail para Pedidos / NF</span>
                <span class="font-bold text-slate-900 text-sm mt-0.5 block truncate" title="<?= htmlspecialchars($fornecedor['email']) ?>">
                    <?= htmlspecialchars($fornecedor['email']) ?>
                </span>
                <span class="text-slate-500 mt-0.5 block truncate"><?= htmlspecialchars($fornecedor['endereco'] ?: 'Endereço não informado') ?></span>
            </div>
        </div>
    </div>

    <!-- Tabela de Produtos Vinculados -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-slate-100 flex justify-between items-center">
            <div class="flex items-center gap-2">
                <i data-lucide="package" class="w-5 h-5 text-[#111184]"></i>
                <h3 class="font-extrabold text-[#111184] text-base">Produtos Vinculados a este Fornecedor</h3>
            </div>
            <span class="px-2.5 py-1 bg-slate-100 text-slate-700 rounded-full text-xs font-bold">
                <?= count($produtos) ?> <?= count($produtos) === 1 ? 'item cadastrado' : 'itens cadastrados' ?>
            </span>
        </div>

        <?php if (empty($produtos)): ?>
            <div class="py-12 text-center text-slate-400 text-xs">
                <i data-lucide="package-open" class="w-12 h-12 mx-auto text-slate-300 mb-2"></i>
                <p class="font-bold text-slate-700">Nenhum produto vinculado ainda</p>
                <p class="text-slate-400 mt-0.5">Vincule este fornecedor no cadastro ou edição de produtos.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider border-b border-slate-200">
                            <th class="py-3 px-4 font-bold">Código EAN</th>
                            <th class="py-3 px-4 font-bold">Produto</th>
                            <th class="py-3 px-4 font-bold">Categoria</th>
                            <th class="py-3 px-4 font-bold text-right">Preço Custo</th>
                            <th class="py-3 px-4 font-bold text-right">Preço Venda</th>
                            <th class="py-3 px-4 font-bold text-center">Estoque Atual</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($produtos as $p): ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3.5 px-4 font-mono text-xs text-slate-500 font-semibold">
                                    <?= htmlspecialchars($p['codigo_barras']) ?>
                                </td>
                                <td class="py-3.5 px-4 font-bold text-slate-900">
                                    <?= htmlspecialchars($p['nome']) ?>
                                </td>
                                <td class="py-3.5 px-4 text-xs text-slate-600">
                                    <?= htmlspecialchars($p['categoria_nome'] ?? '-') ?>
                                </td>
                                <td class="py-3.5 px-4 text-right text-slate-600 font-medium">
                                    <?= format_money($p['preco_custo']) ?>
                                </td>
                                <td class="py-3.5 px-4 text-right font-black text-[#111184]">
                                    <?= format_money($p['preco_venda']) ?>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <span class="inline-block px-2.5 py-1 rounded-full text-xs font-bold <?= $p['estoque_atual'] <= $p['estoque_minimo'] ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' ?>">
                                        <?= $p['estoque_atual'] ?> <?= $p['unidade_medida'] ?>
                                    </span>
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
