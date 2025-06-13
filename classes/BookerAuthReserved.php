<?php
/**
 * Classe BookerAuthReserved - Gestion des réservations avec système de statuts avancé
 */

class BookerAuthReserved extends ObjectModel
{
    // Statuts de réservation
    const STATUS_PENDING = 0;          // En attente de validation
    const STATUS_ACCEPTED = 1;         // Acceptée
    const STATUS_CANCELLED = 2;        // Annulée
    const STATUS_PAID = 3;             // Payée
    const STATUS_EXPIRED = 4;          // Expirée
    const STATUS_PENDING_PAYMENT = 5;  // En attente de paiement
    const STATUS_COMPLETED = 6;        // Terminée
    const STATUS_REFUNDED = 7;         // Remboursée
    
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
    public $date_reserved;
    
    /** @var string */
    public $date_to;
    
    /** @var int */
    public $hour_from;
    
    /** @var int */
    public $hour_to;
    
    /** @var float */
    public $total_price;
    
    /** @var float */
    public $deposit_paid = 0.00;
    
    /** @var int */
    public $status = self::STATUS_PENDING;
    
    /** @var string */
    public $payment_status = 'pending';
    
    /** @var string */
    public $stripe_payment_intent_id;
    
    /** @var string */
    public $stripe_deposit_intent_id;
    
    /** @var string */
    public $notes;
    
    /** @var string */
    public $admin_notes;
    
    /** @var string */
    public $date_expiry;
    
    /** @var string */
    public $date_add;
    
    /** @var string */
    public $date_upd;
    
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
            'booking_reference' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 50, 'required' => true),
            'customer_firstname' => array('type' => self::TYPE_STRING, 'validate' => 'isName', 'size' => 100, 'required' => true),
            'customer_lastname' => array('type' => self::TYPE_STRING, 'validate' => 'isName', 'size' => 100, 'required' => true),
            'customer_email' => array('type' => self::TYPE_STRING, 'validate' => 'isEmail', 'size' => 150, 'required' => true),
            'customer_phone' => array('type' => self::TYPE_STRING, 'validate' => 'isPhoneNumber', 'size' => 50),
            'date_reserved' => array('type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true),
            'date_to' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
            'hour_from' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true),
            'hour_to' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true),
            'total_price' => array('type' => self::TYPE_FLOAT, 'validate' => 'isPrice'),
            'deposit_paid' => array('type' => self::TYPE_FLOAT, 'validate' => 'isPrice'),
            'status' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'payment_status' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 20),
            'stripe_payment_intent_id' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 255),
            'stripe_deposit_intent_id' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 255),
            'notes' => array('type' => self::TYPE_HTML, 'validate' => 'isCleanHtml'),
            'admin_notes' => array('type' => self::TYPE_HTML, 'validate' => 'isCleanHtml'),
            'date_expiry' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
            'date_upd' => array('type' => self::TYPE_DATE, 'validate' => 'isDate')
        ),
    );
    
    /**
     * Obtenir les statuts disponibles
     */
    public static function getStatuses()
    {
        return array(
            self::STATUS_PENDING => 'En attente',
            self::STATUS_ACCEPTED => 'Acceptée',
            self::STATUS_CANCELLED => 'Annulée',
            self::STATUS_PAID => 'Payée',
            self::STATUS_EXPIRED => 'Expirée',
            self::STATUS_PENDING_PAYMENT => 'En attente de paiement',
            self::STATUS_COMPLETED => 'Terminée',
            self::STATUS_REFUNDED => 'Remboursée'
        );
    }
    
    /**
     * Générer une référence unique de réservation
     */
    public static function generateReference()
    {
        do {
            $reference = 'RES-' . date('Ymd') . '-' . strtoupper(Tools::passwdGen(6, 'ALPHANUMERIC'));
            $exists = Db::getInstance()->getValue('
                SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`
                WHERE `booking_reference` = "' . pSQL($reference) . '"'
            );
        } while ($exists);
        
        return $reference;
    }
    
    /**
     * Changer le statut d'une réservation
     */
    public function changeStatus($new_status)
    {
        // Vérifier si le changement de statut est valide
        if (!$this->isStatusChangeValid($this->status, $new_status)) {
            return false;
        }
        
        $this->status = $new_status;
        $this->date_upd = date('Y-m-d H:i:s');
        
        // Actions spécifiques selon le statut
        switch ($new_status) {
            case self::STATUS_ACCEPTED:
                // Vérifier les conflits
                if ($this->hasConflict()) {
                    return false;
                }
                break;
                
            case self::STATUS_CANCELLED:
            case self::STATUS_EXPIRED:
                // Libérer le créneau
                $this->freeSlot();
                break;
                
            case self::STATUS_PENDING_PAYMENT:
                // Créer une commande PrestaShop
                if (!$this->id_order) {
                    $this->createPendingOrder();
                }
                break;
        }
        
        return $this->save();
    }
    
    /**
     * Vérifier si un changement de statut est valide
     */
    private function isStatusChangeValid($old_status, $new_status)
    {
        // Définir les transitions valides
        $valid_transitions = array(
            self::STATUS_PENDING => array(
                self::STATUS_ACCEPTED,
                self::STATUS_CANCELLED,
                self::STATUS_EXPIRED
            ),
            self::STATUS_ACCEPTED => array(
                self::STATUS_PENDING_PAYMENT,
                self::STATUS_PAID,
                self::STATUS_CANCELLED
            ),
            self::STATUS_PENDING_PAYMENT => array(
                self::STATUS_PAID,
                self::STATUS_CANCELLED,
                self::STATUS_EXPIRED
            ),
            self::STATUS_PAID => array(
                self::STATUS_COMPLETED,
                self::STATUS_REFUNDED
            ),
            self::STATUS_CANCELLED => array(),
            self::STATUS_EXPIRED => array(),
            self::STATUS_COMPLETED => array(
                self::STATUS_REFUNDED
            ),
            self::STATUS_REFUNDED => array()
        );
        
        return isset($valid_transitions[$old_status]) && 
               in_array($new_status, $valid_transitions[$old_status]);
    }
    
    /**
     * Vérifier s'il y a un conflit avec d'autres réservations
     */
    public function hasConflict()
    {
        $sql = 'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`
                WHERE `id_booker` = ' . (int)$this->id_booker . '
                AND `id_reserved` != ' . (int)$this->id . '
                AND `status` IN (' . self::STATUS_ACCEPTED . ', ' . self::STATUS_PAID . ', ' . self::STATUS_PENDING_PAYMENT . ')
                AND (
                    (`date_reserved` <= "' . pSQL($this->date_reserved) . '" AND `date_to` >= "' . pSQL($this->date_reserved) . '")
                    OR (`date_reserved` <= "' . pSQL($this->date_to) . '" AND `date_to` >= "' . pSQL($this->date_to) . '")
                    OR (`date_reserved` >= "' . pSQL($this->date_reserved) . '" AND `date_to` <= "' . pSQL($this->date_to) . '")
                )
                AND (
                    (`hour_from` < ' . (int)$this->hour_to . ' AND `hour_to` > ' . (int)$this->hour_from . ')
                )';
        
        return (bool)Db::getInstance()->getValue($sql);
    }
    
    /**
     * Libérer le créneau réservé
     */
    private function freeSlot()
    {
        if ($this->id_auth) {
            $sql = 'UPDATE `' . _DB_PREFIX_ . 'booker_auth`
                    SET `current_bookings` = GREATEST(0, `current_bookings` - 1)
                    WHERE `id_auth` = ' . (int)$this->id_auth;
            
            return Db::getInstance()->execute($sql);
        }
        return true;
    }
    
    /**
     * Créer une commande PrestaShop en attente de paiement
     */
    public function createPendingOrder()
    {
        // Vérifier qu'une commande n'existe pas déjà
        if ($this->id_order) {
            return $this->id_order;
        }
        
        // Récupérer ou créer le client
        $id_customer = $this->getOrCreateCustomer();
        if (!$id_customer) {
            return false;
        }
        
        // Récupérer le booker et son produit associé
        $booker = new Booker($this->id_booker);
        if (!Validate::isLoadedObject($booker) || !$booker->id_product) {
            return false;
        }
        
        // Créer le panier
        $cart = new Cart();
        $cart->id_customer = $id_customer;
        $cart->id_currency = Configuration::get('PS_CURRENCY_DEFAULT');
        $cart->id_lang = Configuration::get('PS_LANG_DEFAULT');
        $cart->id_address_delivery = $this->getCustomerAddress($id_customer);
        $cart->id_address_invoice = $cart->id_address_delivery;
        $cart->add();
        
        // Ajouter le produit au panier
        $cart->updateQty(1, $booker->id_product);
        
        // Créer la commande
        $payment_module = Module::getInstanceByName('booking');
        $order_status = Configuration::get('BOOKING_STATUS_PENDING_PAYMENT', 
                                         Configuration::get('PS_OS_BANKWIRE'));
        
        $cart->getDeliveryOptionList();
        $payment_module->validateOrder(
            $cart->id,
            $order_status,
            $this->total_price,
            'Réservation - ' . $this->booking_reference,
            'Réservation créée depuis le back-office',
            array(),
            null,
            false,
            $cart->secure_key
        );
        
        $this->id_order = $payment_module->currentOrder;
        $this->id_customer = $id_customer;
        $this->status = self::STATUS_PENDING_PAYMENT;
        $this->save();
        
        return $this->id_order;
    }
    
    /**
     * Récupérer ou créer un client PrestaShop
     */
    private function getOrCreateCustomer()
    {
        // Si déjà lié à un client
        if ($this->id_customer) {
            return $this->id_customer;
        }
        
        // Chercher un client existant par email
        $id_customer = Customer::customerExists($this->customer_email, true);
        if ($id_customer) {
            return $id_customer;
        }
        
        // Créer un nouveau client
        $customer = new Customer();
        $customer->firstname = $this->customer_firstname;
        $customer->lastname = $this->customer_lastname;
        $customer->email = $this->customer_email;
        $customer->passwd = Tools::encrypt(Tools::passwdGen(8));
        $customer->newsletter = false;
        $customer->optin = false;
        $customer->active = true;
        
        if ($customer->add()) {
            return $customer->id;
        }
        
        return false;
    }
    
    /**
     * Obtenir l'adresse du client (créer si nécessaire)
     */
    private function getCustomerAddress($id_customer)
    {
        $addresses = Customer::getAddressesTotalById($id_customer);
        if ($addresses > 0) {
            $address = Address::getFirstCustomerAddressId($id_customer);
            return $address;
        }
        
        // Créer une adresse par défaut
        $address = new Address();
        $address->id_customer = $id_customer;
        $address->alias = 'Réservation';
        $address->firstname = $this->customer_firstname;
        $address->lastname = $this->customer_lastname;
        $address->address1 = 'Adresse de réservation';
        $address->city = 'Non spécifiée';
        $address->id_country = Configuration::get('PS_COUNTRY_DEFAULT');
        $address->phone = $this->customer_phone ?: '0000000000';
        
        if ($address->add()) {
            return $address->id;
        }
        
        return false;
    }
    
    /**
     * Annuler les réservations expirées
     */
    public static function cancelExpiredReservations()
    {
        $sql = 'UPDATE `' . _DB_PREFIX_ . 'booker_auth_reserved`
                SET `status` = ' . self::STATUS_EXPIRED . ',
                    `date_upd` = "' . date('Y-m-d H:i:s') . '"
                WHERE `status` = ' . self::STATUS_PENDING . '
                AND `date_expiry` IS NOT NULL
                AND `date_expiry` < NOW()';
        
        return Db::getInstance()->execute($sql);
    }
    
    /**
     * Sauvegarder avec mise à jour des timestamps
     */
    public function save($null_values = false, $auto_date = true)
    {
        if ($auto_date) {
            if (!$this->id) {
                $this->date_add = date('Y-m-d H:i:s');
            }
            $this->date_upd = date('Y-m-d H:i:s');
        }
        
        // Générer une référence si nécessaire
        if (empty($this->booking_reference)) {
            $this->booking_reference = self::generateReference();
        }
        
        return parent::save($null_values, $auto_date);
    }
}