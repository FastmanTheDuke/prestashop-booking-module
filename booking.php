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
        $this->version = '2.1.0'; // Version mise à jour
        $this->author = 'BBb';
        $this->bootstrap = true;
        $this->need_instance = 0;
        
        parent::__construct();

        $this->displayName = $this->l('Système de Réservations Avancé v2.1');
        $this->description = $this->l('Module complet de gestion de réservations avec calendriers interactifs doubles, statuts avancés, intégration produits et paiement Stripe avec caution');
        $this->confirmUninstall = $this->l('Êtes-vous sûr de vouloir désinstaller ce module ? Toutes les données de réservation seront perdues.');

        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
    }

    /**
     * Installation du module
     */
    public function install()
    {
        return parent::install() && 
               $this->installDB() && 
               $this->installTab() && 
               $this->installConfiguration() &&
               $this->registerHooks();
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
     * Enregistrement des hooks
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
               $this->registerHook('actionProductSave');
    }

    /**
     * Installation de la base de données
     */
    private function installDB()
    {
        $sql = array();

        // Table des éléments réservables (bookers)
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'booker` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `id_product` int(11) DEFAULT NULL,
            `name` varchar(255) NOT NULL,
            `description` text,
            `price` decimal(10,2) DEFAULT 0.00,
            `duration` int(11) DEFAULT 60,
            `max_bookings` int(11) DEFAULT 1,
            `active` tinyint(1) DEFAULT 1,
            `auto_confirm` tinyint(1) DEFAULT 0,
            `require_deposit` tinyint(1) DEFAULT 0,
            `deposit_amount` decimal(10,2) DEFAULT 0.00,
            `cancellation_hours` int(11) DEFAULT 24,
            `image` varchar(255) DEFAULT NULL,
            `sort_order` int(11) DEFAULT 0,
            `date_add` datetime NOT NULL,
            `date_upd` datetime NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_product` (`id_product`),
            KEY `idx_active` (`active`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';

        // Table des disponibilités
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'booker_auth` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `id_booker` int(11) NOT NULL,
            `date_from` datetime NOT NULL,
            `date_to` datetime NOT NULL,
            `time_from` time NOT NULL,
            `time_to` time NOT NULL,
            `max_bookings` int(11) DEFAULT 1,
            `current_bookings` int(11) DEFAULT 0,
            `price_override` decimal(10,2) DEFAULT NULL,
            `active` tinyint(1) DEFAULT 1,
            `recurring` tinyint(1) DEFAULT 0,
            `recurring_type` enum("daily","weekly","monthly") DEFAULT NULL,
            `recurring_end` date DEFAULT NULL,
            `notes` text,
            `date_add` datetime NOT NULL,
            `date_upd` datetime NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_booker` (`id_booker`),
            KEY `idx_date_range` (`date_from`, `date_to`),
            KEY `idx_active` (`active`),
            CONSTRAINT `fk_booker_auth_booker` FOREIGN KEY (`id_booker`) REFERENCES `' . _DB_PREFIX_ . 'booker` (`id`) ON DELETE CASCADE
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';

        // Table des réservations
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'booker_auth_reserved` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `id_auth` int(11) NOT NULL,
            `id_booker` int(11) NOT NULL,
            `id_customer` int(11) DEFAULT NULL,
            `id_order` int(11) DEFAULT NULL,
            `booking_reference` varchar(50) NOT NULL UNIQUE,
            `customer_firstname` varchar(100) NOT NULL,
            `customer_lastname` varchar(100) NOT NULL,
            `customer_email` varchar(150) NOT NULL,
            `customer_phone` varchar(50) DEFAULT NULL,
            `date_start` datetime NOT NULL,
            `date_end` datetime NOT NULL,
            `total_price` decimal(10,2) DEFAULT 0.00,
            `deposit_paid` decimal(10,2) DEFAULT 0.00,
            `status` enum("pending","confirmed","paid","cancelled","completed","refunded") DEFAULT "pending",
            `payment_status` enum("pending","authorized","captured","cancelled","refunded") DEFAULT "pending",
            `stripe_payment_intent_id` varchar(255) DEFAULT NULL,
            `stripe_deposit_intent_id` varchar(255) DEFAULT NULL,
            `notes` text,
            `admin_notes` text,
            `date_add` datetime NOT NULL,
            `date_upd` datetime NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_auth` (`id_auth`),
            KEY `idx_booker` (`id_booker`),
            KEY `idx_customer` (`id_customer`),
            KEY `idx_order` (`id_order`),
            KEY `idx_status` (`status`),
            KEY `idx_reference` (`booking_reference`),
            KEY `idx_date_range` (`date_start`, `date_end`),
            CONSTRAINT `fk_reserved_auth` FOREIGN KEY (`id_auth`) REFERENCES `' . _DB_PREFIX_ . 'booker_auth` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_reserved_booker` FOREIGN KEY (`id_booker`) REFERENCES `' . _DB_PREFIX_ . 'booker` (`id`) ON DELETE CASCADE
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';

        // Table de liaison avec les produits PrestaShop
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'booker_product` (
            `id_booker` int(11) NOT NULL,
            `id_product` int(11) NOT NULL,
            `sync_price` tinyint(1) DEFAULT 1,
            `override_price` decimal(10,2) DEFAULT NULL,
            `date_add` datetime NOT NULL,
            PRIMARY KEY (`id_booker`, `id_product`),
            KEY `idx_product` (`id_product`),
            CONSTRAINT `fk_booker_product_booker` FOREIGN KEY (`id_booker`) REFERENCES `' . _DB_PREFIX_ . 'booker` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_booker_product_product` FOREIGN KEY (`id_product`) REFERENCES `' . _DB_PREFIX_ . 'product` (`id_product`) ON DELETE CASCADE
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';

        // Table pour les commandes de réservation
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'booker_reservation_order` (
            `id_reservation` int(11) NOT NULL,
            `id_order` int(11) NOT NULL,
            `order_type` enum("booking","deposit") DEFAULT "booking",
            `amount` decimal(10,2) NOT NULL,
            `date_add` datetime NOT NULL,
            PRIMARY KEY (`id_reservation`, `id_order`, `order_type`),
            KEY `idx_order` (`id_order`),
            CONSTRAINT `fk_reservation_order_reservation` FOREIGN KEY (`id_reservation`) REFERENCES `' . _DB_PREFIX_ . 'booker_auth_reserved` (`id`) ON DELETE CASCADE
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';

        foreach ($sql as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Installation de la configuration
     */
    private function installConfiguration()
    {
        $configs = array(
            // Paramètres généraux
            'BOOKING_DEFAULT_PRICE' => '50.00',
            'BOOKING_DEPOSIT_AMOUNT' => '20.00',
            'BOOKING_MIN_BOOKING_TIME' => '2', // heures avant
            'BOOKING_MAX_BOOKING_DAYS' => '90', // jours dans le futur
            'BOOKING_DEFAULT_DURATION' => '60', // minutes
            'BOOKING_EXPIRY_HOURS' => '24',
            'BOOKING_AUTO_CONFIRM' => '0',
            'BOOKING_MULTI_SELECT' => '1',
            'BOOKING_EMERGENCY_PHONE' => '',
            
            // Paramètres de paiement
            'BOOKING_PAYMENT_ENABLED' => '1',
            'BOOKING_STRIPE_ENABLED' => '0',
            'BOOKING_SAVE_CARDS' => '1',
            'BOOKING_STRIPE_HOLD_DEPOSIT' => '1',
            'BOOKING_PAYMENT_EXPIRY_MINUTES' => '30',
            'BOOKING_AUTO_CREATE_ORDER' => '1',
            'BOOKING_ORDER_STATUS' => Configuration::get('PS_OS_PREPARATION'),
            
            // Notifications
            'BOOKING_NOTIFICATIONS_ENABLED' => '1',
            'BOOKING_AUTO_CONFIRMATION_EMAIL' => '1',
            'BOOKING_AUTO_REMINDERS' => '1',
            'BOOKING_REMINDER_HOURS' => '24',
            'BOOKING_ADMIN_NOTIFICATIONS' => '1',
            'BOOKING_ADMIN_EMAIL' => Configuration::get('PS_SHOP_EMAIL'),
            
            // Paramètres avancés
            'BOOKING_CRON_CLEAN_RESERVATIONS' => '1',
            'BOOKING_SYNC_PRODUCT_PRICE' => '1',
            'BOOKING_BUSINESS_HOURS_START' => '08:00',
            'BOOKING_BUSINESS_HOURS_END' => '18:00',
            'BOOKING_ALLOWED_DAYS' => '1,2,3,4,5,6,7', // Tous les jours
            'BOOKING_DEBUG_MODE' => '0',
            
            // Page CMS pour réservations
            'BOOKING_CMS_ID' => '0'
        );

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
        // Supprimer les anciens onglets si ils existent
        $this->uninstallTab();
        
        // Onglet principal RESERVATIONS
        $tabs = Tab::getTabs(1);
        $position = 0;
        foreach ($tabs as $tab) {
            $position = max($position, $tab["position"]);
        }
        $position++;
        
        $languages = Language::getLanguages(false);
        
        // Créer l'onglet parent
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
        if (!$tab->add()) {
            return false;
        }
        
        $parent_id = $tab->id;
        
        // Sous-onglets du système
        $tabsToCreate = [
            'AdminBooker' => ['📋 Éléments & Produits', 1, 'Gérer les éléments à réserver'],
            'AdminBookerAuth' => ['⏰ Disponibilités', 2, 'Définir les créneaux disponibles'],
            'AdminBookerAuthReserved' => ['🎫 Réservations', 3, 'Gérer les demandes clients'],
            'AdminBookerAvailabilityCalendar' => ['📅 Calendrier Disponibilités', 4, 'Vue calendrier des disponibilités'],
            'AdminBookerReservationCalendar' => ['📋 Calendrier Réservations', 5, 'Vue calendrier des réservations'],
            'AdminBookerSettings' => ['⚙️ Configuration', 6, 'Paramètres du module'],
        ];
        
        foreach ($tabsToCreate as $className => $tabInfo) {
            $tab = new Tab();
            $tab->class_name = $className;
            $tab->position = $tabInfo[1];
            $tab->id_parent = $parent_id;
            $tab->module = $this->name;
            foreach ($languages as $language) {
                $tab->name[$language['id_lang']] = $tabInfo[0];
            }
            if (!$tab->add()) {
                PrestaShopLogger::addLog('Erreur création onglet: ' . $className, 3);
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
     * Désinstallation de la configuration
     */
    private function uninstallConfiguration()
    {
        $config_keys = [
            'BOOKING_DEFAULT_PRICE', 'BOOKING_DEPOSIT_AMOUNT', 'BOOKING_MIN_BOOKING_TIME',
            'BOOKING_MAX_BOOKING_DAYS', 'BOOKING_DEFAULT_DURATION', 'BOOKING_EXPIRY_HOURS',
            'BOOKING_AUTO_CONFIRM', 'BOOKING_MULTI_SELECT', 'BOOKING_EMERGENCY_PHONE',
            'BOOKING_PAYMENT_ENABLED', 'BOOKING_STRIPE_ENABLED', 'BOOKING_SAVE_CARDS',
            'BOOKING_STRIPE_HOLD_DEPOSIT', 'BOOKING_PAYMENT_EXPIRY_MINUTES', 'BOOKING_AUTO_CREATE_ORDER',
            'BOOKING_ORDER_STATUS', 'BOOKING_NOTIFICATIONS_ENABLED', 'BOOKING_AUTO_CONFIRMATION_EMAIL',
            'BOOKING_AUTO_REMINDERS', 'BOOKING_REMINDER_HOURS', 'BOOKING_ADMIN_NOTIFICATIONS',
            'BOOKING_ADMIN_EMAIL', 'BOOKING_CRON_CLEAN_RESERVATIONS', 'BOOKING_SYNC_PRODUCT_PRICE',
            'BOOKING_BUSINESS_HOURS_START', 'BOOKING_BUSINESS_HOURS_END', 'BOOKING_ALLOWED_DAYS',
            'BOOKING_DEBUG_MODE', 'BOOKING_CMS_ID'
        ];
        
        foreach ($config_keys as $key) {
            Configuration::deleteByName($key);
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
     * Page de configuration du module
     */
    public function getContent()
    {
        $output = '';
        
        // Traitement des données
        if (Tools::isSubmit('submit' . $this->name)) {
            $this->processConfiguration();
            $output .= $this->displayConfirmation($this->l('Configuration mise à jour'));
        }
        
        // Liens rapides d'accès
        $output .= $this->renderQuickLinks();
        
        // Formulaire de configuration simplifié
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
                'title' => '📋 Gérer les éléments',
                'desc' => 'Créer et modifier les éléments à réserver',
                'href' => $this->context->link->getAdminLink('AdminBooker'),
                'icon' => 'icon-cog',
                'class' => 'btn-primary'
            ],
            [
                'title' => '⏰ Disponibilités',
                'desc' => 'Définir les créneaux de disponibilité',
                'href' => $this->context->link->getAdminLink('AdminBookerAuth'),
                'icon' => 'icon-calendar',
                'class' => 'btn-info'
            ],
            [
                'title' => '🎫 Réservations',
                'desc' => 'Gérer les demandes de réservation',
                'href' => $this->context->link->getAdminLink('AdminBookerAuthReserved'),
                'icon' => 'icon-list',
                'class' => 'btn-success'
            ],
            [
                'title' => '📅 Calendrier Disponibilités',
                'desc' => 'Vue calendrier des disponibilités',
                'href' => $this->context->link->getAdminLink('AdminBookerAvailabilityCalendar'),
                'icon' => 'icon-calendar-plus-o',
                'class' => 'btn-warning'
            ],
            [
                'title' => '📋 Calendrier Réservations',
                'desc' => 'Vue calendrier des réservations',
                'href' => $this->context->link->getAdminLink('AdminBookerReservationCalendar'),
                'icon' => 'icon-calendar-check-o',
                'class' => 'btn-danger'
            ],
            [
                'title' => '⚙️ Configuration complète',
                'desc' => 'Tous les paramètres du module',
                'href' => $this->context->link->getAdminLink('AdminBookerSettings'),
                'icon' => 'icon-gear',
                'class' => 'btn-default'
            ]
        ];
        
        $html = '<div class="panel">
            <div class="panel-heading">
                <i class="icon-cogs"></i> Accès rapide aux fonctionnalités
            </div>
            <div class="panel-body">
                <div class="row">';
        
        foreach ($links as $link) {
            $html .= '<div class="col-lg-4 col-md-6 col-sm-12" style="margin-bottom: 15px;">
                <div class="media" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #007bff;">
                    <div class="media-left">
                        <i class="' . $link['icon'] . ' fa-2x" style="color: #007bff;"></i>
                    </div>
                    <div class="media-body" style="padding-left: 15px;">
                        <h5 class="media-heading" style="margin-bottom: 8px;">
                            <a href="' . $link['href'] . '" class="' . $link['class'] . '" style="text-decoration: none; font-weight: 600;">
                                ' . $link['title'] . '
                            </a>
                        </h5>
                        <small style="color: #666;">' . $link['desc'] . '</small>
                    </div>
                </div>
            </div>';
        }
        
        $html .= '</div>
            <div class="alert alert-info" style="margin-top: 20px;">
                <i class="icon-info-circle"></i> <strong>Nouveautés v2.1 :</strong> 
                Calendriers doubles séparés, intégration produits avancée, paiement Stripe avec caution
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
                        'type' => 'text',
                        'label' => $this->l('Montant de la caution'),
                        'name' => 'BOOKING_DEPOSIT_AMOUNT',
                        'suffix' => '€',
                        'class' => 'fixed-width-sm',
                        'desc' => $this->l('Montant de la caution à verser')
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Paiement activé'),
                        'name' => 'BOOKING_PAYMENT_ENABLED',
                        'values' => array(
                            array('id' => 'payment_on', 'value' => 1, 'label' => $this->l('Oui')),
                            array('id' => 'payment_off', 'value' => 0, 'label' => $this->l('Non'))
                        )
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Stripe activé'),
                        'name' => 'BOOKING_STRIPE_ENABLED',
                        'values' => array(
                            array('id' => 'stripe_on', 'value' => 1, 'label' => $this->l('Oui')),
                            array('id' => 'stripe_off', 'value' => 0, 'label' => $this->l('Non'))
                        )
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Confirmation automatique'),
                        'name' => 'BOOKING_AUTO_CONFIRM',
                        'values' => array(
                            array('id' => 'auto_on', 'value' => 1, 'label' => $this->l('Oui')),
                            array('id' => 'auto_off', 'value' => 0, 'label' => $this->l('Non'))
                        )
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Téléphone urgence'),
                        'name' => 'BOOKING_EMERGENCY_PHONE',
                        'desc' => $this->l('Numéro affiché pour les urgences')
                    )
                ),
                'submit' => array(
                    'title' => $this->l('Enregistrer'),
                    'name' => 'submit' . $this->name
                )
            )
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->default_form_language = $this->context->language->id;
        $helper->module = $this;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
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
     * Récupérer les valeurs de configuration
     */
    public function getConfigFieldsValues()
    {
        return array(
            'BOOKING_DEFAULT_PRICE' => Configuration::get('BOOKING_DEFAULT_PRICE'),
            'BOOKING_DEPOSIT_AMOUNT' => Configuration::get('BOOKING_DEPOSIT_AMOUNT'),
            'BOOKING_PAYMENT_ENABLED' => Configuration::get('BOOKING_PAYMENT_ENABLED'),
            'BOOKING_STRIPE_ENABLED' => Configuration::get('BOOKING_STRIPE_ENABLED'),
            'BOOKING_AUTO_CONFIRM' => Configuration::get('BOOKING_AUTO_CONFIRM'),
            'BOOKING_EXPIRY_HOURS' => Configuration::get('BOOKING_EXPIRY_HOURS'),
            'BOOKING_MULTI_SELECT' => Configuration::get('BOOKING_MULTI_SELECT'),
            'BOOKING_EMERGENCY_PHONE' => Configuration::get('BOOKING_EMERGENCY_PHONE')
        );
    }

    /**
     * Statistiques des réservations
     */
    public function displayReservationStats()
    {
        try {
            $stats = [
                'total_bookers' => (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker`'),
                'active_bookers' => (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker` WHERE active = 1'),
                'total_availabilities' => (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth`'),
                'total_reservations' => (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`'),
                'pending_reservations' => (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` WHERE status = "pending"'),
                'confirmed_reservations' => (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` WHERE status = "confirmed"'),
                'revenue_month' => (float)Db::getInstance()->getValue('SELECT SUM(total_price) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` WHERE status IN ("paid", "completed") AND MONTH(date_add) = MONTH(NOW()) AND YEAR(date_add) = YEAR(NOW())') ?: 0
            ];
            
            $html = '<div class="row">
                <div class="col-lg-12">
                    <div class="panel">
                        <div class="panel-heading">
                            <i class="icon-bar-chart"></i> Statistiques du système
                        </div>
                        <div class="panel-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="metric-box text-center" style="background: #e3f2fd; padding: 20px; border-radius: 8px;">
                                        <div class="metric-number" style="font-size: 2em; font-weight: bold; color: #1976d2;">' . $stats['total_bookers'] . '</div>
                                        <div class="metric-label">Éléments total</div>
                                        <small>(' . $stats['active_bookers'] . ' actifs)</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="metric-box text-center" style="background: #f3e5f5; padding: 20px; border-radius: 8px;">
                                        <div class="metric-number" style="font-size: 2em; font-weight: bold; color: #7b1fa2;">' . $stats['total_availabilities'] . '</div>
                                        <div class="metric-label">Créneaux disponibles</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="metric-box text-center" style="background: #e8f5e8; padding: 20px; border-radius: 8px;">
                                        <div class="metric-number" style="font-size: 2em; font-weight: bold; color: #388e3c;">' . $stats['total_reservations'] . '</div>
                                        <div class="metric-label">Réservations total</div>
                                        <small>(' . $stats['pending_reservations'] . ' en attente)</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="metric-box text-center" style="background: #fff3e0; padding: 20px; border-radius: 8px;">
                                        <div class="metric-number" style="font-size: 2em; font-weight: bold; color: #f57c00;">' . number_format($stats['revenue_month'], 2) . '€</div>
                                        <div class="metric-label">CA du mois</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>';
            
            return $html;
            
        } catch (Exception $e) {
            return '<div class="alert alert-warning">Impossible de charger les statistiques</div>';
        }
    }

    /**
     * Hook pour ajouter des médias dans le header
     */
    public function hookDisplayHeader($params)
    {
        if ($this->context->controller->php_self == 'cms' && 
            Configuration::get('BOOKING_CMS_ID') == $this->context->controller->cms->id_cms) {
            
            $this->context->controller->registerStylesheet(
                'booking-front',
                'modules/'.$this->name.'/views/css/booking-front.css',
                ['media' => 'all', 'priority' => 100]
            );
            
            $this->context->controller->registerJavascript(
                'booking-front-js',
                'modules/'.$this->name.'/views/js/booking-front.js',
                ['position' => 'bottom', 'priority' => 100]
            );
        }
    }

    /**
     * Hook pour ajouter des médias dans le header admin
     */
    public function hookDisplayBackOfficeHeader($params)
    {        
        $controller = Tools::getValue("controller");
        if (strpos($controller, 'AdminBooker') !== false) {
            $this->context->controller->addCSS('modules/'.$this->name.'/views/css/admin-booking.css');
            $this->context->controller->addJS('modules/'.$this->name.'/views/js/admin-booking.js');
            
            // Ajout spécifique pour les calendriers
            if (strpos($controller, 'Calendar') !== false) {
                $this->context->controller->addCSS('modules/'.$this->name.'/views/css/admin-calendar.css');
                $this->context->controller->addJS('https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.8/index.global.min.js');
                $this->context->controller->addJS('https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.8/locales-all.global.min.js');
                
                if (strpos($controller, 'Availability') !== false) {
                    $this->context->controller->addJS('modules/'.$this->name.'/views/js/availability-calendar.js');
                } elseif (strpos($controller, 'Reservation') !== false) {
                    $this->context->controller->addJS('modules/'.$this->name.'/views/js/reservation-calendar.js');
                }
            }
        }
    }

    /**
     * Hook pour nettoyer les réservations expirées (cron)
     */
    public function hookActionCronJob($params)
    {
        if (Configuration::get('BOOKING_CRON_CLEAN_RESERVATIONS')) {
            $expiry_hours = (int)Configuration::get('BOOKING_EXPIRY_HOURS') ?: 24;
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
     * Hook pour ajouter des médias dans le footer
     */
    public function hookDisplayFooter($params)
    {
        // Chargement conditionnel selon le contexte
        return '';
    }
    
    /**
     * Hook après mise à jour statut commande
     */
    public function hookActionOrderStatusPostUpdate($params)
    {
        $order = new Order($params['id_order']);
        
        // Vérifier si c'est une commande de réservation
        $reservation = BookerAuthReserved::getByOrderId($order->id);
        if ($reservation) {
            $reservation->updateStatusFromOrder($params['newOrderStatus']);
        }
    }
    
    /**
     * Hook confirmation paiement
     */
    public function hookActionPaymentConfirmation($params)
    {
        $order = $params['order'];
        
        // Vérifier si c'est une commande de réservation
        $reservation = BookerAuthReserved::getByOrderId($order->id);
        if ($reservation) {
            $reservation->confirmPayment();
        }
    }
    
    /**
     * Hook pour afficher des champs supplémentaires dans la fiche produit admin
     */
    public function hookDisplayAdminProductsExtra($params)
    {
        $id_product = (int)$params['id_product'];
        
        // Vérifier si ce produit est lié à un booker
        $booker = Booker::getByProductId($id_product);
        
        $this->context->smarty->assign([
            'id_product' => $id_product,
            'booker' => $booker,
            'is_booking_product' => !empty($booker)
        ]);
        
        return $this->display(__FILE__, 'views/templates/admin/product_tab.tpl');
    }
    
    /**
     * Hook après sauvegarde produit
     */
    public function hookActionProductSave($params)
    {
        if (Configuration::get('BOOKING_SYNC_PRODUCT_PRICE')) {
            $id_product = (int)$params['id_product'];
            $booker = Booker::getByProductId($id_product);
            
            if ($booker) {
                $product = new Product($id_product);
                $booker->syncPriceFromProduct($product);
            }
        }
    }
}