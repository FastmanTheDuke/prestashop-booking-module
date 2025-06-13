<?php
/**
 * Classe Booker - Éléments réservables avec intégration produits PrestaShop
 * Version 2.0 avec liaison produits et gestion avancée
 */

class Booker extends ObjectModel
{
    /** @var int */
    public $id_booker;
    
    /** @var int Lien vers un produit PrestaShop */
    public $id_product = 0;
    
    /** @var string */
    public $name;
    
    /** @var string */
    public $description;
    
    /** @var string */
    public $location;
    
    /** @var int */
    public $capacity = 1;
    
    /** @var float */
    public $price = 50.00;
    
    /** @var bool */
    public $deposit_required = true;
    
    /** @var float */
    public $deposit_amount = 0.00;
    
    /** @var bool */
    public $auto_confirm = false;
    
    /** @var int Durée par défaut en minutes */
    public $booking_duration = 60;
    
    /** @var int Délai minimum de réservation en heures */
    public $min_booking_time = 24;
    
    /** @var int Nombre de jours maximum à l'avance */
    public $max_booking_days = 30;
    
    /** @var bool */
    public $active = true;
    
    /** @var string */
    public $date_add;
    
    /** @var string */
    public $date_upd;
    
    /**
     * @see ObjectModel::$definition
     */
    public static $definition = array(
        'table' => 'booker',
        'primary' => 'id_booker',
        'fields' => array(
            'id_product' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
            'name' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 255),
            'description' => array('type' => self::TYPE_HTML, 'validate' => 'isCleanHtml'),
            'location' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 255),
            'capacity' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'price' => array('type' => self::TYPE_FLOAT, 'validate' => 'isPrice'),
            'deposit_required' => array('type' => self::TYPE_BOOL, 'validate' => 'isBool'),
            'deposit_amount' => array('type' => self::TYPE_FLOAT, 'validate' => 'isPrice'),
            'auto_confirm' => array('type' => self::TYPE_BOOL, 'validate' => 'isBool'),
            'booking_duration' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'min_booking_time' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'max_booking_days' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'active' => array('type' => self::TYPE_BOOL, 'validate' => 'isBool'),
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
            'date_upd' => array('type' => self::TYPE_DATE, 'validate' => 'isDate')
        ),
    );
    
    public function __construct($id = null, $id_lang = null, $id_shop = null)
    {
        parent::__construct($id, $id_lang, $id_shop);
        
        // Synchroniser avec le produit PrestaShop si lié
        if ($this->id_product && Validate::isLoadedObject($this)) {
            $this->syncWithProduct();
        }
    }
    
    /**
     * Synchroniser les données avec le produit PrestaShop associé
     */
    public function syncWithProduct()
    {
        if (!$this->id_product) {
            return false;
        }
        
        $product = new Product($this->id_product, false, Context::getContext()->language->id);
        if (!Validate::isLoadedObject($product)) {
            return false;
        }
        
        // Synchroniser le nom si pas défini
        if (empty($this->name)) {
            $this->name = $product->name;
        }
        
        // Synchroniser la description si pas définie
        if (empty($this->description)) {
            $this->description = $product->description_short ?: $product->description;
        }
        
        // Synchroniser le prix si pas défini ou si configuré pour sync auto
        if ($this->price <= 0 || Configuration::get('BOOKING_SYNC_PRODUCT_PRICE')) {
            $this->price = $product->price;
        }
        
        // Synchroniser le statut actif
        $this->active = (bool)$product->active;
        
        return true;
    }
    
    /**
     * Obtenir le produit PrestaShop associé
     */
    public function getProduct()
    {
        if (!$this->id_product) {
            return null;
        }
        
        $product = new Product($this->id_product, false, Context::getContext()->language->id);
        return Validate::isLoadedObject($product) ? $product : null;
    }
    
    /**
     * Lier à un produit PrestaShop
     */
    public function linkToProduct($id_product)
    {
        $product = new Product($id_product);
        if (!Validate::isLoadedObject($product)) {
            return false;
        }
        
        $this->id_product = $id_product;
        $this->syncWithProduct();
        
        return $this->save();
    }
    
    /**
     * Délier du produit PrestaShop
     */
    public function unlinkFromProduct()
    {
        $this->id_product = 0;
        return $this->save();
    }
    
    /**
     * Obtenir les disponibilités futures
     */
    public function getAvailabilities($limit_days = 30)
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'booker_auth`
                WHERE id_booker = ' . (int)$this->id . '
                AND is_available = 1
                AND date_start >= NOW()
                AND date_start <= DATE_ADD(NOW(), INTERVAL ' . (int)$limit_days . ' DAY)
                ORDER BY date_start ASC';
                
        return Db::getInstance()->executeS($sql);
    }
    
    /**
     * Obtenir les réservations futures
     */
    public function getReservations($status = null, $limit_days = 30)
    {
        $sql = 'SELECT r.*, c.firstname, c.lastname 
                FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` r
                LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON r.id_customer = c.id_customer
                WHERE r.id_booker = ' . (int)$this->id . '
                AND r.date_start >= NOW()
                AND r.date_start <= DATE_ADD(NOW(), INTERVAL ' . (int)$limit_days . ' DAY)';
                
        if ($status !== null) {
            $sql .= ' AND r.status = ' . (int)$status;
        }
        
        $sql .= ' ORDER BY r.date_start ASC';
        
        return Db::getInstance()->executeS($sql);
    }
    
    /**
     * Vérifier si une période est disponible
     */
    public function isAvailable($date_start, $date_end)
    {
        // Vérifier les contraintes de réservation
        if (!$this->checkBookingConstraints($date_start, $date_end)) {
            return false;
        }
        
        // Vérifier qu'il existe une disponibilité pour cette période
        $sql = 'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth`
                WHERE id_booker = ' . (int)$this->id . '
                AND is_available = 1
                AND date_start <= "' . pSQL($date_start) . '"
                AND date_end >= "' . pSQL($date_end) . '"
                AND (max_bookings - current_bookings) > 0';
                
        return (bool)Db::getInstance()->getValue($sql);
    }
    
    /**
     * Vérifier les contraintes de réservation
     */
    private function checkBookingConstraints($date_start, $date_end)
    {
        $now = time();
        $start_timestamp = strtotime($date_start);
        $end_timestamp = strtotime($date_end);
        
        // Vérifier que les dates sont cohérentes
        if ($start_timestamp >= $end_timestamp) {
            return false;
        }
        
        // Vérifier le délai minimum
        $min_booking_seconds = $this->min_booking_time * 3600;
        if ($start_timestamp < ($now + $min_booking_seconds)) {
            return false;
        }
        
        // Vérifier le délai maximum
        $max_booking_seconds = $this->max_booking_days * 24 * 3600;
        if ($start_timestamp > ($now + $max_booking_seconds)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Calculer le prix pour une période donnée
     */
    public function calculatePrice($date_start, $date_end, $include_deposit = false)
    {
        $start_time = strtotime($date_start);
        $end_time = strtotime($date_end);
        $duration_hours = ($end_time - $start_time) / 3600;
        
        // Prix de base selon la durée
        $base_price = $this->price;
        
        // Vérifier s'il y a un prix spécial pour cette période
        $sql = 'SELECT price_override FROM `' . _DB_PREFIX_ . 'booker_auth`
                WHERE id_booker = ' . (int)$this->id . '
                AND date_start <= "' . pSQL($date_start) . '"
                AND date_end >= "' . pSQL($date_end) . '"
                AND price_override IS NOT NULL
                LIMIT 1';
                
        $special_price = Db::getInstance()->getValue($sql);
        if ($special_price) {
            $base_price = (float)$special_price;
        }
        
        // Calculer selon la durée si différente de la durée standard
        if ($duration_hours != ($this->booking_duration / 60)) {
            $hourly_rate = $base_price / ($this->booking_duration / 60);
            $total_price = $hourly_rate * $duration_hours;
        } else {
            $total_price = $base_price;
        }
        
        // Ajouter la caution si demandée
        if ($include_deposit && $this->deposit_required) {
            $deposit = $this->deposit_amount ?: ($total_price * 0.3); // 30% par défaut
            $total_price += $deposit;
        }
        
        return round($total_price, 2);
    }
    
    /**
     * Effectuer une réservation
     */
    public function makeReservation($data)
    {
        // Validation des données
        $required_fields = ['date_start', 'date_end', 'customer_email'];
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception('Champ requis manquant: ' . $field);
            }
        }
        
        // Vérifier la disponibilité
        if (!$this->isAvailable($data['date_start'], $data['date_end'])) {
            throw new Exception('Créneau non disponible');
        }
        
        // Trouver le créneau de disponibilité correspondant
        $sql = 'SELECT id_auth FROM `' . _DB_PREFIX_ . 'booker_auth`
                WHERE id_booker = ' . (int)$this->id . '
                AND is_available = 1
                AND date_start <= "' . pSQL($data['date_start']) . '"
                AND date_end >= "' . pSQL($data['date_end']) . '"
                AND (max_bookings - current_bookings) > 0
                LIMIT 1';
                
        $id_auth = Db::getInstance()->getValue($sql);
        if (!$id_auth) {
            throw new Exception('Aucun créneau de disponibilité trouvé');
        }
        
        // Calculer le prix
        $total_price = $this->calculatePrice($data['date_start'], $data['date_end']);
        $deposit_amount = 0;
        if ($this->deposit_required) {
            $deposit_amount = $this->deposit_amount ?: ($total_price * 0.3);
        }
        
        // Créer la réservation
        $reservation = new BookerAuthReserved();
        $reservation->id_auth = $id_auth;
        $reservation->id_booker = $this->id;
        $reservation->customer_firstname = $data['customer_firstname'] ?? '';
        $reservation->customer_lastname = $data['customer_lastname'] ?? '';
        $reservation->customer_email = $data['customer_email'];
        $reservation->customer_phone = $data['customer_phone'] ?? '';
        $reservation->date_start = $data['date_start'];
        $reservation->date_end = $data['date_end'];
        $reservation->total_price = $total_price;
        $reservation->notes = $data['notes'] ?? '';
        $reservation->date_reserved = date('Y-m-d H:i:s');
        $reservation->date_add = date('Y-m-d H:i:s');
        $reservation->date_upd = date('Y-m-d H:i:s');
        
        // Définir la date d'expiration
        $expiry_hours = Configuration::get('BOOKING_EXPIRY_HOURS') ?: 24;
        $reservation->date_expiry = date('Y-m-d H:i:s', strtotime('+' . $expiry_hours . ' hours'));
        
        // Statut initial selon configuration
        $reservation->status = $this->auto_confirm ? BookerAuthReserved::STATUS_VALIDATED : BookerAuthReserved::STATUS_PENDING;
        
        if ($reservation->save()) {
            // Mettre à jour le compteur de réservations
            $this->updateBookingCount($id_auth, 1);
            
            return $reservation;
        } else {
            throw new Exception('Erreur lors de la création de la réservation');
        }
    }
    
    /**
     * Mettre à jour le compteur de réservations d'un créneau
     */
    private function updateBookingCount($id_auth, $increment = 1)
    {
        $sql = 'UPDATE `' . _DB_PREFIX_ . 'booker_auth` 
                SET current_bookings = current_bookings + ' . (int)$increment . '
                WHERE id_auth = ' . (int)$id_auth;
                
        return Db::getInstance()->execute($sql);
    }
    
    /**
     * Obtenir les statistiques de ce booker
     */
    public function getStats($period_days = 30)
    {
        $stats = array();
        
        // Nombre de disponibilités créées
        $stats['availabilities'] = (int)Db::getInstance()->getValue('
            SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth`
            WHERE id_booker = ' . (int)$this->id
        );
        
        // Nombre de réservations par statut
        $stats['reservations'] = array();
        $statuses = BookerAuthReserved::getStatuses();
        foreach ($statuses as $status_id => $status_label) {
            $stats['reservations'][$status_id] = (int)Db::getInstance()->getValue('
                SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`
                WHERE id_booker = ' . (int)$this->id . '
                AND status = ' . (int)$status_id
            );
        }
        
        // Chiffre d'affaires des réservations payées
        $stats['revenue'] = (float)Db::getInstance()->getValue('
            SELECT SUM(total_price) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`
            WHERE id_booker = ' . (int)$this->id . '
            AND status = ' . BookerAuthReserved::STATUS_PAID
        );
        
        // Taux d'occupation (période récente)
        $occupation_sql = '
            SELECT 
                COUNT(ba.id_auth) as total_slots,
                SUM(ba.current_bookings) as booked_slots
            FROM `' . _DB_PREFIX_ . 'booker_auth` ba
            WHERE ba.id_booker = ' . (int)$this->id . '
            AND ba.date_start >= DATE_SUB(NOW(), INTERVAL ' . (int)$period_days . ' DAY)
            AND ba.date_end <= NOW()
        ';
        
        $occupation_data = Db::getInstance()->getRow($occupation_sql);
        $stats['occupation_rate'] = 0;
        if ($occupation_data['total_slots'] > 0) {
            $stats['occupation_rate'] = round(($occupation_data['booked_slots'] / $occupation_data['total_slots']) * 100, 2);
        }
        
        return $stats;
    }
    
    /**
     * Obtenir tous les bookers actifs
     */
    public static function getActiveBookers()
    {
        $sql = 'SELECT b.*, p.reference as product_reference, pl.name as product_name 
                FROM `' . _DB_PREFIX_ . 'booker` b
                LEFT JOIN `' . _DB_PREFIX_ . 'product` p ON b.id_product = p.id_product
                LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON (p.id_product = pl.id_product AND pl.id_lang = ' . (int)Context::getContext()->language->id . ')
                WHERE b.active = 1
                ORDER BY b.name ASC';
                
        return Db::getInstance()->executeS($sql);
    }
    
    /**
     * Obtenir les bookers par produit
     */
    public static function getBookersByProduct($id_product)
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'booker`
                WHERE id_product = ' . (int)$id_product . '
                AND active = 1
                ORDER BY name ASC';
                
        return Db::getInstance()->executeS($sql);
    }
    
    /**
     * Créer un booker à partir d'un produit
     */
    public static function createFromProduct($id_product, $additional_data = array())
    {
        $product = new Product($id_product, false, Context::getContext()->language->id);
        if (!Validate::isLoadedObject($product)) {
            return false;
        }
        
        $booker = new self();
        $booker->id_product = $id_product;
        $booker->name = $product->name;
        $booker->description = $product->description_short ?: $product->description;
        $booker->price = $product->price;
        $booker->active = (bool)$product->active;
        $booker->date_add = date('Y-m-d H:i:s');
        $booker->date_upd = date('Y-m-d H:i:s');
        
        // Appliquer les données supplémentaires
        foreach ($additional_data as $key => $value) {
            if (property_exists($booker, $key)) {
                $booker->$key = $value;
            }
        }
        
        return $booker->save() ? $booker : false;
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
        
        return parent::save($null_values, $auto_date);
    }
}