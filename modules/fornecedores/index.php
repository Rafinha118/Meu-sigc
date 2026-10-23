<?php
/**
 * ===================================================================
 * Sistema Integrado de Gestão Comercial — SIGC (Lee Market)
 * Consulta e Gestão de Fornecedores (modules/fornecedores/index.php)
 * ===================================================================
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../bd.php';

require_permission('fornecedores_consultar');

$pdo = getDBConnection();
$user = current_user();
$busca = trim($_GET['q'] ?? '');

$sql = "SELECT f.*, 
        (SELECT count(*) FROM produtos p WHERE p.fornecedor_id = f.id AND p.status = 'ativo') as total_produtos 
        FROM fornecedores f 
        WHERE f.status = 'ativo'";

$params = [];
if (!empty($busca)) {
    $sql .= " AND (f.razao_social LIKE ? OR f.nome_fantasia LIKE ? OR f.cnpj LIKE ? OR f.contato_nome LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

$sql .= " ORDER BY f.razao_social ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$fornecedores = $stmt->fetchAll();

$pageTitle = 'Fornecedores Parceiros';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="space-y-6">

    <!-- Cabeçalho -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-5 sm:p-6 rounded-2xl border border-slate-200 shadow-sm">
        <div>
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-[#111184]"></span>
                <h1 class="text-xl sm:text-2xl font-black text-[#111184]">Fornecedores e Parceiros</h1>
            </div>
            <p class="text-xs text-slate-500 mt-1">Consulte contatos, telefones, e-mails e os produtos fornecidos ao Lee Market.</p>
        </div>

        <?php if (can_access('fornecedores_gerenciar')): ?>
            <a href="<?= base_url('modules/fornecedores/cadastrar.php') ?>" class="btn-lee-accent text-sm w-full sm:w-auto">
                <i data-lucide="plus-circle" class="w-4 h-4 text-black"></i>
                <span>Cadastrar Fornecedor</span>
            </a>
        <?php endif; ?>
    </div>

    <!-- Barra de Busca -->
    <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-sm">
        <form method="GET" action="<?= base_url('modules/fornecedores/index.php') ?>" class="flex gap-2">
            <div class="relative flex-1">
                <input type="text" name="q" value="<?= htmlspecialchars($busca) ?>" placeholder="Buscar fornecedor por Razão Social, Fantasia, CNPJ ou Contato..."
                       class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-[#111184]">
                <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-3.5"></i>
            </div>
            <button type="submit" class="btn-lee-primary px-5 py-2.5 text-sm">
                Buscar
            </button>
            <?php if (!empty($busca)): ?>
                <a href="<?= base_url('modules/fornecedores/index.php') ?>" class="p-2.5 border border-slate-300 rounded-xl text-slate-500 hover:bg-slate-100" title="Limpar Busca">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Grade de Fornecedores -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <?php if (empty($fornecedores)): ?>
            <div class="col-span-full py-12 text-center text-slate-400 bg-white rounded-2xl border border-slate-200">
                <i data-lucide="truck" class="w-12 h-12 mx-auto text-slate-300 mb-2"></i>
                <h3 class="font-bold text-slate-700">Nenhum fornecedor encontrado</h3>
                <p class="text-xs text-slate-400 mt-1">Verifique o termo de busca ou cadastre um novo fornecedor.</p>
            </div>
        <?php else: ?>
            <?php foreach ($fornecedores as $f): ?>
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 flex flex-col justify-between hover:border-[#111184] transition group">
                    <div>
                        <div class="flex justify-between items-start gap-2">
                            <div>
                                <span class="text-xs font-black text-[#111184] uppercase tracking-wider block">
                                    <?= htmlspecialchars($f['nome_fantasia'] ?: $f['razao_social']) ?>
                                </span>
                                <h3 class="text-sm font-bold text-slate-900 mt-0.5 line-clamp-1" title="<?= htmlspecialchars($f['razao_social']) ?>">
                                    <?= htmlspecialchars($f['razao_social']) ?>
                                </h3>
                            </div>
                            <span class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded text-[10px] font-bold shrink-0">
                                <?= $f['total_produtos'] ?> <?= $f['total_produtos'] === 1 ? 'produto' : 'produtos' ?>
                            </span>
                        </div>

                        <div class="mt-4 space-y-1.5 text-xs text-slate-600 border-t border-slate-100 pt-3">
                            <p class="flex items-center gap-2">
                                <i data-lucide="file-text" class="w-3.5 h-3.5 text-slate-400 shrink-0"></i>
                                <span class="font-mono text-slate-700"><?= htmlspecialchars($f['cnpj']) ?></span>
                            </p>
                            <p class="flex items-center gap-2">
                                <i data-lucide="phone" class="w-3.5 h-3.5 text-slate-400 shrink-0"></i>
                                <span><?= htmlspecialchars($f['telefone']) ?></span>
                            </p>
                            <p class="flex items-center gap-2">
                                <i data-lucide="mail" class="w-3.5 h-3.5 text-slate-400 shrink-0"></i>
                                <span class="truncate"><?= htmlspecialchars($f['email']) ?></span>
                            </p>
                            <?php if (!empty($f['contato_nome'])): ?>
                                <p class="flex items-center gap-2 text-slate-500">
                                    <i data-lucide="user" class="w-3.5 h-3.5 text-slate-400 shrink-0"></i>
                                    <span>Contato: <?= htmlspecialchars($f['contato_nome']) ?></span>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mt-5 pt-3 border-t border-slate-100 flex items-center justify-between gap-2">
                        <a href="<?= base_url('modules/fornecedores/produtos.php?id=' . $f['id']) ?>" class="btn-lee-primary text-xs py-1.5 px-3 flex-1 justify-center">
                            <i data-lucide="package" class="w-3.5 h-3.5"></i>
                            <span>Ver Produtos (<?= $f['total_produtos'] ?>)</span>
                        </a>

                        <?php if (can_access('fornecedores_gerenciar')): ?>
                            <a href="<?= base_url('modules/fornecedores/editar.php?id=' . $f['id']) ?>" class="p-2 border border-slate-200 hover:bg-slate-50 rounded-lg text-slate-600" title="Editar Fornecedor">
                                <i data-lucide="edit-3" class="w-4 h-4"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
