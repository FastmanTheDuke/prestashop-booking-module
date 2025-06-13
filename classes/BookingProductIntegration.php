<?php

/**
 * Classe pour gérer l'intégration entre les réservations et les produits PrestaShop
 */
class BookingProductIntegration
{
    /**
     * Créer un produit pour un booker s'il n'existe pas
     */
    public static function createProductForBooker($booker)
    {
        if (!Validate::isLoadedObject($booker)) {
            return false;
        }
        
        // Vérifier si un produit existe déjà pour ce booker
        $existing_product_id = self::getProductIdForBooker($booker->id);
        if ($existing_product_id) {
            return $existing_product_id;
        }
        
        // Créer un nouveau produit
        $product = new Product();
        $product->name = [];
        $product->description = [];
        $product->description_short = [];
        $product->link_rewrite = [];
        
        // Remplir pour chaque langue
        $languages = Language::getLanguages(false);
        foreach ($languages as $language) {
            $lang_id = $language['id_lang'];
            $product->name[$lang_id] = 'Réservation - ' . $booker->name;
            $product->description[$lang_id] = isset($booker->description[$lang_id]) 
                ? $booker->description[$lang_id] 
                : 'Réservation pour ' . $booker->name;
            $product->description_short[$lang_id] = 'Réservation pour ' . $booker->name;
            $product->link_rewrite[$lang_id] = Tools::link_rewrite('reservation-' . $booker->name);
        }
        
        // Configuration du produit
        $product->price = 50.00; // Prix de base - à adapter
        $product->id_category_default = self::getBookingCategoryId();
        $product->active = 1;
        $product->available_for_order = 1;
        $product->show_price = 1;
        $product->online_only = 0;
        $product->is_virtual = 1; // Produit virtuel car c'est une réservation
        $product->cache_default_attribute = 0;
        $product->minimal_quantity = 1;
        $product->quantity = 9999; // Stock illimité pour les réservations
        $product->out_of_stock = 1; // Autoriser les commandes hors stock
        $product->date_add = date('Y-m-d H:i:s');
        $product->date_upd = date('Y-m-d H:i:s');
        
        if ($product->add()) {
            // Associer le produit au booker
            self::linkProductToBooker($product->id, $booker->id);
            
            // Ajouter à la catégorie
            $product->addToCategories([$product->id_category_default]);
            
            // Créer les combinaisons pour les créneaux horaires si nécessaire
            self::createTimeSlotCombinations($product->id);
            
            return $product->id;
        }
        
        return false;
    }
    
    /**
     * Obtenir ou créer la catégorie pour les réservations
     */
    private static function getBookingCategoryId()
    {
        // Chercher une catégorie existante
        $category_id = Db::getInstance()->getValue('
            SELECT c.id_category 
            FROM `' . _DB_PREFIX_ . 'category_lang` cl
            JOIN `' . _DB_PREFIX_ . 'category` c ON (cl.id_category = c.id_category)
            WHERE cl.name = "Réservations" 
            AND cl.id_lang = ' . (int)Configuration::get('PS_LANG_DEFAULT') . '
            AND c.active = 1
        ');
        
        if ($category_id) {
            return (int)$category_id;
        }
        
        // Créer la catégorie
        $category = new Category();
        $category->name = [];
        $category->description = [];
        $category->link_rewrite = [];
        
        $languages = Language::getLanguages(false);
        foreach ($languages as $language) {
            $lang_id = $language['id_lang'];
            $category->name[$lang_id] = 'Réservations';
            $category->description[$lang_id] = 'Catégorie pour les produits de réservation';
            $category->link_rewrite[$lang_id] = 'reservations';
        }
        
        $category->id_parent = 2; // Catégorie racine
        $category->active = 1;
        $category->date_add = date('Y-m-d H:i:s');
        $category->date_upd = date('Y-m-d H:i:s');
        
        if ($category->add()) {
            return $category->id;
        }
        
        return 2; // Fallback sur la catégorie racine
    }
    
    /**
     * Lier un produit à un booker
     */
    private static function linkProductToBooker($product_id, $booker_id)
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'booker_product` (
            `id_booker` int(10) unsigned NOT NULL,
            `id_product` int(10) unsigned NOT NULL,
            `date_add` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id_booker`),
            UNIQUE KEY `unique_product` (`id_product`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8';
        
        Db::getInstance()->execute($sql);
        
        return Db::getInstance()->insert('booker_product', [
            'id_booker' => (int)$booker_id,
            'id_product' => (int)$product_id
        ]);
    }
    
    /**
     * Obtenir l'ID du produit pour un booker
     */
    public static function getProductIdForBooker($booker_id)
    {
        return Db::getInstance()->getValue('
            SELECT id_product 
            FROM `' . _DB_PREFIX_ . 'booker_product` 
            WHERE id_booker = ' . (int)$booker_id
        );
    }
    
    /**
     * Obtenir l'ID du booker pour un produit
     */
    public static function getBookerIdForProduct($product_id)
    {
        return Db::getInstance()->getValue('
            SELECT id_booker 
            FROM `' . _DB_PREFIX_ . 'booker_product` 
            WHERE id_product = ' . (int)$product_id
        );
    }
    
    /**
     * Créer des combinaisons pour les créneaux horaires
     */
    private static function createTimeSlotCombinations($product_id)
    {
        // Créer un attribut pour les créneaux horaires
        $attribute_group_id = self::createTimeSlotAttributeGroup();
        if (!$attribute_group_id) {
            return false;
        }
        
        // Créer des attributs pour différents créneaux
        $time_slots = [
            '08:00-12:00' => 'Matin (8h-12h)',
            '12:00-16:00' => 'Après-midi (12h-16h)',
            '16:00-20:00' => 'Soirée (16h-20h)',
            '08:00-20:00' => 'Journée complète (8h-20h)'
        ];
        
        foreach ($time_slots as $slot_key => $slot_name) {
            $attribute_id = self::createTimeSlotAttribute($attribute_group_id, $slot_key, $slot_name);
            if ($attribute_id) {
                // Créer la combinaison
                self::createProductCombination($product_id, $attribute_id, $slot_key);
            }
        }
        
        return true;
    }
    
    /**
     * Créer un groupe d'attributs pour les créneaux horaires
     */
    private static function createTimeSlotAttributeGroup()
    {
        // Vérifier s'il existe déjà
        $existing_id = Db::getInstance()->getValue('
            SELECT ag.id_attribute_group 
            FROM `' . _DB_PREFIX_ . 'attribute_group_lang` agl
            JOIN `' . _DB_PREFIX_ . 'attribute_group` ag ON (agl.id_attribute_group = ag.id_attribute_group)
            WHERE agl.name = "Créneaux horaires" 
            AND agl.id_lang = ' . (int)Configuration::get('PS_LANG_DEFAULT')
        );
        
        if ($existing_id) {
            return (int)$existing_id;
        }
        
        // Créer le groupe d'attributs
        $attribute_group = new AttributeGroup();
        $attribute_group->name = [];
        $attribute_group->public_name = [];
        
        $languages = Language::getLanguages(false);
        foreach ($languages as $language) {
            $lang_id = $language['id_lang'];
            $attribute_group->name[$lang_id] = 'Créneaux horaires';
            $attribute_group->public_name[$lang_id] = 'Créneau horaire';
        }
        
        $attribute_group->group_type = 'select';
        $attribute_group->is_color_group = 0;
        
        if ($attribute_group->add()) {
            return $attribute_group->id;
        }
        
        return false;
    }
    
    /**
     * Créer un attribut pour un créneau horaire
     */
    private static function createTimeSlotAttribute($attribute_group_id, $slot_key, $slot_name)
    {
        $attribute = new Attribute();
        $attribute->id_attribute_group = $attribute_group_id;
        $attribute->name = [];
        
        $languages = Language::getLanguages(false);
        foreach ($languages as $language) {
            $lang_id = $language['id_lang'];
            $attribute->name[$lang_id] = $slot_name;
        }
        
        if ($attribute->add()) {
            return $attribute->id;
        }
        
        return false;
    }
    
    /**
     * Créer une combinaison de produit
     */
    private static function createProductCombination($product_id, $attribute_id, $slot_key)
    {
        $combination = new Combination();
        $combination->id_product = $product_id;
        $combination->price = 0; // Prix de base, peut être modifié selon le créneau
        $combination->weight = 0;
        $combination->unit_price_impact = 0;
        $combination->minimal_quantity = 1;
        $combination->quantity = 9999;
        $combination->reference = 'RES-' . $product_id . '-' . str_replace(':', '', $slot_key);
        $combination->available_date = '0000-00-00';
        $combination->default_on = 0;
        
        if ($combination->add()) {
            // Associer l'attribut à la combinaison
            Db::getInstance()->insert('product_attribute_combination', [
                'id_attribute' => (int)$attribute_id,
                'id_product_attribute' => (int)$combination->id
            ]);
            
            return $combination->id;
        }
        
        return false;
    }
    
    /**
     * Créer une commande pour une réservation acceptée
     */
    public static function createOrderForReservation($reservation)
    {
        if (!Validate::isLoadedObject($reservation)) {
            return false;
        }
        
        // Obtenir le booker
        $booker = new Booker($reservation->id_booker);
        if (!Validate::isLoadedObject($booker)) {
            return false;
        }
        
        // Obtenir ou créer le produit pour ce booker
        $product_id = self::getProductIdForBooker($booker->id);
        if (!$product_id) {
            $product_id = self::createProductForBooker($booker);
        }
        
        if (!$product_id) {
            return false;
        }
        
        // Obtenir les informations client
        $customer_info = $reservation->getCustomerInfo();
        if (!$customer_info || !$customer_info['customer_email']) {
            return false;
        }
        
        // Créer ou obtenir le client
        $customer = self::getOrCreateCustomer($customer_info);
        if (!$customer) {
            return false;
        }
        
        // Créer le panier
        $cart = new Cart();
        $cart->id_customer = $customer->id;
        $cart->id_lang = Configuration::get('PS_LANG_DEFAULT');
        $cart->id_currency = Configuration::get('PS_CURRENCY_DEFAULT');
        $cart->id_carrier = Configuration::get('PS_CARRIER_DEFAULT');
        
        if (!$cart->add()) {
            return false;
        }
        
        // Ajouter le produit au panier
        $product = new Product($product_id);
        $price = $reservation->getPrice();
        
        // Créer un produit spécifique avec les détails de la réservation
        $cart_product_name = $product->name[Configuration::get('PS_LANG_DEFAULT')] . 
                           ' - ' . $reservation->date_reserved . 
                           ' (' . $reservation->hour_from . 'h-' . $reservation->hour_to . 'h)';
        
        if (!$cart->updateQty(1, $product_id, null, false, 'up', 0, null, false)) {
            return false;
        }
        
        // Créer la commande
        $order = new Order();
        $order->id_customer = $customer->id;
        $order->id_cart = $cart->id;
        $order->id_lang = Configuration::get('PS_LANG_DEFAULT');
        $order->id_currency = Configuration::get('PS_CURRENCY_DEFAULT');
        $order->id_carrier = Configuration::get('PS_CARRIER_DEFAULT');
        $order->payment = 'Réservation en attente';
        $order->module = 'booking';
        $order->total_paid = $price;
        $order->total_paid_tax_incl = $price;
        $order->total_paid_tax_excl = $price;
        $order->total_products = $price;
        $order->total_products_wt = $price;
        $order->current_state = Configuration::get('PS_OS_PREPARATION'); // En attente de paiement
        $order->reference = Order::generateReference();
        $order->date_add = date('Y-m-d H:i:s');
        $order->date_upd = date('Y-m-d H:i:s');
        
        if ($order->add()) {
            // Lier la commande à la réservation
            self::linkOrderToReservation($order->id, $reservation->id);
            
            // Ajouter les détails de commande
            self::addOrderDetails($order->id, $product_id, $cart_product_name, $price);
            
            return $order->id;
        }
        
        return false;
    }
    
    /**
     * Obtenir ou créer un client
     */
    private static function getOrCreateCustomer($customer_info)
    {
        // Chercher un client existant
        $customer_id = Customer::customerExists($customer_info['customer_email'], true);
        
        if ($customer_id) {
            return new Customer($customer_id);
        }
        
        // Créer un nouveau client
        $customer = new Customer();
        $customer->email = $customer_info['customer_email'];
        $customer->firstname = $customer_info['customer_name'] ?: 'Client';
        $customer->lastname = 'Réservation';
        $customer->passwd = Tools::passwdGen();
        $customer->id_default_group = Configuration::get('PS_CUSTOMER_GROUP');
        $customer->newsletter = 0;
        $customer->optin = 0;
        $customer->active = 1;
        $customer->date_add = date('Y-m-d H:i:s');
        $customer->date_upd = date('Y-m-d H:i:s');
        
        if ($customer->add()) {
            return $customer;
        }
        
        return null;
    }
    
    /**
     * Lier une commande à une réservation
     */
    private static function linkOrderToReservation($order_id, $reservation_id)
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'booker_reservation_order` (
            `id_reservation` int(10) unsigned NOT NULL,
            `id_order` int(10) unsigned NOT NULL,
            `date_add` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id_reservation`),
            UNIQUE KEY `unique_order` (`id_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8';
        
        Db::getInstance()->execute($sql);
        
        return Db::getInstance()->insert('booker_reservation_order', [
            'id_reservation' => (int)$reservation_id,
            'id_order' => (int)$order_id
        ]);
    }
    
    /**
     * Ajouter les détails de commande
     */
    private static function addOrderDetails($order_id, $product_id, $product_name, $price)
    {
        $order_detail = new OrderDetail();
        $order_detail->id_order = $order_id;
        $order_detail->product_id = $product_id;
        $order_detail->product_name = $product_name;
        $order_detail->product_quantity = 1;
        $order_detail->product_price = $price;
        $order_detail->unit_price_tax_incl = $price;
        $order_detail->unit_price_tax_excl = $price;
        $order_detail->total_price_tax_incl = $price;
        $order_detail->total_price_tax_excl = $price;
        
        return $order_detail->add();
    }
    
    /**
     * Obtenir la commande liée à une réservation
     */
    public static function getOrderForReservation($reservation_id)
    {
        $order_id = Db::getInstance()->getValue('
            SELECT id_order 
            FROM `' . _DB_PREFIX_ . 'booker_reservation_order` 
            WHERE id_reservation = ' . (int)$reservation_id
        );
        
        return $order_id ? new Order($order_id) : null;
    }
    
    /**
     * Synchroniser tous les bookers avec les produits
     */
    public static function syncAllBookers()
    {
        $bookers = Booker::getActiveBookers();
        $synced = 0;
        
        foreach ($bookers as $booker_data) {
            $booker = new Booker($booker_data['id_booker']);
            if (self::createProductForBooker($booker)) {
                $synced++;
            }
        }
        
        return $synced;
    }
}