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
        $this->version = '2.1.1';
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
     * Installation de la base de données - VERSION CORRIGÉE AVEC BON SCHÉMA
     */
    private function installDB()
    {
        $sql = array();

        // Table des éléments réservables (bookers) - SCHÉMA CORRIGÉ
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
            `deposit_required` tinyint(1) DEFAULT 0,
            `deposit_amount` decimal(10,2) DEFAULT 0.00,
            `auto_confirm` tinyint(1) DEFAULT 0,
            `google_account` varchar(255) DEFAULT NULL,
            `active` tinyint(1) DEFAULT 1,
            `date_add` datetime NOT NULL,
            `date_upd` datetime NOT NULL,
            PRIMARY KEY (`id_booker`),
            KEY `idx_product` (`id_product`),
            KEY `idx_active` (`active`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';

        // Table des disponibilités - SCHÉMA CORRIGÉ
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
            KEY `idx_active` (`active`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';

        // Table des réservations - SCHÉMA CORRIGÉ
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
            KEY `idx_date_range` (`date_reserved`, `date_to`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';

        // Table de liaison produits
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'booker_product` (
            `id_booker` int(11) NOT NULL,
            `id_product` int(11) NOT NULL,
            `sync_price` tinyint(1) DEFAULT 1,
            `override_price` decimal(10,2) DEFAULT NULL,
            `date_add` datetime NOT NULL,
            PRIMARY KEY (`id_booker`, `id_product`),
            KEY `idx_product` (`id_product`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';

        // Table de liaison réservations-commandes
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'booker_reservation_order` (
            `id_reservation` int(11) NOT NULL,
            `id_order` int(11) NOT NULL,
            `order_type` enum(\'booking\',\'deposit\') DEFAULT \'booking\',
            `amount` decimal(10,2) NOT NULL,
            `date_add` datetime NOT NULL,
            PRIMARY KEY (`id_reservation`, `id_order`, `order_type`),
            KEY `idx_order` (`id_order`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';

        // Table de logs d'activité
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'booking_activity_log` (
            `id_log` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `id_reservation` int(10) unsigned NULL,
            `id_booker` int(10) unsigned NULL,
            `action` VARCHAR(100) NOT NULL,
            `details` TEXT NULL,
            `id_employee` int(10) unsigned NULL,
            `date_add` DATETIME NOT NULL,
            PRIMARY KEY (`id_log`),
            INDEX `idx_id_reservation` (`id_reservation`),
            INDEX `idx_id_booker` (`id_booker`),
            INDEX `idx_action` (`action`),
            INDEX `idx_id_employee` (`id_employee`),
            INDEX `idx_date_add` (`date_add`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';

        // Exécution des requêtes SQL
        foreach ($sql as $query) {
            if (!Db::getInstance()->execute($query)) {
                // Log l'erreur pour diagnostic
                PrestaShopLogger::addLog(
                    'Booking Module Install Error: ' . Db::getInstance()->getMsgError() . ' - Query: ' . $query, 
                    3, 
                    null, 
                    'Booking', 
                    null, 
                    true
                );
                return false;
            }
        }

        return true;
    }

    /**
     * Installation des onglets d'administration
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
                'class_name' => 'AdminBookerView',
                'name' => 'Vue Calendriers',
                'parent_class_name' => 'AdminBooking'
            ),
            array(
                'class_name' => 'AdminBookerSettings',
                'name' => 'Paramètres',
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
            'AdminBookerCalendarAvailability',
            'AdminBookerCalendarReservations',
            'AdminBookerView',
            'AdminBookerSettings',
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
     * Installation des configurations
     */
    private function installConfiguration()
    {
        $configurations = array(
            'BOOKING_DEFAULT_PRICE' => '50.00',
            'BOOKING_DEPOSIT_AMOUNT' => '20.00',
            'BOOKING_DEFAULT_DURATION' => '60',
            'BOOKING_EXPIRY_HOURS' => '24',
            'BOOKING_AUTO_CONFIRM' => '0',
            'BOOKING_MULTI_SELECT' => '1',
            'BOOKING_BUSINESS_HOURS_START' => '08:00',
            'BOOKING_BUSINESS_HOURS_END' => '18:00',
            'BOOKING_ALLOWED_DAYS' => '1,2,3,4,5,6,7',
            'BOOKING_NOTIFICATIONS_ENABLED' => '1',
            'BOOKING_AUTO_CONFIRMATION_EMAIL' => '1',
            'BOOKING_AUTO_REMINDERS' => '0',
            'BOOKING_REMINDER_HOURS' => '24',
            'BOOKING_ADMIN_EMAIL' => Configuration::get('PS_SHOP_EMAIL'),
            'BOOKING_STRIPE_ENABLED' => '0',
            'BOOKING_STRIPE_HOLD_DEPOSIT' => '0',
            'BOOKING_SAVE_CARDS' => '0',
            'BOOKING_DEBUG_MODE' => '0',
            'BOOKING_MIN_BOOKING_TIME' => '24',
            'BOOKING_MAX_BOOKING_DAYS' => '30',
            'BOOKING_SYNC_PRODUCT_PRICE' => '1',
            'BOOKING_SYNC_FROM_PRODUCT' => '0',
            'BOOKING_DEFAULT_CATEGORY' => Configuration::get('PS_HOME_CATEGORY'),
            'BOOKING_DEFAULT_TAX_RULES_GROUP' => '1',
            'BOOKING_STATUS_PENDING_PAYMENT' => Configuration::get('PS_OS_BANKWIRE'),
            'BOOKING_CALENDAR_MIN_TIME' => '08:00',
            'BOOKING_CALENDAR_MAX_TIME' => '20:00',
            'BOOKING_SLOT_DURATION' => '00:30:00'
        );

        foreach ($configurations as $key => $value) {
            if (!Configuration::updateValue($key, $value)) {
                return false;
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
            'BOOKING_DEFAULT_PRICE',
            'BOOKING_DEPOSIT_AMOUNT', 
            'BOOKING_DEFAULT_DURATION',
            'BOOKING_EXPIRY_HOURS',
            'BOOKING_AUTO_CONFIRM',
            'BOOKING_MULTI_SELECT',
            'BOOKING_BUSINESS_HOURS_START',
            'BOOKING_BUSINESS_HOURS_END',
            'BOOKING_ALLOWED_DAYS',
            'BOOKING_NOTIFICATIONS_ENABLED',
            'BOOKING_AUTO_CONFIRMATION_EMAIL',
            'BOOKING_AUTO_REMINDERS',
            'BOOKING_REMINDER_HOURS',
            'BOOKING_ADMIN_EMAIL',
            'BOOKING_STRIPE_ENABLED',
            'BOOKING_STRIPE_HOLD_DEPOSIT',
            'BOOKING_SAVE_CARDS',
            'BOOKING_DEBUG_MODE',
            'BOOKING_MIN_BOOKING_TIME',
            'BOOKING_MAX_BOOKING_DAYS',
            'BOOKING_SYNC_PRODUCT_PRICE',
            'BOOKING_SYNC_FROM_PRODUCT',
            'BOOKING_DEFAULT_CATEGORY',
            'BOOKING_DEFAULT_TAX_RULES_GROUP',
            'BOOKING_STATUS_PENDING_PAYMENT',
            'BOOKING_CALENDAR_MIN_TIME',
            'BOOKING_CALENDAR_MAX_TIME',
            'BOOKING_SLOT_DURATION'
        );

        foreach ($configurations as $key) {
            Configuration::deleteByName($key);
        }

        return true;
    }

    /**
     * Désinstallation de la base de données
     */
    private function uninstallDB()
    {
        $sql = array();
        
        $sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'booking_activity_log`';
        $sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'booker_reservation_order`';
        $sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'booker_product`';
        $sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'booker_auth_reserved`';
        $sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'booker_auth`';
        $sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'booker`';

        foreach ($sql as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Hook d'en-tête
     */
    public function hookDisplayHeader()
    {
        if ($this->context->controller instanceof AdminController) {
            $this->context->controller->addCSS($this->_path . 'views/css/admin-booking.css');
            $this->context->controller->addJS($this->_path . 'views/js/admin-booking.js');
        }
    }

    /**
     * Hook média front
     */
    public function hookActionFrontControllerSetMedia()
    {
        $this->context->controller->addCSS($this->_path . 'views/css/booking-front.css');
        $this->context->controller->addJS($this->_path . 'views/js/booking-front.js');
    }

    /**
     * Hook back-office header
     */
    public function hookDisplayBackOfficeHeader()
    {
        if (Tools::getValue('configure') == $this->name || 
            strpos($_SERVER['REQUEST_URI'], 'AdminBooker') !== false) {
            
            $this->context->controller->addCSS($this->_path . 'views/css/admin-calendar.css');
            $this->context->controller->addJS([
                'https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.8/index.global.min.js',
                $this->_path . 'views/js/availability-calendar.js',
                $this->_path . 'views/js/reservation-calendar.js'
            ]);
        }
    }

    /**
     * Page de configuration
     */
    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitBookingConfig')) {
            $output .= $this->postProcess();
        }

        return $output . $this->displayForm();
    }

    /**
     * Traitement du formulaire de configuration
     */
    private function postProcess()
    {
        $form_values = $this->getConfigFormValues();
        
        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }

        return $this->displayConfirmation($this->l('Configuration mise à jour avec succès.'));
    }

    /**
     * Affichage du formulaire de configuration
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
     * Structure du formulaire de configuration
     */
    private function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Configuration du module de réservations'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('Prix par défaut'),
                        'name' => 'BOOKING_DEFAULT_PRICE',
                        'suffix' => '€',
                        'class' => 'fixed-width-sm',
                        'desc' => $this->l('Prix par défaut pour les réservations'),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Montant de la caution'),
                        'name' => 'BOOKING_DEPOSIT_AMOUNT',
                        'suffix' => '€',
                        'class' => 'fixed-width-sm',
                        'desc' => $this->l('Montant de la caution à bloquer via Stripe'),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Durée par défaut'),
                        'name' => 'BOOKING_DEFAULT_DURATION',
                        'suffix' => 'min',
                        'class' => 'fixed-width-sm',
                        'desc' => $this->l('Durée par défaut des créneaux de réservation'),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Délai d\'expiration'),
                        'name' => 'BOOKING_EXPIRY_HOURS',
                        'suffix' => 'heures',
                        'class' => 'fixed-width-sm',
                        'desc' => $this->l('Délai avant expiration des réservations non confirmées'),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Confirmation automatique'),
                        'name' => 'BOOKING_AUTO_CONFIRM',
                        'values' => array(
                            array('id' => 'active_on', 'value' => 1, 'label' => $this->l('Activé')),
                            array('id' => 'active_off', 'value' => 0, 'label' => $this->l('Désactivé'))
                        ),
                        'desc' => $this->l('Confirmer automatiquement les réservations sans validation manuelle'),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Synchroniser prix produits'),
                        'name' => 'BOOKING_SYNC_PRODUCT_PRICE',
                        'values' => array(
                            array('id' => 'sync_on', 'value' => 1, 'label' => $this->l('Activé')),
                            array('id' => 'sync_off', 'value' => 0, 'label' => $this->l('Désactivé'))
                        ),
                        'desc' => $this->l('Synchroniser automatiquement les prix entre bookers et produits'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Sauvegarder'),
                ),
            ),
        );
    }

    /**
     * Valeurs du formulaire de configuration
     */
    private function getConfigFormValues()
    {
        return array(
            'BOOKING_DEFAULT_PRICE' => Configuration::get('BOOKING_DEFAULT_PRICE'),
            'BOOKING_DEPOSIT_AMOUNT' => Configuration::get('BOOKING_DEPOSIT_AMOUNT'),
            'BOOKING_DEFAULT_DURATION' => Configuration::get('BOOKING_DEFAULT_DURATION'),
            'BOOKING_EXPIRY_HOURS' => Configuration::get('BOOKING_EXPIRY_HOURS'),
            'BOOKING_AUTO_CONFIRM' => Configuration::get('BOOKING_AUTO_CONFIRM'),
            'BOOKING_SYNC_PRODUCT_PRICE' => Configuration::get('BOOKING_SYNC_PRODUCT_PRICE'),
        );
    }
}
?>