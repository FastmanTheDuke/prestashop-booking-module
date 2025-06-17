<?php
/**
 * AdminBookerController - Gestion des éléments réservables
 * Version 2.1.5 - Avec liaison produits PrestaShop et gestion avancée
 */

require_once _PS_MODULE_DIR_ . 'booking/classes/Booker.php';

class AdminBookerController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'booker';
        $this->className = 'Booker';
        $this->identifier = 'id_booker';
        $this->lang = false;
        
        parent::__construct();
        
        $this->addRowAction('edit');
        $this->addRowAction('delete');
        $this->addRowAction('duplicate');
        $this->addRowAction('view_availability');
        $this->addRowAction('view_reservations');
        
        $this->bulk_actions = array(
            'delete' => array(
                'text' => $this->l('Delete selected'),
                'confirm' => $this->l('Delete selected items?'),
                'icon' => 'icon-trash'
            ),
            'activate' => array(
                'text' => $this->l('Enable selection'),
                'icon' => 'icon-power-off text-success'
            ),
            'deactivate' => array(
                'text' => $this->l('Disable selection'),
                'icon' => 'icon-power-off text-danger'
            )
        );
        
        $this->fields_list = array(
            'id_booker' => array(
                'title' => $this->l('ID'),
                'align' => 'center',
                'class' => 'fixed-width-xs'
            ),
            'name' => array(
                'title' => $this->l('Name'),
                'width' => 'auto',
                'filter_key' => 'a!name'
            ),
            'price' => array(
                'title' => $this->l('Price'),
                'width' => 140,
                'type' => 'price',
                'align' => 'text-right'
            ),
            'capacity' => array(
                'title' => $this->l('Capacity'),
                'width' => 80,
                'align' => 'center'
            ),
            'booking_duration' => array(
                'title' => $this->l('Duration (min)'),
                'width' => 100,
                'align' => 'center'
            ),
            'deposit_required' => array(
                'title' => $this->l('Deposit Required'),
                'width' => 100,
                'align' => 'center',
                'type' => 'bool',
                'active' => 'deposit_required'
            ),
            'product_name' => array(
                'title' => $this->l('Linked Product'),
                'width' => 200,
                'orderby' => false,
                'search' => false
            ),
            'reservations_count' => array(
                'title' => $this->l('Reservations'),
                'width' => 100,
                'align' => 'center',
                'orderby' => false,
                'search' => false
            ),
            'active' => array(
                'title' => $this->l('Status'),
                'width' => 70,
                'align' => 'center',
                'active' => 'status',
                'type' => 'bool'
            ),
            'date_add' => array(
                'title' => $this->l('Date add'),
                'width' => 130,
                'align' => 'right',
                'type' => 'datetime'
            )
        );
        
        $this->shopLinkType = 'shop';
        $this->multishop_context = Shop::CONTEXT_ALL;
    }

    /**
     * Override de la liste pour ajouter les informations des produits liés
     */
    public function getList($id_lang, $order_by = null, $order_way = null, $start = 0, $limit = null, $id_lang_shop = false)
    {
        parent::getList($id_lang, $order_by, $order_way, $start, $limit, $id_lang_shop);
        
        if (!empty($this->_list)) {
            foreach ($this->_list as &$row) {
                // Récupérer le produit lié
                $linked_product = $this->getLinkedProduct($row['id_booker']);
                $row['product_name'] = $linked_product ? $linked_product['name'] : $this->l('No product linked');
                
                // Compter les réservations
                $row['reservations_count'] = $this->getReservationsCount($row['id_booker']);
            }
        }
    }

    /**
     * Récupérer le produit lié à un booker
     */
    private function getLinkedProduct($id_booker)
    {
        $sql = 'SELECT p.id_product, pl.name
                FROM ' . _DB_PREFIX_ . 'booker_product bp
                LEFT JOIN ' . _DB_PREFIX_ . 'product p ON bp.id_product = p.id_product
                LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl ON p.id_product = pl.id_product
                WHERE bp.id_booker = ' . (int)$id_booker . '
                AND pl.id_lang = ' . (int)$this->context->language->id . '
                LIMIT 1';
        
        return Db::getInstance()->getRow($sql);
    }

    /**
     * Compter les réservations pour un booker
     */
    private function getReservationsCount($id_booker)
    {
        return Db::getInstance()->getValue('
            SELECT COUNT(*) 
            FROM ' . _DB_PREFIX_ . 'booker_auth_reserved 
            WHERE id_booker = ' . (int)$id_booker
        );
    }

    /**
     * Formulaire d'ajout/édition
     */
    public function renderForm()
    {
        $this->fields_form = array(
            'legend' => array(
                'title' => $this->l('Booking Item'),
                'icon' => 'icon-calendar'
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('Name'),
                    'name' => 'name',
                    'size' => 64,
                    'required' => true,
                    'desc' => $this->l('Enter the name of the bookable item')
                ),
                array(
                    'type' => 'textarea',
                    'label' => $this->l('Description'),
                    'name' => 'description',
                    'rows' => 5,
                    'cols' => 40,
                    'desc' => $this->l('Detailed description of the item')
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Location'),
                    'name' => 'location',
                    'size' => 64,
                    'desc' => $this->l('Physical location of the item')
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Price'),
                    'name' => 'price',
                    'class' => 'fixed-width-sm',
                    'suffix' => $this->context->currency->sign,
                    'desc' => $this->l('Base price for booking this item')
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Capacity'),
                    'name' => 'capacity',
                    'class' => 'fixed-width-sm',
                    'desc' => $this->l('Maximum number of people/bookings at the same time')
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Booking Duration (minutes)'),
                    'name' => 'booking_duration',
                    'class' => 'fixed-width-sm',
                    'desc' => $this->l('Default duration for each booking in minutes')
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Minimum Booking Time (hours)'),
                    'name' => 'min_booking_time',
                    'class' => 'fixed-width-sm',
                    'desc' => $this->l('Minimum time before booking (in hours)')
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Maximum Booking Days'),
                    'name' => 'max_booking_days',
                    'class' => 'fixed-width-sm',
                    'desc' => $this->l('Maximum days in advance for booking')
                ),
                // Section caution
                array(
                    'type' => 'switch',
                    'label' => $this->l('Deposit Required'),
                    'name' => 'deposit_required',
                    'values' => array(
                        array('id' => 'deposit_required_on', 'value' => 1, 'label' => $this->l('Yes')),
                        array('id' => 'deposit_required_off', 'value' => 0, 'label' => $this->l('No'))
                    ),
                    'desc' => $this->l('Require a deposit for this item')
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Deposit Rate (%)'),
                    'name' => 'deposit_rate',
                    'class' => 'fixed-width-sm',
                    'suffix' => '%',
                    'desc' => $this->l('Percentage of the total price to hold as deposit')
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Fixed Deposit Amount'),
                    'name' => 'deposit_amount',
                    'class' => 'fixed-width-sm',
                    'suffix' => $this->context->currency->sign,
                    'desc' => $this->l('Or fixed deposit amount (leave empty to use percentage)')
                ),
                // Configuration avancée
                array(
                    'type' => 'switch',
                    'label' => $this->l('Auto Confirm'),
                    'name' => 'auto_confirm',
                    'values' => array(
                        array('id' => 'auto_confirm_on', 'value' => 1, 'label' => $this->l('Yes')),
                        array('id' => 'auto_confirm_off', 'value' => 0, 'label' => $this->l('No'))
                    ),
                    'desc' => $this->l('Automatically confirm bookings without manual validation')
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Cancellation Hours'),
                    'name' => 'cancellation_hours',
                    'class' => 'fixed-width-sm',
                    'desc' => $this->l('Hours before booking when cancellation is allowed')
                ),
                array(
                    'type' => 'file',
                    'label' => $this->l('Image'),
                    'name' => 'image',
                    'desc' => $this->l('Upload an image for this item')
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Sort Order'),
                    'name' => 'sort_order',
                    'class' => 'fixed-width-sm',
                    'desc' => $this->l('Position in the list')
                ),
                // Liaison produit
                array(
                    'type' => 'select',
                    'label' => $this->l('Link to Product'),
                    'name' => 'id_product',
                    'options' => array(
                        'query' => $this->getProductsList(),
                        'id' => 'id_product',
                        'name' => 'name'
                    ),
                    'desc' => $this->l('Link this booking item to an existing product')
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->l('Active'),
                    'name' => 'active',
                    'required' => false,
                    'values' => array(
                        array('id' => 'active_on', 'value' => 1, 'label' => $this->l('Enabled')),
                        array('id' => 'active_off', 'value' => 0, 'label' => $this->l('Disabled'))
                    )
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
     * Récupérer la liste des produits pour la liaison
     */
    private function getProductsList()
    {
        $products = array();
        $products[] = array('id_product' => 0, 'name' => $this->l('-- No product --'));
        
        $sql = 'SELECT p.id_product, pl.name
                FROM ' . _DB_PREFIX_ . 'product p
                LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl ON p.id_product = pl.id_product
                WHERE pl.id_lang = ' . (int)$this->context->language->id . '
                AND p.active = 1
                ORDER BY pl.name';
        
        $result = Db::getInstance()->executeS($sql);
        if ($result) {
            $products = array_merge($products, $result);
        }
        
        return $products;
    }

    /**
     * Traitement après sauvegarde
     */
    public function postProcess()
    {
        if (Tools::isSubmit('submitAdd' . $this->table)) {
            $id_booker = (int)Tools::getValue('id_booker');
            $id_product = (int)Tools::getValue('id_product');
            
            // Gérer la liaison avec le produit
            if ($id_booker && $id_product) {
                $this->linkToProduct($id_booker, $id_product);
            }
            
            // Gérer l'upload d'image
            if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                $this->handleImageUpload($id_booker);
            }
        }
        
        return parent::postProcess();
    }

    /**
     * Lier un booker à un produit
     */
    private function linkToProduct($id_booker, $id_product)
    {
        // Supprimer les liaisons existantes
        Db::getInstance()->delete('booker_product', 'id_booker = ' . (int)$id_booker);
        
        if ($id_product > 0) {
            // Créer la nouvelle liaison
            $data = array(
                'id_booker' => (int)$id_booker,
                'id_product' => (int)$id_product,
                'sync_price' => 1,
                'date_add' => date('Y-m-d H:i:s')
            );
            
            return Db::getInstance()->insert('booker_product', $data);
        }
        
        return true;
    }

    /**
     * Gérer l'upload d'image
     */
    private function handleImageUpload($id_booker)
    {
        $upload_dir = _PS_MODULE_DIR_ . 'booking/uploads/';
        
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filename = 'booker_' . $id_booker . '.' . $extension;
        $destination = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
            // Mettre à jour la base de données
            return Db::getInstance()->update(
                'booker',
                array('image' => pSQL($filename)),
                'id_booker = ' . (int)$id_booker
            );
        }
        
        return false;
    }

    /**
     * Action personnalisée : voir les disponibilités
     */
    public function displayViewAvailabilityLink($token, $id, $name = null)
    {
        return '<a class="btn btn-default" href="' . self::$currentIndex . '&configure=booking&token=' . $token . '&view_availability=' . $id . '">
                    <i class="icon-calendar"></i> ' . $this->l('Availabilities') . '
                </a>';
    }

    /**
     * Action personnalisée : voir les réservations
     */
    public function displayViewReservationsLink($token, $id, $name = null)
    {
        return '<a class="btn btn-default" href="' . self::$currentIndex . '&configure=booking&token=' . $token . '&view_reservations=' . $id . '">
                    <i class="icon-list"></i> ' . $this->l('Reservations') . '
                </a>';
    }

    /**
     * Action personnalisée : dupliquer
     */
    public function displayDuplicateLink($token, $id, $name = null)
    {
        return '<a class="btn btn-default" href="' . self::$currentIndex . '&configure=booking&token=' . $token . '&duplicate=' . $id . '">
                    <i class="icon-copy"></i> ' . $this->l('Duplicate') . '
                </a>';
    }

    /**
     * Traitement de la duplication
     */
    public function processDuplicate()
    {
        if ($id_booker = (int)Tools::getValue('duplicate')) {
            $booker = new Booker($id_booker);
            if (Validate::isLoadedObject($booker)) {
                $new_booker = clone $booker;
                $new_booker->id_booker = null;
                $new_booker->name = $booker->name . ' (Copy)';
                $new_booker->active = 0; // Désactiver la copie par défaut
                
                if ($new_booker->add()) {
                    $this->confirmations[] = $this->l('Item duplicated successfully');
                } else {
                    $this->errors[] = $this->l('Error occurred during duplication');
                }
            }
        }
    }

    /**
     * Ajouter du CSS et JS personnalisés
     */
    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);
        
        $this->addCSS(_MODULE_DIR_ . 'booking/views/css/admin-booker.css');
        $this->addJS(_MODULE_DIR_ . 'booking/views/js/admin-booker.js');
    }
}
