<?php
/**
 * ===================================================================
 * Sistema Integrado de Gestão Comercial — SIGC (Lee Market)
 * Cadastro de Novo Colaborador (modules/rh/cadastrar.php)
 * ===================================================================
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../bd.php';
require_once __DIR__ . '/../../includes/audit.php';

require_permission('rh_colaboradores');

$pdo = getDBConnection();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validate_csrf($csrf)) {
        $errors[] = 'Token expirado. Tente novamente.';
    }

    $cpf = trim($_POST['cpf'] ?? '');
    $nomeCompleto = trim($_POST['nome_completo'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $senhaConfirma = $_POST['senha_confirma'] ?? '';
    $perfil = $_POST['perfil'] ?? 'operador';
    $cargo = trim($_POST['cargo'] ?? '');

    $cleanCpf = clean_mask($cpf);
    if (strlen($cleanCpf) !== 11) $errors[] = 'CPF inválido. Deve conter 11 dígitos numéricos.';
    if (empty($nomeCompleto)) $errors[] = 'Nome completo é obrigatório.';
    if (empty($senha)) $errors[] = 'A senha de acesso é obrigatória.';
    if (strlen($senha) < 6) $errors[] = 'A senha deve ter no mínimo 6 caracteres.';
    if ($senha !== $senhaConfirma) $errors[] = 'As senhas digitadas não conferem.';
    if (!in_array($perfil, ['dono', 'gerente', 'operador'])) $errors[] = 'Perfil de acesso inválido.';

    $formattedCpf = format_cpf($cleanCpf);

    if (empty($errors)) {
        // Verificar se CPF já existe
        $check = $pdo->prepare("SELECT id FROM usuarios WHERE cpf = ? OR REPLACE(REPLACE(cpf, '.', ''), '-', '') = ? LIMIT 1");
        $check->execute([$formattedCpf, $cleanCpf]);
        if ($check->fetch()) {
            $errors[] = 'Já existe um colaborador cadastrado com este CPF.';
        }
    }

    if (empty($errors)) {
        try {
            // Hash Bcrypt da senha conforme requisitos CORE
            $senhaHash = password_hash($senha, PASSWORD_BCRYPT);

            $stmt = $pdo->prepare("
                INSERT INTO usuarios (cpf, nome_completo, email, telefone, senha, perfil, cargo, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'ativo')
            ");
            $stmt->execute([$formattedCpf, $nomeCompleto, $email, $telefone, $senhaHash, $perfil, $cargo]);

            log_audit(
                'RH',
                'Cadastro de Colaborador',
                "Cadastrou o colaborador '{$nomeCompleto}' (CPF: {$formattedCpf}) com perfil '{$perfil}' e cargo '{$cargo}'."
            );

            set_toast('success', 'Colaborador Cadastrado!', "O colaborador '{$nomeCompleto}' foi registrado com sucesso.");
            redirect('modules/rh/index.php');

        } catch (Throwable $e) {
            $errors[] = 'Erro ao salvar colaborador: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Novo Colaborador';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="max-w-3xl mx-auto space-y-6">

    <div class="flex items-center justify-between bg-white p-5 sm:p-6 rounded-2xl border border-slate-200 shadow-sm">
        <div>
            <div class="flex items-center gap-2">
                <a href="<?= base_url('modules/rh/index.php') ?>" class="p-1 rounded-lg hover:bg-slate-100 text-slate-500">
                    <i data-lucide="arrow-left" class="w-5 h-5"></i>
                </a>
                <h1 class="text-xl sm:text-2xl font-black text-[#111184]">Novo Colaborador</h1>
            </div>
            <p class="text-xs text-slate-500 mt-1 pl-8">Cadastre novos operadores, gerentes ou administradores no supermercado Lee Market.</p>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-800 space-y-1">
            <?php foreach ($errors as $err): ?>
                <p>&bull; <?= htmlspecialchars($err) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form action="<?= base_url('modules/rh/cadastrar.php') ?>" method="POST" class="bg-white p-6 sm:p-8 rounded-2xl border border-slate-200 shadow-sm space-y-5">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="cpf" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">CPF <span class="text-rose-500">*</span></label>
                <input type="text" id="cpf" name="cpf" required placeholder="000.000.000-00" value="<?= htmlspecialchars($_POST['cpf'] ?? '') ?>" class="mask-cpf block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
            </div>

            <div>
                <label for="perfil" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">Perfil de Acesso <span class="text-rose-500">*</span></label>
                <select id="perfil" name="perfil" required class="block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-bold text-[#111184]">
                    <option value="operador" <?= ($_POST['perfil'] ?? '') === 'operador' ? 'selected' : '' ?>>Operador de Caixa (PDV e Baixas)</option>
                    <option value="gerente" <?= ($_POST['perfil'] ?? '') === 'gerente' ? 'selected' : '' ?>>Gerente (Estoque e Relatórios)</option>
                    <option value="dono" <?= ($_POST['perfil'] ?? '') === 'dono' ? 'selected' : '' ?>>Dono / Administrador (Acesso Total)</option>
                </select>
            </div>

            <div class="sm:col-span-2">
                <label for="nome_completo" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">Nome Completo <span class="text-rose-500">*</span></label>
                <input type="text" id="nome_completo" name="nome_completo" required placeholder="Ex: Lucas Ferreira de Souza" value="<?= htmlspecialchars($_POST['nome_completo'] ?? '') ?>" class="block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
            </div>

            <div>
                <label for="cargo" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">Cargo / Função</label>
                <input type="text" id="cargo" name="cargo" placeholder="Ex: Operador de Caixa Matutino" value="<?= htmlspecialchars($_POST['cargo'] ?? '') ?>" class="block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
            </div>

            <div>
                <label for="telefone" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">Telefone / Celular</label>
                <input type="text" id="telefone" name="telefone" placeholder="(11) 90000-0000" value="<?= htmlspecialchars($_POST['telefone'] ?? '') ?>" class="mask-phone block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
            </div>

            <div class="sm:col-span-2">
                <label for="email" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">E-mail Institucional</label>
                <input type="email" id="email" name="email" placeholder="colaborador@leemarket.com.br" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" class="block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
            </div>

            <div>
                <label for="senha" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">Senha Inicial (Bcrypt) <span class="text-rose-500">*</span></label>
                <input type="password" id="senha" name="senha" required placeholder="Mínimo 6 caracteres" class="block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
            </div>

            <div>
                <label for="senha_confirma" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">Confirmar Senha <span class="text-rose-500">*</span></label>
                <input type="password" id="senha_confirma" name="senha_confirma" required placeholder="Repita a senha" class="block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
            </div>
        </div>

        <div class="pt-4 border-t border-slate-200 flex justify-end gap-3">
            <a href="<?= base_url('modules/rh/index.php') ?>" class="px-5 py-2.5 rounded-lg border border-slate-300 text-slate-700 font-bold hover:bg-slate-50 transition text-sm">
                Cancelar
            </a>
            <button type="submit" class="btn-lee-accent text-sm">
                Cadastrar Colaborador
            </button>
        </div>
    </form>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
