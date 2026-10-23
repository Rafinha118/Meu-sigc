<?php
/**
 * ===================================================================
 * Sistema Integrado de Gestão Comercial — SIGC (Lee Market)
 * Cabeçalho Geral (includes/header.php)
 * ===================================================================
 */

require_once __DIR__ . '/../bd.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';

$pageTitle = $pageTitle ?? 'Lee Market — SIGC';
$user = current_user();
$toast = get_toast();
?>
<!DOCTYPE html>
<html lang="pt-BR" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($pageTitle) ?> | SIGC — Lee Market</title>
    
    <!-- Fonte Isometra (Google Fonts) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Isometra:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'lee-blue': '#111184',
                        'lee-blue-dark': '#0c0c66',
                        'lee-blue-light': '#ececfb',
                        'lee-yellow': '#FFD32C',
                        'lee-yellow-dark': '#e6bd1f',
                        'lee-black': '#000000',
                    },
                    fontFamily: {
                        sans: ['Isometra', 'system-ui', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <!-- Ícones Lucide -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- Estilos Customizados -->
    <link rel="stylesheet" href="<?= base_url('assets/css/custom.css') ?>">
</head>
<body class="h-full flex flex-col font-sans bg-slate-50 text-slate-900 antialiased selection:bg-[#FFD32C] selection:text-black">
    <!-- Container global de notificações Toast -->
    <div id="toast-container" class="fixed top-4 right-4 z-50 flex flex-col gap-2 max-w-sm w-full pointer-events-none px-3"></div>
