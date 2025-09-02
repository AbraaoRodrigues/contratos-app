-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 02/09/2025 às 12:56
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `contratos_agudos`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `contratos`
--

CREATE TABLE `contratos` (
  `id` int(11) NOT NULL,
  `numero` varchar(50) DEFAULT NULL,
  `processo` varchar(50) DEFAULT NULL,
  `fornecedor` varchar(150) DEFAULT NULL,
  `data_inicio` date DEFAULT NULL,
  `data_fim` date DEFAULT NULL,
  `valor_total` decimal(12,2) DEFAULT NULL,
  `local_arquivo` enum('1Doc','Físico') DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `objeto` text DEFAULT NULL,
  `data_assinatura` date DEFAULT NULL,
  `prorrogavel` tinyint(1) DEFAULT 0,
  `responsavel` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `empenhos`
--

CREATE TABLE `empenhos` (
  `id` int(11) NOT NULL,
  `contrato_id` int(11) DEFAULT NULL,
  `numero_empenho` varchar(100) NOT NULL,
  `valor_empenhado` decimal(12,2) DEFAULT NULL,
  `data_empenho` date DEFAULT NULL,
  `data_fim_previsto` date DEFAULT NULL,
  `objeto` text NOT NULL,
  `fornecedor` varchar(255) NOT NULL,
  `observacoes` text NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `logs`
--

CREATE TABLE `logs` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `acao` varchar(255) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `criado_em` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `senha_hash` varchar(255) DEFAULT NULL,
  `nivel_acesso` enum('admin','usuario') DEFAULT 'usuario',
  `alertas_config` text DEFAULT NULL,
  `modo_escuro` tinyint(1) DEFAULT 0,
  `ultimo_login` datetime DEFAULT NULL,
  `criado_em` datetime DEFAULT current_timestamp(),
  `avatar` varchar(255) DEFAULT NULL,
  `status` enum('ativo','inativo') DEFAULT 'ativo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome`, `email`, `senha_hash`, `nivel_acesso`, `alertas_config`, `modo_escuro`, `ultimo_login`, `criado_em`, `avatar`, `status`) VALUES
(1, 'Administrador', 'abraao.rodrigues@agudos.sp.gov.br', '$2y$10$MMvzG9muPpE7a2LrSDnXOeBuWqIt1HIwA4H5ijDWqfYyIpSBkvej2', 'admin', '[120,90,75,60,45,30,20,10,5]', 1, '2025-09-01 10:03:30', '2025-08-27 14:26:50', 'avatar_1.jpeg', 'ativo'),
(2, 'Josiane', 'josiane.cardoso@agudos.sp.gov.br', '$2y$10$MGvzeMmCRA9y7bGwpwijsuG4jZK4vmDbLS9eRcErd6meBi7AMg3Om', '', NULL, 0, '2025-08-28 15:28:56', '2025-08-28 15:28:05', NULL, 'inativo');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `contratos`
--
ALTER TABLE `contratos`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `empenhos`
--
ALTER TABLE `empenhos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contrato_id` (`contrato_id`);

--
-- Índices de tabela `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `contratos`
--
ALTER TABLE `contratos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `empenhos`
--
ALTER TABLE `empenhos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `empenhos`
--
ALTER TABLE `empenhos`
  ADD CONSTRAINT `empenhos_ibfk_1` FOREIGN KEY (`contrato_id`) REFERENCES `contratos` (`id`);

--
-- Restrições para tabelas `logs`
--
ALTER TABLE `logs`
  ADD CONSTRAINT `logs_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
