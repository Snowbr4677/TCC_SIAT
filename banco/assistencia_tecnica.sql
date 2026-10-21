CREATE DATABASE IF NOT EXISTS assistencia_tecnica;
USE assistencia_tecnica;

CREATE TABLE IF NOT EXISTS clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    telefone VARCHAR(20),
    email VARCHAR(100),
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS aparelhos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT,
    marca VARCHAR(50),
    modelo VARCHAR(50),
    imei VARCHAR(30),
    defeito TEXT,
    CONSTRAINT fk_aparelhos_clientes FOREIGN KEY (cliente_id)
        REFERENCES clientes(id)
        ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS ordens_servico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT,
    aparelho_id INT,
    status_os VARCHAR(50) DEFAULT 'Recebido',
    valor DECIMAL(10,2),
    data_entrada DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ordens_clientes FOREIGN KEY (cliente_id)
        REFERENCES clientes(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_ordens_aparelhos FOREIGN KEY (aparelho_id)
        REFERENCES aparelhos(id)
        ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS historico_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ordem_id INT NOT NULL,
    status VARCHAR(50) NOT NULL,
    descricao TEXT,
    data_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_historico_ordens FOREIGN KEY (ordem_id)
        REFERENCES ordens_servico(id)
        ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS pagamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ordem_id INT NOT NULL,
    cliente_id INT NOT NULL,
    valor_pago DECIMAL(10,2) NOT NULL,
    forma_pagamento VARCHAR(50) NOT NULL,
    data_pagamento DATETIME DEFAULT CURRENT_TIMESTAMP,
    status_pagamento VARCHAR(30) DEFAULT 'Pago',
    observacao TEXT,
    CONSTRAINT fk_pagamentos_ordem FOREIGN KEY (ordem_id)
        REFERENCES ordens_servico(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_pagamentos_cliente FOREIGN KEY (cliente_id)
        REFERENCES clientes(id)
        ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS despesas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    descricao VARCHAR(255) NOT NULL,
    categoria VARCHAR(100) NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    data_despesa DATE NOT NULL,
    observacao TEXT
);

CREATE TABLE IF NOT EXISTS produtos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) NULL,
    nome VARCHAR(255) NOT NULL,
    categoria VARCHAR(100) NOT NULL,
    quantidade INT NOT NULL DEFAULT 0,
    valor_custo DECIMAL(10,2) NOT NULL DEFAULT 0,
    valor_venda DECIMAL(10,2) NOT NULL DEFAULT 0,
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
    observacao TEXT NULL
);

CREATE TABLE IF NOT EXISTS vendas_marketplace (
    id INT AUTO_INCREMENT PRIMARY KEY,
    marketplace VARCHAR(100) NOT NULL,
    produto_id INT NOT NULL,
    quantidade INT NOT NULL DEFAULT 1,
    valor_venda DECIMAL(10,2) NOT NULL DEFAULT 0,
    data_venda DATETIME DEFAULT CURRENT_TIMESTAMP,
    cliente VARCHAR(150) NULL,
    observacao TEXT NULL,
    CONSTRAINT fk_venda_produto FOREIGN KEY (produto_id)
        REFERENCES produtos(id)
        ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS backup_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_arquivo VARCHAR(255) NOT NULL,
    data_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
    tipo VARCHAR(50) DEFAULT 'manual'
);

CREATE TABLE IF NOT EXISTS configuracoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(100) NOT NULL UNIQUE,
    valor TEXT NULL,
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    login VARCHAR(100) NULL,
    senha VARCHAR(255) NOT NULL,
    foto_perfil VARCHAR(255) NOT NULL DEFAULT 'default.svg',
    perfil VARCHAR(20) NOT NULL DEFAULT 'admin',
    status VARCHAR(20) NOT NULL DEFAULT 'ativo',
    criado_por INT NULL DEFAULT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO usuarios (nome, email, senha)
SELECT 'Administrador', 'admin@assistencia.local', '$2y$10$ljpFAK/NOr1jlyFDLOt3O.PeGP3iAGerKrGf6ToIfc1XTwZYdUGNu'
WHERE NOT EXISTS (
    SELECT 1 FROM usuarios WHERE email = 'admin@assistencia.local'
);

INSERT INTO usuarios (nome, email, senha, foto_perfil, perfil, status)
SELECT 'Funcionário', 'funcionario@assistencia.local', '$2y$10$XfPsbddlxk.iqYKAfBRrx.dzvFtG9/1NHREUS1Qq6HGPTtLQsVAG2', 'default.svg', 'funcionario', 'ativo'
WHERE NOT EXISTS (
    SELECT 1 FROM usuarios WHERE email = 'funcionario@assistencia.local'
);
