<?php
/**
 * ===================================================================
 * Sistema Integrado de Gestão Comercial — SIGC (Lee Market)
 * Painel Central de Auditoria e Logs de Segurança (modules/auditoria/index.php)
 * ===================================================================
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../bd.php';

require_permission('auditoria');

$pdo = getDBConnection();
$user = current_user();
$driver = getDBDriver();

$setor = $_GET['setor'] ?? '';
$usuarioId = (int)($_GET['usuario_id'] ?? 0);
$dataInicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
$dataFim = $_GET['data_fim'] ?? date('Y-m-d');
$busca = trim($_GET['q'] ?? '');

$sql = "SELECT * FROM auditoria_logs WHERE 1=1";
$params = [];

if (!empty($setor)) {
    $sql .= " AND setor = ?";
    $params[] = $setor;
}

if ($usuarioId > 0) {
    $sql .= " AND usuario_id = ?";
    $params[] = $usuarioId;
}

if (!empty($dataInicio)) {
    if ($driver === 'sqlite') {
        $sql .= " AND strftime('%Y-%m-%d', criado_em) >= ?";
    } else {
        $sql .= " AND DATE(criado_em) >= ?";
    }
    $params[] = $dataInicio;
}

if (!empty($dataFim)) {
    if ($driver === 'sqlite') {
        $sql .= " AND strftime('%Y-%m-%d', criado_em) <= ?";
    } else {
        $sql .= " AND DATE(criado_em) <= ?";
    }
    $params[] = $dataFim;
}

if (!empty($busca)) {
    $sql .= " AND (acao LIKE ? OR detalhes LIKE ? OR usuario_nome LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

$sql .= " ORDER BY criado_em DESC LIMIT 250";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Listas para os filtros
$setores = ['Autenticação', 'Produtos', 'Estoque', 'Vendas', 'Fornecedores', 'Financeiro', 'RH', 'Segurança'];
$usuarios = $pdo->query("SELECT id, nome_completo, cargo FROM usuarios ORDER BY nome_completo ASC")->fetchAll();

$pageTitle = 'Histórico Geral de Auditoria';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="space-y-6">

    <!-- Cabeçalho -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-5 sm:p-6 rounded-2xl border border-slate-200 shadow-sm">
        <div>
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-[#111184]"></span>
                <h1 class="text-xl sm:text-2xl font-black text-[#111184]">Histórico e Logs de Auditoria</h1>
            </div>
            <p class="text-xs text-slate-500 mt-1">Rastreabilidade completa de todas as ações, modificações, cadastros e baixas no sistema.</p>
        </div>

        <span class="px-3.5 py-1.5 bg-[#FFD32C] text-black font-extrabold text-xs rounded-full flex items-center gap-1.5 shadow-sm">
            <i data-lucide="shield-check" class="w-4 h-4"></i>
            <span>Auditoria Ativa 24/7</span>
        </span>
    </div>

    <!-- Filtros de Auditoria -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <form method="GET" action="<?= base_url('modules/auditoria/index.php') ?>" class="grid grid-cols-1 sm:grid-cols-12 gap-3 text-xs">
            
            <div class="sm:col-span-3">
                <label for="setor" class="block font-bold text-slate-500 uppercase tracking-wider mb-1">Setor Auditado</label>
                <select id="setor" name="setor" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl font-semibold">
                    <option value="">Todos os Setores</option>
                    <?php foreach ($setores as $s): ?>
                        <option value="<?= $s ?>" <?= $setor === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sm:col-span-3">
                <label for="usuario_id" class="block font-bold text-slate-500 uppercase tracking-wider mb-1">Colaborador</label>
                <select id="usuario_id" name="usuario_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl font-semibold">
                    <option value="0">Todos os Colaboradores</option>
                    <?php foreach ($usuarios as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $usuarioId === (int)$u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['nome_completo']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sm:col-span-2">
                <label for="data_inicio" class="block font-bold text-slate-500 uppercase tracking-wider mb-1">Data Início</label>
                <input type="date" id="data_inicio" name="data_inicio" value="<?= htmlspecialchars($dataInicio) ?>" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl font-semibold">
            </div>

            <div class="sm:col-span-2">
                <label for="data_fim" class="block font-bold text-slate-500 uppercase tracking-wider mb-1">Data Fim</label>
                <input type="date" id="data_fim" name="data_fim" value="<?= htmlspecialchars($dataFim) ?>" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl font-semibold">
            </div>

            <div class="sm:col-span-2 flex items-end">
                <button type="submit" class="btn-lee-primary w-full py-2 justify-center font-bold">
                    Filtrar Logs
                </button>
            </div>

        </form>
    </div>

    <!-- Tabela de Logs -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex justify-between items-center text-xs">
            <span class="font-bold text-[#111184]">Registros Localizados: <strong><?= count($logs) ?></strong></span>
            <span class="text-slate-400">Exibindo até 250 eventos mais recentes</span>
        </div>

        <?php if (empty($logs)): ?>
            <div class="py-12 text-center text-slate-400 text-xs">
                <i data-lucide="shield" class="w-12 h-12 mx-auto text-slate-300 mb-2"></i>
                <p class="font-bold text-slate-700">Nenhum evento registrado com os filtros atuais.</p>
                <p class="text-slate-400 mt-1">Altere o setor ou amplie o período de busca.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider border-b border-slate-200">
                            <th class="py-3 px-4 font-bold">Data & Hora</th>
                            <th class="py-3 px-4 font-bold">Setor</th>
                            <th class="py-3 px-4 font-bold">Ação</th>
                            <th class="py-3 px-4 font-bold">Usuário Responsável</th>
                            <th class="py-3 px-4 font-bold">Detalhes da Ação</th>
                            <th class="py-3 px-4 font-bold text-right">IP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($logs as $log): 
                            $badgeSetor = 'bg-slate-100 text-slate-700';
                            if ($log['setor'] === 'Produtos') $badgeSetor = 'bg-blue-100 text-blue-800';
                            elseif ($log['setor'] === 'Estoque') $badgeSetor = 'bg-amber-100 text-amber-800';
                            elseif ($log['setor'] === 'Vendas') $badgeSetor = 'bg-emerald-100 text-emerald-800';
                            elseif ($log['setor'] === 'Financeiro') $badgeSetor = 'bg-indigo-100 text-indigo-800';
                            elseif ($log['setor'] === 'RH') $badgeSetor = 'bg-purple-100 text-purple-800';
                            elseif ($log['setor'] === 'Autenticação') $badgeSetor = 'bg-sky-100 text-sky-800';
                        ?>
                            <tr class="hover:bg-slate-50/80 transition text-xs">
                                <td class="py-3 px-4 font-mono text-slate-600 whitespace-nowrap">
                                    <?= format_date($log['criado_em']) ?>
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $badgeSetor ?>">
                                        <?= htmlspecialchars($log['setor']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 font-bold text-slate-900 whitespace-nowrap">
                                    <?= htmlspecialchars($log['acao']) ?>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="font-semibold text-slate-800 block"><?= htmlspecialchars($log['usuario_nome']) ?></span>
                                    <span class="text-[10px] text-slate-400 uppercase font-bold"><?= htmlspecialchars($log['usuario_perfil']) ?></span>
                                </td>
                                <td class="py-3 px-4 text-slate-700 max-w-md">
                                    <span class="line-clamp-2" title="<?= htmlspecialchars($log['detalhes']) ?>">
                                        <?= htmlspecialchars($log['detalhes']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right font-mono text-slate-400 text-[11px] whitespace-nowrap">
                                    <?= htmlspecialchars($log['ip_address'] ?: '-') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
