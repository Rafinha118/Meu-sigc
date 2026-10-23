<?php
/**
 * ===================================================================
 * Sistema Integrado de Gestão Comercial — SIGC (Lee Market)
 * Edição de Produto (modules/produtos/editar.php)
 * ===================================================================
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../bd.php';
require_once __DIR__ . '/../../includes/audit.php';

require_permission('produtos_editar');

$pdo = getDBConnection();
$user = current_user();
$errors = [];

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM produtos WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$produto = $stmt->fetch();

if (!$produto) {
    set_toast('error', 'Não Encontrado', 'O produto solicitado não foi localizado.');
    redirect('modules/produtos/index.php');
}

$categorias = $pdo->query("SELECT * FROM categorias ORDER BY nome ASC")->fetchAll();
$fornecedores = $pdo->query("SELECT * FROM fornecedores WHERE status = 'ativo' ORDER BY razao_social ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validate_csrf($csrf)) {
        $errors[] = 'Token expirado. Tente novamente.';
    }

    $nome = trim($_POST['nome'] ?? '');
    $categoriaId = !empty($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : null;
    $fornecedorId = !empty($_POST['fornecedor_id']) ? (int)$_POST['fornecedor_id'] : null;
    $precoCusto = floatval(str_replace(['.', ','], ['', '.'], $_POST['preco_custo'] ?? '0'));
    $precoVenda = floatval(str_replace(['.', ','], ['', '.'], $_POST['preco_venda'] ?? '0'));
    $estoqueMinimo = (int)($_POST['estoque_minimo'] ?? 5);
    $unidadeMedida = trim($_POST['unidade_medida'] ?? 'UN');

    if (empty($nome)) $errors[] = 'O nome do produto é obrigatório.';
    if ($precoVenda <= 0) $errors[] = 'O preço de venda deve ser maior que zero.';

    if (empty($errors)) {
        try {
            $alteracoes = [];
            if ($produto['nome'] !== $nome) $alteracoes[] = "Nome: '{$produto['nome']}' -> '{$nome}'";
            if ((float)$produto['preco_venda'] !== (float)$precoVenda) $alteracoes[] = "Preço Venda: " . format_money($produto['preco_venda']) . " -> " . format_money($precoVenda);
            if ((float)$produto['preco_custo'] !== (float)$precoCusto) $alteracoes[] = "Preço Custo: " . format_money($produto['preco_custo']) . " -> " . format_money($precoCusto);

            $updateStmt = $pdo->prepare("
                UPDATE produtos SET 
                    nome = ?, 
                    categoria_id = ?, 
                    fornecedor_id = ?, 
                    preco_custo = ?, 
                    preco_venda = ?, 
                    estoque_minimo = ?, 
                    unidade_medida = ?
                WHERE id = ?
            ");
            $updateStmt->execute([
                $nome,
                $categoriaId,
                $fornecedorId,
                $precoCusto,
                $precoVenda,
                $estoqueMinimo,
                $unidadeMedida,
                $id
            ]);

            log_audit(
                'Produtos',
                'Edição de Produto',
                "Atualizou produto '{$produto['nome']}' (ID: {$id}). " . implode('; ', $alteracoes)
            );

            set_toast('success', 'Produto Atualizado', "As informações de '{$nome}' foram atualizadas.");
            redirect('modules/produtos/index.php');

        } catch (Throwable $e) {
            $errors[] = 'Erro ao atualizar produto: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Editar Produto';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="max-w-4xl mx-auto space-y-6">

    <div class="flex items-center justify-between bg-white p-5 sm:p-6 rounded-2xl border border-slate-200 shadow-sm">
        <div>
            <div class="flex items-center gap-2">
                <a href="<?= base_url('modules/produtos/index.php') ?>" class="p-1 rounded-lg hover:bg-slate-100 text-slate-500">
                    <i data-lucide="arrow-left" class="w-5 h-5"></i>
                </a>
                <h1 class="text-xl sm:text-2xl font-black text-[#111184]">Editar Produto</h1>
            </div>
            <p class="text-xs text-slate-500 mt-1 pl-8">Alteração de dados cadastrais &bull; Código: <?= htmlspecialchars($produto['codigo_barras']) ?></p>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-800 space-y-1">
            <?php foreach ($errors as $err): ?>
                <p>&bull; <?= htmlspecialchars($err) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form action="<?= base_url('modules/produtos/editar.php?id=' . $id) ?>" method="POST" class="bg-white p-6 sm:p-8 rounded-2xl border border-slate-200 shadow-sm space-y-6">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Código de Barras (Fixo)</label>
                <input type="text" disabled value="<?= htmlspecialchars($produto['codigo_barras']) ?>" class="block w-full px-3.5 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-sm font-mono text-slate-500">
            </div>

            <div>
                <label for="unidade_medida" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">Unidade de Medida</label>
                <select id="unidade_medida" name="unidade_medida" class="block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
                    <option value="UN" <?= $produto['unidade_medida'] === 'UN' ? 'selected' : '' ?>>Unidade (UN)</option>
                    <option value="KG" <?= $produto['unidade_medida'] === 'KG' ? 'selected' : '' ?>>Quilograma (KG)</option>
                    <option value="PCT" <?= $produto['unidade_medida'] === 'PCT' ? 'selected' : '' ?>>Pacote (PCT)</option>
                    <option value="CX" <?= $produto['unidade_medida'] === 'CX' ? 'selected' : '' ?>>Caixa (CX)</option>
                    <option value="L" <?= $produto['unidade_medida'] === 'L' ? 'selected' : '' ?>>Litro (L)</option>
                </select>
            </div>

            <div class="sm:col-span-2">
                <label for="nome" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">Nome do Produto</label>
                <input type="text" id="nome" name="nome" required value="<?= htmlspecialchars($produto['nome']) ?>" class="block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
            </div>

            <div>
                <label for="categoria_id" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">Categoria</label>
                <select id="categoria_id" name="categoria_id" class="block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
                    <option value="">Sem Categoria</option>
                    <?php foreach ($categorias as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= (int)$produto['categoria_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="fornecedor_id" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">Fornecedor</label>
                <select id="fornecedor_id" name="fornecedor_id" class="block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
                    <option value="">Sem Fornecedor</option>
                    <?php foreach ($fornecedores as $f): ?>
                        <option value="<?= $f['id'] ?>" <?= (int)$produto['fornecedor_id'] === (int)$f['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($f['razao_social']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="preco_custo" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">Preço de Custo (R$)</label>
                <input type="number" step="0.01" id="preco_custo" name="preco_custo" value="<?= htmlspecialchars($produto['preco_custo']) ?>" class="block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
            </div>

            <div>
                <label for="preco_venda" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">Preço de Venda (R$)</label>
                <input type="number" step="0.01" id="preco_venda" name="preco_venda" required value="<?= htmlspecialchars($produto['preco_venda']) ?>" class="block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-black text-[#111184]">
            </div>

            <div>
                <label for="estoque_minimo" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">Estoque Mínimo (Alerta)</label>
                <input type="number" min="1" id="estoque_minimo" name="estoque_minimo" value="<?= htmlspecialchars($produto['estoque_minimo']) ?>" class="block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Estoque Atual em Loja</label>
                <div class="px-3.5 py-2.5 bg-slate-100 rounded-xl text-sm font-bold text-slate-700">
                    <?= $produto['estoque_atual'] ?> <?= $produto['unidade_medida'] ?> (ajuste via módulo de estoque)
                </div>
            </div>

        </div>

        <div class="pt-4 border-t border-slate-200 flex justify-end gap-3">
            <a href="<?= base_url('modules/produtos/index.php') ?>" class="px-5 py-2.5 rounded-lg border border-slate-300 text-slate-700 font-bold hover:bg-slate-50 transition text-sm">
                Cancelar
            </a>
            <button type="submit" class="btn-lee-accent text-sm">
                Salvar Alterações
            </button>
        </div>
    </form>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
