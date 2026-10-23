-- =======================================================
-- Sistema Integrado de Gestão Comercial — SIGC (Lee Market)
-- Script de Criação do Banco de Dados e Carga Inicial
-- Compatível com MySQL 5.7+ / 8.0+ / MariaDB
-- =======================================================

CREATE DATABASE IF NOT EXISTS `sigc_market` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `sigc_market`;

-- Tabela de Usuários / Colaboradores
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `cpf` VARCHAR(14) NOT NULL UNIQUE,
  `nome_completo` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NULL,
  `telefone` VARCHAR(20) NULL,
  `senha` VARCHAR(255) NOT NULL,
  `perfil` ENUM('dono', 'gerente', 'operador') NOT NULL,
  `cargo` VARCHAR(100) NULL,
  `status` ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Fornecedores
CREATE TABLE IF NOT EXISTS `fornecedores` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `cnpj` VARCHAR(20) NOT NULL UNIQUE,
  `razao_social` VARCHAR(150) NOT NULL,
  `nome_fantasia` VARCHAR(150) NULL,
  `contato_nome` VARCHAR(100) NULL,
  `telefone` VARCHAR(20) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `endereco` TEXT NULL,
  `status` ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Categorias
CREATE TABLE IF NOT EXISTS `categorias` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(100) NOT NULL,
  `descricao` TEXT NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Produtos
CREATE TABLE IF NOT EXISTS `produtos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `codigo_barras` VARCHAR(50) NOT NULL UNIQUE,
  `nome` VARCHAR(150) NOT NULL,
  `categoria_id` INT NULL,
  `fornecedor_id` INT NULL,
  `preco_custo` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `preco_venda` DECIMAL(10,2) NOT NULL,
  `estoque_atual` INT NOT NULL DEFAULT 0,
  `estoque_minimo` INT NOT NULL DEFAULT 5,
  `unidade_medida` VARCHAR(10) NOT NULL DEFAULT 'UN',
  `status` ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_produtos_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_produtos_fornecedor` FOREIGN KEY (`fornecedor_id`) REFERENCES `fornecedores`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Movimentações de Estoque
CREATE TABLE IF NOT EXISTS `movimentacoes_estoque` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `produto_id` INT NOT NULL,
  `usuario_id` INT NOT NULL,
  `tipo` ENUM('entrada', 'saida', 'ajuste') NOT NULL,
  `quantidade` INT NOT NULL,
  `motivo` VARCHAR(150) NOT NULL,
  `observacao` TEXT NULL,
  `data_hora` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_mov_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mov_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Vendas
CREATE TABLE IF NOT EXISTS `vendas` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `codigo_venda` VARCHAR(30) NOT NULL UNIQUE,
  `usuario_id` INT NOT NULL,
  `cliente_nome` VARCHAR(100) NOT NULL DEFAULT 'Consumidor Final',
  `cliente_cpf` VARCHAR(14) NULL,
  `subtotal` DECIMAL(10,2) NOT NULL,
  `desconto` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total` DECIMAL(10,2) NOT NULL,
  `forma_pagamento` ENUM('dinheiro', 'cartao_credito', 'cartao_debito', 'pix', 'outro') NOT NULL,
  `valor_pago` DECIMAL(10,2) NOT NULL,
  `troco` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('concluida', 'cancelada') NOT NULL DEFAULT 'concluida',
  `data_venda` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_vendas_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Itens da Venda
CREATE TABLE IF NOT EXISTS `itens_venda` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `venda_id` INT NOT NULL,
  `produto_id` INT NOT NULL,
  `quantidade` INT NOT NULL,
  `preco_unitario` DECIMAL(10,2) NOT NULL,
  `subtotal` DECIMAL(10,2) NOT NULL,
  CONSTRAINT `fk_itens_venda` FOREIGN KEY (`venda_id`) REFERENCES `vendas`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_itens_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Fluxo de Caixa (Entradas e Saídas)
CREATE TABLE IF NOT EXISTS `fluxo_caixa` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `tipo` ENUM('entrada', 'saida') NOT NULL,
  `categoria` VARCHAR(50) NOT NULL,
  `descricao` VARCHAR(255) NOT NULL,
  `valor` DECIMAL(10,2) NOT NULL,
  `data_movimento` DATE NOT NULL,
  `forma_pagamento` VARCHAR(50) NULL,
  `referencia_venda_id` INT NULL,
  `usuario_id` INT NOT NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_caixa_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Auditoria (Logs de todas as ações no sistema)
CREATE TABLE IF NOT EXISTS `auditoria_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT NULL,
  `usuario_nome` VARCHAR(150) NOT NULL,
  `usuario_perfil` VARCHAR(50) NOT NULL,
  `setor` VARCHAR(50) NOT NULL,
  `acao` VARCHAR(100) NOT NULL,
  `detalhes` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- CARGA INICIAL DE DADOS (SEED)
-- Senha de todos os usuários iniciais: admin123, gerente123, caixa123 (hash bcrypt)
-- =======================================================

-- Usuários Iniciais
-- Dono: CPF: 111.111.111-11 | Senha: admin123
-- Gerente: CPF: 222.222.222-22 | Senha: gerente123
-- Operador: CPF: 333.333.333-33 | Senha: caixa123
INSERT INTO `usuarios` (`cpf`, `nome_completo`, `email`, `telefone`, `senha`, `perfil`, `cargo`, `status`) VALUES
('111.111.111-11', 'Rafael Dono Lee', 'dono@leemarket.com.br', '(11) 98765-4321', '$2y$10$qditzEYz6OE5BgaVKyaZOe7ZPCSJWU5BPV2SLOr998EwRJubSmnMG', 'dono', 'Proprietário Geral', 'ativo'),
('222.222.222-22', 'Carlos Gerente Silva', 'gerente@leemarket.com.br', '(11) 98888-2222', '$2y$10$HfrY1FQZO1MLk7fIVE.kiuKpsain8f.WH5PrwjMHXbM8CxQbnArTO', 'gerente', 'Gerente Operacional', 'ativo'),
('333.333.333-33', 'Maria Caixa Santos', 'caixa@leemarket.com.br', '(11) 97777-3333', '$2y$10$p0N9m1pfGkz95l1diROMM.2cGYihScknXZc/x7Wcq1fOEMihgwLSC', 'operador', 'Operadora de Caixa', 'ativo')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Categorias
INSERT INTO `categorias` (`id`, `nome`, `descricao`) VALUES
(1, 'Hortifrúti', 'Frutas, legumes e verduras frescas'),
(2, 'Laticínios e Frios', 'Queijos, leites, iogurtes e derivados'),
(3, 'Padaria e Confeitaria', 'Pães, bolos, tortas e biscoitos'),
(4, 'Bebidas', 'Refrigerantes, sucos, águas e energéticos'),
(5, 'Mercearia', 'Arroz, feijão, massas, óleos e temperos'),
(6, 'Higiene e Limpeza', 'Produtos de higiene pessoal e limpeza doméstica')
ON DUPLICATE KEY UPDATE `nome`=VALUES(`nome`);

-- Fornecedores
INSERT INTO `fornecedores` (`id`, `cnpj`, `razao_social`, `nome_fantasia`, `contato_nome`, `telefone`, `email`, `endereco`, `status`) VALUES
(1, '12.345.678/0001-90', 'Distribuidora Alimentos Brasil Ltda', 'Alimentos Brasil', 'Fernando Castro', '(11) 3456-7890', 'contato@alimentosbrasil.com.br', 'Av. das Indústrias, 1500 - São Paulo/SP', 'ativo'),
(2, '98.765.432/0001-10', 'Bebidas & Cia Distribuidora S.A.', 'Bebidas & Cia', 'Juliana Ramos', '(11) 2345-6789', 'vendas@bebidasecia.com.br', 'Rua Central de Logística, 450 - Guarulhos/SP', 'ativo'),
(3, '45.678.901/0001-23', 'Hortifrúti Vale Verde Comércio Ltda', 'Vale Verde', 'Marcos Aurélio', '(11) 4567-8901', 'comercial@valeverde.com.br', 'Rodovia dos Bandeirantes, Km 32 - Jundiaí/SP', 'ativo'),
(4, '56.789.012/0001-34', 'Limpeza Total Produtos Químicos Eireli', 'Limpeza Total', 'Renata Souza', '(11) 5678-9012', 'atendimento@limpezatotal.com.br', 'Rua das Flores, 88 - Osasco/SP', 'ativo')
ON DUPLICATE KEY UPDATE `razao_social`=VALUES(`razao_social`);

-- Produtos
INSERT INTO `produtos` (`id`, `codigo_barras`, `nome`, `categoria_id`, `fornecedor_id`, `preco_custo`, `preco_venda`, `estoque_atual`, `estoque_minimo`, `unidade_medida`, `status`) VALUES
(1, '7891000100101', 'Arroz Branco Tipo 1 5kg - Tio João', 5, 1, 19.50, 26.90, 45, 10, 'PCT', 'ativo'),
(2, '7891000100202', 'Feijão Carioca 1kg - Camil', 5, 1, 5.20, 7.89, 60, 15, 'PCT', 'ativo'),
(3, '7891000100303', 'Óleo de Soja 900ml - Soya', 5, 1, 4.30, 6.49, 80, 20, 'UN', 'ativo'),
(4, '7891000200104', 'Leite Integral 1L - Piracanjuba', 2, 1, 3.40, 4.89, 120, 25, 'UN', 'ativo'),
(5, '7891000200205', 'Queijo Mussarela Fatiado 200g - Scala', 2, 1, 7.50, 11.50, 35, 8, 'PCT', 'ativo'),
(6, '7891000300106', 'Refrigerante Coca-Cola 2L', 4, 2, 6.50, 9.99, 90, 20, 'UN', 'ativo'),
(7, '7891000300207', 'Suco de Laranja Integral 900ml - Prats', 4, 2, 7.90, 12.90, 25, 6, 'UN', 'ativo'),
(8, '7891000400108', 'Banana Prata (Kg)', 1, 3, 3.20, 5.99, 70, 15, 'KG', 'ativo'),
(9, '7891000400209', 'Maçã Fuji Nacional (Kg)', 1, 3, 4.80, 8.49, 50, 12, 'KG', 'ativo'),
(10, '7891000500110', 'Detergente Líquido Neutro 500ml - Ypê', 6, 4, 1.45, 2.69, 110, 25, 'UN', 'ativo'),
(11, '7891000500211', 'Sabão em Pó Lavagem Perfeita 1,6kg - Omo', 6, 4, 16.20, 23.90, 40, 10, 'CX', 'ativo'),
(12, '7891000600112', 'Pão Francês Tradicional (Kg)', 3, 1, 7.00, 14.50, 30, 10, 'KG', 'ativo')
ON DUPLICATE KEY UPDATE `nome`=VALUES(`nome`);

-- Movimentações Iniciais de Estoque
INSERT INTO `movimentacoes_estoque` (`produto_id`, `usuario_id`, `tipo`, `quantidade`, `motivo`, `observacao`, `data_hora`) VALUES
(1, 1, 'entrada', 50, 'Carga inicial de estoque', 'Implantação do sistema SIGC', NOW() - INTERVAL 5 DAY),
(4, 1, 'entrada', 150, 'Carga inicial de estoque', 'Implantação do sistema SIGC', NOW() - INTERVAL 5 DAY),
(6, 1, 'entrada', 100, 'Carga inicial de estoque', 'Implantação do sistema SIGC', NOW() - INTERVAL 5 DAY);

-- Fluxo de Caixa Inicial
INSERT INTO `fluxo_caixa` (`tipo`, `categoria`, `descricao`, `valor`, `data_movimento`, `forma_pagamento`, `referencia_venda_id`, `usuario_id`, `criado_em`) VALUES
('entrada', 'Aporte Inicial', 'Saldo de abertura de caixa operacional', 500.00, CURDATE() - INTERVAL 2 DAY, 'Dinheiro', NULL, 1, NOW() - INTERVAL 2 DAY),
('saida', 'Despesa Fornecedor', 'Pagamento Fatura Distribuidora Alimentos Brasil', 450.00, CURDATE() - INTERVAL 1 DAY, 'PIX', NULL, 1, NOW() - INTERVAL 1 DAY);

-- Logs de Auditoria Iniciais
INSERT INTO `auditoria_logs` (`usuario_id`, `usuario_nome`, `usuario_perfil`, `setor`, `acao`, `detalhes`, `ip_address`, `criado_em`) VALUES
(1, 'Rafael Dono Lee', 'dono', 'Autenticação', 'Inicialização do Sistema', 'Sistema Integrado de Gestão Comercial instalado e configurado.', '127.0.0.1', NOW() - INTERVAL 5 DAY),
(1, 'Rafael Dono Lee', 'dono', 'RH', 'Cadastro de Colaborador', 'Cadastro dos operadores e gerentes para o supermercado Lee Market.', '127.0.0.1', NOW() - INTERVAL 4 DAY),
(1, 'Rafael Dono Lee', 'dono', 'Produtos', 'Carga de Produtos', '12 produtos iniciais inseridos com controle de estoque e fornecedores.', '127.0.0.1', NOW() - INTERVAL 3 DAY);
