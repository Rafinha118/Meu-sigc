<?php
/**
 * ===================================================================
 * Sistema Integrado de Gestão Comercial — SIGC (Lee Market)
 * Cadastro de Fornecedor (modules/fornecedores/cadastrar.php)
 * ===================================================================
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../bd.php';
require_once __DIR__ . '/../../includes/audit.php';

require_permission('fornecedores_gerenciar');

$pdo = getDBConnection();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validate_csrf($csrf)) {
        $errors[] = 'Token expirado. Tente novamente.';
    }

    $cnpj = trim($_POST['cnpj'] ?? '');
    $razaoSocial = trim($_POST['razao_social'] ?? '');
    $nomeFantasia = trim($_POST['nome_fantasia'] ?? '');
    $contatoNome = trim($_POST['contato_nome'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');

    if (empty($cnpj)) $errors[] = 'CNPJ é obrigatório.';
    if (empty($razaoSocial)) $errors[] = 'Razão Social é obrigatória.';
    if (empty($telefone)) $errors[] = 'Telefone é obrigatório.';
    if (empty($email)) $errors[] = 'E-mail é obrigatório.';

    if (empty($errors)) {
        // Verificar se CNPJ já existe
        $check = $pdo->prepare("SELECT id FROM fornecedores WHERE cnpj = ? LIMIT 1");
        $check->execute([$cnpj]);
        if ($check->fetch()) {
            $errors[] = 'Já existe um fornecedor cadastrado com este CNPJ.';
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO fornecedores (cnpj, razao_social, nome_fantasia, contato_nome, telefone, email, endereco, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'ativo')
            ");
            $stmt->execute([$cnpj, $razaoSocial, $nomeFantasia, $contatoNome, $telefone, $email, $endereco]);

            log_audit(
                'Fornecedores',
                'Cadastro de Fornecedor',
                "Cadastrou o fornecedor '{$razaoSocial}' (CNPJ: {$cnpj})."
            );

            set_toast('success', 'Fornecedor Cadastrado!', "O parceiro comercial '{$razaoSocial}' foi cadastrado com sucesso.");
            redirect('modules/fornecedores/index.php');

        } catch (Throwable $e) {
            $errors[] = 'Erro ao salvar fornecedor: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Cadastrar Fornecedor';
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
                <h1 class="text-xl sm:text-2xl font-black text-[#111184]">Cadastrar Fornecedor</h1>
            </div>
            <p class="text-xs text-slate-500 mt-1 pl-8">Adicione um novo distribuidor ou fabricante para fornecimento de produtos ao supermercado.</p>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-800 space-y-1">
            <?php foreach ($errors as $err): ?>
                <p>&bull; <?= htmlspecialchars($err) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form action="<?= base_url('modules/fornecedores/cadastrar.php') ?>" method="POST" class="bg-white p-6 sm:p-8 rounded-2xl border border-slate-200 shadow-sm space-y-5">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="cnpj" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">CNPJ <span class="text-rose-500">*</span></label>
                <input type="text" id="cnpj" name="cnpj" required placeholder="00.000.000/0000-00" value="<?= htmlspecialchars($_POST['cnpj'] ?? '') ?>" class="block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
            </div>

            <div>
                <label for="nome_fantasia" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">Nome Fantasia</label>
                <input type="text" id="nome_fantasia" name="nome_fantasia" placeholder="Nome comercial conhecido" value="<?= htmlspecialchars($_POST['nome_fantasia'] ?? '') ?>" class="block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
            </div>

            <div class="sm:col-span-2">
                <label for="razao_social" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">Razão Social <span class="text-rose-500">*</span></label>
                <input type="text" id="razao_social" name="razao_social" required placeholder="Ex: Distribuidora de Alimentos S.A." value="<?= htmlspecialchars($_POST['razao_social'] ?? '') ?>" class="block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
            </div>

            <div>
                <label for="contato_nome" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">Nome do Contato / Representante</label>
                <input type="text" id="contato_nome" name="contato_nome" placeholder="Ex: Carlos Silva" value="<?= htmlspecialchars($_POST['contato_nome'] ?? '') ?>" class="block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
            </div>

            <div>
                <label for="telefone" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">Telefone / WhatsApp <span class="text-rose-500">*</span></label>
                <input type="text" id="telefone" name="telefone" required placeholder="(11) 90000-0000" value="<?= htmlspecialchars($_POST['telefone'] ?? '') ?>" class="mask-phone block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
            </div>

            <div class="sm:col-span-2">
                <label for="email" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">E-mail para Pedidos <span class="text-rose-500">*</span></label>
                <input type="email" id="email" name="email" required placeholder="pedidos@fornecedor.com.br" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" class="block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
            </div>

            <div class="sm:col-span-2">
                <label for="endereco" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">Endereço Comercial</label>
                <input type="text" id="endereco" name="endereco" placeholder="Rua, número, bairro, cidade/UF" value="<?= htmlspecialchars($_POST['endereco'] ?? '') ?>" class="block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
            </div>
        </div>

        <div class="pt-4 border-t border-slate-200 flex justify-end gap-3">
            <a href="<?= base_url('modules/fornecedores/index.php') ?>" class="px-5 py-2.5 rounded-lg border border-slate-300 text-slate-700 font-bold hover:bg-slate-50 transition text-sm">
                Cancelar
            </a>
            <button type="submit" class="btn-lee-accent text-sm">
                Salvar Fornecedor
            </button>
        </div>
    </form>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
