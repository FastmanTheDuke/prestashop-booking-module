<?php
/**
 * Classe BookingProductIntegration - Gestion de l'intégration avec les produits PrestaShop
 */

class BookingProductIntegration
{
    /**
     * Créer un produit PrestaShop pour un booker
     */
    public static function createProductForBooker($booker)
    {
        $product = new Product();
        
        // Informations de base
        $product->name = array();
        $product->link_rewrite = array();
        $product->description = array();
        $product->description_short = array();
        
        $languages = Language::getLanguages(false);
        foreach ($languages as $lang) {
            $product->name[$lang['id_lang']] = $booker->name;
            $product->link_rewrite[$lang['id_lang']] = Tools::link_rewrite($booker->name);
            $product->description[$lang['id_lang']] = $booker->description ?: '';
            $product->description_short[$lang['id_lang']] = Tools::substr($booker->description, 0, 800);
        }
        
        // Prix et taxes
        $product->price = $booker->price;
        $product->id_tax_rules_group = Configuration::get('BOOKING_DEFAULT_TAX_RULES_GROUP', 1);
        
        // Catégorie
        $id_category = Configuration::get('BOOKING_DEFAULT_CATEGORY');
        if (!$id_category) {
            $id_category = Configuration::get('PS_HOME_CATEGORY');
        }
        $product->id_category_default = $id_category;
        $product->category = array($id_category);
        
        // Autres paramètres
        $product->active = $booker->active;
        $product->available_for_order = true;
        $product->show_price = true;
        $product->visibility = 'both';
        $product->reference = 'BOOK-' . $booker->id;
        $product->minimal_quantity = 1;
        $product->quantity = 999999; // Stock illimité pour les réservations
        $product->out_of_stock = 1; // Permettre les commandes
        
        // Type de produit virtuel (pas de livraison)
        $product->is_virtual = 1;
        $product->weight = 0;
        
        // Sauvegarder le produit
        if ($product->add()) {
            // Associer aux catégories
            $product->updateCategories(array($id_category));
            
            // Ajouter au stock
            StockAvailable::setQuantity($product->id, 0, 999999);
            
            // Enregistrer l'association
            self::saveBookerProductLink($booker->id, $product->id);
            
            return $product->id;
        }
        
        return false;
    }
    
    /**
     * Synchroniser un booker avec son produit
     */
    public static function syncBookerWithProduct($booker)
    {
        if (!$booker->id_product) {
            return false;
        }
        
        $product = new Product($booker->id_product);
        if (!Validate::isLoadedObject($product)) {
            return false;
        }
        
        // Synchroniser les informations
        $languages = Language::getLanguages(false);
        foreach ($languages as $lang) {
            $product->name[$lang['id_lang']] = $booker->name;
            $product->description_short[$lang['id_lang']] = Tools::substr($booker->description, 0, 800);
            if (!empty($booker->description)) {
                $product->description[$lang['id_lang']] = $booker->description;
            }
        }
        
        // Synchroniser le prix si configuré
        if (Configuration::get('BOOKING_SYNC_PRODUCT_PRICE')) {
            $product->price = $booker->price;
        }
        
        $product->active = $booker->active;
        $product->reference = 'BOOK-' . $booker->id;
        
        return $product->update();
    }
    
    /**
     * Synchroniser tous les bookers avec leurs produits
     */
    public static function syncAllBookers()
    {
        $bookers = Db::getInstance()->executeS('
            SELECT * FROM `' . _DB_PREFIX_ . 'booker`
            WHERE active = 1
        ');
        
        $synced = 0;
        foreach ($bookers as $booker_data) {
            $booker = new Booker($booker_data['id_booker']);
            
            if (!$booker->id_product) {
                // Créer un produit
                $product_id = self::createProductForBooker($booker);
                if ($product_id) {
                    $booker->id_product = $product_id;
                    $booker->save();
                    $synced++;
                }
            } else {
                // Synchroniser avec le produit existant
                if (self::syncBookerWithProduct($booker)) {
                    $synced++;
                }
            }
        }
        
        return $synced;
    }
    
    /**
     * Enregistrer le lien entre un booker et un produit
     */
    private static function saveBookerProductLink($id_booker, $id_product)
    {
        return Db::getInstance()->insert('booker_product', array(
            'id_booker' => (int)$id_booker,
            'id_product' => (int)$id_product,
            'sync_price' => 1,
            'date_add' => date('Y-m-d H:i:s')
        ), false, true, Db::INSERT_IGNORE);
    }
    
    /**
     * Hook pour mettre à jour le booker quand le produit est modifié
     */
    public static function hookActionProductUpdate($params)
    {
        if (!isset($params['product']) || !$params['product']->id) {
            return;
        }
        
        $product = $params['product'];
        
        // Vérifier si ce produit est lié à un booker
        $id_booker = Db::getInstance()->getValue('
            SELECT b.id_booker 
            FROM `' . _DB_PREFIX_ . 'booker` b
            WHERE b.id_product = ' . (int)$product->id
        );
        
        if ($id_booker) {
            $booker = new Booker($id_booker);
            if (Validate::isLoadedObject($booker)) {
                // Synchroniser les données si configuré
                if (Configuration::get('BOOKING_SYNC_FROM_PRODUCT')) {
                    $booker->name = $product->name[Context::getContext()->language->id];
                    $booker->price = $product->price;
                    $booker->active = $product->active;
                    $booker->save();
                }
            }
        }
    }
    
    /**
     * Créer une catégorie dédiée aux réservations
     */
    public static function createBookingCategory()
    {
        $category = new Category();
        
        $languages = Language::getLanguages(false);
        foreach ($languages as $lang) {
            $category->name[$lang['id_lang']] = 'Réservations';
            $category->link_rewrite[$lang['id_lang']] = 'reservations';
            $category->description[$lang['id_lang']] = 'Produits de réservation';
        }
        
        $category->id_parent = Configuration::get('PS_HOME_CATEGORY');
        $category->active = true;
        
        if ($category->add()) {
            Configuration::updateValue('BOOKING_DEFAULT_CATEGORY', $category->id);
            return $category->id;
        }
        
        return false;
    }
}