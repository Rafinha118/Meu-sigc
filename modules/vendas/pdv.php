<?php
/**
 * ===================================================================
 * Sistema Integrado de Gestão Comercial — SIGC (Lee Market)
 * Frente de Caixa / Ponto de Venda PDV (modules/vendas/pdv.php)
 * ===================================================================
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../bd.php';

require_permission('vendas_pdv');

$pdo = getDBConnection();
$user = current_user();

// Carregar catálogo de produtos ativos para busca rápida e leitor
$produtos = $pdo->query("SELECT id, codigo_barras, nome, preco_venda, estoque_atual, unidade_medida FROM produtos WHERE status = 'ativo' ORDER BY nome ASC")->fetchAll();

$pageTitle = 'Frente de Caixa (PDV)';
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Layout Especial em Tela Cheia para o PDV -->
<div class="min-h-screen flex flex-col bg-slate-100">
    
    <!-- Topbar do PDV -->
    <header class="bg-[#111184] text-white px-4 py-3 shadow-md">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            
            <div class="flex items-center gap-3">
                <a href="<?= base_url('modules/dashboard/index.php') ?>" class="p-1.5 rounded-lg bg-white/10 hover:bg-white/20 transition" title="Voltar ao Painel">
                    <i data-lucide="arrow-left" class="w-5 h-5 text-[#FFD32C]"></i>
                </a>
                <div class="flex items-center gap-1.5">
                    <span class="text-xl font-black text-white">LEE</span>
                    <span class="text-xl font-black text-[#FFD32C]">MARKET</span>
                    <span class="ml-2 px-2 py-0.5 rounded bg-[#FFD32C] text-black text-[11px] font-black uppercase">PDV Caixa</span>
                </div>
            </div>

            <div class="flex items-center gap-4 text-xs font-semibold">
                <div class="hidden sm:block text-right">
                    <span class="text-slate-300 block text-[10px]">OPERADOR ATUAL</span>
                    <span class="text-white font-bold"><?= htmlspecialchars($user['nome_completo']) ?></span>
                </div>
                <div class="bg-white/10 px-3 py-1.5 rounded-lg border border-white/10">
                    <span id="relogio-pdv" class="font-mono text-xs text-[#FFD32C] font-bold">--:--:--</span>
                </div>
            </div>

        </div>
    </header>

    <!-- Área de Venda Dividida em 2 Colunas -->
    <div class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Coluna Esquerda: Busca de Produtos + Grid Rápido (7 colunas) -->
        <div class="lg:col-span-7 flex flex-col gap-5">
            
            <!-- Barra de Leitura de Código de Barras / Busca -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
                <label for="barcode-input" class="block text-xs font-extrabold text-[#111184] uppercase tracking-wider mb-2">
                    <i data-lucide="scan" class="w-4 h-4 inline-block -mt-0.5 mr-1 text-[#111184]"></i>
                    Escanear Código de Barras ou Buscar Produto
                </label>
                <div class="relative flex gap-2">
                    <div class="relative flex-1">
                        <input type="text" id="barcode-input" autofocus placeholder="Digite o código ou nome do produto e aperte ENTER..."
                               class="w-full pl-10 pr-4 py-3 bg-slate-50 border-2 border-slate-300 rounded-xl text-sm font-bold text-slate-900 focus:bg-white focus:border-[#111184] focus:ring-2 focus:ring-[#111184]/20 transition"
                               autocomplete="off">
                        <i data-lucide="barcode" class="w-5 h-5 text-slate-400 absolute left-3 top-3.5"></i>
                    </div>
                    <button type="button" onclick="adicionarPeloInput()" class="btn-lee-accent px-5">
                        <i data-lucide="plus" class="w-5 h-5 text-black"></i>
                        <span class="hidden sm:inline">Adicionar</span>
                    </button>
                </div>

                <!-- Lista de Sugestões de Busca (Dropdown Dinâmico) -->
                <div id="search-suggestions" class="hidden mt-2 max-h-60 overflow-y-auto bg-white border border-slate-200 rounded-xl shadow-lg divide-y divide-slate-100 z-20"></div>
            </div>

            <!-- Grade de Produtos para Acesso Rápido -->
            <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-sm flex-1 flex flex-col">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-500">Produtos em Destaque</h3>
                    <span class="text-[11px] text-slate-400">Clique para adicionar à venda</span>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 overflow-y-auto max-h-[420px] p-1">
                    <?php foreach ($produtos as $prod): ?>
                        <button type="button" onclick="adicionarAoCarrinho(<?= $prod['id'] ?>)" 
                                class="p-3 text-left bg-slate-50 hover:bg-[#111184]/5 border border-slate-200 hover:border-[#111184] rounded-xl transition flex flex-col justify-between group h-24">
                            <div>
                                <span class="text-xs font-bold text-slate-900 line-clamp-2 leading-tight group-hover:text-[#111184]">
                                    <?= htmlspecialchars($prod['nome']) ?>
                                </span>
                                <span class="text-[10px] text-slate-400 block mt-0.5">
                                    Estoque: <?= $prod['estoque_atual'] ?> <?= $prod['unidade_medida'] ?>
                                </span>
                            </div>
                            <div class="flex justify-between items-center mt-1">
                                <span class="text-xs font-black text-[#111184]"><?= format_money($prod['preco_venda']) ?></span>
                                <span class="w-5 h-5 rounded-full bg-[#FFD32C] text-black flex items-center justify-center font-bold text-xs opacity-0 group-hover:opacity-100 transition">+</span>
                            </div>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

        <!-- Coluna Direita: Carrinho + Resumo de Pagamento (5 colunas) -->
        <div class="lg:col-span-5 flex flex-col gap-5">
            
            <!-- Carrinho de Compras -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col flex-1">
                
                <div class="flex justify-between items-center pb-3 border-b border-slate-200">
                    <div class="flex items-center gap-2">
                        <i data-lucide="shopping-bag" class="w-5 h-5 text-[#111184]"></i>
                        <h2 class="font-extrabold text-[#111184] text-base">Itens da Venda</h2>
                    </div>
                    <button type="button" onclick="limparCarrinho()" class="text-xs font-bold text-rose-600 hover:underline">
                        Limpar Itens
                    </button>
                </div>

                <!-- Tabela de Itens Selecionados -->
                <div class="flex-1 overflow-y-auto max-h-[300px] my-3 divide-y divide-slate-100" id="cart-items-container">
                    <!-- Gerado dinamicamente via JS -->
                    <div id="cart-empty-message" class="py-12 text-center text-slate-400">
                        <i data-lucide="shopping-cart" class="w-10 h-10 mx-auto text-slate-300 mb-2"></i>
                        <p class="text-xs font-bold text-slate-600">Nenhum produto adicionado</p>
                        <p class="text-[11px] text-slate-400">Escaneie um código de barras ou clique nos produtos à esquerda.</p>
                    </div>
                </div>

                <!-- Totais e Finalização -->
                <form id="form-finalizar-venda" action="<?= base_url('modules/vendas/processar.php') ?>" method="POST" class="pt-4 border-t border-slate-200 space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" id="carrinho-json" name="carrinho_json" value="[]">

                    <!-- Resumo Financeiro -->
                    <div class="space-y-1.5 text-xs">
                        <div class="flex justify-between text-slate-600">
                            <span>Subtotal dos Itens:</span>
                            <span id="lbl-subtotal" class="font-bold text-slate-900">R$ 0,00</span>
                        </div>
                        <div class="flex justify-between text-slate-600">
                            <span>Desconto Especial (R$):</span>
                            <input type="number" step="0.01" min="0" id="input-desconto" name="desconto" value="0.00" oninput="atualizarTotais()" class="w-20 px-2 py-0.5 text-right bg-slate-50 border border-slate-300 rounded font-bold text-xs">
                        </div>
                        <div class="flex justify-between items-center text-base font-black pt-2 border-t border-slate-200 text-[#111184]">
                            <span>VALOR TOTAL:</span>
                            <span id="lbl-total" class="text-2xl font-black text-[#111184]">R$ 0,00</span>
                        </div>
                    </div>

                    <!-- Forma de Pagamento -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Forma de Pagamento
                        </label>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <label class="p-2.5 border rounded-xl flex items-center gap-2 cursor-pointer transition hover:bg-slate-50 has-[:checked]:border-[#111184] has-[:checked]:bg-[#111184]/5 has-[:checked]:font-bold">
                                <input type="radio" name="forma_pagamento" value="dinheiro" checked onchange="toggleDinheiro(true)">
                                <span>Dinheiro</span>
                            </label>
                            <label class="p-2.5 border rounded-xl flex items-center gap-2 cursor-pointer transition hover:bg-slate-50 has-[:checked]:border-[#111184] has-[:checked]:bg-[#111184]/5 has-[:checked]:font-bold">
                                <input type="radio" name="forma_pagamento" value="pix" onchange="toggleDinheiro(false)">
                                <span>PIX Instantâneo</span>
                            </label>
                            <label class="p-2.5 border rounded-xl flex items-center gap-2 cursor-pointer transition hover:bg-slate-50 has-[:checked]:border-[#111184] has-[:checked]:bg-[#111184]/5 has-[:checked]:font-bold">
                                <input type="radio" name="forma_pagamento" value="cartao_debito" onchange="toggleDinheiro(false)">
                                <span>Cartão Débito</span>
                            </label>
                            <label class="p-2.5 border rounded-xl flex items-center gap-2 cursor-pointer transition hover:bg-slate-50 has-[:checked]:border-[#111184] has-[:checked]:bg-[#111184]/5 has-[:checked]:font-bold">
                                <input type="radio" name="forma_pagamento" value="cartao_credito" onchange="toggleDinheiro(false)">
                                <span>Cartão Crédito</span>
                            </label>
                        </div>
                    </div>

                    <!-- Campo Dinheiro: Valor Pago e Troco -->
                    <div id="box-dinheiro" class="p-3 bg-amber-50 rounded-xl border border-amber-200 grid grid-cols-2 gap-3 text-xs">
                        <div>
                            <label for="valor_pago" class="block font-bold text-amber-900 mb-1">Valor Recebido (R$)</label>
                            <input type="number" step="0.01" min="0" id="valor_pago" name="valor_pago" placeholder="0.00" oninput="calcularTroco()" class="w-full px-2.5 py-1.5 bg-white border border-amber-300 rounded-lg font-black text-sm">
                        </div>
                        <div>
                            <span class="block font-bold text-amber-900 mb-1">Troco a Devolver</span>
                            <p id="lbl-troco" class="text-lg font-black text-emerald-700 py-1">R$ 0,00</p>
                            <input type="hidden" id="troco_input" name="troco" value="0.00">
                        </div>
                    </div>

                    <!-- Dados do Cliente (Opcional) -->
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div>
                            <input type="text" name="cliente_nome" placeholder="Cliente (Opcional)" value="Consumidor Final" class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-300 rounded-lg text-xs">
                        </div>
                        <div>
                            <input type="text" name="cliente_cpf" placeholder="CPF na Nota (Opcional)" class="mask-cpf w-full px-2.5 py-1.5 bg-slate-50 border border-slate-300 rounded-lg text-xs">
                        </div>
                    </div>

                    <!-- Botão Confirmar Venda -->
                    <button type="button" onclick="confirmarVenda()" class="w-full btn-lee-accent py-3.5 text-base justify-center shadow-lg hover:shadow-[#FFD32C]/40">
                        <i data-lucide="check-circle-2" class="w-5 h-5 text-black"></i>
                        <span>FINALIZAR VENDA (F8)</span>
                    </button>
                </form>

            </div>

        </div>

    </div>

</div>

<!-- Injetar Dados dos Produtos para Busca Local Instantânea -->
<script>
const PRODUTOS_DB = <?= json_encode($produtos, JSON_UNESCAPED_UNICODE) ?>;
let carrinho = [];

// Relógio em tempo real
setInterval(() => {
    const now = new Date();
    document.getElementById('relogio-pdv').innerText = now.toLocaleTimeString('pt-BR');
}, 1000);

// Autocomplete e busca
const barcodeInput = document.getElementById('barcode-input');
const suggestionsBox = document.getElementById('search-suggestions');

barcodeInput.addEventListener('input', (e) => {
    const val = e.target.value.trim().toLowerCase();
    if (!val) {
        suggestionsBox.classList.add('hidden');
        return;
    }

    const matches = PRODUTOS_DB.filter(p => 
        p.codigo_barras.toLowerCase().includes(val) || 
        p.nome.toLowerCase().includes(val)
    ).slice(0, 5);

    if (matches.length > 0) {
        suggestionsBox.innerHTML = matches.map(p => `
            <div class="p-2.5 hover:bg-slate-50 cursor-pointer flex justify-between items-center text-xs" onclick="selecionarSugestao(${p.id})">
                <div>
                    <span class="font-bold text-slate-900">${p.nome}</span>
                    <span class="text-[10px] text-slate-400 block font-mono">Cód: ${p.codigo_barras}</span>
                </div>
                <div class="text-right">
                    <span class="font-black text-[#111184]">R$ ${parseFloat(p.preco_venda).toFixed(2).replace('.', ',')}</span>
                    <span class="text-[10px] text-slate-400 block">Estoque: ${p.estoque_atual}</span>
                </div>
            </div>
        `).join('');
        suggestionsBox.classList.remove('hidden');
    } else {
        suggestionsBox.classList.add('hidden');
    }
});

barcodeInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        adicionarPeloInput();
    }
});

function selecionarSugestao(id) {
    adicionarAoCarrinho(id);
    barcodeInput.value = '';
    suggestionsBox.classList.add('hidden');
    barcodeInput.focus();
}

function adicionarPeloInput() {
    const val = barcodeInput.value.trim().toLowerCase();
    if (!val) return;

    // Busca exata por código de barras primeiro
    let prod = PRODUTOS_DB.find(p => p.codigo_barras.toLowerCase() === val);
    if (!prod) {
        // Busca parcial por nome
        prod = PRODUTOS_DB.find(p => p.nome.toLowerCase().includes(val));
    }

    if (prod) {
        adicionarAoCarrinho(prod.id);
        barcodeInput.value = '';
        suggestionsBox.classList.add('hidden');
    } else {
        showToast('error', 'Produto Não Encontrado', 'Nenhum produto cadastrado com o código ou nome informado.');
    }
    barcodeInput.focus();
}

function adicionarAoCarrinho(prodId) {
    const prod = PRODUTOS_DB.find(p => parseInt(p.id, 10) === parseInt(prodId, 10));
    if (!prod) return;

    const itemExistente = carrinho.find(item => item.id === prod.id);

    if (itemExistente) {
        if (itemExistente.quantidade + 1 > prod.estoque_atual) {
            showToast('warning', 'Estoque Insuficiente', 'Não há estoque disponível para mais unidades deste produto.');
            return;
        }
        itemExistente.quantidade++;
    } else {
        if (prod.estoque_atual <= 0) {
            showToast('error', 'Sem Estoque', 'Este produto está esgotado no estoque.');
            return;
        }
        carrinho.push({
            id: prod.id,
            nome: prod.nome,
            codigo_barras: prod.codigo_barras,
            preco: parseFloat(prod.preco_venda),
            quantidade: 1,
            unidade: prod.unidade_medida,
            estoque_max: prod.estoque_atual
        });
    }

    renderizarCarrinho();
    showToast('info', 'Item Adicionado', prod.nome + ' adicionado à venda.', 1800);
}

function alterarQtd(index, delta) {
    const item = carrinho[index];
    if (!item) return;

    const novaQtd = item.quantidade + delta;
    if (novaQtd <= 0) {
        removerItem(index);
        return;
    }
    if (novaQtd > item.estoque_max) {
        showToast('warning', 'Limite de Estoque', 'Atingiu a quantidade máxima disponível em estoque (' + item.estoque_max + ').');
        return;
    }

    item.quantidade = novaQtd;
    renderizarCarrinho();
}

function removerItem(index) {
    carrinho.splice(index, 1);
    renderizarCarrinho();
}

function limparCarrinho() {
    if (carrinho.length === 0) return;
    carrinho = [];
    renderizarCarrinho();
    showToast('info', 'Carrinho Limpo', 'Todos os itens foram removidos da venda.');
}

function renderizarCarrinho() {
    const container = document.getElementById('cart-items-container');
    const emptyMsg = document.getElementById('cart-empty-message');

    if (carrinho.length === 0) {
        container.innerHTML = `
            <div id="cart-empty-message" class="py-12 text-center text-slate-400">
                <i data-lucide="shopping-cart" class="w-10 h-10 mx-auto text-slate-300 mb-2"></i>
                <p class="text-xs font-bold text-slate-600">Nenhum produto adicionado</p>
                <p class="text-[11px] text-slate-400">Escaneie um código de barras ou clique nos produtos à esquerda.</p>
            </div>
        `;
        if (window.lucide) lucide.createIcons();
        atualizarTotais();
        return;
    }

    container.innerHTML = carrinho.map((item, idx) => `
        <div class="py-2.5 flex items-center justify-between gap-3 text-xs">
            <div class="flex-1 min-w-0">
                <p class="font-bold text-slate-900 truncate">${item.nome}</p>
                <span class="text-[10px] text-slate-400 font-mono">R$ ${item.preco.toFixed(2).replace('.', ',')} un</span>
            </div>
            
            <div class="flex items-center gap-1.5 shrink-0">
                <button type="button" onclick="alterarQtd(${idx}, -1)" class="w-6 h-6 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold flex items-center justify-center">-</button>
                <span class="w-8 text-center font-black text-slate-900">${item.quantidade}</span>
                <button type="button" onclick="alterarQtd(${idx}, 1)" class="w-6 h-6 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold flex items-center justify-center">+</button>
            </div>

            <div class="text-right shrink-0 w-20">
                <span class="font-black text-sm text-[#111184]">R$ ${(item.preco * item.quantidade).toFixed(2).replace('.', ',')}</span>
                <button type="button" onclick="removerItem(${idx})" class="block text-[10px] text-rose-500 hover:underline ml-auto">remover</button>
            </div>
        </div>
    `).join('');

    atualizarTotais();
}

function atualizarTotais() {
    const subtotal = carrinho.reduce((acc, item) => acc + (item.preco * item.quantidade), 0);
    const desconto = parseFloat(document.getElementById('input-desconto').value) || 0;
    const total = Math.max(0, subtotal - desconto);

    document.getElementById('lbl-subtotal').innerText = 'R$ ' + subtotal.toFixed(2).replace('.', ',');
    document.getElementById('lbl-total').innerText = 'R$ ' + total.toFixed(2).replace('.', ',');
    document.getElementById('carrinho-json').value = JSON.stringify(carrinho);

    calcularTroco();
}

function toggleDinheiro(isDinheiro) {
    const box = document.getElementById('box-dinheiro');
    if (isDinheiro) {
        box.classList.remove('hidden');
    } else {
        box.classList.add('hidden');
        document.getElementById('valor_pago').value = '';
        document.getElementById('lbl-troco').innerText = 'R$ 0,00';
    }
}

function calcularTroco() {
    const subtotal = carrinho.reduce((acc, item) => acc + (item.preco * item.quantidade), 0);
    const desconto = parseFloat(document.getElementById('input-desconto').value) || 0;
    const total = Math.max(0, subtotal - desconto);

    const valorPagoInput = document.getElementById('valor_pago');
    const valorPago = parseFloat(valorPagoInput.value) || 0;

    const troco = Math.max(0, valorPago - total);
    document.getElementById('lbl-troco').innerText = 'R$ ' + troco.toFixed(2).replace('.', ',');
    document.getElementById('troco_input').value = troco.toFixed(2);
}

function confirmarVenda() {
    if (carrinho.length === 0) {
        showToast('error', 'Carrinho Vazio', 'Adicione pelo menos um produto ao carrinho antes de finalizar a venda.');
        return;
    }

    const subtotal = carrinho.reduce((acc, item) => acc + (item.preco * item.quantidade), 0);
    const desconto = parseFloat(document.getElementById('input-desconto').value) || 0;
    const total = Math.max(0, subtotal - desconto);

    const forma = document.querySelector('input[name="forma_pagamento"]:checked').value;
    const valorPago = parseFloat(document.getElementById('valor_pago').value) || total;

    if (forma === 'dinheiro' && valorPago < total) {
        showToast('error', 'Valor Insuficiente', 'O valor em dinheiro informado é menor que o valor total da venda.');
        return;
    }

    confirmAction({
        title: 'Confirmar Finalização de Venda',
        message: 'Confirmar venda no valor de R$ ' + total.toFixed(2).replace('.', ',') + ' com pagamento via ' + forma.toUpperCase() + '? Os estoques serão atualizados automaticamente.',
        confirmText: 'Confirmar e Emitir Cupom',
        cancelText: 'Voltar',
        isDanger: false,
        onConfirm: () => {
            document.getElementById('form-finalizar-venda').submit();
        }
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
