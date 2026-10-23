<?php
/**
 * ===================================================================
 * Sistema Integrado de Gestão Comercial — SIGC (Lee Market)
 * Edição de Colaborador (modules/rh/editar.php)
 * ===================================================================
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../bd.php';
require_once __DIR__ . '/../../includes/audit.php';

require_permission('rh_colaboradores');

$id = (int)($_GET['id'] ?? 0);
$pdo = getDBConnection();

$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$colaborador = $stmt->fetch();

if (!$colaborador) {
    set_toast('error', 'Não Encontrado', 'Colaborador não localizado.');
    redirect('modules/rh/index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validate_csrf($csrf)) {
        $errors[] = 'Token expirado. Tente novamente.';
    }

    $nomeCompleto = trim($_POST['nome_completo'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $perfil = $_POST['perfil'] ?? $colaborador['perfil'];
    $cargo = trim($_POST['cargo'] ?? '');
    $novaSenha = $_POST['nova_senha'] ?? '';

    if (empty($nomeCompleto)) $errors[] = 'Nome completo é obrigatório.';
    if (!in_array($perfil, ['dono', 'gerente', 'operador'])) $errors[] = 'Perfil de acesso inválido.';

    if (!empty($novaSenha) && strlen($novaSenha) < 6) {
        $errors[] = 'A nova senha deve ter no mínimo 6 caracteres.';
    }

    if (empty($errors)) {
        try {
            $alteracoes = [];
            if ($colaborador['nome_completo'] !== $nomeCompleto) $alteracoes[] = "Nome: '{$colaborador['nome_completo']}' -> '{$nomeCompleto}'";
            if ($colaborador['perfil'] !== $perfil) $alteracoes[] = "Perfil: '{$colaborador['perfil']}' -> '{$perfil}'";
            if ($colaborador['cargo'] !== $cargo) $alteracoes[] = "Cargo: '{$colaborador['cargo']}' -> '{$cargo}'";

            if (!empty($novaSenha)) {
                $senhaHash = password_hash($novaSenha, PASSWORD_BCRYPT);
                $up = $pdo->prepare("
                    UPDATE usuarios SET 
                        nome_completo = ?, email = ?, telefone = ?, perfil = ?, cargo = ?, senha = ?
                    WHERE id = ?
                ");
                $up->execute([$nomeCompleto, $email, $telefone, $perfil, $cargo, $senhaHash, $id]);
                $alteracoes[] = "Senha redefinida com Bcrypt";
            } else {
                $up = $pdo->prepare("
                    UPDATE usuarios SET 
                        nome_completo = ?, email = ?, telefone = ?, perfil = ?, cargo = ?
                    WHERE id = ?
                ");
                $up->execute([$nomeCompleto, $email, $telefone, $perfil, $cargo, $id]);
            }

            log_audit(
                'RH',
                'Edição de Colaborador',
                "Atualizou dados do colaborador '{$nomeCompleto}' (ID: {$id}). " . implode('; ', $alteracoes)
            );

            set_toast('success', 'Colaborador Atualizado', "Cadastro de '{$nomeCompleto}' atualizado com sucesso.");
            redirect('modules/rh/index.php');

        } catch (Throwable $e) {
            $errors[] = 'Erro ao atualizar colaborador: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Editar Colaborador';
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
                <h1 class="text-xl sm:text-2xl font-black text-[#111184]">Editar Colaborador</h1>
            </div>
            <p class="text-xs text-slate-500 mt-1 pl-8">CPF: <?= format_cpf($colaborador['cpf']) ?></p>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-800 space-y-1">
            <?php foreach ($errors as $err): ?>
                <p>&bull; <?= htmlspecialchars($err) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form action="<?= base_url('modules/rh/editar.php?id=' . $id) ?>" method="POST" class="bg-white p-6 sm:p-8 rounded-2xl border border-slate-200 shadow-sm space-y-5">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">CPF (Identificador)</label>
                <input type="text" disabled value="<?= format_cpf($colaborador['cpf']) ?>" class="block w-full px-3.5 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-sm font-mono text-slate-500">
            </div>

            <div>
                <label for="perfil" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">Perfil de Acesso</label>
                <select id="perfil" name="perfil" class="block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-bold text-[#111184]">
                    <option value="operador" <?= $colaborador['perfil'] === 'operador' ? 'selected' : '' ?>>Operador de Caixa</option>
                    <option value="gerente" <?= $colaborador['perfil'] === 'gerente' ? 'selected' : '' ?>>Gerente Operacional</option>
                    <option value="dono" <?= $colaborador['perfil'] === 'dono' ? 'selected' : '' ?>>Dono / Administrador</option>
                </select>
            </div>

            <div class="sm:col-span-2">
                <label for="nome_completo" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">Nome Completo</label>
                <input type="text" id="nome_completo" name="nome_completo" required value="<?= htmlspecialchars($colaborador['nome_completo']) ?>" class="block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
            </div>

            <div>
                <label for="cargo" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">Cargo</label>
                <input type="text" id="cargo" name="cargo" value="<?= htmlspecialchars($colaborador['cargo']) ?>" class="block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
            </div>

            <div>
                <label for="telefone" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">Telefone</label>
                <input type="text" id="telefone" name="telefone" value="<?= htmlspecialchars($colaborador['telefone']) ?>" class="mask-phone block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
            </div>

            <div class="sm:col-span-2">
                <label for="email" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">E-mail</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($colaborador['email']) ?>" class="block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
            </div>

            <div class="sm:col-span-2 pt-2 border-t border-slate-100">
                <label for="nova_senha" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">Redefinir Senha (Opcional)</label>
                <input type="password" id="nova_senha" name="nova_senha" placeholder="Deixe em branco para manter a senha atual" class="block w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold">
                <span class="text-[10px] text-slate-400 mt-1 block">A nova senha será criptografada com Bcrypt imediatamente.</span>
            </div>
        </div>

        <div class="pt-4 border-t border-slate-200 flex justify-end gap-3">
            <a href="<?= base_url('modules/rh/index.php') ?>" class="px-5 py-2.5 rounded-lg border border-slate-300 text-slate-700 font-bold hover:bg-slate-50 transition text-sm">
                Cancelar
            </a>
            <button type="submit" class="btn-lee-accent text-sm">
                Salvar Alterações
            </button>
        </div>
    </form>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
