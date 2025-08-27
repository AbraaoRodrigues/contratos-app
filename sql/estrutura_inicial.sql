CREATE DATABASE IF NOT EXISTS contratos_agudos;
USE contratos_agudos;
CREATE TABLE usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100),
  email VARCHAR(100) UNIQUE,
  senha_hash VARCHAR(255),
  nivel_acesso ENUM('admin', 'usuario') DEFAULT 'usuario',
  alertas_config TEXT,
  modo_escuro BOOLEAN DEFAULT FALSE,
  ultimo_login DATETIME,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
);
INSERT INTO usuarios (nome, email, senha_hash, nivel_acesso)
VALUES (
    'Administrador',
    'admin@agudos.sp.gov.br',
    '$2y$10$5lCzTYwIxYGyN5oyEXAMPLE9PaX6TIaJpjSytM96puU8yRRYFZVFGa',
    'admin'
  );
-- senha: admin123 (depois altere)
CREATE TABLE contratos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  numero VARCHAR(50),
  processo VARCHAR(50),
  orgao VARCHAR(100),
  data_inicio DATE,
  data_fim DATE,
  valor_total DECIMAL(12, 2),
  local_arquivo ENUM('1Doc', 'Físico'),
  observacoes TEXT
);
CREATE TABLE empenhos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  contrato_id INT,
  valor_empenhado DECIMAL(12, 2),
  data_empenho DATE,
  data_fim_previsto DATE,
  FOREIGN KEY (contrato_id) REFERENCES contratos(id)
);
CREATE TABLE logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT,
  acao VARCHAR(255),
  ip VARCHAR(45),
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);
