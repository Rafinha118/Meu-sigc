/**
 * ===================================================================
 * Sistema Integrado de Gestão Comercial — SIGC (Lee Market)
 * Script Principal da Interface (assets/js/app.js)
 * ===================================================================
 */

document.addEventListener('DOMContentLoaded', () => {
    initMasks();
    initMobileNav();
    initModalEvents();
});

/* ===================================================================
   SISTEMA DE TOAST PERSONALIZADO LEE MARKET
   =================================================================== */
function showToast(type, title, message, duration = 4500) {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'fixed top-4 right-4 z-50 flex flex-col gap-2 max-w-sm w-full pointer-events-none px-3';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = 'toast-enter pointer-events-auto flex items-start gap-3 p-4 rounded-xl shadow-xl border bg-white text-black transition-all';

    // Configurações por tipo
    let iconSvg = '';
    let borderClass = 'border-slate-200';
    let badgeBg = 'bg-slate-100 text-slate-700';

    if (type === 'success') {
        borderClass = 'border-emerald-300';
        badgeBg = 'bg-emerald-100 text-emerald-800';
        iconSvg = `<svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>`;
    } else if (type === 'error') {
        borderClass = 'border-rose-300';
        badgeBg = 'bg-rose-100 text-rose-800';
        iconSvg = `<svg class="w-5 h-5 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>`;
    } else if (type === 'warning') {
        borderClass = 'border-yellow-400 bg-amber-50/70';
        badgeBg = 'bg-[#FFD32C] text-black font-bold';
        iconSvg = `<svg class="w-5 h-5 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>`;
    } else { // info
        borderClass = 'border-[#111184]/40';
        badgeBg = 'bg-[#111184] text-white';
        iconSvg = `<svg class="w-5 h-5 text-[#111184] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>`;
    }

    toast.classList.add(...borderClass.split(' '));

    toast.innerHTML = `
        <div class="p-1.5 rounded-lg ${badgeBg} shrink-0">
            ${iconSvg}
        </div>
        <div class="flex-1 min-w-0">
            <h4 class="font-bold text-sm text-[#111184] leading-tight">${escapeHtml(title)}</h4>
            <p class="text-xs text-slate-700 mt-0.5 leading-relaxed">${escapeHtml(message)}</p>
        </div>
        <button type="button" class="text-slate-400 hover:text-black transition p-1" onclick="dismissToast(this.parentElement)">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    `;

    container.appendChild(toast);

    if (duration > 0) {
        setTimeout(() => {
            dismissToast(toast);
        }, duration);
    }
}

function dismissToast(toast) {
    if (!toast) return;
    toast.classList.remove('toast-enter');
    toast.classList.add('toast-leave');
    setTimeout(() => {
        toast.remove();
    }, 260);
}

/* ===================================================================
   SISTEMA DE MODAIS PERSONALIZADOS LEE MARKET
   (Substitui alert e confirm nativos)
   =================================================================== */
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.classList.add('overflow-hidden');
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
    }
}

let activeConfirmCallback = null;

/**
 * Abre o Modal de Confirmação personalizado Lee Market
 * Substitui window.confirm()
 */
function confirmAction({
    title = 'Confirmar Ação',
    message = 'Você tem certeza que deseja prosseguir com esta ação?',
    confirmText = 'Confirmar',
    cancelText = 'Cancelar',
    isDanger = false,
    onConfirm = null
}) {
    const modal = document.getElementById('lee-confirm-modal');
    if (!modal) {
        // Fallback se modal não estiver no DOM
        if (confirm(message)) {
            if (typeof onConfirm === 'function') onConfirm();
        }
        return;
    }

    document.getElementById('lee-confirm-title').innerText = title;
    document.getElementById('lee-confirm-message').innerText = message;
    
    const confirmBtn = document.getElementById('lee-confirm-btn');
    confirmBtn.innerText = confirmText;

    if (isDanger) {
        confirmBtn.className = 'w-full sm:w-auto px-5 py-2.5 rounded-lg font-bold text-white bg-rose-600 hover:bg-rose-700 transition shadow';
    } else {
        confirmBtn.className = 'btn-lee-accent';
    }

    const cancelBtn = document.getElementById('lee-confirm-cancel');
    cancelBtn.innerText = cancelText;

    activeConfirmCallback = onConfirm;

    openModal('lee-confirm-modal');
}

function initModalEvents() {
    const confirmBtn = document.getElementById('lee-confirm-btn');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', () => {
            closeModal('lee-confirm-modal');
            if (typeof activeConfirmCallback === 'function') {
                activeConfirmCallback();
                activeConfirmCallback = null;
            }
        });
    }

    // Fechar ao pressionar Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.lee-modal-container:not(.hidden)').forEach(modal => {
                closeModal(modal.id);
            });
        }
    });
}

/* ===================================================================
   MÁSCARAS DE ENTRADA (CPF, TELEFONE, MOEDA)
   =================================================================== */
function initMasks() {
    // Máscara de CPF
    document.querySelectorAll('.mask-cpf').forEach(input => {
        input.addEventListener('input', (e) => {
            let v = e.target.value.replace(/\D/g, '');
            if (v.length > 11) v = v.substring(0, 11);
            if (v.length > 9) {
                v = v.replace(/(\d{3})(\d{3})(\d{3})(\d{1,2})/, '$1.$2.$3-$4');
            } else if (v.length > 6) {
                v = v.replace(/(\d{3})(\d{3})(\d{1,3})/, '$1.$2.$3');
            } else if (v.length > 3) {
                v = v.replace(/(\d{3})(\d{1,3})/, '$1.$2');
            }
            e.target.value = v;
        });
    });

    // Máscara de Telefone
    document.querySelectorAll('.mask-phone').forEach(input => {
        input.addEventListener('input', (e) => {
            let v = e.target.value.replace(/\D/g, '');
            if (v.length > 11) v = v.substring(0, 11);
            if (v.length > 10) {
                v = v.replace(/^(\d{2})(\d{5})(\d{4})$/, '($1) $2-$3');
            } else if (v.length > 6) {
                v = v.replace(/^(\d{2})(\d{4})(\d{0,4})$/, '($1) $2-$3');
            } else if (v.length > 2) {
                v = v.replace(/^(\d{2})(\d{0,5})$/, '($1) $2');
            }
            e.target.value = v;
        });
    });
}

/* ===================================================================
   NAVEGAÇÃO RESPONSIVA MOBILE (DRAWER)
   =================================================================== */
function initMobileNav() {
    const toggleBtn = document.getElementById('mobile-menu-btn');
    const drawer = document.getElementById('sidebar-drawer');
    const backdrop = document.getElementById('sidebar-backdrop');
    const closeBtn = document.getElementById('close-sidebar-btn');

    if (toggleBtn && drawer) {
        toggleBtn.addEventListener('click', () => {
            drawer.classList.remove('-translate-x-full');
            if (backdrop) backdrop.classList.remove('hidden');
        });
    }

    if (closeBtn && drawer) {
        closeBtn.addEventListener('click', () => {
            drawer.classList.add('-translate-x-full');
            if (backdrop) backdrop.classList.add('hidden');
        });
    }

    if (backdrop && drawer) {
        backdrop.addEventListener('click', () => {
            drawer.classList.add('-translate-x-full');
            backdrop.classList.add('hidden');
        });
    }
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.innerText = str;
    return div.innerHTML;
}
