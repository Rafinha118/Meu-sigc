<?php
/**
 * ===================================================================
 * Sistema Integrado de Gestão Comercial — SIGC (Lee Market)
 * Catálogo e Consulta de Produtos (modules/produtos/index.php)
 * ===================================================================
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../bd.php';

require_permission('produtos_consultar');

$pdo = getDBConnection();
$user = current_user();
$role = current_user_role();

// Filtros de busca
$search = trim($_GET['q'] ?? '');
$categoriaId = (int)($_GET['categoria'] ?? 0);

$query = "SELECT p.*, c.nome as categoria_nome, f.nome_fantasia as fornecedor_nome 
          FROM produtos p 
          LEFT JOIN categorias c ON p.categoria_id = c.id 
          LEFT JOIN fornecedores f ON p.fornecedor_id = f.id 
          WHERE p.status = 'ativo'";
$params = [];

if (!empty($search)) {
    $query .= " AND (p.nome LIKE ? OR p.codigo_barras LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($categoriaId > 0) {
    $query .= " AND p.categoria_id = ?";
    $params[] = $categoriaId;
}

$query .= " ORDER BY p.nome ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$produtos = $stmt->fetchAll();

// Categorias para filtro
$categorias = $pdo->query("SELECT * FROM categorias ORDER BY nome ASC")->fetchAll();

$pageTitle = 'Catálogo de Produtos';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="space-y-6">

    <!-- Cabeçalho da Página com Ações -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-5 sm:p-6 rounded-2xl border border-slate-200 shadow-sm">
        <div>
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-[#111184]"></span>
                <h1 class="text-xl sm:text-2xl font-black text-[#111184]">Produtos e Estoque</h1>
            </div>
            <p class="text-xs text-slate-500 mt-1">Consulte produtos cadastrados, preços de venda e quantidades em estoque.</p>
        </div>

        <div class="flex flex-wrap gap-2 w-full sm:w-auto">
            <?php if (can_access('produtos_cadastrar')): ?>
                <a href="<?= base_url('modules/produtos/cadastrar.php') ?>" class="btn-lee-accent text-sm w-full sm:w-auto">
                    <i data-lucide="plus-circle" class="w-4 h-4 text-black"></i>
                    <span>Adicionar Produto</span>
                </a>
            <?php endif; ?>

            <?php if (can_access('produtos_alterar_preco')): ?>
                <a href="<?= base_url('modules/produtos/alterar_preco.php') ?>" class="btn-lee-secondary text-sm w-full sm:w-auto">
                    <i data-lucide="tag" class="w-4 h-4"></i>
                    <span>Alterar Preço</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filtros de Pesquisa -->
    <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-sm">
        <form method="GET" action="<?= base_url('modules/produtos/index.php') ?>" class="grid grid-cols-1 sm:grid-cols-12 gap-3">
            <div class="sm:col-span-7">
                <label for="q" class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Pesquisar Produto</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <i data-lucide="search" class="w-4 h-4"></i>
                    </div>
                    <input type="text" id="q" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Buscar por nome ou código de barras..." class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-[#111184] focus:border-[#111184]">
                </div>
            </div>

            <div class="sm:col-span-3">
                <label for="categoria" class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Categoria</label>
                <select id="categoria" name="categoria" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-[#111184] focus:border-[#111184]">
                    <option value="0">Todas as categorias</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $categoriaId === (int)$cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sm:col-span-2 flex items-end gap-2">
                <button type="submit" class="btn-lee-primary w-full py-2 text-sm justify-center">
                    Filtrar
                </button>
                <?php if (!empty($search) || $categoriaId > 0): ?>
                    <a href="<?= base_url('modules/produtos/index.php') ?>" class="p-2 border border-slate-300 hover:bg-slate-100 rounded-xl text-slate-500" title="Limpar Filtro">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Tabela Responsiva de Produtos -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        
        <?php if (empty($produtos)): ?>
            <div class="p-12 text-center text-slate-400">
                <i data-lucide="package-search" class="w-12 h-12 mx-auto text-slate-300 mb-2"></i>
                <h3 class="font-bold text-slate-700">Nenhum produto encontrado</h3>
                <p class="text-xs text-slate-400 mt-1">Tente ajustar os filtros ou cadastre um novo produto.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider border-b border-slate-200">
                            <th class="py-3.5 px-4 font-bold">Código</th>
                            <th class="py-3.5 px-4 font-bold">Produto</th>
                            <th class="py-3.5 px-4 font-bold">Categoria</th>
                            <?php if ($role === 'dono' || $role === 'gerente'): ?>
                                <th class="py-3.5 px-4 font-bold">Fornecedor</th>
                                <th class="py-3.5 px-4 font-bold text-right">Custo</th>
                            <?php endif; ?>
                            <th class="py-3.5 px-4 font-bold text-right">Preço Venda</th>
                            <th class="py-3.5 px-4 font-bold text-center">Estoque</th>
                            <th class="py-3.5 px-4 font-bold text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($produtos as $p): ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3.5 px-4 font-mono text-xs text-slate-500 font-semibold">
                                    <?= htmlspecialchars($p['codigo_barras']) ?>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="font-bold text-slate-900 block"><?= htmlspecialchars($p['nome']) ?></span>
                                    <span class="text-[11px] text-slate-400">Unidade: <?= htmlspecialchars($p['unidade_medida']) ?></span>
                                </td>
                                <td class="py-3.5 px-4 text-xs text-slate-600">
                                    <?= htmlspecialchars($p['categoria_nome'] ?? 'Sem Categoria') ?>
                                </td>

                                <?php if ($role === 'dono' || $role === 'gerente'): ?>
                                    <td class="py-3.5 px-4 text-xs text-slate-600">
                                        <?= htmlspecialchars($p['fornecedor_nome'] ?? '-') ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-medium text-slate-500">
                                        <?= format_money($p['preco_custo']) ?>
                                    </td>
                                <?php endif; ?>

                                <td class="py-3.5 px-4 text-right">
                                    <span class="font-black text-slate-900 text-base"><?= format_money($p['preco_venda']) ?></span>
                                </td>

                                <td class="py-3.5 px-4 text-center">
                                    <?php if ($p['estoque_atual'] <= 0): ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-black bg-rose-100 text-rose-700">
                                            Zerado (0)
                                        </span>
                                    <?php elseif ($p['estoque_atual'] <= $p['estoque_minimo']): ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-black bg-amber-100 text-amber-800" title="Abaixo do estoque mínimo (<?= $p['estoque_minimo'] ?>)">
                                            Baixo (<?= $p['estoque_atual'] ?> <?= $p['unidade_medida'] ?>)
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                                            <?= $p['estoque_atual'] ?> <?= $p['unidade_medida'] ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3.5 px-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        
                                        <?php if (can_access('produtos_alterar_preco')): ?>
                                            <a href="<?= base_url('modules/produtos/alterar_preco.php?id=' . $p['id']) ?>" class="p-1.5 text-slate-500 hover:text-[#111184] hover:bg-slate-100 rounded-lg transition" title="Alterar Preço (Exclusivo Dono)">
                                                <i data-lucide="tag" class="w-4 h-4"></i>
                                            </a>
                                        <?php endif; ?>

                                        <?php if (can_access('produtos_editar')): ?>
                                            <a href="<?= base_url('modules/produtos/editar.php?id=' . $p['id']) ?>" class="p-1.5 text-slate-500 hover:text-blue-600 hover:bg-slate-100 rounded-lg transition" title="Editar Informações">
                                                <i data-lucide="edit-3" class="w-4 h-4"></i>
                                            </a>
                                        <?php endif; ?>

                                        <?php if (can_access('produtos_excluir')): ?>
                                            <button type="button" class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition" title="Remover Produto" onclick="confirmarExclusao(<?= $p['id'] ?>, <?= json_encode($p['nome']) ?>)">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            </button>
                                        <?php endif; ?>

                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="p-4 bg-slate-50 border-t border-slate-200 text-xs text-slate-500 flex justify-between items-center">
                <span>Total de produtos listados: <strong><?= count($produtos) ?></strong></span>
                <span>Preços em Reais (BRL)</span>
            </div>
        <?php endif; ?>

    </div>

</div>

<script>
function confirmarExclusao(id, nome) {
    confirmAction({
        title: 'Remover Produto',
        message: 'Tem certeza que deseja remover o produto "' + nome + '" do catálogo? O histórico de vendas continuará preservado.',
        confirmText: 'Sim, Remover',
        cancelText: 'Cancelar',
        isDanger: true,
        onConfirm: () => {
            window.location.href = '<?= base_url('modules/produtos/excluir.php?id=') ?>' + id + '&csrf_token=<?= csrf_token() ?>';
        }
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
