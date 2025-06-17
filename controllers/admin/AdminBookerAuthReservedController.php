<?php
/**
 * AdminBookerAuthReservedController - Gestion des réservations avec statuts et cautions
 * Version 2.1.5 - Avec système de validation et création de commandes
 */

require_once _PS_MODULE_DIR_ . 'booking/classes/BookerAuthReserved.php';
require_once _PS_MODULE_DIR_ . 'booking/classes/Booker.php';
require_once _PS_MODULE_DIR_ . 'booking/classes/StripeDepositManager.php';

class AdminBookerAuthReservedController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'booker_auth_reserved';
        $this->className = 'BookerAuthReserved';
        $this->identifier = 'id_reserved';
        $this->lang = false;
        
        parent::__construct();
        
        $this->addRowAction('edit');
        $this->addRowAction('delete');
        $this->addRowAction('view_details');
        $this->addRowAction('validate');
        $this->addRowAction('cancel');
        $this->addRowAction('send_email');
        
        $this->bulk_actions = array(
            'delete' => array(
                'text' => $this->l('Delete selected'),
                'confirm' => $this->l('Delete selected reservations?'),
                'icon' => 'icon-trash'
            ),
            'validate_selection' => array(
                'text' => $this->l('Validate selection'),
                'icon' => 'icon-check text-success'
            ),
            'cancel_selection' => array(
                'text' => $this->l('Cancel selection'),
                'icon' => 'icon-times text-danger'
            ),
            'send_reminder' => array(
                'text' => $this->l('Send reminder'),
                'icon' => 'icon-envelope'
            ),
            'export_csv' => array(
                'text' => $this->l('Export CSV'),
                'icon' => 'icon-download'
            )
        );
        
        $this->fields_list = array(
            'id_reserved' => array(
                'title' => $this->l('ID'),
                'align' => 'center',
                'class' => 'fixed-width-xs'
            ),
            'booking_reference' => array(
                'title' => $this->l('Reference'),
                'width' => 120,
                'align' => 'center'
            ),
            'booker_name' => array(
                'title' => $this->l('Item'),
                'width' => 150,
                'filter_key' => 'b!name',
                'orderby' => false
            ),
            'customer_name' => array(
                'title' => $this->l('Customer'),
                'width' => 180,
                'orderby' => false,
                'callback' => 'formatCustomerName'
            ),
            'customer_email' => array(
                'title' => $this->l('Email'),
                'width' => 200
            ),
            'date_reserved' => array(
                'title' => $this->l('Date'),
                'width' => 100,
                'type' => 'date',
                'align' => 'center'
            ),
            'time_slot' => array(
                'title' => $this->l('Time'),
                'width' => 120,
                'align' => 'center',
                'orderby' => false,
                'search' => false,
                'callback' => 'formatTimeSlot'
            ),
            'total_price' => array(
                'title' => $this->l('Total Price'),
                'width' => 120,
                'type' => 'price',
                'align' => 'text-right'
            ),
            'deposit_info' => array(
                'title' => $this->l('Deposit'),
                'width' => 120,
                'align' => 'center',
                'orderby' => false,
                'search' => false,
                'callback' => 'formatDepositInfo'
            ),
            'status_badge' => array(
                'title' => $this->l('Status'),
                'width' => 120,
                'align' => 'center',
                'orderby' => false,
                'search' => false,
                'callback' => 'formatStatusBadge'
            ),
            'payment_status' => array(
                'title' => $this->l('Payment'),
                'width' => 100,
                'align' => 'center',
                'callback' => 'formatPaymentStatus'
            ),
            'order_link' => array(
                'title' => $this->l('Order'),
                'width' => 80,
                'align' => 'center',
                'orderby' => false,
                'search' => false,
                'callback' => 'formatOrderLink'
            ),
            'date_add' => array(
                'title' => $this->l('Created'),
                'width' => 130,
                'align' => 'right',
                'type' => 'datetime'
            )
        );
        
        $this->_select = 'b.name as booker_name, 
                         CONCAT(a.customer_firstname, " ", a.customer_lastname) as customer_name,
                         a.hour_from, a.hour_to';
        $this->_join = 'LEFT JOIN `' . _DB_PREFIX_ . 'booker` b ON (a.id_booker = b.id_booker)';
        
        $this->shopLinkType = 'shop';
        $this->multishop_context = Shop::CONTEXT_ALL;
    }

    /**
     * Formatter pour le nom du client
     */
    public static function formatCustomerName($customer_name, $row)
    {
        $name = trim($customer_name);
        if (empty($name)) {
            $name = $row['customer_email'];
        }
        
        // Ajouter un lien vers le profil client si disponible
        if (!empty($row['id_customer'])) {
            return '<a href="' . Context::getContext()->link->getAdminLink('AdminCustomers') . '&id_customer=' . $row['id_customer'] . '&viewcustomer" target="_blank">' . $name . '</a>';
        }
        
        return $name;
    }

    /**
     * Formatter pour le créneau horaire
     */
    public static function formatTimeSlot($time, $row)
    {
        $hour_from = str_pad($row['hour_from'], 2, '0', STR_PAD_LEFT) . ':00';
        $hour_to = str_pad($row['hour_to'], 2, '0', STR_PAD_LEFT) . ':00';
        
        return '<span class="label label-default">' . $hour_from . ' - ' . $hour_to . '</span>';
    }

    /**
     * Formatter pour les informations de caution
     */
    public static function formatDepositInfo($deposit, $row)
    {
        if (!$row['deposit_required']) {
            return '<span class="label label-default">No deposit</span>';
        }
        
        $status = $row['deposit_status'];
        $colors = array(
            'none' => 'default',
            'pending' => 'warning',
            'authorized' => 'info',
            'captured' => 'danger',
            'released' => 'success',
            'failed' => 'danger'
        );
        
        $color = isset($colors[$status]) ? $colors[$status] : 'default';
        $label = ucfirst(str_replace('_', ' ', $status));
        
        return '<span class="label label-' . $color . '">' . $label . '</span>';
    }

    /**
     * Formatter pour le badge de statut
     */
    public static function formatStatusBadge($status, $row)
    {
        $statuses = array(
            0 => array('label' => 'Pending', 'color' => 'warning'),
            1 => array('label' => 'Confirmed', 'color' => 'info'),
            2 => array('label' => 'Paid', 'color' => 'success'),
            3 => array('label' => 'Cancelled', 'color' => 'danger'),
            4 => array('label' => 'Completed', 'color' => 'success'),
            5 => array('label' => 'Refunded', 'color' => 'warning')
        );
        
        $status_info = isset($statuses[$row['status']]) ? $statuses[$row['status']] : array('label' => 'Unknown', 'color' => 'default');
        
        return '<span class="label label-' . $status_info['color'] . '">' . $status_info['label'] . '</span>';
    }

    /**
     * Formatter pour le statut de paiement
     */
    public static function formatPaymentStatus($payment_status, $row)
    {
        $colors = array(
            'pending' => 'warning',
            'authorized' => 'info',
            'captured' => 'success',
            'cancelled' => 'danger',
            'refunded' => 'warning'
        );
        
        $color = isset($colors[$payment_status]) ? $colors[$payment_status] : 'default';
        $label = ucfirst($payment_status);
        
        return '<span class="label label-' . $color . '">' . $label . '</span>';
    }

    /**
     * Formatter pour le lien vers la commande
     */
    public static function formatOrderLink($order, $row)
    {
        if (empty($row['id_order'])) {
            return '<span class="text-muted">No order</span>';
        }
        
        return '<a href="' . Context::getContext()->link->getAdminLink('AdminOrders') . '&id_order=' . $row['id_order'] . '&vieworder" target="_blank" class="btn btn-xs btn-default">
                    <i class="icon-external-link"></i> #' . $row['id_order'] . '
                </a>';
    }

    /**
     * Formulaire d'édition
     */
    public function renderForm()
    {
        $this->fields_form = array(
            'legend' => array(
                'title' => $this->l('Reservation Details'),
                'icon' => 'icon-calendar'
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('Booking Reference'),
                    'name' => 'booking_reference',
                    'readonly' => true,
                    'desc' => $this->l('Unique reference for this booking')
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('Status'),
                    'name' => 'status',
                    'required' => true,
                    'options' => array(
                        'query' => array(
                            array('key' => 0, 'name' => $this->l('Pending')),
                            array('key' => 1, 'name' => $this->l('Confirmed')),
                            array('key' => 2, 'name' => $this->l('Paid')),
                            array('key' => 3, 'name' => $this->l('Cancelled')),
                            array('key' => 4, 'name' => $this->l('Completed')),
                            array('key' => 5, 'name' => $this->l('Refunded'))
                        ),
                        'id' => 'key',
                        'name' => 'name'
                    ),
                    'desc' => $this->l('Current status of the reservation')
                ),
                // Informations client
                array(
                    'type' => 'text',
                    'label' => $this->l('Customer First Name'),
                    'name' => 'customer_firstname',
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Customer Last Name'),
                    'name' => 'customer_lastname',
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Customer Email'),
                    'name' => 'customer_email',
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Customer Phone'),
                    'name' => 'customer_phone'
                ),
                // Détails de la réservation
                array(
                    'type' => 'date',
                    'label' => $this->l('Date'),
                    'name' => 'date_reserved',
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Hour From'),
                    'name' => 'hour_from',
                    'class' => 'fixed-width-sm',
                    'required' => true,
                    'desc' => $this->l('Start hour (24h format)')
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Hour To'),
                    'name' => 'hour_to',
                    'class' => 'fixed-width-sm',
                    'required' => true,
                    'desc' => $this->l('End hour (24h format)')
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Total Price'),
                    'name' => 'total_price',
                    'class' => 'fixed-width-sm',
                    'suffix' => Context::getContext()->currency->sign
                ),
                // Notes
                array(
                    'type' => 'textarea',
                    'label' => $this->l('Customer Notes'),
                    'name' => 'notes',
                    'rows' => 3,
                    'cols' => 40
                ),
                array(
                    'type' => 'textarea',
                    'label' => $this->l('Admin Notes'),
                    'name' => 'admin_notes',
                    'rows' => 3,
                    'cols' => 40,
                    'desc' => $this->l('Internal notes (not visible to customer)')
                )
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
            )
        );

        if (!($obj = $this->loadObject(true))) {
            return;
        }

        return parent::renderForm();
    }

    /**
     * Action de validation d'une réservation
     */
    public function displayValidateLink($token, $id, $name = null)
    {
        return '<a class="btn btn-success btn-xs" href="' . self::$currentIndex . '&configure=booking&token=' . $token . '&validate_reservation=' . $id . '" onclick="return confirm(\'Validate this reservation?\')">
                    <i class="icon-check"></i> ' . $this->l('Validate') . '
                </a>';
    }

    /**
     * Action d'annulation d'une réservation
     */
    public function displayCancelLink($token, $id, $name = null)
    {
        return '<a class="btn btn-danger btn-xs" href="' . self::$currentIndex . '&configure=booking&token=' . $token . '&cancel_reservation=' . $id . '" onclick="return confirm(\'Cancel this reservation?\')">
                    <i class="icon-times"></i> ' . $this->l('Cancel') . '
                </a>';
    }

    /**
     * Action d'envoi d'email
     */
    public function displaySendEmailLink($token, $id, $name = null)
    {
        return '<a class="btn btn-info btn-xs" href="' . self::$currentIndex . '&configure=booking&token=' . $token . '&send_email=' . $id . '">
                    <i class="icon-envelope"></i> ' . $this->l('Send Email') . '
                </a>';
    }

    /**
     * Action de détails
     */
    public function displayViewDetailsLink($token, $id, $name = null)
    {
        return '<a class="btn btn-default btn-xs" href="' . self::$currentIndex . '&configure=booking&token=' . $token . '&view_reservation_details=' . $id . '">
                    <i class="icon-eye"></i> ' . $this->l('Details') . '
                </a>';
    }

    /**
     * Traitement de la validation d'une réservation
     */
    public function processValidateReservation()
    {
        if ($id_reserved = (int)Tools::getValue('validate_reservation')) {
            $reservation = new BookerAuthReserved($id_reserved);
            
            if (Validate::isLoadedObject($reservation)) {
                // Changer le statut
                $reservation->status = BookerAuthReserved::STATUS_CONFIRMED;
                $reservation->date_upd = date('Y-m-d H:i:s');
                
                if ($reservation->update()) {
                    // Créer une commande en attente de paiement
                    $this->createPendingOrder($reservation);
                    
                    // Envoyer email de confirmation
                    $this->sendConfirmationEmail($reservation);
                    
                    $this->confirmations[] = $this->l('Reservation validated successfully');
                } else {
                    $this->errors[] = $this->l('Error occurred during validation');
                }
            }
        }
    }

    /**
     * Traitement de l'annulation d'une réservation
     */
    public function processCancelReservation()
    {
        if ($id_reserved = (int)Tools::getValue('cancel_reservation')) {
            $reservation = new BookerAuthReserved($id_reserved);
            
            if (Validate::isLoadedObject($reservation)) {
                // Libérer la caution si elle est autorisée
                if ($reservation->deposit_status == 'authorized') {
                    $depositManager = new StripeDepositManager();
                    $depositManager->releaseDeposit($id_reserved);
                }
                
                // Changer le statut
                $reservation->status = BookerAuthReserved::STATUS_CANCELLED;
                $reservation->date_upd = date('Y-m-d H:i:s');
                
                if ($reservation->update()) {
                    // Libérer le créneau
                    $this->releaseAvailabilitySlot($reservation);
                    
                    // Envoyer email d'annulation
                    $this->sendCancellationEmail($reservation);
                    
                    $this->confirmations[] = $this->l('Reservation cancelled successfully');
                } else {
                    $this->errors[] = $this->l('Error occurred during cancellation');
                }
            }
        }
    }

    /**
     * Créer une commande en attente de paiement
     */
    private function createPendingOrder($reservation)
    {
        try {
            // Récupérer ou créer le client
            $customer = $this->getOrCreateCustomer($reservation);
            
            // Créer le panier
            $cart = new Cart();
            $cart->id_customer = $customer->id;
            $cart->id_lang = (int)Configuration::get('PS_LANG_DEFAULT');
            $cart->id_currency = (int)Configuration::get('PS_CURRENCY_DEFAULT');
            $cart->id_carrier = (int)Configuration::get('PS_CARRIER_DEFAULT');
            $cart->add();
            
            // Récupérer le produit lié au booker
            $product = $this->getLinkedProduct($reservation->id_booker);
            
            if ($product) {
                // Ajouter le produit au panier
                $cart->updateQty(1, $product['id_product']);
                
                // Créer la commande
                $order_status = (int)Configuration::get('BOOKING_STATUS_PENDING_PAYMENT', Configuration::get('PS_OS_BANKWIRE'));
                
                $order = new Order();
                $order->id_customer = $customer->id;
                $order->id_cart = $cart->id;
                $order->id_currency = $cart->id_currency;
                $order->id_lang = $cart->id_lang;
                $order->id_carrier = $cart->id_carrier;
                $order->current_state = $order_status;
                $order->payment = 'Booking Module';
                $order->module = 'booking';
                $order->total_paid = $reservation->total_price;
                $order->total_paid_tax_incl = $reservation->total_price;
                $order->total_paid_tax_excl = $reservation->total_price;
                $order->total_products = $reservation->total_price;
                $order->total_products_wt = $reservation->total_price;
                $order->reference = Order::generateReference();
                $order->add();
                
                // Lier la réservation à la commande
                $reservation->id_order = $order->id;
                $reservation->update();
                
                return $order;
            }
            
        } catch (Exception $e) {
            $this->errors[] = $this->l('Error creating order: ') . $e->getMessage();
        }
        
        return false;
    }

    /**
     * Récupérer ou créer un client
     */
    private function getOrCreateCustomer($reservation)
    {
        // Chercher un client existant avec cet email
        $customer = new Customer();
        $customer->getByEmail($reservation->customer_email);
        
        if (!Validate::isLoadedObject($customer)) {
            // Créer un nouveau client
            $customer = new Customer();
            $customer->firstname = $reservation->customer_firstname;
            $customer->lastname = $reservation->customer_lastname;
            $customer->email = $reservation->customer_email;
            $customer->passwd = Tools::hash(Tools::passwdGen());
            $customer->id_default_group = (int)Configuration::get('PS_CUSTOMER_GROUP');
            $customer->add();
        }
        
        return $customer;
    }

    /**
     * Récupérer le produit lié à un booker
     */
    private function getLinkedProduct($id_booker)
    {
        return Db::getInstance()->getRow('
            SELECT p.id_product, pl.name
            FROM ' . _DB_PREFIX_ . 'booker_product bp
            LEFT JOIN ' . _DB_PREFIX_ . 'product p ON bp.id_product = p.id_product
            LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl ON p.id_product = pl.id_product
            WHERE bp.id_booker = ' . (int)$id_booker . '
            AND pl.id_lang = ' . (int)$this->context->language->id . '
            LIMIT 1'
        );
    }

    /**
     * Libérer un créneau de disponibilité
     */
    private function releaseAvailabilitySlot($reservation)
    {
        return Db::getInstance()->execute('
            UPDATE ' . _DB_PREFIX_ . 'booker_auth 
            SET current_bookings = current_bookings - 1
            WHERE id_auth = ' . (int)$reservation->id_auth . '
            AND current_bookings > 0'
        );
    }

    /**
     * Envoyer email de confirmation
     */
    private function sendConfirmationEmail($reservation)
    {
        return Mail::Send(
            (int)Configuration::get('PS_LANG_DEFAULT'),
            'booking_confirmed',
            $this->l('Booking Confirmed'),
            array(
                '{booking_reference}' => $reservation->booking_reference,
                '{customer_name}' => $reservation->customer_firstname . ' ' . $reservation->customer_lastname,
                '{date_reserved}' => $reservation->date_reserved
            ),
            $reservation->customer_email,
            $reservation->customer_firstname . ' ' . $reservation->customer_lastname,
            null,
            null,
            null,
            null,
            _PS_MODULE_DIR_ . 'booking/mails/'
        );
    }

    /**
     * Envoyer email d'annulation
     */
    private function sendCancellationEmail($reservation)
    {
        return Mail::Send(
            (int)Configuration::get('PS_LANG_DEFAULT'),
            'booking_cancelled',
            $this->l('Booking Cancelled'),
            array(
                '{booking_reference}' => $reservation->booking_reference,
                '{customer_name}' => $reservation->customer_firstname . ' ' . $reservation->customer_lastname,
                '{date_reserved}' => $reservation->date_reserved
            ),
            $reservation->customer_email,
            $reservation->customer_firstname . ' ' . $reservation->customer_lastname,
            null,
            null,
            null,
            null,
            _PS_MODULE_DIR_ . 'booking/mails/'
        );
    }

    /**
     * Validation en lot
     */
    public function processBulkValidateSelection()
    {
        $selection = Tools::getValue($this->table . 'Box');
        
        if (is_array($selection) && count($selection)) {
            $validated = 0;
            foreach ($selection as $id_reserved) {
                $reservation = new BookerAuthReserved((int)$id_reserved);
                if (Validate::isLoadedObject($reservation) && $reservation->status == BookerAuthReserved::STATUS_PENDING) {
                    $reservation->status = BookerAuthReserved::STATUS_CONFIRMED;
                    $reservation->date_upd = date('Y-m-d H:i:s');
                    
                    if ($reservation->update()) {
                        $this->createPendingOrder($reservation);
                        $this->sendConfirmationEmail($reservation);
                        $validated++;
                    }
                }
            }
            
            $this->confirmations[] = sprintf($this->l('%d reservations validated successfully'), $validated);
        }
    }

    /**
     * Ajouter du CSS et JS personnalisés
     */
    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);
        
        $this->addCSS(_MODULE_DIR_ . 'booking/views/css/admin-reservations.css');
        $this->addJS(_MODULE_DIR_ . 'booking/views/js/admin-reservations.js');
    }

    /**
     * Toolbar avec actions spéciales
     */
    public function initToolbar()
    {
        parent::initToolbar();
        
        $this->toolbar_btn['export'] = array(
            'href' => self::$currentIndex . '&configure=booking&token=' . $this->token . '&export_reservations=1',
            'desc' => $this->l('Export Reservations'),
            'icon' => 'process-icon-export'
        );
        
        $this->toolbar_btn['stats'] = array(
            'href' => self::$currentIndex . '&configure=booking&token=' . $this->token . '&reservation_stats=1',
            'desc' => $this->l('Statistics'),
            'icon' => 'process-icon-stats'
        );
    }
}
