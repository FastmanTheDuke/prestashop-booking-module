<?php 
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once (dirname(__FILE__). '/classes/Booker.php');
require_once (dirname(__FILE__). '/classes/BookerAuth.php');
require_once (dirname(__FILE__). '/classes/BookerAuthReserved.php');
require_once (dirname(__FILE__). '/classes/StripeDepositManager.php');
require_once (dirname(__FILE__). '/classes/BookingNotificationSystem.php');

class Booking extends Module  {
    protected $token = "";
    static $base = _DB_NAME_;
    
    public function __construct()
    {
        $this->name = 'booking';
        $this->tab = 'others';
        $this->version = '2.1.5';
        $this->author = 'FastmanTheDuke';
        $this->bootstrap = true;
        $this->need_instance = 0;
        
        parent::__construct();

        $this->displayName = $this->l('Système de Réservations Avancé v2.1.5');
        $this->description = $this->l('Module complet de gestion de réservations avec cautions Stripe, empreinte CB, calendriers interactifs, statuts avancés et intégration e-commerce complète. Installation corrigée.');
        $this->confirmUninstall = $this->l('Êtes-vous sûr de vouloir désinstaller ce module ? Toutes les données de réservation et de caution seront perdues définitivement.');

        $this->ps_versions_compliancy = array('min' => '1.7.6', 'max' => _PS_VERSION_);
    }

    /**
     * Installation du module - VERSION 2.1.5 CORRIGÉE
     */
    public function install()
    {
        return parent::install() && 
               $this->installDB() && 
               $this->installTab() && 
               $this->installConfiguration() &&
               $this->registerHooks() &&
               $this->installOrderStates() &&
               $this->createDirectories();
    }

    /**
     * Désinstallation du module
     */
    public function uninstall()
    {
        return parent::uninstall() && 
               $this->uninstallTab() && 
               $this->uninstallConfiguration() &&
               $this->uninstallDB();
    }

    /**
     * Enregistrement des hooks - VERSION ÉTENDUE v2.1.5
     */
    private function registerHooks()
    {
        return $this->registerHook('displayHeader') &&
               $this->registerHook('displayFooter') &&
               $this->registerHook('displayBackOfficeHeader') &&
               $this->registerHook('actionFrontControllerSetMedia') &&
               $this->registerHook('displayCMSDisputeInformation') &&
               $this->registerHook('actionCronJob') &&
               $this->registerHook('actionOrderStatusPostUpdate') &&
               $this->registerHook('actionPaymentConfirmation') &&
               $this->registerHook('displayAdminProductsExtra') &&
               $this->registerHook('actionProductSave') &&
               // Nouveaux hooks v2.1.5
               $this->registerHook('actionValidateOrder') &&
               $this->registerHook('actionOrderHistoryAddAfter') &&
               $this->registerHook('displayShoppingCart') &&
               $this->registerHook('actionCartSave') &&
               $this->registerHook('displayProductButtons') &&
               $this->registerHook('displayCustomerAccount') &&
               $this->registerHook('displayMyAccountBlock');
    }

    /**
     * Installation de la base de données - VERSION 2.1.5 CORRIGÉE ET OPTIMISÉE
     */
    private function installDB()
    {
        $sql = array();

        // ÉTAPE 1: Tables principales sans contraintes de clé étrangère
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'booker` (
            `id_booker` int(11) NOT NULL AUTO_INCREMENT,
            `id_product` int(11) DEFAULT NULL,
            `name` varchar(255) NOT NULL,
            `description` text,
            `location` varchar(255) DEFAULT NULL,
            `price` decimal(10,2) DEFAULT 0.00,
            `capacity` int(11) DEFAULT 1,
            `booking_duration` int(11) DEFAULT 60,
            `min_booking_time` int(11) DEFAULT 24,
            `max_booking_days` int(11) DEFAULT 30,
            `deposit_required` tinyint(1) DEFAULT 1,
            `deposit_rate` decimal(5,2) DEFAULT 30.00,
            `deposit_amount` decimal(10,2) DEFAULT 0.00,
            `auto_confirm` tinyint(1) DEFAULT 0,
            `cancellation_hours` int(11) DEFAULT 24,
            `image` varchar(255) DEFAULT NULL,
            `sort_order` int(11) DEFAULT 0,
            `google_account` varchar(255) DEFAULT NULL,
            `stripe_product_id` varchar(255) DEFAULT NULL,
            `stripe_price_id` varchar(255) DEFAULT NULL,
            `active` tinyint(1) DEFAULT 1,
            `date_add` datetime NOT NULL,
            `date_upd` datetime NOT NULL,
            PRIMARY KEY (`id_booker`),
            KEY `idx_product` (`id_product`),
            KEY `idx_active` (`active`),
            KEY `idx_sort_order` (`sort_order`),
            KEY `idx_stripe_product` (`stripe_product_id`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';

        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'booker_auth` (
            `id_auth` int(11) NOT NULL AUTO_INCREMENT,
            `id_booker` int(11) NOT NULL,
            `date_from` datetime NOT NULL,
            `date_to` datetime NOT NULL,
            `time_from` time NOT NULL,
            `time_to` time NOT NULL,
            `max_bookings` int(11) DEFAULT 1,
            `current_bookings` int(11) DEFAULT 0,
            `price_override` decimal(10,2) DEFAULT NULL,
            `deposit_override` decimal(10,2) DEFAULT NULL,
            `active` tinyint(1) DEFAULT 1,
            `recurring` tinyint(1) DEFAULT 0,
            `recurring_type` enum(\'daily\',\'weekly\',\'monthly\') DEFAULT NULL,
            `recurring_end` date DEFAULT NULL,
            `notes` text,
            `date_add` datetime NOT NULL,
            `date_upd` datetime NOT NULL,
            PRIMARY KEY (`id_auth`),
            KEY `idx_booker` (`id_booker`),
            KEY `idx_date_range` (`date_from`, `date_to`),
            KEY `idx_active` (`active`),
            KEY `idx_recurring` (`recurring`),
            KEY `idx_recurring_type` (`recurring_type`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';

        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'booker_auth_reserved` (
            `id_reserved` int(11) NOT NULL AUTO_INCREMENT,
            `id_auth` int(11) NOT NULL,
            `id_booker` int(11) NOT NULL,
            `id_customer` int(11) DEFAULT NULL,
            `id_order` int(11) DEFAULT NULL,
            `booking_reference` varchar(50) NOT NULL,
            `customer_firstname` varchar(100) NOT NULL,
            `customer_lastname` varchar(100) NOT NULL,
            `customer_email` varchar(150) NOT NULL,
            `customer_phone` varchar(50) DEFAULT NULL,
            `date_reserved` date NOT NULL,
            `date_to` date DEFAULT NULL,
            `hour_from` int(11) NOT NULL,
            `hour_to` int(11) NOT NULL,
            `total_price` decimal(10,2) DEFAULT 0.00,
            `deposit_paid` decimal(10,2) DEFAULT 0.00,
            `status` int(11) DEFAULT 0,
            `payment_status` enum(\'pending\',\'authorized\',\'captured\',\'cancelled\',\'refunded\') DEFAULT \'pending\',
            `stripe_payment_intent_id` varchar(255) DEFAULT NULL,
            `stripe_deposit_intent_id` varchar(255) DEFAULT NULL,
            `deposit_required` tinyint(1) NOT NULL DEFAULT 1,
            `deposit_rate` decimal(5,2) DEFAULT NULL,
            `deposit_status` varchar(20) DEFAULT \'none\',
            `card_fingerprint` varchar(255) DEFAULT NULL,
            `auto_capture_date` datetime DEFAULT NULL,
            `reminder_sent` tinyint(1) DEFAULT 0,
            `notes` text,
            `admin_notes` text,
            `date_expiry` datetime DEFAULT NULL,
            `date_add` datetime NOT NULL,
            `date_upd` datetime NOT NULL,
            PRIMARY KEY (`id_reserved`),
            UNIQUE KEY `idx_reference` (`booking_reference`),
            KEY `idx_auth` (`id_auth`),
            KEY `idx_booker` (`id_booker`),
            KEY `idx_customer` (`id_customer`),
            KEY `idx_order` (`id_order`),
            KEY `idx_status` (`status`),
            KEY `idx_payment_status` (`payment_status`),
            KEY `idx_date_range` (`date_reserved`, `date_to`),
            KEY `idx_deposit_status` (`deposit_status`),
            KEY `idx_auto_capture` (`auto_capture_date`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';

        // ÉTAPE 2: Tables du système de cautions Stripe v2.1.5
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'booking_customers` (
            `id_booking_customer` int(11) NOT NULL AUTO_INCREMENT,
            `id_customer` int(11) NOT NULL DEFAULT 0,
            `stripe_customer_id` varchar(255) NOT NULL,
            `date_add` datetime NOT NULL,
            `date_upd` datetime NOT NULL,
            PRIMARY KEY (`id_booking_customer`),
            UNIQUE KEY `idx_customer` (`id_customer`),
            UNIQUE KEY `idx_stripe_customer` (`stripe_customer_id`),
            KEY `idx_date_add` (`date_add`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';

        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'booking_deposits` (
            `id_deposit` int(11) NOT NULL AUTO_INCREMENT,
            `id_reservation` int(11) NOT NULL,
            `setup_intent_id` varchar(255) DEFAULT NULL,
            `payment_method_id` varchar(255) DEFAULT NULL,
            `payment_intent_id` varchar(255) DEFAULT NULL,
            `stripe_transaction_id` varchar(255) DEFAULT NULL,
            `deposit_amount` int(11) NOT NULL DEFAULT 0 COMMENT \'Montant en centimes\',
            `captured_amount` int(11) NOT NULL DEFAULT 0 COMMENT \'Montant capturé en centimes\',
            `refunded_amount` int(11) NOT NULL DEFAULT 0 COMMENT \'Montant remboursé en centimes\',
            `status` varchar(20) NOT NULL DEFAULT \'pending\',
            `failure_reason` text DEFAULT NULL,
            `metadata` text DEFAULT NULL COMMENT \'JSON avec métadonnées Stripe\',
            `date_authorized` datetime DEFAULT NULL,
            `date_captured` datetime DEFAULT NULL,
            `date_released` datetime DEFAULT NULL,
            `date_refunded` datetime DEFAULT NULL,
            `date_add` datetime NOT NULL,
            `date_upd` datetime NOT NULL,
            PRIMARY KEY (`id_deposit`),
            UNIQUE KEY `idx_reservation` (`id_reservation`),
            KEY `idx_status` (`status`),
            KEY `idx_setup_intent` (`setup_intent_id`),
            KEY `idx_payment_intent` (`payment_intent_id`),
            KEY `idx_date_add` (`date_add`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';

        // ÉTAPE 3: Table d'historique des cautions - STRUCTURE CORRIGÉE
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'booking_deposit_history` (
            `id_history` int(11) NOT NULL AUTO_INCREMENT,
            `id_deposit` int(11) NOT NULL,
            `id_reservation` int(11) NOT NULL,
            `action_type` varchar(50) NOT NULL COMMENT \'created, authorized, captured, released, refunded, failed\',
            `old_status` varchar(20) DEFAULT NULL,
            `new_status` varchar(20) NOT NULL,
            `amount` int(11) DEFAULT NULL COMMENT \'Montant concerné en centimes\',
            `stripe_id` varchar(255) DEFAULT NULL COMMENT \'ID Stripe de la transaction\',
            `details` text DEFAULT NULL COMMENT \'Détails de l\\\'action\',
            `id_employee` int(11) DEFAULT NULL COMMENT \'Employé qui a effectué l\\\'action\',
            `date_add` datetime NOT NULL,
            PRIMARY KEY (`id_history`),
            KEY `idx_deposit` (`id_deposit`),
            KEY `idx_reservation` (`id_reservation`),
            KEY `idx_action_type` (`action_type`),
            KEY `idx_date_add` (`date_add`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';

        // ÉTAPE 4: Tables système étendues
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'booking_webhooks` (
            `id_webhook` int(11) NOT NULL AUTO_INCREMENT,
            `stripe_event_id` varchar(255) NOT NULL,
            `event_type` varchar(100) NOT NULL,
            `processed` tinyint(1) NOT NULL DEFAULT 0,
            `payload` longtext DEFAULT NULL COMMENT \'JSON du webhook\',
            `processing_result` text DEFAULT NULL,
            `date_received` datetime NOT NULL,
            `date_processed` datetime DEFAULT NULL,
            PRIMARY KEY (`id_webhook`),
            UNIQUE KEY `idx_stripe_event` (`stripe_event_id`),
            KEY `idx_event_type` (`event_type`),
            KEY `idx_processed` (`processed`),
            KEY `idx_date_received` (`date_received`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';

        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'booking_deposit_config` (
            `id_config` int(11) NOT NULL AUTO_INCREMENT,
            `id_booker` int(11) DEFAULT NULL COMMENT \'Configuration spécifique à un booker, NULL = global\',
            `deposit_required` tinyint(1) NOT NULL DEFAULT 1,
            `deposit_rate` decimal(5,2) NOT NULL DEFAULT 30.00 COMMENT \'Pourcentage de caution\',
            `min_deposit_amount` decimal(10,2) NOT NULL DEFAULT 50.00 COMMENT \'Montant minimum de caution\',
            `max_deposit_amount` decimal(10,2) NOT NULL DEFAULT 2000.00 COMMENT \'Montant maximum de caution\',
            `auto_capture_delay` int(11) NOT NULL DEFAULT 24 COMMENT \'Délai avant capture auto en heures\',
            `auto_release_delay` int(11) NOT NULL DEFAULT 168 COMMENT \'Délai avant libération auto en heures (7 jours)\',
            `capture_on_start` tinyint(1) NOT NULL DEFAULT 0 COMMENT \'Capturer au début de la réservation\',
            `release_on_end` tinyint(1) NOT NULL DEFAULT 1 COMMENT \'Libérer à la fin de la réservation\',
            `date_add` datetime NOT NULL,
            `date_upd` datetime NOT NULL,
            PRIMARY KEY (`id_config`),
            UNIQUE KEY `idx_booker` (`id_booker`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';

        // ÉTAPE 5: Tables de liaison et support
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'booker_product` (
            `id_booker` int(11) NOT NULL,
            `id_product` int(11) NOT NULL,
            `sync_price` tinyint(1) DEFAULT 1,
            `override_price` decimal(10,2) DEFAULT NULL,
            `sync_stock` tinyint(1) DEFAULT 1,
            `date_add` datetime NOT NULL,
            PRIMARY KEY (`id_booker`, `id_product`),
            KEY `idx_product` (`id_product`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';

        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'booker_reservation_order` (
            `id_reservation` int(11) NOT NULL,
            `id_order` int(11) NOT NULL,
            `order_type` enum(\'booking\',\'deposit\') DEFAULT \'booking\',
            `amount` decimal(10,2) NOT NULL,
            `date_add` datetime NOT NULL,
            PRIMARY KEY (`id_reservation`, `id_order`, `order_type`),
            KEY `idx_order` (`id_order`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';

        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'booking_activity_log` (
            `id_log` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `id_reservation` int(10) unsigned NULL,
            `id_booker` int(10) unsigned NULL,
            `action` VARCHAR(100) NOT NULL,
            `details` TEXT NULL,
            `id_employee` int(10) unsigned NULL,
            `ip_address` varchar(45) DEFAULT NULL,
            `user_agent` varchar(255) DEFAULT NULL,
            `date_add` DATETIME NOT NULL,
            PRIMARY KEY (`id_log`),
            INDEX `idx_reservation` (`id_reservation`),
            INDEX `idx_booker` (`id_booker`),
            INDEX `idx_action` (`action`),
            INDEX `idx_employee` (`id_employee`),
            INDEX `idx_date_add` (`date_add`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';

        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'booker_lang` (
            `id_booker` int(10) unsigned NOT NULL,
            `id_lang` int(10) unsigned NOT NULL,
            `name` varchar(255) DEFAULT NULL,
            `description` TEXT DEFAULT NULL,
            PRIMARY KEY (`id_booker`, `id_lang`),
            KEY `idx_booker` (`id_booker`),
            KEY `idx_lang` (`id_lang`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';

        // ÉTAPE 6: Exécution des requêtes SQL avec gestion d'erreurs améliorée
        foreach ($sql as $query) {
            if (!Db::getInstance()->execute($query)) {
                PrestaShopLogger::addLog(
                    'Booking Module v2.1.5 Install Error: ' . Db::getInstance()->getMsgError() . ' - Query: ' . substr($query, 0, 200) . '...', 
                    3, 
                    null, 
                    'Booking', 
                    null, 
                    true
                );
                return false;
            }
        }

        // ÉTAPE 7: Ajouter les contraintes de clé étrangère APRÈS création des tables
        $foreign_keys = array();
        
        // Contraintes pour booking_deposits
        $foreign_keys[] = 'ALTER TABLE `' . _DB_PREFIX_ . 'booking_deposits` 
                          ADD CONSTRAINT `fk_deposits_reservation` 
                          FOREIGN KEY (`id_reservation`) 
                          REFERENCES `' . _DB_PREFIX_ . 'booker_auth_reserved` (`id_reserved`) 
                          ON DELETE CASCADE';
        
        // Contraintes pour booking_deposit_history  
        $foreign_keys[] = 'ALTER TABLE `' . _DB_PREFIX_ . 'booking_deposit_history` 
                          ADD CONSTRAINT `fk_history_deposit` 
                          FOREIGN KEY (`id_deposit`) 
                          REFERENCES `' . _DB_PREFIX_ . 'booking_deposits` (`id_deposit`) 
                          ON DELETE CASCADE';
                          
        $foreign_keys[] = 'ALTER TABLE `' . _DB_PREFIX_ . 'booking_deposit_history` 
                          ADD CONSTRAINT `fk_history_reservation` 
                          FOREIGN KEY (`id_reservation`) 
                          REFERENCES `' . _DB_PREFIX_ . 'booker_auth_reserved` (`id_reserved`) 
                          ON DELETE CASCADE';

        // Contrainte pour booking_deposit_config
        $foreign_keys[] = 'ALTER TABLE `' . _DB_PREFIX_ . 'booking_deposit_config` 
                          ADD CONSTRAINT `fk_config_booker` 
                          FOREIGN KEY (`id_booker`) 
                          REFERENCES `' . _DB_PREFIX_ . 'booker` (`id_booker`) 
                          ON DELETE CASCADE';

        // Exécuter les contraintes (ignorer les erreurs si déjà présentes)
        foreach ($foreign_keys as $constraint) {
            Db::getInstance()->execute($constraint);
        }

        // ÉTAPE 8: Insérer la configuration globale par défaut pour les cautions
        Db::getInstance()->execute('
            INSERT IGNORE INTO `' . _DB_PREFIX_ . 'booking_deposit_config` 
            (`id_booker`, `deposit_required`, `deposit_rate`, `min_deposit_amount`, `max_deposit_amount`, `auto_capture_delay`, `auto_release_delay`, `date_add`, `date_upd`)
            VALUES 
            (NULL, 1, 30.00, 50.00, 2000.00, 24, 168, NOW(), NOW())
        ');

        // Log de succès
        PrestaShopLogger::addLog(
            'Booking Module v2.1.5: Installation de la base de données terminée avec succès - Toutes les tables créées', 
            1, 
            null, 
            'Booking', 
            null, 
            true
        );

        return true;
    }

    /**
     * Installation des onglets d'administration - VERSION 2.1.5 COMPLÈTE
     */
    private function installTab()
    {
        $tabs = array(
            array(
                'class_name' => 'AdminBooking',
                'name' => 'Réservations',
                'parent_class_name' => 'IMPROVE',
                'icon' => 'calendar'
            ),
            array(
                'class_name' => 'AdminBooker',
                'name' => 'Éléments & Produits',
                'parent_class_name' => 'AdminBooking'
            ),
            array(
                'class_name' => 'AdminBookerAuth',
                'name' => 'Disponibilités',
                'parent_class_name' => 'AdminBooking'
            ),
            array(
                'class_name' => 'AdminBookerAuthReserved',
                'name' => 'Réservations',
                'parent_class_name' => 'AdminBooking'
            ),
            array(
                'class_name' => 'AdminBookerDeposits',
                'name' => 'Cautions Stripe',
                'parent_class_name' => 'AdminBooking'
            ),
            array(
                'class_name' => 'AdminBookerCalendarAvailability',
                'name' => 'Calendrier Disponibilités',
                'parent_class_name' => 'AdminBooking'
            ),
            array(
                'class_name' => 'AdminBookerCalendarReservations',
                'name' => 'Calendrier Réservations',
                'parent_class_name' => 'AdminBooking'
            ),
            array(
                'class_name' => 'AdminBookerReservations',
                'name' => 'Gestion Réservations',
                'parent_class_name' => 'AdminBooking'
            ),
            array(
                'class_name' => 'AdminBookerView',
                'name' => 'Vue Calendriers',
                'parent_class_name' => 'AdminBooking'
            ),
            array(
                'class_name' => 'AdminBookerSettings',
                'name' => 'Paramètres',
                'parent_class_name' => 'AdminBooking'
            ),
            array(
                'class_name' => 'AdminBookerStats',
                'name' => 'Statistiques',
                'parent_class_name' => 'AdminBooking'
            )
        );

        foreach ($tabs as $tab_data) {
            $tab = new Tab();
            $tab->class_name = $tab_data['class_name'];
            $tab->module = $this->name;
            $tab->active = 1;
            
            if (isset($tab_data['icon'])) {
                $tab->icon = $tab_data['icon'];
            }

            foreach (Language::getLanguages(false) as $lang) {
                $tab->name[$lang['id_lang']] = $tab_data['name'];
            }

            if ($tab_data['parent_class_name']) {
                $tab->id_parent = (int)Tab::getIdFromClassName($tab_data['parent_class_name']);
            }

            if (!$tab->add()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Désinstallation des onglets
     */
    private function uninstallTab()
    {
        $tab_classes = array(
            'AdminBooker',
            'AdminBookerAuth', 
            'AdminBookerAuthReserved',
            'AdminBookerDeposits',
            'AdminBookerCalendarAvailability',
            'AdminBookerCalendarReservations',
            'AdminBookerReservations',
            'AdminBookerView',
            'AdminBookerSettings',
            'AdminBookerStats',
            'AdminBooking'
        );

        foreach ($tab_classes as $class_name) {
            $id_tab = (int)Tab::getIdFromClassName($class_name);
            if ($id_tab) {
                $tab = new Tab($id_tab);
                if (!$tab->delete()) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Installation des configurations - VERSION 2.1.5 ÉTENDUE
     */
    private function installConfiguration()
    {
        $configurations = array(
            // Paramètres de base
            'BOOKING_DEFAULT_PRICE' => '50.00',
            'BOOKING_DEPOSIT_AMOUNT' => '20.00',
            'BOOKING_DEFAULT_DURATION' => '60',
            'BOOKING_EXPIRY_HOURS' => '24',
            'BOOKING_AUTO_CONFIRM' => '0',
            'BOOKING_MULTI_SELECT' => '1',
            
            // Nouveau système de cautions Stripe v2.1.5
            'BOOKING_STRIPE_DEPOSIT_ENABLED' => '1',
            'BOOKING_DEPOSIT_RATE' => '30',
            'BOOKING_DEPOSIT_MIN_AMOUNT' => '50',
            'BOOKING_DEPOSIT_MAX_AMOUNT' => '2000',
            'BOOKING_DEPOSIT_AUTO_CAPTURE_DELAY' => '24',
            'BOOKING_DEPOSIT_AUTO_RELEASE_DELAY' => '168',
            'BOOKING_DEPOSIT_CAPTURE_ON_START' => '0',
            'BOOKING_DEPOSIT_RELEASE_ON_END' => '1',
            'BOOKING_STRIPE_WEBHOOK_SECRET' => '',
            'BOOKING_STRIPE_TEST_MODE' => '1',
            'BOOKING_STRIPE_TEST_SECRET_KEY' => '',
            'BOOKING_STRIPE_TEST_PUBLIC_KEY' => '',
            'BOOKING_STRIPE_LIVE_SECRET_KEY' => '',
            'BOOKING_STRIPE_LIVE_PUBLIC_KEY' => '',
            
            // Horaires d'ouverture
            'BOOKING_BUSINESS_HOURS_START' => '08:00',
            'BOOKING_BUSINESS_HOURS_END' => '18:00',
            'BOOKING_ALLOWED_DAYS' => '1,2,3,4,5,6,7',
            'BOOKING_CALENDAR_MIN_TIME' => '08:00',
            'BOOKING_CALENDAR_MAX_TIME' => '20:00',
            'BOOKING_SLOT_DURATION' => '00:30:00',
            
            // Notifications étendues
            'BOOKING_NOTIFICATIONS_ENABLED' => '1',
            'BOOKING_AUTO_CONFIRMATION_EMAIL' => '1',
            'BOOKING_AUTO_REMINDERS' => '0',
            'BOOKING_REMINDER_HOURS' => '24',
            'BOOKING_ADMIN_EMAIL' => Configuration::get('PS_SHOP_EMAIL'),
            'BOOKING_DEPOSIT_EMAIL_NOTIFICATIONS' => '1',
            'BOOKING_SMS_NOTIFICATIONS' => '0',
            
            // Stripe et paiements étendus
            'BOOKING_STRIPE_ENABLED' => '0',
            'BOOKING_STRIPE_HOLD_DEPOSIT' => '1',
            'BOOKING_SAVE_CARDS' => '1',
            'BOOKING_STRIPE_PUBLISHABLE_KEY' => '',
            'BOOKING_STRIPE_SECRET_KEY' => '',
            'BOOKING_STRIPE_CONNECT_ENABLED' => '0',
            'BOOKING_PARTIAL_PAYMENTS' => '0',
            
            // Paramètres avancés
            'BOOKING_DEBUG_MODE' => '0',
            'BOOKING_MIN_BOOKING_TIME' => '24',
            'BOOKING_MAX_BOOKING_DAYS' => '30',
            'BOOKING_SYNC_PRODUCT_PRICE' => '1',
            'BOOKING_SYNC_FROM_PRODUCT' => '0',
            'BOOKING_AUTO_CREATE_PRODUCT' => '1',
            'BOOKING_DELETE_LINKED_PRODUCT' => '0',
            'BOOKING_INVENTORY_MANAGEMENT' => '1',
            
            // Intégrations
            'BOOKING_DEFAULT_CATEGORY' => Configuration::get('PS_HOME_CATEGORY'),
            'BOOKING_DEFAULT_TAX_RULES_GROUP' => '1',
            'BOOKING_STATUS_PENDING_PAYMENT' => Configuration::get('PS_OS_BANKWIRE'),
            'BOOKING_GOOGLE_CALENDAR_SYNC' => '0',
            'BOOKING_GOOGLE_API_KEY' => '',
            'BOOKING_ICAL_EXPORT' => '1',
            'BOOKING_OUTLOOK_INTEGRATION' => '0',
            
            // Interface et calendriers améliorés
            'BOOKING_CALENDAR_THEME' => 'default',
            'BOOKING_CALENDAR_VIEW' => 'dayGridMonth',
            'BOOKING_CALENDAR_FIRST_DAY' => '1',
            'BOOKING_CALENDAR_WEEK_NUMBERS' => '0',
            'BOOKING_CALENDAR_ALL_DAY' => '0',
            'BOOKING_MODERN_UI' => '1',
            'BOOKING_RESPONSIVE_DESIGN' => '1',
            'BOOKING_DARK_MODE' => '0',
            
            // Sécurité et performances
            'BOOKING_CACHE_ENABLED' => '1',
            'BOOKING_CACHE_LIFETIME' => '3600',
            'BOOKING_MAX_SIMULTANEOUS_BOOKINGS' => '5',
            'BOOKING_RATE_LIMIT_ENABLED' => '1',
            'BOOKING_RATE_LIMIT_REQUESTS' => '10',
            'BOOKING_RATE_LIMIT_WINDOW' => '60',
            'BOOKING_CAPTCHA_ENABLED' => '0',
            'BOOKING_IP_WHITELIST' => '',
            
            // Analytics et reporting
            'BOOKING_ANALYTICS_ENABLED' => '1',
            'BOOKING_GOOGLE_ANALYTICS_ID' => '',
            'BOOKING_CONVERSION_TRACKING' => '1',
            'BOOKING_EXPORT_FORMATS' => 'csv,pdf,excel',
            'BOOKING_AUTO_REPORTS' => '0',
            'BOOKING_REPORT_EMAIL' => '',
            
            // Personnalisation avancée
            'BOOKING_CUSTOM_CSS' => '',
            'BOOKING_CUSTOM_JS' => '',
            'BOOKING_LOGO_URL' => '',
            'BOOKING_BRAND_COLORS' => '#3498db,#2ecc71,#e74c3c',
            'BOOKING_CUSTOM_FIELDS' => '0',
            'BOOKING_TERMS_CONDITIONS_ID' => '0'
        );

        foreach ($configurations as $key => $value) {
            if (!Configuration::updateValue($key, $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Installation des statuts de commande personnalisés
     */
    private function installOrderStates()
    {
        // Statut : Réservation en attente de paiement
        $order_state = new OrderState();
        $order_state->name = array();
        foreach (Language::getLanguages() as $language) {
            $order_state->name[$language['id_lang']] = 'Réservation en attente de paiement';
        }
        $order_state->send_email = true;
        $order_state->color = '#FF6600';
        $order_state->hidden = false;
        $order_state->delivery = false;
        $order_state->logable = true;
        $order_state->invoice = false;
        $order_state->module_name = $this->name;
        $order_state->template = 'booking_pending';
        
        if ($order_state->add()) {
            Configuration::updateValue('BOOKING_STATUS_PENDING_PAYMENT', $order_state->id);
        }

        // Statut : Caution autorisée
        $order_state = new OrderState();
        $order_state->name = array();
        foreach (Language::getLanguages() as $language) {
            $order_state->name[$language['id_lang']] = 'Caution autorisée';
        }
        $order_state->send_email = true;
        $order_state->color = '#3498db';
        $order_state->hidden = false;
        $order_state->delivery = false;
        $order_state->logable = true;
        $order_state->invoice = false;
        $order_state->module_name = $this->name;
        $order_state->template = 'deposit_authorized';
        
        if ($order_state->add()) {
            Configuration::updateValue('BOOKING_STATUS_DEPOSIT_AUTHORIZED', $order_state->id);
        }

        return true;
    }

    /**
     * Créer les dossiers nécessaires
     */
    private function createDirectories()
    {
        $directories = array(
            $this->getLocalPath() . 'logs/',
            $this->getLocalPath() . 'exports/',
            $this->getLocalPath() . 'uploads/',
            $this->getLocalPath() . 'cache/'
        );

        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    return false;
                }
                
                // Créer .htaccess pour sécuriser
                file_put_contents($dir . '.htaccess', 'deny from all');
            }
        }

        return true;
    }

    /**
     * Désinstallation des configurations
     */
    private function uninstallConfiguration()
    {
        $configurations = array(
            'BOOKING_DEFAULT_PRICE', 'BOOKING_DEPOSIT_AMOUNT', 'BOOKING_DEFAULT_DURATION',
            'BOOKING_EXPIRY_HOURS', 'BOOKING_AUTO_CONFIRM', 'BOOKING_MULTI_SELECT',
            'BOOKING_STRIPE_DEPOSIT_ENABLED', 'BOOKING_DEPOSIT_RATE', 'BOOKING_DEPOSIT_MIN_AMOUNT',
            'BOOKING_DEPOSIT_MAX_AMOUNT', 'BOOKING_DEPOSIT_AUTO_CAPTURE_DELAY', 'BOOKING_DEPOSIT_AUTO_RELEASE_DELAY',
            'BOOKING_BUSINESS_HOURS_START', 'BOOKING_BUSINESS_HOURS_END', 'BOOKING_ALLOWED_DAYS',
            'BOOKING_NOTIFICATIONS_ENABLED', 'BOOKING_AUTO_CONFIRMATION_EMAIL', 'BOOKING_AUTO_REMINDERS',
            'BOOKING_STRIPE_ENABLED', 'BOOKING_STRIPE_HOLD_DEPOSIT', 'BOOKING_SAVE_CARDS',
            'BOOKING_DEBUG_MODE', 'BOOKING_MIN_BOOKING_TIME', 'BOOKING_MAX_BOOKING_DAYS',
            'BOOKING_MODERN_UI', 'BOOKING_RESPONSIVE_DESIGN', 'BOOKING_ANALYTICS_ENABLED'
        );

        foreach ($configurations as $key) {
            Configuration::deleteByName($key);
        }

        return true;
    }

    /**
     * Désinstallation de la base de données - MISE À JOUR v2.1.5
     */
    private function uninstallDB()
    {
        $sql = array();
        
        // Supprimer dans l'ordre des dépendances (contraintes FK)
        $sql[] = 'SET FOREIGN_KEY_CHECKS = 0';
        $sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'booking_deposit_history`';
        $sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'booking_deposits`';
        $sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'booking_deposit_config`';
        $sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'booking_webhooks`';
        $sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'booking_customers`';
        $sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'booker_lang`';
        $sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'booking_activity_log`';
        $sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'booker_reservation_order`';
        $sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'booker_product`';
        $sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'booker_auth_reserved`';
        $sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'booker_auth`';
        $sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'booker`';
        $sql[] = 'SET FOREIGN_KEY_CHECKS = 1';

        foreach ($sql as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Hook d'en-tête - AMÉLIORÉ v2.1.5
     */
    public function hookDisplayHeader()
    {
        if ($this->context->controller instanceof AdminController) {
            $this->context->controller->addCSS($this->_path . 'views/css/admin-calendar.css');
            $this->context->controller->addCSS($this->_path . 'views/css/back.css');
            $this->context->controller->addJS($this->_path . 'views/js/back.js');
            
            // CSS spécifique pour les cautions
            if (strpos($_SERVER['REQUEST_URI'], 'AdminBookerDeposits') !== false) {
                $this->context->controller->addCSS($this->_path . 'views/css/deposits.css');
            }
        }
        
        // Stripe JS pour le front-office
        if (Configuration::get('BOOKING_STRIPE_ENABLED') && 
            !($this->context->controller instanceof AdminController)) {
            $this->context->controller->addJS('https://js.stripe.com/v3/');
        }
    }

    /**
     * Hook média front - ÉTENDU v2.1.5
     */
    public function hookActionFrontControllerSetMedia()
    {
        // CSS moderne responsive
        $this->context->controller->addCSS($this->_path . 'views/css/booking-modern.css');
        $this->context->controller->addCSS($this->_path . 'views/css/front.css');
        
        // JavaScript moderne avec gestion Stripe
        $this->context->controller->addJS($this->_path . 'views/js/booking-manager.js');
        $this->context->controller->addJS($this->_path . 'views/js/booking-front.js');
        
        // Configuration JavaScript pour Stripe
        if (Configuration::get('BOOKING_STRIPE_ENABLED')) {
            $stripe_config = array(
                'publicKey' => Configuration::get('BOOKING_STRIPE_TEST_MODE') ? 
                              Configuration::get('BOOKING_STRIPE_TEST_PUBLIC_KEY') : 
                              Configuration::get('BOOKING_STRIPE_LIVE_PUBLIC_KEY'),
                'depositEnabled' => Configuration::get('BOOKING_STRIPE_DEPOSIT_ENABLED'),
                'depositRate' => Configuration::get('BOOKING_DEPOSIT_RATE', 30)
            );
            
            Media::addJsDef(array(
                'bookingStripeConfig' => $stripe_config
            ));
        }
    }

    /**
     * Hook back-office header - ÉTENDU v2.1.5
     */
    public function hookDisplayBackOfficeHeader()
    {
        if (Tools::getValue('configure') == $this->name || 
            strpos($_SERVER['REQUEST_URI'], 'AdminBooker') !== false) {
            
            // FullCalendar et dépendances
            $this->context->controller->addJS([
                'https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.8/index.global.min.js',
                'https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js',
                $this->_path . 'views/js/availability-calendar.js',
                $this->_path . 'views/js/reservation-calendar.js'
            ]);
            
            // Configuration JavaScript globale
            Media::addJsDef(array(
                'bookingAdminConfig' => array(
                    'ajaxUrl' => $this->context->link->getAdminLink('AdminBooking'),
                    'token' => Tools::getAdminTokenLite('AdminBooking'),
                    'depositEnabled' => Configuration::get('BOOKING_STRIPE_DEPOSIT_ENABLED'),
                    'moduleDir' => $this->_path
                )
            ));
        }
    }

    /**
     * Hook pour traitement des cron jobs - ÉTENDU v2.1.5
     */
    public function hookActionCronJob()
    {
        // Nettoyer les réservations expirées
        BookerAuthReserved::cancelExpiredReservations();
        
        // Traitement automatique des cautions
        $this->processAutomaticDeposits();
        
        // Envoyer les rappels de réservation
        if (Configuration::get('BOOKING_AUTO_REMINDERS')) {
            $this->sendBookingReminders();
        }
        
        // Synchroniser avec Google Calendar si activé
        if (Configuration::get('BOOKING_GOOGLE_CALENDAR_SYNC')) {
            $this->syncGoogleCalendar();
        }
        
        // Nettoyer les logs anciens
        $this->cleanOldLogs();
        
        // Générer les rapports automatiques
        if (Configuration::get('BOOKING_AUTO_REPORTS')) {
            $this->generateAutomaticReports();
        }
    }

    /**
     * Traitement automatique des cautions selon leur statut
     */
    private function processAutomaticDeposits()
    {
        if (!Configuration::get('BOOKING_STRIPE_DEPOSIT_ENABLED')) {
            return;
        }
        
        $depositManager = new StripeDepositManager();
        
        // Capturer automatiquement les cautions dont le délai est dépassé
        if (Configuration::get('BOOKING_DEPOSIT_CAPTURE_ON_START')) {
            $auto_capture_delay = Configuration::get('BOOKING_DEPOSIT_AUTO_CAPTURE_DELAY', 24);
            
            $sql = 'SELECT r.id_reserved 
                    FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` r
                    LEFT JOIN `' . _DB_PREFIX_ . 'booking_deposits` d ON r.id_reserved = d.id_reservation
                    WHERE d.status = "authorized"
                    AND r.auto_capture_date IS NOT NULL
                    AND r.auto_capture_date <= NOW()';
            
            $reservations = Db::getInstance()->executeS($sql);
            
            foreach ($reservations as $reservation) {
                $depositManager->captureDeposit($reservation['id_reserved']);
            }
        }
        
        // Libérer automatiquement les cautions après la réservation
        if (Configuration::get('BOOKING_DEPOSIT_RELEASE_ON_END')) {
            $auto_release_delay = Configuration::get('BOOKING_DEPOSIT_AUTO_RELEASE_DELAY', 168);
            
            $sql = 'SELECT r.id_reserved 
                    FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` r
                    LEFT JOIN `' . _DB_PREFIX_ . 'booking_deposits` d ON r.id_reserved = d.id_reservation
                    WHERE d.status = "authorized"
                    AND r.status = ' . BookerAuthReserved::STATUS_COMPLETED . '
                    AND r.date_upd < DATE_SUB(NOW(), INTERVAL ' . (int)$auto_release_delay . ' HOUR)';
            
            $reservations = Db::getInstance()->executeS($sql);
            
            foreach ($reservations as $reservation) {
                $depositManager->releaseDeposit($reservation['id_reserved']);
            }
        }
    }

    /**
     * Envoyer les rappels de réservation - AMÉLIORÉ v2.1.5
     */
    private function sendBookingReminders()
    {
        if (!Configuration::get('BOOKING_NOTIFICATIONS_ENABLED')) {
            return;
        }
        
        $reminder_hours = Configuration::get('BOOKING_REMINDER_HOURS', 24);
        $notification_system = new BookingNotificationSystem();
        
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` 
                WHERE `status` IN (' . BookerAuthReserved::STATUS_ACCEPTED . ', ' . BookerAuthReserved::STATUS_PAID . ') 
                AND `date_reserved` = DATE_ADD(CURDATE(), INTERVAL ' . (int)$reminder_hours . ' HOUR)
                AND reminder_sent = 0';
        
        $reservations = Db::getInstance()->executeS($sql);
        
        foreach ($reservations as $reservation) {
            if ($notification_system->sendReminderEmail($reservation)) {
                // Marquer le rappel comme envoyé
                Db::getInstance()->execute('
                    UPDATE `' . _DB_PREFIX_ . 'booker_auth_reserved` 
                    SET reminder_sent = 1 
                    WHERE id_reserved = ' . (int)$reservation['id_reserved']
                );
            }
        }
    }

    /**
     * Nettoyer les anciens logs
     */
    private function cleanOldLogs()
    {
        $retention_days = 90; // Garder 90 jours de logs
        
        // Nettoyer les logs d'activité
        Db::getInstance()->execute('
            DELETE FROM `' . _DB_PREFIX_ . 'booking_activity_log` 
            WHERE date_add < DATE_SUB(NOW(), INTERVAL ' . (int)$retention_days . ' DAY)
        ');
        
        // Nettoyer les webhooks traités
        Db::getInstance()->execute('
            DELETE FROM `' . _DB_PREFIX_ . 'booking_webhooks` 
            WHERE processed = 1 
            AND date_received < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ');
    }

    /**
     * Hook de validation de commande - NOUVEAU v2.1.5
     */
    public function hookActionValidateOrder($params)
    {
        $order = $params['order'];
        $cart = $params['cart'];
        
        // Vérifier si la commande contient des réservations
        $this->linkOrderToReservations($order, $cart);
    }

    /**
     * Lier une commande aux réservations correspondantes
     */
    private function linkOrderToReservations($order, $cart)
    {
        // Rechercher les réservations en attente pour ce client
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` 
                WHERE customer_email = "' . pSQL($order->getCustomer()->email) . '"
                AND status = ' . BookerAuthReserved::STATUS_PENDING_PAYMENT . '
                AND id_order IS NULL
                ORDER BY date_add DESC
                LIMIT 1';
        
        $reservation = Db::getInstance()->getRow($sql);
        
        if ($reservation) {
            // Lier la réservation à la commande
            Db::getInstance()->execute('
                UPDATE `' . _DB_PREFIX_ . 'booker_auth_reserved` 
                SET id_order = ' . (int)$order->id . ',
                    status = ' . BookerAuthReserved::STATUS_PAID . ',
                    payment_status = "captured",
                    date_upd = NOW()
                WHERE id_reserved = ' . (int)$reservation['id_reserved']
            );
            
            // Logger l'activité
            $this->logActivity('order_linked', 'Commande #' . $order->id . ' liée à la réservation', $reservation['id_reserved']);
        }
    }

    /**
     * Logger une activité
     */
    private function logActivity($action, $details = null, $id_reservation = null, $id_booker = null)
    {
        $employee_id = isset($this->context->employee) ? $this->context->employee->id : null;
        $ip_address = Tools::getRemoteAddr();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        
        return Db::getInstance()->execute('
            INSERT INTO `' . _DB_PREFIX_ . 'booking_activity_log`
            (id_reservation, id_booker, action, details, id_employee, ip_address, user_agent, date_add)
            VALUES (
                ' . ($id_reservation ? (int)$id_reservation : 'NULL') . ',
                ' . ($id_booker ? (int)$id_booker : 'NULL') . ',
                "' . pSQL($action) . '",
                ' . ($details ? '"' . pSQL($details) . '"' : 'NULL') . ',
                ' . ($employee_id ? (int)$employee_id : 'NULL') . ',
                "' . pSQL($ip_address) . '",
                "' . pSQL(substr($user_agent, 0, 255)) . '",
                NOW()
            )
        ');
    }

    /**
     * Page de configuration étendue - VERSION 2.1.5
     */
    public function getContent()
    {
        $output = '';

        // Traitement des formulaires
        if (Tools::isSubmit('submitBookingConfig')) {
            $output .= $this->postProcess();
        } elseif (Tools::isSubmit('submitStripeConfig')) {
            $output .= $this->postProcessStripe();
        } elseif (Tools::isSubmit('testStripeConnection')) {
            $output .= $this->testStripeConnection();
        }

        // Afficher les alertes importantes
        $output .= $this->displayAlerts();

        // Afficher les statistiques rapides
        $output .= $this->displayQuickStats();

        // Formulaires de configuration
        $output .= $this->displayConfigurationTabs();

        return $output;
    }

    /**
     * Afficher les alertes importantes
     */
    private function displayAlerts()
    {
        $alerts = '';
        
        // Vérifier la configuration Stripe
        if (Configuration::get('BOOKING_STRIPE_ENABLED') && 
            !Configuration::get('BOOKING_STRIPE_SECRET_KEY')) {
            $alerts .= $this->displayWarning($this->l('Configuration Stripe incomplète. Veuillez configurer vos clés API.'));
        }
        
        // Vérifier les webhooks
        if (Configuration::get('BOOKING_STRIPE_DEPOSIT_ENABLED') && 
            !Configuration::get('BOOKING_STRIPE_WEBHOOK_SECRET')) {
            $alerts .= $this->displayWarning($this->l('Webhook Stripe non configuré. Les cautions ne seront pas synchronisées automatiquement.'));
        }
        
        // Vérifier les permissions
        $log_dir = $this->getLocalPath() . 'logs/';
        if (!is_writable($log_dir)) {
            $alerts .= $this->displayError($this->l('Le dossier logs/ n\'est pas accessible en écriture.'));
        }
        
        return $alerts;
    }

    /**
     * Afficher les statistiques rapides
     */
    private function displayQuickStats()
    {
        $stats = $this->getQuickStats();
        
        return $this->display(__FILE__, 'views/templates/admin/quick_stats.tpl');
    }

    /**
     * Récupérer les statistiques rapides
     */
    private function getQuickStats()
    {
        $stats = array();
        
        // Réservations du jour
        $stats['today_reservations'] = Db::getInstance()->getValue('
            SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`
            WHERE DATE(date_add) = CURDATE()
        ');
        
        // Cautions en attente
        $stats['pending_deposits'] = Db::getInstance()->getValue('
            SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booking_deposits`
            WHERE status = "pending"
        ');
        
        // Chiffre d'affaires du mois
        $stats['monthly_revenue'] = Db::getInstance()->getValue('
            SELECT SUM(total_price) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`
            WHERE MONTH(date_add) = MONTH(CURDATE())
            AND YEAR(date_add) = YEAR(CURDATE())
            AND status IN (' . BookerAuthReserved::STATUS_PAID . ', ' . BookerAuthReserved::STATUS_COMPLETED . ')
        ');
        
        return $stats;
    }

    /**
     * Traitement du formulaire principal
     */
    private function postProcess()
    {
        $form_values = $this->getConfigFormValues();
        
        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }

        $this->logActivity('config_updated', 'Configuration principale mise à jour');
        
        return $this->displayConfirmation($this->l('Configuration mise à jour avec succès.'));
    }

    /**
     * Traitement de la configuration Stripe
     */
    private function postProcessStripe()
    {
        $stripe_keys = array(
            'BOOKING_STRIPE_TEST_SECRET_KEY',
            'BOOKING_STRIPE_TEST_PUBLIC_KEY',
            'BOOKING_STRIPE_LIVE_SECRET_KEY',
            'BOOKING_STRIPE_LIVE_PUBLIC_KEY',
            'BOOKING_STRIPE_WEBHOOK_SECRET',
            'BOOKING_STRIPE_TEST_MODE',
            'BOOKING_STRIPE_DEPOSIT_ENABLED'
        );
        
        foreach ($stripe_keys as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
        
        $this->logActivity('stripe_config_updated', 'Configuration Stripe mise à jour');
        
        return $this->displayConfirmation($this->l('Configuration Stripe mise à jour avec succès.'));
    }

    /**
     * Tester la connexion Stripe
     */
    private function testStripeConnection()
    {
        try {
            $depositManager = new StripeDepositManager();
            
            // Test simple : récupérer les informations du compte
            $test_mode = Configuration::get('BOOKING_STRIPE_TEST_MODE');
            $secret_key = $test_mode ? 
                         Configuration::get('BOOKING_STRIPE_TEST_SECRET_KEY') : 
                         Configuration::get('BOOKING_STRIPE_LIVE_SECRET_KEY');
            
            if (empty($secret_key)) {
                throw new Exception('Clé secrète Stripe non configurée');
            }
            
            // Initialiser Stripe avec la clé
            \Stripe\Stripe::setApiKey($secret_key);
            $account = \Stripe\Account::retrieve();
            
            $mode = $test_mode ? 'test' : 'live';
            return $this->displayConfirmation(
                $this->l('Connexion Stripe réussie !') . '<br>' .
                $this->l('Compte :') . ' ' . $account->display_name . '<br>' .
                $this->l('Mode :') . ' ' . $mode
            );
            
        } catch (Exception $e) {
            return $this->displayError(
                $this->l('Erreur de connexion Stripe :') . ' ' . $e->getMessage()
            );
        }
    }

    /**
     * Afficher les onglets de configuration
     */
    private function displayConfigurationTabs()
    {
        // Code pour générer les onglets de configuration
        return $this->displayForm();
    }

    /**
     * Formulaire de configuration simplifié pour la v2.1.5
     */
    private function displayForm()
    {
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitBookingConfig';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Structure du formulaire de configuration - MISE À JOUR v2.1.5
     */
    private function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Configuration du module de réservations v2.1.5'),
                    'icon' => 'icon-cogs',
                ),
                'description' => $this->l('Version corrigée avec installation robuste et gestion avancée des cautions Stripe.'),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Interface moderne'),
                        'name' => 'BOOKING_MODERN_UI',
                        'values' => array(
                            array('id' => 'modern_on', 'value' => 1, 'label' => $this->l('Activé')),
                            array('id' => 'modern_off', 'value' => 0, 'label' => $this->l('Désactivé'))
                        ),
                        'desc' => $this->l('Utiliser la nouvelle interface moderne et responsive'),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Cautions Stripe'),
                        'name' => 'BOOKING_STRIPE_DEPOSIT_ENABLED',
                        'values' => array(
                            array('id' => 'deposit_on', 'value' => 1, 'label' => $this->l('Activé')),
                            array('id' => 'deposit_off', 'value' => 0, 'label' => $this->l('Désactivé'))
                        ),
                        'desc' => $this->l('Activer le système de cautions avec empreinte CB'),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Taux de caution (%)'),
                        'name' => 'BOOKING_DEPOSIT_RATE',
                        'suffix' => '%',
                        'class' => 'fixed-width-sm',
                        'desc' => $this->l('Pourcentage du montant total à pré-autoriser en caution'),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Mode test Stripe'),
                        'name' => 'BOOKING_STRIPE_TEST_MODE',
                        'values' => array(
                            array('id' => 'test_on', 'value' => 1, 'label' => $this->l('Test')),
                            array('id' => 'test_off', 'value' => 0, 'label' => $this->l('Live'))
                        ),
                        'desc' => $this->l('Utiliser les clés de test Stripe pour les développements'),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Notifications email'),
                        'name' => 'BOOKING_NOTIFICATIONS_ENABLED',
                        'values' => array(
                            array('id' => 'notif_on', 'value' => 1, 'label' => $this->l('Activé')),
                            array('id' => 'notif_off', 'value' => 0, 'label' => $this->l('Désactivé'))
                        ),
                        'desc' => $this->l('Envoyer des notifications automatiques'),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Mode debug'),
                        'name' => 'BOOKING_DEBUG_MODE',
                        'values' => array(
                            array('id' => 'debug_on', 'value' => 1, 'label' => $this->l('Activé')),
                            array('id' => 'debug_off', 'value' => 0, 'label' => $this->l('Désactivé'))
                        ),
                        'desc' => $this->l('Activer les logs détaillés pour le débogage'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Sauvegarder la configuration'),
                    'class' => 'btn btn-default pull-right'
                ),
            ),
        );
    }

    /**
     * Valeurs du formulaire de configuration - MISE À JOUR v2.1.5
     */
    private function getConfigFormValues()
    {
        return array(
            'BOOKING_MODERN_UI' => Configuration::get('BOOKING_MODERN_UI', 1),
            'BOOKING_STRIPE_DEPOSIT_ENABLED' => Configuration::get('BOOKING_STRIPE_DEPOSIT_ENABLED', 1),
            'BOOKING_DEPOSIT_RATE' => Configuration::get('BOOKING_DEPOSIT_RATE', 30),
            'BOOKING_STRIPE_TEST_MODE' => Configuration::get('BOOKING_STRIPE_TEST_MODE', 1),
            'BOOKING_NOTIFICATIONS_ENABLED' => Configuration::get('BOOKING_NOTIFICATIONS_ENABLED', 1),
            'BOOKING_DEBUG_MODE' => Configuration::get('BOOKING_DEBUG_MODE', 0),
        );
    }
}
