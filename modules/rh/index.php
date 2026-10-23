<?php
/**
 * ===================================================================
 * Sistema Integrado de Gestão Comercial — SIGC (Lee Market)
 * Gestão de RH e Funções dos Colaboradores (modules/rh/index.php)
 * ===================================================================
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../bd.php';
require_once __DIR__ . '/../../includes/audit.php';

require_permission('rh_colaboradores');

$pdo = getDBConnection();
$user = current_user();
$busca = trim($_GET['q'] ?? '');

// Alternar Status (Ativo / Inativo) via GET com CSRF
if (isset($_GET['toggle_id'])) {
    $toggleId = (int)$_GET['toggle_id'];
    $csrf = $_GET['csrf_token'] ?? '';

    if (validate_csrf($csrf)) {
        if ($toggleId === (int)$user['id']) {
            set_toast('warning', 'Ação Bloqueada', 'Você não pode desativar seu próprio usuário em uso.');
        } else {
            $stmtUser = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
            $stmtUser->execute([$toggleId]);
            $uTarget = $stmtUser->fetch();

            if ($uTarget) {
                $novoStatus = $uTarget['status'] === 'ativo' ? 'inativo' : 'ativo';
                $pdo->prepare("UPDATE usuarios SET status = ? WHERE id = ?")->execute([$novoStatus, $toggleId]);

                log_audit(
                    'RH',
                    'Alteração de Status de Colaborador',
                    "Status do colaborador '{$uTarget['nome_completo']}' alterado para '{$novoStatus}'."
                );

                set_toast('success', 'Status Alterado', "Colaborador '{$uTarget['nome_completo']}' agora está {$novoStatus}.");
            }
        }
    }
    redirect('modules/rh/index.php');
}

$sql = "SELECT * FROM usuarios WHERE 1=1";
$params = [];

if (!empty($busca)) {
    $sql .= " AND (nome_completo LIKE ? OR cpf LIKE ? OR email LIKE ? OR cargo LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

$sql .= " ORDER BY perfil ASC, nome_completo ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$colaboradores = $stmt->fetchAll();

$pageTitle = 'Gestão de RH e Colaboradores';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="space-y-6">

    <!-- Cabeçalho -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-5 sm:p-6 rounded-2xl border border-slate-200 shadow-sm">
        <div>
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-[#111184]"></span>
                <h1 class="text-xl sm:text-2xl font-black text-[#111184]">Gestão de Recursos Humanos (RH)</h1>
            </div>
            <p class="text-xs text-slate-500 mt-1">Gerencie os colaboradores, suas funções, perfis de acesso e credenciais de segurança.</p>
        </div>

        <a href="<?= base_url('modules/rh/cadastrar.php') ?>" class="btn-lee-accent text-sm w-full sm:w-auto">
            <i data-lucide="user-plus" class="w-4 h-4 text-black"></i>
            <span>Novo Colaborador</span>
        </a>
    </div>

    <!-- Barra de Pesquisa -->
    <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-sm">
        <form method="GET" action="<?= base_url('modules/rh/index.php') ?>" class="flex gap-2">
            <div class="relative flex-1">
                <input type="text" name="q" value="<?= htmlspecialchars($busca) ?>" placeholder="Buscar por nome, CPF, e-mail ou cargo..."
                       class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-[#111184]">
                <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-3.5"></i>
            </div>
            <button type="submit" class="btn-lee-primary px-5 py-2.5 text-sm">
                Buscar
            </button>
            <?php if (!empty($busca)): ?>
                <a href="<?= base_url('modules/rh/index.php') ?>" class="p-2.5 border border-slate-300 rounded-xl text-slate-500 hover:bg-slate-100" title="Limpar">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Tabela de Colaboradores -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider border-b border-slate-200">
                        <th class="py-3.5 px-4 font-bold">Colaborador</th>
                        <th class="py-3.5 px-4 font-bold">CPF</th>
                        <th class="py-3.5 px-4 font-bold">Perfil / Nível</th>
                        <th class="py-3.5 px-4 font-bold">Cargo</th>
                        <th class="py-3.5 px-4 font-bold">Contato</th>
                        <th class="py-3.5 px-4 font-bold text-center">Status</th>
                        <th class="py-3.5 px-4 font-bold text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($colaboradores as $colab): 
                        $badgeStyle = 'bg-slate-100 text-slate-700';
                        if ($colab['perfil'] === 'dono') $badgeStyle = 'bg-[#FFD32C] text-black font-extrabold';
                        elseif ($colab['perfil'] === 'gerente') $badgeStyle = 'bg-blue-100 text-[#111184] font-bold';
                        elseif ($colab['perfil'] === 'operador') $badgeStyle = 'bg-emerald-100 text-emerald-800 font-bold';
                    ?>
                        <tr class="hover:bg-slate-50/80 transition text-xs">
                            <td class="py-3.5 px-4">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full bg-[#111184] text-[#FFD32C] font-bold flex items-center justify-center shrink-0">
                                        <?= strtoupper(substr($colab['nome_completo'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <span class="font-bold text-slate-900 block text-sm"><?= htmlspecialchars($colab['nome_completo']) ?></span>
                                        <span class="text-slate-400 text-[11px]"><?= htmlspecialchars($colab['email']) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3.5 px-4 font-mono font-semibold text-slate-700">
                                <?= format_cpf($colab['cpf']) ?>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="px-2.5 py-1 rounded-full text-[11px] <?= $badgeStyle ?>">
                                    <?= ucfirst($colab['perfil']) ?>
                                </span>
                            </td>
                            <td class="py-3.5 px-4 font-medium text-slate-800">
                                <?= htmlspecialchars($colab['cargo'] ?: '-') ?>
                            </td>
                            <td class="py-3.5 px-4 text-slate-500">
                                <?= htmlspecialchars($colab['telefone'] ?: '-') ?>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <?php if ($colab['status'] === 'ativo'): ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700">
                                        Ativo
                                    </span>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-700">
                                        Inativo
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="<?= base_url('modules/rh/editar.php?id=' . $colab['id']) ?>" class="p-1.5 text-slate-500 hover:text-blue-600 hover:bg-slate-100 rounded-lg transition" title="Editar Colaborador">
                                        <i data-lucide="edit-3" class="w-4 h-4"></i>
                                    </a>

                                    <?php if ($colab['id'] !== $user['id']): ?>
                                        <button type="button" onclick="confirmarStatus(<?= $colab['id'] ?>, <?= json_encode($colab['nome_completo']) ?>, '<?= $colab['status'] ?>')" class="p-1.5 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition" title="<?= $colab['status'] === 'ativo' ? 'Desativar' : 'Ativar' ?>">
                                            <i data-lucide="<?= $colab['status'] === 'ativo' ? 'user-x' : 'user-check' ?>" class="w-4 h-4"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
function confirmarStatus(id, nome, statusAtual) {
    const acao = statusAtual === 'ativo' ? 'desativar' : 'ativar';
    confirmAction({
        title: (statusAtual === 'ativo' ? 'Desativar' : 'Ativar') + ' Colaborador',
        message: 'Deseja realmente ' + acao + ' o acesso do colaborador "' + nome + '" ao sistema?',
        confirmText: 'Sim, Confirmar',
        cancelText: 'Cancelar',
        isDanger: statusAtual === 'ativo',
        onConfirm: () => {
            window.location.href = '<?= base_url('modules/rh/index.php?toggle_id=') ?>' + id + '&csrf_token=<?= csrf_token() ?>';
        }
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
