<?php
require_once (dirname(__FILE__). '/../../classes/BookerAuthReserved.php');

class AdminBookerAuthReservedController extends ModuleAdminController
{
    protected $_module = NULL;
    public $controller_type='admin';   
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
        
        $this->fields_list = array(
            'id_reserved' => array(
                'title' => 'ID', 
                'filter_key' => 'a!id_reserved', 
                'align' => 'center',
                'class' => 'fixed-width-xs',
                'remove_onclick' => true
            ),       
            'id_booker' => array(
                'title' => 'Booker', 
                'filter_key' => 'a!id_booker', 
                'align' => 'center',
                'remove_onclick' => true
            ),           
            'date_reserved' => array(
                'title' => 'Date début', 
                'filter_key' => 'a!date_reserved', 
                'align' => 'center',
                'type' => 'date',
                'remove_onclick' => true
            ),
            'date_to' => array(
                'title' => 'Date fin', 
                'filter_key' => 'a!date_to', 
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
            'status' => array(
                'title' => 'Statut', 
                'filter_key' => 'a!status', 
                'align' => 'center',
                'type' => 'select',
                'list' => BookerAuthReserved::getStatuses(),
                'filter_type' => 'int',
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
            'active' => array(
                'title' => 'Actif', 
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
        switch ((int)$value) {
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
        
        return '<span class="label ' . $class . '">' . $label . '</span>';
    }
    
    /**
     * Traitement des actions en lot
     */
    public function processBulkAccept()
    {
        $this->processBulkStatusChange(BookerAuthReserved::STATUS_ACCEPTED, 'Réservation(s) acceptée(s)');
    }
    
    public function processBulkCancel()
    {
        $this->processBulkStatusChange(BookerAuthReserved::STATUS_CANCELLED, 'Réservation(s) annulée(s)');
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
            SELECT b.id_booker, b.name
            FROM `' . _DB_PREFIX_ . 'booker` b
            LEFT JOIN `' . _DB_PREFIX_ . 'booker_lang` bl ON (b.id_booker = bl.id_booker AND bl.id_lang = ' . (int)$this->context->language->id . ')
            WHERE b.active = 1
            ORDER BY b.name
        ');
        
        $booker_options = array();
        foreach ($bookers as $booker) {
            $booker_options[] = array(
                'id_booker' => $booker['id_booker'],
                'name' => $booker['name'] ? $booker['name'] : 'Booker #' . $booker['id_booker']
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
                    'type' => 'date',
                    'label' => 'Date de début',
                    'name' => 'date_reserved',
                    'id' => 'date_reserved', 
                    'required' => true,
                ),
                array(
                    'type' => 'date',
                    'label' => 'Date de fin (optionnel)',
                    'name' => 'date_to',
                    'id' => 'date_to',
                    'desc' => 'Laissez vide pour une réservation d\'une seule journée'
                ),
                array(
                    'type' => 'select',
                    'label' => 'Heure de début',
                    'name' => 'hour_from',
                    'id' => 'hour_from', 
                    'required' => true,
                    'options' => array(
                        'query' => $this->getHourOptions(),
                        'id' => 'value',
                        'name' => 'label'
                    )
                ),
                array(
                    'type' => 'select',
                    'label' => 'Heure de fin',
                    'name' => 'hour_to',
                    'id' => 'hour_to', 
                    'required' => true,
                    'options' => array(
                        'query' => $this->getHourOptions(),
                        'id' => 'value',
                        'name' => 'label'
                    )
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
                    'desc' => 'Attention : changer vers "Accepté" ou "Payé" vérifiera les conflits'
                ),
                array(
                    'type' => 'switch',
                    'label' => 'Actif',
                    'name' => 'active',
                    'required' => false,
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => 'Oui'
                        ), 
                        array(
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => 'Non'
                        )
                    ),
                )
            ),
            'submit' => array('title' => 'Sauvegarder'),			
        );

        return parent::renderForm();
    }
    
    /**
     * Obtenir les options d'heures
     */
    private function getHourOptions()
    {
        $hours = array();
        for ($i = 0; $i <= 23; $i++) {
            $hours[] = array(
                'value' => $i,
                'label' => sprintf('%02d:00', $i)
            );
        }
        return $hours;
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
        // Vérifier que les heures sont cohérentes
        if ($reservation->hour_from >= $reservation->hour_to) {
            $this->errors[] = 'L\'heure de début doit être inférieure à l\'heure de fin';
            return false;
        }
        
        // Vérifier que les dates sont cohérentes
        if ($reservation->date_to && $reservation->date_to != '0000-00-00' && $reservation->date_reserved > $reservation->date_to) {
            $this->errors[] = 'La date de début doit être inférieure ou égale à la date de fin';
            return false;
        }
        
        // Vérifier les conflits pour les réservations acceptées/payées
        if (in_array($reservation->status, [BookerAuthReserved::STATUS_ACCEPTED, BookerAuthReserved::STATUS_PAID])) {
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
                WHERE `status` = ' . (int)$status_id . ' 
                AND `active` = 1
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