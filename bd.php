<?php
/**
 * ===================================================================
 * Sistema Integrado de Gestão Comercial — SIGC (Lee Market)
 * Arquivo Central de Conexão com o Banco de Dados (bd.php)
 * ===================================================================
 * Implementa PDO para segurança total contra SQL Injection.
 * Tenta conexão primária via MySQL e, se o serviço MySQL não estiver ativo,
 * utiliza SQLite de forma transparente e automática para testes locais.
 */

// Configurações padrão do MySQL
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'sigc_market');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

$pdo = null;
$db_driver_name = 'mysql';

try {
    // 1. Tentar conectar ao servidor MySQL
    $dsnWithoutDb = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 2,
    ];

    $pdoInit = new PDO($dsnWithoutDb, DB_USER, DB_PASS, $options);
    
    // Garantir que a base de dados existe
    $pdoInit->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // Conectar à base de dados específica
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    $db_driver_name = 'mysql';

    // Inicializar tabelas se ainda não existirem
    sigc_init_mysql_schema($pdo);

} catch (Throwable $e) {
    // 2. Se o MySQL não estiver disponível, utilizar SQLite localmente para garantir funcionamento contínuo
    try {
        $sqlitePath = __DIR__ . '/sigc.sqlite';
        $pdo = new PDO("sqlite:" . $sqlitePath, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("PRAGMA foreign_keys = ON;");
        $db_driver_name = 'sqlite';

        sigc_init_sqlite_schema($pdo);
    } catch (Throwable $ex) {
        die("Erro crítico na conexão com o banco de dados: " . htmlspecialchars($ex->getMessage()));
    }
}

/**
 * Retorna a instância ativa do PDO
 */
function getDBConnection(): PDO {
    global $pdo;
    return $pdo;
}

/**
 * Retorna o driver em uso (mysql ou sqlite)
 */
function getDBDriver(): string {
    global $db_driver_name;
    return $db_driver_name;
}

/**
 * Inicializa as tabelas no MySQL se não existirem
 */
function sigc_init_mysql_schema(PDO $pdo): void {
    // Verificar se tabela usuarios já existe
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'usuarios'")->fetch();
    if ($tableCheck) {
        return; // Já inicializado
    }

    $sqlPath = __DIR__ . '/database.sql';
    if (file_exists($sqlPath)) {
        $sql = file_get_contents($sqlPath);
        // Executar comandos em lote
        $pdo->exec($sql);
    }
}

/**
 * Inicializa as tabelas no SQLite se não existirem
 */
function sigc_init_sqlite_schema(PDO $pdo): void {
    $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='usuarios'")->fetch();
    if ($check) {
        return; // Já inicializado
    }

    $schema = "
    CREATE TABLE IF NOT EXISTS usuarios (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      cpf TEXT NOT NULL UNIQUE,
      nome_completo TEXT NOT NULL,
      email TEXT,
      telefone TEXT,
      senha TEXT NOT NULL,
      perfil TEXT NOT NULL,
      cargo TEXT,
      status TEXT NOT NULL DEFAULT 'ativo',
      criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS fornecedores (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      cnpj TEXT NOT NULL UNIQUE,
      razao_social TEXT NOT NULL,
      nome_fantasia TEXT,
      contato_nome TEXT,
      telefone TEXT NOT NULL,
      email TEXT NOT NULL,
      endereco TEXT,
      status TEXT NOT NULL DEFAULT 'ativo',
      criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS categorias (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      nome TEXT NOT NULL,
      descricao TEXT,
      criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS produtos (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      codigo_barras TEXT NOT NULL UNIQUE,
      nome TEXT NOT NULL,
      categoria_id INTEGER,
      fornecedor_id INTEGER,
      preco_custo REAL NOT NULL DEFAULT 0.00,
      preco_venda REAL NOT NULL,
      estoque_atual INTEGER NOT NULL DEFAULT 0,
      estoque_minimo INTEGER NOT NULL DEFAULT 5,
      unidade_medida TEXT NOT NULL DEFAULT 'UN',
      status TEXT NOT NULL DEFAULT 'ativo',
      criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL,
      FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id) ON DELETE SET NULL
    );

    CREATE TABLE IF NOT EXISTS movimentacoes_estoque (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      produto_id INTEGER NOT NULL,
      usuario_id INTEGER NOT NULL,
      tipo TEXT NOT NULL,
      quantidade INTEGER NOT NULL,
      motivo TEXT NOT NULL,
      observacao TEXT,
      data_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE,
      FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS vendas (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      codigo_venda TEXT NOT NULL UNIQUE,
      usuario_id INTEGER NOT NULL,
      cliente_nome TEXT NOT NULL DEFAULT 'Consumidor Final',
      cliente_cpf TEXT,
      subtotal REAL NOT NULL,
      desconto REAL NOT NULL DEFAULT 0.00,
      total REAL NOT NULL,
      forma_pagamento TEXT NOT NULL,
      valor_pago REAL NOT NULL,
      troco REAL NOT NULL DEFAULT 0.00,
      status TEXT NOT NULL DEFAULT 'concluida',
      data_venda DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS itens_venda (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      venda_id INTEGER NOT NULL,
      produto_id INTEGER NOT NULL,
      quantidade INTEGER NOT NULL,
      preco_unitario REAL NOT NULL,
      subtotal REAL NOT NULL,
      FOREIGN KEY (venda_id) REFERENCES vendas(id) ON DELETE CASCADE,
      FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS fluxo_caixa (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      tipo TEXT NOT NULL,
      categoria TEXT NOT NULL,
      descricao TEXT NOT NULL,
      valor REAL NOT NULL,
      data_movimento DATE NOT NULL,
      forma_pagamento TEXT,
      referencia_venda_id INTEGER,
      usuario_id INTEGER NOT NULL,
      criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS auditoria_logs (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      usuario_id INTEGER,
      usuario_nome TEXT NOT NULL,
      usuario_perfil TEXT NOT NULL,
      setor TEXT NOT NULL,
      acao TEXT NOT NULL,
      detalhes TEXT,
      ip_address TEXT,
      criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    );
    ";

    $pdo->exec($schema);

    // Carga inicial (Seed)
    $stmtUsers = $pdo->prepare("INSERT OR IGNORE INTO usuarios (cpf, nome_completo, email, telefone, senha, perfil, cargo, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmtUsers->execute(['111.111.111-11', 'Rafael Dono Lee', 'dono@leemarket.com.br', '(11) 98765-4321', '$2y$10$qditzEYz6OE5BgaVKyaZOe7ZPCSJWU5BPV2SLOr998EwRJubSmnMG', 'dono', 'Proprietário Geral', 'ativo']);
    $stmtUsers->execute(['222.222.222-22', 'Carlos Gerente Silva', 'gerente@leemarket.com.br', '(11) 98888-2222', '$2y$10$HfrY1FQZO1MLk7fIVE.kiuKpsain8f.WH5PrwjMHXbM8CxQbnArTO', 'gerente', 'Gerente Operacional', 'ativo']);
    $stmtUsers->execute(['333.333.333-33', 'Maria Caixa Santos', 'caixa@leemarket.com.br', '(11) 97777-3333', '$2y$10$p0N9m1pfGkz95l1diROMM.2cGYihScknXZc/x7Wcq1fOEMihgwLSC', 'operador', 'Operadora de Caixa', 'ativo']);

    // Categorias
    $cats = [
        [1, 'Hortifrúti', 'Frutas, legumes e verduras frescas'],
        [2, 'Laticínios e Frios', 'Queijos, leites, iogurtes e derivados'],
        [3, 'Padaria e Confeitaria', 'Pães, bolos, tortas e biscoitos'],
        [4, 'Bebidas', 'Refrigerantes, sucos, águas e energéticos'],
        [5, 'Mercearia', 'Arroz, feijão, massas, óleos e temperos'],
        [6, 'Higiene e Limpeza', 'Produtos de higiene pessoal e limpeza doméstica']
    ];
    $stmtCat = $pdo->prepare("INSERT OR IGNORE INTO categorias (id, nome, descricao) VALUES (?, ?, ?)");
    foreach ($cats as $c) {
        $stmtCat->execute($c);
    }

    // Fornecedores
    $fornecedores = [
        [1, '12.345.678/0001-90', 'Distribuidora Alimentos Brasil Ltda', 'Alimentos Brasil', 'Fernando Castro', '(11) 3456-7890', 'contato@alimentosbrasil.com.br', 'Av. das Indústrias, 1500 - São Paulo/SP'],
        [2, '98.765.432/0001-10', 'Bebidas & Cia Distribuidora S.A.', 'Bebidas & Cia', 'Juliana Ramos', '(11) 2345-6789', 'vendas@bebidasecia.com.br', 'Rua Central de Logística, 450 - Guarulhos/SP'],
        [3, '45.678.901/0001-23', 'Hortifrúti Vale Verde Comércio Ltda', 'Vale Verde', 'Marcos Aurélio', '(11) 4567-8901', 'comercial@valeverde.com.br', 'Rodovia dos Bandeirantes, Km 32 - Jundiaí/SP'],
        [4, '56.789.012/0001-34', 'Limpeza Total Produtos Químicos Eireli', 'Limpeza Total', 'Renata Souza', '(11) 5678-9012', 'atendimento@limpezatotal.com.br', 'Rua das Flores, 88 - Osasco/SP']
    ];
    $stmtForn = $pdo->prepare("INSERT OR IGNORE INTO fornecedores (id, cnpj, razao_social, nome_fantasia, contato_nome, telefone, email, endereco) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($fornecedores as $f) {
        $stmtForn->execute($f);
    }

    // Produtos
    $prods = [
        [1, '7891000100101', 'Arroz Branco Tipo 1 5kg - Tio João', 5, 1, 19.50, 26.90, 45, 10, 'PCT'],
        [2, '7891000100202', 'Feijão Carioca 1kg - Camil', 5, 1, 5.20, 7.89, 60, 15, 'PCT'],
        [3, '7891000100303', 'Óleo de Soja 900ml - Soya', 5, 1, 4.30, 6.49, 80, 20, 'UN'],
        [4, '7891000200104', 'Leite Integral 1L - Piracanjuba', 2, 1, 3.40, 4.89, 120, 25, 'UN'],
        [5, '7891000200205', 'Queijo Mussarela Fatiado 200g - Scala', 2, 1, 7.50, 11.50, 35, 8, 'PCT'],
        [6, '7891000300106', 'Refrigerante Coca-Cola 2L', 4, 2, 6.50, 9.99, 90, 20, 'UN'],
        [7, '7891000300207', 'Suco de Laranja Integral 900ml - Prats', 4, 2, 7.90, 12.90, 25, 6, 'UN'],
        [8, '7891000400108', 'Banana Prata (Kg)', 1, 3, 3.20, 5.99, 70, 15, 'KG'],
        [9, '7891000400209', 'Maçã Fuji Nacional (Kg)', 1, 3, 4.80, 8.49, 50, 12, 'KG'],
        [10, '7891000500110', 'Detergente Líquido Neutro 500ml - Ypê', 6, 4, 1.45, 2.69, 110, 25, 'UN'],
        [11, '7891000500211', 'Sabão em Pó Lavagem Perfeita 1,6kg - Omo', 6, 4, 16.20, 23.90, 40, 10, 'CX'],
        [12, '7891000600112', 'Pão Francês Tradicional (Kg)', 3, 1, 7.00, 14.50, 30, 10, 'KG']
    ];
    $stmtProd = $pdo->prepare("INSERT OR IGNORE INTO produtos (id, codigo_barras, nome, categoria_id, fornecedor_id, preco_custo, preco_venda, estoque_atual, estoque_minimo, unidade_medida) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($prods as $p) {
        $stmtProd->execute($p);
    }

    // Fluxo de caixa inicial
    $pdo->exec("INSERT INTO fluxo_caixa (tipo, categoria, descricao, valor, data_movimento, forma_pagamento, usuario_id) VALUES 
        ('entrada', 'Aporte Inicial', 'Saldo de abertura de caixa operacional', 500.00, date('now', '-2 days'), 'Dinheiro', 1),
        ('saida', 'Despesa Fornecedor', 'Pagamento Fatura Distribuidora Alimentos Brasil', 450.00, date('now', '-1 day'), 'PIX', 1)
    ");

    // Auditoria inicial
    $pdo->exec("INSERT INTO auditoria_logs (usuario_id, usuario_nome, usuario_perfil, setor, acao, detalhes, ip_address) VALUES 
        (1, 'Rafael Dono Lee', 'dono', 'Autenticação', 'Inicialização do Sistema', 'Sistema SIGC instalado e configurado.', '127.0.0.1'),
        (1, 'Rafael Dono Lee', 'dono', 'RH', 'Cadastro de Colaborador', 'Colaboradores cadastrados: Dono, Gerente e Operador de Caixa.', '127.0.0.1'),
        (1, 'Rafael Dono Lee', 'dono', 'Produtos', 'Carga de Produtos', 'Produtos essenciais inseridos com controle de estoque e fornecedores.', '127.0.0.1')
    ");
}
