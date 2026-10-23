<?php
/**
 * ===================================================================
 * Sistema Integrado de Gestão Comercial — SIGC (Lee Market)
 * Entrada / Reabastecimento de Estoque (modules/estoque/entrada.php)
 * ===================================================================
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../bd.php';
require_once __DIR__ . '/../../includes/audit.php';

require_permission('estoque_gerenciar');

$pdo = getDBConnection();
$user = current_user();

$selectedId = (int)($_GET['id'] ?? 0);
$produto = null;

if ($selectedId > 0) {
    $stmt = $pdo->prepare("SELECT p.*, c.nome as categoria_nome, f.razao_social as fornecedor_nome FROM produtos p LEFT JOIN categorias c ON p.categoria_id = c.id LEFT JOIN fornecedores f ON p.fornecedor_id = f.id WHERE p.id = ? AND p.status = 'ativo'");
    $stmt->execute([$selectedId]);
    $produto = $stmt->fetch();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validate_csrf($csrf)) {
        $errors[] = 'Token expirado. Tente novamente.';
    }

    $prodId = (int)($_POST['produto_id'] ?? 0);
    $quantidade = (int)($_POST['quantidade'] ?? 0);
    $custoUnitario = floatval(str_replace(['.', ','], ['', '.'], $_POST['custo_unitario'] ?? '0'));
    $notaFiscal = trim($_POST['nota_fiscal'] ?? '');
    $observacao = trim($_POST['observacao'] ?? 'Entrada de mercadoria para reposição');

    if ($prodId <= 0) $errors[] = 'Selecione um produto.';
    if ($quantidade <= 0) $errors[] = 'A quantidade recebida deve ser maior que zero.';

    if (empty($errors)) {
        $pStmt = $pdo->prepare("SELECT * FROM produtos WHERE id = ?");
        $pStmt->execute([$prodId]);
        $prodAtual = $pStmt->fetch();

        if (!$prodAtual) {
            $errors[] = 'Produto não localizado.';
        } else {
            try {
                $pdo->beginTransaction();

                // 1. Atualizar estoque
                $upStmt = $pdo->prepare("UPDATE produtos SET estoque_atual = estoque_atual + ? WHERE id = ?");
                $upStmt->execute([$quantidade, $prodId]);

                // 2. Se custo foi informado e diferente, opcionalmente atualizar preco_custo
                if ($custoUnitario > 0) {
                    $pdo->prepare("UPDATE produtos SET preco_custo = ? WHERE id = ?")->execute([$custoUnitario, $prodId]);
                }

                // 3. Registrar movimentação
                $movStmt = $pdo->prepare("
                    INSERT INTO movimentacoes_estoque (produto_id, usuario_id, tipo, quantidade, motivo, observacao)
                    VALUES (?, ?, 'entrada', ?, 'Reabastecimento de Estoque', ?)
                ");
                $obsFinal = "NF: " . ($notaFiscal ?: 'S/N') . " &bull; " . $observacao;
                $movStmt->execute([$prodId, $user['id'], $quantidade, $obsFinal]);

                // 4. Auditoria
                log_audit(
                    'Estoque',
                    'Entrada de Estoque',
                    "Entrada de +{$quantidade} {$prodAtual['unidade_medida']} no produto '{$prodAtual['nome']}'. Estoque anterior: {$prodAtual['estoque_atual']} -> Novo: " . ($prodAtual['estoque_atual'] + $quantidade) . "."
                );

                $pdo->commit();

                set_toast('success', 'Estoque Reabastecido!', "Entrada de +{$quantidade} {$prodAtual['unidade_medida']} computada com sucesso para '{$prodAtual['nome']}'.");
                redirect('modules/estoque/index.php');

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = 'Erro ao processar entrada de estoque: ' . $e->getMessage();
            }
        }
    }
}

$produtosDisponiveis = $pdo->query("SELECT id, codigo_barras, nome, estoque_atual, unidade_medida, preco_custo FROM produtos WHERE status = 'ativo' ORDER BY nome ASC")->fetchAll();

$pageTitle = 'Entrada de Estoque';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="max-w-3xl mx-auto space-y-6">

    <div class="flex items-center justify-between bg-white p-5 sm:p-6 rounded-2xl border border-slate-200 shadow-sm">
        <div>
            <div class="flex items-center gap-2">
                <a href="<?= base_url('modules/estoque/index.php') ?>" class="p-1 rounded-lg hover:bg-slate-100 text-slate-500">
                    <i data-lucide="arrow-left" class="w-5 h-5"></i>
                </a>
                <h1 class="text-xl sm:text-2xl font-black text-[#111184]">Entrada e Reabastecimento</h1>
            </div>
            <p class="text-xs text-slate-500 mt-1 pl-8">Adicione novas remessas de mercadorias entregues por fornecedores ao estoque da loja.</p>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-800 space-y-1">
            <?php foreach ($errors as $err): ?>
                <p>&bull; <?= htmlspecialchars($err) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="bg-white p-6 sm:p-8 rounded-2xl border border-slate-200 shadow-sm space-y-6">
        
        <div>
            <label for="select_prod" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-2">
                1. Selecione o Produto para Reabastecer:
            </label>
            <select id="select_prod" onchange="window.location.href = '<?= base_url('modules/estoque/entrada.php?id=') ?>' + this.value" class="w-full px-4 py-3 bg-slate-50 border border-slate-300 rounded-xl text-sm font-bold text-slate-900 focus:ring-2 focus:ring-[#111184]">
                <option value="">-- Escolha o produto --</option>
                <?php foreach ($produtosDisponiveis as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= ($produto && (int)$produto['id'] === (int)$p['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['nome']) ?> &bull; Estoque atual: <?= $p['estoque_atual'] ?> <?= $p['unidade_medida'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($produto): ?>
            <form action="<?= base_url('modules/estoque/entrada.php') ?>" method="POST" class="pt-6 border-t border-slate-200 space-y-6">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="produto_id" value="<?= $produto['id'] ?>">

                <div class="bg-slate-50 p-5 rounded-2xl border border-slate-200 grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Produto</span>
                        <p class="font-extrabold text-slate-900 text-sm mt-0.5"><?= htmlspecialchars($produto['nome']) ?></p>
                        <span class="text-[11px] text-slate-500 font-mono">Cód: <?= htmlspecialchars($produto['codigo_barras']) ?></span>
                    </div>

                    <div>
                        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Fornecedor</span>
                        <p class="font-bold text-slate-800 text-sm mt-0.5"><?= htmlspecialchars($produto['fornecedor_nome'] ?? 'Geral') ?></p>
                        <span class="text-[11px] text-slate-500">Custo Cadastrado: <?= format_money($produto['preco_custo']) ?></span>
                    </div>

                    <div class="p-3 bg-white rounded-xl border border-slate-200">
                        <span class="text-[11px] font-bold text-[#111184] uppercase tracking-wider block">Estoque Atual</span>
                        <p class="text-xl font-black text-[#111184] mt-0.5">
                            <?= $produto['estoque_atual'] ?> <span class="text-xs font-semibold"><?= htmlspecialchars($produto['unidade_medida']) ?></span>
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label for="quantidade" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">
                            Quantidade Recebida <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="number" min="1" id="quantidade" name="quantidade" required placeholder="Ex: 50"
                                   class="block w-full px-3.5 py-3 bg-emerald-50/40 border border-emerald-300 rounded-xl text-base font-black text-emerald-800 focus:ring-2 focus:ring-[#111184]">
                            <span class="absolute right-3.5 top-3.5 text-xs font-bold text-slate-400">
                                <?= htmlspecialchars($produto['unidade_medida']) ?>
                            </span>
                        </div>
                    </div>

                    <div>
                        <label for="custo_unitario" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">
                            Custo Unitário da Remessa (R$)
                        </label>
                        <input type="number" step="0.01" min="0" id="custo_unitario" name="custo_unitario" value="<?= htmlspecialchars($produto['preco_custo']) ?>"
                               class="block w-full px-3.5 py-3 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold focus:ring-2 focus:ring-[#111184]">
                    </div>

                    <div>
                        <label for="nota_fiscal" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">
                            Número da Nota Fiscal / Pedido
                        </label>
                        <input type="text" id="nota_fiscal" name="nota_fiscal" placeholder="Ex: NF-e 004812"
                               class="block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-[#111184]">
                    </div>

                    <div>
                        <label for="observacao" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">
                            Observações
                        </label>
                        <input type="text" id="observacao" name="observacao" placeholder="Ex: Carga entregue às 14h sem avarias"
                               class="block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-[#111184]">
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-200 flex justify-end gap-3">
                    <a href="<?= base_url('modules/estoque/index.php') ?>" class="px-5 py-2.5 rounded-lg border border-slate-300 text-slate-700 font-bold hover:bg-slate-50 transition text-sm">
                        Cancelar
                    </a>
                    <button type="submit" class="btn-lee-primary text-sm">
                        <i data-lucide="package-plus" class="w-4 h-4"></i>
                        <span>Confirmar Entrada no Estoque</span>
                    </button>
                </div>

            </form>
        <?php else: ?>
            <div class="p-8 text-center text-slate-400 bg-slate-50 rounded-xl border border-dashed border-slate-200">
                <i data-lucide="box" class="w-10 h-10 mx-auto text-slate-300 mb-2"></i>
                <p class="font-bold text-slate-600">Selecione um produto para lançar a reposição de mercadoria.</p>
            </div>
        <?php endif; ?>

    </div>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
