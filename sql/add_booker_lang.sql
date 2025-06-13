-- Ajout de la table de langues pour booker (optionnel - permet d'activer le multilangue si nécessaire)
CREATE TABLE IF NOT EXISTS `PREFIX_booker_lang` (
    `id_booker` int(11) NOT NULL,
    `id_lang` int(11) NOT NULL,
    `name` varchar(255) DEFAULT NULL,
    `description` text DEFAULT NULL,
    PRIMARY KEY (`id_booker`, `id_lang`),
    KEY `idx_lang` (`id_lang`)
);

-- Insérer les données existantes dans la table de langues pour tous les bookers existants
INSERT INTO `PREFIX_booker_lang` (`id_booker`, `id_lang`, `name`, `description`)
SELECT 
    b.id,
    1 as id_lang,
    b.name,
    b.description
FROM `PREFIX_booker` b
WHERE NOT EXISTS (
    SELECT 1 FROM `PREFIX_booker_lang` bl 
    WHERE bl.id_booker = b.id AND bl.id_lang = 1
);
