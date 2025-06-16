<?php
/**
 * Contrôleur front-office pour les réservations
 * Gestion du processus complet de réservation avec paiement Stripe
 */

class BookingBookingModuleFrontController extends ModuleFrontController
{
    public $php_self = 'booking';
    public $ssl = true;
    
    private $stripe_manager;
    
    public function __construct()
    {
        parent::__construct();
        
        $this->context = Context::getContext();
        
        // Initialiser le gestionnaire Stripe si disponible
        if (file_exists(dirname(__FILE__) . '/../classes/StripePaymentManager.php')) {
            require_once(dirname(__FILE__) . '/../classes/StripePaymentManager.php');
            $this->stripe_manager = new StripePaymentManager();
        }
    }
    
    /**
     * Initialisation du contrôleur
     */
    public function init()
    {
        parent::init();
        
        // Vérifier si le module est actif
        if (!Module::isEnabled('booking')) {
            Tools::redirect('index.php');
        }
        
        // Traitement AJAX
        if (Tools::isSubmit('ajax')) {
            $this->processAjax();
        }
    }
    
    /**
     * Affichage principal
     */
    public function initContent()
    {
        parent::initContent();
        
        $action = Tools::getValue('action', 'list');
        
        switch ($action) {
            case 'calendar':
                $this->displayCalendar();
                break;
            case 'book':
                $this->displayBookingForm();
                break;
            case 'confirm':
                $this->displayConfirmation();
                break;
            case 'payment':
                $this->displayPayment();
                break;
            case 'success':
                $this->displaySuccess();
                break;
            default:
                $this->displayBookersList();
        }
    }
    
    /**
     * Afficher la liste des éléments réservables
     */
    private function displayBookersList()
    {
        $bookers = $this->getAvailableBookers();
        
        $this->context->smarty->assign([
            'bookers' => $bookers,
            'page_title' => $this->l('Réservations disponibles'),
            'meta_title' => $this->l('Réservations'),
            'calendar_enabled' => Configuration::get('BOOKING_SHOW_CALENDAR_FRONT', 1),
            'booking_config' => $this->getBookingConfig()
        ]);
        
        $this->setTemplate('module:booking/views/templates/front/bookers_list.tpl');
    }
    
    /**
     * Afficher le calendrier de réservations
     */
    private function displayCalendar()
    {
        $id_booker = (int)Tools::getValue('id_booker', 0);
        $bookers = $this->getAvailableBookers();
        $selected_booker = null;
        
        if ($id_booker) {
            foreach ($bookers as $booker) {
                if ($booker['id_booker'] == $id_booker) {
                    $selected_booker = $booker;
                    break;
                }
            }
        }
        
        $this->context->smarty->assign([
            'bookers' => $bookers,
            'selected_booker' => $selected_booker,
            'calendar_events' => json_encode($this->getCalendarEvents($id_booker)),
            'calendar_config' => $this->getCalendarConfig(),
            'ajax_url' => $this->context->link->getModuleLink('booking', 'booking'),
            'booking_url' => $this->context->link->getModuleLink('booking', 'booking', ['action' => 'book']),
            'page_title' => $this->l('Calendrier des réservations')
        ]);
        
        // Ajouter les assets CSS/JS pour le calendrier
        $this->addCalendarAssets();
        
        $this->setTemplate('module:booking/views/templates/front/calendar.tpl');
    }
    
    /**
     * Afficher le formulaire de réservation
     */
    private function displayBookingForm()
    {
        $id_booker = (int)Tools::getValue('id_booker');
        $selected_date = Tools::getValue('date');
        $selected_time = Tools::getValue('time');
        
        if (!$id_booker) {
            Tools::redirect($this->context->link->getModuleLink('booking', 'booking'));
        }
        
        $booker = $this->getBookerDetails($id_booker);
        if (!$booker) {
            $this->errors[] = $this->l('Élément de réservation introuvable');
            $this->redirectWithNotifications($this->context->link->getModuleLink('booking', 'booking'));
        }
        
        // Vérifier les disponibilités pour la date sélectionnée
        $available_slots = $this->getAvailableSlots($id_booker, $selected_date);
        
        $this->context->smarty->assign([
            'booker' => $booker,
            'selected_date' => $selected_date,
            'selected_time' => $selected_time,
            'available_slots' => $available_slots,
            'time_slots' => $this->getTimeSlots(),
            'booking_config' => $this->getBookingConfig(),
            'customer' => $this->context->customer,
            'stripe_config' => $this->stripe_manager ? $this->stripe_manager->getPublicConfig() : null,
            'page_title' => $this->l('Réserver') . ' - ' . $booker['name']
        ]);
        
        $this->setTemplate('module:booking/views/templates/front/booking_form.tpl');
    }
    
    /**
     * Traiter la soumission du formulaire de réservation
     */
    public function postProcess()
    {
        if (Tools::isSubmit('submitBooking')) {
            $this->processBookingSubmission();
        } elseif (Tools::isSubmit('confirmPayment')) {
            $this->processPaymentConfirmation();
        }
        
        parent::postProcess();
    }
    
    /**
     * Traiter la soumission d'une réservation
     */
    private function processBookingSubmission()
    {
        $id_booker = (int)Tools::getValue('id_booker');
        $date_reserved = Tools::getValue('date_reserved');
        $hour_from = (int)Tools::getValue('hour_from');
        $hour_to = (int)Tools::getValue('hour_to');
        $customer_firstname = Tools::getValue('customer_firstname');
        $customer_lastname = Tools::getValue('customer_lastname');
        $customer_email = Tools::getValue('customer_email');
        $customer_phone = Tools::getValue('customer_phone');
        $customer_message = Tools::getValue('customer_message');
        
        // Validation des données
        $errors = $this->validateBookingData([
            'id_booker' => $id_booker,
            'date_reserved' => $date_reserved,
            'hour_from' => $hour_from,
            'hour_to' => $hour_to,
            'customer_firstname' => $customer_firstname,
            'customer_lastname' => $customer_lastname,
            'customer_email' => $customer_email
        ]);
        
        if (!empty($errors)) {
            $this->errors = array_merge($this->errors, $errors);
            return;
        }
        
        // Vérifier la disponibilité
        if (!$this->checkAvailability($id_booker, $date_reserved, $hour_from, $hour_to)) {
            $this->errors[] = $this->l('Ce créneau n\'est plus disponible');
            return;
        }
        
        // Récupérer les détails du booker pour calculer le prix
        $booker = $this->getBookerDetails($id_booker);
        $total_price = $this->calculatePrice($booker, $hour_from, $hour_to);
        
        // Créer la réservation
        $reservation_data = [
            'id_booker' => $id_booker,
            'date_reserved' => $date_reserved,
            'hour_from' => $hour_from,
            'hour_to' => $hour_to,
            'customer_firstname' => $customer_firstname,
            'customer_lastname' => $customer_lastname,
            'customer_email' => $customer_email,
            'customer_phone' => $customer_phone,
            'customer_message' => $customer_message,
            'total_price' => $total_price,
            'booking_reference' => $this->generateBookingReference(),
            'status' => $booker['auto_confirm'] ? 1 : 0, // Auto-confirmation ou en attente
            'date_add' => date('Y-m-d H:i:s')
        ];
        
        $id_reserved = $this->createReservation($reservation_data);
        
        if ($id_reserved) {
            // Si Stripe est activé et qu'un paiement est requis
            if ($this->stripe_manager && Configuration::get('BOOKING_STRIPE_ENABLED') && $total_price > 0) {
                $reservation_data['id_reserved'] = $id_reserved;
                $reservation_data['booker_name'] = $booker['name'];
                $reservation_data['deposit_amount'] = $booker['require_deposit'] ? $booker['deposit_amount'] : 0;
                
                // Rediriger vers la page de paiement
                Tools::redirect($this->context->link->getModuleLink('booking', 'booking', [
                    'action' => 'payment',
                    'id_reserved' => $id_reserved
                ]));
            } else {
                // Rediriger vers la confirmation
                Tools::redirect($this->context->link->getModuleLink('booking', 'booking', [
                    'action' => 'confirm',
                    'id_reserved' => $id_reserved,
                    'reference' => $reservation_data['booking_reference']
                ]));
            }
        } else {
            $this->errors[] = $this->l('Erreur lors de la création de la réservation');
        }
    }
    
    /**
     * Afficher la page de paiement Stripe
     */
    private function displayPayment()
    {
        $id_reserved = (int)Tools::getValue('id_reserved');
        
        if (!$id_reserved) {
            Tools::redirect($this->context->link->getModuleLink('booking', 'booking'));
        }
        
        $reservation = $this->getReservationDetails($id_reserved);
        if (!$reservation) {
            $this->errors[] = $this->l('Réservation introuvable');
            $this->redirectWithNotifications($this->context->link->getModuleLink('booking', 'booking'));
        }
        
        // Créer le Payment Intent Stripe
        $payment_result = $this->stripe_manager->createPaymentIntent($reservation, $reservation['require_deposit']);
        
        if (!$payment_result['success']) {
            $this->errors[] = $this->l('Erreur lors de l\'initialisation du paiement: ') . $payment_result['error'];
            $this->redirectWithNotifications($this->context->link->getModuleLink('booking', 'booking'));
        }
        
        $this->context->smarty->assign([
            'reservation' => $reservation,
            'payment_intent' => $payment_result['payment_intent'],
            'setup_intent' => $payment_result['setup_intent'],
            'stripe_config' => $this->stripe_manager->getPublicConfig(),
            'page_title' => $this->l('Paiement de la réservation'),
            'return_url' => $this->context->link->getModuleLink('booking', 'booking', [
                'action' => 'success',
                'id_reserved' => $id_reserved
            ])
        ]);
        
        // Ajouter Stripe.js
        $this->context->controller->addJS('https://js.stripe.com/v3/');
        
        $this->setTemplate('module:booking/views/templates/front/payment.tpl');
    }
    
    /**
     * Afficher la page de succès
     */
    private function displaySuccess()
    {
        $id_reserved = (int)Tools::getValue('id_reserved');
        $payment_intent_id = Tools::getValue('payment_intent');
        
        if (!$id_reserved) {
            Tools::redirect($this->context->link->getModuleLink('booking', 'booking'));
        }
        
        $reservation = $this->getReservationDetails($id_reserved);
        
        $this->context->smarty->assign([
            'reservation' => $reservation,
            'payment_intent_id' => $payment_intent_id,
            'page_title' => $this->l('Réservation confirmée')
        ]);
        
        $this->setTemplate('module:booking/views/templates/front/success.tpl');
    }
    
    /**
     * Traitement des requêtes AJAX
     */
    private function processAjax()
    {
        $action = Tools::getValue('ajax_action');
        
        switch ($action) {
            case 'getAvailableSlots':
                $this->ajaxGetAvailableSlots();
                break;
            case 'checkAvailability':
                $this->ajaxCheckAvailability();
                break;
            case 'getBookerDetails':
                $this->ajaxGetBookerDetails();
                break;
            case 'confirmPayment':
                $this->ajaxConfirmPayment();
                break;
            default:
                $this->ajaxReturn(['error' => 'Action inconnue']);
        }
    }
    
    /**
     * AJAX: Récupérer les créneaux disponibles
     */
    private function ajaxGetAvailableSlots()
    {
        $id_booker = (int)Tools::getValue('id_booker');
        $date = Tools::getValue('date');
        
        $slots = $this->getAvailableSlots($id_booker, $date);
        
        $this->ajaxReturn(['slots' => $slots]);
    }
    
    /**
     * AJAX: Vérifier la disponibilité
     */
    private function ajaxCheckAvailability()
    {
        $id_booker = (int)Tools::getValue('id_booker');
        $date = Tools::getValue('date');
        $hour_from = (int)Tools::getValue('hour_from');
        $hour_to = (int)Tools::getValue('hour_to');
        
        $available = $this->checkAvailability($id_booker, $date, $hour_from, $hour_to);
        
        $this->ajaxReturn(['available' => $available]);
    }
    
    /**
     * AJAX: Confirmer le paiement Stripe
     */
    private function ajaxConfirmPayment()
    {
        $payment_intent_id = Tools::getValue('payment_intent_id');
        $payment_method_id = Tools::getValue('payment_method_id');
        
        if (!$this->stripe_manager) {
            $this->ajaxReturn(['error' => 'Stripe non configuré']);
        }
        
        $result = $this->stripe_manager->confirmPayment($payment_intent_id, $payment_method_id);
        
        $this->ajaxReturn($result);
    }
    
    /**
     * Méthodes utilitaires
     */
    
    private function getAvailableBookers()
    {
        $sql = 'SELECT b.*, p.reference as product_reference
                FROM `' . _DB_PREFIX_ . 'booker` b
                LEFT JOIN `' . _DB_PREFIX_ . 'product` p ON b.id_product = p.id_product
                WHERE b.active = 1
                ORDER BY b.sort_order ASC, b.name ASC';
        
        $results = Db::getInstance()->executeS($sql);
        
        foreach ($results as &$booker) {
            $booker['price'] = (float)$booker['price'];
            $booker['capacity'] = (int)$booker['capacity'];
            $booker['booking_duration'] = (int)$booker['booking_duration'];
        }
        
        return $results ?: [];
    }
    
    private function getBookerDetails($id_booker)
    {
        $sql = 'SELECT b.*, p.reference as product_reference
                FROM `' . _DB_PREFIX_ . 'booker` b
                LEFT JOIN `' . _DB_PREFIX_ . 'product` p ON b.id_product = p.id_product
                WHERE b.id_booker = ' . (int)$id_booker . ' AND b.active = 1';
        
        return Db::getInstance()->getRow($sql);
    }
    
    private function getAvailableSlots($id_booker, $date)
    {
        // Récupérer les disponibilités pour cette date
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'booker_auth` 
                WHERE id_booker = ' . (int)$id_booker . '
                AND active = 1
                AND date_from <= "' . pSQL($date) . '"
                AND date_to >= "' . pSQL($date) . '"';
        
        $availabilities = Db::getInstance()->executeS($sql);
        
        // Récupérer les réservations existantes pour cette date
        $sql = 'SELECT hour_from, hour_to FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`
                WHERE id_booker = ' . (int)$id_booker . '
                AND date_reserved = "' . pSQL($date) . '"
                AND status IN (0, 1, 2)'; // Exclure annulées et expirées
        
        $reservations = Db::getInstance()->executeS($sql);
        
        // Calculer les créneaux libres
        $slots = [];
        foreach ($availabilities as $availability) {
            $start_time = strtotime($availability['time_from']);
            $end_time = strtotime($availability['time_to']);
            
            for ($time = $start_time; $time < $end_time; $time += 1800) { // Créneaux de 30 min
                $hour = (int)date('H', $time);
                $minute = (int)date('i', $time);
                
                $is_available = true;
                foreach ($reservations as $reservation) {
                    if ($hour >= $reservation['hour_from'] && $hour < $reservation['hour_to']) {
                        $is_available = false;
                        break;
                    }
                }
                
                if ($is_available) {
                    $slots[] = [
                        'hour' => $hour,
                        'minute' => $minute,
                        'time' => sprintf('%02d:%02d', $hour, $minute),
                        'label' => sprintf('%02d:%02d', $hour, $minute)
                    ];
                }
            }
        }
        
        return $slots;
    }
    
    private function checkAvailability($id_booker, $date, $hour_from, $hour_to)
    {
        // Vérifier qu'il y a une disponibilité pour ce créneau
        $sql = 'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth` 
                WHERE id_booker = ' . (int)$id_booker . '
                AND active = 1
                AND date_from <= "' . pSQL($date) . '"
                AND date_to >= "' . pSQL($date) . '"
                AND time_from <= "' . sprintf('%02d:00:00', $hour_from) . '"
                AND time_to >= "' . sprintf('%02d:00:00', $hour_to) . '"';
        
        $has_availability = (bool)Db::getInstance()->getValue($sql);
        
        if (!$has_availability) {
            return false;
        }
        
        // Vérifier qu'il n'y a pas de conflit avec une réservation existante
        $sql = 'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`
                WHERE id_booker = ' . (int)$id_booker . '
                AND date_reserved = "' . pSQL($date) . '"
                AND status IN (0, 1, 2)
                AND (
                    (hour_from < ' . (int)$hour_to . ' AND hour_to > ' . (int)$hour_from . ')
                )';
        
        $has_conflict = (bool)Db::getInstance()->getValue($sql);
        
        return !$has_conflict;
    }
    
    private function calculatePrice($booker, $hour_from, $hour_to)
    {
        $duration_hours = $hour_to - $hour_from;
        return $booker['price'] * $duration_hours;
    }
    
    private function generateBookingReference()
    {
        return 'BOOK-' . date('Ym') . '-' . strtoupper(Tools::substr(md5(uniqid()), 0, 6));
    }
    
    private function createReservation($data)
    {
        $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'booker_auth_reserved` 
                (id_booker, date_reserved, hour_from, hour_to, customer_firstname, 
                 customer_lastname, customer_email, customer_phone, notes, 
                 total_price, booking_reference, status, date_add)
                VALUES (
                    ' . (int)$data['id_booker'] . ',
                    "' . pSQL($data['date_reserved']) . '",
                    ' . (int)$data['hour_from'] . ',
                    ' . (int)$data['hour_to'] . ',
                    "' . pSQL($data['customer_firstname']) . '",
                    "' . pSQL($data['customer_lastname']) . '",
                    "' . pSQL($data['customer_email']) . '",
                    "' . pSQL($data['customer_phone']) . '",
                    "' . pSQL($data['customer_message']) . '",
                    ' . (float)$data['total_price'] . ',
                    "' . pSQL($data['booking_reference']) . '",
                    ' . (int)$data['status'] . ',
                    "' . pSQL($data['date_add']) . '"
                )';
        
        if (Db::getInstance()->execute($sql)) {
            return Db::getInstance()->Insert_ID();
        }
        
        return false;
    }
    
    private function getReservationDetails($id_reserved)
    {
        $sql = 'SELECT r.*, b.name as booker_name, b.require_deposit, b.deposit_amount
                FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` r
                LEFT JOIN `' . _DB_PREFIX_ . 'booker` b ON r.id_booker = b.id_booker
                WHERE r.id_reserved = ' . (int)$id_reserved;
        
        return Db::getInstance()->getRow($sql);
    }
    
    private function validateBookingData($data)
    {
        $errors = [];
        
        if (!$data['id_booker'] || !Validate::isUnsignedId($data['id_booker'])) {
            $errors[] = $this->l('Élément de réservation invalide');
        }
        
        if (!$data['date_reserved'] || !Validate::isDate($data['date_reserved'])) {
            $errors[] = $this->l('Date de réservation invalide');
        }
        
        if (!$data['customer_firstname'] || !Validate::isName($data['customer_firstname'])) {
            $errors[] = $this->l('Prénom invalide');
        }
        
        if (!$data['customer_lastname'] || !Validate::isName($data['customer_lastname'])) {
            $errors[] = $this->l('Nom invalide');
        }
        
        if (!$data['customer_email'] || !Validate::isEmail($data['customer_email'])) {
            $errors[] = $this->l('Email invalide');
        }
        
        if ($data['hour_from'] >= $data['hour_to']) {
            $errors[] = $this->l('Heures de réservation invalides');
        }
        
        return $errors;
    }
    
    private function getCalendarEvents($id_booker = 0)
    {
        // Événements des disponibilités et réservations pour le calendrier front
        $where_booker = $id_booker ? ' AND r.id_booker = ' . (int)$id_booker : '';
        
        $sql = 'SELECT r.*, b.name as booker_name
                FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` r
                LEFT JOIN `' . _DB_PREFIX_ . 'booker` b ON r.id_booker = b.id_booker
                WHERE r.date_reserved >= CURDATE()
                AND r.status IN (0, 1, 2)
                ' . $where_booker . '
                ORDER BY r.date_reserved ASC';
        
        $reservations = Db::getInstance()->executeS($sql);
        $events = [];
        
        foreach ($reservations as $reservation) {
            $events[] = [
                'title' => 'Réservé',
                'start' => $reservation['date_reserved'] . 'T' . sprintf('%02d:00:00', $reservation['hour_from']),
                'end' => $reservation['date_reserved'] . 'T' . sprintf('%02d:00:00', $reservation['hour_to']),
                'color' => '#dc3545',
                'rendering' => 'background'
            ];
        }
        
        return $events;
    }
    
    private function getCalendarConfig()
    {
        return [
            'locale' => $this->context->language->iso_code,
            'firstDay' => 1,
            'businessHours' => [
                'daysOfWeek' => [1, 2, 3, 4, 5, 6, 7],
                'startTime' => Configuration::get('BOOKING_BUSINESS_HOURS_START', '08:00'),
                'endTime' => Configuration::get('BOOKING_BUSINESS_HOURS_END', '18:00')
            ],
            'minTime' => Configuration::get('BOOKING_CALENDAR_MIN_TIME', '08:00:00'),
            'maxTime' => Configuration::get('BOOKING_CALENDAR_MAX_TIME', '20:00:00'),
            'slotDuration' => Configuration::get('BOOKING_SLOT_DURATION', '00:30:00')
        ];
    }
    
    private function getBookingConfig()
    {
        return [
            'min_booking_time' => Configuration::get('BOOKING_MIN_BOOKING_TIME', 24),
            'max_booking_days' => Configuration::get('BOOKING_MAX_BOOKING_DAYS', 30),
            'default_duration' => Configuration::get('BOOKING_DEFAULT_DURATION', 60),
            'auto_confirm' => Configuration::get('BOOKING_AUTO_CONFIRM', 0),
            'stripe_enabled' => Configuration::get('BOOKING_STRIPE_ENABLED', 0)
        ];
    }
    
    private function getTimeSlots()
    {
        $slots = [];
        for ($hour = 8; $hour <= 20; $hour++) {
            for ($minute = 0; $minute < 60; $minute += 30) {
                $time = sprintf('%02d:%02d', $hour, $minute);
                $slots[] = [
                    'value' => $hour + ($minute / 60),
                    'label' => $time,
                    'hour' => $hour,
                    'minute' => $minute
                ];
            }
        }
        return $slots;
    }
    
    private function addCalendarAssets()
    {
        $this->context->controller->addCSS('https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.8/main.min.css');
        $this->context->controller->addJS([
            'https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.8/index.global.min.js',
            'https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.8/locales/fr.global.min.js'
        ]);
    }
    
    private function ajaxReturn($data)
    {
        header('Content-Type: application/json');
        die(json_encode($data));
    }
}