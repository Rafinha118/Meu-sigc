<?php
/**
 * ===================================================================
 * Sistema Integrado de Gestão Comercial — SIGC (Lee Market)
 * Edição de Fornecedor (modules/fornecedores/editar.php)
 * ===================================================================
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../bd.php';
require_once __DIR__ . '/../../includes/audit.php';

require_permission('fornecedores_gerenciar');

$id = (int)($_GET['id'] ?? 0);
$pdo = getDBConnection();

$stmt = $pdo->prepare("SELECT * FROM fornecedores WHERE id = ? AND status = 'ativo' LIMIT 1");
$stmt->execute([$id]);
$fornecedor = $stmt->fetch();

if (!$fornecedor) {
    set_toast('error', 'Não Encontrado', 'Fornecedor não localizado.');
    redirect('modules/fornecedores/index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validate_csrf($csrf)) {
        $errors[] = 'Token expirado. Tente novamente.';
    }

    $razaoSocial = trim($_POST['razao_social'] ?? '');
    $nomeFantasia = trim($_POST['nome_fantasia'] ?? '');
    $contatoNome = trim($_POST['contato_nome'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');

    if (empty($razaoSocial)) $errors[] = 'Razão Social é obrigatória.';
    if (empty($telefone)) $errors[] = 'Telefone é obrigatório.';
    if (empty($email)) $errors[] = 'E-mail é obrigatório.';

    if (empty($errors)) {
        try {
            $up = $pdo->prepare("
                UPDATE fornecedores SET 
                    razao_social = ?, 
                    nome_fantasia = ?, 
                    contato_nome = ?, 
                    telefone = ?, 
                    email = ?, 
                    endereco = ?
                WHERE id = ?
            ");
            $up->execute([$razaoSocial, $nomeFantasia, $contatoNome, $telefone, $email, $endereco, $id]);

            log_audit(
                'Fornecedores',
                'Edição de Fornecedor',
                "Atualizou dados do fornecedor '{$razaoSocial}' (ID: {$id})."
            );

            set_toast('success', 'Fornecedor Atualizado', "Dados de '{$razaoSocial}' atualizados com sucesso.");
            redirect('modules/fornecedores/index.php');

        } catch (Throwable $e) {
            $errors[] = 'Erro ao atualizar fornecedor: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Editar Fornecedor';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="max-w-3xl mx-auto space-y-6">

    <div class="flex items-center justify-between bg-white p-5 sm:p-6 rounded-2xl border border-slate-200 shadow-sm">
        <div>
            <div class="flex items-center gap-2">
                <a href="<?= base_url('modules/fornecedores/index.php') ?>" class="p-1 rounded-lg hover:bg-slate-100 text-slate-500">
                    <i data-lucide="arrow-left" class="w-5 h-5"></i>
                </a>
                <h1 class="text-xl sm:text-2xl font-black text-[#111184]">Editar Fornecedor</h1>
            </div>
            <p class="text-xs text-slate-500 mt-1 pl-8">CNPJ: <?= htmlspecialchars($fornecedor['cnpj']) ?></p>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-800 space-y-1">
            <?php foreach ($errors as $err): ?>
                <p>&bull; <?= htmlspecialchars($err) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form action="<?= base_url('modules/fornecedores/editar.php?id=' . $id) ?>" method="POST" class="bg-white p-6 sm:p-8 rounded-2xl border border-slate-200 shadow-sm space-y-5">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">CNPJ (Fixo)</label>
                <input type="text" disabled value="<?= htmlspecialchars($fornecedor['cnpj']) ?>" class="block w-full px-3.5 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-sm font-mono text-slate-500">
            </div>

            <div>
                <label for="nome_fantasia" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">Nome Fantasia</label>
                <input type="text" id="nome_fantasia" name="nome_fantasia" value="<?= htmlspecialchars($fornecedor['nome_fantasia']) ?>" class="block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
            </div>

            <div class="sm:col-span-2">
                <label for="razao_social" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">Razão Social</label>
                <input type="text" id="razao_social" name="razao_social" required value="<?= htmlspecialchars($fornecedor['razao_social']) ?>" class="block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
            </div>

            <div>
                <label for="contato_nome" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">Contato Comercial</label>
                <input type="text" id="contato_nome" name="contato_nome" value="<?= htmlspecialchars($fornecedor['contato_nome']) ?>" class="block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
            </div>

            <div>
                <label for="telefone" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">Telefone</label>
                <input type="text" id="telefone" name="telefone" required value="<?= htmlspecialchars($fornecedor['telefone']) ?>" class="mask-phone block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
            </div>

            <div class="sm:col-span-2">
                <label for="email" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">E-mail</label>
                <input type="email" id="email" name="email" required value="<?= htmlspecialchars($fornecedor['email']) ?>" class="block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
            </div>

            <div class="sm:col-span-2">
                <label for="endereco" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">Endereço</label>
                <input type="text" id="endereco" name="endereco" value="<?= htmlspecialchars($fornecedor['endereco']) ?>" class="block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
            </div>
        </div>

        <div class="pt-4 border-t border-slate-200 flex justify-end gap-3">
            <a href="<?= base_url('modules/fornecedores/index.php') ?>" class="px-5 py-2.5 rounded-lg border border-slate-300 text-slate-700 font-bold hover:bg-slate-50 transition text-sm">
                Cancelar
            </a>
            <button type="submit" class="btn-lee-accent text-sm">
                Salvar Alterações
            </button>
        </div>
    </form>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
