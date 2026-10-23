<?php
/**
 * ===================================================================
 * Sistema Integrado de Gestão Comercial — SIGC (Lee Market)
 * Ponto de Entrada do Sistema (index.php)
 * ===================================================================
 */

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect('modules/dashboard/index.php');
} else {
    redirect('modules/auth/login.php');
}
