<?php
/**
 * ===================================================================
 * Sistema Integrado de Gestão Comercial — SIGC (Lee Market)
 * Módulo de Alteração de Preços (Exclusivo Dono)
 * ===================================================================
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../bd.php';
require_once __DIR__ . '/../../includes/audit.php';

require_permission('produtos_alterar_preco');

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
        $errors[] = 'Sessão expirada. Tente novamente.';
    }

    $prodId = (int)($_POST['produto_id'] ?? 0);
    $novoPreco = floatval(str_replace(['.', ','], ['', '.'], $_POST['novo_preco'] ?? '0'));
    $motivo = trim($_POST['motivo'] ?? 'Ajuste comercial de margem');

    // Validações
    if ($prodId <= 0) $errors[] = 'Selecione um produto válido.';
    if ($novoPreco <= 0) $errors[] = 'O novo preço de venda deve ser maior que zero (R$ 0,00).';

    if (empty($errors)) {
        // Buscar dados atuais do produto
        $pStmt = $pdo->prepare("SELECT * FROM produtos WHERE id = ?");
        $pStmt->execute([$prodId]);
        $prodAtual = $pStmt->fetch();

        if (!$prodAtual) {
            $errors[] = 'Produto não localizado.';
        } else {
            $precoAntigo = (float)$prodAtual['preco_venda'];
            
            // Atualizar preço
            $upStmt = $pdo->prepare("UPDATE produtos SET preco_venda = ? WHERE id = ?");
            $upStmt->execute([$novoPreco, $prodId]);

            // Auditoria com dados antigos e novos
            log_audit(
                'Produtos',
                'Alteração de Preço',
                "Preço do produto '{$prodAtual['nome']}' (Cód: {$prodAtual['codigo_barras']}) alterado de " . 
                format_money($precoAntigo) . " para " . format_money($novoPreco) . ". Motivo: {$motivo}."
            );

            set_toast('success', 'Preço Alterado!', "O preço de '{$prodAtual['nome']}' foi atualizado para " . format_money($novoPreco));
            redirect('modules/produtos/index.php');
        }
    }
}

// Carregar lista de todos os produtos ativos para seleção
$todosProdutos = $pdo->query("SELECT id, codigo_barras, nome, preco_venda, preco_custo FROM produtos WHERE status = 'ativo' ORDER BY nome ASC")->fetchAll();

$pageTitle = 'Alteração de Preço (Exclusivo Dono)';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="max-w-3xl mx-auto space-y-6">

    <!-- Cabeçalho -->
    <div class="flex items-center justify-between bg-white p-5 sm:p-6 rounded-2xl border border-slate-200 shadow-sm">
        <div>
            <div class="flex items-center gap-2">
                <a href="<?= base_url('modules/produtos/index.php') ?>" class="p-1 rounded-lg hover:bg-slate-100 text-slate-500">
                    <i data-lucide="arrow-left" class="w-5 h-5"></i>
                </a>
                <h1 class="text-xl sm:text-2xl font-black text-[#111184]">Modificação de Preços de Produtos</h1>
            </div>
            <p class="text-xs text-slate-500 mt-1 pl-8">Funcionalidade de acesso restrito ao Dono &bull; Todas as modificações são auditadas.</p>
        </div>
        <div class="hidden sm:block">
            <span class="px-3 py-1 bg-[#FFD32C] text-black font-extrabold text-xs rounded-full">
                Exclusivo Dono
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
            <label for="select_produto" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-2">
                1. Selecione o Produto que terá o preço modificado:
            </label>
            <select id="select_produto" onchange="window.location.href = '<?= base_url('modules/produtos/alterar_preco.php?id=') ?>' + this.value" class="w-full px-4 py-3 bg-slate-50 border border-slate-300 rounded-xl text-sm font-bold text-slate-900 focus:ring-2 focus:ring-[#111184]">
                <option value="">-- Escolha um produto da lista --</option>
                <?php foreach ($todosProdutos as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= ($produto && (int)$produto['id'] === (int)$p['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['nome']) ?> &bull; Atual: <?= format_money($p['preco_venda']) ?> (Cód: <?= $p['codigo_barras'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($produto): ?>
            <!-- Passo 2 & 3: Formulário de Confirmação do Novo Preço -->
            <form id="form-alterar-preco" action="<?= base_url('modules/produtos/alterar_preco.php') ?>" method="POST" class="pt-6 border-t border-slate-200 space-y-6">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="produto_id" value="<?= $produto['id'] ?>">

                <!-- Card de Comparação -->
                <div class="bg-slate-50 p-5 rounded-2xl border border-slate-200 grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Produto Selecionado</span>
                        <p class="font-extrabold text-slate-900 text-sm mt-0.5"><?= htmlspecialchars($produto['nome']) ?></p>
                        <span class="text-[11px] text-slate-500 font-mono">Cód: <?= htmlspecialchars($produto['codigo_barras']) ?></span>
                    </div>

                    <div>
                        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Preço de Custo</span>
                        <p class="font-bold text-slate-600 text-sm mt-0.5"><?= format_money($produto['preco_custo']) ?></p>
                        <span class="text-[10px] text-slate-400">Referência de margem</span>
                    </div>

                    <div class="p-3 bg-white rounded-xl border border-slate-200">
                        <span class="text-[11px] font-bold text-[#111184] uppercase tracking-wider block">Preço Atual de Venda</span>
                        <p class="text-xl font-black text-[#111184] mt-0.5"><?= format_money($produto['preco_venda']) ?></p>
                    </div>
                </div>

                <!-- Input Novo Preço -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label for="novo_preco" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">
                            Novo Preço de Venda (R$) <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-emerald-600 font-black text-sm">R$</span>
                            <input type="number" step="0.01" min="0.01" id="novo_preco" name="novo_preco" required placeholder="0.00"
                                   class="block w-full pl-11 pr-3.5 py-3 bg-emerald-50/40 border border-emerald-300 rounded-xl text-base font-black text-slate-900 focus:ring-2 focus:ring-[#111184]">
                        </div>
                    </div>

                    <div>
                        <label for="motivo" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">
                            Motivo / Justificativa da Alteração
                        </label>
                        <input type="text" id="motivo" name="motivo" placeholder="Ex: Aumento do fornecedor, promoção, etc."
                               value="Reajuste comercial de tabela"
                               class="block w-full px-3.5 py-3 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold focus:ring-2 focus:ring-[#111184]">
                    </div>
                </div>

                <!-- Ações -->
                <div class="pt-4 border-t border-slate-200 flex flex-col-reverse sm:flex-row justify-end gap-3">
                    <a href="<?= base_url('modules/produtos/index.php') ?>" class="px-5 py-2.5 rounded-lg border border-slate-300 text-slate-700 font-bold hover:bg-slate-50 transition text-sm text-center">
                        Cancelar
                    </a>
                    <button type="button" onclick="confirmarNovoPreco()" class="btn-lee-accent text-sm">
                        <i data-lucide="check" class="w-4 h-4 text-black"></i>
                        <span>Confirmar Alteração de Preço</span>
                    </button>
                </div>
            </form>

            <script>
            function confirmarNovoPreco() {
                const novo = document.getElementById('novo_preco').value;
                if (!novo || parseFloat(novo) <= 0) {
                    showToast('error', 'Valor Inválido', 'Por favor, informe um novo valor válido maior que zero.');
                    return;
                }

                confirmAction({
                    title: 'Confirmar Reajuste de Preço',
                    message: 'Confirma a alteração do produto "<?= htmlspecialchars($produto['nome']) ?>" para R$ ' + parseFloat(novo).toFixed(2).replace('.', ',') + '? Esta ação será registrada no histórico de auditoria.',
                    confirmText: 'Sim, Atualizar Preço',
                    cancelText: 'Voltar',
                    isDanger: false,
                    onConfirm: () => {
                        document.getElementById('form-alterar-preco').submit();
                    }
                });
            }
            </script>
        <?php else: ?>
            <div class="p-8 text-center text-slate-400 bg-slate-50 rounded-xl border border-dashed border-slate-200">
                <i data-lucide="tag" class="w-10 h-10 mx-auto text-slate-300 mb-2"></i>
                <p class="font-bold text-slate-600">Selecione um produto acima para visualizar o preço atual e definir o novo valor.</p>
            </div>
        <?php endif; ?>

    </div>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
