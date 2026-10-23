<?php
/**
 * ===================================================================
 * Sistema Integrado de Gestão Comercial — SIGC (Lee Market)
 * Cadastro de Novo Produto (modules/produtos/cadastrar.php)
 * ===================================================================
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../bd.php';
require_once __DIR__ . '/../../includes/audit.php';

require_permission('produtos_cadastrar');

$pdo = getDBConnection();
$user = current_user();
$errors = [];

// Carregar categorias e fornecedores para o formulário
$categorias = $pdo->query("SELECT * FROM categorias ORDER BY nome ASC")->fetchAll();
$fornecedores = $pdo->query("SELECT * FROM fornecedores WHERE status = 'ativo' ORDER BY razao_social ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validate_csrf($csrf)) {
        $errors[] = 'Token de segurança expirou. Atualize e tente novamente.';
    }

    $codigoBarras = trim($_POST['codigo_barras'] ?? '');
    $nome = trim($_POST['nome'] ?? '');
    $categoriaId = !empty($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : null;
    $fornecedorId = !empty($_POST['fornecedor_id']) ? (int)$_POST['fornecedor_id'] : null;
    $precoCusto = floatval(str_replace(['.', ','], ['', '.'], $_POST['preco_custo'] ?? '0'));
    $precoVenda = floatval(str_replace(['.', ','], ['', '.'], $_POST['preco_venda'] ?? '0'));
    $estoqueInicial = (int)($_POST['estoque_inicial'] ?? 0);
    $estoqueMinimo = (int)($_POST['estoque_minimo'] ?? 5);
    $unidadeMedida = trim($_POST['unidade_medida'] ?? 'UN');

    // Validações
    if (empty($codigoBarras)) $errors[] = 'Informe o código de barras ou código identificador do produto.';
    if (empty($nome)) $errors[] = 'Informe o nome do produto.';
    if ($precoVenda <= 0) $errors[] = 'O preço de venda deve ser maior que zero.';
    if ($estoqueInicial < 0) $errors[] = 'O estoque inicial não pode ser negativo.';

    // Verificar se código de barras já existe
    if (empty($errors)) {
        $checkStmt = $pdo->prepare("SELECT id FROM produtos WHERE codigo_barras = ? LIMIT 1");
        $checkStmt->execute([$codigoBarras]);
        if ($checkStmt->fetch()) {
            $errors[] = 'Já existe um produto cadastrado com este código de barras.';
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $insertStmt = $pdo->prepare("
                INSERT INTO produtos 
                (codigo_barras, nome, categoria_id, fornecedor_id, preco_custo, preco_venda, estoque_atual, estoque_minimo, unidade_medida, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'ativo')
            ");
            $insertStmt->execute([
                $codigoBarras,
                $nome,
                $categoriaId,
                $fornecedorId,
                $precoCusto,
                $precoVenda,
                $estoqueInicial,
                $estoqueMinimo,
                $unidadeMedida
            ]);
            $novoProdutoId = (int)$pdo->lastInsertId();

            // Se houve estoque inicial, registrar movimentação
            if ($estoqueInicial > 0) {
                $movStmt = $pdo->prepare("
                    INSERT INTO movimentacoes_estoque (produto_id, usuario_id, tipo, quantidade, motivo, observacao)
                    VALUES (?, ?, 'entrada', ?, 'Carga inicial de cadastro', 'Estoque inicial inserido no cadastro do produto')
                ");
                $movStmt->execute([$novoProdutoId, $user['id'], $estoqueInicial]);
            }

            // Registrar Auditoria
            log_audit(
                'Produtos',
                'Cadastro de Produto',
                "Cadastrou o produto '{$nome}' (Cód: {$codigoBarras}) com preço R$ {$precoVenda} e estoque {$estoqueInicial} {$unidadeMedida}."
            );

            $pdo->commit();

            set_toast('success', 'Produto Cadastrado!', "O produto '{$nome}' foi inserido com sucesso no sistema Lee Market.");
            redirect('modules/produtos/index.php');

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Erro ao salvar produto: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Cadastrar Novo Produto';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="max-w-4xl mx-auto space-y-6">

    <!-- Cabeçalho -->
    <div class="flex items-center justify-between bg-white p-5 sm:p-6 rounded-2xl border border-slate-200 shadow-sm">
        <div>
            <div class="flex items-center gap-2">
                <a href="<?= base_url('modules/produtos/index.php') ?>" class="p-1 rounded-lg hover:bg-slate-100 text-slate-500">
                    <i data-lucide="arrow-left" class="w-5 h-5"></i>
                </a>
                <h1 class="text-xl sm:text-2xl font-black text-[#111184]">Cadastrar Novo Produto</h1>
            </div>
            <p class="text-xs text-slate-500 mt-1 pl-8">Preencha os dados do produto para disponibilizá-lo para venda no PDV e controle de estoque.</p>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 flex items-start gap-3">
            <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-600 shrink-0 mt-0.5"></i>
            <div class="text-xs text-rose-800 space-y-1">
                <p class="font-bold">Corrija os seguintes apontamentos:</p>
                <ul class="list-disc pl-4 space-y-0.5">
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <!-- Formulário -->
    <form action="<?= base_url('modules/produtos/cadastrar.php') ?>" method="POST" class="bg-white p-6 sm:p-8 rounded-2xl border border-slate-200 shadow-sm space-y-6">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            
            <!-- Código de Barras -->
            <div>
                <label for="codigo_barras" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">
                    Código de Barras / EAN <span class="text-rose-500">*</span>
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <i data-lucide="barcode" class="w-4 h-4"></i>
                    </div>
                    <input type="text" id="codigo_barras" name="codigo_barras" required placeholder="Ex: 7891234567890"
                           value="<?= htmlspecialchars($_POST['codigo_barras'] ?? '') ?>"
                           class="block w-full pl-10 pr-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold focus:ring-2 focus:ring-[#111184] focus:border-[#111184]">
                </div>
            </div>

            <!-- Unidade de Medida -->
            <div>
                <label for="unidade_medida" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">
                    Unidade de Medida
                </label>
                <select id="unidade_medida" name="unidade_medida" class="block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold focus:ring-2 focus:ring-[#111184] focus:border-[#111184]">
                    <option value="UN" <?= ($_POST['unidade_medida'] ?? '') === 'UN' ? 'selected' : '' ?>>Unidade (UN)</option>
                    <option value="KG" <?= ($_POST['unidade_medida'] ?? '') === 'KG' ? 'selected' : '' ?>>Quilograma (KG)</option>
                    <option value="PCT" <?= ($_POST['unidade_medida'] ?? '') === 'PCT' ? 'selected' : '' ?>>Pacote (PCT)</option>
                    <option value="CX" <?= ($_POST['unidade_medida'] ?? '') === 'CX' ? 'selected' : '' ?>>Caixa (CX)</option>
                    <option value="L" <?= ($_POST['unidade_medida'] ?? '') === 'L' ? 'selected' : '' ?>>Litro (L)</option>
                </select>
            </div>

            <!-- Nome do Produto -->
            <div class="sm:col-span-2">
                <label for="nome" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">
                    Nome Completo do Produto <span class="text-rose-500">*</span>
                </label>
                <input type="text" id="nome" name="nome" required placeholder="Ex: Arroz Tipo 1 5kg Marca X"
                       value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>"
                       class="block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold focus:ring-2 focus:ring-[#111184] focus:border-[#111184]">
            </div>

            <!-- Categoria -->
            <div>
                <label for="categoria_id" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">
                    Categoria
                </label>
                <select id="categoria_id" name="categoria_id" class="block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold focus:ring-2 focus:ring-[#111184] focus:border-[#111184]">
                    <option value="">Selecione uma categoria...</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ((int)($_POST['categoria_id'] ?? 0) === (int)$cat['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Fornecedor -->
            <div>
                <label for="fornecedor_id" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">
                    Fornecedor Vinculado
                </label>
                <select id="fornecedor_id" name="fornecedor_id" class="block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold focus:ring-2 focus:ring-[#111184] focus:border-[#111184]">
                    <option value="">Selecione um fornecedor...</option>
                    <?php foreach ($fornecedores as $forn): ?>
                        <option value="<?= $forn['id'] ?>" <?= ((int)($_POST['fornecedor_id'] ?? 0) === (int)$forn['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($forn['razao_social']) ?> (<?= htmlspecialchars($forn['nome_fantasia'] ?? '') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Preço de Custo -->
            <div>
                <label for="preco_custo" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">
                    Preço de Custo (R$)
                </label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 font-bold text-xs">R$</span>
                    <input type="number" step="0.01" min="0" id="preco_custo" name="preco_custo" placeholder="0,00"
                           value="<?= htmlspecialchars($_POST['preco_custo'] ?? '') ?>"
                           class="block w-full pl-10 pr-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold focus:ring-2 focus:ring-[#111184] focus:border-[#111184]">
                </div>
            </div>

            <!-- Preço de Venda -->
            <div>
                <label for="preco_venda" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">
                    Preço de Venda (R$) <span class="text-rose-500">*</span>
                </label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-emerald-600 font-bold text-xs">R$</span>
                    <input type="number" step="0.01" min="0.01" id="preco_venda" name="preco_venda" required placeholder="0,00"
                           value="<?= htmlspecialchars($_POST['preco_venda'] ?? '') ?>"
                           class="block w-full pl-10 pr-3.5 py-2.5 bg-emerald-50/50 border border-emerald-300 rounded-xl text-sm font-black text-slate-900 focus:ring-2 focus:ring-[#111184] focus:border-[#111184]">
                </div>
            </div>

            <!-- Estoque Inicial -->
            <div>
                <label for="estoque_inicial" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">
                    Estoque Inicial Disponível
                </label>
                <input type="number" min="0" id="estoque_inicial" name="estoque_inicial" placeholder="0"
                       value="<?= htmlspecialchars($_POST['estoque_inicial'] ?? '0') ?>"
                       class="block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold focus:ring-2 focus:ring-[#111184] focus:border-[#111184]">
            </div>

            <!-- Estoque Mínimo de Segurança -->
            <div>
                <label for="estoque_minimo" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">
                    Estoque Mínimo (Alerta de Reposição)
                </label>
                <input type="number" min="1" id="estoque_minimo" name="estoque_minimo" placeholder="5"
                       value="<?= htmlspecialchars($_POST['estoque_minimo'] ?? '5') ?>"
                       class="block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold focus:ring-2 focus:ring-[#111184] focus:border-[#111184]">
            </div>

        </div>

        <div class="pt-4 border-t border-slate-200 flex flex-col-reverse sm:flex-row justify-end gap-3">
            <a href="<?= base_url('modules/produtos/index.php') ?>" class="px-5 py-2.5 rounded-lg border border-slate-300 text-slate-700 font-bold hover:bg-slate-50 transition text-sm text-center">
                Cancelar
            </a>
            <button type="submit" class="btn-lee-accent text-sm">
                <i data-lucide="check" class="w-4 h-4 text-black"></i>
                <span>Confirmar e Salvar Produto</span>
            </button>
        </div>

    </form>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
