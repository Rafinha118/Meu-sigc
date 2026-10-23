<?php
/**
 * ===================================================================
 * Sistema Integrado de Gestão Comercial — SIGC (Lee Market)
 * Registro de Saída de Estoque / Baixa (modules/estoque/saida.php)
 * ===================================================================
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../bd.php';
require_once __DIR__ . '/../../includes/audit.php';

require_permission('estoque_saida');

$pdo = getDBConnection();
$user = current_user();

$selectedId = (int)($_GET['id'] ?? 0);
$produto = null;

if ($selectedId > 0) {
    $stmt = $pdo->prepare("SELECT p.*, c.nome as categoria_nome FROM produtos p LEFT JOIN categorias c ON p.categoria_id = c.id WHERE p.id = ? AND p.status = 'ativo'");
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
    $motivo = trim($_POST['motivo'] ?? 'Avaria');
    $observacao = trim($_POST['observacao'] ?? '');

    if ($prodId <= 0) $errors[] = 'Selecione um produto.';
    if ($quantidade <= 0) $errors[] = 'A quantidade a retirar deve ser maior que zero.';

    if (empty($errors)) {
        $pStmt = $pdo->prepare("SELECT * FROM produtos WHERE id = ?");
        $pStmt->execute([$prodId]);
        $prodAtual = $pStmt->fetch();

        if (!$prodAtual) {
            $errors[] = 'Produto não localizado.';
        } elseif ($prodAtual['estoque_atual'] < $quantidade) {
            $errors[] = "Quantidade indisponível em estoque. Estoque atual: {$prodAtual['estoque_atual']} {$prodAtual['unidade_medida']}.";
        } else {
            try {
                $pdo->beginTransaction();

                // 1. Atualizar quantidade no estoque
                $upStmt = $pdo->prepare("UPDATE produtos SET estoque_atual = estoque_atual - ? WHERE id = ?");
                $upStmt->execute([$quantidade, $prodId]);

                // 2. Registrar movimentação de saída
                $movStmt = $pdo->prepare("
                    INSERT INTO movimentacoes_estoque (produto_id, usuario_id, tipo, quantidade, motivo, observacao)
                    VALUES (?, ?, 'saida', ?, ?, ?)
                ");
                $movStmt->execute([$prodId, $user['id'], $quantidade, $motivo, $observacao]);

                // 3. Auditoria
                log_audit(
                    'Estoque',
                    'Saída de Estoque',
                    "Saída de {$quantidade} {$prodAtual['unidade_medida']} do produto '{$prodAtual['nome']}' (Cód: {$prodAtual['codigo_barras']}). Motivo: {$motivo}. Obs: {$observacao}"
                );

                $pdo->commit();

                set_toast('success', 'Saída Registrada!', "Baixa de {$quantidade} {$prodAtual['unidade_medida']} realizada com sucesso para o produto '{$prodAtual['nome']}'.");
                redirect('modules/estoque/index.php');

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = 'Erro ao processar saída de estoque: ' . $e->getMessage();
            }
        }
    }
}

// Lista de produtos ativos com estoque > 0
$produtosDisponiveis = $pdo->query("SELECT id, codigo_barras, nome, estoque_atual, unidade_medida FROM produtos WHERE status = 'ativo' ORDER BY nome ASC")->fetchAll();

$pageTitle = 'Registrar Saída de Estoque';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="max-w-3xl mx-auto space-y-6">

    <!-- Cabeçalho -->
    <div class="flex items-center justify-between bg-white p-5 sm:p-6 rounded-2xl border border-slate-200 shadow-sm">
        <div>
            <div class="flex items-center gap-2">
                <a href="<?= base_url('modules/estoque/index.php') ?>" class="p-1 rounded-lg hover:bg-slate-100 text-slate-500">
                    <i data-lucide="arrow-left" class="w-5 h-5"></i>
                </a>
                <h1 class="text-xl sm:text-2xl font-black text-[#111184]">Registrar Saída de Estoque</h1>
            </div>
            <p class="text-xs text-slate-500 mt-1 pl-8">Registre baixas no estoque por motivo de avarias, vencimento, quebras ou uso interno.</p>
        </div>
        <div class="hidden sm:block">
            <span class="px-3 py-1 bg-rose-100 text-rose-800 font-bold text-xs rounded-full flex items-center gap-1">
                <i data-lucide="arrow-up-right" class="w-3.5 h-3.5"></i> Baixa Operacional
            </span>
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
        
        <!-- Passo 1: Selecionar Produto -->
        <div>
            <label for="select_prod" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-2">
                1. Selecione o Produto para dar Baixa:
            </label>
            <select id="select_prod" onchange="window.location.href = '<?= base_url('modules/estoque/saida.php?id=') ?>' + this.value" class="w-full px-4 py-3 bg-slate-50 border border-slate-300 rounded-xl text-sm font-bold text-slate-900 focus:ring-2 focus:ring-[#111184]">
                <option value="">-- Selecione o produto --</option>
                <?php foreach ($produtosDisponiveis as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= ($produto && (int)$produto['id'] === (int)$p['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['nome']) ?> &bull; Disponível: <?= $p['estoque_atual'] ?> <?= $p['unidade_medida'] ?> (Cód: <?= $p['codigo_barras'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($produto): ?>
            <!-- Passo 2 & 3: Formulário de Saída -->
            <form id="form-saida-estoque" action="<?= base_url('modules/estoque/saida.php') ?>" method="POST" class="pt-6 border-t border-slate-200 space-y-6">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="produto_id" value="<?= $produto['id'] ?>">

                <!-- Dados do Produto Selecionado -->
                <div class="bg-slate-50 p-5 rounded-2xl border border-slate-200 grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Produto</span>
                        <p class="font-extrabold text-slate-900 text-sm mt-0.5"><?= htmlspecialchars($produto['nome']) ?></p>
                        <span class="text-[11px] text-slate-500 font-mono">Cód: <?= htmlspecialchars($produto['codigo_barras']) ?></span>
                    </div>

                    <div>
                        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Preço de Venda</span>
                        <p class="font-bold text-slate-800 text-sm mt-0.5"><?= format_money($produto['preco_venda']) ?></p>
                        <span class="text-[11px] text-slate-500">Unidade: <?= htmlspecialchars($produto['unidade_medida']) ?></span>
                    </div>

                    <div class="p-3 bg-white rounded-xl border border-slate-200">
                        <span class="text-[11px] font-bold text-[#111184] uppercase tracking-wider block">Estoque Disponível</span>
                        <p class="text-xl font-black text-[#111184] mt-0.5">
                            <?= $produto['estoque_atual'] ?> <span class="text-xs font-semibold"><?= htmlspecialchars($produto['unidade_medida']) ?></span>
                        </p>
                    </div>
                </div>

                <!-- Quantidade e Motivo -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label for="quantidade" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">
                            Quantidade a Retirar <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="number" min="1" max="<?= max(1, $produto['estoque_atual']) ?>" id="quantidade" name="quantidade" required placeholder="1"
                                   class="block w-full px-3.5 py-3 bg-rose-50/30 border border-rose-300 rounded-xl text-base font-black text-rose-700 focus:ring-2 focus:ring-[#111184]">
                            <span class="absolute right-3.5 top-3.5 text-xs font-bold text-slate-400">
                                <?= htmlspecialchars($produto['unidade_medida']) ?>
                            </span>
                        </div>
                    </div>

                    <div>
                        <label for="motivo" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">
                            Motivo da Saída <span class="text-rose-500">*</span>
                        </label>
                        <select id="motivo" name="motivo" required class="block w-full px-3.5 py-3 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold focus:ring-2 focus:ring-[#111184]">
                            <option value="Avaria / Quebra de Produto">Avaria / Quebra de Produto</option>
                            <option value="Vencimento / Prazo de Validade">Vencimento / Prazo de Validade</option>
                            <option value="Consumo / Uso Interno">Consumo / Uso Interno</option>
                            <option value="Ajuste de Balanço / Inventário">Ajuste de Balanço / Inventário</option>
                            <option value="Devolução ao Fornecedor">Devolução ao Fornecedor</option>
                            <option value="Doação Autorizada">Doação Autorizada</option>
                            <option value="Extravio / Perda">Extravio / Perda</option>
                        </select>
                    </div>

                    <div class="sm:col-span-2">
                        <label for="observacao" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">
                            Observações Complementares (Opcional)
                        </label>
                        <input type="text" id="observacao" name="observacao" placeholder="Ex: Produto quebrado na gôndola 4 pelo cliente"
                               class="block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-[#111184]">
                    </div>
                </div>

                <!-- Confirmação -->
                <div class="pt-4 border-t border-slate-200 flex flex-col-reverse sm:flex-row justify-end gap-3">
                    <a href="<?= base_url('modules/estoque/index.php') ?>" class="px-5 py-2.5 rounded-lg border border-slate-300 text-slate-700 font-bold hover:bg-slate-50 transition text-sm text-center">
                        Cancelar
                    </a>
                    <button type="button" onclick="confirmarSaidaEstoque()" class="btn-lee-accent text-sm">
                        <i data-lucide="check" class="w-4 h-4 text-black"></i>
                        <span>Confirmar Saída do Estoque</span>
                    </button>
                </div>

            </form>

            <script>
            function confirmarSaidaEstoque() {
                const qtd = parseInt(document.getElementById('quantidade').value, 10);
                const motivo = document.getElementById('motivo').value;
                const disponivel = <?= (int)$produto['estoque_atual'] ?>;

                if (isNaN(qtd) || qtd <= 0) {
                    showToast('error', 'Quantidade Inválida', 'Informe uma quantidade válida maior que zero.');
                    return;
                }

                if (qtd > disponivel) {
                    showToast('error', 'Estoque Insuficiente', 'A quantidade solicitada (' + qtd + ') excede o disponível em estoque (' + disponivel + ').');
                    return;
                }

                confirmAction({
                    title: 'Confirmar Baixa de Estoque',
                    message: 'Confirma a saída de ' + qtd + ' <?= htmlspecialchars($produto['unidade_medida']) ?> do produto "<?= htmlspecialchars($produto['nome']) ?>" por motivo de "' + motivo + '"?',
                    confirmText: 'Sim, Dar Baixa',
                    cancelText: 'Voltar',
                    isDanger: true,
                    onConfirm: () => {
                        document.getElementById('form-saida-estoque').submit();
                    }
                });
            }
            </script>
        <?php else: ?>
            <div class="p-8 text-center text-slate-400 bg-slate-50 rounded-xl border border-dashed border-slate-200">
                <i data-lucide="package" class="w-10 h-10 mx-auto text-slate-300 mb-2"></i>
                <p class="font-bold text-slate-600">Selecione um produto acima para registrar a saída de mercadoria.</p>
            </div>
        <?php endif; ?>

    </div>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
