<?php
/**
 * Contrôleur pour le calendrier de gestion des réservations
 * Interface calendrier dédiée à la visualisation et gestion des réservations clients
 */

require_once(dirname(__FILE__) . '/../../classes/Booker.php');
require_once(dirname(__FILE__) . '/../../classes/BookerAuth.php');
require_once(dirname(__FILE__) . '/../../classes/BookerAuthReserved.php');

class AdminBookerCalendarReservationsController extends ModuleAdminController
{
    protected $_module = NULL;
    public $controller_type = 'admin';

    public function __construct()
    {
        $this->display = 'view';
        $this->context = Context::getContext();
        $this->bootstrap = true;
        $this->table = 'booker_auth_reserved';
        $this->identifier = 'id_reserved';
        $this->className = 'BookerAuthReserved';
        $this->meta_title = 'Calendrier des Réservations';
        
        parent::__construct();
    }

    /**
     * Initialisation du contenu principal
     */
    public function initContent()
    {
        // Charger les ressources nécessaires
        $this->addCSS([
            'https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.8/main.min.css',
            $this->module->getPathUri() . 'views/css/admin-calendar.css'
        ]);
        
        $this->addJS([
            'https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.8/index.global.min.js',
            'https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js',
            $this->module->getPathUri() . 'views/js/reservation-calendar.js'
        ]);

        // Récupérer les bookers actifs
        $bookers = $this->getActiveBookers();
        
        // Récupérer les statuts de réservations
        $statuses = $this->getReservationStatuses();
        
        // Paramètres de configuration du calendrier
        $calendar_config = [
            'firstDay' => Configuration::get('BOOKING_CALENDAR_FIRST_DAY', 1),
            'minTime' => Configuration::get('BOOKING_CALENDAR_MIN_TIME', '08:00'),
            'maxTime' => Configuration::get('BOOKING_CALENDAR_MAX_TIME', '20:00'),
            'slotDuration' => Configuration::get('BOOKING_SLOT_DURATION', '00:30:00'),
            'businessHours' => [
                'startTime' => Configuration::get('BOOKING_BUSINESS_HOURS_START', '08:00'),
                'endTime' => Configuration::get('BOOKING_BUSINESS_HOURS_END', '18:00'),
                'daysOfWeek' => explode(',', Configuration::get('BOOKING_ALLOWED_DAYS', '1,2,3,4,5,6,7'))
            ]
        ];

        $this->context->smarty->assign([
            'bookers' => $bookers,
            'statuses' => $statuses,
            'calendar_config' => $calendar_config,
            'ajax_url' => $this->context->link->getAdminLink('AdminBookerCalendarReservations'),
            'current_index' => self::$currentIndex,
            'token' => $this->token,
            'module_path' => $this->module->getPathUri(),
            'can_edit' => true,
            'calendar_type' => 'reservations'
        ]);

        parent::initContent();
    }

    /**
     * Rendu de la vue du calendrier
     */
    public function renderView()
    {
        $template_path = $this->module->getLocalPath() . 'views/templates/admin/calendar_reservations.tpl';
        
        if (!file_exists($template_path)) {
            // Créer le template de base si il n'existe pas
            $this->createDefaultTemplate();
        }
        
        return $this->context->smarty->fetch($template_path);
    }

    /**
     * Récupérer les bookers actifs
     */
    private function getActiveBookers()
    {
        return Db::getInstance()->executeS('
            SELECT b.id_booker, b.name, b.location, b.price, b.capacity
            FROM `' . _DB_PREFIX_ . 'booker` b
            WHERE b.active = 1
            ORDER BY b.sort_order ASC, b.name ASC
        ');
    }

    /**
     * Récupérer les statuts de réservations
     */
    private function getReservationStatuses()
    {
        return [
            0 => ['label' => 'En attente', 'color' => '#ffc107'],
            1 => ['label' => 'Acceptée', 'color' => '#28a745'],
            2 => ['label' => 'En attente de paiement', 'color' => '#17a2b8'],
            3 => ['label' => 'Payée', 'color' => '#007bff'],
            4 => ['label' => 'Annulée', 'color' => '#dc3545'],
            5 => ['label' => 'Expirée', 'color' => '#6c757d'],
            6 => ['label' => 'Terminée', 'color' => '#343a40'],
            7 => ['label' => 'Remboursée', 'color' => '#fd7e14']
        ];
    }

    /**
     * AJAX : Récupérer les réservations pour le calendrier
     */
    public function ajaxProcessGetReservations()
    {
        $id_booker = (int)Tools::getValue('id_booker');
        $status_filter = Tools::getValue('status_filter');
        $start = Tools::getValue('start');
        $end = Tools::getValue('end');
        
        $sql = 'SELECT 
            r.id_reserved,
            r.id_booker,
            r.booking_reference,
            r.customer_firstname,
            r.customer_lastname,
            r.customer_email,
            r.customer_phone,
            r.date_reserved,
            r.date_to,
            r.hour_from,
            r.hour_to,
            r.total_price,
            r.deposit_paid,
            r.status,
            r.payment_status,
            r.notes,
            r.admin_notes,
            b.name as booker_name,
            b.location as booker_location
        FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` r
        LEFT JOIN `' . _DB_PREFIX_ . 'booker` b ON (r.id_booker = b.id_booker)
        WHERE DATE(r.date_reserved) >= "' . pSQL($start) . '"
        AND DATE(COALESCE(r.date_to, r.date_reserved)) <= "' . pSQL($end) . '"';
        
        if ($id_booker > 0) {
            $sql .= ' AND r.id_booker = ' . (int)$id_booker;
        }
        
        if ($status_filter !== '' && $status_filter !== null) {
            $sql .= ' AND r.status = ' . (int)$status_filter;
        }
        
        $sql .= ' ORDER BY r.date_reserved ASC, r.hour_from ASC';
        
        $reservations = Db::getInstance()->executeS($sql);
        $events = [];
        $statuses = $this->getReservationStatuses();
        
        foreach ($reservations as $reservation) {
            $start_datetime = $reservation['date_reserved'] . 'T' . sprintf('%02d:00:00', $reservation['hour_from']);
            $end_datetime = ($reservation['date_to'] ?: $reservation['date_reserved']) . 'T' . sprintf('%02d:00:00', $reservation['hour_to']);
            
            $status_info = $statuses[$reservation['status']] ?? ['label' => 'Inconnu', 'color' => '#6c757d'];
            
            $events[] = [
                'id' => 'res_' . $reservation['id_reserved'],
                'title' => $reservation['customer_firstname'] . ' ' . $reservation['customer_lastname'] . ' - ' . $reservation['booker_name'],
                'start' => $start_datetime,
                'end' => $end_datetime,
                'backgroundColor' => $status_info['color'],
                'borderColor' => $this->getReservationBorderColor($reservation),
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'type' => 'reservation',
                    'id_reserved' => $reservation['id_reserved'],
                    'id_booker' => $reservation['id_booker'],
                    'booking_reference' => $reservation['booking_reference'],
                    'booker_name' => $reservation['booker_name'],
                    'booker_location' => $reservation['booker_location'],
                    'customer_name' => $reservation['customer_firstname'] . ' ' . $reservation['customer_lastname'],
                    'customer_email' => $reservation['customer_email'],
                    'customer_phone' => $reservation['customer_phone'],
                    'total_price' => $reservation['total_price'],
                    'deposit_paid' => $reservation['deposit_paid'],
                    'status' => $reservation['status'],
                    'status_label' => $status_info['label'],
                    'payment_status' => $reservation['payment_status'],
                    'notes' => $reservation['notes'],
                    'admin_notes' => $reservation['admin_notes']
                ]
            ];
        }
        
        die(json_encode($events));
    }

    /**
     * AJAX : Créer une nouvelle réservation
     */
    public function ajaxProcessCreateReservation()
    {
        $id_booker = (int)Tools::getValue('id_booker');
        $customer_firstname = Tools::getValue('customer_firstname');
        $customer_lastname = Tools::getValue('customer_lastname');
        $customer_email = Tools::getValue('customer_email');
        $customer_phone = Tools::getValue('customer_phone');
        $date_reserved = Tools::getValue('date_reserved');
        $date_to = Tools::getValue('date_to');
        $hour_from = (int)Tools::getValue('hour_from');
        $hour_to = (int)Tools::getValue('hour_to');
        $total_price = (float)Tools::getValue('total_price', 0);
        $notes = Tools::getValue('notes');
        $admin_notes = Tools::getValue('admin_notes');

        if (!$id_booker || !$customer_firstname || !$customer_lastname || !$customer_email || !$date_reserved) {
            die(json_encode(['success' => false, 'message' => 'Paramètres manquants']));
        }

        // Vérifier que le booker existe
        $booker = new Booker($id_booker);
        if (!Validate::isLoadedObject($booker)) {
            die(json_encode(['success' => false, 'message' => 'Booker introuvable']));
        }

        // Générer une référence unique
        $booking_reference = 'BK' . date('Ymd') . '-' . strtoupper(Tools::substr(uniqid(), -6));

        // Créer la réservation
        $reservation = new BookerAuthReserved();
        $reservation->id_booker = $id_booker;
        $reservation->booking_reference = $booking_reference;
        $reservation->customer_firstname = $customer_firstname;
        $reservation->customer_lastname = $customer_lastname;
        $reservation->customer_email = $customer_email;
        $reservation->customer_phone = $customer_phone;
        $reservation->date_reserved = $date_reserved;
        $reservation->date_to = $date_to ?: $date_reserved;
        $reservation->hour_from = $hour_from;
        $reservation->hour_to = $hour_to;
        $reservation->total_price = $total_price;
        $reservation->deposit_paid = 0.00;
        $reservation->status = 0; // En attente
        $reservation->payment_status = 'pending';
        $reservation->notes = $notes;
        $reservation->admin_notes = $admin_notes;
        $reservation->date_add = date('Y-m-d H:i:s');
        $reservation->date_upd = date('Y-m-d H:i:s');

        // Définir la date d'expiration
        $expiry_hours = Configuration::get('BOOKING_EXPIRY_HOURS', 24);
        $reservation->date_expiry = date('Y-m-d H:i:s', strtotime('+' . $expiry_hours . ' hours'));

        if ($reservation->add()) {
            die(json_encode([
                'success' => true, 
                'message' => 'Réservation créée avec succès',
                'id' => $reservation->id,
                'booking_reference' => $booking_reference
            ]));
        } else {
            die(json_encode(['success' => false, 'message' => 'Erreur lors de la création']));
        }
    }

    /**
     * AJAX : Mettre à jour une réservation
     */
    public function ajaxProcessUpdateReservation()
    {
        $id_reserved = (int)Tools::getValue('id_reserved');
        $reservation = new BookerAuthReserved($id_reserved);
        
        if (!Validate::isLoadedObject($reservation)) {
            die(json_encode(['success' => false, 'message' => 'Réservation introuvable']));
        }

        $reservation->customer_firstname = Tools::getValue('customer_firstname');
        $reservation->customer_lastname = Tools::getValue('customer_lastname');
        $reservation->customer_email = Tools::getValue('customer_email');
        $reservation->customer_phone = Tools::getValue('customer_phone');
        $reservation->date_reserved = Tools::getValue('date_reserved');
        $reservation->date_to = Tools::getValue('date_to') ?: Tools::getValue('date_reserved');
        $reservation->hour_from = (int)Tools::getValue('hour_from');
        $reservation->hour_to = (int)Tools::getValue('hour_to');
        $reservation->total_price = (float)Tools::getValue('total_price');
        $reservation->status = (int)Tools::getValue('status');
        $reservation->notes = Tools::getValue('notes');
        $reservation->admin_notes = Tools::getValue('admin_notes');
        $reservation->date_upd = date('Y-m-d H:i:s');

        if ($reservation->update()) {
            die(json_encode(['success' => true, 'message' => 'Réservation mise à jour avec succès']));
        } else {
            die(json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']));
        }
    }

    /**
     * AJAX : Supprimer une réservation
     */
    public function ajaxProcessDeleteReservation()
    {
        $id_reserved = (int)Tools::getValue('id_reserved');
        $reservation = new BookerAuthReserved($id_reserved);
        
        if (!Validate::isLoadedObject($reservation)) {
            die(json_encode(['success' => false, 'message' => 'Réservation introuvable']));
        }

        if ($reservation->delete()) {
            die(json_encode(['success' => true, 'message' => 'Réservation supprimée avec succès']));
        } else {
            die(json_encode(['success' => false, 'message' => 'Erreur lors de la suppression']));
        }
    }

    /**
     * AJAX : Actions en lot sur les réservations
     */
    public function ajaxProcessBulkAction()
    {
        $action = Tools::getValue('action');
        $ids = Tools::getValue('ids');
        
        if (!is_array($ids) || empty($ids)) {
            die(json_encode(['success' => false, 'message' => 'Aucune sélection']));
        }

        $success_count = 0;
        $error_count = 0;

        switch ($action) {
            case 'accept':
                foreach ($ids as $id) {
                    $reservation = new BookerAuthReserved(str_replace('res_', '', $id));
                    if (Validate::isLoadedObject($reservation) && $reservation->status == 0) {
                        $reservation->status = 1; // Acceptée
                        if ($reservation->update()) {
                            $success_count++;
                        } else {
                            $error_count++;
                        }
                    }
                }
                break;

            case 'cancel':
                foreach ($ids as $id) {
                    $reservation = new BookerAuthReserved(str_replace('res_', '', $id));
                    if (Validate::isLoadedObject($reservation)) {
                        $reservation->status = 4; // Annulée
                        if ($reservation->update()) {
                            $success_count++;
                        } else {
                            $error_count++;
                        }
                    }
                }
                break;

            case 'create_orders':
                foreach ($ids as $id) {
                    $reservation = new BookerAuthReserved(str_replace('res_', '', $id));
                    if (Validate::isLoadedObject($reservation) && $reservation->status == 1) {
                        if ($this->createOrderForReservation($reservation)) {
                            $success_count++;
                        } else {
                            $error_count++;
                        }
                    }
                }
                break;

            case 'delete':
                foreach ($ids as $id) {
                    $reservation = new BookerAuthReserved(str_replace('res_', '', $id));
                    if (Validate::isLoadedObject($reservation)) {
                        if ($reservation->delete()) {
                            $success_count++;
                        } else {
                            $error_count++;
                        }
                    }
                }
                break;
        }

        die(json_encode([
            'success' => true,
            'message' => "$success_count opération(s) réussie(s), $error_count erreur(s)"
        ]));
    }

    /**
     * AJAX : Obtenir les détails d'une réservation
     */
    public function ajaxProcessGetReservationDetails()
    {
        $id_reserved = (int)Tools::getValue('id_reserved');
        
        $sql = 'SELECT 
            r.*,
            b.name as booker_name,
            b.location as booker_location,
            b.price as booker_price,
            c.firstname as customer_registered_firstname,
            c.lastname as customer_registered_lastname
        FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` r
        LEFT JOIN `' . _DB_PREFIX_ . 'booker` b ON (r.id_booker = b.id_booker)
        LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON (r.id_customer = c.id_customer)
        WHERE r.id_reserved = ' . (int)$id_reserved;
        
        $reservation = Db::getInstance()->getRow($sql);
        
        if ($reservation) {
            $statuses = $this->getReservationStatuses();
            $reservation['status_label'] = $statuses[$reservation['status']]['label'] ?? 'Inconnu';
            
            die(json_encode(['success' => true, 'data' => $reservation]));
        } else {
            die(json_encode(['success' => false, 'message' => 'Réservation introuvable']));
        }
    }

    /**
     * Créer une commande PrestaShop pour une réservation
     */
    private function createOrderForReservation($reservation)
    {
        try {
            // Récupérer ou créer le client
            $customer = $this->getOrCreateCustomer($reservation);
            if (!$customer) return false;
            
            // Récupérer le booker et son produit lié
            $booker = new Booker($reservation->id_booker);
            if (!$booker->id_product) return false;
            
            // Créer la commande
            $order_id = $this->createOrder($customer, $booker, $reservation);
            
            if ($order_id) {
                // Mettre à jour la réservation
                $reservation->status = 2; // En attente de paiement
                $reservation->id_order = $order_id;
                return $reservation->update();
            }
            
            return false;
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog('Erreur création commande réservation #' . $reservation->id . ': ' . $e->getMessage(), 3);
            return false;
        }
    }

    /**
     * Créer ou récupérer un client
     */
    private function getOrCreateCustomer($reservation)
    {
        // Chercher un client existant par email
        $id_customer = Customer::customerExists($reservation->customer_email, true);
        
        if ($id_customer) {
            return new Customer($id_customer);
        }
        
        // Créer un nouveau client
        $customer = new Customer();
        $customer->firstname = $reservation->customer_firstname;
        $customer->lastname = $reservation->customer_lastname;
        $customer->email = $reservation->customer_email;
        $customer->passwd = Tools::encrypt(Tools::passwdGen());
        $customer->active = 1;
        
        if ($customer->add()) {
            return $customer;
        }
        
        return false;
    }

    /**
     * Créer une commande PrestaShop
     */
    private function createOrder($customer, $booker, $reservation)
    {
        $context = Context::getContext();
        
        // Créer le panier
        $cart = new Cart();
        $cart->id_customer = $customer->id;
        $cart->id_address_delivery = Address::getFirstCustomerAddressId($customer->id) ?: 1;
        $cart->id_address_invoice = Address::getFirstCustomerAddressId($customer->id) ?: 1;
        $cart->id_currency = Configuration::get('PS_CURRENCY_DEFAULT');
        $cart->id_lang = Configuration::get('PS_LANG_DEFAULT');
        $cart->add();
        
        // Ajouter le produit au panier
        $cart->updateQty(1, $booker->id_product);
        
        // Créer la commande
        $order = new Order();
        $order->id_customer = $customer->id;
        $order->id_cart = $cart->id;
        $order->id_currency = $cart->id_currency;
        $order->id_lang = $cart->id_lang;
        $order->id_address_delivery = $cart->id_address_delivery;
        $order->id_address_invoice = $cart->id_address_invoice;
        $order->current_state = Configuration::get('BOOKING_STATUS_PENDING_PAYMENT', Configuration::get('PS_OS_BANKWIRE'));
        $order->payment = 'Réservation en attente';
        $order->module = 'booking';
        $order->total_paid = $reservation->total_price;
        $order->total_paid_tax_incl = $reservation->total_price;
        $order->total_paid_tax_excl = $reservation->total_price;
        $order->total_products = $reservation->total_price;
        $order->total_products_wt = $reservation->total_price;
        $order->conversion_rate = 1;
        
        if ($order->add()) {
            return $order->id;
        }
        
        return false;
    }

    /**
     * Obtenir la couleur de bordure d'une réservation
     */
    private function getReservationBorderColor($reservation)
    {
        // Bordure différente selon le statut de paiement
        switch ($reservation['payment_status']) {
            case 'authorized':
            case 'captured':
                return '#007bff'; // Bleu pour paiement OK
            case 'cancelled':
            case 'refunded':
                return '#dc3545'; // Rouge pour paiement annulé
            default:
                return '#6c757d'; // Gris par défaut
        }
    }

    /**
     * Créer le template par défaut
     */
    private function createDefaultTemplate()
    {
        $template_dir = $this->module->getLocalPath() . 'views/templates/admin/';
        if (!is_dir($template_dir)) {
            mkdir($template_dir, 0755, true);
        }
        
        $template_content = $this->getDefaultTemplateContent();
        file_put_contents($template_dir . 'calendar_reservations.tpl', $template_content);
    }

    /**
     * Contenu du template par défaut
     */
    private function getDefaultTemplateContent()
    {
        return '<div class="booking-calendar-container">
    <div class="panel">
        <div class="panel-heading">
            <h3><i class="icon-calendar"></i> Calendrier des Réservations</h3>
        </div>
        <div class="panel-body">
            <div class="calendar-controls mb-3">
                <div class="row">
                    <div class="col-md-3">
                        <select id="booker-filter" class="form-control">
                            <option value="">Tous les éléments</option>
                            {foreach from=$bookers item=booker}
                                <option value="{$booker.id_booker}">{$booker.name}</option>
                            {/foreach}
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="status-filter" class="form-control">
                            <option value="">Tous les statuts</option>
                            {foreach from=$statuses key=status_id item=status_info}
                                <option value="{$status_id}">{$status_info.label}</option>
                            {/foreach}
                        </select>
                    </div>
                    <div class="col-md-6 text-right">
                        <button class="btn btn-primary" id="add-reservation">
                            <i class="icon-plus"></i> Ajouter réservation
                        </button>
                        <button class="btn btn-success" id="bulk-accept" disabled>
                            <i class="icon-check"></i> Accepter
                        </button>
                        <button class="btn btn-info" id="bulk-create-orders" disabled>
                            <i class="icon-shopping-cart"></i> Créer commandes
                        </button>
                        <button class="btn btn-danger" id="bulk-cancel" disabled>
                            <i class="icon-remove"></i> Annuler
                        </button>
                    </div>
                </div>
                
                <div class="row mt-2">
                    <div class="col-md-12">
                        <div class="legend">
                            <small>
                                <strong>Légende :</strong>
                                {foreach from=$statuses key=status_id item=status_info}
                                    <span class="legend-item" style="background-color: {$status_info.color};">{$status_info.label}</span>
                                {/foreach}
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            <div id="calendar"></div>
        </div>
    </div>
</div>

<script>
var bookingCalendarConfig = {$calendar_config|json_encode};
var bookingStatuses = {$statuses|json_encode};
var ajaxUrl = "{$ajax_url}";
var currentToken = "{$token}";
</script>';
    }
}
?>