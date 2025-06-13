<?php
/**
 * Classe BookerAuthReserved - Gestion des réservations avec statuts avancés
 * Version 2.0 avec intégration commandes et paiements
 */

class BookerAuthReserved extends ObjectModel
{
    /** @var int */
    public $id_reserved;
    
    /** @var int */
    public $id_auth;
    
    /** @var int */
    public $id_booker;
    
    /** @var int */
    public $id_customer;
    
    /** @var int */
    public $id_order;
    
    /** @var string */
    public $booking_reference;
    
    /** @var string */
    public $customer_firstname;
    
    /** @var string */
    public $customer_lastname;
    
    /** @var string */
    public $customer_email;
    
    /** @var string */
    public $customer_phone;
    
    /** @var string */
    public $date_start;
    
    /** @var string */
    public $date_end;
    
    /** @var int */
    public $status = 0;
    
    /** @var float */
    public $total_price;
    
    /** @var float */
    public $deposit_paid = 0.00;
    
    /** @var string */
    public $stripe_payment_intent;
    
    /** @var string */
    public $stripe_setup_intent;
    
    /** @var string */
    public $notes;
    
    /** @var string */
    public $admin_notes;
    
    /** @var string */
    public $date_reserved;
    
    /** @var string */
    public $date_expiry;
    
    /** @var string */
    public $date_confirmed;
    
    /** @var string */
    public $date_add;
    
    /** @var string */
    public $date_upd;
    
    // Constantes de statuts
    const STATUS_PENDING = 0;        // En attente de validation
    const STATUS_VALIDATED = 1;      // Validée (en attente de paiement)
    const STATUS_PAID = 2;           // Payée et confirmée
    const STATUS_CANCELLED = 3;      // Annulée
    const STATUS_EXPIRED = 4;        // Expirée
    const STATUS_REFUNDED = 5;       // Remboursée
    const STATUS_IN_PROGRESS = 6;    // En cours (jour J)
    const STATUS_COMPLETED = 7;      // Terminée
    
    /**
     * @see ObjectModel::$definition
     */
    public static $definition = array(
        'table' => 'booker_auth_reserved',
        'primary' => 'id_reserved',
        'fields' => array(
            'id_auth' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true),
            'id_booker' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true),
            'id_customer' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
            'id_order' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
            'booking_reference' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true, 'size' => 50),
            'customer_firstname' => array('type' => self::TYPE_STRING, 'validate' => 'isName', 'size' => 100),
            'customer_lastname' => array('type' => self::TYPE_STRING, 'validate' => 'isName', 'size' => 100),
            'customer_email' => array('type' => self::TYPE_STRING, 'validate' => 'isEmail', 'size' => 255),
            'customer_phone' => array('type' => self::TYPE_STRING, 'validate' => 'isPhoneNumber', 'size' => 20),
            'date_start' => array('type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true),
            'date_end' => array('type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true),
            'status' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'total_price' => array('type' => self::TYPE_FLOAT, 'validate' => 'isPrice'),
            'deposit_paid' => array('type' => self::TYPE_FLOAT, 'validate' => 'isPrice'),
            'stripe_payment_intent' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 255),
            'stripe_setup_intent' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 255),
            'notes' => array('type' => self::TYPE_HTML, 'validate' => 'isCleanHtml'),
            'admin_notes' => array('type' => self::TYPE_HTML, 'validate' => 'isCleanHtml'),
            'date_reserved' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
            'date_expiry' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
            'date_confirmed' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
            'date_upd' => array('type' => self::TYPE_DATE, 'validate' => 'isDate')
        ),
    );
    
    public function __construct($id = null, $id_lang = null, $id_shop = null)
    {
        parent::__construct($id, $id_lang, $id_shop);
        
        // Génération automatique de la référence si nouvelle réservation
        if (!$this->id && !$this->booking_reference) {
            $this->booking_reference = $this->generateBookingReference();
        }
    }
    
    /**
     * Génération d'une référence unique de réservation
     */
    public function generateBookingReference()
    {
        $prefix = 'BK';
        $timestamp = time();
        $random = sprintf('%04d', mt_rand(0, 9999));
        return $prefix . date('ymd', $timestamp) . $random;
    }
    
    /**
     * Obtenir tous les statuts possibles
     */
    public static function getStatuses()
    {
        return array(
            self::STATUS_PENDING => 'En attente de validation',
            self::STATUS_VALIDATED => 'Validée (en attente de paiement)',
            self::STATUS_PAID => 'Payée et confirmée',
            self::STATUS_CANCELLED => 'Annulée',
            self::STATUS_EXPIRED => 'Expirée',
            self::STATUS_REFUNDED => 'Remboursée',
            self::STATUS_IN_PROGRESS => 'En cours',
            self::STATUS_COMPLETED => 'Terminée'
        );
    }
    
    /**
     * Obtenir le libellé d'un statut
     */
    public static function getStatusLabel($status)
    {
        $statuses = self::getStatuses();
        return isset($statuses[$status]) ? $statuses[$status] : 'Inconnu';
    }
    
    /**
     * Obtenir la couleur associée à un statut
     */
    public static function getStatusColor($status)
    {
        $colors = array(
            self::STATUS_PENDING => '#ffc107',     // Jaune
            self::STATUS_VALIDATED => '#17a2b8',   // Bleu
            self::STATUS_PAID => '#28a745',        // Vert
            self::STATUS_CANCELLED => '#dc3545',   // Rouge
            self::STATUS_EXPIRED => '#6c757d',     // Gris
            self::STATUS_REFUNDED => '#fd7e14',    // Orange
            self::STATUS_IN_PROGRESS => '#007bff', // Bleu foncé
            self::STATUS_COMPLETED => '#6f42c1'    // Violet
        );
        
        return isset($colors[$status]) ? $colors[$status] : '#6c757d';
    }
    
    /**
     * Valider une réservation
     */
    public function validate($admin_notes = '')
    {
        if ($this->status != self::STATUS_PENDING) {
            throw new Exception('Seules les réservations en attente peuvent être validées');
        }
        
        $this->status = self::STATUS_VALIDATED;
        $this->admin_notes = $admin_notes;
        $this->date_confirmed = date('Y-m-d H:i:s');
        $this->date_upd = date('Y-m-d H:i:s');
        
        $result = $this->save();
        
        if ($result) {
            // Créer automatiquement une commande si configuré
            if (Configuration::get('BOOKING_AUTO_CREATE_ORDER')) {
                $this->createOrder();
            }
            
            // Envoyer notification
            $this->sendValidationNotification();
            
            // Log de l'action
            PrestaShopLogger::addLog(
                'Réservation validée: ' . $this->booking_reference,
                1,
                null,
                'BookerAuthReserved',
                $this->id,
                true
            );
        }
        
        return $result;
    }
    
    /**
     * Annuler une réservation
     */
    public function cancel($admin_notes = '', $refund = false)
    {
        if (in_array($this->status, [self::STATUS_CANCELLED, self::STATUS_COMPLETED])) {
            throw new Exception('Cette réservation ne peut pas être annulée');
        }
        
        $old_status = $this->status;
        $this->status = self::STATUS_CANCELLED;
        $this->admin_notes = $admin_notes;
        $this->date_upd = date('Y-m-d H:i:s');
        
        $result = $this->save();
        
        if ($result) {
            // Libérer le créneau
            $this->releaseTimeSlot();
            
            // Traiter le remboursement si nécessaire
            if ($refund && $old_status == self::STATUS_PAID) {
                $this->processRefund();
            }
            
            // Envoyer notification
            $this->sendCancellationNotification();
            
            // Log de l'action
            PrestaShopLogger::addLog(
                'Réservation annulée: ' . $this->booking_reference,
                1,
                null,
                'BookerAuthReserved',
                $this->id,
                true
            );
        }
        
        return $result;
    }
    
    /**
     * Marquer comme payée
     */
    public function markAsPaid($payment_details = [])
    {
        if ($this->status != self::STATUS_VALIDATED) {
            throw new Exception('Seules les réservations validées peuvent être marquées comme payées');
        }
        
        $this->status = self::STATUS_PAID;
        $this->date_upd = date('Y-m-d H:i:s');
        
        if (isset($payment_details['stripe_payment_intent'])) {
            $this->stripe_payment_intent = $payment_details['stripe_payment_intent'];
        }
        
        if (isset($payment_details['deposit_paid'])) {
            $this->deposit_paid = (float)$payment_details['deposit_paid'];
        }
        
        $result = $this->save();
        
        if ($result) {
            // Envoyer notification de confirmation
            $this->sendPaymentConfirmationNotification();
            
            // Log de l'action
            PrestaShopLogger::addLog(
                'Réservation payée: ' . $this->booking_reference,
                1,
                null,
                'BookerAuthReserved',
                $this->id,
                true
            );
        }
        
        return $result;
    }
    
    /**
     * Créer une commande PrestaShop pour cette réservation
     */
    public function createOrder()
    {
        try {
            // Vérifier qu'aucune commande n'existe déjà
            if ($this->id_order) {
                return false;
            }
            
            // Récupérer les informations du booker
            $booker = new Booker($this->id_booker);
            if (!Validate::isLoadedObject($booker)) {
                throw new Exception('Booker introuvable');
            }
            
            // Créer ou récupérer le client
            $customer = $this->getOrCreateCustomer();
            
            // Créer le panier
            $cart = new Cart();
            $cart->id_customer = $customer->id;
            $cart->id_address_delivery = $customer->id_address;
            $cart->id_address_invoice = $customer->id_address;
            $cart->id_lang = (int)Configuration::get('PS_LANG_DEFAULT');
            $cart->id_currency = (int)Configuration::get('PS_CURRENCY_DEFAULT');
            $cart->id_carrier = (int)Configuration::get('PS_CARRIER_DEFAULT');
            $cart->save();
            
            // Ajouter le produit au panier si le booker a un produit associé
            if ($booker->id_product) {
                $cart->updateQty(1, $booker->id_product);
            }
            
            // Créer la commande
            $order_status = Configuration::get('BOOKING_ORDER_STATUS') ?: Configuration::get('PS_OS_PREPARATION');
            
            $payment_module = Module::getInstanceByName('bankwire'); // Module de paiement par défaut
            if (!$payment_module) {
                $payment_module = new PaymentModule();
            }
            
            $order_total = $this->total_price ?: $booker->price;
            
            $payment_module->validateOrder(
                $cart->id,
                $order_status,
                $order_total,
                'Réservation - ' . $booker->name,
                'Réservation ' . $this->booking_reference . ' du ' . date('d/m/Y', strtotime($this->date_start)),
                [],
                null,
                false,
                $customer->secure_key
            );
            
            // Récupérer l'ID de la commande créée
            $this->id_order = $payment_module->currentOrder;
            $this->save();
            
            return true;
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'Erreur création commande pour réservation ' . $this->booking_reference . ': ' . $e->getMessage(),
                3,
                null,
                'BookerAuthReserved',
                $this->id,
                true
            );
            return false;
        }
    }
    
    /**
     * Obtenir ou créer un client pour cette réservation
     */
    private function getOrCreateCustomer()
    {
        // Si un client est déjà associé
        if ($this->id_customer) {
            $customer = new Customer($this->id_customer);
            if (Validate::isLoadedObject($customer)) {
                return $customer;
            }
        }
        
        // Chercher un client existant par email
        $id_customer = Customer::customerExists($this->customer_email, true);
        if ($id_customer) {
            $customer = new Customer($id_customer);
            $this->id_customer = $id_customer;
            $this->save();
            return $customer;
        }
        
        // Créer nouveau client
        $customer = new Customer();
        $customer->firstname = $this->customer_firstname;
        $customer->lastname = $this->customer_lastname;
        $customer->email = $this->customer_email;
        $customer->passwd = Tools::encrypt(Tools::passwdGen());
        $customer->id_default_group = (int)Configuration::get('PS_CUSTOMER_GROUP');
        $customer->add();
        
        // Créer adresse par défaut
        $address = new Address();
        $address->id_customer = $customer->id;
        $address->firstname = $this->customer_firstname;
        $address->lastname = $this->customer_lastname;
        $address->phone = $this->customer_phone;
        $address->id_country = (int)Configuration::get('PS_COUNTRY_DEFAULT');
        $address->alias = 'Réservation';
        $address->city = 'Non spécifié';
        $address->postcode = '00000';
        $address->address1 = 'Adresse de réservation';
        $address->add();
        
        $customer->id_address = $address->id;
        $customer->save();
        
        $this->id_customer = $customer->id;
        $this->save();
        
        return $customer;
    }
    
    /**
     * Libérer le créneau de disponibilité
     */
    private function releaseTimeSlot()
    {
        $auth = new BookerAuth($this->id_auth);
        if (Validate::isLoadedObject($auth) && $auth->current_bookings > 0) {
            $auth->current_bookings--;
            $auth->save();
        }
    }
    
    /**
     * Traiter un remboursement
     */
    private function processRefund()
    {
        // À implémenter selon le module de paiement utilisé
        // Intégration avec Stripe, PayPal, etc.
        
        if ($this->stripe_payment_intent && Configuration::get('BOOKING_STRIPE_ENABLED')) {
            // Traitement remboursement Stripe
            $this->processStripeRefund();
        }
        
        $this->status = self::STATUS_REFUNDED;
        $this->save();
    }
    
    /**
     * Traitement remboursement Stripe
     */
    private function processStripeRefund()
    {
        // À implémenter avec l'API Stripe
        // Exemple de structure:
        /*
        try {
            \Stripe\Stripe::setApiKey(Configuration::get('STRIPE_SECRET_KEY'));
            
            $refund = \Stripe\Refund::create([
                'payment_intent' => $this->stripe_payment_intent,
                'amount' => $this->deposit_paid * 100, // Stripe utilise les centimes
                'reason' => 'requested_by_customer'
            ]);
            
            return $refund->status === 'succeeded';
        } catch (Exception $e) {
            PrestaShopLogger::addLog('Erreur remboursement Stripe: ' . $e->getMessage(), 3);
            return false;
        }
        */
    }
    
    /**
     * Nettoyer les réservations expirées
     */
    public static function cancelExpiredReservations($expiry_hours = 24)
    {
        $expiry_date = date('Y-m-d H:i:s', strtotime('-' . (int)$expiry_hours . ' hours'));
        
        $sql = 'SELECT id_reserved FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`
                WHERE status = ' . self::STATUS_PENDING . '
                AND date_reserved < "' . pSQL($expiry_date) . '"';
                
        $expired_ids = Db::getInstance()->executeS($sql);
        $count = 0;
        
        foreach ($expired_ids as $row) {
            $reservation = new self($row['id_reserved']);
            if (Validate::isLoadedObject($reservation)) {
                $reservation->status = self::STATUS_EXPIRED;
                $reservation->date_upd = date('Y-m-d H:i:s');
                if ($reservation->save()) {
                    $reservation->releaseTimeSlot();
                    $count++;
                }
            }
        }
        
        return $count;
    }
    
    /**
     * Obtenir les réservations par statut
     */
    public static function getReservationsByStatus($status, $limit = null)
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`
                WHERE status = ' . (int)$status . '
                ORDER BY date_reserved DESC';
                
        if ($limit) {
            $sql .= ' LIMIT ' . (int)$limit;
        }
        
        return Db::getInstance()->executeS($sql);
    }
    
    /**
     * Obtenir les statistiques des réservations
     */
    public static function getStats($date_from = null, $date_to = null)
    {
        $date_condition = '';
        if ($date_from && $date_to) {
            $date_condition = ' AND date_reserved BETWEEN "' . pSQL($date_from) . '" AND "' . pSQL($date_to) . '"';
        }
        
        $stats = [];
        $statuses = self::getStatuses();
        
        foreach ($statuses as $status_id => $status_label) {
            $count = Db::getInstance()->getValue('
                SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`
                WHERE status = ' . (int)$status_id . $date_condition
            );
            $stats[$status_id] = [
                'label' => $status_label,
                'count' => (int)$count
            ];
        }
        
        return $stats;
    }
    
    // Méthodes de notification (à implémenter selon les besoins)
    private function sendValidationNotification() {
        // À implémenter
    }
    
    private function sendCancellationNotification() {
        // À implémenter  
    }
    
    private function sendPaymentConfirmationNotification() {
        // À implémenter
    }
}