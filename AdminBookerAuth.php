<?php
require_once (dirname(__FILE__). '/../../classes/BookerAuth.php');
require_once (dirname(__FILE__). '/../../classes/Booker.php');

class AdminBookerAuthController extends ModuleAdminController
{
    protected $_module = NULL;
    public $controller_type='admin';   
    protected $position_identifier = 'id_auth';

    public function __construct()
    {
        $this->display = 'options';
        $this->context = Context::getContext();
        $this->bootstrap = true;
        $this->table = 'booker_auth';
        $this->identifier = 'id_auth';
        $this->className = 'BookerAuth';
        $this->_defaultOrderBy = 'date_from';
        $this->_defaultOrderWay = 'DESC';
        $this->allow_export = true;
        
        $this->fields_list = array(
            'id_auth' => array(
                'title' => 'ID', 
                'filter_key' => 'a!id_auth', 
                'align' => 'center',
                'class' => 'fixed-width-xs',
                'remove_onclick' => true
            ),       
            'booker_name' => array(
                'title' => 'Booker', 
                'filter_key' => 'b!name',
                'remove_onclick' => true
            ),
            'date_from' => array(
                'title' => 'Date début', 
                'filter_key' => 'a!date_from', 
                'align' => 'center',
                'type' => 'datetime',
                'remove_onclick' => true
            ),
            'date_to' => array(
                'title' => 'Date fin', 
                'filter_key' => 'a!date_to', 
                'align' => 'center',
                'type' => 'datetime',
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
            )
        );
        
        $this->has_bulk_actions = true;
        $this->shopLinkType = '';
        $this->no_link = false;
        $this->simple_header = false;
        $this->actions = array('edit', 'delete', 'view');
        $this->list_no_link = false;
        
        // Jointure pour récupérer le nom du booker
        $this->_join = 'LEFT JOIN `' . _DB_PREFIX_ . 'booker` b ON (a.id_booker = b.id_booker)';
        $this->_select = 'b.name as booker_name';
        
        parent::__construct();
    }
    
    /**
     * Récupérer la liste des bookers disponibles
     */
    private function getBookerOptions()
    {
        $bookers = Db::getInstance()->executeS('
            SELECT b.id_booker, b.name
            FROM `' . _DB_PREFIX_ . 'booker` b
            WHERE b.active = 1
            ORDER BY b.name ASC
        ');
        
        $options = array();
        if ($bookers) {
            foreach ($bookers as $booker) {
                $options[] = array(
                    'id_booker' => $booker['id_booker'],
                    'name' => $booker['name']
                );
            }
        }
        
        return $options;
    }
    
    public function renderForm()
    {
        $this->display = 'edit';
        $this->initToolbar();
        
        // Récupérer les bookers disponibles
        $booker_options = $this->getBookerOptions();
        
        if (empty($booker_options)) {
            $this->errors[] = 'Aucun booker disponible. Créez d\'abord un booker dans "Éléments à réserver".';
            return false;
        }
        
        $this->fields_form = array(
            'legend' => array(
                'title' => $this->module->l('Gérer les disponibilités'),
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
                    ),
                    'desc' => 'Sélectionner l\'élément pour lequel définir les disponibilités'
                ),
                array(
                    'type' => 'datetime',
                    'label' => 'Date et heure de début',
                    'name' => 'date_from',
                    'id' => 'date_from', 
                    'required' => true,
                    'desc' => 'Date et heure à partir de laquelle l\'élément est disponible'
                ),
                array(
                    'type' => 'datetime',
                    'label' => 'Date et heure de fin',
                    'name' => 'date_to',
                    'id' => 'date_to', 
                    'required' => true,
                    'desc' => 'Date et heure jusqu\'à laquelle l\'élément est disponible'
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
     * Validation avant sauvegarde
     */
    private function validateAvailability($availability)
    {
        // Vérifier que la date de début est antérieure à la date de fin
        if ($availability->date_from >= $availability->date_to) {
            $this->errors[] = 'La date de début doit être antérieure à la date de fin';
            return false;
        }
        
        // Vérifier que le booker existe
        $booker = new Booker($availability->id_booker);
        if (!Validate::isLoadedObject($booker)) {
            $this->errors[] = 'Le booker sélectionné n\'existe pas';
            return false;
        }
        
        // Vérifier les chevauchements avec d'autres disponibilités du même booker
        $sql = 'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth` 
                WHERE `id_booker` = ' . (int)$availability->id_booker . '
                AND `id_auth` != ' . (int)$availability->id . '
                AND `active` = 1
                AND (
                    (`date_from` <= "' . pSQL($availability->date_from) . '" AND `date_to` > "' . pSQL($availability->date_from) . '")
                    OR (`date_from` < "' . pSQL($availability->date_to) . '" AND `date_to` >= "' . pSQL($availability->date_to) . '")
                    OR (`date_from` >= "' . pSQL($availability->date_from) . '" AND `date_to` <= "' . pSQL($availability->date_to) . '")
                )';
        
        if (Db::getInstance()->getValue($sql)) {
            $this->errors[] = 'Cette période de disponibilité chevauche avec une autre période existante pour ce booker';
            return false;
        }
        
        return true;
    }
    
    /**
     * Ajout d'une disponibilité
     */
    public function processAdd()
    {
        $object = new $this->className();
        $this->copyFromPost($object, $this->table);
        
        // Ajouter les dates de création/modification
        $object->date_add = date('Y-m-d H:i:s');
        $object->date_upd = date('Y-m-d H:i:s');
        
        // Valider la disponibilité
        if (!$this->validateAvailability($object)) {
            return false;
        }
        
        if ($object->add()) {
            $this->confirmations[] = 'Disponibilité créée avec succès';
            return true;
        } else {
            $this->errors[] = 'Erreur lors de la création de la disponibilité';
            return false;
        }
    }
    
    /**
     * Mise à jour d'une disponibilité
     */
    public function processUpdate()
    {
        $id = (int)Tools::getValue($this->identifier);
        $object = new $this->className($id);
        
        if (!Validate::isLoadedObject($object)) {
            $this->errors[] = 'Disponibilité introuvable';
            return false;
        }
        
        $this->copyFromPost($object, $this->table);
        $object->date_upd = date('Y-m-d H:i:s');
        
        // Valider la disponibilité
        if (!$this->validateAvailability($object)) {
            return false;
        }
        
        if ($object->update()) {
            $this->confirmations[] = 'Disponibilité mise à jour avec succès';
            return true;
        } else {
            $this->errors[] = 'Erreur lors de la mise à jour de la disponibilité';
            return false;
        }
    }
    
    public function initPageHeaderToolbar()
    {
        // Vérifier s'il y a des bookers disponibles
        $booker_count = Db::getInstance()->getValue('
            SELECT COUNT(*) 
            FROM `' . _DB_PREFIX_ . 'booker` 
            WHERE active = 1
        ');
        
        if ($booker_count > 0) {
            $this->page_header_toolbar_btn['new'] = array(
                'href' => self::$currentIndex . '&add' . $this->table . '&token=' . $this->token,
                'desc' => 'Ajouter une nouvelle disponibilité',
                'icon' => 'process-icon-new'
            );
        } else {
            $this->page_header_toolbar_btn['add_booker'] = array(
                'href' => $this->context->link->getAdminLink('AdminBooker') . '&add' . 'booker',
                'desc' => 'Créer d\'abord un booker',
                'icon' => 'process-icon-new'
            );
        }
        
        parent::initPageHeaderToolbar();
    }
    
    public function renderList()
    {
        // Vérifier s'il y a des bookers
        $booker_count = Db::getInstance()->getValue('
            SELECT COUNT(*) 
            FROM `' . _DB_PREFIX_ . 'booker` 
            WHERE active = 1
        ');
        
        if ($booker_count == 0) {
            $this->context->smarty->assign('warning_no_booker', true);
        }
        
        $list = parent::renderList();
        
        $this->context->smarty->assign(array(		  
            'ajaxUrl' => $this->context->link->getAdminLink('AdminBookerAuth'),
            'booker_admin_link' => $this->context->link->getAdminLink('AdminBooker')
        ));
        
        $warning = '';
        if ($booker_count == 0) {
            $warning = '<div class="alert alert-warning">
                <strong>Attention :</strong> Aucun booker n\'est disponible. 
                <a href="' . $this->context->link->getAdminLink('AdminBooker') . '" class="btn btn-primary btn-sm">
                    Créer un booker
                </a>
            </div>';
        }
        
        $content = $this->context->smarty->fetch($this->getTemplatePath().'ajax.tpl');
        
        return $warning . $list . $content;
    }
}