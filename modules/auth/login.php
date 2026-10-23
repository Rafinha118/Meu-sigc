<?php
/**
 * ===================================================================
 * Sistema Integrado de Gestão Comercial — SIGC (Lee Market)
 * Tela de Autenticação (modules/auth/login.php)
 * ===================================================================
 */

require_once __DIR__ . '/../../bd.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

// Se já estiver logado, redireciona ao dashboard
if (is_logged_in()) {
    redirect('modules/dashboard/index.php');
}

$errorMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cpf = trim($_POST['cpf'] ?? '');
    $nomeCompleto = trim($_POST['nome_completo'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!validate_csrf($csrf)) {
        $errorMessage = 'Sessão expirada. Por favor, atualize a página e tente novamente.';
    } else {
        $auth = authenticate_user($cpf, $nomeCompleto, $senha);
        if ($auth['success']) {
            set_toast('success', 'Bem-vindo(a)!', 'Acesso autorizado com perfil: ' . ucfirst($auth['user']['perfil']));
            redirect('modules/dashboard/index.php');
        } else {
            $errorMessage = $auth['message'];
        }
    }
}

$pageTitle = 'Acesso ao Sistema';
$toast = get_toast();
?>
<!DOCTYPE html>
<html lang="pt-BR" class="h-full bg-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($pageTitle) ?> | Lee Market — SIGC</title>
    
    <!-- Google Fonts Isometra -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Isometra:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'lee-blue': '#111184',
                        'lee-blue-dark': '#0c0c66',
                        'lee-yellow': '#FFD32C',
                        'lee-yellow-dark': '#e6bd1f',
                    },
                    fontFamily: {
                        sans: ['Isometra', 'system-ui', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="<?= base_url('assets/css/custom.css') ?>">
</head>
<body class="min-h-full flex flex-col justify-center py-8 sm:px-6 lg:px-8 bg-gradient-to-br from-slate-100 via-slate-50 to-[#111184]/5">

    <!-- Container Toast -->
    <div id="toast-container" class="fixed top-4 right-4 z-50 flex flex-col gap-2 max-w-sm w-full pointer-events-none px-3"></div>

    <div class="sm:mx-auto sm:w-full sm:max-w-md px-4">
        
        <!-- Logo e Cabeçalho -->
        <div class="text-center">
            <div class="inline-flex w-16 h-16 rounded-2xl bg-[#111184] items-center justify-center text-white shadow-xl shadow-[#111184]/30 mb-4 ring-4 ring-[#FFD32C]/30">
                <i data-lucide="shopping-cart" class="w-9 h-9 text-[#FFD32C]"></i>
            </div>
            <div class="flex items-center justify-center gap-2">
                <h1 class="text-3xl font-black tracking-tight text-[#111184]">LEE</h1>
                <h1 class="text-3xl font-black tracking-tight text-[#FFD32C] drop-shadow-sm">MARKET</h1>
            </div>
            <p class="text-xs font-bold uppercase tracking-widest text-slate-500 mt-1">Sistema Integrado de Gestão Comercial (SIGC)</p>
        </div>

        <!-- Card de Login -->
        <div class="mt-7 bg-white py-8 px-6 sm:px-10 shadow-xl shadow-slate-200/70 rounded-3xl border border-slate-200/80">
            
            <div class="mb-6">
                <h2 class="text-lg font-black text-[#111184]">Entrar no Sistema</h2>
                <p class="text-xs text-slate-500 mt-0.5">Informe seu CPF, nome completo e senha de acesso.</p>
            </div>

            <?php if (!empty($errorMessage)): ?>
                <div class="mb-5 p-4 rounded-xl bg-rose-50 border border-rose-200 flex items-start gap-3">
                    <i data-lucide="alert-circle" class="w-5 h-5 text-rose-600 shrink-0 mt-0.5"></i>
                    <div class="text-xs text-rose-800 font-medium">
                        <?= htmlspecialchars($errorMessage) ?>
                    </div>
                </div>
            <?php endif; ?>

            <form action="<?= base_url('modules/auth/login.php') ?>" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <!-- CPF -->
                <div>
                    <label for="cpf" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">
                        CPF do Colaborador <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i data-lucide="id-card" class="w-4 h-4"></i>
                        </div>
                        <input type="text" id="cpf" name="cpf" required placeholder="000.000.000-00" maxlength="14"
                               value="<?= htmlspecialchars($_POST['cpf'] ?? '') ?>"
                               class="mask-cpf block w-full pl-10 pr-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-[#111184] focus:border-[#111184] transition">
                    </div>
                </div>

                <!-- Nome Completo -->
                <div>
                    <label for="nome_completo" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">
                        Nome Completo <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i data-lucide="user" class="w-4 h-4"></i>
                        </div>
                        <input type="text" id="nome_completo" name="nome_completo" required placeholder="Digite seu nome cadastrado"
                               value="<?= htmlspecialchars($_POST['nome_completo'] ?? '') ?>"
                               class="block w-full pl-10 pr-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-[#111184] focus:border-[#111184] transition">
                    </div>
                </div>

                <!-- Senha -->
                <div>
                    <label for="senha" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">
                        Senha de Acesso <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i data-lucide="lock" class="w-4 h-4"></i>
                        </div>
                        <input type="password" id="senha" name="senha" required placeholder="••••••••"
                               class="block w-full pl-10 pr-10 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-[#111184] focus:border-[#111184] transition">
                        <button type="button" onclick="togglePasswordVisibility()" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600">
                            <i id="eye-icon" data-lucide="eye" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full btn-lee-accent py-3 text-base justify-center shadow-lg hover:shadow-[#FFD32C]/40">
                        <i data-lucide="log-in" class="w-5 h-5 text-black"></i>
                        <span>Acessar o Lee Market</span>
                    </button>
                </div>
            </form>

            <!-- Atalhos de Demonstração (Facilita Avaliação) -->
            <div class="mt-6 pt-5 border-t border-slate-200">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 text-center mb-3">
                    Acesso Rápido para Testes
                </p>
                <div class="grid grid-cols-3 gap-2">
                    <button type="button" onclick="prefillLogin('111.111.111-11', 'Rafael Dono Lee', 'admin123')" class="p-2 text-center rounded-xl border border-slate-200 hover:border-[#111184] hover:bg-slate-50 transition">
                        <span class="block text-[11px] font-black text-[#111184]">Dono</span>
                        <span class="block text-[9px] text-slate-500">Acesso Total</span>
                    </button>
                    <button type="button" onclick="prefillLogin('222.222.222-22', 'Carlos Gerente Silva', 'gerente123')" class="p-2 text-center rounded-xl border border-slate-200 hover:border-[#111184] hover:bg-slate-50 transition">
                        <span class="block text-[11px] font-black text-blue-700">Gerente</span>
                        <span class="block text-[9px] text-slate-500">Estoque/Relat.</span>
                    </button>
                    <button type="button" onclick="prefillLogin('333.333.333-33', 'Maria Caixa Santos', 'caixa123')" class="p-2 text-center rounded-xl border border-slate-200 hover:border-[#111184] hover:bg-slate-50 transition">
                        <span class="block text-[11px] font-black text-emerald-700">Caixa</span>
                        <span class="block text-[9px] text-slate-500">PDV & Saída</span>
                    </button>
                </div>
            </div>

        </div>

        <p class="text-center text-xs text-slate-400 mt-6">
            Autenticação Segura &bull; Hash Bcrypt &bull; Auditoria de Acessos
        </p>

    </div>

    <script src="<?= base_url('assets/js/app.js') ?>"></script>
    <script>
        if (window.lucide) lucide.createIcons();

        function prefillLogin(cpf, name, pass) {
            document.getElementById('cpf').value = cpf;
            document.getElementById('nome_completo').value = name;
            document.getElementById('senha').value = pass;
            showToast('info', 'Dados Preenchidos', 'Perfil carregado: ' + name);
        }

        function togglePasswordVisibility() {
            const passInput = document.getElementById('senha');
            const eye = document.getElementById('eye-icon');
            if (passInput.type === 'password') {
                passInput.type = 'text';
                eye.setAttribute('data-lucide', 'eye-off');
            } else {
                passInput.type = 'password';
                eye.setAttribute('data-lucide', 'eye');
            }
            if (window.lucide) lucide.createIcons();
        }

        <?php if (!empty($toast)): ?>
            showToast(
                <?= json_encode($toast['type'] ?? 'info') ?>,
                <?= json_encode($toast['title'] ?? 'Aviso') ?>,
                <?= json_encode($toast['message'] ?? '') ?>
            );
        <?php endif; ?>
    </script>
</body>
</html>
