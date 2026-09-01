<?php
/**
 * ============================================
 * LAVEXPRESS - BACKEND
 * ============================================
 * 
 * INSTRUÇÕES DE USO:
 * 1. Coloque todos os arquivos no mesmo diretório
 * 2. Acesse index.html pelo navegador (via servidor PHP)
 * 3. Senha padrão inicial: 1234
 * 4. O banco lavanderia.db é criado automaticamente
 * 
 * REQUISITOS:
 * - PHP 7.4+
 * - Extensão SQLite3 habilitada
 * - Permissão de escrita no diretório
 */

// Configurar error reporting para debug


error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Iniciar sessão antes de qualquer output
session_start();
date_default_timezone_set('America/Sao_Paulo');
// Headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Configurações
define('DB_FILE', __DIR__ . '/lavanderia.db');
define('UPLOAD_DIR', __DIR__ . '/uploads/icons/');
define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024); // 2MB
define('ALLOWED_TYPES', ['image/png', 'image/jpeg', 'image/jpg']);
define('BACKUP_DIR', __DIR__ . '/backups/');

// Criar diretórios se não existirem
if (!file_exists(UPLOAD_DIR)) {
    @mkdir(UPLOAD_DIR, 0755, true);
}
if (!file_exists(BACKUP_DIR)) {
    @mkdir(BACKUP_DIR, 0755, true);
    // Proteção .htaccess
    file_put_contents(BACKUP_DIR . '.htaccess', "Deny from all");
}

// ============================================
// BANCO DE DADOS
// ============================================

function getDB() {
    static $db = null;
    
    if ($db === null) {
        try {
            $isNew = !file_exists(DB_FILE);
            $db = new SQLite3(DB_FILE);
            $db->enableExceptions(true);
            $db->exec('PRAGMA foreign_keys = ON');
            $db->exec('PRAGMA journal_mode = WAL');
            
            initDatabase($db);
            autoBackupDiario();
        } catch (Exception $e) {
            response(false, null, 'Erro ao conectar banco: ' . $e->getMessage());
        }
    }
    
    return $db;
}

function initDatabase($db) {
    // Tabela de usuários
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT DEFAULT 'Dono',
            senha_hash TEXT NOT NULL,
            senha_config_hash TEXT,
            primeiro_acesso INTEGER DEFAULT 1,
            nivel TEXT DEFAULT 'operador',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Migração: Adicionar colunas se não existirem
    $cols = $db->query("PRAGMA table_info(users)");
    $temNivel = false;
    $temNome = false;
    while ($col = $cols->fetchArray(SQLITE3_ASSOC)) {
        if ($col['name'] === 'nivel') $temNivel = true;
        if ($col['name'] === 'nome') $temNome = true;
    }
    if (!$temNivel) {
        $db->exec("ALTER TABLE users ADD COLUMN nivel TEXT DEFAULT 'operador'");
        $db->exec("UPDATE users SET nivel = 'admin' WHERE id = (SELECT MIN(id) FROM users)");
    }
    if (!$temNome) {
        $db->exec("ALTER TABLE users ADD COLUMN nome TEXT DEFAULT 'Dono'");
    }
    
    // Inserir usuário padrão apenas se não existir nenhum
    $count = $db->querySingle("SELECT COUNT(*) FROM users");
    if ($count == 0) {
        $hash = password_hash('1234', PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (senha_hash) VALUES (?)");
        $stmt->bindValue(1, $hash, SQLITE3_TEXT);
        $stmt->execute();
    }
    
    // Tabela de configurações
    $db->exec("
        CREATE TABLE IF NOT EXISTS settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT DEFAULT 'LavExpress',
            cnpj TEXT,
            whatsapp TEXT,
            pix TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    // Inserir configurações padrão apenas se não existir nenhuma
    $countSettings = $db->querySingle("SELECT COUNT(*) FROM settings");
    if ($countSettings == 0) {
        $db->exec("INSERT INTO settings (nome) VALUES ('LavExpress')");
    }
    
    // Tabela de clientes
    $db->exec("
        CREATE TABLE IF NOT EXISTS clients (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT NOT NULL,
            telefone TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(telefone)
        )
    ");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_clients_telefone ON clients(telefone)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_clients_nome ON clients(nome)");
    
    // Tabela de serviços (categorias)
    $db->exec("
        CREATE TABLE IF NOT EXISTS services (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT NOT NULL,
            icone TEXT,
            ordem INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Tabela de subserviços
    $db->exec("
        CREATE TABLE IF NOT EXISTS subservices (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            service_id INTEGER NOT NULL,
            nome TEXT NOT NULL,
            preco INTEGER NOT NULL,
            icone TEXT,
            ordem INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
        )
    ");
    
    // Tabela de pedidos
    $db->exec("
        CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            client_id INTEGER,
            cliente_nome TEXT NOT NULL,
            cliente_telefone TEXT NOT NULL,
            subtotal INTEGER NOT NULL DEFAULT 0,
            desconto INTEGER NOT NULL DEFAULT 0,
            desconto_tipo TEXT DEFAULT 'valor',
            desconto_valor INTEGER DEFAULT 0,
            total INTEGER NOT NULL DEFAULT 0,
            adiantamento INTEGER NOT NULL DEFAULT 0,
            observacoes TEXT,
            status TEXT DEFAULT 'pendente',
            data_pedido DATE,
            data_entrega DATE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
        )
    ");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_orders_created ON orders(created_at)");
    
    // Tabela de itens do pedido
    $db->exec("
        CREATE TABLE IF NOT EXISTS order_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL,
            subservice_id INTEGER,
            nome TEXT NOT NULL,
            icone TEXT,
            preco INTEGER NOT NULL,
            quantidade INTEGER NOT NULL DEFAULT 1,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            FOREIGN KEY (subservice_id) REFERENCES subservices(id) ON DELETE SET NULL
        )
    ");

    // Tabela de Despesas
    $db->exec("
        CREATE TABLE IF NOT EXISTS expenses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            descricao TEXT NOT NULL,
            valor INTEGER NOT NULL,
            categoria TEXT,
            status TEXT DEFAULT 'pendente',
            data_vencimento DATE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Normalizar datas (Caso tenham sido importadas em formato BR)
    $db->exec("
        UPDATE orders SET 
            created_at = substr(created_at,7,4) || '-' || substr(created_at,4,2) || '-' || substr(created_at,1,2) || substr(created_at,11)
        WHERE created_at LIKE '__/__/____%'
    ");
    $db->exec("
        UPDATE orders SET 
            data_pedido = substr(data_pedido,7,4) || '-' || substr(data_pedido,4,2) || '-' || substr(data_pedido,1,2)
        WHERE data_pedido LIKE '__/__/____'
    ");
    $db->exec("
        UPDATE orders SET 
            data_entrega = substr(data_entrega,7,4) || '-' || substr(data_entrega,4,2) || '-' || substr(data_entrega,1,2)
        WHERE data_entrega LIKE '__/__/____'
    ");
    
    // Popular serviços padrão apenas se a tabela estiver vazia
    $countServices = $db->querySingle("SELECT COUNT(*) FROM services");
    if ($countServices == 0) {
        popularServicoPadrao($db);
    }
}

function popularServicoPadrao($db) {
    $servicos = [
        ['🤵', 'Roupas Sociais', [
            ['Terno', 3700],
            ['Vestido', 4000],
            ['Gravata', 500],
            ['Colete', 800],
            ['Calça Social', 1500],
            ['Blazer', 2700],
            ['Camisa Social', 1100],
            ['Saia', 1100],
            ['Conjunto Feminino', 3500],
            ['Jaleco', 1100]
        ]],
        ['👕', 'Camisetas e Calças', [
            ['Camiseta', 1000],
            ['Calça Jeans/Brim', 1000],
            ['Uniforme', 1000],
            ['Macacão', 1800],
            ['Bermuda', 1200]
        ]],
        ['🧥', 'Roupa de Frio', [
            ['Jaqueta', 3700],
            ['Sobretudo', 3700],
            ['Blusa Moletom', 1100],
            ['Calça Moletom', 1100]
        ]],
        ['⛪', 'Ecumênico', [
            ['Batina', 2000],
            ['Veste', 2000],
            ['Veste Simples', 1100]
        ]],
        ['👙', 'Peças Pequenas', [
            ['Par de Meia', 600],
            ['Cueca', 600],
            ['Calcinha', 600],
            ['Sutiã', 600],
            ['Biquíni', 1200],
            ['Sunga', 1200],
            ['Boné', 800],
            ['Luvas', 800],
            ['Gorro', 800]
        ]],
        ['🛏️', 'Cama', [
            ['Edredom', 4700],
            ['Manta/Colcha', 3500],
            ['Lençol Lavar/Passar', 2000],
            ['Lençol Dobrar', 1500],
            ['Fronha', 800],
            ['Almofada/Travesseiro', 1800],
            ['Rede', 4700]
        ]],
        ['🛁', 'Banho', [
            ['Toalha de Banho', 1200],
            ['Toalha de Rosto', 800],
            ['Roupão', 1200],
            ['Tapete WC', 800]
        ]],
        ['🪑', 'Toalhas de Mesa', [
            ['4 lugares', 1000],
            ['6 lugares', 1500],
            ['8 lugares', 2000],
            ['10 lugares', 2500]
        ]],
        ['🟫', 'Tapetes', [
            ['Sala', 5000],
            ['WC', 800]
        ]],
        ['🧸', 'Pelúcia', [
            ['Grande', 4500],
            ['Média', 3500],
            ['Pequena', 2500]
        ]],
        ['🎒', 'Mochilas e Bolsas', [
            ['Mochila Grande', 4500],
            ['Mochila Média', 3500],
            ['Mochila Pequena', 2000],
            ['Lancheira', 2000],
            ['Estojo', 1500],
            ['Bolsa', 3500]
        ]],
        ['👟', 'Calçados', [
            ['Calçado', 2500]
        ]],
        ['🪝', 'Cabide e Embalagem', [
            ['Cabide', 240],
            ['Embalagem', 200]
        ]],
        ['👶', 'Bebê', [
            ['Carrinho', 7000],
            ['Bebê Conforto', 5000],
            ['Chiqueirinho', 8000]
        ]],
        ['🧺', 'Cesto Lava & Seca', [
            ['Cesto', 5000]
        ]],
        ['🪟', 'Cortina', [
            ['Cortina', 7000]
        ]]
    ];
    
    $ordem = 0;
    foreach ($servicos as $servico) {
        $icone = $servico[0];
        $nome = $servico[1];
        $subs = $servico[2];
        
        $stmt = $db->prepare("INSERT INTO services (nome, icone, ordem) VALUES (?, ?, ?)");
        $stmt->bindValue(1, $nome, SQLITE3_TEXT);
        $stmt->bindValue(2, $icone, SQLITE3_TEXT);
        $stmt->bindValue(3, $ordem++, SQLITE3_INTEGER);
        $stmt->execute();
        
        $serviceId = $db->lastInsertRowID();
        
        $subOrdem = 0;
        foreach ($subs as $sub) {
            $stmt = $db->prepare("INSERT INTO subservices (service_id, nome, preco, icone, ordem) VALUES (?, ?, ?, ?, ?)");
            $stmt->bindValue(1, $serviceId, SQLITE3_INTEGER);
            $stmt->bindValue(2, $sub[0], SQLITE3_TEXT);
            $stmt->bindValue(3, $sub[1], SQLITE3_INTEGER);
            $stmt->bindValue(4, $icone, SQLITE3_TEXT);
            $stmt->bindValue(5, $subOrdem++, SQLITE3_INTEGER);
            $stmt->execute();
        }
    }
}

// ============================================
// FUNÇÕES AUXILIARES
// ============================================

function response($ok, $data = null, $error = null) {
    $response = ['ok' => $ok];
    if ($data !== null) $response['data'] = $data;
    if ($error !== null) $response['error'] = $error;
    
    // Incluir CSRF token na resposta
    if (isset($_SESSION['csrf_token'])) {
        $response['csrf_token'] = $_SESSION['csrf_token'];
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

function isAuthenticated() {
    return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

function requireAuth() {
    if (!isAuthenticated()) {
        response(false, null, 'Não autenticado');
    }
}

function requireAdmin() {
    requireAuth();
    if (($_SESSION['user_nivel'] ?? 'operador') !== 'admin') {
        response(false, null, 'Acesso negado: Apenas para o Dono.');
    }
}

function autoBackupDiario() {
    // Backup diário (Ano_Mês_Dia) ex: backup_2024_02_25.db
    $nomeBackup = 'backup_' . date('Y_m_d') . '.db';
    $caminhoBackup = BACKUP_DIR . $nomeBackup;
    
    // 1. Criar o backup do dia se não existir
    if (!file_exists($caminhoBackup) && file_exists(DB_FILE)) {
        @copy(DB_FILE, $caminhoBackup);
    }

    // 2. Rotação: Manter apenas os 3 últimos
    $backups = glob(BACKUP_DIR . 'backup_*.db');
    if (count($backups) > 3) {
        // Ordenar por data de modificação (mais recentes primeiro)
        usort($backups, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        // Deletar a partir do 4º arquivo
        for ($i = 3; $i < count($backups); $i++) {
            @unlink($backups[$i]);
        }
    }
}

// Gerar ou obter token CSRF
function getCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validar token CSRF (para operações que modificam dados)
function validateCsrf() {
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        response(false, null, 'Token CSRF inválido. Recarregue a página.');
    }
}

function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    if ($input === null) return '';
    return htmlspecialchars(trim((string)$input), ENT_QUOTES, 'UTF-8');
}

function getParam($key, $default = null) {
    $value = $_POST[$key] ?? $_GET[$key] ?? $default;
    if (is_string($value) && strlen($value) > 0) {
        $firstChar = substr($value, 0, 1);
        if ($firstChar === '[' || $firstChar === '{') {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }
    }
    return $value;
}

// Buscar ou criar cliente (elimina duplicação entre criar/atualizar pedido)
function upsertClient($db, $nome, $telefone) {
    $stmt = $db->prepare("SELECT id FROM clients WHERE telefone = ?");
    $stmt->bindValue(1, $telefone, SQLITE3_TEXT);
    $result = $stmt->execute()->fetchArray();
    
    if ($result) {
        return $result['id'];
    }
    
    $stmt = $db->prepare("INSERT INTO clients (nome, telefone) VALUES (?, ?)");
    $stmt->bindValue(1, $nome, SQLITE3_TEXT);
    $stmt->bindValue(2, $telefone, SQLITE3_TEXT);
    $stmt->execute();
    return $db->lastInsertRowID();
}

// Inserir itens de um pedido (elimina duplicação)
function insertOrderItems($db, $orderId, $itens) {
    foreach ($itens as $item) {
        $stmt = $db->prepare("
            INSERT INTO order_items (order_id, subservice_id, nome, icone, preco, quantidade) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bindValue(1, $orderId, SQLITE3_INTEGER);
        $stmt->bindValue(2, isset($item['subservicoId']) ? (int)$item['subservicoId'] : null, SQLITE3_INTEGER);
        $stmt->bindValue(3, $item['nome'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(4, $item['icone'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(5, (int)($item['preco'] ?? 0), SQLITE3_INTEGER);
        $stmt->bindValue(6, (int)($item['quantidade'] ?? 1), SQLITE3_INTEGER);
        $stmt->execute();
    }
}

// Carregar itens de múltiplos pedidos de uma vez (elimina N+1)
function loadOrderItems($db, $orderIds) {
    if (empty($orderIds)) return [];
    
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $stmt = $db->prepare("SELECT * FROM order_items WHERE order_id IN ($placeholders) ORDER BY id");
    foreach ($orderIds as $i => $id) {
        $stmt->bindValue($i + 1, (int)$id, SQLITE3_INTEGER);
    }
    $result = $stmt->execute();
    
    $grouped = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $grouped[$row['order_id']][] = $row;
    }
    return $grouped;
}

function uploadIcon($file) {
    if (!$file || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    // Validar tamanho
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        throw new Exception('Arquivo muito grande (máx 2MB)');
    }
    
    // Validar tipo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, ALLOWED_TYPES)) {
        throw new Exception('Tipo de arquivo não permitido');
    }
    
    // Gerar nome seguro
    $ext = ($mime === 'image/png') ? 'png' : 'jpg';
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $filepath = UPLOAD_DIR . $filename;
    
    // Mover arquivo
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Erro ao salvar arquivo');
    }
    
    return 'uploads/icons/' . $filename;
}

// ============================================
// HANDLERS
// ============================================

// AUTH
// AUTH
function handleAuthStatus() {
    $db = getDB();
    // Se não estiver autenticado, retornar apenas status básico sem morrer (para o frontend decidir)
    if (!isAuthenticated()) {
        $count = $db->querySingle("SELECT COUNT(*) FROM users");
        $user = $db->querySingle("SELECT primeiro_acesso FROM users LIMIT 1", true);
        
        // Mostrar dica apenas se for o único usuário e ainda não trocou a senha
        $mostrarDica = ($count == 1 && ($user['primeiro_acesso'] ?? 0) === 1);
        
        response(true, [
            'autenticado' => false,
            'primeiro_acesso' => $mostrarDica
        ]);
    }

    $user = $db->querySingle("SELECT primeiro_acesso, nivel FROM users WHERE id = " . $_SESSION['user_id'], true);
    
    // Sincronizar nível na sessão (importante para requireAdmin)
    $_SESSION['user_nivel'] = $user['nivel'] ?? 'operador';
    
    response(true, [
        'autenticado' => true,
        'primeiro_acesso' => ($user['primeiro_acesso'] ?? 0) === 1,
        'nivel' => $_SESSION['user_nivel']
    ]);
}

function handleLogin() {
    $senha = getParam('senha');
    if (!$senha) {
        response(false, null, 'Senha obrigatória');
    }
    
    $db = getDB();
    $result = $db->query("SELECT id, nome, senha_hash, nivel FROM users");
    
    $foundUser = null;
    while ($user = $result->fetchArray(SQLITE3_ASSOC)) {
        if (password_verify($senha, $user['senha_hash'])) {
            $foundUser = $user;
            break;
        }
    }
    
    if (!$foundUser) {
        response(false, null, 'Senha incorreta');
    }
    
    $_SESSION['authenticated'] = true;
    $_SESSION['user_id'] = $foundUser['id'];
    $_SESSION['user_nivel'] = $foundUser['nivel'] ?? 'operador';
    $_SESSION['user_nome'] = $foundUser['nome'] ?? 'Usuário';
    
    // Gerar token CSRF ao fazer login
    getCsrfToken();
    
    response(true, [
        'message' => 'Login realizado',
        'nome' => $_SESSION['user_nome'],
        'nivel' => $_SESSION['user_nivel']
    ]);
}

function handleLogout() {
    $_SESSION = [];
    session_destroy();
    response(true, ['message' => 'Logout realizado']);
}

function handleAlterarSenha() {
    requireAuth();
    
    $novaSenha = getParam('nova_senha');
    if (!$novaSenha || strlen($novaSenha) < 4) {
        response(false, null, 'Senha deve ter pelo menos 4 caracteres');
    }
    
    $db = getDB();
    $hash = password_hash($novaSenha, PASSWORD_DEFAULT);
    
    $stmt = $db->prepare("UPDATE users SET senha_hash = ?, primeiro_acesso = 0 WHERE id = 1");
    $stmt->bindValue(1, $hash, SQLITE3_TEXT);
    $stmt->execute();
    
    response(true, ['message' => 'Senha alterada']);
}

// USER MANAGEMENT (Admin Only)
function handleListUsers() {
    requireAdmin();
    $db = getDB();
    $result = $db->query("SELECT id, nome, nivel, created_at FROM users ORDER BY created_at DESC");
    $users = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $users[] = $row;
    }
    response(true, $users);
}

function handleSaveUser() {
    requireAdmin();
    validateCsrf();
    
    $id = getParam('id');
    $nome = getParam('nome');
    $nivel = getParam('nivel', 'operador');
    $senha = getParam('senha');
    
    if (!$nome) response(false, null, 'Nome é obrigatório');
    
    $db = getDB();
    
    if ($id) {
        // Update
        if ($senha) {
            $hash = password_hash($senha, PASSWORD_DEFAULT);
            $primeiroAcessoSql = ($id == 1) ? ", primeiro_acesso = 0" : "";
            $stmt = $db->prepare("UPDATE users SET nome = ?, nivel = ?, senha_hash = ? $primeiroAcessoSql WHERE id = ?");
            $stmt->bindValue(1, $nome, SQLITE3_TEXT);
            $stmt->bindValue(2, $nivel, SQLITE3_TEXT);
            $stmt->bindValue(3, $hash, SQLITE3_TEXT);
            $stmt->bindValue(4, (int)$id, SQLITE3_INTEGER);
        } else {
            $stmt = $db->prepare("UPDATE users SET nome = ?, nivel = ? WHERE id = ?");
            $stmt->bindValue(1, $nome, SQLITE3_TEXT);
            $stmt->bindValue(2, $nivel, SQLITE3_TEXT);
            $stmt->bindValue(3, (int)$id, SQLITE3_INTEGER);
        }
        $stmt->execute();
    } else {
        // Create
        if (!$senha) response(false, null, 'Senha é obrigatória para novos usuários');
        $hash = password_hash($senha, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (nome, nivel, senha_hash, primeiro_acesso) VALUES (?, ?, ?, 0)");
        $stmt->bindValue(1, $nome, SQLITE3_TEXT);
        $stmt->bindValue(2, $nivel, SQLITE3_TEXT);
        $stmt->bindValue(3, $hash, SQLITE3_TEXT);
        $stmt->execute();
    }
    
    response(true, ['message' => 'Usuário salvo']);
}

function handleDeleteUser() {
    requireAdmin();
    validateCsrf();
    
    $id = (int)getParam('id');
    if ($id === (int)$_SESSION['user_id']) {
        response(false, null, 'Você não pode excluir a si mesmo');
    }
    
    $db = getDB();
    $db->prepare("DELETE FROM users WHERE id = ?")->bindValue(1, $id, SQLITE3_INTEGER)->execute();
    response(true, ['message' => 'Usuário excluído']);
}

function handleDefinirSenhaConfig() {
    requireAuth();
    
    $senha = getParam('senha');
    if (!$senha) {
        response(false, null, 'Senha obrigatória');
    }
    
    $db = getDB();
    $hash = password_hash($senha, PASSWORD_DEFAULT);
    
    $stmt = $db->prepare("UPDATE users SET senha_config_hash = ? WHERE id = 1");
    $stmt->bindValue(1, $hash, SQLITE3_TEXT);
    $stmt->execute();
    
    response(true, ['message' => 'Senha de configurações definida']);
}

function handleVerifyConfigPassword() {
    requireAuth();
    
    $senha = getParam('senha');
    if (!$senha) {
        response(false, null, 'Senha obrigatória');
    }
    
    $db = getDB();
    $user = $db->querySingle("SELECT senha_hash, senha_config_hash FROM users LIMIT 1", true);
    
    if (!$user) {
        response(false, null, 'Usuário não encontrado');
    }
    
    // Se não há senha config, aceitar a senha principal
    if (empty($user['senha_config_hash'])) {
        if (password_verify($senha, $user['senha_hash'])) {
            response(true, ['message' => 'OK']);
        }
    } else {
        if (password_verify($senha, $user['senha_config_hash'])) {
            response(true, ['message' => 'OK']);
        }
    }
    
    response(false, null, 'Senha incorreta');
}

// SETTINGS
function handleGetSettings() {
    requireAuth();
    
    $db = getDB();
    $settings = $db->querySingle("SELECT nome, cnpj, whatsapp, pix FROM settings LIMIT 1", true);
    
    response(true, $settings ?: ['nome' => 'LavExpress', 'cnpj' => '', 'whatsapp' => '', 'pix' => '']);
}

function handleSaveSettings() {
    requireAuth();
    
    $db = getDB();
    $agora = date('Y-m-d H:i:s'); // Horário local (America/Sao_Paulo)
    $stmt = $db->prepare("UPDATE settings SET nome = ?, cnpj = ?, whatsapp = ?, pix = ?, updated_at = ? WHERE id = 1");
    $stmt->bindValue(1, sanitize(getParam('nome', '')), SQLITE3_TEXT);
    $stmt->bindValue(2, sanitize(getParam('cnpj', '')), SQLITE3_TEXT);
    $stmt->bindValue(3, sanitize(getParam('whatsapp', '')), SQLITE3_TEXT);
    $stmt->bindValue(4, sanitize(getParam('pix', '')), SQLITE3_TEXT);
    $stmt->bindValue(5, $agora, SQLITE3_TEXT);
    $stmt->execute();
    
    response(true, ['message' => 'Configurações salvas']);
}

// CLIENTES
function handleBuscarClientes() {
    requireAuth();
    
    $termo = sanitize(getParam('termo', ''));
    if (strlen($termo) < 2) {
        response(true, []);
    }
    
    $db = getDB();
    $stmt = $db->prepare("SELECT id, nome, telefone FROM clients WHERE nome LIKE ? OR telefone LIKE ? LIMIT 10");
    $like = "%" . $termo . "%";
    $stmt->bindValue(1, $like, SQLITE3_TEXT);
    $stmt->bindValue(2, $like, SQLITE3_TEXT);
    $result = $stmt->execute();
    
    $clientes = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $clientes[] = $row;
    }
    
    response(true, $clientes);
}

function handleHistoricoCliente() {
    requireAuth();
    
    $clienteId = intval(getParam('cliente_id', 0));
    $telefone = sanitize(getParam('telefone', ''));
    
    $db = getDB();
    
    // Buscar por ID ou telefone
    $where = '';
    if ($clienteId > 0) {
        $where = "client_id = " . $clienteId;
    } elseif (!empty($telefone)) {
        $telefone = preg_replace('/\D/', '', $telefone);
        $where = "cliente_telefone = '" . $db->escapeString($telefone) . "'";
    } else {
        response(true, ['pedidos' => [], 'total_gasto' => 0, 'total_pedidos' => 0]);
    }
    
    // Total de pedidos e valor
    $stmtTotal = $db->query("SELECT COUNT(*) as total, COALESCE(SUM(total), 0) as gasto FROM orders WHERE $where");
    $totais = $stmtTotal->fetchArray(SQLITE3_ASSOC);
    
    // Últimos 5 pedidos
    $result = $db->query("SELECT id, status, total, created_at, data_entrega FROM orders WHERE $where ORDER BY created_at DESC LIMIT 5");
    
    $pedidos = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $pedidos[] = $row;
    }
    
    response(true, [
        'pedidos' => $pedidos,
        'total_gasto' => intval($totais['gasto']),
        'total_pedidos' => intval($totais['total'])
    ]);
}

function handleExportarClientesCSV() {
    requireAuth();
    
    $db = getDB();
    $result = $db->query("SELECT nome, telefone FROM clients ORDER BY nome");
    
    $csv = "nome,telefone\n";
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $csv .= '"' . str_replace('"', '""', $row['nome']) . '","' . $row['telefone'] . "\"\n";
    }
    
    response(true, $csv);
}

function handleImportarClientesCSV() {
    requireAuth();
    
    $csv = getParam('csv', '');
    if (empty($csv)) {
        response(false, null, 'CSV vazio');
    }
    
    $lines = explode("\n", $csv);
    
    $db = getDB();
    $importados = 0;
    
    foreach ($lines as $i => $line) {
        if ($i === 0) continue; // Pular header
        
        $line = trim($line);
        if (empty($line)) continue;
        
        // Parse CSV simples
        preg_match_all('/"([^"]*)"|([^,]+)/', $line, $matches);
        $fields = [];
        for ($j = 0; $j < count($matches[0]); $j++) {
            $fields[] = $matches[1][$j] !== '' ? $matches[1][$j] : $matches[2][$j];
        }
        
        if (count($fields) >= 2) {
            $nome = trim($fields[0], '"');
            $telefone = preg_replace('/\D/', '', $fields[1]);
            
            if ($nome && $telefone) {
                try {
                    $stmt = $db->prepare("INSERT OR IGNORE INTO clients (nome, telefone) VALUES (?, ?)");
                    $stmt->bindValue(1, $nome, SQLITE3_TEXT);
                    $stmt->bindValue(2, $telefone, SQLITE3_TEXT);
                    $stmt->execute();
                    if ($db->changes() > 0) $importados++;
                } catch (Exception $e) {
                    // Ignorar erros de duplicata
                }
            }
        }
    }
    
    response(true, ['importados' => $importados]);
}

function handleExportarClientesJSON() {
    requireAuth();
    
    $db = getDB();
    
    $clientes = [];
    $result = $db->query("SELECT * FROM clients ORDER BY nome");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $clienteId = $row['id'];
        
        // Buscar pedidos do cliente
        $pedidos = [];
        $stmtPedidos = $db->prepare("SELECT * FROM orders WHERE client_id = ?");
        $stmtPedidos->bindValue(1, $clienteId, SQLITE3_INTEGER);
        $resPedidos = $stmtPedidos->execute();
        
        while ($pedido = $resPedidos->fetchArray(SQLITE3_ASSOC)) {
            // Buscar itens do pedido
            $itens = [];
            $stmtItens = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $stmtItens->bindValue(1, $pedido['id'], SQLITE3_INTEGER);
            $resItens = $stmtItens->execute();
            
            while ($item = $resItens->fetchArray(SQLITE3_ASSOC)) {
                $itens[] = $item;
            }
            
            $pedido['itens'] = $itens;
            $pedidos[] = $pedido;
        }
        
        $row['pedidos'] = $pedidos;
        $clientes[] = $row;
    }
    
    response(true, $clientes);
}

function handleImportarClientesJSON() {
    requireAuth();
    
    $dados = getParam('dados', []);
    if (!is_array($dados)) {
        response(false, null, 'Dados inválidos');
    }
    
    $db = getDB();
    $importados = 0;
    
    foreach ($dados as $cliente) {
        $telefone = preg_replace('/\D/', '', $cliente['telefone'] ?? '');
        $nome = $cliente['nome'] ?? '';
        
        if (!$nome || !$telefone) continue;
        
        // Verificar se existe
        $stmt = $db->prepare("SELECT id FROM clients WHERE telefone = ?");
        $stmt->bindValue(1, $telefone, SQLITE3_TEXT);
        $existing = $stmt->execute()->fetchArray();
        
        if ($existing) {
            $clienteId = $existing['id'];
        } else {
            $stmt = $db->prepare("INSERT INTO clients (nome, telefone, created_at) VALUES (?, ?, ?)");
            $stmt->bindValue(1, $nome, SQLITE3_TEXT);
            $stmt->bindValue(2, $telefone, SQLITE3_TEXT);
            $stmt->bindValue(3, $cliente['created_at'] ?? date('Y-m-d H:i:s'), SQLITE3_TEXT);
            $stmt->execute();
            $clienteId = $db->lastInsertRowID();
            $importados++;
        }
        
        // Importar pedidos se existirem
        if (!empty($cliente['pedidos']) && is_array($cliente['pedidos'])) {
            foreach ($cliente['pedidos'] as $pedido) {
                $stmt = $db->prepare("
                    INSERT INTO orders (client_id, cliente_nome, cliente_telefone, subtotal, desconto, 
                                       desconto_tipo, desconto_valor, total, adiantamento, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bindValue(1, $clienteId, SQLITE3_INTEGER);
                $stmt->bindValue(2, $pedido['cliente_nome'] ?? $nome, SQLITE3_TEXT);
                $stmt->bindValue(3, $pedido['cliente_telefone'] ?? $telefone, SQLITE3_TEXT);
                $stmt->bindValue(4, (int)($pedido['subtotal'] ?? 0), SQLITE3_INTEGER);
                $stmt->bindValue(5, (int)($pedido['desconto'] ?? 0), SQLITE3_INTEGER);
                $stmt->bindValue(6, $pedido['desconto_tipo'] ?? 'valor', SQLITE3_TEXT);
                $stmt->bindValue(7, (int)($pedido['desconto_valor'] ?? 0), SQLITE3_INTEGER);
                $stmt->bindValue(8, (int)($pedido['total'] ?? 0), SQLITE3_INTEGER);
                $stmt->bindValue(9, (int)($pedido['adiantamento'] ?? 0), SQLITE3_INTEGER);
                $stmt->bindValue(10, $pedido['status'] ?? 'pendente', SQLITE3_TEXT);
                $stmt->bindValue(11, $pedido['created_at'] ?? date('Y-m-d H:i:s'), SQLITE3_TEXT);
                $stmt->execute();
                $orderId = $db->lastInsertRowID();
                
                // Itens do pedido
                if (!empty($pedido['itens']) && is_array($pedido['itens'])) {
                    foreach ($pedido['itens'] as $item) {
                        $stmt = $db->prepare("
                            INSERT INTO order_items (order_id, nome, icone, preco, quantidade) 
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->bindValue(1, $orderId, SQLITE3_INTEGER);
                        $stmt->bindValue(2, $item['nome'] ?? '', SQLITE3_TEXT);
                        $stmt->bindValue(3, $item['icone'] ?? '', SQLITE3_TEXT);
                        $stmt->bindValue(4, (int)($item['preco'] ?? 0), SQLITE3_INTEGER);
                        $stmt->bindValue(5, (int)($item['quantidade'] ?? 1), SQLITE3_INTEGER);
                        $stmt->execute();
                    }
                }
            }
        }
    }
    
    response(true, ['importados' => $importados]);
}

// SERVIÇOS
function handleGetServicos() {
    requireAuth();
    
    $db = getDB();
    
    $servicos = [];
    $result = $db->query("SELECT * FROM services ORDER BY ordem, id");
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $serviceId = $row['id'];
        
        // Buscar subserviços
        $subs = [];
        $stmt = $db->prepare("SELECT * FROM subservices WHERE service_id = ? ORDER BY ordem, id");
        $stmt->bindValue(1, $serviceId, SQLITE3_INTEGER);
        $resSubs = $stmt->execute();
        
        while ($sub = $resSubs->fetchArray(SQLITE3_ASSOC)) {
            $subs[] = $sub;
        }
        
        $row['subservicos'] = $subs;
        $servicos[] = $row;
    }
    
    response(true, $servicos);
}

function handleCriarServico() {
    requireAuth();
    
    $nome = sanitize(getParam('nome'));
    if (!$nome) {
        response(false, null, 'Nome obrigatório');
    }
    
    $icone = sanitize(getParam('icone', ''));
    
    // Upload de ícone
    if (isset($_FILES['icone_file']) && $_FILES['icone_file']['error'] === UPLOAD_ERR_OK) {
        $icone = uploadIcon($_FILES['icone_file']);
    }
    
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO services (nome, icone) VALUES (?, ?)");
    $stmt->bindValue(1, $nome, SQLITE3_TEXT);
    $stmt->bindValue(2, $icone, SQLITE3_TEXT);
    $stmt->execute();
    
    response(true, ['id' => $db->lastInsertRowID()]);
}

function handleEditarServico() {
    requireAuth();
    
    $id = (int)getParam('id');
    $nome = sanitize(getParam('nome'));
    
    if (!$id || !$nome) {
        response(false, null, 'Dados inválidos');
    }
    
    $icone = sanitize(getParam('icone', ''));
    
    // Upload de ícone
    if (isset($_FILES['icone_file']) && $_FILES['icone_file']['error'] === UPLOAD_ERR_OK) {
        $icone = uploadIcon($_FILES['icone_file']);
    }
    
    $db = getDB();
    
    // Se não mudou ícone, manter o atual
    if (!$icone) {
        $stmt = $db->prepare("SELECT icone FROM services WHERE id = ?");
        $stmt->bindValue(1, $id, SQLITE3_INTEGER);
        $result = $stmt->execute()->fetchArray();
        $icone = $result ? $result['icone'] : '';
    }
    
    $stmt = $db->prepare("UPDATE services SET nome = ?, icone = ? WHERE id = ?");
    $stmt->bindValue(1, $nome, SQLITE3_TEXT);
    $stmt->bindValue(2, $icone, SQLITE3_TEXT);
    $stmt->bindValue(3, $id, SQLITE3_INTEGER);
    $stmt->execute();
    
    response(true, ['message' => 'Serviço atualizado']);
}

function handleExcluirServico() {
    requireAuth();
    
    $id = (int)getParam('id');
    
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM services WHERE id = ?");
    $stmt->bindValue(1, $id, SQLITE3_INTEGER);
    $stmt->execute();
    
    response(true, ['message' => 'Serviço excluído']);
}

function handleCriarSubservico() {
    requireAuth();
    
    $servicoId = (int)getParam('servico_id');
    $nome = sanitize(getParam('nome'));
    $preco = (int)getParam('preco');
    
    if (!$servicoId || !$nome) {
        response(false, null, 'Dados inválidos');
    }
    
    $icone = sanitize(getParam('icone', ''));
    
    // Upload de ícone
    if (isset($_FILES['icone_file']) && $_FILES['icone_file']['error'] === UPLOAD_ERR_OK) {
        $icone = uploadIcon($_FILES['icone_file']);
    }
    
    $db = getDB();
    
    // Se não tem ícone, usar o do serviço pai
    if (!$icone) {
        $stmt = $db->prepare("SELECT icone FROM services WHERE id = ?");
        $stmt->bindValue(1, $servicoId, SQLITE3_INTEGER);
        $result = $stmt->execute()->fetchArray();
        $icone = $result ? $result['icone'] : '';
    }
    
    $stmt = $db->prepare("INSERT INTO subservices (service_id, nome, preco, icone) VALUES (?, ?, ?, ?)");
    $stmt->bindValue(1, $servicoId, SQLITE3_INTEGER);
    $stmt->bindValue(2, $nome, SQLITE3_TEXT);
    $stmt->bindValue(3, $preco, SQLITE3_INTEGER);
    $stmt->bindValue(4, $icone, SQLITE3_TEXT);
    $stmt->execute();
    
    response(true, ['id' => $db->lastInsertRowID()]);
}

function handleEditarSubservico() {
    requireAuth();
    
    $id = (int)getParam('id');
    $nome = sanitize(getParam('nome'));
    $preco = (int)getParam('preco');
    
    if (!$id || !$nome) {
        response(false, null, 'Dados inválidos');
    }
    
    $icone = sanitize(getParam('icone', ''));
    
    // Upload de ícone
    if (isset($_FILES['icone_file']) && $_FILES['icone_file']['error'] === UPLOAD_ERR_OK) {
        $icone = uploadIcon($_FILES['icone_file']);
    }
    
    $db = getDB();
    
    // Se não mudou ícone, manter o atual
    if (!$icone) {
        $stmt = $db->prepare("SELECT icone FROM subservices WHERE id = ?");
        $stmt->bindValue(1, $id, SQLITE3_INTEGER);
        $result = $stmt->execute()->fetchArray();
        $icone = $result ? $result['icone'] : '';
    }
    
    $stmt = $db->prepare("UPDATE subservices SET nome = ?, preco = ?, icone = ? WHERE id = ?");
    $stmt->bindValue(1, $nome, SQLITE3_TEXT);
    $stmt->bindValue(2, $preco, SQLITE3_INTEGER);
    $stmt->bindValue(3, $icone, SQLITE3_TEXT);
    $stmt->bindValue(4, $id, SQLITE3_INTEGER);
    $stmt->execute();
    
    response(true, ['message' => 'Subserviço atualizado']);
}

function handleExcluirSubservico() {
    requireAuth();
    
    $id = (int)getParam('id');
    
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM subservices WHERE id = ?");
    $stmt->bindValue(1, $id, SQLITE3_INTEGER);
    $stmt->execute();
    
    response(true, ['message' => 'Subserviço excluído']);
}

function handleExportarServicos() {
    requireAuth();
    
    $db = getDB();
    
    $servicos = [];
    $result = $db->query("SELECT * FROM services ORDER BY ordem, id");
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $serviceId = $row['id'];
        
        $subs = [];
        $stmt = $db->prepare("SELECT * FROM subservices WHERE service_id = ? ORDER BY ordem, id");
        $stmt->bindValue(1, $serviceId, SQLITE3_INTEGER);
        $resSubs = $stmt->execute();
        
        while ($sub = $resSubs->fetchArray(SQLITE3_ASSOC)) {
            unset($sub['id'], $sub['service_id']);
            $subs[] = $sub;
        }
        
        unset($row['id']);
        $row['subservicos'] = $subs;
        $servicos[] = $row;
    }
    
    response(true, $servicos);
}

function handleImportarServicos() {
    requireAuth();
    validateCsrf();
    
    $dados = getParam('dados', []);
    if (!is_array($dados)) {
        response(false, null, 'Dados inválidos');
    }
    
    $db = getDB();
    
    // Usar transação para proteger contra falha parcial
    $db->exec('BEGIN TRANSACTION');
    try {
        $db->exec("DELETE FROM subservices");
        $db->exec("DELETE FROM services");
        
        $ordem = 0;
        foreach ($dados as $servico) {
            $stmt = $db->prepare("INSERT INTO services (nome, icone, ordem) VALUES (?, ?, ?)");
            $stmt->bindValue(1, $servico['nome'] ?? '', SQLITE3_TEXT);
            $stmt->bindValue(2, $servico['icone'] ?? '', SQLITE3_TEXT);
            $stmt->bindValue(3, $ordem++, SQLITE3_INTEGER);
            $stmt->execute();
            
            $serviceId = $db->lastInsertRowID();
            
            $subOrdem = 0;
            $subservicos = $servico['subservicos'] ?? [];
            if (is_array($subservicos)) {
                foreach ($subservicos as $sub) {
                    $stmt = $db->prepare("INSERT INTO subservices (service_id, nome, preco, icone, ordem) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bindValue(1, $serviceId, SQLITE3_INTEGER);
                    $stmt->bindValue(2, $sub['nome'] ?? '', SQLITE3_TEXT);
                    $stmt->bindValue(3, (int)($sub['preco'] ?? 0), SQLITE3_INTEGER);
                    $stmt->bindValue(4, $sub['icone'] ?? $servico['icone'] ?? '', SQLITE3_TEXT);
                    $stmt->bindValue(5, $subOrdem++, SQLITE3_INTEGER);
                    $stmt->execute();
                }
            }
        }
        $db->exec('COMMIT');
    } catch (Exception $e) {
        $db->exec('ROLLBACK');
        response(false, null, 'Erro ao importar: ' . $e->getMessage());
    }
    
    response(true, ['message' => 'Serviços importados']);
}

function handleResetarServicos() {
    requireAuth();
    
    $db = getDB();
    
    // Limpar serviços existentes
    $db->exec("DELETE FROM subservices");
    $db->exec("DELETE FROM services");
    
    // Popular com padrões
    popularServicoPadrao($db);
    
    response(true, ['message' => 'Serviços resetados para o padrão']);
}

// PEDIDOS
function handleCriarPedido() {
    requireAuth();
    validateCsrf();
    
    $clienteNome = sanitize(getParam('cliente_nome'));
    $clienteTelefone = preg_replace('/\D/', '', getParam('cliente_telefone') ?? '');
    $itens = getParam('itens', []);
    
    if (!$clienteNome || !$clienteTelefone || empty($itens) || !is_array($itens)) {
        response(false, null, 'Dados incompletos');
    }
    
    $db = getDB();
    $clienteId = upsertClient($db, $clienteNome, $clienteTelefone);
    
    $subtotal = (int)getParam('subtotal', 0);
    $desconto = (int)getParam('desconto', 0);
    $descontoTipo = getParam('desconto_tipo', 'valor');
    $descontoValor = (int)getParam('desconto_valor', 0);
    $total = (int)getParam('total', 0);
    $adiantamento = (int)getParam('adiantamento', 0);
    $status = sanitize(getParam('status', 'pendente'));
    $observacoes = sanitize(getParam('observacoes', ''));
    $dataPedido = getParam('data_pedido', date('Y-m-d'));
    $dataEntrega = getParam('data_entrega', '');
    
    $agora = date('Y-m-d H:i:s');
    
    $db->exec('BEGIN TRANSACTION');
    try {
        $stmt = $db->prepare("
            INSERT INTO orders (client_id, cliente_nome, cliente_telefone, subtotal, desconto, 
                               desconto_tipo, desconto_valor, total, adiantamento, observacoes, status, 
                               data_pedido, data_entrega, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bindValue(1, $clientId, SQLITE3_INTEGER);
        $stmt->bindValue(2, $clienteNome, SQLITE3_TEXT);
        $stmt->bindValue(3, $clienteTelefone, SQLITE3_TEXT);
        $stmt->bindValue(4, $subtotal, SQLITE3_INTEGER);
        $stmt->bindValue(5, $desconto, SQLITE3_INTEGER);
        $stmt->bindValue(6, $descontoTipo, SQLITE3_TEXT);
        $stmt->bindValue(7, $descontoValor, SQLITE3_INTEGER);
        $stmt->bindValue(8, $total, SQLITE3_INTEGER);
        $stmt->bindValue(9, $adiantamento, SQLITE3_INTEGER);
        $stmt->bindValue(10, $observacoes, SQLITE3_TEXT);
        $stmt->bindValue(11, $status, SQLITE3_TEXT);
        $stmt->bindValue(12, $dataPedido, SQLITE3_TEXT);
        $stmt->bindValue(13, $dataEntrega, SQLITE3_TEXT);
        $stmt->bindValue(14, $agora, SQLITE3_TEXT);
        $stmt->bindValue(15, $agora, SQLITE3_TEXT);
        $stmt->execute();
        
        $orderId = $db->lastInsertRowID();
        insertOrderItems($db, $orderId, $itens);
        
        $db->exec('COMMIT');
    } catch (Exception $e) {
        $db->exec('ROLLBACK');
        response(false, null, 'Erro ao criar pedido: ' . $e->getMessage());
    }
    
    response(true, ['id' => $orderId]);
}

function handleAtualizarPedido() {
    requireAuth();
    validateCsrf();
    
    $id = (int)getParam('id');
    if (!$id) {
        response(false, null, 'ID do pedido obrigatório');
    }
    
    $clienteNome = sanitize(getParam('cliente_nome'));
    $clienteTelefone = preg_replace('/\D/', '', getParam('cliente_telefone') ?? '');
    $itens = getParam('itens', []);
    
    if (!$clienteNome || !$clienteTelefone || empty($itens) || !is_array($itens)) {
        response(false, null, 'Dados incompletos');
    }
    
    $db = getDB();
    $clienteId = upsertClient($db, $clienteNome, $clienteTelefone);
    
    $subtotal = (int)getParam('subtotal', 0);
    $desconto = (int)getParam('desconto', 0);
    $descontoTipo = getParam('desconto_tipo', 'valor');
    $descontoValor = (int)getParam('desconto_valor', 0);
    $total = (int)getParam('total', 0);
    $adiantamento = (int)getParam('adiantamento', 0);
    $status = sanitize(getParam('status'));
    $observacoes = sanitize(getParam('observacoes', ''));
    $dataPedido = getParam('data_pedido', '');
    $dataEntrega = getParam('data_entrega', '');
    
    $agora = date('Y-m-d H:i:s');
    
    $db->exec('BEGIN TRANSACTION');
    try {
        $stmt = $db->prepare("
            UPDATE orders SET 
                client_id = ?, cliente_nome = ?, cliente_telefone = ?, 
                subtotal = ?, desconto = ?, desconto_tipo = ?, desconto_valor = ?, 
                total = ?, adiantamento = ?, status = ?, observacoes = ?,
                data_pedido = ?, data_entrega = ?, updated_at = ?
            WHERE id = ?
        ");
        $stmt->bindValue(1, $clientId, SQLITE3_INTEGER);
        $stmt->bindValue(2, $clienteNome, SQLITE3_TEXT);
        $stmt->bindValue(3, $clienteTelefone, SQLITE3_TEXT);
        $stmt->bindValue(4, $subtotal, SQLITE3_INTEGER);
        $stmt->bindValue(5, $desconto, SQLITE3_INTEGER);
        $stmt->bindValue(6, $descontoTipo, SQLITE3_TEXT);
        $stmt->bindValue(7, $descontoValor, SQLITE3_INTEGER);
        $stmt->bindValue(8, $total, SQLITE3_INTEGER);
        $stmt->bindValue(9, $adiantamento, SQLITE3_INTEGER);
        $stmt->bindValue(10, $status, SQLITE3_TEXT);
        $stmt->bindValue(11, $observacoes, SQLITE3_TEXT);
        $stmt->bindValue(12, $dataPedido, SQLITE3_TEXT);
        $stmt->bindValue(13, $dataEntrega, SQLITE3_TEXT);
        $stmt->bindValue(14, $agora, SQLITE3_TEXT);
        $stmt->bindValue(15, $id, SQLITE3_INTEGER);
        $stmt->execute();
        
        // Remover itens antigos e inserir novos
        $stmt = $db->prepare("DELETE FROM order_items WHERE order_id = ?");
        $stmt->bindValue(1, $id, SQLITE3_INTEGER);
        $stmt->execute();
        insertOrderItems($db, $id, $itens);
        
        $db->exec('COMMIT');
    } catch (Exception $e) {
        $db->exec('ROLLBACK');
        response(false, null, 'Erro ao atualizar pedido: ' . $e->getMessage());
    }
    
    response(true, ['id' => $id, 'message' => 'Pedido atualizado']);
}

function handleListarPedidos() {
    requireAuth();
    
    $dataInicio = getParam('dataInicio');
    $dataFim = getParam('dataFim');
    $status = sanitize(getParam('status', ''));
    $busca = sanitize(getParam('busca', ''));
    $pagina = max(1, (int)getParam('pagina', 1));
    $porPagina = max(1, min(100, (int)getParam('por_pagina', 50)));
    $offset = ($pagina - 1) * $porPagina;
    
    $db = getDB();
    
    $where = "1=1";
    $params = [];
    
    if ($dataInicio) {
        $where .= " AND DATE(created_at) >= ?";
        $params[] = $dataInicio;
    }
    if ($dataFim) {
        $where .= " AND DATE(created_at) <= ?";
        $params[] = $dataFim;
    }
    if ($status) {
        $where .= " AND status = ?";
        $params[] = $status;
    }
    if ($busca) {
        $where .= " AND (cliente_nome LIKE ? OR cliente_telefone LIKE ?)";
        $params[] = "%" . $busca . "%";
        $params[] = "%" . $busca . "%";
    }
    
    // Contar total para paginação
    $countSql = "SELECT COUNT(*) as total FROM orders WHERE $where";
    $countStmt = $db->prepare($countSql);
    foreach ($params as $i => $param) {
        $countStmt->bindValue($i + 1, $param, SQLITE3_TEXT);
    }
    $totalResult = $countStmt->execute()->fetchArray(SQLITE3_ASSOC);
    $totalRegistros = (int)($totalResult['total'] ?? 0);
    $totalPaginas = max(1, ceil($totalRegistros / $porPagina));
    
    // Buscar pedidos com paginação
    $sql = "SELECT * FROM orders WHERE $where ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $stmt = $db->prepare($sql);
    foreach ($params as $i => $param) {
        $stmt->bindValue($i + 1, $param, SQLITE3_TEXT);
    }
    $stmt->bindValue(count($params) + 1, $porPagina, SQLITE3_INTEGER);
    $stmt->bindValue(count($params) + 2, $offset, SQLITE3_INTEGER);
    
    $result = $stmt->execute();
    $pedidos = [];
    $orderIds = [];
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $orderIds[] = $row['id'];
        $pedidos[] = $row;
    }
    
    // Carregar todos os itens de uma vez (elimina N+1)
    $itensPorPedido = loadOrderItems($db, $orderIds);
    foreach ($pedidos as &$pedido) {
        $pedido['itens'] = $itensPorPedido[$pedido['id']] ?? [];
    }
    unset($pedido);
    
    response(true, [
        'pedidos' => $pedidos,
        'paginacao' => [
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'total_registros' => $totalRegistros,
            'total_paginas' => $totalPaginas
        ]
    ]);
}

function handleGetPedido() {
    requireAuth();
    
    $id = (int)getParam('id');
    
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->bindValue(1, $id, SQLITE3_INTEGER);
    $pedido = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    
    if (!$pedido) {
        response(false, null, 'Pedido não encontrado');
    }
    
    // Buscar itens
    $stmtItens = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $stmtItens->bindValue(1, $id, SQLITE3_INTEGER);
    $resItens = $stmtItens->execute();
    
    $itens = [];
    while ($item = $resItens->fetchArray(SQLITE3_ASSOC)) {
        $itens[] = $item;
    }
    
    $pedido['itens'] = $itens;
    
    response(true, $pedido);
}

function handleAlterarStatus() {
    requireAuth();
    
    $id = (int)getParam('id');
    $status = sanitize(getParam('status'));
    
    $statusValidos = ['pendente', 'processando', 'pronto', 'entregue', 'pago'];
    if (!in_array($status, $statusValidos)) {
        response(false, null, 'Status inválido');
    }
    
    $db = getDB();
    $agora = date('Y-m-d H:i:s'); // Horário local (America/Sao_Paulo)
    $stmt = $db->prepare("UPDATE orders SET status = ?, updated_at = ? WHERE id = ?");
    $stmt->bindValue(1, $status, SQLITE3_TEXT);
    $stmt->bindValue(2, $agora, SQLITE3_TEXT);
    $stmt->bindValue(3, $id, SQLITE3_INTEGER);
    $stmt->execute();
    
    response(true, ['message' => 'Status atualizado']);
}

function handleAlterarStatusMassa() {
    requireAuth();
    validateCsrf();
    
    $pedidos = getParam('pedidos', []);
    $status = sanitize(getParam('status'));
    
    if (empty($pedidos) || !is_array($pedidos) || !$status) {
        response(false, null, 'Dados inválidos');
    }
    
    $statusValidos = ['pendente', 'processando', 'pronto', 'entregue', 'pago'];
    if (!in_array($status, $statusValidos)) {
        response(false, null, 'Status inválido');
    }
    
    $db = getDB();
    $agora = date('Y-m-d H:i:s');
    
    // Transação para garantir atomicidade
    $db->exec('BEGIN TRANSACTION');
    try {
        foreach ($pedidos as $id) {
            $stmt = $db->prepare("UPDATE orders SET status = ?, updated_at = ? WHERE id = ?");
            $stmt->bindValue(1, $status, SQLITE3_TEXT);
            $stmt->bindValue(2, $agora, SQLITE3_TEXT);
            $stmt->bindValue(3, (int)$id, SQLITE3_INTEGER);
            $stmt->execute();
        }
        $db->exec('COMMIT');
    } catch (Exception $e) {
        $db->exec('ROLLBACK');
        response(false, null, 'Erro ao atualizar status: ' . $e->getMessage());
    }
    
    response(true, ['message' => 'Status atualizados']);
}

function handleExcluirPedido() {
    requireAdmin();
    
    $id = (int)getParam('id');
    
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM orders WHERE id = ?");
    $stmt->bindValue(1, $id, SQLITE3_INTEGER);
    $stmt->execute();
    
    response(true, ['message' => 'Pedido excluído']);
}

function handleExportarPedidos() {
    requireAuth();
    
    $db = getDB();
    
    $pedidos = [];
    $orderIds = [];
    $result = $db->query("SELECT * FROM orders ORDER BY created_at DESC");
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $orderIds[] = $row['id'];
        $pedidos[] = $row;
    }
    
    // Carregar todos os itens de uma vez (elimina N+1)
    $itensPorPedido = loadOrderItems($db, $orderIds);
    foreach ($pedidos as &$pedido) {
        $pedido['itens'] = $itensPorPedido[$pedido['id']] ?? [];
    }
    unset($pedido);
    
    response(true, $pedidos);
}

function handleImportarPedidos() {
    requireAuth();
    
    $dados = getParam('dados', []);
    if (!is_array($dados)) {
        response(false, null, 'Dados inválidos');
    }
    
    $db = getDB();
    $importados = 0;
    
    foreach ($dados as $pedido) {
        // Verificar/criar cliente
        $telefone = preg_replace('/\D/', '', $pedido['cliente_telefone'] ?? '');
        
        if (empty($telefone)) continue;
        
        $stmt = $db->prepare("SELECT id FROM clients WHERE telefone = ?");
        $stmt->bindValue(1, $telefone, SQLITE3_TEXT);
        $result = $stmt->execute()->fetchArray();
        
        if ($result) {
            $clienteId = $result['id'];
        } else {
            $stmt = $db->prepare("INSERT INTO clients (nome, telefone) VALUES (?, ?)");
            $stmt->bindValue(1, $pedido['cliente_nome'] ?? 'Importado', SQLITE3_TEXT);
            $stmt->bindValue(2, $telefone, SQLITE3_TEXT);
            $stmt->execute();
            $clienteId = $db->lastInsertRowID();
        }
        
        // Criar pedido
        $stmt = $db->prepare("
            INSERT INTO orders (client_id, cliente_nome, cliente_telefone, subtotal, desconto, 
                               desconto_tipo, desconto_valor, total, adiantamento, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bindValue(1, $clienteId, SQLITE3_INTEGER);
        $stmt->bindValue(2, $pedido['cliente_nome'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(3, $telefone, SQLITE3_TEXT);
        $stmt->bindValue(4, (int)($pedido['subtotal'] ?? 0), SQLITE3_INTEGER);
        $stmt->bindValue(5, (int)($pedido['desconto'] ?? 0), SQLITE3_INTEGER);
        $stmt->bindValue(6, $pedido['desconto_tipo'] ?? 'valor', SQLITE3_TEXT);
        $stmt->bindValue(7, (int)($pedido['desconto_valor'] ?? 0), SQLITE3_INTEGER);
        $stmt->bindValue(8, (int)($pedido['total'] ?? 0), SQLITE3_INTEGER);
        $stmt->bindValue(9, (int)($pedido['adiantamento'] ?? 0), SQLITE3_INTEGER);
        $stmt->bindValue(10, $pedido['status'] ?? 'pendente', SQLITE3_TEXT);
        $stmt->bindValue(11, $pedido['created_at'] ?? date('Y-m-d H:i:s'), SQLITE3_TEXT);
        $stmt->execute();
        
        $orderId = $db->lastInsertRowID();
        
        // Itens
        $itensData = $pedido['itens'] ?? [];
        if (is_array($itensData)) {
            foreach ($itensData as $item) {
                $stmt = $db->prepare("
                    INSERT INTO order_items (order_id, nome, icone, preco, quantidade) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->bindValue(1, $orderId, SQLITE3_INTEGER);
                $stmt->bindValue(2, $item['nome'] ?? '', SQLITE3_TEXT);
                $stmt->bindValue(3, $item['icone'] ?? '', SQLITE3_TEXT);
                $stmt->bindValue(4, (int)($item['preco'] ?? 0), SQLITE3_INTEGER);
                $stmt->bindValue(5, (int)($item['quantidade'] ?? 1), SQLITE3_INTEGER);
                $stmt->execute();
            }
        }
        
        $importados++;
    }
    
    response(true, ['importados' => $importados]);
}

// DASHBOARD
function handleGetDashboard() {
    requireAdmin();
    
    $periodoParam = getParam('dias', '30');
    
    $db = getDB();
    
    // Tratar período "mes" (Este mês)
    if ($periodoParam === 'mes') {
        $dataInicio = date('Y-m-01'); // Primeiro dia do mês atual
        $dias = (int)date('d'); // Dias passados no mês
        $dataAnteriorInicio = date('Y-m-01', strtotime('-1 month')); // Primeiro dia do mês anterior
    } else {
        $dias = (int)$periodoParam;
        if ($dias < 1) $dias = 30;
        $dataInicio = date('Y-m-d', strtotime("-{$dias} days"));
        $dataAnteriorInicio = date('Y-m-d', strtotime("-" . ($dias * 2) . " days"));
    }
    
    // Período atual
    $stmt = $db->prepare("SELECT 
        SUM(CASE WHEN status = 'pago' THEN total ELSE 0 END) as receitas_pagas,
        SUM(CASE WHEN status != 'pago' THEN total - adiantamento ELSE 0 END) as recebiveis,
        COUNT(*) as total_pedidos,
        SUM(total) as faturamento_total,
        SUM(desconto) as total_descontos,
        SUM(adiantamento) as total_adiantamentos,
        SUM(CASE WHEN status = 'pago' THEN 1 ELSE 0 END) as pedidos_pagos,
        AVG(total) as ticket_medio
        FROM orders WHERE (created_at >= ? OR DATE(created_at) >= ?)");
    $stmt->bindValue(1, $dataInicio, SQLITE3_TEXT);
    $stmt->bindValue(2, $dataInicio, SQLITE3_TEXT);
    $atual = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    
    // Período anterior
    $stmt = $db->prepare("SELECT 
        SUM(total) as faturamento_total,
        COUNT(*) as total_pedidos
        FROM orders WHERE (created_at >= ? OR DATE(created_at) >= ?) AND (created_at < ? OR DATE(created_at) < ?)");
    $stmt->bindValue(1, $dataAnteriorInicio, SQLITE3_TEXT);
    $stmt->bindValue(2, $dataAnteriorInicio, SQLITE3_TEXT);
    $stmt->bindValue(3, $dataInicio, SQLITE3_TEXT);
    $stmt->bindValue(4, $dataInicio, SQLITE3_TEXT);
    $anterior = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    
    // Comparações de Faturamento (Total de pedidos, mesmo não pagos)
    $faturamentoCompare = 0;
    $anteriorFaturamento = (float)($anterior['faturamento_total'] ?? 0);
    $atualFaturamento = (float)($atual['faturamento_total'] ?? 0);
    if ($anteriorFaturamento > 0) {
        $faturamentoCompare = (($atualFaturamento - $anteriorFaturamento) / $anteriorFaturamento) * 100;
    }
    
    $pedidosCompare = 0;
    $anteriorPedidos = (int)($anterior['total_pedidos'] ?? 0);
    $atualPedidos = (int)($atual['total_pedidos'] ?? 0);
    if ($anteriorPedidos > 0) {
        $pedidosCompare = (($atualPedidos - $anteriorPedidos) / $anteriorPedidos) * 100;
    }
    
    // Média de itens por pedido
    $stmt = $db->prepare("
        SELECT AVG(cnt) as media FROM (
            SELECT COUNT(*) as cnt FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            WHERE (o.created_at >= ? OR DATE(o.created_at) >= ?)
            GROUP BY o.id
        )
    ");
    $stmt->bindValue(1, $dataInicio, SQLITE3_TEXT);
    $stmt->bindValue(2, $dataInicio, SQLITE3_TEXT);
    $mediaResult = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    $mediaItens = (float)($mediaResult['media'] ?? 0);
    
    // Taxa de pagamento
    $totalPedidosAtual = (int)($atual['total_pedidos'] ?? 0);
    $pedidosPagos = (int)($atual['pedidos_pagos'] ?? 0);
    $taxaPagamento = $totalPedidosAtual > 0 ? ($pedidosPagos / $totalPedidosAtual) * 100 : 0;
    
    // Novos clientes
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM clients WHERE DATE(created_at) >= ?");
    $stmt->bindValue(1, $dataInicio, SQLITE3_TEXT);
    $novosResult = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    $novosClientes = (int)($novosResult['cnt'] ?? 0);
    
    // Top serviços
    $topServicos = [];
    $stmt = $db->prepare("
        SELECT oi.nome, SUM(oi.quantidade) as vendas
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        WHERE (o.created_at >= ? OR DATE(o.created_at) >= ?)
        GROUP BY oi.nome
        ORDER BY vendas DESC
        LIMIT 5
    ");
    $stmt->bindValue(1, $dataInicio, SQLITE3_TEXT);
    $stmt->bindValue(2, $dataInicio, SQLITE3_TEXT);
    $result = $stmt->execute();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $topServicos[] = $row;
    }
    
    // Top clientes por frequência
    $topClientesFreq = [];
    $stmt = $db->prepare("
        SELECT cliente_nome as nome, COUNT(*) as pedidos
        FROM orders
        WHERE (created_at >= ? OR DATE(created_at) >= ?)
        GROUP BY cliente_telefone
        ORDER BY pedidos DESC
        LIMIT 5
    ");
    $stmt->bindValue(1, $dataInicio, SQLITE3_TEXT);
    $result = $stmt->execute();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $topClientesFreq[] = $row;
    }
    
    // Top clientes por faturamento
    $topClientesFat = [];
    $stmt = $db->prepare("
        SELECT cliente_nome as nome, SUM(total) as total
        FROM orders
        WHERE (created_at >= ? OR DATE(created_at) >= ?)
        GROUP BY cliente_telefone
        ORDER BY total DESC
        LIMIT 5
    ");
    $stmt->bindValue(1, $dataInicio, SQLITE3_TEXT);
    $result = $stmt->execute();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $topClientesFat[] = $row;
    }
    
    // Por status
    $porStatus = [];
    $stmt = $db->prepare("
        SELECT status, COUNT(*) as quantidade
        FROM orders
        WHERE (created_at >= ? OR DATE(created_at) >= ?)
        GROUP BY status
    ");
    $stmt->bindValue(1, $dataInicio, SQLITE3_TEXT);
    $stmt->bindValue(2, $dataInicio, SQLITE3_TEXT);
    $result = $stmt->execute();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $porStatus[] = $row;
    }
    
    // Por dia da semana (0=domingo)
    $porDiaSemana = [0, 0, 0, 0, 0, 0, 0];
    $stmt = $db->prepare("
        SELECT strftime('%w', created_at) as dia, COUNT(*) as cnt
        FROM orders
        WHERE (created_at >= ? OR DATE(created_at) >= ?)
        GROUP BY dia
    ");
    $stmt->bindValue(1, $dataInicio, SQLITE3_TEXT);
    $stmt->bindValue(2, $dataInicio, SQLITE3_TEXT);
    $result = $stmt->execute();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $porDiaSemana[(int)$row['dia']] = (int)$row['cnt'];
    }
    
    // Por hora
    $porHora = [];
    $stmt = $db->prepare("
        SELECT strftime('%H', created_at) as hora, COUNT(*) as quantidade
        FROM orders
        WHERE (created_at >= ? OR DATE(created_at) >= ?)
        GROUP BY hora
        ORDER BY hora
    ");
    $stmt->bindValue(1, $dataInicio, SQLITE3_TEXT);
    $stmt->bindValue(2, $dataInicio, SQLITE3_TEXT);
    $result = $stmt->execute();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $porHora[] = ['hora' => (int)$row['hora'], 'quantidade' => (int)$row['quantidade']];
    }
    
    // Receita por dia
    $receitaPorDia = [];
    $stmt = $db->prepare("
        SELECT DATE(created_at) as data, SUM(total) as valor
        FROM orders
        WHERE (created_at >= ? OR DATE(created_at) >= ?)
        GROUP BY DATE(created_at)
        ORDER BY data
    ");
    $stmt->bindValue(1, $dataInicio, SQLITE3_TEXT);
    $stmt->bindValue(2, $dataInicio, SQLITE3_TEXT);
    $result = $stmt->execute();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        // Converter data para formato brasileiro dd/mm
        $dataFormatada = date('d/m', strtotime($row['data']));
        $receitaPorDia[] = [
            'data' => $dataFormatada,
            'valor' => (int)$row['valor']
        ];
    }
    
    // Insights
    $insights = [];
    
    if ($faturamentoCompare > 0) {
        $insights[] = ['icon' => '📈', 'text' => 'Faturamento cresceu ' . number_format($faturamentoCompare, 1) . '% vs período anterior'];
    } elseif ($faturamentoCompare < 0) {
        $insights[] = ['icon' => '📉', 'text' => 'Faturamento caiu ' . number_format(abs($faturamentoCompare), 1) . '% vs período anterior'];
    }
    
    // Melhor dia
    $diasNomes = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
    $maxPedidos = max($porDiaSemana);
    if ($maxPedidos > 0) {
        $melhorDia = array_search($maxPedidos, $porDiaSemana);
        $insights[] = ['icon' => '📅', 'text' => 'Melhor dia: ' . $diasNomes[$melhorDia]];
    }
    
    // Horário de pico
    if (!empty($porHora)) {
        $porHoraSorted = $porHora;
        usort($porHoraSorted, function($a, $b) { return $b['quantidade'] - $a['quantidade']; });
        $horaPico = $porHoraSorted[0]['hora'];
        $insights[] = ['icon' => '🕐', 'text' => 'Horário de pico: ' . $horaPico . 'h'];
    }
    
    // Serviço campeão
    if (!empty($topServicos)) {
        $insights[] = ['icon' => '🏆', 'text' => 'Serviço campeão: ' . $topServicos[0]['nome']];
    }
    
    // Cliente destaque
    if (!empty($topClientesFat)) {
        $insights[] = ['icon' => '⭐', 'text' => 'Cliente destaque: ' . $topClientesFat[0]['nome']];
    }
    
    // Pedidos antigos
    $antigos = $db->querySingle("
        SELECT COUNT(*) FROM orders 
        WHERE status IN ('pendente', 'processando') 
        AND DATE(created_at) < DATE('now', '-7 days')
    ");
    if ($antigos > 0) {
        $insights[] = ['icon' => '⚠️', 'text' => $antigos . ' pedidos pendentes há mais de 7 dias'];
    }
    
    response(true, [
        'receitas_pagas' => (int)($atual['receitas_pagas'] ?? 0),
        'recebiveis' => (int)($atual['recebiveis'] ?? 0),
        'total_pedidos' => (int)($atual['total_pedidos'] ?? 0),
        'faturamento_total' => (int)($atual['faturamento_total'] ?? 0),
        'total_descontos' => (int)($atual['total_descontos'] ?? 0),
        'total_adiantamentos' => (int)($atual['total_adiantamentos'] ?? 0),
        'pedidos_pagos' => (int)($atual['pedidos_pagos'] ?? 0),
        'ticket_medio' => (int)($atual['ticket_medio'] ?? 0),
        'media_itens' => $mediaItens,
        'taxa_pagamento' => $taxaPagamento,
        'novos_clientes' => $novosClientes,
        'receitas_compare' => $faturamentoCompare,
        'pedidos_compare' => $pedidosCompare,
        'top_servicos' => $topServicos,
        'top_clientes_freq' => $topClientesFreq,
        'top_clientes_fat' => $topClientesFat,
        'por_status' => $porStatus,
        'por_dia_semana' => $porDiaSemana,
        'por_hora' => $porHora,
        'receita_por_dia' => $receitaPorDia,
        'insights' => $insights,
        'total_despesas' => (int)($db->querySingle("SELECT SUM(valor) FROM expenses WHERE (created_at >= '$dataInicio' OR DATE(created_at) >= '$dataInicio')") ?: 0)
    ]);
}

// ============================================
// DESPESAS
// ============================================

function handleGetExpenses() {
    requireAdmin();
    $db = getDB();
    
    $dataInicio = getParam('data_inicio', date('Y-m-01'));
    $dataFim = getParam('data_fim', date('Y-m-d'));
    $status = getParam('status', '');
    
    $query = "SELECT * FROM expenses WHERE (data_vencimento BETWEEN ? AND ? OR (data_vencimento IS NULL AND DATE(created_at) BETWEEN ? AND ?))";
    if ($status) {
        $query .= " AND status = '$status'";
    }
    $query .= " ORDER BY data_vencimento ASC, created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindValue(1, $dataInicio, SQLITE3_TEXT);
    $stmt->bindValue(2, $dataFim, SQLITE3_TEXT);
    $stmt->bindValue(3, $dataInicio, SQLITE3_TEXT);
    $stmt->bindValue(4, $dataFim, SQLITE3_TEXT);
    
    $result = $stmt->execute();
    $expenses = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $expenses[] = $row;
    }
    
    response(true, $expenses);
}

function handleSaveExpense() {
    requireAdmin();
    validateCsrf();
    
    $id = getParam('id');
    $descricao = getParam('descricao');
    $valor = (int)getParam('valor');
    $categoria = getParam('categoria');
    $status = getParam('status', 'pendente');
    $data_vencimento = getParam('data_vencimento');
    
    if (!$descricao || $valor <= 0) {
        response(false, null, 'Descrição e valor são obrigatórios');
    }
    
    $db = getDB();
    if ($id) {
        $stmt = $db->prepare("UPDATE expenses SET descricao = ?, valor = ?, categoria = ?, status = ?, data_vencimento = ? WHERE id = ?");
        $stmt->bindValue(6, (int)$id, SQLITE3_INTEGER);
    } else {
        $stmt = $db->prepare("INSERT INTO expenses (descricao, valor, categoria, status, data_vencimento) VALUES (?, ?, ?, ?, ?)");
    }
    
    $stmt->bindValue(1, $descricao, SQLITE3_TEXT);
    $stmt->bindValue(2, $valor, SQLITE3_INTEGER);
    $stmt->bindValue(3, $categoria, SQLITE3_TEXT);
    $stmt->bindValue(4, $status, SQLITE3_TEXT);
    $stmt->bindValue(5, $data_vencimento, SQLITE3_TEXT);
    
    $stmt->execute();
    response(true, ['id' => $id ?: $db->lastInsertRowID()]);
}

function handleDeleteExpense() {
    requireAdmin();
    validateCsrf();
    
    $id = (int)getParam('id');
    if (!$id) response(false, null, 'ID inválido');
    
    $db = getDB();
    $db->prepare("DELETE FROM expenses WHERE id = ?")->bindValue(1, $id, SQLITE3_INTEGER)->execute();
    response(true, ['message' => 'Despesa excluída']);
}

// BACKUP
function handleBackup() {
    requireAdmin();
    
    $db = getDB();
    
    $backup = [
        'version' => '1.0',
        'date' => date('Y-m-d H:i:s'),
        'settings' => [],
        'clients' => [],
        'services' => [],
        'orders' => []
    ];
    
    // Settings
    $settings = $db->querySingle("SELECT * FROM settings LIMIT 1", true);
    $backup['settings'] = $settings ?: [];
    
    // Clients
    $result = $db->query("SELECT * FROM clients");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $backup['clients'][] = $row;
    }
    
    // Services e subservices
    $result = $db->query("SELECT * FROM services ORDER BY ordem");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $subs = [];
        $stmt = $db->prepare("SELECT * FROM subservices WHERE service_id = ?");
        $stmt->bindValue(1, $row['id'], SQLITE3_INTEGER);
        $resSubs = $stmt->execute();
        while ($sub = $resSubs->fetchArray(SQLITE3_ASSOC)) {
            $subs[] = $sub;
        }
        $row['subservices'] = $subs;
        $backup['services'][] = $row;
    }
    
    // Orders e items
    $result = $db->query("SELECT * FROM orders");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $items = [];
        $stmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $stmt->bindValue(1, $row['id'], SQLITE3_INTEGER);
        $resItems = $stmt->execute();
        while ($item = $resItems->fetchArray(SQLITE3_ASSOC)) {
            $items[] = $item;
        }
        $row['items'] = $items;
        $backup['orders'][] = $row;
    }
    
    response(true, $backup);
}

function handleRestaurar() {
    requireAdmin();
    validateCsrf();
    
    $dados = getParam('dados', []);
    if (!is_array($dados) || empty($dados)) {
        response(false, null, 'Dados inválidos');
    }
    
    $db = getDB();
    
    // TRANSAÇÃO COMPLETA — se qualquer parte falhar, nada muda
    $db->exec('BEGIN TRANSACTION');
    try {
        // Limpar tabelas
        $db->exec("DELETE FROM order_items");
        $db->exec("DELETE FROM orders");
        $db->exec("DELETE FROM subservices");
        $db->exec("DELETE FROM services");
        $db->exec("DELETE FROM clients");
        
        // Restaurar settings
        if (!empty($dados['settings']) && is_array($dados['settings'])) {
            $s = $dados['settings'];
            $stmt = $db->prepare("UPDATE settings SET nome = ?, cnpj = ?, whatsapp = ?, pix = ? WHERE id = 1");
            $stmt->bindValue(1, $s['nome'] ?? '', SQLITE3_TEXT);
            $stmt->bindValue(2, $s['cnpj'] ?? '', SQLITE3_TEXT);
            $stmt->bindValue(3, $s['whatsapp'] ?? '', SQLITE3_TEXT);
            $stmt->bindValue(4, $s['pix'] ?? '', SQLITE3_TEXT);
            $stmt->execute();
        }
        
        $clientMap = [];
        $serviceMap = [];
        $subserviceMap = [];
        
        // Restaurar clients
        $clients = $dados['clients'] ?? [];
        if (is_array($clients)) {
            foreach ($clients as $c) {
                $stmt = $db->prepare("INSERT INTO clients (nome, telefone, created_at) VALUES (?, ?, ?)");
                $stmt->bindValue(1, $c['nome'] ?? '', SQLITE3_TEXT);
                $stmt->bindValue(2, $c['telefone'] ?? '', SQLITE3_TEXT);
                $stmt->bindValue(3, $c['created_at'] ?? date('Y-m-d H:i:s'), SQLITE3_TEXT);
                $stmt->execute();
                $clientMap[$c['id']] = $db->lastInsertRowID();
            }
        }
        
        // Restaurar services
        $services = $dados['services'] ?? [];
        if (is_array($services)) {
            foreach ($services as $s) {
                $stmt = $db->prepare("INSERT INTO services (nome, icone, ordem) VALUES (?, ?, ?)");
                $stmt->bindValue(1, $s['nome'] ?? '', SQLITE3_TEXT);
                $stmt->bindValue(2, $s['icone'] ?? '', SQLITE3_TEXT);
                $stmt->bindValue(3, (int)($s['ordem'] ?? 0), SQLITE3_INTEGER);
                $stmt->execute();
                $newServiceId = $db->lastInsertRowID();
                $serviceMap[$s['id']] = $newServiceId;
                
                $subservices = $s['subservices'] ?? [];
                if (is_array($subservices)) {
                    foreach ($subservices as $sub) {
                        $stmt = $db->prepare("INSERT INTO subservices (service_id, nome, preco, icone, ordem) VALUES (?, ?, ?, ?, ?)");
                        $stmt->bindValue(1, $newServiceId, SQLITE3_INTEGER);
                        $stmt->bindValue(2, $sub['nome'] ?? '', SQLITE3_TEXT);
                        $stmt->bindValue(3, (int)($sub['preco'] ?? 0), SQLITE3_INTEGER);
                        $stmt->bindValue(4, $sub['icone'] ?? '', SQLITE3_TEXT);
                        $stmt->bindValue(5, (int)($sub['ordem'] ?? 0), SQLITE3_INTEGER);
                        $stmt->execute();
                        $subserviceMap[$sub['id']] = $db->lastInsertRowID();
                    }
                }
            }
        }
        
        // Restaurar orders
        $orders = $dados['orders'] ?? [];
        if (is_array($orders)) {
            foreach ($orders as $o) {
                $newClientId = isset($o['client_id']) && isset($clientMap[$o['client_id']]) ? $clientMap[$o['client_id']] : null;
                
                $stmt = $db->prepare("
                    INSERT INTO orders (client_id, cliente_nome, cliente_telefone, subtotal, desconto, 
                                       desconto_tipo, desconto_valor, total, adiantamento, observacoes, status, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bindValue(1, $newClientId, SQLITE3_INTEGER);
                $stmt->bindValue(2, $o['cliente_nome'] ?? '', SQLITE3_TEXT);
                $stmt->bindValue(3, $o['cliente_telefone'] ?? '', SQLITE3_TEXT);
                $stmt->bindValue(4, (int)($o['subtotal'] ?? 0), SQLITE3_INTEGER);
                $stmt->bindValue(5, (int)($o['desconto'] ?? 0), SQLITE3_INTEGER);
                $stmt->bindValue(6, $o['desconto_tipo'] ?? 'valor', SQLITE3_TEXT);
                $stmt->bindValue(7, (int)($o['desconto_valor'] ?? 0), SQLITE3_INTEGER);
                $stmt->bindValue(8, (int)($o['total'] ?? 0), SQLITE3_INTEGER);
                $stmt->bindValue(9, (int)($o['adiantamento'] ?? 0), SQLITE3_INTEGER);
                $stmt->bindValue(10, $o['observacoes'] ?? '', SQLITE3_TEXT);
                $stmt->bindValue(11, $o['status'] ?? 'pendente', SQLITE3_TEXT);
                $stmt->bindValue(12, $o['created_at'] ?? date('Y-m-d H:i:s'), SQLITE3_TEXT);
                $stmt->bindValue(13, $o['updated_at'] ?? date('Y-m-d H:i:s'), SQLITE3_TEXT);
                $stmt->execute();
                $newOrderId = $db->lastInsertRowID();
                
                $items = $o['items'] ?? [];
                if (is_array($items)) {
                    foreach ($items as $item) {
                        $newSubId = isset($item['subservice_id']) && isset($subserviceMap[$item['subservice_id']]) 
                            ? $subserviceMap[$item['subservice_id']] : null;
                        
                        $stmt = $db->prepare("
                            INSERT INTO order_items (order_id, subservice_id, nome, icone, preco, quantidade) 
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->bindValue(1, $newOrderId, SQLITE3_INTEGER);
                        $stmt->bindValue(2, $newSubId, SQLITE3_INTEGER);
                        $stmt->bindValue(3, $item['nome'] ?? '', SQLITE3_TEXT);
                        $stmt->bindValue(4, $item['icone'] ?? '', SQLITE3_TEXT);
                        $stmt->bindValue(5, (int)($item['preco'] ?? 0), SQLITE3_INTEGER);
                        $stmt->bindValue(6, (int)($item['quantidade'] ?? 1), SQLITE3_INTEGER);
                        $stmt->execute();
                    }
                }
            }
        }
        
        $db->exec('COMMIT');
    } catch (Exception $e) {
        $db->exec('ROLLBACK');
        response(false, null, 'Erro ao restaurar backup: ' . $e->getMessage());
    }
    
    response(true, ['message' => 'Backup restaurado com sucesso']);
}

function handleRepararBanco() {
    requireAdmin();
    
    $db = getDB();
    
    // Verificar integridade
    $result = $db->querySingle("PRAGMA integrity_check");
    
    if ($result !== 'ok') {
        response(false, null, 'Banco corrompido: ' . $result);
    }
    
    // Vacuum para otimizar
    $db->exec("VACUUM");
    
    // Recriar índices
    $db->exec("REINDEX");

    // ---------------------------------------------------------
    // Limpeza de sub-serviços duplicados e ícones
    // ---------------------------------------------------------
    
    // 1. Primeiro, normalizar todos os nomes (Remover espaços extras)
    $db->exec("UPDATE services SET nome = trim(nome)");
    $db->exec("UPDATE subservices SET nome = trim(nome)");
    
    // 2. Buscar grupos de nomes duplicados (Ignorando maiúsculas/minúsculas)
    $result = $db->query("SELECT nome, COUNT(*) as qtd FROM services GROUP BY nome COLLATE NOCASE HAVING qtd > 1");
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $nome = $row['nome'];
        
        // 3. Buscar todos os IDs para este nome (Independente de caixa)
        $ids = [];
        $stmt = $db->prepare("SELECT id FROM services WHERE nome = ? COLLATE NOCASE ORDER BY (CASE WHEN length(icone) > 10 THEN 0 ELSE 1 END), id ASC");
        $stmt->bindValue(1, $nome, SQLITE3_TEXT);
        $resIds = $stmt->execute();
        
        while ($idRow = $resIds->fetchArray(SQLITE3_ASSOC)) {
            $ids[] = $idRow['id'];
        }
        
        if (count($ids) > 1) {
            $keepId = array_shift($ids); // O "vencedor" que será mantido
            
            // 4. Relinkar todos os subserviços dos duplicados para o vencedor
            foreach ($ids as $dupId) {
                $stmtRelink = $db->prepare("UPDATE subservices SET service_id = ? WHERE service_id = ?");
                $stmtRelink->bindValue(1, $keepId, SQLITE3_INTEGER);
                $stmtRelink->bindValue(2, $dupId, SQLITE3_INTEGER);
                $stmtRelink->execute();
                
                // 5. Deletar o serviço duplicado agora que está órfão
                $stmtDel = $db->prepare("DELETE FROM services WHERE id = ?");
                $stmtDel->bindValue(1, $dupId, SQLITE3_INTEGER);
                $stmtDel->execute();
            }
        }
    }
    
    // 5. Deduplicar subserviços dentro de cada serviço remanescente
    $servicesRes = $db->query("SELECT id, icone FROM services");
    while ($sRow = $servicesRes->fetchArray(SQLITE3_ASSOC)) {
        $sid = $sRow['id'];
        $sIcon = $sRow['icone'];
        
        // Buscar nomes duplicados dentro DESTE serviço
        $subNames = $db->query("SELECT nome, COUNT(*) as qtd FROM subservices WHERE service_id = $sid GROUP BY nome COLLATE NOCASE HAVING qtd > 1");
        while ($snRow = $subNames->fetchArray(SQLITE3_ASSOC)) {
            $sname = $snRow['nome'];
            
            // Buscar duplicados do mesmo item, priorizando o que tem ícone customizado se houver
            $subIds = [];
            $stmtSub = $db->prepare("SELECT id FROM subservices WHERE service_id = ? AND nome = ? COLLATE NOCASE ORDER BY (CASE WHEN length(icone) > 10 THEN 0 ELSE 1 END), id ASC");
            $stmtSub->bindValue(1, $sid, SQLITE3_INTEGER);
            $stmtSub->bindValue(2, $sname, SQLITE3_TEXT);
            $resSubIds = $stmtSub->execute();
            
            while ($subIdRow = $resSubIds->fetchArray(SQLITE3_ASSOC)) {
                $subIds[] = $subIdRow['id'];
            }
            
            if (count($subIds) > 1) {
                array_shift($subIds); // Manter o primeiro (melhor ícone ou mais antigo)
                $placeholders = implode(',', $subIds);
                $db->exec("DELETE FROM subservices WHERE id IN ($placeholders)");
            }
        }
        
        // 6. Sincronizar ícones: sub-serviços que usam ícone padrão (curto) devem seguir o ícone do pai
        // Mas apenas se o pai tiver um ícone personalizado (longo).
        // Se o pai é emoji, mantemos o emoji no filho também por coerência.
        $stmtUpdateIcon = $db->prepare("UPDATE subservices SET icone = ? WHERE service_id = ? AND length(icone) <= 10");
        $stmtUpdateIcon->bindValue(1, $sIcon, SQLITE3_TEXT);
        $stmtUpdateIcon->bindValue(2, $sid, SQLITE3_INTEGER);
        $stmtUpdateIcon->execute();
    }
    
    // Limpeza final de subserviços que ficaram sem pai (segurança)
    $db->exec("DELETE FROM subservices WHERE service_id NOT IN (SELECT id FROM services)");
    
    // Reordenar para evitar buracos
    $db->exec("UPDATE services SET ordem = id WHERE ordem = 0 OR ordem IS NULL");
    
    response(true, ['message' => 'Banco reparado com sucesso! Duplicados removidos e ícones sincronizados.']);
}

// ============================================
// ROTEADOR
// ============================================

$action = getParam('action', '');

try {
    switch ($action) {
        // Auth
        case 'auth_status': handleAuthStatus(); break;
        case 'login': handleLogin(); break;
        case 'logout': handleLogout(); break;
        case 'alterar_senha': handleAlterarSenha(); break;
        case 'definir_senha_config': handleDefinirSenhaConfig(); break;
        case 'verify_config_password': handleVerifyConfigPassword(); break;
        
        // Users
        case 'list_users': handleListUsers(); break;
        case 'save_user': handleSaveUser(); break;
        case 'delete_user': handleDeleteUser(); break;
        
        // Settings
        case 'get_settings': handleGetSettings(); break;
        case 'save_settings': handleSaveSettings(); break;
        
        // Clientes
        case 'buscar_clientes': handleBuscarClientes(); break;
        case 'historico_cliente': handleHistoricoCliente(); break;
        case 'exportar_clientes_csv': handleExportarClientesCSV(); break;
        case 'importar_clientes_csv': handleImportarClientesCSV(); break;
        case 'exportar_clientes_json': handleExportarClientesJSON(); break;
        case 'importar_clientes_json': handleImportarClientesJSON(); break;
        
        // Serviços
        case 'get_servicos': handleGetServicos(); break;
        case 'criar_servico': handleCriarServico(); break;
        case 'editar_servico': handleEditarServico(); break;
        case 'excluir_servico': handleExcluirServico(); break;
        case 'criar_subservico': handleCriarSubservico(); break;
        case 'editar_subservico': handleEditarSubservico(); break;
        case 'excluir_subservico': handleExcluirSubservico(); break;
        case 'exportar_servicos': handleExportarServicos(); break;
        case 'importar_servicos': handleImportarServicos(); break;
        case 'resetar_servicos': handleResetarServicos(); break;
        
        // Pedidos
        case 'criar_pedido': handleCriarPedido(); break;
        case 'atualizar_pedido': handleAtualizarPedido(); break;
        case 'listar_pedidos': handleListarPedidos(); break;
        case 'get_pedido': handleGetPedido(); break;
        case 'alterar_status': handleAlterarStatus(); break;
        case 'alterar_status_massa': handleAlterarStatusMassa(); break;
        case 'excluir_pedido': handleExcluirPedido(); break;
        case 'exportar_pedidos': handleExportarPedidos(); break;
        case 'importar_pedidos': handleImportarPedidos(); break;
        
        // Dashboard
        case 'get_dashboard': handleGetDashboard(); break;
        
        // Despesas
        case 'get_expenses': handleGetExpenses(); break;
        case 'save_expense': handleSaveExpense(); break;
        case 'delete_expense': handleDeleteExpense(); break;
        
        // Backup
        case 'backup': handleBackup(); break;
        case 'restaurar': handleRestaurar(); break;
        case 'reparar_banco': handleRepararBanco(); break;
        
        default:
            response(false, null, 'Ação não encontrada: ' . $action);
    }
} catch (Exception $e) {
    response(false, null, 'Erro: ' . $e->getMessage());
}
