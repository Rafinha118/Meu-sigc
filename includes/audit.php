<?php
/**
 * ===================================================================
 * Sistema Integrado de Gestão Comercial — SIGC (Lee Market)
 * Módulo Central de Auditoria e Logs (includes/audit.php)
 * ===================================================================
 * Registra qualquer ação ou alteração realizada no sistema para fins de auditoria.
 */

require_once __DIR__ . '/../bd.php';
require_once __DIR__ . '/helpers.php';

/**
 * Registra um evento de auditoria no banco de dados
 *
 * @param string $setor Setor envolvido (ex: Autenticação, Produtos, Estoque, Vendas, Fornecedores, Financeiro, RH)
 * @param string $acao Nome da ação realizada (ex: 'Cadastro de Produto', 'Alteração de Preço', 'Exclusão')
 * @param string|array $detalhes Informações descritivas ou comparativas da alteração
 * @param int|null $userId ID do usuário (opcional, se nulo usa a sessão ativa)
 * @param string|null $userName Nome do usuário (opcional, se nulo usa a sessão ativa)
 * @param string|null $userRole Perfil do usuário (opcional, se nulo usa a sessão ativa)
 * @return bool
 */
function log_audit(
    string $setor,
    string $acao,
    $detalhes = '',
    ?int $userId = null,
    ?string $userName = null,
    ?string $userRole = null
): bool {
    try {
        $pdo = getDBConnection();

        // Se dados do usuário não forem passados, recupera da sessão
        if ($userId === null && isset($_SESSION['user']['id'])) {
            $userId = (int)$_SESSION['user']['id'];
        }
        if ($userName === null && isset($_SESSION['user']['nome_completo'])) {
            $userName = $_SESSION['user']['nome_completo'];
        } elseif ($userName === null) {
            $userName = 'Sistema / Não autenticado';
        }

        if ($userRole === null && isset($_SESSION['user']['perfil'])) {
            $userRole = $_SESSION['user']['perfil'];
        } elseif ($userRole === null) {
            $userRole = 'visitante';
        }

        if (is_array($detalhes)) {
            $detalhes = json_encode($detalhes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        $ip = get_client_ip();

        $stmt = $pdo->prepare("
            INSERT INTO auditoria_logs 
            (usuario_id, usuario_nome, usuario_perfil, setor, acao, detalhes, ip_address, criado_em)
            VALUES (?, ?, ?, ?, ?, ?, ?, " . (getDBDriver() === 'sqlite' ? "datetime('now')" : "NOW()") . ")
        ");

        return $stmt->execute([
            $userId,
            $userName,
            $userRole,
            $setor,
            $acao,
            $detalhes,
            $ip
        ]);
    } catch (Throwable $e) {
        error_log("Falha ao registrar auditoria: " . $e->getMessage());
        return false;
    }
}
