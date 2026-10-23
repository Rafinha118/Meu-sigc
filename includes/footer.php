<?php
/**
 * ===================================================================
 * Sistema Integrado de Gestão Comercial — SIGC (Lee Market)
 * Rodapé Geral e Injeção de Scripts/Modais (includes/footer.php)
 * ===================================================================
 */

require_once __DIR__ . '/helpers.php';
$toast = $toast ?? get_toast();
?>
    </main>
</div>

<!-- Modal Personalizado de Confirmação Lee Market (Substituto de window.confirm) -->
<div id="lee-confirm-modal" class="lee-modal-container fixed inset-0 z-50 hidden items-center justify-center p-4 modal-backdrop transition-opacity">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-slate-200 overflow-hidden transform transition-all animate-in fade-in zoom-in-95 duration-200">
        <!-- Barra de Destaque Superior -->
        <div class="h-2 bg-[#111184] w-full"></div>
        
        <div class="p-6">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-xl bg-[#111184]/10 text-[#111184] flex items-center justify-center shrink-0">
                    <i data-lucide="help-circle" class="w-6 h-6 text-[#111184]"></i>
                </div>
                <div class="flex-1">
                    <h3 id="lee-confirm-title" class="text-lg font-bold text-[#111184] leading-snug">Confirmar Ação</h3>
                    <p id="lee-confirm-message" class="text-sm text-slate-600 mt-1 leading-relaxed">Você tem certeza que deseja prosseguir com esta ação?</p>
                </div>
            </div>

            <!-- Botões de Ação do Modal -->
            <div class="mt-6 flex flex-col-reverse sm:flex-row sm:justify-end gap-2.5">
                <button type="button" id="lee-confirm-cancel" onclick="closeModal('lee-confirm-modal')" class="w-full sm:w-auto px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 font-semibold hover:bg-slate-100 transition text-sm text-center">
                    Cancelar
                </button>
                <button type="button" id="lee-confirm-btn" class="btn-lee-accent text-sm">
                    Confirmar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Rodapé Inferior -->
<footer class="bg-white border-t border-slate-200 py-5 text-center text-xs text-slate-500 mt-auto">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row justify-between items-center gap-2">
        <p class="font-medium text-slate-600">
            <strong class="text-[#111184]">Lee Market</strong> — Sistema Integrado de Gestão Comercial (SIGC) &copy; <?= date('Y') ?>
        </p>
        <p class="text-slate-400">
            Padrão de Segurança: Hash Bcrypt &bull; Conexão Segura PDO &bull; Auditoria Ativa
        </p>
    </div>
</footer>

<!-- Script Principal -->
<script src="<?= base_url('assets/js/app.js') ?>"></script>

<script>
    // Inicializar ícones Lucide
    if (window.lucide) {
        lucide.createIcons();
    }

    // Disparar notificação Toast de Sessão caso exista
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
