<?php
/**
 * ===================================================================
 * Sistema Integrado de Gestão Comercial — SIGC (Lee Market)
 * Encerramento de Sessão (modules/auth/logout.php)
 * ===================================================================
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

logout_user();
set_toast('info', 'Sessão Encerrada', 'Você saiu com segurança do sistema Lee Market.');
redirect('modules/auth/login.php');
