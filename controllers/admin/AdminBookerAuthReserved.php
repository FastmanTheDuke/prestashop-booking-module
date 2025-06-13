<?php
require_once (dirname(__FILE__). '/../../classes/BookerAuthReserved.php');

class AdminBookerAuthReservedController extends ModuleAdminController
{
    protected $_module = NULL;
    public $controller_type='admin';   
    protected $position_identifier = 'id';

    public function __construct()
    {
        $this->display = 'options';
        $this->context = Context::getContext();
        $this->bootstrap = true;
        $this->table = 'booker_auth_reserved';
        $this->identifier = 'id';
        $this->className = 'BookerAuthReserved';
        $this->_defaultOrderBy = 'date_start';
        $this->_defaultOrderWay = 'DESC';
        $this->allow_export = true;
        
        $this->fields_list = array(
            'id' => array(
                'title' => 'ID', 
                'filter_key' => 'a!id', 
                'align' => 'center',
                'class' => 'fixed-width-xs',
                'remove_onclick' => true
            ),       
            'booking_reference' => array(
                'title' => 'Référence', 
                'filter_key' => 'a!booking_reference', 
                'align' => 'center',
                'remove_onclick' => true
            ),
            'customer_firstname' => array(
                'title' => 'Prénom', 
                'filter_key' => 'a!customer_firstname', 
                'remove_onclick' => true
            ),
            'customer_lastname' => array(
                'title' => 'Nom', 
                'filter_key' => 'a!customer_lastname', 
                'remove_onclick' => true
            ),
            'customer_email' => array(
                'title' => 'Email', 
                'filter_key' => 'a!customer_email', 
                'remove_onclick' => true
            ),           
            'date_start' => array(
                'title' => 'Date début', 
                'filter_key' => 'a!date_start', 
                'align' => 'center',
                'type' => 'datetime',
                'remove_onclick' => true
            ),
            'date_end' => array(
                'title' => 'Date fin', 
                'filter_key' => 'a!date_end', 
                'align' => 'center',
                'type' => 'datetime',
                'remove_onclick' => true
            ),
            'total_price' => array(
                'title' => 'Prix total', 
                'filter_key' => 'a!total_price', 
                'align' => 'center',
                'suffix' => ' €',
                'type' => 'price',
                'remove_onclick' => true
            ),
            'status' => array(
                'title' => 'Statut', 
                'filter_key' => 'a!status', 
                'align' => 'center',
                'type' => 'select',
                'list' => BookerAuthReserved::getStatuses(),
                'filter_type' => 'string',
                'callback' => 'displayStatus',
                'remove_onclick' => true
            ),
            'date_add' => array(
                'title' => 'Date création', 
                'filter_key' => 'a!date_add', 
                'align' => 'center',
                'type' => 'datetime',
                'remove_onclick' => true
            ),
        );
        
        $this->bulk_actions = array(
            'delete' => array(
                'text' => 'Supprimer sélectionnés',
                'confirm' => 'Supprimer les éléments sélectionnés ?',
                'icon' => 'icon-trash'
            ),
            'accept' => array(
                'text' => 'Accepter les réservations',
                'icon' => 'icon-check'
            ),
            'cancel' => array(
                'text' => 'Annuler les réservations',
                'icon' => 'icon-remove'
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
     * Afficher le statut avec couleur
     */
    public function displayStatus($value, $row)
    {
        $status_labels = BookerAuthReserved::getStatuses();
        $label = isset($status_labels[$value]) ? $status_labels[$value] : 'Inconnu';
        
        $class = '';
        switch ($value) {
            case 'pending':
                $class = 'label-warning';
                break;
            case 'confirmed':
                $class = 'label-info';
                break;
            case 'paid':
            case 'completed':
                $class = 'label-success';
                break;
            case 'cancelled':
            case 'refunded':
                $class = 'label-danger';
                break;
        }
        
        return '<span class="label ' . $class . '">' . $label . '</span>';
    }
    
    /**
     * Traitement des actions en lot
     */
    public function processBulkAccept()
    {
        $this->processBulkStatusChange('confirmed', 'Réservation(s) acceptée(s)');
    }
    
    public function processBulkCancel()
    {
        $this->processBulkStatusChange('cancelled', 'Réservation(s) annulée(s)');
    }
    
    private function processBulkStatusChange($new_status, $success_message)
    {
        $errors = 0;
        $success = 0;
        
        if (is_array($this->boxes) && !empty($this->boxes)) {
            foreach ($this->boxes as $id) {
                $reservation = new BookerAuthReserved((int)$id);
                if (Validate::isLoadedObject($reservation)) {
                    if ($reservation->changeStatus($new_status)) {
                        $success++;
                    } else {
                        $errors++;
                    }
                }
            }
        }
        
        if ($success > 0) {
            $this->confirmations[] = $success . ' ' . $success_message;
        }
        if ($errors > 0) {
            $this->errors[] = $errors . ' erreur(s) lors du changement de statut';
        }
    }
    
    public function renderForm()
    {
        $this->display = 'edit';
        $this->initToolbar();
        
        // Récupérer les bookers disponibles
        $bookers = Db::getInstance()->executeS('
            SELECT b.id, b.name
            FROM `' . _DB_PREFIX_ . 'booker` b
            WHERE b.active = 1
            ORDER BY b.name
        ');
        
        $booker_options = array();
        foreach ($bookers as $booker) {
            $booker_options[] = array(
                'id_booker' => $booker['id'],
                'name' => $booker['name'] ? $booker['name'] : 'Booker #' . $booker['id']
            );
        }
        
        $this->fields_form = array(
            'legend' => array(
                'title' => $this->module->l('Gérer la réservation'),
                'icon' => 'icon-calendar'
            ),
            'input' => array(
                array(
                    'type' => 'select',
                    'label' => 'Booker',
                    'id' => 'id_booker',
                    'name' => 'id_booker',
                    'required' => true,
                    'options' => array(
                        'query' => $booker_options,
                        'id' => 'id_booker',
                        'name' => 'name'
                    )
                ),
                array(
                    'type' => 'text',
                    'label' => 'Référence de réservation',
                    'name' => 'booking_reference',
                    'required' => true,
                    'hint' => 'Référence unique de la réservation'
                ),
                array(
                    'type' => 'text',
                    'label' => 'Prénom du client',
                    'name' => 'customer_firstname',
                    'required' => true,
                ),
                array(
                    'type' => 'text',
                    'label' => 'Nom du client',
                    'name' => 'customer_lastname',
                    'required' => true,
                ),
                array(
                    'type' => 'text',
                    'label' => 'Email du client',
                    'name' => 'customer_email',
                    'required' => true,
                ),
                array(
                    'type' => 'text',
                    'label' => 'Téléphone du client',
                    'name' => 'customer_phone',
                ),
                array(
                    'type' => 'datetime',
                    'label' => 'Date et heure de début',
                    'name' => 'date_start',
                    'required' => true,
                ),
                array(
                    'type' => 'datetime',
                    'label' => 'Date et heure de fin',
                    'name' => 'date_end',
                    'required' => true,
                ),
                array(
                    'type' => 'text',
                    'label' => 'Prix total',
                    'name' => 'total_price',
                    'suffix' => '€',
                    'class' => 'fixed-width-sm',
                ),
                array(
                    'type' => 'text',
                    'label' => 'Caution versée',
                    'name' => 'deposit_paid',
                    'suffix' => '€',
                    'class' => 'fixed-width-sm',
                ),
                array(
                    'type' => 'select',
                    'label' => 'Statut',
                    'name' => 'status',
                    'id' => 'status',
                    'required' => true,
                    'options' => array(
                        'query' => $this->getStatusOptions(),
                        'id' => 'value',
                        'name' => 'label'
                    ),
                    'desc' => 'Attention : changer vers "Confirmé" ou "Payé" vérifiera les conflits'
                ),
                array(
                    'type' => 'textarea',
                    'label' => 'Notes',
                    'name' => 'notes',
                    'rows' => 3,
                ),
                array(
                    'type' => 'textarea',
                    'label' => 'Notes administrateur',
                    'name' => 'admin_notes',
                    'rows' => 3,
                )
            ),
            'submit' => array('title' => 'Sauvegarder'),			
        );

        return parent::renderForm();
    }
    
    /**
     * Obtenir les options de statut
     */
    private function getStatusOptions()
    {
        $options = array();
        foreach (BookerAuthReserved::getStatuses() as $value => $label) {
            $options[] = array(
                'value' => $value,
                'label' => $label
            );
        }
        return $options;
    }
    
    /**
     * Validation avant sauvegarde
     */
    public function processAdd()
    {
        $object = new $this->className();
        $this->copyFromPost($object, $this->table);
        
        // Générer une référence unique si pas fournie
        if (empty($object->booking_reference)) {
            $object->booking_reference = 'BK' . date('Ymd') . '-' . Tools::passwdGen(6, 'NUMERIC');
        }
        
        // Ajouter les dates de création/modification
        $object->date_add = date('Y-m-d H:i:s');
        $object->date_upd = date('Y-m-d H:i:s');
        
        // Vérifier les conflits avant sauvegarde
        if (!$this->validateReservation($object)) {
            return false;
        }
        
        if ($object->add()) {
            $this->confirmations[] = 'Réservation créée avec succès';
            return true;
        } else {
            $this->errors[] = 'Erreur lors de la création de la réservation';
            return false;
        }
    }
    
    public function processUpdate()
    {
        $id = (int)Tools::getValue($this->identifier);
        $object = new $this->className($id);
        
        if (!Validate::isLoadedObject($object)) {
            $this->errors[] = 'Réservation introuvable';
            return false;
        }
        
        $this->copyFromPost($object, $this->table);
        $object->date_upd = date('Y-m-d H:i:s');
        
        // Vérifier les conflits avant sauvegarde
        if (!$this->validateReservation($object)) {
            return false;
        }
        
        if ($object->update()) {
            $this->confirmations[] = 'Réservation mise à jour avec succès';
            return true;
        } else {
            $this->errors[] = 'Erreur lors de la mise à jour de la réservation';
            return false;
        }
    }
    
    /**
     * Valider une réservation
     */
    private function validateReservation($reservation)
    {
        // Vérifier que les dates sont cohérentes
        if ($reservation->date_start >= $reservation->date_end) {
            $this->errors[] = 'La date de début doit être antérieure à la date de fin';
            return false;
        }
        
        // Vérifier les conflits pour les réservations confirmées/payées
        if (in_array($reservation->status, ['confirmed', 'paid', 'completed'])) {
            if ($reservation->hasConflict()) {
                $this->errors[] = 'Cette réservation entre en conflit avec une réservation existante';
                return false;
            }
        }
        
        return true;
    }
    
    public function initPageHeaderToolbar()
    {
        $this->page_header_toolbar_btn['new'] = array(
            'href' => self::$currentIndex . '&add' . $this->table . '&token=' . $this->token,
            'desc' => 'Ajouter une nouvelle réservation',
            'icon' => 'process-icon-new'
        );
        
        // Bouton pour nettoyer les réservations expirées
        $this->page_header_toolbar_btn['clean'] = array(
            'href' => self::$currentIndex . '&cleanExpired&token=' . $this->token,
            'desc' => 'Nettoyer les réservations expirées',
            'icon' => 'process-icon-eraser',
            'js' => 'return confirm(\'Êtes-vous sûr de vouloir marquer comme expirées toutes les demandes de réservation de plus de 24h ?\')'
        );
        
        parent::initPageHeaderToolbar();
    }
    
    /**
     * Nettoyer les réservations expirées
     */
    public function processCleanExpired()
    {
        if (BookerAuthReserved::cancelExpiredReservations()) {
            $this->confirmations[] = 'Réservations expirées nettoyées avec succès';
        } else {
            $this->errors[] = 'Erreur lors du nettoyage des réservations expirées';
        }
    }
    
    public function renderList()
    {
        // Ajouter les statistiques en haut de la liste
        $stats = $this->getReservationStats();
        $this->context->smarty->assign('reservation_stats', $stats);
        
        $list = parent::renderList();
        
        $this->context->smarty->assign(array(		  
            'ajaxUrl' => $this->context->link->getAdminLink('AdminBookerAuthReserved')
        ));
        
        $stats_html = $this->context->smarty->fetch($this->getTemplatePath().'reservation_stats.tpl');
        $content = $this->context->smarty->fetch($this->getTemplatePath().'ajax.tpl');
        
        return $stats_html . $list . $content;
    }
    
    /**
     * Obtenir les statistiques des réservations
     */
    private function getReservationStats()
    {
        $stats = array();
        $statuses = BookerAuthReserved::getStatuses();
        
        foreach ($statuses as $status_id => $status_label) {
            $count = Db::getInstance()->getValue('
                SELECT COUNT(*) 
                FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` 
                WHERE `status` = "' . pSQL($status_id) . '"
            ');
            $stats[] = array(
                'label' => $status_label,
                'count' => (int)$count,
                'status_id' => $status_id
            );
        }
        
        return $stats;
    }
}
