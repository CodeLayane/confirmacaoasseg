-- Execute no phpMyAdmin para atualizar o banco
-- Adicionar coluna slug na tabela eventos
ALTER TABLE `eventos` ADD COLUMN `slug` varchar(255) DEFAULT NULL AFTER `nome`;
UPDATE `eventos` SET `slug` = 'assego-combat' WHERE `id` = 1;

-- Adicionar coluna config_form para personalização do formulário
ALTER TABLE `eventos` ADD COLUMN `config_form` text DEFAULT NULL AFTER `campos_extras`;

-- Atualizar config do ASSEGO Combat
UPDATE `eventos` SET `config_form` = '{"titulo":"CADASTRE-SE PARA O ASSEGO COMBAT","subtitulo":"Participe das seletivas de Boxing e Jiu-Jitsu"}' WHERE `id` = 1;
