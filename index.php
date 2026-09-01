<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <link rel="icon"
        href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='0.9em' font-size='90'>🧺</text></svg>">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LavExpress</title>
    <link rel="stylesheet" href="style.css?v=<?php echo filemtime('style.css'); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/pt.js"></script>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#667eea">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="description"
        content="LavExpress - Sistema de gestão para lavanderia - PDV, pedidos, dashboard e clientes">

</head>

<body>
    <!-- Login Overlay -->
    <div id="loginOverlay" class="overlay">
        <div class="login-box glass">
            <div class="login-icon">🧺</div>
            <h1>LavExpress</h1>
            <p class="login-subtitle">Faça login para continuar</p>
            <div id="defaultPassHint" class="default-pass-hint hidden">
                ⚠️ Senha padrão inicial: <strong>1234</strong>
            </div>
            <form id="loginForm">
                <div class="form-group">
                    <label>Senha</label>
                    <input type="password" id="loginPassword" placeholder="Digite sua senha" required>
                </div>
                <button type="submit" class="btn btn-primary btn-full">Entrar</button>
            </form>
            <div id="loginError" class="error-msg hidden"></div>
        </div>
    </div>

    <!-- Config Password Modal -->
    <div id="configPasswordModal" class="modal hidden">
        <div class="modal-content glass small">
            <div class="modal-header">
                <h2>🔐 Área Protegida</h2>
                <button class="close-btn" onclick="closeConfigPasswordModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>Digite a senha de configurações para acessar:</p>
                <div class="form-group">
                    <input type="password" id="configPasswordInput" placeholder="Senha de configurações">
                </div>
                <div id="configPassError" class="error-msg hidden"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeConfigPasswordModal()">Cancelar</button>
                <button class="btn btn-primary" onclick="verifyConfigPassword()">Acessar</button>
            </div>
        </div>
    </div>

    <!-- Main App Container -->
    <div id="appContainer" class="hidden">
        <!-- Header -->
        <header class="header glass">
            <div class="header-left">
                <span class="logo">🧺</span>
                <h1 id="empresaNome">LavExpress</h1>
            </div>
            <nav class="nav-tabs">
                <button class="nav-tab active" data-tab="pdv">🧾 PDV</button>
                <button class="nav-tab" data-tab="pedidos">📦 Pedidos</button>
                <button class="nav-tab" data-tab="dashboard">📊 Dashboard</button>
                <button class="nav-tab" data-tab="despesas">💸 Despesas</button>
                <button class="nav-tab" data-tab="config">⚙️ Configurações</button>
                <button class="nav-tab" onclick="atualizarSistema()">🔄 Atualizar</button>
                <a href="tutorial.html" target="_blank" class="nav-tab">Tutorial</a>
            </nav>
            <div class="header-right">
                <button class="btn btn-icon" id="themeToggle" onclick="toggleTheme()" title="Alternar tema">☀️</button>
                <button class="btn btn-logout" onclick="logout()">Sair</button>
                <div class="shortcuts-hint" title="Atalhos: F2=PDV, F3=Pedidos, F4=Dashboard, ESC=Fechar">⌨️</div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="main-content">
            <!-- PDV Section -->
            <section id="pdvSection" class="section active">
                <div class="pdv-container">
                    <!-- Top Section (Banner + Cliente) -->
                    <div class="pdv-top">
                        <!-- Banner de Edição -->
                        <div id="editBanner" class="edit-banner hidden">
                            <span>✏️ Editando Pedido <strong id="editBannerNumero">#0</strong></span>
                            <button class="btn btn-sm btn-danger" onclick="cancelarEdicaoPedido()">✗ Cancelar</button>
                        </div>

                        <!-- Cliente -->
                        <div class="pdv-cliente glass">
                            <h3>👤 Cliente</h3>
                            <div class="form-row">
                                <div class="form-group flex-2">
                                    <label>Nome</label>
                                    <input type="text" id="clienteNome" placeholder="Digite o nome do cliente"
                                        autocomplete="off">
                                    <div id="clienteSuggestions" class="suggestions hidden"></div>
                                </div>
                                <div class="form-group flex-1">
                                    <label>Telefone (WhatsApp)</label>
                                    <input type="text" id="clienteTelefone" placeholder="(00) 00000-0000"
                                        maxlength="15">
                                </div>
                            </div>
                            <div id="clienteInfo" class="cliente-info hidden">
                                <span class="badge badge-success">✓ Cliente cadastrado</span>
                            </div>
                            <div id="clienteHistorico" class="cliente-historico hidden">
                                <div class="historico-header">
                                    <span>📊 Histórico</span>
                                    <span id="historicoResumo" class="historico-resumo"></span>
                                </div>
                                <div id="historicoLista" class="historico-lista"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Serviços Grid -->
                    <div class="pdv-servicos glass">
                        <div class="section-header">
                            <h3>🏷️ Serviços</h3>

                            <div class="form-group" style="margin-bottom:0; max-width:240px">
                                <input type="text" id="buscaServicos" placeholder="Buscar serviço...">
                            </div>
                        </div>

                        <div id="servicosGrid" class="servicos-grid"></div>
                    </div>

                    <!-- Carrinho -->
                    <div class="pdv-carrinho glass">
                        <h3>🛒 Carrinho</h3>
                        <div id="carrinhoItems" class="carrinho-items"></div>
                        <div class="carrinho-empty" id="carrinhoEmpty">
                            <span>🛒</span>
                            <p>Carrinho vazio</p>
                        </div>

                        <div class="carrinho-footer">
                            <div class="datas-row">
                                <div class="form-group">
                                    <label>📅 Data do Pedido</label>
                                    <input type="date" id="dataPedido">
                                </div>
                                <div class="form-group">
                                    <label>📦 Previsão Entrega</label>
                                    <input type="date" id="dataEntrega">
                                </div>
                            </div>
                            <div class="form-row status-item-row" style="margin-bottom: 8px;">
                                <div class="form-group">
                                    <label>📌 Status do Pedido</label>
                                    <select id="novoPedidoStatus">
                                        <option value="pendente" selected>Pendente</option>
                                        <option value="processando">Processando</option>
                                        <option value="pronto">Pronto</option>
                                        <option value="entregue">Entregue</option>
                                        <option value="pago">Pago</option>
                                    </select>
                                </div>
                            </div>
                            <div class="desconto-row">
                                <div class="form-group">
                                    <label>Desconto</label>
                                    <div class="desconto-input">
                                        <select id="descontoTipo">
                                            <option value="valor">R$</option>
                                            <option value="porcentagem">%</option>
                                        </select>
                                        <input type="number" id="descontoValor" placeholder="0" min="0" step="0.01">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Adiantamento</label>
                                    <input type="number" id="adiantamento" placeholder="0,00" min="0" step="0.01">
                                </div>
                            </div>
                            <!-- NOVO: Campo de Observações -->
                            <div class="form-group" style="margin-top: 10px;">
                                <label>📝 Observações</label>
                                <textarea id="observacoes" placeholder="Observacoes do pedido..." rows="2"></textarea>

                            </div>
                            <div class="totais">
                                <div class="total-row">
                                    <span>Subtotal:</span>
                                    <span id="subtotal">R$ 0,00</span>
                                </div>
                                <div class="total-row desconto-display hidden" id="descontoDisplay">
                                    <span>Desconto:</span>
                                    <span id="descontoTotal">- R$ 0,00</span>
                                </div>

                                <div class="total-row total">
                                    <span>Total do Pedido:</span>
                                    <span id="totalPedido">R$ 0,00</span>
                                </div>
                                <div class="total-row adiantamento-display hidden" id="adiantamentoDisplay">
                                    <span>Valor Pago:</span>
                                    <span id="adiantamentoTotal">- R$ 0,00</span>
                                </div>
                                <div class="total-row saldo">
                                    <span>Saldo a Pagar:</span>
                                    <span id="saldo">R$ 0,00</span>
                                </div>

                            </div>

                            <div class="pedido-actions">
                                <button class="btn btn-primary btn-full btn-lg" id="finalizarPedido" disabled>
                                    ✓ Finalizar Pedido
                                </button>
                                <button class="btn btn-danger btn-full hidden" id="cancelarEdicao"
                                    onclick="cancelarEdicaoPedido()">
                                    ✗ Cancelar Edição
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Pedidos Section -->
            <section id="pedidosSection" class="section">
                <div class="section-header">
                    <h2>📦 Gestão de Pedidos</h2>
                    <div class="header-actions">
                        <div class="form-group inline">
                            <input type="date" id="filtroDataInicio">
                            <span>até</span>
                            <input type="date" id="filtroDataFim">
                        </div>
                        <select id="filtroStatus">
                            <option value="">Todos os status</option>
                            <option value="pendente">Pendente</option>
                            <option value="processando">Processando</option>
                            <option value="pronto">Pronto</option>
                            <option value="entregue">Entregue</option>
                            <option value="pago">Pago</option>
                        </select>
                        <input type="text" id="filtroBusca" placeholder="Buscar cliente...">
                        <button class="btn btn-secondary" onclick="filtrarPedidos()">🔍 Filtrar</button>
                    </div>
                </div>

                <div class="bulk-actions glass hidden" id="bulkActions">
                    <span id="selectedCount">0 selecionados</span>
                    <select id="bulkStatus">
                        <option value="">Alterar status para...</option>
                        <option value="pendente">Pendente</option>
                        <option value="processando">Processando</option>
                        <option value="pronto">Pronto</option>
                        <option value="entregue">Entregue</option>
                        <option value="pago">Pago</option>
                    </select>
                    <button class="btn btn-primary" onclick="aplicarStatusEmMassa()">Aplicar</button>
                </div>

                <div class="table-container glass">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAll" onchange="toggleSelectAll()"></th>
                                <th class="sortable" data-sort="id" onclick="ordenarPor('id')">Nº <span
                                        class="sort-icon"></span></th>
                                <th class="sortable" data-sort="cliente_nome" onclick="ordenarPor('cliente_nome')">
                                    Cliente <span class="sort-icon"></span></th>
                                <th>Itens</th>
                                <th class="sortable" data-sort="total" onclick="ordenarPor('total')">Total <span
                                        class="sort-icon"></span></th>
                                <th>Saldo</th>
                                <th class="sortable" data-sort="status" onclick="ordenarPor('status')">Status <span
                                        class="sort-icon"></span></th>
                                <th class="sortable" data-sort="created_at" onclick="ordenarPor('created_at')">Data
                                    <span class="sort-icon"></span>
                                </th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="pedidosTable"></tbody>
                    </table>
                </div>

                <div class="section-footer">
                    <button class="btn btn-secondary" onclick="exportarPedidos()">📤 Exportar Pedidos</button>
                    <button class="btn btn-secondary" onclick="document.getElementById('importPedidosFile').click()">📥
                        Importar Pedidos</button>
                    <input type="file" id="importPedidosFile" accept=".json" class="hidden"
                        onchange="importarPedidos(event)">
                </div>
            </section>

            <!-- Dashboard Section -->
            <section id="dashboardSection" class="section">
                <div class="section-header">
                    <h2>📊 Dashboard</h2>
                    <div class="header-actions">
                        <select id="dashboardPeriodo" onchange="carregarDashboard()">
                            <option value="mes">Este mês</option>
                            <option value="7">Últimos 7 dias</option>
                            <option value="30" selected>Últimos 30 dias</option>
                            <option value="90">Últimos 90 dias</option>
                            <option value="365">Último ano</option>
                        </select>
                    </div>
                </div>

                <div class="dashboard-grid">
                    <!-- Cards principais -->
                    <div class="dash-card glass primary">
                        <div class="card-icon">💰</div>
                        <div class="card-content">
                            <span class="card-label">Receitas Pagas</span>
                            <span class="card-value" id="dashReceitasPagas">R$ 0,00</span>
                            <span class="card-compare" id="dashReceitasCompare"></span>
                        </div>
                    </div>
                    <div class="dash-card glass warning">
                        <div class="card-icon">📋</div>
                        <div class="card-content">
                            <span class="card-label">Recebíveis</span>
                            <span class="card-value" id="dashRecebiveis">R$ 0,00</span>
                        </div>
                    </div>
                    <div class="dash-card glass info">
                        <div class="card-icon">📦</div>
                        <div class="card-content">
                            <span class="card-label">Pedidos no Período</span>
                            <span class="card-value" id="dashPedidos">0</span>
                            <span class="card-compare" id="dashPedidosCompare"></span>
                        </div>
                    </div>
                    <div class="dash-card glass success">
                        <div class="card-icon">🎫</div>
                        <div class="card-content">
                            <span class="card-label">Ticket Médio</span>
                            <span class="card-value" id="dashTicketMedio">R$ 0,00</span>
                        </div>
                    </div>

                    <!-- Mais indicadores -->
                    <div class="dash-card glass small">
                        <div class="card-content">
                            <span class="card-label">Média Itens/Pedido</span>
                            <span class="card-value" id="dashMediaItens">0</span>
                        </div>
                    </div>
                    <div class="dash-card glass small">
                        <div class="card-content">
                            <span class="card-label">Faturamento Total</span>
                            <span class="card-value" id="dashFaturamento">R$ 0,00</span>
                        </div>
                    </div>
                    <div class="dash-card glass small">
                        <div class="card-content">
                            <span class="card-label">Total Descontos</span>
                            <span class="card-value" id="dashDescontos">R$ 0,00</span>
                        </div>
                    </div>
                    <div class="dash-card glass small">
                        <div class="card-content">
                            <span class="card-label">Taxa Pagamento</span>
                            <span class="card-value" id="dashTaxaPagamento">0%</span>
                        </div>
                    </div>
                    <div class="dash-card glass small">
                        <div class="card-content">
                            <span class="card-label">Pedidos Pagos</span>
                            <span class="card-value" id="dashPedidosPagos">0</span>
                        </div>
                    </div>
                    <div class="dash-card glass small">
                        <div class="card-content">
                            <span class="card-label">Novos Clientes</span>
                            <span class="card-value" id="dashNovosClientes">0</span>
                        </div>
                    </div>
                    <div class="dash-card glass small">
                        <div class="card-content">
                            <span class="card-label">Adiantamentos</span>
                            <span class="card-value" id="dashAdiantamentos">R$ 0,00</span>
                        </div>
                    </div>
                    <div class="dash-card glass purple">
                        <div class="card-content">
                            <span class="card-label">📈 Projeção Mês</span>
                            <span class="card-value" id="dashProjecaoMes">R$ 0,00</span>
                            <span class="card-compare" id="dashProjecaoInfo"></span>
                        </div>
                    </div>
                    <div class="dash-card glass red">
                        <div class="card-content">
                            <span class="card-label">💸 Total Despesas</span>
                            <span class="card-value" id="dashTotalDespesas">R$ 0,00</span>
                        </div>
                    </div>
                    <div class="dash-card glass green">
                        <div class="card-content">
                            <span class="card-label">💰 Lucro Líquido</span>
                            <span class="card-value" id="dashLucroLiquido">R$ 0,00</span>
                        </div>
                    </div>
                </div>

                <!-- Rankings -->
                <div class="dashboard-rankings">
                    <div class="ranking-card glass">
                        <h4>🏆 Top 5 Serviços</h4>
                        <div id="topServicos" class="ranking-list"></div>
                    </div>
                    <div class="ranking-card glass">
                        <h4>👥 Top 5 Clientes (Frequência)</h4>
                        <div id="topClientesFreq" class="ranking-list"></div>
                    </div>
                    <div class="ranking-card glass">
                        <h4>💎 Top 5 Clientes (Faturamento)</h4>
                        <div id="topClientesFat" class="ranking-list"></div>
                    </div>
                </div>

                <!-- Insights -->
                <div class="insights-card glass">
                    <h4>💡 Insights Automáticos</h4>
                    <div id="insightsList" class="insights-list"></div>
                </div>

                <!-- Gráficos -->
                <div class="charts-grid">
                    <div class="chart-card glass">
                        <h4>📊 Distribuição por Status</h4>
                        <canvas id="chartStatus"></canvas>
                    </div>
                    <div class="chart-card glass">
                        <h4>📅 Pedidos por Dia da Semana</h4>
                        <canvas id="chartDiaSemana"></canvas>
                    </div>
                    <div class="chart-card glass full-width">
                        <h4>🕐 Horários de Maior Movimento</h4>
                        <canvas id="chartHorarios"></canvas>
                    </div>
                </div>
            </section>

            <!-- Despesas Section -->
            <section id="despesasSection" class="section">
                <div class="section-header">
                    <h2>💸 Gestão de Despesas</h2>
                    <div class="header-actions">
                        <div class="form-group inline">
                            <input type="date" id="despesaFiltroInicio">
                            <span>até</span>
                            <input type="date" id="despesaFiltroFim">
                        </div>
                        <select id="despesaFiltroStatus">
                            <option value="">Todos os status</option>
                            <option value="pendente">Pendente</option>
                            <option value="pago">Pago</option>
                        </select>
                        <button class="btn btn-secondary" onclick="carregarDespesas()">🔍 Filtrar</button>
                        <button class="btn btn-primary" onclick="abrirModalDespesa()">+ Nova Despesa</button>
                    </div>
                </div>

                <div class="table-container glass">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Descrição</th>
                                <th>Categoria</th>
                                <th>Vencimento</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="despesasTable"></tbody>
                    </table>
                </div>

                <div class="section-footer">
                    <div class="totais-despesas">
                        <span>Total no período: <strong id="totalDespesasPeriodo">R$ 0,00</strong></span>
                    </div>
                </div>
            </section>

            <!-- Configurações Section -->
            <section id="configSection" class="section">
                <div class="config-grid">
                    <!-- Dados da Empresa -->
                    <div class="config-card glass">
                        <h3>🏢 Dados da Empresa</h3>
                        <form id="empresaForm">
                            <div class="form-group">
                                <label>Nome da Empresa</label>
                                <input type="text" id="cfgNome" placeholder="Nome da lavanderia">
                            </div>
                            <div class="form-group">
                                <label>CNPJ</label>
                                <input type="text" id="cfgCnpj" placeholder="00.000.000/0000-00">
                            </div>
                            <div class="form-group">
                                <label>WhatsApp</label>
                                <input type="text" id="cfgWhatsapp" placeholder="(00) 00000-0000">
                            </div>
                            <div class="form-group">
                                <label>Chave PIX</label>
                                <input type="text" id="cfgPix" placeholder="Chave PIX">
                            </div>
                            <button type="submit" class="btn btn-primary">Salvar Dados</button>
                        </form>
                    </div>

                    <!-- Segurança -->
                    <div class="config-card glass">
                        <h3>🔐 Segurança</h3>
                        <form id="senhaForm">
                            <div class="form-group">
                                <label>Nova Senha de Login</label>
                                <input type="password" id="novaSenha" placeholder="Nova senha">
                            </div>
                            <div class="form-group">
                                <label>Confirmar Senha</label>
                                <input type="password" id="confirmarSenha" placeholder="Confirmar senha">
                            </div>
                            <button type="submit" class="btn btn-primary">Alterar Senha</button>
                        </form>
                        <hr>
                        <form id="senhaConfigForm">
                            <div class="form-group">
                                <label>Senha de Configurações</label>
                                <input type="password" id="senhaConfig" placeholder="Senha para área de config">
                            </div>
                            <button type="submit" class="btn btn-secondary">Definir Senha Config</button>
                        </form>
                    </div>

                    <!-- Gestão de Serviços -->
                    <div class="config-card glass full-width">
                        <h3>🏷️ Gestão de Serviços</h3>
                        <div class="servicos-manager">
                            <div class="servicos-actions">
                                <button class="btn btn-primary" onclick="abrirModalServico()"
                                    title="Criar nova categoria de serviço (Ex: Tapetes)">+ Nova Categoria</button>
                                <button class="btn btn-secondary" onclick="exportarServicos()"
                                    title="Salvar arquivo com todos os serviços e ícones">📤 Exportar</button>
                                <button class="btn btn-secondary"
                                    onclick="document.getElementById('importServicosFile').click()"
                                    title="Carregar serviços de um arquivo salvo">📥 Importar</button>
                                <input type="file" id="importServicosFile" accept=".json" class="hidden"
                                    onchange="importarServicos(event)">
                            </div>
                            <div id="servicosList" class="servicos-list"></div>
                        </div>
                    </div>

                    <!-- Clientes -->
                    <div class="config-card glass">
                        <h3>👥 Clientes</h3>
                        <div class="config-actions">
                            <button class="btn btn-secondary" onclick="exportarClientesCSV()"
                                title="Exportar lista simples (Excel) com Nome e Telefone">↓ CSV</button>
                            <button class="btn btn-secondary"
                                onclick="document.getElementById('importClientesCSV').click()"
                                title="Importar novos clientes de uma planilha">↑ CSV</button>
                            <input type="file" id="importClientesCSV" accept=".csv" class="hidden"
                                onchange="importarClientesCSV(event)">
                            <button class="btn btn-secondary" onclick="exportarClientesJSON()"
                                title="BACKUP COMPLETO: Salva clientes com todo o histórico de compras">↓ JSON</button>
                            <button class="btn btn-secondary"
                                onclick="document.getElementById('importClientesJSON').click()"
                                title="RESTAURAR: Adiciona clientes e histórico de um backup (Cuidado com duplicidade)">↑
                                JSON</button>
                            <input type="file" id="importClientesJSON" accept=".json" class="hidden"
                                onchange="importarClientesJSON(event)">
                        </div>
                    </div>

                    <!-- Backup -->
                    <div class="config-card glass">
                        <h3>💾 Backup e Restauração</h3>
                        <div class="config-actions">
                            <button class="btn btn-primary" onclick="fazerBackup()"
                                title="SEGURANÇA TOTAL: Salva uma cópia de TODO o sistema (Faça semanalmente)">📤
                                Backup</button>
                            <button class="btn btn-warning" onclick="document.getElementById('restoreFile').click()"
                                title="PERIGO: Apaga tudo que existe hoje e restaura o backup selecionado">📥
                                Restaurar</button>
                            <input type="file" id="restoreFile" accept=".json" class="hidden"
                                onchange="restaurarBackup(event)">
                            <button class="btn btn-danger" onclick="repararBanco()"
                                title="TÉCNICO: Use apenas se o sistema apresentar erros ou travamentos">🔧
                                Reparar</button>
                        </div>
                    </div>

                    <!-- Usuários -->
                    <div class="config-card glass">
                        <h3>👥 Gerenciamento de Usuários</h3>
                        <div class="config-actions">
                            <button class="btn btn-primary" onclick="abrirModalUsuario()">➕ Novo Usuário</button>
                        </div>
                        <div id="usuariosList" class="usuarios-list"></div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- Modal Subserviços -->
    <div id="subservicosModal" class="modal hidden">
        <div class="modal-content glass">
            <div class="modal-header">
                <h2 id="modalServicoNome"></h2>
                <button class="close-btn" onclick="fecharModalSubservicos()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="subservicosGrid" class="subservicos-grid"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" onclick="fecharModalSubservicos()">✓ Concluir</button>
            </div>
        </div>
    </div>

    <!-- Modal Detalhes Pedido -->
    <div id="pedidoModal" class="modal hidden">
        <div class="modal-content glass large">
            <div class="modal-header">
                <h2>📦 Detalhes do Pedido #<span id="pedidoNumero"></span></h2>
                <button class="close-btn" onclick="fecharModalPedido()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="pedido-info-grid">
                    <div class="pedido-cliente">
                        <h4>👤 Cliente</h4>
                        <p id="pedidoCliente"></p>
                        <p id="pedidoTelefone"></p>
                    </div>
                    <div class="pedido-status">
                        <div class="form-group">
                            <label>📌 Status</label>
                            <select id="pedidoStatus" onchange="atualizarStatusPedido()"></select>
                        </div>
                    </div>

                    <div class="pedido-data">
                        <h4>📅 Data</h4>
                        <p id="pedidoData"></p>
                        <p id="pedidoEntrega" style="font-size: 0.9em; color: var(--text-muted); margin-top: 4px;"></p>
                    </div>
                </div>

                <h4>🛒 Itens</h4>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Qtd</th>
                            <th>Valor Unit.</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody id="pedidoItens"></tbody>
                </table>

                <div class="pedido-totais">
                    <div class="total-row">
                        <span>Subtotal:</span>
                        <span id="pedidoSubtotal"></span>
                    </div>
                    <div class="total-row" id="pedidoDescontoRow">
                        <span>Desconto:</span>
                        <span id="pedidoDesconto"></span>
                    </div>
                    <div class="total-row total">
                        <span>Total:</span>
                        <span id="pedidoTotal"></span>
                    </div>
                    <div class="total-row">
                        <span>Adiantamento:</span>
                        <span id="pedidoAdiantamento"></span>
                    </div>
                    <!-- NOVO: Observações do Pedido -->
                    <div class="form-group" id="pedidoObservacoesContainer" style="margin-top: 15px; display: none;">
                        <label>📝 Observações</label>
                        <textarea id="pedidoObservacoes" readonly rows="3"></textarea>
                    </div>

                    <div class="total-row saldo">
                        <span>Saldo:</span>
                        <span id="pedidoSaldo"></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="imprimirPedido()">🖨️ Imprimir</button>
                <button class="btn btn-success" onclick="enviarWhatsApp()">📱 Enviar Comprovante</button>
                <button class="btn btn-info" onclick="abrirWhatsAppCliente()">💬 Abrir Chat</button>
                <button class="btn btn-primary" onclick="repetirPedido()">🔄 Repetir Pedido</button>
                <button class="btn btn-danger" onclick="excluirPedido()">🗑️ Excluir</button>
            </div>
        </div>
    </div>

    <!-- Modal Serviço/Categoria -->
    <div id="servicoModal" class="modal hidden">
        <div class="modal-content glass">
            <div class="modal-header">
                <h2 id="servicoModalTitle">Nova Categoria</h2>
                <button class="close-btn" onclick="fecharModalServico()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="servicoForm">
                    <input type="hidden" id="servicoId">
                    <div class="form-group">
                        <label>Nome da Categoria</label>
                        <input type="text" id="servicoNome" required>
                    </div>
                    <div class="form-group">
                        <label>Ícone</label>
                        <div class="icon-selector">
                            <div class="icon-option">
                                <input type="radio" name="iconType" value="emoji" id="iconEmoji" checked>
                                <label for="iconEmoji">Emoji</label>
                                <input type="text" id="servicoEmoji" placeholder="🧺" maxlength="4">
                            </div>
                            <div class="icon-option">
                                <input type="radio" name="iconType" value="image" id="iconImage">
                                <label for="iconImage">Imagem</label>
                                <input type="file" id="servicoIconFile" accept="image/png,image/jpeg">
                            </div>
                        </div>
                        <div id="iconPreview" class="icon-preview"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="fecharModalServico()">Cancelar</button>
                <button class="btn btn-primary" onclick="salvarServico()">Salvar</button>
            </div>
        </div>
    </div>

    <!-- Modal Subserviço -->
    <div id="subservicoModal" class="modal hidden">
        <div class="modal-content glass">
            <div class="modal-header">
                <h2 id="subservicoModalTitle">Novo Subserviço</h2>
                <button class="close-btn" onclick="fecharModalSubservico()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="subservicoForm">
                    <input type="hidden" id="subservicoId">
                    <input type="hidden" id="subservicoServicoId">
                    <div class="form-group">
                        <label>Nome do Subserviço</label>
                        <input type="text" id="subservicoNome" required>
                    </div>
                    <div class="form-group">
                        <label>Preço (R$)</label>
                        <input type="number" id="subservicoPreco" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Ícone</label>
                        <div class="icon-selector">
                            <div class="icon-option">
                                <input type="radio" name="subIconType" value="emoji" id="subIconEmoji" checked>
                                <label for="subIconEmoji">Emoji</label>
                                <input type="text" id="subservicoEmoji" placeholder="👔" maxlength="4">
                            </div>
                            <div class="icon-option">
                                <input type="radio" name="subIconType" value="image" id="subIconImage">
                                <label for="subIconImage">Imagem</label>
                                <input type="file" id="subservicoIconFile" accept="image/png,image/jpeg">
                            </div>
                        </div>
                        <div id="subIconPreview" class="icon-preview"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="fecharModalSubservico()">Cancelar</button>
                <button class="btn btn-primary" onclick="salvarSubservico()">Salvar</button>
            </div>
        </div>
    </div>

    <!-- Modal Pedido Criado (Sucesso) -->
    <div id="pedidoCriadoModal" class="modal hidden">
        <div class="modal-content glass small">
            <div class="modal-header">
                <h2>✅ Pedido Gerado!</h2>
                <button class="close-btn" onclick="fecharModalPedidoCriado()">&times;</button>
            </div>
            <div class="modal-body">
                <p id="pedidoCriadoMsg" class="success-msg"></p>
                <div class="modal-actions-grid">
                    <button class="btn btn-secondary" id="btnImprimirPedido">🖨️ Imprimir</button>
                    <button class="btn btn-success" id="btnZapPedido">📱 Enviar WhatsApp</button>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary btn-full" onclick="fecharModalPedidoCriado()">Fechar e Novo
                    Pedido</button>
            </div>
        </div>
    </div>

    <!-- Modal Despesa -->
    <div id="despesaModal" class="modal hidden">
        <div class="modal-content glass">
            <div class="modal-header">
                <h2 id="despesaModalTitle">Nova Despesa</h2>
                <button class="close-btn" onclick="fecharModalDespesa()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="despesaForm">
                    <input type="hidden" id="despesaId">
                    <div class="form-group">
                        <label>Descrição</label>
                        <input type="text" id="despesaDescricao" placeholder="Ex: Conta de Luz" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group flex-1">
                            <label>Valor (R$)</label>
                            <input type="number" id="despesaValor" step="0.01" min="0" required>
                        </div>
                        <div class="form-group flex-1">
                            <label>Vencimento</label>
                            <input type="date" id="despesaVencimento">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group flex-1">
                            <label>Categoria</label>
                            <select id="despesaCategoria">
                                <option value="Fixo">Fixo</option>
                                <option value="Variável">Variável</option>
                                <option value="Insumos">Insumos</option>
                                <option value="Equipamentos">Equipamentos</option>
                                <option value="Funcionários">Funcionários</option>
                                <option value="Outros">Outros</option>
                            </select>
                        </div>
                        <div class="form-group flex-1">
                            <label>Status</label>
                            <select id="despesaStatus">
                                <option value="pendente">Pendente</option>
                                <option value="pago">Pago</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="fecharModalDespesa()">Cancelar</button>
                <button class="btn btn-primary" onclick="salvarDespesa()">Salvar</button>
            </div>
        </div>
    </div>

    <!-- Modal Usuário -->
    <div id="usuarioModal" class="modal hidden">
        <div class="modal-content glass small">
            <div class="modal-header">
                <h2 id="usuarioModalTitle">Novo Usuário</h2>
                <button class="close-btn" onclick="fecharModalUsuario()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="usuarioForm">
                    <input type="hidden" id="usuarioId">
                    <div class="form-group">
                        <label>Nome do Usuário</label>
                        <input type="text" id="usuarioNome" placeholder="Ex: João Silva" required>
                    </div>
                    <div class="form-group">
                        <label>Senha</label>
                        <input type="password" id="usuarioSenha"
                            placeholder="Digite a senha (ou deixe vazio p/ manter)">
                    </div>
                    <div class="form-group">
                        <label>Nível de Acesso</label>
                        <select id="usuarioNivel">
                            <option value="operador">Funcionário (Operador)</option>
                            <option value="admin">Proprietário (Admin)</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="fecharModalUsuario()">Cancelar</button>
                <button class="btn btn-primary" onclick="salvarUsuario()">Salvar</button>
            </div>
        </div>
    </div>

    <!-- Print Frame -->
    <iframe id="printFrame" class="hidden"></iframe>

    <!-- Toast Notifications -->
    <div id="toastContainer" class="toast-container"></div>

    <script src="script.js?v=<?php echo filemtime('script.js'); ?>"></script>
</body>

</html>