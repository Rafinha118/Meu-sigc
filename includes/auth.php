<?php
/**
 * ===================================================================
 * Sistema Integrado de Gestão Comercial — SIGC (Lee Market)
 * Módulo de Autenticação e Controle de Acesso (includes/auth.php)
 * ===================================================================
 */

require_once __DIR__ . '/../bd.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/audit.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Matriz de Permissões por Perfil
 * - Dono: Acesso total
 * - Gerente: Estoque, Relatórios de Vendas, Fornecedores, Consulta de Produtos
 * - Operador: Vendas (PDV), Saída de Estoque, Consulta de Produtos e Estoque
 */
const ROLE_PERMISSIONS = [
    'dono' => [
        'dashboard',
        'fluxo_caixa',
        'faturamento',
        'relatorios_vendas',
        'produtos_consultar',
        'produtos_cadastrar',
        'produtos_editar',
        'produtos_alterar_preco',
        'produtos_excluir',
        'estoque_consultar',
        'estoque_gerenciar',
        'estoque_saida',
        'estoque_entrada',
        'vendas_pdv',
        'vendas_historico',
        'fornecedores_consultar',
        'fornecedores_gerenciar',
        'rh_colaboradores',
        'auditoria'
    ],
    'gerente' => [
        'dashboard',
        'relatorios_vendas',
        'produtos_consultar',
        'estoque_consultar',
        'estoque_gerenciar',
        'estoque_saida',
        'estoque_entrada',
        'fornecedores_consultar',
        'vendas_historico'
    ],
    'operador' => [
        'dashboard',
        'vendas_pdv',
        'vendas_historico',
        'estoque_saida',
        'produtos_consultar',
        'estoque_consultar'
    ]
];

/**
 * Verifica se há usuário logado na sessão
 */
function is_logged_in(): bool {
    return !empty($_SESSION['user']['id']);
}

/**
 * Retorna os dados do usuário autenticado
 */
function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

/**
 * Retorna o perfil do usuário atual ('dono', 'gerente', 'operador')
 */
function current_user_role(): ?string {
    return $_SESSION['user']['perfil'] ?? null;
}

/**
 * Verifica se o usuário atual tem permissão para uma funcionalidade
 */
function can_access(string $feature): bool {
    $role = current_user_role();
    if (!$role) return false;
    
    // Dono tem acesso irrestrito
    if ($role === 'dono') return true;

    $allowed = ROLE_PERMISSIONS[$role] ?? [];
    return in_array($feature, $allowed);
}

/**
 * Exige que o usuário esteja autenticado
 */
function require_login(): void {
    if (!is_logged_in()) {
        set_toast('warning', 'Acesso Restrito', 'Faça login com seu CPF, Nome e Senha para continuar.');
        redirect('modules/auth/login.php');
    }
}

/**
 * Exige que o usuário possua uma permissão específica
 */
function require_permission(string $feature): void {
    require_login();
    if (!can_access($feature)) {
        log_audit(
            'Segurança',
            'Tentativa de Acesso Não Autorizado',
            "Tentou acessar funcionalidade restrita: '{$feature}' sem ter permissão."
        );
        set_toast('error', 'Acesso Negado', 'Seu perfil não tem permissão para acessar esta área.');
        redirect('modules/dashboard/index.php');
    }
}

/**
 * Realiza autenticação do usuário com CPF, Nome Completo e Senha (bcrypt)
 */
function authenticate_user(string $cpf, string $nomeCompleto, string $senha): array {
    $cleanCpf = clean_mask($cpf);
    $nomeCompleto = trim($nomeCompleto);

    if (empty($cleanCpf) || empty($nomeCompleto) || empty($senha)) {
        return ['success' => false, 'message' => 'Por favor, preencha todos os campos obrigatórios.'];
    }

    try {
        $pdo = getDBConnection();

        // Buscar usuário por CPF (compatível com ou sem máscara)
        $formattedCpf = format_cpf($cleanCpf);
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE (cpf = ? OR REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), '/', '') = ?) LIMIT 1");
        $stmt->execute([$formattedCpf, $cleanCpf]);
        $user = $stmt->fetch();

        if (!$user) {
            log_audit('Autenticação', 'Falha no Login', "CPF não localizado: {$formattedCpf}");
            return ['success' => false, 'message' => 'Credenciais inválidas. Verifique os dados e tente novamente.'];
        }

        // Validação de status ativo
        if ($user['status'] !== 'ativo') {
            log_audit('Autenticação', 'Bloqueio de Acesso', "Usuário inativo tentou acessar: {$user['nome_completo']}", $user['id'], $user['nome_completo'], $user['perfil']);
            return ['success' => false, 'message' => 'Esta conta de colaborador está desativada. Contate a administração.'];
        }

        // Validação do Nome Completo (case-insensitive e tolerante a espaços)
        $dbName = mb_strtolower(trim($user['nome_completo']), 'UTF-8');
        $inputName = mb_strtolower($nomeCompleto, 'UTF-8');

        if ($dbName !== $inputName) {
            log_audit('Autenticação', 'Falha no Login', "Nome informado '{$nomeCompleto}' não confere com cadastro.", $user['id'], $user['nome_completo'], $user['perfil']);
            return ['success' => false, 'message' => 'O nome completo informado não confere com o cadastro deste CPF.'];
        }

        // Validação da Senha com Bcrypt
        if (!password_verify($senha, $user['senha'])) {
            log_audit('Autenticação', 'Falha no Login', "Senha incorreta informada para o usuário.", $user['id'], $user['nome_completo'], $user['perfil']);
            return ['success' => false, 'message' => 'Senha incorreta. Tente novamente.'];
        }

        // Login realizado com sucesso!
        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id'            => (int)$user['id'],
            'cpf'           => $user['cpf'],
            'nome_completo' => $user['nome_completo'],
            'email'         => $user['email'],
            'telefone'      => $user['telefone'],
            'perfil'        => $user['perfil'],
            'cargo'         => $user['cargo'] ?? ucfirst($user['perfil']),
        ];

        log_audit(
            'Autenticação',
            'Login com Sucesso',
            "Usuário logou no sistema com perfil '{$user['perfil']}'.",
            (int)$user['id'],
            $user['nome_completo'],
            $user['perfil']
        );

        return ['success' => true, 'user' => $_SESSION['user']];

    } catch (Throwable $e) {
        error_log("Erro de autenticação: " . $e->getMessage());
        return ['success' => false, 'message' => 'Ocorreu um erro no servidor ao validar os dados.'];
    }
}

/**
 * Encerra a sessão do usuário
 */
function logout_user(): void {
    if (is_logged_in()) {
        $user = current_user();
        log_audit(
            'Autenticação',
            'Logout',
            "Sessão encerrada pelo usuário.",
            $user['id'],
            $user['nome_completo'],
            $user['perfil']
        );
    }

    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
}
