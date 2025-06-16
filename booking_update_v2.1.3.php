<?php
/**
 * Script de mise à jour du module Booking vers la version 2.1.3
 * Corrige les erreurs de base de données et ajoute les nouvelles fonctionnalités
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once(dirname(__FILE__) . '/booking.php');

class BookingUpdater
{
    private $module;
    private $db;
    private $errors = array();
    private $successes = array();
    
    public function __construct($module)
    {
        $this->module = $module;
        $this->db = Db::getInstance();
    }
    
    /**
     * Exécuter la mise à jour complète
     */
    public function executeUpdate()
    {
        $this->logMessage('=== DÉBUT DE LA MISE À JOUR BOOKING v2.1.3 ===');
        
        try {
            // 1. Vérification de la structure de base de données
            $this->checkAndFixDatabaseStructure();
            
            // 2. Migration des données existantes
            $this->migrateExistingData();
            
            // 3. Création des nouveaux contrôleurs
            $this->createMissingControllers();
            
            // 4. Mise à jour des configurations
            $this->updateConfigurations();
            
            // 5. Optimisation de la base de données
            $this->optimizeDatabase();
            
            // 6. Vérification finale
            $this->finalChecks();
            
            $this->logMessage('=== MISE À JOUR TERMINÉE AVEC SUCCÈS ===');
            return true;
            
        } catch (Exception $e) {
            $this->logError('Erreur lors de la mise à jour: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Vérifier et corriger la structure de la base de données
     */
    private function checkAndFixDatabaseStructure()
    {
        $this->logMessage('1. Vérification de la structure de la base de données...');
        
        // Vérifier si la colonne 'active' existe dans booker_auth_reserved
        if (!$this->columnExists('booker_auth_reserved', 'active')) {
            $sql = 'ALTER TABLE `' . _DB_PREFIX_ . 'booker_auth_reserved` 
                    ADD COLUMN `active` TINYINT(1) DEFAULT 1 AFTER `date_upd`';
            
            if ($this->db->execute($sql)) {
                $this->logSuccess('Colonne "active" ajoutée à booker_auth_reserved');
            } else {
                $this->logError('Échec ajout colonne "active": ' . $this->db->getMsgError());
            }
        } else {
            $this->logMessage('Colonne "active" déjà présente');
        }
        
        // Ajouter les colonnes manquantes si nécessaire
        $this->addMissingColumns();
        
        // Créer les tables manquantes
        $this->createMissingTables();
        
        // Ajouter les index pour les performances
        $this->addPerformanceIndexes();
    }
    
    /**
     * Ajouter les colonnes manquantes
     */
    private function addMissingColumns()
    {
        $columns_to_add = array(
            'booker_auth_reserved' => array(
                'id_customer' => 'INT(11) DEFAULT NULL AFTER `id_booker`',
                'id_order' => 'INT(11) DEFAULT NULL AFTER `id_customer`',
                'booking_reference' => 'VARCHAR(50) DEFAULT NULL AFTER `id_reserved`',
                'date_start' => 'DATETIME DEFAULT NULL AFTER `date_reserved`',
                'date_end' => 'DATETIME DEFAULT NULL AFTER `date_start`'
            ),
            'booker' => array(
                'id_product' => 'INT(11) DEFAULT NULL AFTER `id_booker`',
                'auto_confirm' => 'TINYINT(1) DEFAULT 0 AFTER `active`',
                'require_deposit' => 'TINYINT(1) DEFAULT 0 AFTER `auto_confirm`',
                'deposit_amount' => 'DECIMAL(10,2) DEFAULT 0.00 AFTER `require_deposit`',
                'cancellation_hours' => 'INT(11) DEFAULT 24 AFTER `deposit_amount`'
            )
        );
        
        foreach ($columns_to_add as $table => $columns) {
            foreach ($columns as $column => $definition) {
                if (!$this->columnExists($table, $column)) {
                    $sql = 'ALTER TABLE `' . _DB_PREFIX_ . $table . '` ADD COLUMN `' . $column . '` ' . $definition;
                    
                    if ($this->db->execute($sql)) {
                        $this->logSuccess("Colonne $column ajoutée à $table");
                    } else {
                        $this->logError("Échec ajout colonne $column à $table: " . $this->db->getMsgError());
                    }
                }
            }
        }
    }
    
    /**
     * Créer les tables manquantes
     */
    private function createMissingTables()
    {
        $tables = array(
            'booker_product' => 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'booker_product` (
                `id_booker` INT(11) NOT NULL,
                `id_product` INT(11) NOT NULL,
                `sync_price` TINYINT(1) DEFAULT 1,
                `override_price` DECIMAL(10,2) DEFAULT NULL,
                `date_add` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id_booker`, `id_product`),
                KEY `idx_product` (`id_product`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8',
            
            'booker_reservation_order' => 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'booker_reservation_order` (
                `id_reservation` INT(11) NOT NULL,
                `id_order` INT(11) NOT NULL,
                `order_type` ENUM(\'booking\',\'deposit\') DEFAULT \'booking\',
                `amount` DECIMAL(10,2) NOT NULL,
                `date_add` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id_reservation`, `id_order`, `order_type`),
                KEY `idx_order` (`id_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8',
            
            'booking_activity_log' => 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'booking_activity_log` (
                `id_log` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_reservation` INT(10) UNSIGNED NULL,
                `id_booker` INT(10) UNSIGNED NULL,
                `action` VARCHAR(100) NOT NULL,
                `details` TEXT NULL,
                `id_employee` INT(10) UNSIGNED NULL,
                `date_add` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id_log`),
                INDEX `idx_reservation` (`id_reservation`),
                INDEX `idx_booker` (`id_booker`),
                INDEX `idx_action` (`action`),
                INDEX `idx_employee` (`id_employee`),
                INDEX `idx_date_add` (`date_add`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
        );
        
        foreach ($tables as $table_name => $sql) {
            if (!$this->tableExists($table_name)) {
                if ($this->db->execute($sql)) {
                    $this->logSuccess("Table $table_name créée");
                } else {
                    $this->logError("Échec création table $table_name: " . $this->db->getMsgError());
                }
            }
        }
    }
    
    /**
     * Ajouter les index pour les performances
     */
    private function addPerformanceIndexes()
    {
        $indexes = array(
            'booker_auth_reserved' => array(
                'idx_active' => '(`active`)',
                'idx_status_active' => '(`status`, `active`)',
                'idx_date_status' => '(`date_reserved`, `status`)',
                'idx_booking_reference' => '(`booking_reference`)',
                'idx_customer_order' => '(`id_customer`, `id_order`)'
            ),
            'booker' => array(
                'idx_product' => '(`id_product`)',
                'idx_active' => '(`active`)'
            )
        );
        
        foreach ($indexes as $table => $table_indexes) {
            foreach ($table_indexes as $index_name => $columns) {
                if (!$this->indexExists($table, $index_name)) {
                    $sql = 'ALTER TABLE `' . _DB_PREFIX_ . $table . '` ADD INDEX `' . $index_name . '` ' . $columns;
                    
                    if ($this->db->execute($sql)) {
                        $this->logSuccess("Index $index_name ajouté à $table");
                    } else {
                        $this->logError("Échec ajout index $index_name à $table: " . $this->db->getMsgError());
                    }
                }
            }
        }
    }
    
    /**
     * Migrer les données existantes
     */
    private function migrateExistingData()
    {
        $this->logMessage('2. Migration des données existantes...');
        
        // Activer toutes les réservations existantes
        $sql = 'UPDATE `' . _DB_PREFIX_ . 'booker_auth_reserved` SET `active` = 1 WHERE `active` IS NULL';
        $this->db->execute($sql);
        
        // Générer des références de réservation pour les réservations sans référence
        $sql = 'UPDATE `' . _DB_PREFIX_ . 'booker_auth_reserved` 
                SET `booking_reference` = CONCAT(\'BOOK-\', DATE_FORMAT(date_add, \'%Y%m\'), \'-\', id_reserved)
                WHERE `booking_reference` IS NULL OR `booking_reference` = \'\'';
        $this->db->execute($sql);
        
        // Convertir les heures en datetime pour date_start
        $sql = 'UPDATE `' . _DB_PREFIX_ . 'booker_auth_reserved` 
                SET `date_start` = CONCAT(date_reserved, \' \', LPAD(hour_from, 2, \'0\'), \':00:00\')
                WHERE `date_start` IS NULL';
        $this->db->execute($sql);
        
        // Calculer date_end basée sur date_start + 1 heure par défaut
        $sql = 'UPDATE `' . _DB_PREFIX_ . 'booker_auth_reserved` 
                SET `date_end` = DATE_ADD(date_start, INTERVAL 1 HOUR)
                WHERE `date_end` IS NULL AND date_start IS NOT NULL';
        $this->db->execute($sql);
        
        $this->logSuccess('Migration des données terminée');
    }
    
    /**
     * Créer les contrôleurs manquants
     */
    private function createMissingControllers()
    {
        $this->logMessage('3. Vérification des contrôleurs...');
        
        // Vérifier si AdminBookerStats existe dans les onglets
        $id_tab = Tab::getIdFromClassName('AdminBookerStats');
        
        if (!$id_tab) {
            $tab = new Tab();
            $tab->class_name = 'AdminBookerStats';
            $tab->module = $this->module->name;
            $tab->active = 1;
            $tab->id_parent = Tab::getIdFromClassName('AdminBooking');
            
            foreach (Language::getLanguages(false) as $lang) {
                $tab->name[$lang['id_lang']] = 'Statistiques';
            }
            
            if ($tab->add()) {
                $this->logSuccess('Onglet AdminBookerStats créé');
            } else {
                $this->logError('Échec création onglet AdminBookerStats');
            }
        }
    }
    
    /**
     * Mettre à jour les configurations
     */
    private function updateConfigurations()
    {
        $this->logMessage('4. Mise à jour des configurations...');
        
        $new_configs = array(
            'BOOKING_MODULE_VERSION' => '2.1.3',
            'BOOKING_LAST_UPDATE' => date('Y-m-d H:i:s'),
            'BOOKING_STRUCTURE_VERSION' => '2.1.3',
            'BOOKING_AUTO_CREATE_ORDERS' => '0',
            'BOOKING_ORDER_STATUS_PENDING' => Configuration::get('PS_OS_BANKWIRE'),
            'BOOKING_SEND_CONFIRMATION_EMAIL' => '1'
        );
        
        foreach ($new_configs as $key => $value) {
            Configuration::updateValue($key, $value);
        }
        
        $this->logSuccess('Configurations mises à jour');
    }
    
    /**
     * Optimiser la base de données
     */
    private function optimizeDatabase()
    {
        $this->logMessage('5. Optimisation de la base de données...');
        
        $tables = array(
            'booker',
            'booker_auth',
            'booker_auth_reserved',
            'booker_product',
            'booker_reservation_order',
            'booking_activity_log'
        );
        
        foreach ($tables as $table) {
            if ($this->tableExists($table)) {
                $this->db->execute('OPTIMIZE TABLE `' . _DB_PREFIX_ . $table . '`');
            }
        }
        
        $this->logSuccess('Base de données optimisée');
    }
    
    /**
     * Vérifications finales
     */
    private function finalChecks()
    {
        $this->logMessage('6. Vérifications finales...');
        
        // Vérifier que les tables principales existent
        $required_tables = array('booker', 'booker_auth', 'booker_auth_reserved');
        
        foreach ($required_tables as $table) {
            if (!$this->tableExists($table)) {
                throw new Exception("Table requise manquante: $table");
            }
        }
        
        // Vérifier que les colonnes critiques existent
        if (!$this->columnExists('booker_auth_reserved', 'active')) {
            throw new Exception("Colonne 'active' manquante dans booker_auth_reserved");
        }
        
        // Enregistrer la mise à jour dans les logs
        if ($this->tableExists('booking_activity_log')) {
            $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'booking_activity_log` 
                    (`action`, `details`, `date_add`) 
                    VALUES (\'module_update\', \'Mise à jour vers v2.1.3 terminée\', NOW())';
            $this->db->execute($sql);
        }
        
        $this->logSuccess('Vérifications finales réussies');
    }
    
    /**
     * Utilitaires de vérification
     */
    private function tableExists($table)
    {
        $sql = 'SHOW TABLES LIKE \'' . _DB_PREFIX_ . pSQL($table) . '\'';
        return (bool)$this->db->getValue($sql);
    }
    
    private function columnExists($table, $column)
    {
        $sql = 'SHOW COLUMNS FROM `' . _DB_PREFIX_ . pSQL($table) . '` LIKE \'' . pSQL($column) . '\'';
        return (bool)$this->db->getValue($sql);
    }
    
    private function indexExists($table, $index)
    {
        $sql = 'SHOW INDEX FROM `' . _DB_PREFIX_ . pSQL($table) . '` WHERE Key_name = \'' . pSQL($index) . '\'';
        return (bool)$this->db->getValue($sql);
    }
    
    /**
     * Système de logs
     */
    private function logMessage($message)
    {
        PrestaShopLogger::addLog('[BOOKING UPDATE] ' . $message, 1);
        echo "<div class='alert alert-info'>$message</div>\n";
    }
    
    private function logSuccess($message)
    {
        $this->successes[] = $message;
        PrestaShopLogger::addLog('[BOOKING UPDATE SUCCESS] ' . $message, 1);
        echo "<div class='alert alert-success'>✓ $message</div>\n";
    }
    
    private function logError($message)
    {
        $this->errors[] = $message;
        PrestaShopLogger::addLog('[BOOKING UPDATE ERROR] ' . $message, 3);
        echo "<div class='alert alert-danger'>✗ $message</div>\n";
    }
    
    /**
     * Obtenir le rapport de mise à jour
     */
    public function getUpdateReport()
    {
        return array(
            'successes' => $this->successes,
            'errors' => $this->errors,
            'success_count' => count($this->successes),
            'error_count' => count($this->errors)
        );
    }
}

// Si exécuté directement (pas via include)
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    
    // Vérifier les permissions
    if (!Tools::isSubmit('update_booking') || !Tools::getAdminTokenLite('AdminModules')) {
        die('Accès non autorisé');
    }
    
    echo '<div class="panel"><div class="panel-heading"><h3>Mise à jour du module Booking v2.1.3</h3></div><div class="panel-body">';
    
    try {
        $booking_module = new Booking();
        $updater = new BookingUpdater($booking_module);
        
        if ($updater->executeUpdate()) {
            echo '<div class="alert alert-success"><strong>Mise à jour terminée avec succès !</strong></div>';
        } else {
            echo '<div class="alert alert-danger"><strong>Erreurs lors de la mise à jour.</strong></div>';
        }
        
        $report = $updater->getUpdateReport();
        echo '<h4>Rapport de mise à jour:</h4>';
        echo '<p><strong>' . $report['success_count'] . '</strong> opérations réussies</p>';
        echo '<p><strong>' . $report['error_count'] . '</strong> erreurs</p>';
        
    } catch (Exception $e) {
        echo '<div class="alert alert-danger">Erreur fatale: ' . $e->getMessage() . '</div>';
    }
    
    echo '</div></div>';
}