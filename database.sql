SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `role` enum('admin','operador') NOT NULL DEFAULT 'operador',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `usuarios` (`nome`, `email`, `senha`, `role`) VALUES
('Administrador ASSEGO', 'admin', 'admin123', 'admin');

CREATE TABLE IF NOT EXISTS `eventos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL COMMENT 'URL amigável do evento',
  `descricao` text,
  `data_inicio` date DEFAULT NULL,
  `data_fim` date DEFAULT NULL,
  `local` varchar(255) DEFAULT NULL,
  `banner_base64` longtext,
  `cor_tema` varchar(7) DEFAULT '#1e40af',
  `campos_extras` text COMMENT 'JSON com campos personalizados',
  `config_form` text DEFAULT NULL COMMENT 'JSON com personalização do formulário',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `eventos` (`nome`, `slug`, `descricao`, `data_inicio`, `local`, `cor_tema`, `campos_extras`) VALUES
('ASSEGO Combat', 'assego-combat', 'Seletivas de Boxing e Jiu-Jitsu', '2026-04-15', 'Goiânia - GO', '#1e3a8a',
 '[{"nome":"idade","label":"Idade","tipo":"number","obrigatorio":true},{"nome":"cpf","label":"CPF","tipo":"text","obrigatorio":true},{"nome":"modalidade","label":"Modalidade","tipo":"select","opcoes":["Boxing","Jiu-Jitsu","Ambas"],"obrigatorio":true},{"nome":"peso","label":"Peso (kg)","tipo":"number","obrigatorio":false},{"nome":"faixa","label":"Faixa (Jiu-Jitsu)","tipo":"select","opcoes":["Branca","Azul","Roxa","Marrom","Preta","N/A"],"obrigatorio":false}]');

CREATE TABLE IF NOT EXISTS `usuario_eventos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `evento_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ue` (`usuario_id`, `evento_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `participantes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `evento_id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `whatsapp` varchar(20) DEFAULT NULL,
  `instagram` varchar(100) DEFAULT NULL,
  `endereco` varchar(255) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `estado` varchar(2) DEFAULT NULL,
  `cep` varchar(10) DEFAULT NULL,
  `observacoes` text,
  `campos_extras` text,
  `presente` tinyint(1) NOT NULL DEFAULT 0,
  `data_presenca` datetime DEFAULT NULL,
  `aprovado` tinyint(1) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `evento_id` (`evento_id`),
  KEY `idx_aprovado` (`aprovado`),
  KEY `idx_presente` (`presente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fotos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `participante_id` int(11) NOT NULL,
  `dados` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `participante_id` (`participante_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `materiais` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `participante_id` int(11) DEFAULT NULL,
  `tipo` varchar(50) DEFAULT NULL,
  `titulo` varchar(255) DEFAULT NULL,
  `descricao` text,
  `arquivo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `participante_id` (`participante_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
