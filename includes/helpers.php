<?php
/**
 * ===================================================================
 * Sistema Integrado de Gestão Comercial — SIGC (Lee Market)
 * Funções Auxiliares Gerais (includes/helpers.php)
 * ===================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Retorna a URL base do projeto dinamicamente
 */
function base_url(string $path = ''): string {
    // Detecta o caminho base do projeto relativo ao DOCUMENT_ROOT
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    // Normalizar removendo pastas internas de módulos se houver
    $subfolders = ['/modules/auth', '/modules/dashboard', '/modules/produtos', '/modules/estoque', 
                   '/modules/vendas', '/modules/relatorios', '/modules/financeiro', 
                   '/modules/fornecedores', '/modules/rh', '/modules/auditoria'];
    
    $base = $scriptDir;
    foreach ($subfolders as $sub) {
        if (str_ends_with($base, $sub)) {
            $base = substr($base, 0, -strlen($sub));
            break;
        }
    }
    
    $base = rtrim($base, '/');
    $cleanPath = ltrim($path, '/');
    return ($base ? $base : '') . ($cleanPath ? '/' . $cleanPath : '/');
}

/**
 * Redireciona para um caminho com URL base
 */
function redirect(string $path): void {
    header("Location: " . base_url($path));
    exit;
}

/**
 * Formata um valor numérico para Moeda Real Brasileiro (R$)
 */
function format_money($val): string {
    $num = floatval($val);
    return 'R$ ' . number_format($num, 2, ',', '.');
}

/**
 * Formata data e hora para o padrão brasileiro
 */
function format_date(?string $datetime, bool $withTime = true): string {
    if (!$datetime) return '-';
    $ts = strtotime($datetime);
    if (!$ts) return '-';
    return date($withTime ? 'd/m/Y H:i' : 'd/m/Y', $ts);
}

/**
 * Sanitiza texto contra XSS
 */
function sanitize(?string $str): string {
    return htmlspecialchars(trim((string)$str), ENT_QUOTES, 'UTF-8');
}

/**
 * Remove pontuação de CPF / CNPJ
 */
function clean_mask(?string $str): string {
    return preg_replace('/\D/', '', (string)$str);
}

/**
 * Formata CPF para 000.000.000-00
 */
function format_cpf(?string $cpf): string {
    $c = clean_mask($cpf);
    if (strlen($c) !== 11) return $cpf ?: '-';
    return substr($c, 0, 3) . '.' . substr($c, 3, 3) . '.' . substr($c, 6, 3) . '-' . substr($c, 9, 2);
}

/**
 * Define uma mensagem Toast para ser exibida na próxima tela
 */
function set_toast(string $type, string $title, string $message): void {
    $_SESSION['toast'] = [
        'type'    => in_array($type, ['success', 'error', 'warning', 'info']) ? $type : 'info',
        'title'   => $title,
        'message' => $message,
    ];
}

/**
 * Recupera e remove o Toast da sessão
 */
function get_toast(): ?array {
    if (isset($_SESSION['toast'])) {
        $toast = $_SESSION['toast'];
        unset($_SESSION['toast']);
        return $toast;
    }
    return null;
}

/**
 * Gera ou recupera token CSRF para proteger formulários
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valida o token CSRF recebido via POST
 */
function validate_csrf(?string $token): bool {
    return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Retorna o endereço IP do cliente
 */
function get_client_ip(): string {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}
