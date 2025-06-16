<?php
/**
 * Contrôleur administrateur pour la gestion avancée des réservations
 * Version corrigée - suppression des références à la colonne 'active' inexistante
 */

require_once(dirname(__FILE__) . '/../../classes/Booker.php');
require_once(dirname(__FILE__) . '/../../classes/BookerAuth.php');
require_once(dirname(__FILE__) . '/../../classes/BookerAuthReserved.php');

class AdminBookerReservationsController extends ModuleAdminController
{
    protected $_module = NULL;
    public $controller_type = 'admin';   
    protected $position_identifier = 'id_reserved';

    public function __construct()
    {
        $this->display = 'options';
        $this->context = Context::getContext();
        $this->bootstrap = true;
        $this->table = 'booker_auth_reserved';
        $this->identifier = 'id_reserved';
        $this->className = 'BookerAuthReserved';
        $this->_defaultOrderBy = 'date_reserved';
        $this->_defaultOrderWay = 'DESC';
        $this->allow_export = true;
        
        // Configuration de la liste avec focus sur la validation
        $this->fields_list = array(
            'id_reserved' => array(
                'title' => 'ID', 
                'filter_key' => 'a!id_reserved', 
                'align' => 'center',
                'class' => 'fixed-width-xs',
                'remove_onclick' => true
            ),
            'booking_reference' => array(
                'title' => 'Référence',
                'filter_key' => 'a!booking_reference',
                'align' => 'center',
                'class' => 'fixed-width-lg',
                'remove_onclick' => true
            ),
            'booker_name' => array(
                'title' => 'Élément', 
                'filter_key' => 'b!name', 
                'align' => 'left',
                'remove_onclick' => true
            ),
            'customer_name' => array(
                'title' => 'Client',
                'filter_key' => 'a!customer_firstname',
                'align' => 'left',
                'callback' => 'getCustomerFullName',
                'remove_onclick' => true
            ),
            'customer_email' => array(
                'title' => 'Email',
                'filter_key' => 'a!customer_email',
                'align' => 'left',
                'remove_onclick' => true
            ),
            'date_reserved' => array(
                'title' => 'Date début', 
                'filter_key' => 'a!date_reserved', 
                'align' => 'center',
                'type' => 'date',
                'remove_onclick' => true
            ),
            'hour_from' => array(
                'title' => 'Heure début', 
                'filter_key' => 'a!hour_from', 
                'align' => 'center',
                'suffix' => 'h',
                'remove_onclick' => true
            ),
            'hour_to' => array(
                'title' => 'Heure fin', 
                'filter_key' => 'a!hour_to', 
                'align' => 'center',
                'suffix' => 'h',
                'remove_onclick' => true
            ),
            'total_price' => array(
                'title' => 'Prix',
                'filter_key' => 'a!total_price',
                'align' => 'right',
                'type' => 'price',
                'currency' => true,
                'remove_onclick' => true
            ),
            'status_badge' => array(
                'title' => 'Statut',
                'align' => 'center',
                'callback' => 'getStatusBadge',
                'orderby' => false,
                'search' => false,
                'remove_onclick' => true
            ),
            'payment_status_badge' => array(
                'title' => 'Paiement',
                'align' => 'center',
                'callback' => 'getPaymentStatusBadge',
                'orderby' => false,
                'search' => false,
                'remove_onclick' => true
            ),
            'date_add' => array(
                'title' => 'Créée le',
                'filter_key' => 'a!date_add',
                'align' => 'center',
                'type' => 'datetime',
                'remove_onclick' => true
            )
        );
        
        // Actions en lot
        $this->bulk_actions = array(
            'accept' => array(
                'text' => 'Accepter les réservations',
                'icon' => 'icon-check'
            ),
            'refuse' => array(
                'text' => 'Refuser les réservations',
                'icon' => 'icon-times'
            ),
            'delete' => array(
                'text' => 'Supprimer sélectionnés',
                'icon' => 'icon-trash'
            )
        );
        
        $this->has_bulk_actions = true;
        $this->shopLinkType = '';
        $this->no_link = false;
        $this->simple_header = false;
        $this->actions = array('view', 'edit', 'delete');
        $this->list_no_link = false;
        
        // Jointure pour récupérer le nom du booker
        $this->_join = 'LEFT JOIN `' . _DB_PREFIX_ . 'booker` b ON (a.id_booker = b.id_booker)';
        $this->_select = 'b.name as booker_name, CONCAT(a.customer_firstname, " ", a.customer_lastname) as customer_name';
        
        // Filtre par défaut : ne montrer que les réservations récentes (SUPPRESSION DE LA CONDITION active = 1)
        $this->_where = 'AND a.date_reserved >= DATE_SUB(NOW(), INTERVAL 6 MONTH)';
        
        parent::__construct();
    }
    
    /**
     * Boutons d'en-tête personnalisés
     */
    public function initPageHeaderToolbar()
    {
        // Statistiques rapides
        $pending_count = $this->getPendingReservationsCount();
        
        $this->page_header_toolbar_btn['stats'] = array(
            'href' => $this->context->link->getAdminLink('AdminBookerStats'),
            'desc' => 'Réservations en attente: ' . $pending_count,
            'icon' => 'process-icon-stats'
        );
        
        $this->page_header_toolbar_btn['calendar'] = array(
            'href' => $this->context->link->getAdminLink('AdminBookerView'),
            'desc' => 'Vue calendrier',
            'icon' => 'process-icon-calendar'
        );
        
        $this->page_header_toolbar_btn['export'] = array(
            'href' => self::$currentIndex . '&export' . $this->table . '&token=' . $this->token,
            'desc' => 'Exporter CSV',
            'icon' => 'process-icon-export'
        );
        
        $this->page_header_toolbar_btn['create_orders'] = array(
            'href' => self::$currentIndex . '&action=createPendingOrders&token=' . $this->token,
            'desc' => 'Créer commandes en attente',
            'icon' => 'process-icon-new',
            'class' => 'btn-success'
        );
        
        parent::initPageHeaderToolbar();
    }
    
    /**
     * Traitement des actions personnalisées
     */
    public function postProcess()
    {
        if (Tools::getValue('action') === 'createPendingOrders') {
            $this->createPendingOrders();
        }
        
        return parent::postProcess();
    }
    
    /**
     * Créer des commandes en attente pour les réservations acceptées
     */
    private function createPendingOrders()
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` 
                WHERE status = 1 AND id_order IS NULL';
        
        $reservations = Db::getInstance()->executeS($sql);
        $created_orders = 0;
        
        foreach ($reservations as $reservation) {
            if ($this->createOrderFromReservation($reservation)) {
                $created_orders++;
            }
        }
        
        if ($created_orders > 0) {
            $this->confirmations[] = sprintf('%d commande(s) créée(s) avec succès', $created_orders);
        } else {
            $this->warnings[] = 'Aucune commande n\'a pu être créée';
        }
    }
    
    /**
     * Créer une commande PrestaShop à partir d'une réservation
     */
    private function createOrderFromReservation($reservation)
    {
        try {
            // 1. Récupérer ou créer le client
            $customer = $this->getOrCreateCustomer($reservation);
            if (!$customer) {
                return false;
            }
            
            // 2. Récupérer le produit associé au booker
            $product = $this->getBookerProduct($reservation['id_booker']);
            if (!$product) {
                return false;
            }
            
            // 3. Créer le panier
            $cart = new Cart();
            $cart->id_customer = $customer->id;
            $cart->id_address_delivery = $customer->id_address;
            $cart->id_address_invoice = $customer->id_address;
            $cart->id_lang = $this->context->language->id;
            $cart->id_currency = $this->context->currency->id;
            $cart->id_carrier = 1; // Carrier par défaut pour produits virtuels
            $cart->recyclable = 0;
            $cart->gift = 0;
            $cart->add();
            
            // 4. Ajouter le produit au panier
            $cart->updateQty(1, $product->id);
            
            // 5. Créer la commande
            $order = new Order();
            $order->id_customer = $customer->id;
            $order->id_cart = $cart->id;
            $order->id_currency = $this->context->currency->id;
            $order->id_lang = $this->context->language->id;
            $order->id_carrier = 1;
            $order->current_state = Configuration::get('PS_OS_BANKWIRE'); // En attente de paiement
            $order->payment = 'Réservation';
            $order->module = 'booking';
            $order->total_paid = $reservation['total_price'];
            $order->total_paid_tax_incl = $reservation['total_price'];
            $order->total_paid_tax_excl = $reservation['total_price'];
            $order->total_products = $reservation['total_price'];
            $order->total_products_wt = $reservation['total_price'];
            $order->conversion_rate = 1;
            $order->add();
            
            // 6. Mettre à jour la réservation avec l'ID de commande
            $update_sql = 'UPDATE `' . _DB_PREFIX_ . 'booker_auth_reserved` 
                          SET id_order = ' . (int)$order->id . ', status = 2 
                          WHERE id_reserved = ' . (int)$reservation['id_reserved'];
            
            Db::getInstance()->execute($update_sql);
            
            return true;
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog('Erreur création commande réservation: ' . $e->getMessage(), 3);
            return false;
        }
    }
    
    /**
     * Récupérer ou créer un client
     */
    private function getOrCreateCustomer($reservation)
    {
        // Rechercher un client existant par email
        $id_customer = Customer::customerExists($reservation['customer_email'], true);
        
        if ($id_customer) {
            return new Customer($id_customer);
        }
        
        // Créer un nouveau client
        $customer = new Customer();
        $customer->firstname = $reservation['customer_firstname'];
        $customer->lastname = $reservation['customer_lastname'];
        $customer->email = $reservation['customer_email'];
        $customer->passwd = Tools::encrypt(Tools::passwdGen());
        $customer->active = 1;
        $customer->add();
        
        // Créer une adresse par défaut
        $address = new Address();
        $address->id_customer = $customer->id;
        $address->firstname = $customer->firstname;
        $address->lastname = $customer->lastname;
        $address->address1 = 'Adresse de réservation';
        $address->city = 'Ville';
        $address->postcode = '00000';
        $address->id_country = Country::getByIso('FR');
        $address->alias = 'Adresse de réservation';
        $address->add();
        
        $customer->id_address = $address->id;
        $customer->update();
        
        return $customer;
    }
    
    /**
     * Récupérer le produit associé à un booker
     */
    private function getBookerProduct($id_booker)
    {
        $sql = 'SELECT id_product FROM `' . _DB_PREFIX_ . 'booker` WHERE id_booker = ' . (int)$id_booker;
        $id_product = Db::getInstance()->getValue($sql);
        
        if ($id_product) {
            return new Product($id_product);
        }
        
        return null;
    }
    
    /**
     * Rendu de la liste avec filtres rapides
     */
    public function renderList()
    {
        // Ajouter des filtres rapides
        $this->addQuickFilters();
        
        $list = parent::renderList();
        
        // Ajouter les statistiques en haut
        $stats_html = $this->renderReservationStats();
        
        // Ajouter JavaScript pour actions rapides
        $this->context->smarty->assign(array(		  
            'ajaxUrl' => $this->context->link->getAdminLink('AdminBookerReservations'),
            'quick_actions_enabled' => true,
            'pending_count' => $this->getPendingReservationsCount()
        ));
        
        $js_actions = '
        <script>
        $(document).ready(function() {
            $(".quick-action").click(function(e) {
                e.preventDefault();
                var action = $(this).data("action");
                var id = $(this).data("id");
                
                $.ajax({
                    url: "' . $this->context->link->getAdminLink('AdminBookerReservations') . '",
                    type: "POST",
                    data: {
                        ajax: 1,
                        action: "quickAction",
                        quick_action: action,
                        id_reserved: id
                    },
                    success: function(response) {
                        var data = JSON.parse(response);
                        if (data.success) {
                            showSuccessMessage(data.message);
                            location.reload();
                        } else {
                            showErrorMessage(data.message);
                        }
                    }
                });
            });
        });
        </script>';
        
        return $stats_html . $list . $js_actions;
    }
    
    /**
     * Ajouter des filtres rapides
     */
    private function addQuickFilters()
    {
        $quick_filters = array(
            'all' => 'Toutes',
            'pending' => 'En attente',
            'accepted' => 'Acceptées',
            'paid' => 'Payées',
            'today' => 'Aujourd\'hui',
            'tomorrow' => 'Demain',
            'week' => 'Cette semaine'
        );
        
        $current_filter = Tools::getValue('quick_filter', 'all');
        
        // Appliquer le filtre à la requête
        $this->applyQuickFilter($current_filter);
    }
    
    /**
     * Appliquer un filtre rapide
     */
    private function applyQuickFilter($filter)
    {
        switch ($filter) {
            case 'pending':
                $this->_where .= ' AND a.status = 0';
                break;
                
            case 'accepted':
                $this->_where .= ' AND a.status = 1';
                break;
                
            case 'paid':
                $this->_where .= ' AND a.status = 2';
                break;
                
            case 'today':
                $this->_where .= ' AND DATE(a.date_reserved) = CURDATE()';
                break;
                
            case 'tomorrow':
                $this->_where .= ' AND DATE(a.date_reserved) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)';
                break;
                
            case 'week':
                $this->_where .= ' AND a.date_reserved BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)';
                break;
        }
    }
    
    /**
     * Statistiques des réservations
     */
    private function renderReservationStats()
    {
        $stats = array(
            'total' => $this->getTotalReservations(),
            'pending' => $this->getPendingReservationsCount(),
            'accepted' => $this->getAcceptedReservationsCount(),
            'paid' => $this->getPaidReservationsCount(),
            'today' => $this->getTodayReservationsCount(),
            'revenue_today' => $this->getTodayRevenue(),
            'revenue_month' => $this->getMonthRevenue()
        );
        
        $html = '<div class="panel">
            <div class="panel-heading">
                <i class="icon-bar-chart"></i> Statistiques rapides
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-lg-2 col-md-3 col-sm-6">
                        <div class="alert alert-info text-center">
                            <div style="font-size: 2em; font-weight: bold;">' . $stats['pending'] . '</div>
                            <div>En attente</div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-3 col-sm-6">
                        <div class="alert alert-warning text-center">
                            <div style="font-size: 2em; font-weight: bold;">' . $stats['accepted'] . '</div>
                            <div>Acceptées</div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-3 col-sm-6">
                        <div class="alert alert-success text-center">
                            <div style="font-size: 2em; font-weight: bold;">' . $stats['paid'] . '</div>
                            <div>Payées</div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-3 col-sm-6">
                        <div class="alert alert-primary text-center">
                            <div style="font-size: 2em; font-weight: bold;">' . $stats['today'] . '</div>
                            <div>Aujourd\'hui</div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-3 col-sm-6">
                        <div class="alert alert-success text-center">
                            <div style="font-size: 1.5em; font-weight: bold;">' . number_format($stats['revenue_today'], 2) . '€</div>
                            <div>CA jour</div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-3 col-sm-6">
                        <div class="alert alert-success text-center">
                            <div style="font-size: 1.5em; font-weight: bold;">' . number_format($stats['revenue_month'], 2) . '€</div>
                            <div>CA mois</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
        
        return $html;
    }
    
    /**
     * Callbacks pour l'affichage
     */
    public function getCustomerFullName($value, $row)
    {
        return $row['customer_firstname'] . ' ' . $row['customer_lastname'];
    }
    
    public function getStatusBadge($value, $row)
    {
        $statuses = array(
            0 => 'En attente',
            1 => 'Acceptée',
            2 => 'Payée',
            3 => 'Annulée',
            4 => 'Expirée',
            5 => 'Terminée'
        );
        
        $status_label = isset($statuses[$row['status']]) ? $statuses[$row['status']] : 'Inconnu';
        
        $class = 'label-default';
        switch ($row['status']) {
            case 0:
                $class = 'label-warning';
                break;
            case 1:
                $class = 'label-info';
                break;
            case 2:
                $class = 'label-success';
                break;
            case 3:
            case 4:
                $class = 'label-danger';
                break;
            case 5:
                $class = 'label-primary';
                break;
        }
        
        return '<span class="label ' . $class . '">' . $status_label . '</span>';
    }
    
    public function getPaymentStatusBadge($value, $row)
    {
        $payment_statuses = array(
            'pending' => 'En attente',
            'authorized' => 'Autorisé',
            'captured' => 'Capturé',
            'cancelled' => 'Annulé',
            'refunded' => 'Remboursé'
        );
        
        $status_label = isset($payment_statuses[$row['payment_status']]) ? $payment_statuses[$row['payment_status']] : 'Inconnu';
        
        $class = 'label-default';
        switch ($row['payment_status']) {
            case 'pending':
                $class = 'label-warning';
                break;
            case 'authorized':
                $class = 'label-info';
                break;
            case 'captured':
                $class = 'label-success';
                break;
            case 'cancelled':
            case 'refunded':
                $class = 'label-danger';
                break;
        }
        
        return '<span class="label ' . $class . '">' . $status_label . '</span>';
    }
    
    /**
     * Actions AJAX pour validation rapide
     */
    public function ajaxProcessQuickAction()
    {
        $action = Tools::getValue('quick_action');
        $id_reserved = (int)Tools::getValue('id_reserved');
        
        if (!$id_reserved) {
            die(json_encode(array('success' => false, 'message' => 'ID manquant')));
        }
        
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` WHERE id_reserved = ' . $id_reserved;
        $reservation = Db::getInstance()->getRow($sql);
        
        if (!$reservation) {
            die(json_encode(array('success' => false, 'message' => 'Réservation introuvable')));
        }
        
        switch ($action) {
            case 'accept':
                $success = Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . 'booker_auth_reserved` SET status = 1 WHERE id_reserved = ' . $id_reserved);
                $message = $success ? 'Réservation acceptée' : 'Erreur lors de l\'acceptation';
                break;
                
            case 'refuse':
                $success = Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . 'booker_auth_reserved` SET status = 3 WHERE id_reserved = ' . $id_reserved);
                $message = $success ? 'Réservation refusée' : 'Erreur lors du refus';
                break;
                
            default:
                $success = false;
                $message = 'Action inconnue';
        }
        
        die(json_encode(array(
            'success' => $success,
            'message' => $message
        )));
    }
    
    /**
     * Gestion des actions en lot
     */
    protected function processBulkAccept()
    {
        $selected = Tools::getValue($this->table . 'Box');
        
        if (empty($selected)) {
            $this->errors[] = 'Aucune réservation sélectionnée';
            return false;
        }
        
        $success_count = 0;
        
        foreach ($selected as $id) {
            $sql = 'UPDATE `' . _DB_PREFIX_ . 'booker_auth_reserved` SET status = 1 WHERE id_reserved = ' . (int)$id;
            if (Db::getInstance()->execute($sql)) {
                $success_count++;
            }
        }
        
        $this->confirmations[] = $success_count . ' réservation(s) acceptée(s)';
        return true;
    }
    
    protected function processBulkRefuse()
    {
        $selected = Tools::getValue($this->table . 'Box');
        
        if (empty($selected)) {
            $this->errors[] = 'Aucune réservation sélectionnée';
            return false;
        }
        
        $success_count = 0;
        
        foreach ($selected as $id) {
            $sql = 'UPDATE `' . _DB_PREFIX_ . 'booker_auth_reserved` SET status = 3 WHERE id_reserved = ' . (int)$id;
            if (Db::getInstance()->execute($sql)) {
                $success_count++;
            }
        }
        
        $this->confirmations[] = $success_count . ' réservation(s) refusée(s)';
        return true;
    }
    
    /**
     * Méthodes de statistiques (SUPPRESSION DES CONDITIONS active = 1)
     */
    private function getTotalReservations()
    {
        return (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`');
    }
    
    private function getPendingReservationsCount()
    {
        return (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` WHERE status = 0');
    }
    
    private function getAcceptedReservationsCount()
    {
        return (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` WHERE status = 1');
    }
    
    private function getPaidReservationsCount()
    {
        return (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` WHERE status = 2');
    }
    
    private function getTodayReservationsCount()
    {
        return (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` WHERE DATE(date_reserved) = CURDATE()');
    }
    
    private function getTodayRevenue()
    {
        $result = Db::getInstance()->getValue('SELECT SUM(total_price) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` WHERE DATE(date_reserved) = CURDATE() AND status = 2');
        return $result ? (float)$result : 0;
    }
    
    private function getMonthRevenue()
    {
        $result = Db::getInstance()->getValue('SELECT SUM(total_price) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` WHERE MONTH(date_reserved) = MONTH(CURDATE()) AND YEAR(date_reserved) = YEAR(CURDATE()) AND status = 2');
        return $result ? (float)$result : 0;
    }
}