<?php
require_once (dirname(__FILE__). '/../../classes/Booker.php');
require_once (dirname(__FILE__). '/../../classes/BookingProductIntegration.php');

class AdminBookerController extends ModuleAdminController
{
    protected $_module = NULL;
    public $controller_type='admin';   
    protected $position_identifier = 'id';

    public function __construct()
    {
        $this->display = 'options';
        $this->context = Context::getContext();
        $this->bootstrap = true;
        $this->table = 'booker';
        $this->identifier = 'id';
        $this->className = 'Booker';
        $this->_defaultOrderBy = 'id';
        $this->_defaultOrderWay = 'DESC';
        $this->lang = false; // Désactiver le multilangue car table ps_booker_lang n'existe pas
        $this->allow_export = true;
		
        $this->fields_list = array(
            'id' => array(
                'title' => 'ID', 
                'filter_key' => 'a!id', 
                'align' => 'center', 
                'width' => 25,
                'class' => 'fixed-width-xs',
                'remove_onclick' => true
            ),            
            'name' => array(
                'title' => 'Nom', 
                'width' => '200', 
                'filter_key' => 'a!name',
                'remove_onclick' => true
            ),
            'id_product' => array(
                'title' => 'Produit lié',
                'width' => '150',
                'callback' => 'displayLinkedProduct',
                'remove_onclick' => true
            ),
            'price' => array(
                'title' => 'Prix', 
                'width' => '80',
                'align' => 'center',
                'suffix' => ' €',
                'type' => 'price',
                'remove_onclick' => true
            ),
            'max_bookings' => array(
                'title' => 'Capacité', 
                'width' => '60',
                'align' => 'center',
                'remove_onclick' => true
            ),
            'duration' => array(
                'title' => 'Durée (min)', 
                'width' => '80',
                'align' => 'center',
                'remove_onclick' => true
            ),
            'date_add' => array(
                'title' => 'Date création', 
                'align' => 'center',
                'type' => 'datetime',
                'filter_key' => 'a!date_add',
                'remove_onclick' => true
            ),
            'active' => array(
                'title' => 'Actif', 
                'width' => 25, 
                'align' => 'center', 
                'active' => 'status', 
                'type' => 'bool', 
                'orderby' => false,
                'remove_onclick' => true
            ),
        );
        
        $this->bulk_actions = array(
            'delete' => array(
                'text' => 'Supprimer sélectionnés',
                'confirm' => 'Supprimer les éléments sélectionnés ?',
                'icon' => 'icon-trash'
            ),
            'enable' => array(
                'text' => 'Activer sélectionnés',
                'icon' => 'icon-power-off'
            ),
            'disable' => array(
                'text' => 'Désactiver sélectionnés',
                'icon' => 'icon-power-off'
            ),
            'sync_products' => array(
                'text' => 'Synchroniser avec produits',
                'icon' => 'icon-refresh'
            )
        );
        
        $this->has_bulk_actions = true;
        $this->shopLinkType = '';
        $this->no_link = false;
        $this->simple_header = false;
        $this->actions = array('edit', 'delete', 'view');
        $this->list_no_link = false;
        
        parent::__construct();
    }
    
    /**
     * Afficher le produit lié
     */
    public function displayLinkedProduct($id_product, $row)
    {
        if (!$id_product || $id_product == 0) {
            return '<span class="label label-warning">Aucun produit</span>';
        }
        
        $product = new Product($id_product, false, $this->context->language->id);
        if (!Validate::isLoadedObject($product)) {
            return '<span class="label label-danger">Produit introuvable</span>';
        }
        
        $product_url = $this->context->link->getAdminLink('AdminProducts') . '&id_product=' . $id_product . '&updateproduct';
        
        return '<a href="' . $product_url . '" target="_blank" class="btn btn-default btn-xs">
                    <i class="icon-external-link"></i> ' . Tools::truncate($product->name, 30) . '
                </a>';
    }
    
    public function renderForm()
    {
        $this->display = 'edit';
        $this->initToolbar();
		
        // Récupérer les produits disponibles
        $products = Product::getProducts($this->context->language->id, 0, 0, 'id_product', 'ASC', false, true);
        $products_options = array();
        $products_options[] = array('id' => 0, 'name' => '-- Créer un nouveau produit automatiquement --');
        
        foreach ($products as $product) {
            $products_options[] = array(
                'id' => $product['id_product'],
                'name' => '[' . $product['id_product'] . '] ' . $product['name']
            );
        }
        
        $this->fields_form = array(
            'legend' => array(
                'title' => $this->module->l('Gérer l\'élément à réserver'),
                'icon' => 'icon-cog'
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => 'Nom',
                    'name' => 'name',
                    'id' => 'name', 
                    'required' => true,
                    'size' => 50,
                    'hint' => 'Nom de l\'élément à réserver (bateau, salle, équipement...)'
                ),
                array(
                    'type' => 'select',
                    'label' => 'Produit PrestaShop associé',
                    'name' => 'id_product',
                    'options' => array(
                        'query' => $products_options,
                        'id' => 'id',
                        'name' => 'name'
                    ),
                    'hint' => 'Sélectionnez un produit existant ou laissez vide pour en créer un automatiquement'
                ),
                array(
                    'type' => 'textarea',
                    'label' => 'Description',
                    'name' => 'description',
                    'cols' => 60,
                    'required' => false,
                    'rows' => 6,
                    'class' => 'rte',
                    'autoload_rte' => true,
                    'hint' => 'Description détaillée de l\'élément à réserver',
                ),
                array(
                    'type' => 'text',
                    'label' => 'Prix de base',
                    'name' => 'price',
                    'suffix' => '€',
                    'class' => 'fixed-width-sm',
                    'hint' => 'Prix de base pour une réservation standard'
                ),
                array(
                    'type' => 'text',
                    'label' => 'Capacité maximale',
                    'name' => 'max_bookings',
                    'class' => 'fixed-width-sm',
                    'hint' => 'Nombre maximum de réservations simultanées'
                ),
                array(
                    'type' => 'text',
                    'label' => 'Durée par défaut (minutes)',
                    'name' => 'duration',
                    'class' => 'fixed-width-sm',
                    'suffix' => 'min',
                    'hint' => 'Durée standard d\'une réservation en minutes'
                ),
                array(
                    'type' => 'text',
                    'label' => 'Délai annulation (heures)',
                    'name' => 'cancellation_hours',
                    'class' => 'fixed-width-sm',
                    'suffix' => 'h',
                    'hint' => 'Délai minimum avant annulation sans frais'
                ),
                array(
                    'type' => 'switch',
                    'label' => 'Caution requise',
                    'name' => 'require_deposit',
                    'is_bool' => true,
                    'values' => array(
                        array('id' => 'deposit_on', 'value' => 1, 'label' => 'Oui'),
                        array('id' => 'deposit_off', 'value' => 0, 'label' => 'Non')
                    ),
                    'hint' => 'Une caution sera-t-elle demandée pour les réservations ?'
                ),
                array(
                    'type' => 'text',
                    'label' => 'Montant de la caution',
                    'name' => 'deposit_amount',
                    'suffix' => '€',
                    'class' => 'fixed-width-sm',
                    'hint' => 'Montant fixe de la caution'
                ),
                array(
                    'type' => 'switch',
                    'label' => 'Confirmation automatique',
                    'name' => 'auto_confirm',
                    'is_bool' => true,
                    'values' => array(
                        array('id' => 'auto_on', 'value' => 1, 'label' => 'Oui'),
                        array('id' => 'auto_off', 'value' => 0, 'label' => 'Non')
                    ),
                    'hint' => 'Les réservations seront-elles confirmées automatiquement ?'
                ),
                array(
                    'type' => 'switch',
                    'label' => 'Actif',
                    'name' => 'active',
                    'required' => false,
                    'is_bool' => true,
                    'values' => array(
                        array('id' => 'active_on', 'value' => 1, 'label' => 'Oui'), 
                        array('id' => 'active_off', 'value' => 0, 'label' => 'Non')
                    ),
                )
            ),
            'submit' => array('title' => 'Sauvegarder'),			
        );

        return parent::renderForm();
    }
    
    /**
     * Traitement avant sauvegarde
     */
    public function processSave()
    {
        $id_booker = (int)Tools::getValue('id');
        $id_product = (int)Tools::getValue('id_product');
        
        // Traitement standard
        $result = parent::processSave();
        
        if ($result) {
            // Récupérer l'objet créé/modifié
            if ($id_booker) {
                $booker = new Booker($id_booker);
            } else {
                // Nouveau booker, récupérer le dernier créé
                $last_id = Db::getInstance()->Insert_ID();
                $booker = new Booker($last_id);
            }
            
            if (Validate::isLoadedObject($booker)) {
                // Gestion de l'intégration produit
                if ($id_product == 0) {
                    // Créer un nouveau produit automatiquement
                    $new_product_id = BookingProductIntegration::createProductForBooker($booker);
                    if ($new_product_id) {
                        $booker->id_product = $new_product_id;
                        $booker->update();
                        $this->confirmations[] = 'Produit PrestaShop créé automatiquement (ID: ' . $new_product_id . ')';
                    }
                } else {
                    // Lier au produit existant
                    $booker->id_product = $id_product;
                    $booker->update();
                    $this->confirmations[] = 'Élément lié au produit PrestaShop (ID: ' . $id_product . ')';
                }
                
                // Synchroniser les données avec le produit
                if ($booker->id_product) {
                    $booker->syncWithProduct();
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Traitement des actions en lot
     */
    public function processBulkSyncProducts()
    {
        $booker_ids = Tools::getValue('bookerBox');
        if (!is_array($booker_ids) || empty($booker_ids)) {
            $this->errors[] = 'Aucun élément sélectionné';
            return;
        }
        
        $synced = 0;
        foreach ($booker_ids as $id_booker) {
            $booker = new Booker((int)$id_booker);
            if (Validate::isLoadedObject($booker)) {
                if (!$booker->id_product) {
                    // Créer un produit si aucun n'existe
                    $product_id = BookingProductIntegration::createProductForBooker($booker);
                    if ($product_id) {
                        $booker->id_product = $product_id;
                        $booker->update();
                        $synced++;
                    }
                } else {
                    // Synchroniser avec le produit existant
                    if ($booker->syncWithProduct()) {
                        $synced++;
                    }
                }
            }
        }
        
        $this->confirmations[] = $synced . ' élément(s) synchronisé(s) avec les produits PrestaShop';
    }
    
    /**
     * Ajout d'un booker
     */
    public function processAdd()
    {
        $object = new $this->className();
        $this->copyFromPost($object, $this->table);
        
        // Valeurs par défaut
        if (!$object->price || $object->price <= 0) {
            $object->price = Configuration::get('BOOKING_DEFAULT_PRICE', 50.00);
        }
        if (!$object->max_bookings || $object->max_bookings <= 0) {
            $object->max_bookings = 1;
        }
        if (!$object->duration || $object->duration <= 0) {
            $object->duration = 60;
        }
        if (!$object->cancellation_hours) {
            $object->cancellation_hours = 24;
        }
        
        // Ajouter les dates de création/modification
        $object->date_add = date('Y-m-d H:i:s');
        $object->date_upd = date('Y-m-d H:i:s');
        
        if ($object->add()) {
            $this->confirmations[] = 'Élément créé avec succès';
            return true;
        } else {
            $this->errors[] = 'Erreur lors de la création';
            return false;
        }
    }
    
    /**
     * Mise à jour d'un booker
     */
    public function processUpdate()
    {
        $id = (int)Tools::getValue($this->identifier);
        $object = new $this->className($id);
        
        if (!Validate::isLoadedObject($object)) {
            $this->errors[] = 'Élément introuvable';
            return false;
        }
        
        $this->copyFromPost($object, $this->table);
        $object->date_upd = date('Y-m-d H:i:s');
        
        if ($object->update()) {
            $this->confirmations[] = 'Élément mis à jour avec succès';
            return true;
        } else {
            $this->errors[] = 'Erreur lors de la mise à jour';
            return false;
        }
    }
    
    public function initPageHeaderToolbar()
    {
        $this->page_header_toolbar_btn['new'] = array(
            'href' => self::$currentIndex . '&add' . $this->table . '&token=' . $this->token,
            'desc' => 'Ajouter un nouvel élément à réserver',
            'icon' => 'process-icon-new'
        );
        
        $this->page_header_toolbar_btn['sync_all'] = array(
            'href' => self::$currentIndex . '&syncAllProducts&token=' . $this->token,
            'desc' => 'Synchroniser tous les éléments avec les produits',
            'icon' => 'process-icon-refresh'
        );
        
        parent::initPageHeaderToolbar();
    }
    
    /**
     * Synchroniser tous les bookers avec les produits
     */
    public function processSyncAllProducts()
    {
        try {
            $synced = BookingProductIntegration::syncAllBookers();
            $this->confirmations[] = $synced . ' élément(s) synchronisé(s) avec les produits PrestaShop';
        } catch (Exception $e) {
            $this->errors[] = 'Erreur lors de la synchronisation: ' . $e->getMessage();
        }
        
        return true;
    }
    
    public function renderList()
    {
        // Ajouter des informations contextuelles
        $total_bookers = Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker`');
        $active_bookers = Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker` WHERE active = 1');
        $linked_products = Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker` WHERE id_product > 0');
        
        $info_panel = '
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-info"></i> Statistiques des éléments à réserver
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-lg-3">
                        <div class="alert alert-info text-center">
                            <div style="font-size: 2em; font-weight: bold;">' . $total_bookers . '</div>
                            <div>Total éléments</div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="alert alert-success text-center">
                            <div style="font-size: 2em; font-weight: bold;">' . $active_bookers . '</div>
                            <div>Éléments actifs</div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="alert alert-warning text-center">
                            <div style="font-size: 2em; font-weight: bold;">' . $linked_products . '</div>
                            <div>Produits liés</div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="alert alert-default text-center">
                            <div style="font-size: 2em; font-weight: bold;">' . ($total_bookers - $linked_products) . '</div>
                            <div>Sans produit</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
        
        $list = parent::renderList();	
        $this->context->smarty->assign(array(	  
            'ajaxUrl' => $this->context->link->getAdminLink('AdminBooker')
        ));
        $content = $this->context->smarty->fetch($this->getTemplatePath().'ajax.tpl');
        return $info_panel . $list . $content;
    }
}
