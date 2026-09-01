/* ============================================
   LAVEXPRESS - FRONTEND
   JavaScript Puro (Vanilla JS)
   ============================================ */

// Estado Global
const state = {
    autenticado: false,
    configAutenticado: false,
    servicos: [],
    carrinho: [],
    clienteAtual: null,
    pedidoAtual: null,
    pedidosSelecionados: [],
    charts: {},
    csrfToken: '',
    paginaAtual: 1,
    totalPaginas: 1,
    flatpickrInstances: [],
    ordenacao: { campo: 'created_at', direcao: 'DESC' },
    tema: localStorage.getItem('tema') || 'dark',
    pedidoEditandoId: null, // Fix #22: Inicializar no estado global
    expenses: [],
    usuarios: [],
};

console.log('🚀 LavExpress v2.0 - Loaded');

// API Base
const API = 'sistema.php';

// ============================================
// USUÁRIOS (MUDADO PARA O TOPO PARA GARANTIR CARREGAMENTO)
// ============================================

async function carregarUsuarios() {
    if (state.userNivel !== 'admin') return;
    try {
        const result = await api('list_users');
        if (result.ok) {
            state.usuarios = result.data;
            renderUsuarios();
        }
    } catch (error) {
        console.error('Erro ao carregar usuários:', error);
        showToast('Erro ao carregar lista de usuários', 'error');
    }
}

function renderUsuarios() {
    const list = document.getElementById('usuariosList');
    if (!list) return;
    list.innerHTML = (state.usuarios || []).map(u => `
        <div class="usuario-item glass">
            <div class="usuario-info">
                <strong>${escapeHtml(u.nome)}</strong>
                <span class="badge badge-sm ${u.nivel === 'admin' ? 'btn-primary' : 'btn-secondary'}">${u.nivel}</span>
            </div>
            <div class="usuario-btns">
                <button class="btn btn-sm btn-icon" onclick="abrirModalUsuario(${u.id})" title="Editar">✏️</button>
                <button class="btn btn-sm btn-icon btn-danger" onclick="deletarUsuario(${u.id})" title="Excluir">🗑️</button>
            </div>
        </div>
    `).join('');
}

function abrirModalUsuario(id = null) {
    const form = document.getElementById('usuarioForm');
    if (!form) return;
    form.reset();
    document.getElementById('usuarioId').value = id || '';
    document.getElementById('usuarioModalTitle').textContent = id ? 'Editar Usuário' : 'Novo Usuário';
    if (id) {
        const user = state.usuarios.find(u => u.id === id);
        if (user) {
            document.getElementById('usuarioNome').value = user.nome;
            document.getElementById('usuarioNivel').value = user.nivel;
        }
    }
    document.getElementById('usuarioModal').classList.remove('hidden');
}

function fecharModalUsuario() {
    const el = document.getElementById('usuarioModal');
    if (el) el.classList.add('hidden');
}

async function salvarUsuario() {
    const id = document.getElementById('usuarioId').value;
    const data = {
        nome: document.getElementById('usuarioNome').value,
        senha: document.getElementById('usuarioSenha').value,
        nivel: document.getElementById('usuarioNivel').value
    };
    if (id) data.id = id;
    if (!data.nome) return showToast('Nome é obrigatório', 'error');
    if (!id && !data.senha) return showToast('Senha obrigatória', 'error');
    try {
        const result = await api('save_user', data);
        if (result.ok) {
            showToast('Usuário salvo!');
            fecharModalUsuario();
            carregarUsuarios();
        }
    } catch (error) { showToast(error.message, 'error'); }
}

async function deletarUsuario(id) {
    const aceito = await confirmar('Excluir', 'Deseja excluir?');
    if (!aceito) return;
    try {
        const result = await api('delete_user', { id });
        if (result.ok) { carregarUsuarios(); showToast('Excluído'); }
    } catch (error) { showToast(error.message, 'error'); }
}

window.abrirModalUsuario = abrirModalUsuario;
window.fecharModalUsuario = fecharModalUsuario;
window.salvarUsuario = salvarUsuario;
window.deletarUsuario = deletarUsuario;

// DESPESAS EXPORT
window.abrirModalDespesa = abrirModalDespesa;
window.fecharModalDespesa = fecharModalDespesa;
window.salvarDespesa = salvarDespesa;
window.editarDespesa = editarDespesa;
window.excluirDespesa = excluirDespesa;
window.carregarDespesas = carregarDespesas;

// ============================================
// DESPESAS (MUDADO PARA O TOPO)
// ============================================

async function carregarDespesas() {
    const dataInicio = document.getElementById('despesaFiltroInicio')?.value || '';
    const dataFim = document.getElementById('despesaFiltroFim')?.value || '';
    const status = document.getElementById('despesaFiltroStatus')?.value || '';

    try {
        const result = await api('get_expenses', {
            data_inicio: dataInicio,
            data_fim: dataFim,
            status: status
        });

        if (result.ok) {
            state.expenses = result.data;
            renderDespesas();
        }
    } catch (error) {
        console.error('Erro ao carregar despesas:', error);
    }
}

function renderDespesas() {
    const table = document.getElementById('despesasTable');
    if (!table) return;
    let total = 0;

    table.innerHTML = (state.expenses || []).map(exp => {
        total += exp.valor;
        return `
            <tr>
                <td>${escapeHtml(exp.descricao)}</td>
                <td><span class="badge badge-secondary">${exp.categoria || 'Geral'}</span></td>
                <td>${exp.data_vencimento ? exp.data_vencimento.split('-').reverse().join('/') : '-'}</td>
                <td><strong>${formatMoney(exp.valor)}</strong></td>
                <td><span class="badge badge-${exp.status === 'pago' ? 'success' : 'warning'}">${exp.status.toUpperCase()}</span></td>
                <td>
                    <div class="actions-cell">
                        <button class="btn btn-sm btn-secondary" onclick="editarDespesa(${exp.id})" title="Editar">✏️</button>
                        <button class="btn btn-sm btn-danger" onclick="excluirDespesa(${exp.id})" title="Excluir">🗑️</button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');

    if (state.expenses.length === 0) {
        table.innerHTML = '<tr><td colspan="6" style="text-align:center; padding: 20px;">Nenhuma despesa encontrada</td></tr>';
    }

    const totalEl = document.getElementById('totalDespesasPeriodo');
    if (totalEl) totalEl.textContent = formatMoney(total);
}

function abrirModalDespesa() {
    const title = document.getElementById('despesaModalTitle');
    const id = document.getElementById('despesaId');
    const form = document.getElementById('despesaForm');
    const modal = document.getElementById('despesaModal');

    if (title) title.textContent = 'Nova Despesa';
    if (id) id.value = '';
    if (form) form.reset();
    if (modal) modal.classList.remove('hidden');
}

function fecharModalDespesa() {
    const modal = document.getElementById('despesaModal');
    if (modal) modal.classList.add('hidden');
}

function editarDespesa(id) {
    const expense = state.expenses.find(e => e.id === id);
    if (!expense) return;

    const title = document.getElementById('despesaModalTitle');
    const inputId = document.getElementById('despesaId');
    const desc = document.getElementById('despesaDescricao');
    const val = document.getElementById('despesaValor');
    const venc = document.getElementById('despesaVencimento');
    const cat = document.getElementById('despesaCategoria');
    const stat = document.getElementById('despesaStatus');
    const modal = document.getElementById('despesaModal');

    if (title) title.textContent = 'Editar Despesa';
    if (inputId) inputId.value = expense.id;
    if (desc) desc.value = expense.descricao;
    if (val) val.value = (expense.valor / 100).toFixed(2);
    if (venc) venc.value = expense.data_vencimento || '';
    if (cat) cat.value = expense.categoria || 'Fixo';
    if (stat) stat.value = expense.status || 'pendente';

    if (modal) modal.classList.remove('hidden');
}

async function salvarDespesa() {
    const id = document.getElementById('despesaId').value;
    const data = {
        descricao: document.getElementById('despesaDescricao').value,
        valor: parseMoney(document.getElementById('despesaValor').value),
        data_vencimento: document.getElementById('despesaVencimento').value,
        categoria: document.getElementById('despesaCategoria').value,
        status: document.getElementById('despesaStatus').value
    };

    if (id) data.id = id;

    if (!data.descricao || data.valor <= 0) {
        showToast('Preencha a descrição e o valor corretamente', 'error');
        return;
    }

    try {
        const result = await api('save_expense', data);
        if (result.ok) {
            showToast('Despesa salva com sucesso!');
            fecharModalDespesa();
            carregarDespesas();
        }
    } catch (error) {
        showToast('Erro ao salvar despesa: ' + error.message, 'error');
    }
}

async function excluirDespesa(id) {
    const aceito = await confirmar('🗑️ Excluir Despesa', 'Deseja realmente excluir esta despesa?');
    if (!aceito) return;

    try {
        const result = await api('delete_expense', { id });
        if (result.ok) {
            showToast('Despesa excluída');
            carregarDespesas();
        }
    } catch (error) {
        showToast('Erro ao excluir: ' + error.message, 'error');
    }
}

// ============================================
// UTILITÁRIOS
// ============================================

// Função para obter data/hora local no formato MySQL/SQLite (horário de Brasília)
function getDataHoraLocal() {
    const agora = new Date();
    const ano = agora.getFullYear();
    const mes = String(agora.getMonth() + 1).padStart(2, '0');
    const dia = String(agora.getDate()).padStart(2, '0');
    const hora = String(agora.getHours()).padStart(2, '0');
    const minuto = String(agora.getMinutes()).padStart(2, '0');
    const segundo = String(agora.getSeconds()).padStart(2, '0');

    return `${ano}-${mes}-${dia} ${hora}:${minuto}:${segundo}`;
}

// Converte data do formato d/m/Y para Y-m-d
function converterDataParaBanco(dataBR) {
    if (!dataBR) return '';
    // Se já está no formato Y-m-d, retorna direto
    if (dataBR.match(/^\d{4}-\d{2}-\d{2}$/)) return dataBR;
    // Se está no formato d/m/Y, converte
    if (dataBR.match(/^\d{2}\/\d{2}\/\d{4}$/)) {
        const [dia, mes, ano] = dataBR.split('/');
        return `${ano}-${mes}-${dia}`;
    }
    return dataBR;
}

// Feriados nacionais fixos do Brasil (mês-dia)
const FERIADOS_FIXOS = [
    '01-01', // Confraternização Universal
    '04-21', // Tiradentes
    '05-01', // Dia do Trabalho
    '09-07', // Independência
    '10-12', // Nossa Senhora Aparecida
    '11-02', // Finados
    '11-15', // Proclamação da República
    '11-20', // Consciênia Negra
    '12-25'  // Natal
];

// Calcula a Páscoa para um ano (algoritmo de Gauss)
function calcularPascoa(ano) {
    const a = ano % 19;
    const b = Math.floor(ano / 100);
    const c = ano % 100;
    const d = Math.floor(b / 4);
    const e = b % 4;
    const f = Math.floor((b + 8) / 25);
    const g = Math.floor((b - f + 1) / 3);
    const h = (19 * a + b - d - g + 15) % 30;
    const i = Math.floor(c / 4);
    const k = c % 4;
    const l = (32 + 2 * e + 2 * i - h - k) % 7;
    const m = Math.floor((a + 11 * h + 22 * l) / 451);
    const mes = Math.floor((h + l - 7 * m + 114) / 31);
    const dia = ((h + l - 7 * m + 114) % 31) + 1;
    return new Date(ano, mes - 1, dia);
}

// Retorna array de feriados móveis para um ano
function getFeriadosMoveis(ano) {
    const pascoa = calcularPascoa(ano);
    const feriados = [];

    // Carnaval (47 dias antes da Páscoa - segunda e terça)
    const carnavalTerca = new Date(pascoa);
    carnavalTerca.setDate(pascoa.getDate() - 47);
    const carnavalSegunda = new Date(carnavalTerca);
    carnavalSegunda.setDate(carnavalTerca.getDate() - 1);
    feriados.push(carnavalSegunda.toISOString().split('T')[0]);
    feriados.push(carnavalTerca.toISOString().split('T')[0]);

    // Sexta-feira Santa (2 dias antes da Páscoa)
    const sextaSanta = new Date(pascoa);
    sextaSanta.setDate(pascoa.getDate() - 2);
    feriados.push(sextaSanta.toISOString().split('T')[0]);

    // Corpus Christi (60 dias após a Páscoa)
    const corpusChristi = new Date(pascoa);
    corpusChristi.setDate(pascoa.getDate() + 60);
    feriados.push(corpusChristi.toISOString().split('T')[0]);

    return feriados;
}

// Verifica se uma data é feriado
function isFeriado(data) {
    const ano = data.getFullYear();
    const mesdia = (data.getMonth() + 1).toString().padStart(2, '0') + '-' +
        data.getDate().toString().padStart(2, '0');

    // Verificar feriados fixos
    if (FERIADOS_FIXOS.includes(mesdia)) return true;

    // Verificar feriados móveis — Fix #17: usar timezone local
    const feriadosMoveis = getFeriadosMoveis(ano);
    const dataStr = `${data.getFullYear()}-${String(data.getMonth() + 1).padStart(2, '0')}-${String(data.getDate()).padStart(2, '0')}`;
    return feriadosMoveis.includes(dataStr);
}

// Verifica se é dia útil (não é sábado, domingo ou feriado)
function isDiaUtil(data) {
    const diaSemana = data.getDay();
    // 0 = domingo, 6 = sábado
    if (diaSemana === 0 || diaSemana === 6) return false;
    if (isFeriado(data)) return false;
    return true;
}

// Adiciona N dias úteis a uma data
function adicionarDiasUteis(dataInicial, diasUteis) {
    const data = new Date(dataInicial);
    let diasAdicionados = 0;

    while (diasAdicionados < diasUteis) {
        data.setDate(data.getDate() + 1);
        if (isDiaUtil(data)) {
            diasAdicionados++;
        }
    }

    return data;
}

// Formata data para input date (YYYY-MM-DD) — Fix #17: usar timezone local
function formatDateInput(data) {
    const y = data.getFullYear();
    const m = String(data.getMonth() + 1).padStart(2, '0');
    const d = String(data.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

// Calcula projeção de recebíveis para o mês atual
function calcularProjecaoMes(recebiveisAtuais, periodo) {
    const hoje = new Date();
    const diaAtual = hoje.getDate();
    const ultimoDiaMes = new Date(hoje.getFullYear(), hoje.getMonth() + 1, 0).getDate();
    const diasRestantes = ultimoDiaMes - diaAtual;

    // Média diária baseada nos recebíveis atuais dividido pelos dias passados
    const mediaDiaria = diaAtual > 0 ? recebiveisAtuais / diaAtual : 0;

    // Projeção = recebíveis atuais + (média diária * dias restantes)
    const projecao = recebiveisAtuais + (mediaDiaria * diasRestantes);

    return {
        valor: Math.round(projecao),
        info: `Base: ${diaAtual} dias | Resta: ${diasRestantes} dias`
    };
}

function formatMoney(cents) {
    return 'R$ ' + (cents / 100).toFixed(2).replace('.', ',');
}

function parseMoney(str) {
    if (!str) return 0;
    return Math.round(parseFloat(str.toString().replace(',', '.')) * 100);
}

function formatPhone(phone) {
    if (!phone) return '';
    const nums = phone.replace(/\D/g, '');
    if (nums.length === 11) {
        return `(${nums.slice(0, 2)}) ${nums.slice(2, 7)}-${nums.slice(7)}`;
    } else if (nums.length === 10) {
        return `(${nums.slice(0, 2)}) ${nums.slice(2, 6)}-${nums.slice(6)}`;
    }
    return phone;
}

function cleanPhone(phone) {
    return phone.replace(/\D/g, '');
}

function formatDate(dateStr) {
    // Garante que a data seja interpretada como horário local, não UTC
    const iso = dateStr.replace(' ', 'T');
    const date = new Date(iso);
    return date.toLocaleDateString('pt-BR') + ' ' + date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
}

function renderIcon(icon) {
    if (!icon) return '';
    if (icon.startsWith('uploads/') || icon.startsWith('/uploads/')) {
        return `<img src="${icon}" alt="icon" style="width: 20px; height: 20px; object-fit: contain;">`;
    }
    return icon;
}

function renderIconLarge(icon) {
    if (!icon) return '';
    if (icon.startsWith('uploads/') || icon.startsWith('/uploads/')) {
        return `<img src="${icon}" alt="icon" style="width: 36px; height: 36px; object-fit: contain;">`;
    }
    return icon;
}

function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <span>${type === 'success' ? '✓' : type === 'error' ? '✗' : '⚠'}</span>
        <span>${message}</span>
    `;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
}

async function api(action, data = {}) {
    try {
        const formData = new FormData();
        formData.append('action', action);
        // Enviar CSRF token
        if (state.csrfToken) {
            formData.append('csrf_token', state.csrfToken);
        }

        for (const [key, value] of Object.entries(data)) {
            if (value instanceof File) {
                formData.append(key, value);
            } else if (typeof value === 'object') {
                formData.append(key, JSON.stringify(value));
            } else {
                formData.append(key, value);
            }
        }

        const response = await fetch(API, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        const result = await response.json();

        // Atualizar CSRF token da resposta
        if (result.csrf_token) {
            state.csrfToken = result.csrf_token;
        }

        if (!result.ok && result.error) {
            if (result.error === 'Não autenticado') {
                state.autenticado = false;
                showLogin();
            }
            throw new Error(result.error);
        }

        return result;
    } catch (error) {
        console.error('API Error:', error);
        throw error;
    }
}

// Loading overlay
function showLoading(msg = 'Carregando...') {
    let overlay = document.getElementById('loadingOverlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'loadingOverlay';
        overlay.className = 'loading-overlay';
        overlay.innerHTML = `<div class="loading-spinner"></div><div class="loading-text">${msg}</div>`;
        document.body.appendChild(overlay);
    } else {
        overlay.querySelector('.loading-text').textContent = msg;
        overlay.classList.remove('hidden');
    }
}

function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) overlay.classList.add('hidden');
}

// Modal de confirmação reutilizável
function confirmar(titulo, mensagem) {
    return new Promise((resolve) => {
        let modal = document.getElementById('confirmModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'confirmModal';
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content glass small">
                    <div class="modal-header">
                        <h2 id="confirmTitulo"></h2>
                    </div>
                    <div class="modal-body">
                        <p id="confirmMensagem"></p>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" id="confirmCancelar">Cancelar</button>
                        <button class="btn btn-danger" id="confirmOk">Confirmar</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        modal.querySelector('#confirmTitulo').textContent = titulo;
        modal.querySelector('#confirmMensagem').textContent = mensagem;
        modal.classList.remove('hidden');

        const btnOk = modal.querySelector('#confirmOk');
        const btnCancelar = modal.querySelector('#confirmCancelar');

        function cleanup() {
            modal.classList.add('hidden');
            btnOk.removeEventListener('click', onOk);
            btnCancelar.removeEventListener('click', onCancel);
        }
        function onOk() { cleanup(); resolve(true); }
        function onCancel() { cleanup(); resolve(false); }

        btnOk.addEventListener('click', onOk);
        btnCancelar.addEventListener('click', onCancel);
    });
}

// ============================================
// AUTENTICAÇÃO
// ============================================

async function checkAuth() {
    try {
        const result = await api('auth_status');
        if (result.ok && result.data.autenticado) {
            state.autenticado = true;
            state.userNivel = result.data.nivel || 'operador';
            hideLogin();
            init();
            applyRoleVisibility();
        } else {
            showLogin();
            if (result.data && result.data.primeiro_acesso) {
                document.getElementById('defaultPassHint').classList.remove('hidden');
            } else {
                document.getElementById('defaultPassHint').classList.add('hidden');
            }
        }
    } catch (error) {
        showLogin();
    }
}

function showLogin() {
    document.getElementById('loginOverlay').classList.remove('hidden');
    document.getElementById('appContainer').classList.add('hidden');
}

function hideLogin() {
    document.getElementById('loginOverlay').classList.add('hidden');
    document.getElementById('appContainer').classList.remove('hidden');
}

document.getElementById('loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const senha = document.getElementById('loginPassword').value;
    const errorDiv = document.getElementById('loginError');

    try {
        const result = await api('login', { senha });
        if (result.ok) {
            state.autenticado = true;
            state.userNivel = result.data.nivel || 'operador';
            hideLogin();
            init();
            applyRoleVisibility();
            showToast('Login realizado com sucesso!');
        }
    } catch (error) {
        errorDiv.textContent = error.message;
        errorDiv.classList.remove('hidden');
    }
});

async function logout() {
    try {
        await api('logout');
        state.autenticado = false;
        state.configAutenticado = false;
        checkAuth();
        showToast('Logout realizado', 'warning');
    } catch (error) {
        showToast('Erro ao fazer logout', 'error');
    }
}

function applyRoleVisibility() {
    const nivel = state.userNivel || 'operador';
    const isAdmin = (nivel === 'admin');

    // Abas do menu lateral
    const tabsToHide = ['dashboard', 'despesas', 'config'];
    tabsToHide.forEach(tabName => {
        const tabEl = document.querySelector(`.nav-tab[data-tab="${tabName}"]`);
        if (tabEl) {
            if (isAdmin) {
                tabEl.classList.remove('hidden');
            } else {
                tabEl.classList.add('hidden');
            }
        }
    });

    // Se o usuário é operador e está em uma aba proibida, manda pro PDV
    const activeTab = document.querySelector('.nav-tab.active')?.dataset.tab;
    if (!isAdmin && tabsToHide.includes(activeTab)) {
        switchTab('pdv');
    }

    // Botão de excluir pedidos (na lista de pedidos)
    // Isso será aplicado também dinamicamente ao renderizar a lista
    updateGlobalUIPermissions(isAdmin);
}

function updateGlobalUIPermissions(isAdmin) {
    const body = document.body;
    if (isAdmin) {
        body.classList.remove('user-operador');
        body.classList.add('user-admin');
    } else {
        body.classList.remove('user-admin');
        body.classList.add('user-operador');
    }
}

// ============================================
// NAVEGAÇÃO
// ============================================

function initNavigation() {
    document.querySelectorAll('.nav-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            const tabName = tab.dataset.tab;

            // Se for config, verificar senha
            if (tabName === 'config' && !state.configAutenticado) {
                showConfigPasswordModal();
                return;
            }

            switchTab(tabName);
        });
    });
}

function switchTab(tabName) {
    // Atualizar tabs
    document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
    document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');

    // Atualizar seções
    document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
    document.getElementById(`${tabName}Section`).classList.add('active');

    // Carregar dados específicos
    if (tabName === 'pedidos') carregarPedidos();
    if (tabName === 'dashboard') carregarDashboard();
    if (tabName === 'despesas') carregarDespesas();
    if (tabName === 'config') {
        carregarConfiguracoes();
        carregarUsuarios();
    }
}

// ============================================
// CONFIG PASSWORD
// ============================================

function showConfigPasswordModal() {
    document.getElementById('configPasswordModal').classList.remove('hidden');
    document.getElementById('configPasswordInput').value = '';
    document.getElementById('configPassError').classList.add('hidden');
}

function closeConfigPasswordModal() {
    document.getElementById('configPasswordModal').classList.add('hidden');
}

async function verifyConfigPassword() {
    const senha = document.getElementById('configPasswordInput').value;
    const errorDiv = document.getElementById('configPassError');

    try {
        const result = await api('verify_config_password', { senha });
        if (result.ok) {
            state.configAutenticado = true;
            closeConfigPasswordModal();
            switchTab('config');
        }
    } catch (error) {
        errorDiv.textContent = error.message;
        errorDiv.classList.remove('hidden');
    }
}

// ============================================
// INICIALIZAÇÃO
// ============================================

async function init() {
    initNavigation();
    initKeyboardShortcuts();
    initTheme();
    await carregarServicos();
    await carregarDadosEmpresa();
    initPDV();
    initFiltros();
}

async function carregarDadosEmpresa() {
    try {
        const result = await api('get_settings');
        if (result.ok && result.data) {
            document.getElementById('empresaNome').textContent = result.data.nome || 'LavExpress';
        }
    } catch (error) {
        console.error('Erro ao carregar dados da empresa:', error);
    }
}

// ============================================
// PDV - SERVIÇOS
// ============================================

async function carregarServicos() {
    try {
        const result = await api('get_servicos');
        if (result.ok) {
            state.servicos = result.data;
            renderServicos();
        }
    } catch (error) {
        showToast('Erro ao carregar serviços', 'error');
    }
}

function renderServicos() {
    const grid = document.getElementById('servicosGrid');
    grid.innerHTML = state.servicos.map(s => `
        <div class="servico-card" onclick="abrirSubservicos(${s.id})">
            <div class="servico-icon">${renderIconLarge(s.icone)}</div>
            <div class="servico-nome">${s.nome}</div>
        </div>
    `).join('');
}
function filtrarServicosPorNome(termo) {
    termo = termo.toLowerCase();

    const filtrados = state.servicos.filter(servico => {
        if (servico.nome.toLowerCase().includes(termo)) return true;
        return (servico.subservicos || []).some(sub =>
            sub.nome.toLowerCase().includes(termo)
        );
    });

    const grid = document.getElementById('servicosGrid');
    // Fix XSS: usar encodeURIComponent no termo para evitar injeção
    const termoEscaped = termo.replace(/'/g, "\\'").replace(/"/g, '&quot;');
    grid.innerHTML = filtrados.map(s => `
        <div class="servico-card" onclick="abrirSubservicos(${s.id}, '${termoEscaped}')">
            <div class="servico-icon">${renderIconLarge(s.icone)}</div>
            <div class="servico-nome">${escapeHtml(s.nome)}</div>
        </div>
    `).join('');
}

// Escapar HTML para prevenir XSS
function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}


function abrirSubservicos(servicoId, termo = '') {

    const servico = state.servicos.find(s => s.id === servicoId);
    if (!servico) return;

    // Fix #1: Escapar nome do serviço
    document.getElementById('modalServicoNome').innerHTML = `${renderIcon(servico.icone)} ${escapeHtml(servico.nome)}`;

    const grid = document.getElementById('subservicosGrid');
    const subservicosFiltrados = termo
        ? (servico.subservicos || []).filter(sub =>
            sub.nome.toLowerCase().includes(termo)
        )
        : (servico.subservicos || []);

    grid.innerHTML = subservicosFiltrados.map(sub => `

        <div class="subservico-card" onclick="adicionarAoCarrinho(${servicoId}, ${sub.id})">
            <div class="subservico-icon">${renderIcon(sub.icone)}</div>
            <div class="subservico-info">
                <div class="subservico-nome">${escapeHtml(sub.nome)}</div>
                <div class="subservico-preco">${formatMoney(sub.preco)}</div>
            </div>
        </div>
    `).join('');

    document.getElementById('subservicosModal').classList.remove('hidden');
}

function fecharModalSubservicos() {
    document.getElementById('subservicosModal').classList.add('hidden');
}

// ============================================
// PDV - CARRINHO
// ============================================

function initPDV() {
    // Formatação telefone
    document.getElementById('clienteTelefone').addEventListener('input', (e) => {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 11) value = value.slice(0, 11);
        e.target.value = formatPhone(value);
    });
    // Busca de serviços
    const buscaServicos = document.getElementById('buscaServicos');
    if (buscaServicos) {
        buscaServicos.addEventListener('input', () => {
            const termo = buscaServicos.value.trim();
            if (termo === '') {
                renderServicos(); // ← ISSO FALTAVA
            } else {
                filtrarServicosPorNome(termo);
            }
        });

    }

    // Busca cliente por nome
    let searchTimeout;
    document.getElementById('clienteNome').addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => buscarClientes(e.target.value), 300);
    });

    // Busca cliente por telefone
    let searchTimeoutTel;
    document.getElementById('clienteTelefone').addEventListener('input', (e) => {
        clearTimeout(searchTimeoutTel);
        const telefone = cleanPhone(e.target.value);
        if (telefone.length >= 2) {
            searchTimeoutTel = setTimeout(() => buscarClientes(telefone), 300);
        }
    });

    // Calcular totais
    document.getElementById('descontoValor').addEventListener('input', calcularTotais);
    document.getElementById('descontoTipo').addEventListener('change', calcularTotais);
    document.getElementById('adiantamento').addEventListener('input', calcularTotais);

    // Finalizar pedido
    document.getElementById('finalizarPedido').addEventListener('click', finalizarPedido);

    // Inicializar datas
    inicializarDatas();
}

function inicializarDatas() {
    // Destruir instâncias anteriores para evitar memory leak
    state.flatpickrInstances.forEach(fp => {
        if (fp && typeof fp.destroy === 'function') fp.destroy();
    });
    state.flatpickrInstances = [];

    const hoje = new Date();
    const dataEntrega = adicionarDiasUteis(hoje, 2);

    state.flatpickrInstances.push(
        flatpickr("#dataPedido", {
            locale: "pt",
            dateFormat: "d/m/Y",
            defaultDate: hoje,
            altInput: true,
            altFormat: "d/m/Y",
            onDayCreate(_, __, ___, dayElem) {
                if (isFeriado(dayElem.dateObj)) {
                    dayElem.classList.add("feriado");
                    dayElem.title = "Feriado Nacional";
                }
            }
        })
    );

    state.flatpickrInstances.push(
        flatpickr("#dataEntrega", {
            locale: "pt",
            dateFormat: "d/m/Y",
            defaultDate: dataEntrega,
            altInput: true,
            altFormat: "d/m/Y",
            onDayCreate(_, __, ___, dayElem) {
                if (isFeriado(dayElem.dateObj)) {
                    dayElem.classList.add("feriado");
                    dayElem.title = "Feriado Nacional";
                }
            }
        })
    );
}


async function buscarClientes(termo) {
    if (!termo || termo.length < 2) {
        document.getElementById('clienteSuggestions').classList.add('hidden');
        return;
    }

    try {
        const result = await api('buscar_clientes', { termo });
        if (result.ok && result.data.length > 0) {
            const container = document.getElementById('clienteSuggestions');
            // Fix XSS: usar data attributes em vez de inline onclick com strings
            container.innerHTML = result.data.map(c => `
                <div class="suggestion-item" data-id="${c.id}" data-nome="${escapeHtml(c.nome)}" data-telefone="${escapeHtml(c.telefone)}">
                    <strong>${escapeHtml(c.nome)}</strong> - ${formatPhone(c.telefone)}
                </div>
            `).join('');
            // Event delegation
            container.querySelectorAll('.suggestion-item').forEach(el => {
                el.addEventListener('click', () => {
                    selecionarCliente(
                        parseInt(el.dataset.id),
                        el.dataset.nome,
                        el.dataset.telefone
                    );
                });
            });
            container.classList.remove('hidden');
        } else {
            document.getElementById('clienteSuggestions').classList.add('hidden');
        }
    } catch (error) {
        console.error('Erro ao buscar clientes:', error);
    }
}

function selecionarCliente(id, nome, telefone) {
    state.clienteAtual = { id, nome, telefone };
    document.getElementById('clienteNome').value = nome;
    document.getElementById('clienteTelefone').value = formatPhone(telefone);
    document.getElementById('clienteSuggestions').classList.add('hidden');
    document.getElementById('clienteInfo').classList.remove('hidden');
    atualizarBotaoFinalizar();
    carregarHistoricoCliente(id);
}

async function carregarHistoricoCliente(clienteId) {
    try {
        const result = await api('historico_cliente', { cliente_id: clienteId });
        const container = document.getElementById('clienteHistorico');
        const lista = document.getElementById('historicoLista');
        const resumo = document.getElementById('historicoResumo');

        if (result.ok && result.data.pedidos.length > 0) {
            const { total_pedidos, total_gasto, pedidos } = result.data;

            resumo.textContent = `${total_pedidos} pedidos | ${formatMoney(total_gasto)}`;

            lista.innerHTML = pedidos.map(p => `
                <div class="historico-item" onclick="abrirDetalhesPedido(${p.id})">
                    <div class="historico-data">${formatDate(p.created_at)}</div>
                    <div class="historico-info">
                        <span class="status-badge status-${p.status}">${p.status}</span>
                        <span class="historico-total">${formatMoney(p.total)}</span>
                    </div>
                </div>
            `).join('');

            if (total_pedidos > pedidos.length) {
                lista.innerHTML += `<div class="historico-ver-mais" onclick="event.stopPropagation(); switchTab('pedidos'); document.getElementById('filtroBusca').value='${escapeHtml(state.clienteAtual?.nome || '')}'; filtrarPedidos();">Ver todos os ${total_pedidos} pedidos →</div>`;
            }

            container.classList.remove('hidden');
        } else {
            container.classList.add('hidden');
        }
    } catch (error) {
        console.error('Erro ao carregar histórico:', error);
    }
}

function adicionarAoCarrinho(servicoId, subservicoId) {
    const servico = state.servicos.find(s => s.id === servicoId);
    const sub = servico.subservicos.find(ss => ss.id === subservicoId);

    const existente = state.carrinho.find(item => item.subservicoId === subservicoId);

    if (existente) {
        existente.quantidade++;
    } else {
        state.carrinho.push({
            servicoId,
            subservicoId,
            nome: sub.nome,
            icone: sub.icone || servico.icone,
            preco: sub.preco,
            quantidade: 1
        });
    }


    renderCarrinho();
    calcularTotais();
    atualizarBotaoFinalizar();
    showToast(`${sub.nome} adicionado ao carrinho`);
}

function renderCarrinho() {
    const container = document.getElementById('carrinhoItems');
    const empty = document.getElementById('carrinhoEmpty');

    if (state.carrinho.length === 0) {
        container.innerHTML = '';
        empty.classList.remove('hidden');
        return;
    }

    empty.classList.add('hidden');
    container.innerHTML = state.carrinho.map((item, idx) => `
        <div class="carrinho-item">
            <div class="carrinho-item-icon">${renderIcon(item.icone)}</div>
            <div class="carrinho-item-info">
                <div class="carrinho-item-nome">${escapeHtml(item.nome)}</div>
                <div class="carrinho-item-preco">
  R$
  <input
    type="number"
    class="preco-editavel"
    min="0"
    step="1"
    value="${(item.preco / 100).toFixed(2)}"
    oninput="editarPrecoItem(${idx}, this.value)"
  >
</div>

            </div>
            <div class="carrinho-item-qty">
                <button class="qty-btn" onclick="alterarQuantidade(${idx}, -1)">−</button>
                <span>${item.quantidade}</span>
                <button class="qty-btn" onclick="alterarQuantidade(${idx}, 1)">+</button>
            </div>
            <div class="carrinho-item-subtotal">${formatMoney(item.preco * item.quantidade)}</div>
            <span class="carrinho-item-remove" onclick="removerDoCarrinho(${idx})">🗑️</span>
        </div>
    `).join('');
}



function removerDoCarrinho(idx) {
    state.carrinho.splice(idx, 1);
    renderCarrinho();
    calcularTotais();
    atualizarBotaoFinalizar();
}
function alterarQuantidade(idx, delta) {
    state.carrinho[idx].quantidade += delta;

    if (state.carrinho[idx].quantidade <= 0) {
        state.carrinho.splice(idx, 1);
    }

    renderCarrinho();
    calcularTotais();
    atualizarBotaoFinalizar();
}
function editarPrecoItem(idx, valor) {
    let novoPreco = Math.round(parseFloat(valor || 0) * 100);
    if (novoPreco < 0) novoPreco = 0;

    state.carrinho[idx].preco = novoPreco;

    // NÃO re-renderizar o carrinho inteiro — atualizar apenas subtotal e totais
    const container = document.getElementById('carrinhoItems');
    const items = container.querySelectorAll('.carrinho-item');
    if (items[idx]) {
        const subtotalEl = items[idx].querySelector('.carrinho-item-subtotal');
        if (subtotalEl) {
            subtotalEl.textContent = formatMoney(state.carrinho[idx].preco * state.carrinho[idx].quantidade);
        }
    }
    calcularTotais();
}

function calcularTotais() {
    const subtotal = state.carrinho.reduce((sum, item) => sum + (item.preco * item.quantidade), 0);

    const descontoTipo = document.getElementById('descontoTipo').value;
    const descontoValor = parseMoney(document.getElementById('descontoValor').value);

    let desconto = 0;
    if (descontoTipo === 'porcentagem') {
        desconto = Math.round(subtotal * (descontoValor / 10000));
    } else {
        desconto = descontoValor;
    }

    if (desconto > subtotal) desconto = subtotal;

    const total = subtotal - desconto;
    let adiantamento = parseMoney(document.getElementById('adiantamento').value);
    // Fix #11: Limitar adiantamento ao total para evitar saldo negativo
    if (adiantamento > total) adiantamento = total;
    const saldo = total - adiantamento;

    document.getElementById('subtotal').textContent = formatMoney(subtotal);
    document.getElementById('totalPedido').textContent = formatMoney(total);
    document.getElementById('saldo').textContent = formatMoney(saldo);

    const descontoDisplay = document.getElementById('descontoDisplay');
    if (desconto > 0) {
        descontoDisplay.classList.remove('hidden');
        document.getElementById('descontoTotal').textContent = '- ' + formatMoney(desconto);
    } else {
        descontoDisplay.classList.add('hidden');
    }

    const adiantamentoDisplay = document.getElementById('adiantamentoDisplay');
    if (adiantamento > 0) {
        adiantamentoDisplay.classList.remove('hidden');
        document.getElementById('adiantamentoTotal').textContent = '- ' + formatMoney(adiantamento);
    } else {
        adiantamentoDisplay.classList.add('hidden');
    }
}

function atualizarBotaoFinalizar() {
    const btn = document.getElementById('finalizarPedido');
    const nome = document.getElementById('clienteNome').value.trim();
    const telefone = cleanPhone(document.getElementById('clienteTelefone').value);

    btn.disabled = !(state.carrinho.length > 0 && nome && telefone.length >= 10);
}

async function finalizarPedido() {
    const nome = document.getElementById('clienteNome').value.trim();
    const telefone = cleanPhone(document.getElementById('clienteTelefone').value);

    const descontoTipo = document.getElementById('descontoTipo').value;
    const descontoValor = parseMoney(document.getElementById('descontoValor').value);
    const adiantamento = parseMoney(document.getElementById('adiantamento').value);

    const dataPedido = converterDataParaBanco(document.getElementById('dataPedido').value);
    const dataEntrega = converterDataParaBanco(document.getElementById('dataEntrega').value);
    const status = document.getElementById('novoPedidoStatus').value;
    const observacoes = document.getElementById('observacoes').value.trim();
    const subtotal = state.carrinho.reduce((sum, item) => sum + (item.preco * item.quantidade), 0);
    let desconto = 0;
    if (descontoTipo === 'porcentagem') {
        desconto = Math.round(subtotal * (descontoValor / 10000));
    } else {
        desconto = descontoValor;
    }
    if (desconto > subtotal) desconto = subtotal;

    const total = subtotal - desconto;

    try {
        // Verificar se é edição ou novo pedido
        if (state.pedidoEditandoId) {
            // Atualizar pedido existente
            const result = await api('atualizar_pedido', {
                id: state.pedidoEditandoId,
                cliente_nome: nome,
                cliente_telefone: telefone,
                cliente_id: state.clienteAtual?.id || null,
                itens: state.carrinho,
                subtotal,
                desconto,
                desconto_tipo: descontoTipo,
                desconto_valor: descontoValor,
                total,
                adiantamento,
                data_pedido: dataPedido,
                data_entrega: dataEntrega,
                status: status,
                observacoes: observacoes,
            });

            if (result.ok) {
                showToast(`Pedido #${state.pedidoEditandoId} atualizado com sucesso!`);
                limparPDV();
            }
        } else {
            // Criar novo pedido
            const result = await api('criar_pedido', {
                cliente_nome: nome,
                cliente_telefone: telefone,
                cliente_id: state.clienteAtual?.id || null,
                itens: state.carrinho,
                subtotal,
                desconto,
                desconto_tipo: descontoTipo,
                desconto_valor: descontoValor,
                total,
                adiantamento,
                data_pedido: dataPedido,
                data_entrega: dataEntrega,
                status: status,
                observacoes: observacoes
            });

            if (result.ok) {
                const pedidoCriado = {
                    id: result.data.id,
                    created_at: getDataHoraLocal(),
                    data_entrega: dataEntrega,
                    status: status,
                    subtotal,
                    desconto,
                    total,
                    adiantamento,
                    itens: state.carrinho,
                    cliente_nome: nome,
                    cliente_telefone: telefone,
                    observacoes
                };

                state.pedidoAtual = pedidoCriado;

                // AUTOMÁTICO: Nenhum (apenas abrir modal)
                // imprimirPedido();
                // enviarWhatsApp(pedidoCriado);

                // Fix #16: Mensagem correta (WhatsApp e impressão NÃO são automáticos)
                showToast(`Pedido #${result.data.id} criado com sucesso!`);

                // Mostrar Modal Pós-Crição (para opções extras ou re-impressão)
                abrirModalPedidoCriado(pedidoCriado);
            }
        }
    } catch (error) {
        showToast('Erro ao salvar pedido: ' + error.message, 'error');
    }
}

function abrirModalPedidoCriado(pedido) {
    const modal = document.getElementById('pedidoCriadoModal');
    document.getElementById('pedidoCriadoMsg').textContent = `Pedido #${pedido.id} gerado com sucesso.`;

    // Configurar botões
    const btnZap = document.getElementById('btnZapPedido');
    const btnPrint = document.getElementById('btnImprimirPedido');

    // Remover listeners antigos (clone)
    btnZap.replaceWith(btnZap.cloneNode(true));
    btnPrint.replaceWith(btnPrint.cloneNode(true));

    document.getElementById('btnZapPedido').onclick = () => enviarWhatsApp(pedido);
    document.getElementById('btnImprimirPedido').onclick = () => imprimirPedido();

    modal.classList.remove('hidden');
    // Não limpar PDV aqui, pois o modal está sobre ele. Limpar apenas ao fechar ou iniciar novo.
    // Mas os dados do cliente e carrinho devem ser limpos visualmente se formos iniciar "novo".
    // Para manter consistência com o comportamento anterior, limpamos os dados do form mas mantemos o modal.
    limparPDV();
}

function fecharModalPedidoCriado() {
    document.getElementById('pedidoCriadoModal').classList.add('hidden');
    state.pedidoAtual = null;
}

function limparPDV() {
    state.clienteAtual = null;
    state.pedidoEditandoId = null;
    document.getElementById('clienteNome').value = '';
    document.getElementById('clienteTelefone').value = '';
    document.getElementById('clienteInfo').classList.add('hidden');
    document.getElementById('clienteHistorico').classList.add('hidden');
    document.getElementById('descontoValor').value = '';
    document.getElementById('descontoTipo').value = 'valor';
    document.getElementById('adiantamento').value = '';
    document.getElementById('finalizarPedido').innerHTML = '✓ Finalizar Pedido';
    document.getElementById('cancelarEdicao').classList.add('hidden');
    document.getElementById('editBanner').classList.add('hidden');
    document.getElementById('observacoes').value = '';
    document.getElementById('novoPedidoStatus').value = 'pendente';

    // Resetar datas
    inicializarDatas();

    // 🔥 LIMPAR CARRINHO (ESTA LINHA FALTAVA)
    state.carrinho = [];

    // Re-render
    renderCarrinho();
    calcularTotais();
    atualizarBotaoFinalizar();
}


// ============================================
// PEDIDOS
// ============================================

function initFiltros() {
    const hoje = new Date();
    const inicio = new Date(hoje);
    inicio.setDate(inicio.getDate() - 30);

    // Filtros de Pedidos
    document.getElementById('filtroDataInicio').value = inicio.toISOString().split('T')[0];
    document.getElementById('filtroDataFim').value = hoje.toISOString().split('T')[0];

    // Filtros de Despesas
    const primeiroDiaMes = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
    document.getElementById('despesaFiltroInicio').value = primeiroDiaMes.toISOString().split('T')[0];
    document.getElementById('despesaFiltroFim').value = hoje.toISOString().split('T')[0];
}

async function carregarPedidos(pagina = 1) {
    try {
        const dataInicio = document.getElementById('filtroDataInicio').value;
        const dataFim = document.getElementById('filtroDataFim').value;
        const status = document.getElementById('filtroStatus').value;
        const busca = document.getElementById('filtroBusca').value;

        showLoading('Carregando pedidos...');
        const result = await api('listar_pedidos', {
            dataInicio,
            dataFim,
            status,
            busca,
            pagina,
            ordenacao_campo: state.ordenacao.campo,
            ordenacao_direcao: state.ordenacao.direcao
        });
        hideLoading();

        if (result.ok) {
            const dados = result.data;
            const pedidos = dados.pedidos || dados; // compatibilidade
            const paginacao = dados.paginacao || null;

            if (paginacao) {
                state.paginaAtual = paginacao.pagina;
                state.totalPaginas = paginacao.total_paginas;
            }

            renderPedidos(pedidos);
            renderPaginacao(paginacao);
            atualizarIconesOrdenacao();
        }
    } catch (error) {
        hideLoading();
        showToast('Erro ao carregar pedidos', 'error');
    }
}

function ordenarPor(campo) {
    if (state.ordenacao.campo === campo) {
        state.ordenacao.direcao = state.ordenacao.direcao === 'ASC' ? 'DESC' : 'ASC';
    } else {
        state.ordenacao.campo = campo;
        state.ordenacao.direcao = 'ASC';
    }
    carregarPedidos(1);
}

function atualizarIconesOrdenacao() {
    document.querySelectorAll('.sortable .sort-icon').forEach(icon => icon.textContent = '');
    const th = document.querySelector(`th[data-sort="${state.ordenacao.campo}"]`);
    if (th) {
        const icon = th.querySelector('.sort-icon');
        if (icon) icon.textContent = state.ordenacao.direcao === 'ASC' ? '▲' : '▼';
    }
}

function filtrarPedidos() {
    state.paginaAtual = 1;
    carregarPedidos(1);
}

function renderPaginacao(paginacao) {
    let container = document.getElementById('paginacao');
    if (!container) {
        container = document.createElement('div');
        container.id = 'paginacao';
        container.className = 'paginacao';
        const tableContainer = document.querySelector('#pedidosSection .table-container');
        tableContainer.parentNode.insertBefore(container, tableContainer.nextSibling);
    }

    if (!paginacao || paginacao.total_paginas <= 1) {
        container.innerHTML = '';
        return;
    }

    const { pagina, total_paginas, total_registros } = paginacao;
    let html = `<span class="paginacao-info">${total_registros} pedidos</span>`;
    html += `<div class="paginacao-btns">`;
    html += `<button class="btn btn-sm btn-secondary" onclick="carregarPedidos(1)" ${pagina <= 1 ? 'disabled' : ''}>«</button>`;
    html += `<button class="btn btn-sm btn-secondary" onclick="carregarPedidos(${pagina - 1})" ${pagina <= 1 ? 'disabled' : ''}>‹</button>`;

    // Mostrar até 5 páginas ao redor da atual
    const inicio = Math.max(1, pagina - 2);
    const fim = Math.min(total_paginas, pagina + 2);
    for (let i = inicio; i <= fim; i++) {
        html += `<button class="btn btn-sm ${i === pagina ? 'btn-primary' : 'btn-secondary'}" onclick="carregarPedidos(${i})">${i}</button>`;
    }

    html += `<button class="btn btn-sm btn-secondary" onclick="carregarPedidos(${pagina + 1})" ${pagina >= total_paginas ? 'disabled' : ''}>›</button>`;
    html += `<button class="btn btn-sm btn-secondary" onclick="carregarPedidos(${total_paginas})" ${pagina >= total_paginas ? 'disabled' : ''}>»</button>`;
    html += `</div>`;

    container.innerHTML = html;
}

function renderPedidos(pedidos) {
    const tbody = document.getElementById('pedidosTable');

    if (!pedidos || pedidos.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" style="text-align: center; padding: 40px;">Nenhum pedido encontrado</td></tr>';
        return;
    }

    // Fix #1: Escapar dados do usuário com escapeHtml()
    tbody.innerHTML = pedidos.map(p => `
        <tr>
            <td data-label="Selecionar"><input type="checkbox" class="pedido-check" value="${p.id}" onchange="atualizarSelecao()"></td>
            <td data-label="ID">#${p.id}</td>
            <td data-label="Cliente">${escapeHtml(p.cliente_nome)}</td>
            <td data-label="Itens">
                <div class="itens-icons">
                    ${(p.itens || []).slice(0, 6).map(i => `<span class="item-icon">${renderIcon(i.icone)}</span>`).join('')}
                    ${p.itens && p.itens.length > 6 ? `<span>+${p.itens.length - 6}</span>` : ''}
                </div>
            </td>
            <td data-label="Total">${formatMoney(p.total)}</td>
            <td data-label="Saldo">${formatMoney(p.total - (p.adiantamento || 0))}</td>
            <td data-label="Status"><span class="status-badge status-${escapeHtml(p.status)}">${escapeHtml(p.status)}</span></td>
            <td data-label="Data">${formatDate(p.created_at)}</td>
            <td data-label="Ações">
                <div class="table-actions">
                    <button class="btn btn-sm btn-info" onclick="abrirDetalhesPedido(${p.id})" title="Ver Detalhes">👁️</button>
                    <button class="btn btn-sm btn-warning" onclick="editarPedido(${p.id})" title="Editar">✏️</button>
                    <button class="btn btn-sm btn-secondary" onclick="imprimirPedidoRapido(${p.id})" title="Imprimir">🖨️</button>
                    <button class="btn btn-sm btn-danger admin-only" onclick="deletarPedido(${p.id})" title="Excluir">🗑️</button>
                </div>
            </td>
        </tr>
    `).join('');
}

function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll').checked;
    document.querySelectorAll('.pedido-check').forEach(cb => cb.checked = selectAll);
    atualizarSelecao();
}

function atualizarSelecao() {
    const checks = document.querySelectorAll('.pedido-check:checked');
    state.pedidosSelecionados = Array.from(checks).map(cb => parseInt(cb.value));

    const bulk = document.getElementById('bulkActions');
    if (state.pedidosSelecionados.length > 0) {
        bulk.classList.remove('hidden');
        document.getElementById('selectedCount').textContent = `${state.pedidosSelecionados.length} selecionados`;
    } else {
        bulk.classList.add('hidden');
    }
}

async function aplicarStatusEmMassa() {
    const novoStatus = document.getElementById('bulkStatus').value;
    if (!novoStatus) {
        showToast('Selecione um status', 'warning');
        return;
    }

    try {
        const result = await api('alterar_status_massa', {
            pedidos: state.pedidosSelecionados,
            status: novoStatus
        });

        if (result.ok) {
            showToast(`${state.pedidosSelecionados.length} pedidos atualizados`);
            carregarPedidos(state.paginaAtual);
            document.getElementById('bulkStatus').value = '';
            document.getElementById('selectAll').checked = false;
            atualizarSelecao();
        }
    } catch (error) {
        showToast('Erro ao atualizar pedidos', 'error');
    }
}

async function abrirDetalhesPedido(id) {
    try {
        const result = await api('get_pedido', { id });

        if (result.ok) {
            state.pedidoAtual = result.data;
            renderDetalhesPedido(result.data);
            document.getElementById('pedidoModal').classList.remove('hidden');
        }
    } catch (error) {
        showToast('Erro ao carregar pedido', 'error');
    }
}

function renderDetalhesPedido(pedido) {
    document.getElementById('pedidoNumero').textContent = pedido.id;
    document.getElementById('pedidoCliente').textContent = pedido.cliente_nome;
    document.getElementById('pedidoTelefone').textContent = formatPhone(pedido.cliente_telefone);
    document.getElementById('pedidoData').textContent = formatDate(pedido.created_at);
    document.getElementById('pedidoEntrega').textContent = pedido.data_entrega ? 'Entrega: ' + formatDate(pedido.data_entrega) : '';

    const selectStatus = document.getElementById('pedidoStatus');
    selectStatus.innerHTML = ['pendente', 'processando', 'pronto', 'entregue', 'pago']
        .map(s => `<option value="${s}" ${s === pedido.status ? 'selected' : ''}>${s.charAt(0).toUpperCase() + s.slice(1)}</option>`)
        .join('');

    const tbody = document.getElementById('pedidoItens');
    tbody.innerHTML = (pedido.itens || []).map(item => `
        <tr>
            <td><span class="item-icon">${renderIcon(item.icone)}</span> ${escapeHtml(item.nome)}</td>
            <td>${item.quantidade}</td>
            <td>${formatMoney(item.preco)}</td>
            <td>${formatMoney(item.preco * item.quantidade)}</td>
        </tr>
    `).join('');

    document.getElementById('pedidoSubtotal').textContent = formatMoney(pedido.subtotal);

    const descontoRow = document.getElementById('pedidoDescontoRow');
    if (pedido.desconto > 0) {
        descontoRow.style.display = 'flex';
        document.getElementById('pedidoDesconto').textContent = '- ' + formatMoney(pedido.desconto);
    } else {
        descontoRow.style.display = 'none';
    }

    document.getElementById('pedidoTotal').textContent = formatMoney(pedido.total);
    document.getElementById('pedidoAdiantamento').textContent = '- ' + formatMoney(pedido.adiantamento || 0);
    // NOVO: Exibir observações se existirem
    const obsContainer = document.getElementById('pedidoObservacoesContainer');
    const obsText = document.getElementById('pedidoObservacoes');
    if (pedido.observacoes && pedido.observacoes.trim()) {
        obsText.value = pedido.observacoes;

        obsContainer.style.display = 'block';
    } else {
        obsContainer.style.display = 'none';
    }
    document.getElementById('pedidoSaldo').textContent = formatMoney(pedido.total - (pedido.adiantamento || 0));
}

function fecharModalPedido() {
    document.getElementById('pedidoModal').classList.add('hidden');
    state.pedidoAtual = null;
}

async function atualizarStatusPedido() {
    const novoStatus = document.getElementById('pedidoStatus').value;

    try {
        await api('alterar_status', {
            id: state.pedidoAtual.id,
            status: novoStatus
        });

        state.pedidoAtual.status = novoStatus;
        showToast('Status atualizado');
        carregarPedidos(state.paginaAtual);
    } catch (error) {
        showToast('Erro ao atualizar status', 'error');
    }
}

async function excluirPedido() {
    const aceito = await confirmar('🗑️ Excluir Pedido', `Deseja excluir o pedido #${state.pedidoAtual.id}? Esta ação não pode ser desfeita.`);
    if (!aceito) return;

    try {
        showLoading('Excluindo pedido...');
        await api('excluir_pedido', { id: state.pedidoAtual.id });
        hideLoading();
        showToast('Pedido excluído');
        fecharModalPedido();
        carregarPedidos(state.paginaAtual);
    } catch (error) {
        hideLoading();
        showToast('Erro ao excluir pedido', 'error');
    }
}

// Repetir pedido — carrega itens do pedido atual no carrinho como novo pedido
function repetirPedido() {
    if (!state.pedidoAtual) return;
    const pedido = state.pedidoAtual;

    // Limpar carrinho atual
    state.carrinho = [];
    state.pedidoEditandoId = null;

    // Preencher cliente
    document.getElementById('clienteNome').value = pedido.cliente_nome || '';
    document.getElementById('clienteTelefone').value = formatPhone(pedido.cliente_telefone || '');
    state.clienteAtual = {
        id: pedido.client_id,
        nome: pedido.cliente_nome,
        telefone: pedido.cliente_telefone
    };
    document.getElementById('clienteInfo').classList.remove('hidden');

    // Carregar itens no carrinho
    if (pedido.itens && pedido.itens.length > 0) {
        pedido.itens.forEach(item => {
            state.carrinho.push({
                servicoId: item.service_id || null,
                subservicoId: item.subservice_id || null,
                nome: item.nome,
                icone: item.icone,
                preco: item.preco,
                quantidade: item.quantidade
            });
        });
    }

    // Fechar modal e ir para PDV
    fecharModalPedido();
    switchTab('pdv');
    inicializarDatas();
    renderCarrinho();
    calcularTotais();
    atualizarBotaoFinalizar();

    showToast('Itens carregados no carrinho — crie um novo pedido');
}

// Editar pedido - carrega no PDV para edição
async function editarPedido(id) {
    try {
        const result = await api('get_pedido', { id });

        if (result.ok) {
            const pedido = result.data;

            // Limpar carrinho atual
            state.carrinho = [];

            // Preencher dados do cliente
            document.getElementById('clienteNome').value = pedido.cliente_nome;
            document.getElementById('clienteTelefone').value = formatPhone(pedido.cliente_telefone);
            state.clienteAtual = {
                id: pedido.client_id,
                nome: pedido.cliente_nome,
                telefone: pedido.cliente_telefone
            };
            document.getElementById('clienteInfo').classList.remove('hidden');

            // Carregar itens no carrinho
            if (pedido.itens && pedido.itens.length > 0) {
                pedido.itens.forEach(item => {
                    state.carrinho.push({
                        servicoId: item.service_id || null,
                        subservicoId: item.subservice_id || null,
                        nome: item.nome,
                        icone: item.icone,
                        preco: item.preco,
                        quantidade: item.quantidade
                    });
                });
            }

            // Preencher desconto
            document.getElementById('descontoTipo').value = pedido.desconto_tipo || 'valor';
            document.getElementById('descontoValor').value = pedido.desconto_valor ? (pedido.desconto_valor / 100).toFixed(2) : '';

            // Preencher adiantamento
            document.getElementById('adiantamento').value = pedido.adiantamento ? (pedido.adiantamento / 100).toFixed(2) : '';

            // Fix #7: Restaurar datas na edição
            if (pedido.created_at) {
                const fpDataPedido = state.flatpickrInstances.find(fp => fp.element && fp.element.id === 'dataPedido');
                if (fpDataPedido) {
                    fpDataPedido.setDate(pedido.created_at.split(' ')[0], true);
                } else {
                    const dpEl = document.getElementById('dataPedido');
                    if (dpEl) dpEl.value = pedido.created_at.split(' ')[0];
                }
            }
            if (pedido.data_entrega) {
                const fpDataEntrega = state.flatpickrInstances.find(fp => fp.element && fp.element.id === 'dataEntrega');
                if (fpDataEntrega) {
                    fpDataEntrega.setDate(pedido.data_entrega, true);
                } else {
                    const deEl = document.getElementById('dataEntrega');
                    if (deEl) deEl.value = pedido.data_entrega;
                }
            }

            // Preencher observações
            const obsEl = document.getElementById('observacoes');
            if (obsEl) obsEl.value = pedido.observacoes || '';

            // Preencher status
            const statusEl = document.getElementById('novoPedidoStatus');
            if (statusEl) statusEl.value = pedido.status || 'pendente';

            // Guardar ID do pedido sendo editado
            state.pedidoEditandoId = id;

            // Mudar texto do botão
            document.getElementById('finalizarPedido').innerHTML = '✏️ Atualizar Pedido #' + id;

            // Mostrar botão de cancelar edição
            document.getElementById('cancelarEdicao').classList.remove('hidden');

            // Mostrar banner de edição
            document.getElementById('editBannerNumero').textContent = '#' + id;
            document.getElementById('editBanner').classList.remove('hidden');

            // Ir para aba PDV
            switchTab('pdv');

            // Renderizar carrinho
            renderCarrinho();
            calcularTotais();
            atualizarBotaoFinalizar();

            showToast('Pedido carregado para edição');
        }
    } catch (error) {
        showToast('Erro ao carregar pedido: ' + error.message, 'error');
    }
}

// Fix #10: Função cancelarEdicaoPedido que era chamada no HTML mas não existia
function cancelarEdicaoPedido() {
    state.pedidoEditandoId = null;
    state.carrinho = [];
    state.clienteAtual = null;

    document.getElementById('clienteNome').value = '';
    document.getElementById('clienteTelefone').value = '';
    document.getElementById('clienteInfo').classList.add('hidden');
    document.getElementById('descontoTipo').value = 'valor';
    document.getElementById('descontoValor').value = '';
    document.getElementById('adiantamento').value = '';
    const obsEl = document.getElementById('observacoes');
    if (obsEl) obsEl.value = '';
    document.getElementById('novoPedidoStatus').value = 'pendente';

    document.getElementById('finalizarPedido').innerHTML = '✅ Finalizar Pedido';
    document.getElementById('cancelarEdicao').classList.add('hidden');
    document.getElementById('editBanner').classList.add('hidden');

    renderCarrinho();
    calcularTotais();
    atualizarBotaoFinalizar();

    showToast('Edição cancelada');
}

// Imprimir pedido rapidamente sem abrir modal
async function imprimirPedidoRapido(id) {
    try {
        const result = await api('get_pedido', { id });

        if (result.ok) {
            state.pedidoAtual = result.data;
            await imprimirPedido();
            showToast('Impressão enviada');
        }
    } catch (error) {
        showToast('Erro ao imprimir: ' + error.message, 'error');
    }
}

async function deletarPedido(id) {
    const aceito = await confirmar('🗑️ Excluir Pedido', 'Deseja realmente excluir este pedido permanentemente?');
    if (!aceito) return;

    try {
        await api('excluir_pedido', { id });
        showToast('Pedido excluído com sucesso');
        carregarPedidos(state.paginaAtual);
    } catch (error) {
        showToast('Erro ao excluir: ' + error.message, 'error');
    }
}

// Salvar status do pedido rapidamente
async function salvarStatusPedido(id, statusAtual) {
    const statusOptions = ['pendente', 'processando', 'pronto', 'entregue', 'pago'];
    const currentIndex = statusOptions.indexOf(statusAtual);
    const nextStatus = statusOptions[(currentIndex + 1) % statusOptions.length];

    const novoStatus = prompt(
        `Status atual: ${statusAtual.toUpperCase()}\n\nEscolha o novo status:\n` +
        statusOptions.map((s, i) => `${i + 1}. ${s}`).join('\n') +
        '\n\nDigite o número ou nome do status:',
        nextStatus
    );

    if (!novoStatus) return;

    let statusFinal = novoStatus.toLowerCase().trim();

    // Se digitou número, converter para status
    if (/^[1-5]$/.test(statusFinal)) {
        statusFinal = statusOptions[parseInt(statusFinal) - 1];
    }

    if (!statusOptions.includes(statusFinal)) {
        showToast('Status inválido', 'error');
        return;
    }

    try {
        await api('alterar_status', { id, status: statusFinal });
        showToast(`Status alterado para: ${statusFinal.toUpperCase()}`);
        carregarPedidos(state.paginaAtual);
    } catch (error) {
        showToast('Erro ao salvar status: ' + error.message, 'error');
    }
}

// ============================================
// IMPRESSÃO
// ============================================

async function imprimirPedido() {
    const pedido = state.pedidoAtual;
    if (!pedido) return;

    let settings = {};
    try {
        const result = await api('get_settings');
        if (result.ok) settings = result.data;
    } catch (e) { }

    // QR Code para WhatsApp do CLIENTE com mensagem "PEDIDO PRONTO"
    const clientePhone = cleanPhone(pedido.cliente_telefone || '');
    const mensagemPronto = '✅ PEDIDO PRONTO';

    const whatsappClienteUrl = clientePhone
        ? `https://wa.me/55${clientePhone}?text=${encodeURIComponent(mensagemPronto)}`
        : '';

    const qrCodeClienteUrl = clientePhone
        ? `https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=${encodeURIComponent(whatsappClienteUrl)}`
        : '';

    // QR Code da empresa (opcional)
    const empresaPhone = cleanPhone(settings.whatsapp || '');


    const printContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Comprovante #${pedido.id}</title>
            <style>
                @page {
                    size: 80mm auto;
                    margin: 0;
                }
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                body {
                    font-family: 'Courier New', monospace;
                    font-size: 12px;
                    width: 80mm;
                    padding: 5mm;
                    font-weight: bold;
                }
                .header {
                    text-align: center;
                    border-bottom: 2px dashed #000;
                    padding-bottom: 10px;
                    margin-bottom: 10px;
                }
                .empresa-nome {
                    font-size: 18px;
                    font-weight: bold;
                    margin-bottom: 5px;
                }
                .info {
                    margin-bottom: 10px;
                }
                .info-row {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 2px;
                }
                .itens {
                    border-top: 2px dashed #000;
                    border-bottom: 2px dashed #000;
                    padding: 10px 0;
                    margin-bottom: 10px;
                }
                .item {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 5px;
                }
                .item-nome {
                    flex: 1;
                }
                .totais {
                    margin-bottom: 10px;
                }
                .total-row {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 3px;
                }
                .total-row.destaque {
                    font-size: 14px;
                    font-weight: bold;
                    border-top: 1px solid #000;
                    padding-top: 5px;
                    margin-top: 5px;
                }
                .total-row.saldo {
                    font-size: 15px;
                    font-weight: bold;
                    border-top: 1px solid #000;
                    padding-top: 5px;
                    margin-top: 5px;
                }
                .status {
                    text-align: center;
                    padding: 8px;
                    border: 2px solid #000;
                    margin-bottom: 15px;
                    text-transform: uppercase;
                    font-size: 14px;
                    font-weight: bold;
                }
                .qr-section {
                    text-align: center;
                    margin-top: 15px;
                    padding: 10px;
                    border: 2px dashed #000;
                    background: #f5f5f5;
                }
                .qr-section p {
                    margin-bottom: 8px;
                    font-size: 11px;
                    font-weight: bold;
                }
                .qr-section .qr-title {
                    font-size: 13px;
                    font-weight: bold;
                    margin-bottom: 5px;
                }
                .qr-section img {
                    width: 100px;
                    height: 100px;
                }
                .qr-section .qr-hint {
                    font-size: 9px;
                    margin-top: 5px;
                    color: #333;
                }
                .footer {
                    text-align: center;
                    margin-top: 15px;
                    font-size: 11px;
                    border-top: 1px dashed #000;
                    padding-top: 10px;
                }
                .footer .thanks {
                    font-size: 13px;
                    font-weight: bold;
                    margin-bottom: 5px;
                }
            </style>
            <script>
                window.onload = function() {
                    // Pequeno delay para garantir renderização
                    setTimeout(function() {
                        window.print();
                        window.close();
                    }, 500);
                };
            </script>
        </head>
        <body>
            <div class="header">
                <div class="empresa-nome">${settings.nome || 'LavExpress'}</div>
                ${settings.cnpj ? `<div>CNPJ: ${settings.cnpj}</div>` : ''}
                ${settings.whatsapp ? `<div>WhatsApp: ${formatPhone(settings.whatsapp)}</div>` : ''}
            </div>
            
            <div class="info">
                <div class="info-row">
                    <span>Pedido:</span>
                    <span>#${pedido.id}</span>
                </div>
                <div class="info-row">
                    <span>Data:</span>
                    <span>${formatDate(pedido.created_at)}</span>

                </div>
                <div class="info-row">
                    <span>Entrega:</span>
                    <span>${pedido.data_entrega ? pedido.data_entrega.split('-').reverse().join('/') : 'A combinar'}</span>
                </div>
                <div class="info-row">
                    <span>Cliente:</span>
                    <span>${pedido.cliente_nome}</span>
                </div>
                <div class="info-row">
                    <span>Telefone:</span>
                    <span>${formatPhone(pedido.cliente_telefone)}</span>
                </div>
            </div>
            
            <div class="itens">
                ${(pedido.itens || []).map(item => `
                    <div class="item">
                        <span class="item-nome">${item.quantidade}x ${item.nome}</span>
                        <span>${formatMoney(item.preco * item.quantidade)}</span>
                    </div>
                `).join('')}
            </div>
            
            <div class="totais">
  <div class="total-row">
    <span>Subtotal:</span>
    <span>${formatMoney(pedido.subtotal)}</span>
  </div>

  ${pedido.desconto > 0 ? `
    <div class="total-row">
      <span>Desconto:</span>
      <span>- ${formatMoney(pedido.desconto)}</span>
    </div>
  ` : ''}

  <div class="total-row destaque">
    <span>Total do Pedido:</span>
    <span>${formatMoney(pedido.total)}</span>
  </div>

  ${pedido.adiantamento > 0 ? `
    <div class="total-row">
      <span>Valor Pago:</span>
      <span>- ${formatMoney(pedido.adiantamento)}</span>
    </div>
  ` : ''}

  <div class="total-row saldo">
    <span>Saldo a Pagar:</span>
    <span>${formatMoney(pedido.total - (pedido.adiantamento || 0))}</span>
  </div>
</div>

            
            <div class="status">
                Status: ${pedido.status}
            </div>
            
            ${pedido.observacoes && pedido.observacoes.trim() ? `
                <div class="observacoes">
                    <div class="observacoes-titulo">📝 Observações:</div>
                    <div class="observacoes-texto">${pedido.observacoes}</div>
                </div>
            ` : ''}
            
            ${qrCodeClienteUrl ? `
                <div class="qr-section">
                    
                    <p>Escaneie para avisar o cliente:</p>
                    <img src="${qrCodeClienteUrl}" alt="QR WhatsApp Cliente">
                    
                </div>
            ` : ''}
            
            <div class="footer">
                <div class="thanks">⚠️ GUARDE ESSE RECIBO</div>
                
            </div>
        </body>
        </html>
    `;

    const printFrame = document.getElementById('printFrame');
    const doc = printFrame.contentWindow.document;

    doc.open();
    doc.write(printContent);
    // print() é chamado pelo script injetado no HTML (onload)
    const images = printFrame.contentWindow.document.querySelectorAll('img');
    let loadedImages = 0;
    const totalImages = images.length;

    const triggerPrint = () => {
        printFrame.contentWindow.focus();
        printFrame.contentWindow.print();
    };

    if (totalImages === 0) {
        setTimeout(triggerPrint, 500);
    } else {
        images.forEach(img => {
            if (img.complete) {
                loadedImages++;
                if (loadedImages === totalImages) triggerPrint();
            } else {
                img.onload = () => {
                    loadedImages++;
                    if (loadedImages === totalImages) triggerPrint();
                };
                img.onerror = () => {
                    loadedImages++;
                    if (loadedImages === totalImages) triggerPrint();
                };
            }
        });

        // Safety timeout in case images take too long
        setTimeout(() => {
            if (loadedImages < totalImages) {
                console.log("Printing anyway due to timeout");
                triggerPrint();
            }
        }, 3000);
    }
}

async function enviarWhatsApp(pedido = state.pedidoAtual) {
    if (!pedido) return;

    let settings = {};
    try {
        const result = await api('get_settings');
        if (result.ok) settings = result.data;
    } catch (e) { }

    const telefone = cleanPhone(pedido.cliente_telefone);

    if (!telefone || telefone.length < 10) {
        showToast('Telefone inválido para envio no WhatsApp', 'warning');
        return;
    }
    // ============================================
    // TICKET / COMPROVANTE (WHATSAPP)
    // ============================================

    const mensagem = gerarTextoTicket(pedido, settings);

    const url = `https://wa.me/55${telefone}?text=${encodeURIComponent(mensagem)}`;
    window.open(url, '_blank');

}


// ============================================
// DASHBOARD
// ============================================

async function carregarDashboard() {
    const periodo = document.getElementById('dashboardPeriodo').value;

    try {
        const result = await api('get_dashboard', { dias: periodo });

        if (result.ok) {
            renderDashboard(result.data, periodo);
        }
    } catch (error) {
        showToast('Erro ao carregar dashboard', 'error');
    }
}

function renderDashboard(data, periodo = '30') {
    // Cards principais - Faturamento Total agora é o destaque (baseado nos recebíveis/pedidos totais)
    document.getElementById('dashFaturamento').textContent = formatMoney(data.faturamento_total || 0);
    document.getElementById('dashReceitasPagas').textContent = formatMoney(data.receitas_pagas || 0);
    document.getElementById('dashRecebiveis').textContent = formatMoney(data.recebiveis || 0);
    document.getElementById('dashPedidos').textContent = data.total_pedidos || 0;
    document.getElementById('dashTicketMedio').textContent = formatMoney(data.ticket_medio || 0);

    // Comparação de Faturamento (Total)
    const faturamentoCompare = document.getElementById('dashReceitasCompare'); // Reutilizando o elemento de receitas
    if (data.receitas_compare !== undefined) {
        faturamentoCompare.textContent = `${data.receitas_compare >= 0 ? '+' : ''}${data.receitas_compare.toFixed(1)}% faturamento vs período anterior`;
        faturamentoCompare.className = `card-compare ${data.receitas_compare >= 0 ? 'positive' : 'negative'}`;
    }

    const pedidosCompare = document.getElementById('dashPedidosCompare');
    if (data.pedidos_compare !== undefined) {
        pedidosCompare.textContent = `${data.pedidos_compare >= 0 ? '+' : ''}${data.pedidos_compare.toFixed(1)}% pedidos vs período anterior`;
        pedidosCompare.className = `card-compare ${data.pedidos_compare >= 0 ? 'positive' : 'negative'}`;
    }

    // Cards menores
    document.getElementById('dashMediaItens').textContent = (data.media_itens || 0).toFixed(1);
    // document.getElementById('dashFaturamento').textContent = formatMoney(data.faturamento_total || 0); // Já movido para o topo
    document.getElementById('dashDescontos').textContent = formatMoney(data.total_descontos || 0);
    document.getElementById('dashTaxaPagamento').textContent = `${(data.taxa_pagamento || 0).toFixed(0)}%`;
    document.getElementById('dashPedidosPagos').textContent = data.pedidos_pagos || 0;
    document.getElementById('dashNovosClientes').textContent = data.novos_clientes || 0;
    document.getElementById('dashAdiantamentos').textContent = formatMoney(data.total_adiantamentos || 0);

    // Projeção do mês baseada no FATURAMENTO TOTAL (pedidos feitos)
    const projecaoMes = calcularProjecaoMes(data.faturamento_total || 0, periodo);
    document.getElementById('dashProjecaoMes').textContent = formatMoney(projecaoMes.valor);
    document.getElementById('dashProjecaoInfo').textContent = projecaoMes.info;
    document.getElementById('dashProjecaoInfo').className = 'card-compare';

    // Lucro Líquido e Despesas
    const totalDespesas = data.total_despesas || 0;
    const faturamentoTotal = data.faturamento_total || 0;
    const lucroLiquido = faturamentoTotal - totalDespesas;

    if (document.getElementById('dashTotalDespesas')) {
        document.getElementById('dashTotalDespesas').textContent = formatMoney(totalDespesas);
    }
    if (document.getElementById('dashLucroLiquido')) {
        document.getElementById('dashLucroLiquido').textContent = formatMoney(lucroLiquido);
        document.getElementById('dashLucroLiquido').className = `card-value ${lucroLiquido >= 0 ? 'success' : 'danger'}`;
    }

    // Rankings
    renderRanking('topServicos', data.top_servicos || [], 'vendas');
    renderRanking('topClientesFreq', data.top_clientes_freq || [], 'pedidos');
    renderRanking('topClientesFat', data.top_clientes_fat || [], 'total', true);

    // Insights
    renderInsights(data.insights || []);

    // Gráficos
    renderCharts(data);
}

function renderRanking(containerId, items, valueKey, isMoney = false) {
    const container = document.getElementById(containerId);

    if (!items || items.length === 0) {
        container.innerHTML = '<p style="color: var(--text-muted);">Sem dados</p>';
        return;
    }

    // Fix #1: Escapar nomes no ranking
    container.innerHTML = items.map((item, idx) => `
        <div class="ranking-item">
            <span class="ranking-position">${idx + 1}</span>
            <div class="ranking-info">
                <div class="ranking-name">${escapeHtml(item.nome)}</div>
                <div class="ranking-value">${isMoney ? formatMoney(item[valueKey]) : item[valueKey]}</div>
            </div>
        </div>
    `).join('');
}

function renderInsights(insights) {
    const container = document.getElementById('insightsList');

    if (!insights || insights.length === 0) {
        container.innerHTML = '<p style="color: var(--text-muted);">Sem insights disponíveis</p>';
        return;
    }

    // Fix #1: Escapar texto dos insights
    container.innerHTML = insights.map(insight => `
        <div class="insight-item">
            <span class="insight-icon">${insight.icon}</span>
            <span>${escapeHtml(insight.text)}</span>
        </div>
    `).join('');
}

function renderCharts(data) {
    // Destruir gráficos existentes
    Object.values(state.charts).forEach(chart => chart.destroy());
    state.charts = {};

    // Fix #14: Cores dos gráficos adaptadas ao tema atual
    const isDark = state.tema === 'dark';
    const textColor = isDark ? 'rgba(255,255,255,0.7)' : 'rgba(0,0,0,0.7)';
    const gridColor = isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)';

    const chartOptions = {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                labels: { color: textColor }
            }
        },
        scales: {
            x: {
                ticks: { color: textColor },
                grid: { color: gridColor }
            },
            y: {
                ticks: { color: textColor },
                grid: { color: gridColor }
            }
        }
    };

    // Status
    if (data.por_status) {
        state.charts.status = new Chart(document.getElementById('chartStatus'), {
            type: 'doughnut',
            data: {
                labels: data.por_status.map(s => s.status),
                datasets: [{
                    data: data.por_status.map(s => s.quantidade),
                    backgroundColor: ['#f59e0b', '#3b82f6', '#8b5cf6', '#10b981', '#22c55e']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: { color: textColor }
                    }
                }
            }
        });
    }

    // Pedidos por dia da semana
    if (data.por_dia_semana) {
        state.charts.diaSemana = new Chart(document.getElementById('chartDiaSemana'), {
            type: 'bar',
            data: {
                labels: ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'],
                datasets: [{
                    label: 'Pedidos',
                    data: data.por_dia_semana,
                    backgroundColor: '#10b981'
                }]
            },
            options: chartOptions
        });
    }

    // Horários
    if (data.por_hora) {
        state.charts.horarios = new Chart(document.getElementById('chartHorarios'), {
            type: 'bar',
            data: {
                labels: data.por_hora.map(h => `${h.hora}h`),
                datasets: [{
                    label: 'Pedidos',
                    data: data.por_hora.map(h => h.quantidade),
                    backgroundColor: '#3b82f6'
                }]
            },
            options: chartOptions
        });
    }
}


// ============================================
// CONFIGURAÇÕES
// ============================================

async function carregarConfiguracoes() {
    try {
        const result = await api('get_settings');
        if (result.ok) {
            document.getElementById('cfgNome').value = result.data.nome || '';
            document.getElementById('cfgCnpj').value = result.data.cnpj || '';
            document.getElementById('cfgWhatsapp').value = formatPhone(result.data.whatsapp || '');
            document.getElementById('cfgPix').value = result.data.pix || '';
        }
    } catch (error) {
        console.error('Erro ao carregar configurações:', error);
    }

    await carregarServicosConfig();
}

document.getElementById('empresaForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    try {
        const result = await api('save_settings', {
            nome: document.getElementById('cfgNome').value,
            cnpj: document.getElementById('cfgCnpj').value,
            whatsapp: cleanPhone(document.getElementById('cfgWhatsapp').value),
            pix: document.getElementById('cfgPix').value
        });

        if (result.ok) {
            showToast('Dados salvos com sucesso!');
            document.getElementById('empresaNome').textContent = document.getElementById('cfgNome').value || 'LavExpress';
        }
    } catch (error) {
        showToast('Erro ao salvar: ' + error.message, 'error');
    }
});

document.getElementById('senhaForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const nova = document.getElementById('novaSenha').value;
    const confirmar = document.getElementById('confirmarSenha').value;

    if (nova !== confirmar) {
        showToast('As senhas não coincidem', 'error');
        return;
    }

    try {
        const result = await api('alterar_senha', { nova_senha: nova });
        if (result.ok) {
            showToast('Senha alterada com sucesso!');
            document.getElementById('novaSenha').value = '';
            document.getElementById('confirmarSenha').value = '';
        }
    } catch (error) {
        showToast('Erro ao alterar senha: ' + error.message, 'error');
    }
});

document.getElementById('senhaConfigForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const senha = document.getElementById('senhaConfig').value;

    try {
        const result = await api('definir_senha_config', { senha });
        if (result.ok) {
            showToast('Senha de configurações definida!');
            document.getElementById('senhaConfig').value = '';
        }
    } catch (error) {
        showToast('Erro: ' + error.message, 'error');
    }
});

// ============================================
// GESTÃO DE SERVIÇOS
// ============================================

async function carregarServicosConfig() {
    try {
        const result = await api('get_servicos');
        if (result.ok) {
            state.servicos = result.data;
            renderServicosConfig();
        }
    } catch (error) {
        console.error('Erro ao carregar serviços:', error);
    }
}

function renderServicosConfig() {
    const container = document.getElementById('servicosList');

    container.innerHTML = state.servicos.map(s => `
        <div class="servico-item">
            <div class="servico-header" onclick="toggleSubservicos(${s.id})">
                <span class="servico-header-icon">${renderIcon(s.icone)}</span>
                <span class="servico-header-nome">${escapeHtml(s.nome)}</span>
                <span>${(s.subservicos || []).length} itens</span>
                <div class="servico-header-actions">
                    <button class="btn btn-sm btn-secondary" onclick="event.stopPropagation(); abrirModalSubservicoNovo(${s.id})">+ Item</button>
                    <button class="btn btn-sm btn-secondary" onclick="event.stopPropagation(); editarServico(${s.id})">✏️</button>
                    <button class="btn btn-sm btn-danger" onclick="event.stopPropagation(); excluirServico(${s.id})">🗑️</button>
                </div>
            </div>
            <div class="subservicos-container" id="subservicos-${s.id}">
                ${(s.subservicos || []).map(sub => `
                    <div class="subservico-item">
                        <span class="subservico-item-icon">${renderIcon(sub.icone)}</span>
                        <span class="subservico-item-nome">${escapeHtml(sub.nome)}</span>
                        <span class="subservico-item-preco">${formatMoney(sub.preco)}</span>
                        <div class="subservico-item-actions">
                            <button class="btn btn-sm btn-secondary" onclick="editarSubservico(${s.id}, ${sub.id})">✏️</button>
                            <button class="btn btn-sm btn-danger" onclick="excluirSubservico(${sub.id})">🗑️</button>
                        </div>
                    </div>
                `).join('')}
            </div>
        </div>
    `).join('');
}

function toggleSubservicos(servicoId) {
    const container = document.getElementById(`subservicos-${servicoId}`);
    container.classList.toggle('open');
}

function abrirModalServico() {
    document.getElementById('servicoModalTitle').textContent = 'Nova Categoria';
    document.getElementById('servicoId').value = '';
    document.getElementById('servicoNome').value = '';
    document.getElementById('servicoEmoji').value = '';
    document.getElementById('servicoIconFile').value = '';
    document.getElementById('iconEmoji').checked = true;
    document.getElementById('iconPreview').innerHTML = '';
    document.getElementById('servicoModal').classList.remove('hidden');
}

function editarServico(id) {
    const servico = state.servicos.find(s => s.id === id);
    if (!servico) return;

    document.getElementById('servicoModalTitle').textContent = 'Editar Categoria';
    document.getElementById('servicoId').value = id;
    document.getElementById('servicoNome').value = servico.nome;

    if (servico.icone && servico.icone.startsWith('uploads/')) {
        document.getElementById('iconImage').checked = true;
        document.getElementById('iconPreview').innerHTML = `<img src="${servico.icone}" alt="icon">`;
    } else {
        document.getElementById('iconEmoji').checked = true;
        document.getElementById('servicoEmoji').value = servico.icone || '';
        document.getElementById('iconPreview').innerHTML = `<span class="emoji-preview">${servico.icone || ''}</span>`;
    }

    document.getElementById('servicoModal').classList.remove('hidden');
}

function fecharModalServico() {
    document.getElementById('servicoModal').classList.add('hidden');
}

async function salvarServico() {
    const id = document.getElementById('servicoId').value;
    const nome = document.getElementById('servicoNome').value;
    const iconType = document.querySelector('input[name="iconType"]:checked').value;

    const data = { nome };

    if (id) data.id = id;

    if (iconType === 'emoji') {
        data.icone = document.getElementById('servicoEmoji').value;
    } else {
        const file = document.getElementById('servicoIconFile').files[0];
        if (file) {
            data.icone_file = file;
        }
    }

    try {
        const result = await api(id ? 'editar_servico' : 'criar_servico', data);
        if (result.ok) {
            showToast('Categoria salva com sucesso!');
            fecharModalServico();
            await carregarServicos();
            await carregarServicosConfig();
        }
    } catch (error) {
        showToast('Erro ao salvar: ' + error.message, 'error');
    }
}

async function excluirServico(id) {
    const aceito = await confirmar('🗑️ Excluir Categoria', 'Excluir esta categoria e todos os seus itens?');
    if (!aceito) return;

    try {
        await api('excluir_servico', { id });
        showToast('Categoria excluída');
        await carregarServicos();
        await carregarServicosConfig();
    } catch (error) {
        showToast('Erro ao excluir: ' + error.message, 'error');
    }
}

function abrirModalSubservicoNovo(servicoId) {
    document.getElementById('subservicoModalTitle').textContent = 'Novo Subserviço';
    document.getElementById('subservicoId').value = '';
    document.getElementById('subservicoServicoId').value = servicoId;
    document.getElementById('subservicoNome').value = '';
    document.getElementById('subservicoPreco').value = '';
    document.getElementById('subservicoEmoji').value = '';
    document.getElementById('subservicoIconFile').value = '';
    document.getElementById('subIconEmoji').checked = true;
    document.getElementById('subIconPreview').innerHTML = '';
    document.getElementById('subservicoModal').classList.remove('hidden');
}

function editarSubservico(servicoId, subId) {
    const servico = state.servicos.find(s => s.id === servicoId);
    const sub = servico?.subservicos?.find(ss => ss.id === subId);
    if (!sub) return;

    document.getElementById('subservicoModalTitle').textContent = 'Editar Subserviço';
    document.getElementById('subservicoId').value = subId;
    document.getElementById('subservicoServicoId').value = servicoId;
    document.getElementById('subservicoNome').value = sub.nome;
    document.getElementById('subservicoPreco').value = (sub.preco / 100).toFixed(2);

    if (sub.icone && sub.icone.startsWith('uploads/')) {
        document.getElementById('subIconImage').checked = true;
        document.getElementById('subIconPreview').innerHTML = `<img src="${sub.icone}" alt="icon">`;
    } else {
        document.getElementById('subIconEmoji').checked = true;
        document.getElementById('subservicoEmoji').value = sub.icone || '';
        document.getElementById('subIconPreview').innerHTML = `<span class="emoji-preview">${sub.icone || ''}</span>`;
    }

    document.getElementById('subservicoModal').classList.remove('hidden');
}

function fecharModalSubservico() {
    document.getElementById('subservicoModal').classList.add('hidden');
}

async function salvarSubservico() {
    const id = document.getElementById('subservicoId').value;
    const servicoId = document.getElementById('subservicoServicoId').value;
    const nome = document.getElementById('subservicoNome').value;
    const preco = parseMoney(document.getElementById('subservicoPreco').value);
    const iconType = document.querySelector('input[name="subIconType"]:checked').value;

    const data = { servico_id: servicoId, nome, preco };

    if (id) data.id = id;

    if (iconType === 'emoji') {
        data.icone = document.getElementById('subservicoEmoji').value;
    } else {
        const file = document.getElementById('subservicoIconFile').files[0];
        if (file) {
            data.icone_file = file;
        }
    }

    try {
        const result = await api(id ? 'editar_subservico' : 'criar_subservico', data);
        if (result.ok) {
            showToast('Subserviço salvo com sucesso!');
            fecharModalSubservico();
            await carregarServicos();
            await carregarServicosConfig();
        }
    } catch (error) {
        showToast('Erro ao salvar: ' + error.message, 'error');
    }
}

async function excluirSubservico(id) {
    const aceito = await confirmar('🗑️ Excluir Subserviço', 'Excluir este subserviço?');
    if (!aceito) return;

    try {
        await api('excluir_subservico', { id });
        showToast('Subserviço excluído');
        await carregarServicos();
        await carregarServicosConfig();
    } catch (error) {
        showToast('Erro ao excluir: ' + error.message, 'error');
    }
}

async function exportarServicos() {
    try {
        const result = await api('exportar_servicos');
        if (result.ok) {
            const blob = new Blob([JSON.stringify(result.data, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `servicos_${new Date().toISOString().split('T')[0]}.json`;
            a.click();
            URL.revokeObjectURL(url);
            showToast('Serviços exportados');
        }
    } catch (error) {
        showToast('Erro ao exportar', 'error');
    }
}

async function importarServicos(event) {
    const file = event.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = async (e) => {
        try {
            const data = JSON.parse(e.target.result);
            const result = await api('importar_servicos', { dados: data });
            if (result.ok) {
                showToast('Serviços importados com sucesso!');
                await carregarServicos();
                await carregarServicosConfig();
            }
        } catch (error) {
            showToast('Erro ao importar: ' + error.message, 'error');
        }
    };
    reader.readAsText(file);
    event.target.value = '';
}

async function resetarServicos() {
    const aceito = await confirmar('⚠️ Resetar Serviços', 'Isso irá substituir todos os serviços pelos padrões. Continuar?');
    if (!aceito) return;

    try {
        const result = await api('resetar_servicos');
        if (result.ok) {
            showToast('Serviços resetados para o padrão');
            await carregarServicos();
            await carregarServicosConfig();
        }
    } catch (error) {
        showToast('Erro ao resetar: ' + error.message, 'error');
    }
}

// ============================================
// CLIENTES
// ============================================

async function exportarClientesCSV() {
    try {
        const result = await api('exportar_clientes_csv');
        if (result.ok) {
            const blob = new Blob([result.data], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `clientes_${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            URL.revokeObjectURL(url);
            showToast('Clientes exportados em CSV');
        }
    } catch (error) {
        showToast('Erro ao exportar', 'error');
    }
}

async function importarClientesCSV(event) {
    const file = event.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = async (e) => {
        try {
            const result = await api('importar_clientes_csv', { csv: e.target.result });
            if (result.ok) {
                showToast(`${result.data.importados} clientes importados`);
            }
        } catch (error) {
            showToast('Erro ao importar: ' + error.message, 'error');
        }
    };
    reader.readAsText(file);
    event.target.value = '';
}

async function exportarClientesJSON() {
    try {
        const result = await api('exportar_clientes_json');
        if (result.ok) {
            const blob = new Blob([JSON.stringify(result.data, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `clientes_historico_${new Date().toISOString().split('T')[0]}.json`;
            a.click();
            URL.revokeObjectURL(url);
            showToast('Clientes e histórico exportados');
        }
    } catch (error) {
        showToast('Erro ao exportar', 'error');
    }
}

async function importarClientesJSON(event) {
    const file = event.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = async (e) => {
        try {
            const data = JSON.parse(e.target.result);
            const result = await api('importar_clientes_json', { dados: data });
            if (result.ok) {
                showToast(`${result.data.importados} clientes importados com histórico`);
            }
        } catch (error) {
            showToast('Erro ao importar: ' + error.message, 'error');
        }
    };
    reader.readAsText(file);
    event.target.value = '';
}

// ============================================
// BACKUP
// ============================================

async function fazerBackup() {
    try {
        const result = await api('backup');
        if (result.ok) {
            const blob = new Blob([JSON.stringify(result.data, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `backup_lavanderia_${new Date().toISOString().split('T')[0]}.json`;
            a.click();
            URL.revokeObjectURL(url);
            showToast('Backup realizado com sucesso!');
        }
    } catch (error) {
        showToast('Erro ao fazer backup', 'error');
    }
}

async function restaurarBackup(event) {
    const file = event.target.files[0];
    if (!file) return;

    const aceito = await confirmar('⚠️ Restaurar Backup', 'Isso irá substituir todos os dados atuais. Continuar?');
    if (!aceito) {
        event.target.value = '';
        return;
    }

    const reader = new FileReader();
    reader.onload = async (e) => {
        try {
            const data = JSON.parse(e.target.result);
            const result = await api('restaurar', { dados: data });
            if (result.ok) {
                showToast('Backup restaurado com sucesso!');
                location.reload();
            }
        } catch (error) {
            showToast('Erro ao restaurar: ' + error.message, 'error');
        }
    };
    reader.readAsText(file);
    event.target.value = '';
}

async function repararBanco() {
    const aceito = await confirmar('🔧 Reparar Banco', 'Isso irá verificar e reparar a estrutura do banco de dados. Continuar?');
    if (!aceito) return;

    try {
        const result = await api('reparar_banco');
        if (result.ok) {
            showToast('Banco de dados verificado e reparado!');
        }
    } catch (error) {
        showToast('Erro ao reparar: ' + error.message, 'error');
    }
}

// ============================================
// EVENT LISTENERS EXTRAS
// ============================================

// Preview de ícone de serviço
document.getElementById('servicoEmoji').addEventListener('input', (e) => {
    document.getElementById('iconPreview').innerHTML = `<span class="emoji-preview">${e.target.value}</span>`;
});

document.getElementById('servicoIconFile').addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = (ev) => {
            document.getElementById('iconPreview').innerHTML = `<img src="${ev.target.result}" alt="preview">`;
        };
        reader.readAsDataURL(file);
    }
});

// Preview de ícone de subserviço
document.getElementById('subservicoEmoji').addEventListener('input', (e) => {
    document.getElementById('subIconPreview').innerHTML = `<span class="emoji-preview">${e.target.value}</span>`;
});

document.getElementById('subservicoIconFile').addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = (ev) => {
            document.getElementById('subIconPreview').innerHTML = `<img src="${ev.target.result}" alt="preview">`;
        };
        reader.readAsDataURL(file);
    }
});

// Fechar modais ao clicar fora
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.add('hidden');
        }
    });
});



// Listener para atualizar botão finalizar
document.getElementById('clienteNome').addEventListener('input', atualizarBotaoFinalizar);
document.getElementById('clienteTelefone').addEventListener('input', atualizarBotaoFinalizar);

// Inicialização
document.addEventListener('DOMContentLoaded', checkAuth);

function formatDateBRFromUTC(dateStr) {
    const iso = dateStr.replace(' ', 'T');
    const date = new Date(iso);

    return date.toLocaleDateString('pt-BR', {
        timeZone: 'America/Sao_Paulo'
    }) + ' ' + date.toLocaleTimeString('pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
        timeZone: 'America/Sao_Paulo'
    });
}

// Busca de serviços no PDV
// Fix #6/#20: Removido listener duplicado de busca de serviços (já existe em initPDV)

function abrirWhatsAppCliente() {
    if (!state.pedidoAtual || !state.pedidoAtual.cliente_telefone) {
        showToast('Telefone do cliente não encontrado', 'error');
        return;
    }

    const telefone = cleanPhone(state.pedidoAtual.cliente_telefone);

    if (telefone.length < 10) {
        showToast('Telefone inválido', 'error');
        return;
    }

    const url = `https://wa.me/55${telefone}`;
    window.open(url, '_blank');
}

async function finalizarEnviarEImprimir(pedidoCriado) {
    await enviarWhatsApp(pedidoCriado);
    state.pedidoAtual = pedidoCriado;
    await imprimirPedido();
}

function gerarTextoTicket(pedido, settings = {}) {
    const entregaFormatada = pedido.data_entrega
        ? pedido.data_entrega.split('-').reverse().join('/')
        : 'A combinar';

    const itensTexto = (pedido.itens || []).map(i =>
        `${i.quantidade}x ${i.nome} - ${formatMoney(i.preco * i.quantidade)}`
    ).join('\n');

    const dataPedido = pedido.created_at
        ? formatDate(pedido.created_at)
        : '';

    const dataEntrega = pedido.data_entrega
        ? pedido.data_entrega.split('-').reverse().join('/')
        : 'A combinar';

    const desconto = pedido.desconto || 0;
    const adiantamento = pedido.adiantamento || 0;
    const saldo = pedido.total - adiantamento;

    return `
================================
        ${settings.nome || 'LAVEXPRESS'}
================================
Pedido Nº: ${String(pedido.id).padStart(4, '0')}
Cliente: ${pedido.cliente_nome || 'Não informado'}
Telefone: ${formatPhone(pedido.cliente_telefone)}
Data: ${dataPedido}
Entrega: ${dataEntrega}
--------------------------------
ITENS
--------------------------------
${itensTexto}
--------------------------------
Subtotal: ${formatMoney(pedido.subtotal)}
${desconto > 0 ? `Desconto: - ${formatMoney(desconto)}\n` : ''}Total: ${formatMoney(pedido.total)}
${adiantamento > 0 ? `Pago: - ${formatMoney(adiantamento)}\n` : ''}Saldo: ${formatMoney(saldo)}
--------------------------------
Status: ${pedido.status?.toUpperCase() || 'PENDENTE'}
================================
`.trim();
}

function atualizarSistema() {
    window.location.reload(true);
}

// ============================================
// ATALHOS DE TECLADO
// ============================================

function initKeyboardShortcuts() {
    document.addEventListener('keydown', (e) => {
        // Ignorar se estiver em input/textarea
        const tag = document.activeElement?.tagName;
        const isInput = tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT';

        if (e.key === 'Escape') {
            // Fechar qualquer modal aberto
            document.querySelectorAll('.modal:not(.hidden)').forEach(m => m.classList.add('hidden'));
            return;
        }

        if (isInput) return;

        if (e.key === 'F2') {
            e.preventDefault();
            switchTab('pdv');
            document.getElementById('clienteNome').focus();
        } else if (e.key === 'F3') {
            e.preventDefault();
            switchTab('pedidos');
            document.getElementById('filtroBusca').focus();
        } else if (e.key === 'F4') {
            e.preventDefault();
            switchTab('dashboard');
        }
    });
}

// ============================================
// TOGGLE TEMA CLARO/ESCURO
// ============================================

function initTheme() {
    applyTheme(state.tema);
}

function toggleTheme() {
    state.tema = state.tema === 'dark' ? 'light' : 'dark';
    localStorage.setItem('tema', state.tema);
    applyTheme(state.tema);
}

function applyTheme(tema) {
    document.documentElement.setAttribute('data-theme', tema);
    const btn = document.getElementById('themeToggle');
    if (btn) btn.textContent = tema === 'dark' ? '☀️' : '🌙';
}

// ============================================
// PWA — Service Worker
// ============================================
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('sw.js').catch(() => { });
    });
}

