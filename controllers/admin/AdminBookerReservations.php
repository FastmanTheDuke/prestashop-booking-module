<?php
/**
 * Contrôleur administrateur pour la gestion avancée des réservations
 * Vue orientée validation et actions rapides sur les réservations
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
            ),
            'active' => array(
                'title' => 'Actif', 
                'filter_key' => 'a!active', 
                'align' => 'center',
                'type' => 'bool',
                'active' => 'status',
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
            ),
            'enable' => array(
                'text' => 'Activer sélectionnés',
                'icon' => 'icon-power-off'
            ),
            'disable' => array(
                'text' => 'Désactiver sélectionnés',
                'icon' => 'icon-power-off'
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
        
        // Filtre par défaut : ne montrer que les réservations récentes
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
            'href' => '#',
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
        
        parent::initPageHeaderToolbar();
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
        
        $content = $this->context->smarty->fetch($this->getTemplatePath() . 'reservation_actions.tpl');
        
        return $stats_html . $list . $content;
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
        
        foreach ($quick_filters as $key => $label) {
            $class = ($current_filter === $key) ? 'btn-primary' : 'btn-default';
            $url = self::$currentIndex . '&quick_filter=' . $key . '&token=' . $this->token;
            
            $this->page_header_toolbar_btn['filter_' . $key] = array(
                'href' => $url,
                'desc' => $label,
                'class' => 'btn ' . $class . ' btn-sm'
            );
        }
        
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
                $this->_where .= ' AND a.status = ' . BookerAuthReserved::STATUS_PENDING;
                break;
                
            case 'accepted':
                $this->_where .= ' AND a.status = ' . BookerAuthReserved::STATUS_ACCEPTED;
                break;
                
            case 'paid':
                $this->_where .= ' AND a.status = ' . BookerAuthReserved::STATUS_PAID;
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
        $statuses = BookerAuthReserved::getStatuses();
        $status_label = isset($statuses[$row['status']]) ? $statuses[$row['status']] : 'Inconnu';
        
        $class = 'label-default';
        switch ($row['status']) {
            case BookerAuthReserved::STATUS_PENDING:
                $class = 'label-warning';
                break;
            case BookerAuthReserved::STATUS_ACCEPTED:
                $class = 'label-info';
                break;
            case BookerAuthReserved::STATUS_PAID:
                $class = 'label-success';
                break;
            case BookerAuthReserved::STATUS_CANCELLED:
            case BookerAuthReserved::STATUS_EXPIRED:
                $class = 'label-danger';
                break;
        }
        
        return '<span class="label ' . $class . '">' . $status_label . '</span>';
    }
    
    public function getPaymentStatusBadge($value, $row)
    {
        $payment_statuses = array(
            BookerAuthReserved::PAYMENT_PENDING => 'En attente',
            BookerAuthReserved::PAYMENT_PARTIAL => 'Partiel',
            BookerAuthReserved::PAYMENT_COMPLETED => 'Complet',
            BookerAuthReserved::PAYMENT_REFUNDED => 'Remboursé'
        );
        
        $status_label = isset($payment_statuses[$row['payment_status']]) ? $payment_statuses[$row['payment_status']] : 'Inconnu';
        
        $class = 'label-default';
        switch ($row['payment_status']) {
            case BookerAuthReserved::PAYMENT_PENDING:
                $class = 'label-warning';
                break;
            case BookerAuthReserved::PAYMENT_PARTIAL:
                $class = 'label-info';
                break;
            case BookerAuthReserved::PAYMENT_COMPLETED:
                $class = 'label-success';
                break;
            case BookerAuthReserved::PAYMENT_REFUNDED:
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
        
        $reservation = new BookerAuthReserved($id_reserved);
        
        if (!Validate::isLoadedObject($reservation)) {
            die(json_encode(array('success' => false, 'message' => 'Réservation introuvable')));
        }
        
        switch ($action) {
            case 'accept':
                $reservation->status = BookerAuthReserved::STATUS_ACCEPTED;
                $success = $reservation->update();
                $message = $success ? 'Réservation acceptée' : 'Erreur lors de l\'acceptation';
                break;
                
            case 'refuse':
                $reservation->status = BookerAuthReserved::STATUS_CANCELLED;
                $reservation->cancellation_reason = 'Refusée par l\'administrateur';
                $success = $reservation->update();
                $message = $success ? 'Réservation refusée' : 'Erreur lors du refus';
                break;
                
            case 'toggle_active':
                $reservation->active = !$reservation->active;
                $success = $reservation->update();
                $message = $success ? 'Statut modifié' : 'Erreur lors de la modification';
                break;
                
            default:
                $success = false;
                $message = 'Action inconnue';
        }
        
        die(json_encode(array(
            'success' => $success,
            'message' => $message,
            'new_status' => $reservation->status,
            'new_active' => $reservation->active
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
            $reservation = new BookerAuthReserved((int)$id);
            
            if (Validate::isLoadedObject($reservation)) {
                $reservation->status = BookerAuthReserved::STATUS_ACCEPTED;
                
                if ($reservation->update()) {
                    $success_count++;
                }
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
            $reservation = new BookerAuthReserved((int)$id);
            
            if (Validate::isLoadedObject($reservation)) {
                $reservation->status = BookerAuthReserved::STATUS_CANCELLED;
                $reservation->cancellation_reason = 'Refusée en lot par l\'administrateur';
                
                if ($reservation->update()) {
                    $success_count++;
                }
            }
        }
        
        $this->confirmations[] = $success_count . ' réservation(s) refusée(s)';
        return true;
    }
    
    /**
     * Méthodes de statistiques
     */
    private function getTotalReservations()
    {
        return (int)Db::getInstance()->getValue('
            SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` 
            WHERE active = 1
        ');
    }
    
    private function getPendingReservationsCount()
    {
        return (int)Db::getInstance()->getValue('
            SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` 
            WHERE active = 1 AND status = ' . BookerAuthReserved::STATUS_PENDING
        );
    }
    
    private function getAcceptedReservationsCount()
    {
        return (int)Db::getInstance()->getValue('
            SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` 
            WHERE active = 1 AND status = ' . BookerAuthReserved::STATUS_ACCEPTED
        );
    }
    
    private function getPaidReservationsCount()
    {
        return (int)Db::getInstance()->getValue('
            SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` 
            WHERE active = 1 AND status = ' . BookerAuthReserved::STATUS_PAID
        );
    }
    
    private function getTodayReservationsCount()
    {
        return (int)Db::getInstance()->getValue('
            SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` 
            WHERE active = 1 AND DATE(date_reserved) = CURDATE()
        ');
    }
    
    private function getTodayRevenue()
    {
        $result = Db::getInstance()->getValue('
            SELECT SUM(total_price) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` 
            WHERE active = 1 AND DATE(date_reserved) = CURDATE() AND status = ' . BookerAuthReserved::STATUS_PAID
        );
        
        return $result ? (float)$result : 0;
    }
    
    private function getMonthRevenue()
    {
        $result = Db::getInstance()->getValue('
            SELECT SUM(total_price) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` 
            WHERE active = 1 AND MONTH(date_reserved) = MONTH(CURDATE()) AND YEAR(date_reserved) = YEAR(CURDATE()) AND status = ' . BookerAuthReserved::STATUS_PAID
        );
        
        return $result ? (float)$result : 0;
    }
}