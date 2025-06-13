<?php 
if (!defined('_PS_VERSION_')) {
    exit;
}
error_reporting(E_ALL & ~E_NOTICE);
ini_set('error_reporting', E_ALL & ~E_NOTICE);

require_once (dirname(__FILE__). '/classes/Booker.php');
require_once (dirname(__FILE__). '/classes/BookerAuth.php');
require_once (dirname(__FILE__). '/classes/BookerAuthReserved.php');

class Booking extends Module  {
	protected $token = "";
	static $base = _DB_NAME_;
	
	public function __construct()
    {
        $this->name = 'booking';
        $this->tab = 'others';
        $this->version = '2.0.0'; // Version améliorée
        $this->author = 'BBb';
        $this->bootstrap = true;
        $this->need_instance = 0;
        
        parent::__construct();

        $this->displayName = $this->l('Système de Réservations Avancé v2');
        $this->description = $this->l('Module complet de gestion de réservations avec calendriers interactifs, statuts avancés, intégration produits et paiement Stripe');
        $this->confirmUninstall = $this->l('Êtes-vous sûr de vouloir désinstaller ce module ? Toutes les données de réservation seront perdues.');
        
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
    }

    /**
     * Installation du module
     */
    public function install()
    {
        if (!parent::install() ||
            !$this->installDB() ||
            !$this->installTab() ||
            !$this->registerHooks() ||
            !$this->installConfiguration()) {
            return false;
        }
        
        $this->addCronTask();
        $this->createBookingCMSPage();
        
        return true;
    }

    /**
     * Désinstallation du module
     */
    public function uninstall()
    {
        if (!parent::uninstall() ||
            !$this->uninstallDB() ||
            !$this->uninstallTab() ||
            !$this->uninstallConfiguration()) {
            return false;
        }
        
        $this->removeCronTask();
        
        return true;
    }
    
    /**
     * Enregistrer les hooks
     */
    private function registerHooks()
    {
        return $this->registerHook('displayBackOfficeHeader') &&
               $this->registerHook('displayHeader') &&
               $this->registerHook('actionFrontControllerSetMedia') &&
               $this->registerHook('displayCMSDisputeInformation') &&
               $this->registerHook('actionCronJob') &&
               $this->registerHook('actionProductSave') &&
               $this->registerHook('actionOrderStatusUpdate') &&
               $this->registerHook('displayProductActions') &&
               $this->registerHook('actionBookingPaymentSuccess') &&
               $this->registerHook('actionBookingPaymentFailed') &&
               $this->registerHook('actionBookingValidated') &&
               $this->registerHook('actionBookingCancelled');
    }
    
    /**
     * Installation de la base de données
     */
    private function installDB()
    {
        $queries = [
            // Table des éléments réservables (maintenant liée aux produits)
            "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "booker` (
                `id_booker` int(11) NOT NULL AUTO_INCREMENT,
                `id_product` int(11) NOT NULL DEFAULT 0,
                `name` varchar(255) DEFAULT NULL,
                `description` text,
                `location` varchar(255) DEFAULT NULL,
                `capacity` int(3) DEFAULT 1,
                `price` decimal(10,2) DEFAULT 50.00,
                `deposit_required` tinyint(1) DEFAULT 1,
                `deposit_amount` decimal(10,2) DEFAULT 0.00,
                `auto_confirm` tinyint(1) DEFAULT 0,
                `booking_duration` int(3) DEFAULT 60,
                `min_booking_time` int(3) DEFAULT 24,
                `max_booking_days` int(3) DEFAULT 30,
                `google_account` varchar(255) DEFAULT NULL,
                `active` tinyint(1) DEFAULT 1,
                `date_add` datetime NOT NULL,
                `date_upd` datetime NOT NULL,
                PRIMARY KEY (`id_booker`),
                KEY `id_product` (`id_product`),
                KEY `active` (`active`)
            ) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=utf8;",
            
            // Table des disponibilités
            "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "booker_auth` (
                `id_auth` int(11) NOT NULL AUTO_INCREMENT,
                `id_booker` int(11) NOT NULL,
                `date_start` datetime NOT NULL,
                `date_end` datetime NOT NULL,
                `is_available` tinyint(1) DEFAULT 1,
                `max_bookings` int(3) DEFAULT 1,
                `current_bookings` int(3) DEFAULT 0,
                `price_override` decimal(10,2) DEFAULT NULL,
                `notes` text,
                `date_add` datetime NOT NULL,
                `date_upd` datetime NOT NULL,
                PRIMARY KEY (`id_auth`),
                KEY `id_booker` (`id_booker`),
                KEY `date_range` (`date_start`, `date_end`),
                KEY `available` (`is_available`)
            ) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=utf8;",
            
            // Table des réservations avec statuts avancés
            "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "booker_auth_reserved` (
                `id_reserved` int(11) NOT NULL AUTO_INCREMENT,
                `id_auth` int(11) NOT NULL,
                `id_booker` int(11) NOT NULL,
                `id_customer` int(11) DEFAULT NULL,
                `id_order` int(11) DEFAULT NULL,
                `booking_reference` varchar(50) NOT NULL,
                `customer_firstname` varchar(100) DEFAULT NULL,
                `customer_lastname` varchar(100) DEFAULT NULL,
                `customer_email` varchar(255) DEFAULT NULL,
                `customer_phone` varchar(20) DEFAULT NULL,
                `date_start` datetime NOT NULL,
                `date_end` datetime NOT NULL,
                `status` int(2) DEFAULT 0,
                `total_price` decimal(10,2) DEFAULT NULL,
                `deposit_paid` decimal(10,2) DEFAULT 0.00,
                `stripe_payment_intent` varchar(255) DEFAULT NULL,
                `stripe_setup_intent` varchar(255) DEFAULT NULL,
                `notes` text,
                `admin_notes` text,
                `date_reserved` datetime NOT NULL,
                `date_expiry` datetime DEFAULT NULL,
                `date_confirmed` datetime DEFAULT NULL,
                `date_upd` datetime NOT NULL,
                PRIMARY KEY (`id_reserved`),
                UNIQUE KEY `booking_reference` (`booking_reference`),
                KEY `id_auth` (`id_auth`),
                KEY `id_booker` (`id_booker`),
                KEY `id_customer` (`id_customer`),
                KEY `id_order` (`id_order`),
                KEY `status` (`status`),
                KEY `date_range` (`date_start`, `date_end`)
            ) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=utf8;",
            
            // Table de liaison booker-produit
            "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "booker_product` (
                `id_booker` int(10) unsigned NOT NULL,
                `id_product` int(10) unsigned NOT NULL,
                `date_add` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id_booker`),
                UNIQUE KEY `unique_product` (`id_product`)
            ) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=utf8;",
            
            // Table de liaison réservation-commande
            "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "booker_reservation_order` (
                `id_reservation` int(10) unsigned NOT NULL,
                `id_order` int(10) unsigned NOT NULL,
                `date_add` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id_reservation`),
                UNIQUE KEY `unique_order` (`id_order`)
            ) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=utf8;"
        ];

        foreach ($queries as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }
        
        // Mise à jour des tables existantes si nécessaire
        $this->updateExistingTables();
        
        return true;
    }
    
    /**
     * Mise à jour des tables existantes
     */
    private function updateExistingTables()
    {
        $alterQueries = [
            "ALTER TABLE `" . _DB_PREFIX_ . "booker` 
             ADD COLUMN IF NOT EXISTS `id_product` int(11) NOT NULL DEFAULT 0 AFTER `id_booker`,
             ADD COLUMN IF NOT EXISTS `location` varchar(255) DEFAULT NULL AFTER `description`,
             ADD COLUMN IF NOT EXISTS `capacity` int(3) DEFAULT 1 AFTER `location`,
             ADD COLUMN IF NOT EXISTS `price` DECIMAL(10,2) DEFAULT 50.00 AFTER `capacity`,
             ADD COLUMN IF NOT EXISTS `deposit_required` TINYINT(1) DEFAULT 1 AFTER `price`,
             ADD COLUMN IF NOT EXISTS `deposit_amount` DECIMAL(10,2) DEFAULT 0.00 AFTER `deposit_required`,
             ADD COLUMN IF NOT EXISTS `auto_confirm` TINYINT(1) DEFAULT 0 AFTER `deposit_amount`,
             ADD COLUMN IF NOT EXISTS `booking_duration` INT(3) DEFAULT 60 AFTER `auto_confirm`,
             ADD COLUMN IF NOT EXISTS `min_booking_time` INT(3) DEFAULT 24 AFTER `booking_duration`,
             ADD COLUMN IF NOT EXISTS `max_booking_days` INT(3) DEFAULT 30 AFTER `min_booking_time`,
             ADD INDEX IF NOT EXISTS `id_product` (`id_product`)",
             
            "ALTER TABLE `" . _DB_PREFIX_ . "booker_auth` 
             ADD COLUMN IF NOT EXISTS `is_available` TINYINT(1) DEFAULT 1 AFTER `date_end`,
             ADD COLUMN IF NOT EXISTS `max_bookings` INT(3) DEFAULT 1 AFTER `is_available`,
             ADD COLUMN IF NOT EXISTS `current_bookings` INT(3) DEFAULT 0 AFTER `max_bookings`,
             ADD COLUMN IF NOT EXISTS `price_override` DECIMAL(10,2) DEFAULT NULL AFTER `current_bookings`,
             ADD COLUMN IF NOT EXISTS `notes` TEXT AFTER `price_override`,
             ADD INDEX IF NOT EXISTS `date_range` (`date_start`, `date_end`),
             ADD INDEX IF NOT EXISTS `available` (`is_available`)",
             
            "ALTER TABLE `" . _DB_PREFIX_ . "booker_auth_reserved` 
             ADD COLUMN IF NOT EXISTS `id_customer` INT(11) DEFAULT NULL AFTER `id_booker`,
             ADD COLUMN IF NOT EXISTS `id_order` INT(11) DEFAULT NULL AFTER `id_customer`,
             ADD COLUMN IF NOT EXISTS `booking_reference` VARCHAR(50) NOT NULL AFTER `id_order`,
             ADD COLUMN IF NOT EXISTS `customer_phone` VARCHAR(20) DEFAULT NULL AFTER `customer_email`,
             ADD COLUMN IF NOT EXISTS `date_start` DATETIME NOT NULL AFTER `customer_phone`,
             ADD COLUMN IF NOT EXISTS `date_end` DATETIME NOT NULL AFTER `date_start`,
             ADD COLUMN IF NOT EXISTS `total_price` DECIMAL(10,2) DEFAULT NULL AFTER `status`,
             ADD COLUMN IF NOT EXISTS `deposit_paid` DECIMAL(10,2) DEFAULT 0.00 AFTER `total_price`,
             ADD COLUMN IF NOT EXISTS `stripe_payment_intent` VARCHAR(255) DEFAULT NULL AFTER `deposit_paid`,
             ADD COLUMN IF NOT EXISTS `stripe_setup_intent` VARCHAR(255) DEFAULT NULL AFTER `stripe_payment_intent`,
             ADD COLUMN IF NOT EXISTS `admin_notes` TEXT AFTER `notes`,
             ADD COLUMN IF NOT EXISTS `date_expiry` DATETIME DEFAULT NULL AFTER `date_reserved`,
             ADD COLUMN IF NOT EXISTS `date_confirmed` DATETIME DEFAULT NULL AFTER `date_expiry`,
             ADD UNIQUE INDEX IF NOT EXISTS `booking_reference` (`booking_reference`),
             ADD INDEX IF NOT EXISTS `id_customer` (`id_customer`),
             ADD INDEX IF NOT EXISTS `id_order` (`id_order`),
             ADD INDEX IF NOT EXISTS `date_range` (`date_start`, `date_end`)"
        ];
        
        foreach ($alterQueries as $query) {
            try {
                Db::getInstance()->execute($query);
            } catch (Exception $e) {
                PrestaShopLogger::addLog('Erreur mise à jour table: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Configuration par défaut
     */
    private function installConfiguration()
    {
        $configs = [
            'BOOKING_DEFAULT_PRICE' => 50.00,
            'BOOKING_DEPOSIT_AMOUNT' => 20.00,
            'BOOKING_PAYMENT_ENABLED' => 1,
            'BOOKING_STRIPE_ENABLED' => 0,
            'BOOKING_SAVE_CARDS' => 0,
            'BOOKING_STRIPE_HOLD_DEPOSIT' => 0,
            'BOOKING_AUTO_CONFIRM' => 0,
            'BOOKING_EXPIRY_HOURS' => 24,
            'BOOKING_MULTI_SELECT' => 1,
            'BOOKING_EMERGENCY_PHONE' => '',
            'BOOKING_CRON_CLEAN_RESERVATIONS' => 1,
            'BOOKING_MIN_BOOKING_TIME' => 24,
            'BOOKING_MAX_BOOKING_DAYS' => 30,
            'BOOKING_DEFAULT_DURATION' => 60,
            'BOOKING_BUSINESS_HOURS_START' => '08:00',
            'BOOKING_BUSINESS_HOURS_END' => '18:00',
            'BOOKING_ALLOWED_DAYS' => '1,2,3,4,5,6', // Lundi à Samedi
            'BOOKING_PAYMENT_EXPIRY_MINUTES' => 30,
            'BOOKING_AUTO_CREATE_ORDER' => 0,
            'BOOKING_ORDER_STATUS' => Configuration::get('PS_OS_PREPARATION'),
            'BOOKING_NOTIFICATIONS_ENABLED' => 1,
            'BOOKING_AUTO_CONFIRMATION_EMAIL' => 1,
            'BOOKING_AUTO_REMINDERS' => 0,
            'BOOKING_REMINDER_HOURS' => 24,
            'BOOKING_ADMIN_NOTIFICATIONS' => 1,
            'BOOKING_ADMIN_EMAIL' => Configuration::get('PS_SHOP_EMAIL'),
            'BOOKING_SYNC_PRODUCT_PRICE' => 0,
            'BOOKING_DEBUG_MODE' => 0,
        ];
        
        foreach ($configs as $key => $value) {
            if (!Configuration::get($key)) {
                Configuration::updateValue($key, $value);
            }
        }
        
        return true;
    }
    
    /**
     * Installation des onglets admin
     */
    private function installTab()
    {
        // Onglet principal RESERVATIONS
        $tabs = Tab::getTabs(1);
        $position = 0;
        foreach ($tabs as $tab) {
            $position = max($position, $tab["position"]);
        }
        $position++;
        
        $tab_id = Tab::getIdFromClassName('BOOKING');
        $languages = Language::getLanguages(false);

        if ($tab_id == false) {
            $tab = new Tab();
            $tab->class_name = 'BOOKING';
            $tab->position = $position;
            $tab->id_parent = 0;
            $tab->module = null;
            $tab->wording = "RESERVATIONS";
            $tab->wording_domain = "Admin.Navigation.Menu";
            foreach ($languages as $language) {
                $tab->name[$language['id_lang']] = "RESERVATIONS";
            }
            $tab->add();
        }
        
        // Sous-onglets du système mis à jour
        $tabsToCreate = [
            'AdminBooker' => ['Éléments & Produits', 3],
            'AdminBookerAuth' => ['Disponibilités', 4],
            'AdminBookerAuthReserved' => ['Réservations', 5],
            'AdminBookerAvailabilityCalendar' => ['📅 Calendrier Disponibilités', 6],
            'AdminBookerReservationCalendar' => ['📋 Calendrier Réservations', 7],
            'AdminBookerSettings' => ['⚙️ Configuration', 8],
        ];
        
        foreach ($tabsToCreate as $className => $tabInfo) {
            $tab_id = Tab::getIdFromClassName($className);
            if ($tab_id == false) {
                $tab = new Tab();
                $tab->class_name = $className;
                $tab->position = $tabInfo[1];
                $tab->id_parent = (int)Tab::getIdFromClassName('BOOKING');
                $tab->module = $this->name;
                foreach ($languages as $language) {
                    $tab->name[$language['id_lang']] = $tabInfo[0];
                }
                $tab->add();
            }
        }
        
        return true;
    }
    
    /**
     * Désinstallation des onglets
     */
    private function uninstallTab()
    {
        $tabs_to_remove = [
            'BOOKING', 'AdminBooker', 'AdminBookerAuth', 
            'AdminBookerAuthReserved', 'AdminBookerAvailabilityCalendar', 
            'AdminBookerReservationCalendar', 'AdminBookerSettings',
            // Anciens noms pour compatibilité
            'AdminBookerView', 'AdminBookerCalendar', 'AdminBookerCalendarAvailability',
            'AdminBookerCalendarReservations'
        ];
        
        foreach ($tabs_to_remove as $tab_class) {
            $tab_id = (int)Tab::getIdFromClassName($tab_class);
            if ($tab_id) {
                $tab = new Tab($tab_id);
                try {
                    $tab->delete();
                } catch (Exception $e) {
                    PrestaShopLogger::addLog('Erreur suppression onglet ' . $tab_class . ': ' . $e->getMessage());
                }
            }
        }
        
        return true;
    }
    
    /**
     * Désinstallation de la base de données
     */
    private function uninstallDB()
    {
        $tables = [
            'booker_reservation_order',
            'booker_product',
            'booker_auth_reserved',
            'booker_auth', 
            'booker'
        ];
        
        foreach ($tables as $table) {
            if (!Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . $table . '`')) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Désinstallation de la configuration
     */
    private function uninstallConfiguration()
    {
        $configs = [
            'BOOKING_DEFAULT_PRICE', 'BOOKING_DEPOSIT_AMOUNT', 'BOOKING_PAYMENT_ENABLED',
            'BOOKING_STRIPE_ENABLED', 'BOOKING_SAVE_CARDS', 'BOOKING_STRIPE_HOLD_DEPOSIT',
            'BOOKING_AUTO_CONFIRM', 'BOOKING_EXPIRY_HOURS', 'BOOKING_MULTI_SELECT',
            'BOOKING_EMERGENCY_PHONE', 'BOOKING_CRON_CLEAN_RESERVATIONS', 'BOOKING_MIN_BOOKING_TIME',
            'BOOKING_MAX_BOOKING_DAYS', 'BOOKING_DEFAULT_DURATION', 'BOOKING_BUSINESS_HOURS_START',
            'BOOKING_BUSINESS_HOURS_END', 'BOOKING_ALLOWED_DAYS', 'BOOKING_PAYMENT_EXPIRY_MINUTES',
            'BOOKING_AUTO_CREATE_ORDER', 'BOOKING_ORDER_STATUS', 'BOOKING_NOTIFICATIONS_ENABLED',
            'BOOKING_AUTO_CONFIRMATION_EMAIL', 'BOOKING_AUTO_REMINDERS', 'BOOKING_REMINDER_HOURS',
            'BOOKING_ADMIN_NOTIFICATIONS', 'BOOKING_ADMIN_EMAIL', 'BOOKING_SYNC_PRODUCT_PRICE',
            'BOOKING_DEBUG_MODE', 'BOOKING_CMS_ID'
        ];
        
        foreach ($configs as $config) {
            Configuration::deleteByName($config);
        }
        
        return true;
    }
    
    /**
     * Configuration du module
     */
    public function getContent()
    {
        $output = '';
        
        if (Tools::isSubmit('submit' . $this->name)) {
            $this->processConfiguration();
            $output .= $this->displayConfirmation($this->l('Configuration mise à jour'));
        }
        
        // Liens rapides
        $output .= $this->renderQuickLinks();
        
        // Formulaire de configuration simplifié (la config complète est dans AdminBookerSettings)
        $output .= $this->displayForm();
        
        // Statistiques des réservations
        $output .= $this->displayReservationStats();
        
        return $output;
    }
    
    /**
     * Traitement de la configuration
     */
    private function processConfiguration()
    {
        $configFields = [
            'BOOKING_DEFAULT_PRICE',
            'BOOKING_DEPOSIT_AMOUNT', 
            'BOOKING_PAYMENT_ENABLED',
            'BOOKING_STRIPE_ENABLED',
            'BOOKING_AUTO_CONFIRM',
            'BOOKING_EXPIRY_HOURS',
            'BOOKING_MULTI_SELECT',
            'BOOKING_EMERGENCY_PHONE'
        ];
        
        foreach ($configFields as $field) {
            Configuration::updateValue($field, Tools::getValue($field));
        }
    }
    
    /**
     * Liens rapides d'administration
     */
    private function renderQuickLinks()
    {
        $links = [
            [
                'title' => 'Gérer les éléments',
                'desc' => 'Créer et modifier les éléments à réserver',
                'href' => $this->context->link->getAdminLink('AdminBooker'),
                'icon' => 'icon-cog'
            ],
            [
                'title' => 'Disponibilités',
                'desc' => 'Définir les créneaux de disponibilité',
                'href' => $this->context->link->getAdminLink('AdminBookerAuth'),
                'icon' => 'icon-calendar'
            ],
            [
                'title' => 'Réservations',
                'desc' => 'Gérer les demandes de réservation',
                'href' => $this->context->link->getAdminLink('AdminBookerAuthReserved'),
                'icon' => 'icon-list'
            ],
            [
                'title' => 'Calendrier Disponibilités',
                'desc' => 'Vue calendrier des disponibilités',
                'href' => $this->context->link->getAdminLink('AdminBookerAvailabilityCalendar'),
                'icon' => 'icon-calendar-plus-o'
            ],
            [
                'title' => 'Calendrier Réservations',
                'desc' => 'Vue calendrier des réservations',
                'href' => $this->context->link->getAdminLink('AdminBookerReservationCalendar'),
                'icon' => 'icon-calendar-check-o'
            ],
            [
                'title' => 'Configuration complète',
                'desc' => 'Tous les paramètres du module',
                'href' => $this->context->link->getAdminLink('AdminBookerSettings'),
                'icon' => 'icon-gear'
            ]
        ];
        
        $html = '<div class="panel"><div class="panel-heading">
            <i class="icon-cogs"></i> Accès rapide
        </div><div class="panel-body">
            <div class="row">';
        
        foreach ($links as $link) {
            $html .= '<div class="col-lg-2 col-md-4 col-sm-6">
                <div class="media">
                    <div class="media-left">
                        <i class="' . $link['icon'] . ' fa-2x"></i>
                    </div>
                    <div class="media-body">
                        <h5 class="media-heading">
                            <a href="' . $link['href'] . '" class="btn btn-link" style="padding: 0;">
                                ' . $link['title'] . '
                            </a>
                        </h5>
                        <small>' . $link['desc'] . '</small>
                    </div>
                </div>
            </div>';
        }
        
        $html .= '</div>
            <div class="alert alert-info">
                <strong>ℹ️ Note :</strong> Pour une configuration complète, utilisez l\'onglet 
                <a href="' . $this->context->link->getAdminLink('AdminBookerSettings') . '">Configuration</a> 
                dans le menu Réservations.
            </div>
        </div></div>';
        
        return $html;
    }
    
    /**
     * Formulaire de configuration (simplifié)
     */
    public function displayForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Configuration rapide'),
                    'icon' => 'icon-cogs'
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('Prix par défaut'),
                        'name' => 'BOOKING_DEFAULT_PRICE',
                        'suffix' => '€',
                        'class' => 'fixed-width-sm',
                        'desc' => $this->l('Prix par défaut pour une réservation')
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Paiement activé'),
                        'name' => 'BOOKING_PAYMENT_ENABLED',
                        'values' => array(
                            array('id' => 'active_on', 'value' => 1, 'label' => $this->l('Oui')),
                            array('id' => 'active_off', 'value' => 0, 'label' => $this->l('Non'))
                        )
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Stripe activé'),
                        'name' => 'BOOKING_STRIPE_ENABLED',
                        'values' => array(
                            array('id' => 'stripe_on', 'value' => 1, 'label' => $this->l('Oui')),
                            array('id' => 'stripe_off', 'value' => 0, 'label' => $this->l('Non'))
                        ),
                        'desc' => $this->l('Nécessite le module Stripe pour PrestaShop')
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Confirmation automatique'),
                        'name' => 'BOOKING_AUTO_CONFIRM',
                        'values' => array(
                            array('id' => 'auto_on', 'value' => 1, 'label' => $this->l('Oui')),
                            array('id' => 'auto_off', 'value' => 0, 'label' => $this->l('Non'))
                        ),
                        'desc' => $this->l('Confirmer automatiquement les réservations')
                    )
                ),
                'submit' => array(
                    'title' => $this->l('Enregistrer'),
                    'class' => 'btn btn-default pull-right'
                )
            )
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->module = $this;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submit' . $this->name;
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );

        return $helper->generateForm(array($fields_form));
    }
    
    /**
     * Valeurs des champs de configuration
     */
    public function getConfigFieldsValues()
    {
        return array(
            'BOOKING_DEFAULT_PRICE' => Tools::getValue('BOOKING_DEFAULT_PRICE', Configuration::get('BOOKING_DEFAULT_PRICE')),
            'BOOKING_PAYMENT_ENABLED' => Tools::getValue('BOOKING_PAYMENT_ENABLED', Configuration::get('BOOKING_PAYMENT_ENABLED')),
            'BOOKING_STRIPE_ENABLED' => Tools::getValue('BOOKING_STRIPE_ENABLED', Configuration::get('BOOKING_STRIPE_ENABLED')),
            'BOOKING_AUTO_CONFIRM' => Tools::getValue('BOOKING_AUTO_CONFIRM', Configuration::get('BOOKING_AUTO_CONFIRM'))
        );
    }
    
    /**
     * Statistiques des réservations
     */
    private function displayReservationStats()
    {
        try {
            $stats = [
                [
                    'count' => (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` WHERE status = 0'),
                    'label' => $this->l('En attente'),
                    'class' => 'warning'
                ],
                [
                    'count' => (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` WHERE status = 1'),
                    'label' => $this->l('Validées'),
                    'class' => 'info'
                ],
                [
                    'count' => (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` WHERE status = 2'),
                    'label' => $this->l('Payées'),
                    'class' => 'success'
                ],
                [
                    'count' => (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` WHERE date_start > NOW() AND status IN (1,2)'),
                    'label' => $this->l('À venir'),
                    'class' => 'primary'
                ]
            ];
        } catch (Exception $e) {
            return '<div class="alert alert-danger">' . $this->l('Erreur lors du chargement des statistiques') . '</div>';
        }
        
        $html = '<div class="panel">
            <div class="panel-heading">
                <i class="icon-bar-chart"></i> ' . $this->l('Statistiques des réservations') . '
            </div>
            <div class="panel-body">
                <div class="row">';
        
        foreach ($stats as $stat) {
            $alert_class = 'alert-' . $stat['class'];
            $html .= '<div class="col-lg-3 col-md-6">
                <div class="alert ' . $alert_class . ' text-center">
                    <div style="font-size: 2em; font-weight: bold;">' . $stat['count'] . '</div>
                    <div>' . $stat['label'] . '</div>
                </div>
            </div>';
        }
        
        $html .= '</div></div></div>';
        
        return $html;
    }
    
    /**
     * Créer une page CMS pour les réservations
     */
    private function createBookingCMSPage()
    {
        $cms = new CMS();
        $languages = Language::getLanguages(false);
        
        foreach ($languages as $language) {
            $cms->meta_title[$language['id_lang']] = 'Réservation en ligne';
            $cms->meta_description[$language['id_lang']] = 'Effectuez votre réservation en ligne';
            $cms->content[$language['id_lang']] = '<p>Le module se chargera automatiquement sur cette page.</p>';
            $cms->link_rewrite[$language['id_lang']] = 'reservation-en-ligne';
        }
        
        if ($cms->add()) {
            Configuration::updateValue('BOOKING_CMS_ID', $cms->id);
            return true;
        }
        
        return false;
    }
    
    // Gestion des tâches cron
    private function addCronTask()
    {
        Configuration::updateValue('BOOKING_CRON_CLEAN_RESERVATIONS', 1);
    }
    
    private function removeCronTask()
    {
        Configuration::deleteByName('BOOKING_CRON_CLEAN_RESERVATIONS');
    }
    
    /**
     * Hook pour les tâches cron
     */
    public function hookActionCronJob($params)
    {
        if (Configuration::get('BOOKING_CRON_CLEAN_RESERVATIONS')) {
            $expiry_hours = Configuration::get('BOOKING_EXPIRY_HOURS') ?: 24;
            BookerAuthReserved::cancelExpiredReservations($expiry_hours);
            
            PrestaShopLogger::addLog(
                'Nettoyage automatique des réservations expirées effectué',
                1,
                null,
                'BookerAuthReserved',
                null,
                true
            );
        }
    }
    
    /**
     * Hook pour afficher l'interface de réservation sur la page CMS
     */
    public function hookDisplayCMSDisputeInformation($params)
    {
        $cms_id = Configuration::get('BOOKING_CMS_ID');
        if ('cms' === $this->context->controller->php_self && 
            $this->context->controller->cms->id_cms == $cms_id) {
            
            Tools::redirect($this->context->link->getModuleLink('booking', 'booking'));
        }
    }
    
    /**
     * Hook pour ajouter les médias sur les pages front
     */
    public function hookActionFrontControllerSetMedia($params)
    {
        $this->context->controller->registerStylesheet(
            'booking-notifications',
            'modules/'.$this->name.'/views/css/booking-notifications.css',
            ['media' => 'all', 'priority' => 100]
        );
    }
    
    /**
     * Hook pour ajouter des médias dans le header admin
     */
    public function hookDisplayBackOfficeHeader($params)
    {        
        $this->context->controller->addJS('modules/'.$this->name.'/js/tabs.js');
        
        $controller = Tools::getValue("controller");
        if (strpos($controller, 'AdminBooker') !== false) {
            $this->context->controller->addCSS('modules/'.$this->name.'/views/css/AdminBooker.css');
        }
    }
    
    /**
     * Hook header pour ajouter des variables JS globales
     */
    public function hookDisplayHeader($params)
    {
        Media::addJsDef([
            'booking_module_url' => $this->context->link->getModuleLink('booking', 'booking'),
            'booking_ajax_url' => $this->context->link->getModuleLink('booking', 'booking'),
        ]);
    }
    
    /**
     * Hook lors de la sauvegarde d'un produit
     */
    public function hookActionProductSave($params)
    {
        // Synchroniser les données Booker avec le produit si nécessaire
        if (isset($params['id_product'])) {
            $this->syncProductWithBooker($params['id_product']);
        }
    }
    
    /**
     * Hook lors du changement de statut de commande
     */
    public function hookActionOrderStatusUpdate($params)
    {
        // Gérer les changements de statut des commandes liées aux réservations
        $this->handleOrderStatusChange($params);
    }
    
    /**
     * Synchronisation produit/booker
     */
    private function syncProductWithBooker($id_product)
    {
        // À implémenter : synchronisation automatique
    }
    
    /**
     * Gestion des changements de statut de commande
     */
    private function handleOrderStatusChange($params)
    {
        // À implémenter : logique de gestion des statuts
    }
}