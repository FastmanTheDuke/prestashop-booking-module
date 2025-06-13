<?php
require_once (dirname(__FILE__). '/../../classes/Booker.php');

class AdminBookerController extends ModuleAdminController
{
    protected $_module = NULL;
    public $controller_type='admin';   
    protected $position_identifier = 'id_booker';

    public function __construct()
    {
        $this->display = 'options';
        $this->context = Context::getContext();
        $this->bootstrap = true;
        $this->table = 'booker';
        $this->identifier = 'id_booker';
        $this->className = 'Booker';
        $this->_defaultOrderBy = 'id_booker';
        $this->_defaultOrderWay = 'DESC';
        $this->lang = true;
        $this->allow_export = true;
		
        $this->fields_list = array(
            'id_booker' => array(
                'title' => 'ID', 
                'filter_key' => 'a!id_booker', 
                'align' => 'center', 
                'width' => 25,
                'class' => 'fixed-width-xs',
                'remove_onclick' => true
            ),            
            'name' => array(
                'title' => 'Nom', 
                'width' => '300', 
                'filter_key' => 'a!name',
                'remove_onclick' => true
            ),
            'description' => array(
                'title' => 'Description', 
                'width' => '300',
                'lang' => true,
                'remove_onclick' => true
            ),				
            'google_account' => array(
                'title' => 'Compte Google', 
                'width' => '200',
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
    
    public function renderForm()
    {
        $this->display = 'edit';
        $this->initToolbar();
		
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
                ),
                array(
                    'type' => 'textarea',
                    'label' => 'Description (multilingue)',
                    'name' => 'description',
                    'cols' => 60,
                    'required' => false,
                    'lang' => true,
                    'rows' => 10,
                    'class' => 'rte',
                    'autoload_rte' => true,
                    'hint' => 'Description détaillée de l\'élément à réserver',
                ),
                array(
                    'type' => 'text',
                    'label' => 'Compte Google (email)',
                    'name' => 'google_account',
                    'id' => 'google_account',
                    'size' => 50,
                    'desc' => 'Email Google associé pour la gestion du calendrier'
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
     * Ajout d'un booker
     */
    public function processAdd()
    {
        $object = new $this->className();
        $this->copyFromPost($object, $this->table);
        
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
        
        parent::initPageHeaderToolbar();
    }
    
    public function renderList()
    {
        $list = parent::renderList();	
        $this->context->smarty->assign(array(		  
            'ajaxUrl' => $this->context->link->getAdminLink('AdminBooker')
        ));
        $content = $this->context->smarty->fetch($this->getTemplatePath().'ajax.tpl');
        return $list . $content;
    }
}