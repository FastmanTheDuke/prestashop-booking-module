<?php
/**
 * AdminBookerAuthController - Gestion des disponibilités de réservations
 * Version 2.1.5 - Avec gestion avancée des créneaux et récurrence
 */

require_once _PS_MODULE_DIR_ . 'booking/classes/BookerAuth.php';
require_once _PS_MODULE_DIR_ . 'booking/classes/Booker.php';

class AdminBookerAuthController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'booker_auth';
        $this->className = 'BookerAuth';
        $this->identifier = 'id_auth';
        $this->lang = false;
        
        parent::__construct();
        
        $this->addRowAction('edit');
        $this->addRowAction('delete');
        $this->addRowAction('duplicate');
        $this->addRowAction('view_reservations');
        
        $this->bulk_actions = array(
            'delete' => array(
                'text' => $this->l('Delete selected'),
                'confirm' => $this->l('Delete selected availabilities?'),
                'icon' => 'icon-trash'
            ),
            'activate' => array(
                'text' => $this->l('Enable selection'),
                'icon' => 'icon-power-off text-success'
            ),
            'deactivate' => array(
                'text' => $this->l('Disable selection'),
                'icon' => 'icon-power-off text-danger'
            ),
            'bulk_recurring' => array(
                'text' => $this->l('Create recurring'),
                'icon' => 'icon-repeat'
            )
        );
        
        $this->fields_list = array(
            'id_auth' => array(
                'title' => $this->l('ID'),
                'align' => 'center',
                'class' => 'fixed-width-xs'
            ),
            'booker_name' => array(
                'title' => $this->l('Item'),
                'width' => 200,
                'filter_key' => 'b!name',
                'orderby' => false
            ),
            'date_from' => array(
                'title' => $this->l('Date From'),
                'width' => 120,
                'type' => 'date',
                'align' => 'center'
            ),
            'date_to' => array(
                'title' => $this->l('Date To'),
                'width' => 120,
                'type' => 'date',
                'align' => 'center'
            ),
            'time_from' => array(
                'title' => $this->l('Time From'),
                'width' => 100,
                'align' => 'center'
            ),
            'time_to' => array(
                'title' => $this->l('Time To'),
                'width' => 100,
                'align' => 'center'
            ),
            'max_bookings' => array(
                'title' => $this->l('Max Bookings'),
                'width' => 100,
                'align' => 'center'
            ),
            'current_bookings' => array(
                'title' => $this->l('Current'),
                'width' => 80,
                'align' => 'center',
                'color' => 'current_bookings',
                'callback' => 'formatCurrentBookings'
            ),
            'availability_rate' => array(
                'title' => $this->l('Availability'),
                'width' => 100,
                'align' => 'center',
                'orderby' => false,
                'search' => false,
                'callback' => 'formatAvailabilityRate'
            ),
            'price_override' => array(
                'title' => $this->l('Custom Price'),
                'width' => 120,
                'type' => 'price',
                'align' => 'text-right'
            ),
            'recurring' => array(
                'title' => $this->l('Recurring'),
                'width' => 80,
                'align' => 'center',
                'type' => 'bool',
                'callback' => 'formatRecurring'
            ),
            'active' => array(
                'title' => $this->l('Status'),
                'width' => 70,
                'align' => 'center',
                'active' => 'status',
                'type' => 'bool'
            )
        );
        
        $this->_select = 'b.name as booker_name';
        $this->_join = 'LEFT JOIN `' . _DB_PREFIX_ . 'booker` b ON (a.id_booker = b.id_booker)';
        $this->_where = 'AND b.active = 1';
        
        $this->shopLinkType = 'shop';
        $this->multishop_context = Shop::CONTEXT_ALL;
    }

    /**
     * Override de la liste pour calculer des données supplémentaires
     */
    public function getList($id_lang, $order_by = null, $order_way = null, $start = 0, $limit = null, $id_lang_shop = false)
    {
        parent::getList($id_lang, $order_by, $order_way, $start, $limit, $id_lang_shop);
        
        if (!empty($this->_list)) {
            foreach ($this->_list as &$row) {
                // Calculer le taux d'occupation
                $max = (int)$row['max_bookings'];
                $current = (int)$row['current_bookings'];
                $row['availability_rate'] = $max > 0 ? round((($max - $current) / $max) * 100, 1) : 0;
            }
        }
    }

    /**
     * Formatter pour les réservations actuelles avec couleur
     */
    public static function formatCurrentBookings($current, $row)
    {
        $max = (int)$row['max_bookings'];
        $current = (int)$current;
        
        $color = 'success';
        if ($current >= $max) {
            $color = 'danger';
        } elseif ($current > ($max * 0.8)) {
            $color = 'warning';
        }
        
        return '<span class="badge badge-' . $color . '">' . $current . '/' . $max . '</span>';
    }

    /**
     * Formatter pour le taux de disponibilité
     */
    public static function formatAvailabilityRate($rate, $row)
    {
        $rate = (float)$rate;
        
        $color = 'success';
        if ($rate < 20) {
            $color = 'danger';
        } elseif ($rate < 50) {
            $color = 'warning';
        }
        
        return '<span class="badge badge-' . $color . '">' . $rate . '%</span>';
    }

    /**
     * Formatter pour la récurrence
     */
    public static function formatRecurring($recurring, $row)
    {
        if (!$recurring) {
            return '<span class="badge badge-default">No</span>';
        }
        
        $type = strtoupper(substr($row['recurring_type'], 0, 1));
        return '<span class="badge badge-info" title="' . ucfirst($row['recurring_type']) . '">' . $type . '</span>';
    }

    /**
     * Formulaire d'ajout/édition
     */
    public function renderForm()
    {
        $this->fields_form = array(
            'legend' => array(
                'title' => $this->l('Availability Slot'),
                'icon' => 'icon-time'
            ),
            'input' => array(
                array(
                    'type' => 'select',
                    'label' => $this->l('Bookable Item'),
                    'name' => 'id_booker',
                    'required' => true,
                    'options' => array(
                        'query' => $this->getBookersList(),
                        'id' => 'id_booker',
                        'name' => 'name'
                    ),
                    'desc' => $this->l('Select the item for this availability slot')
                ),
                // Dates et heures
                array(
                    'type' => 'date',
                    'label' => $this->l('Date From'),
                    'name' => 'date_from',
                    'required' => true,
                    'desc' => $this->l('Start date for availability')
                ),
                array(
                    'type' => 'date',
                    'label' => $this->l('Date To'),
                    'name' => 'date_to',
                    'required' => true,
                    'desc' => $this->l('End date for availability (can be same as start date)')
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Time From'),
                    'name' => 'time_from',
                    'required' => true,
                    'class' => 'fixed-width-sm',
                    'placeholder' => '09:00',
                    'desc' => $this->l('Start time (format: HH:MM)')
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Time To'),
                    'name' => 'time_to',
                    'required' => true,
                    'class' => 'fixed-width-sm',
                    'placeholder' => '18:00',
                    'desc' => $this->l('End time (format: HH:MM)')
                ),
                // Capacité
                array(
                    'type' => 'text',
                    'label' => $this->l('Maximum Bookings'),
                    'name' => 'max_bookings',
                    'required' => true,
                    'class' => 'fixed-width-sm',
                    'desc' => $this->l('Maximum number of simultaneous bookings for this slot')
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Custom Price'),
                    'name' => 'price_override',
                    'class' => 'fixed-width-sm',
                    'suffix' => $this->context->currency->sign,
                    'desc' => $this->l('Override the default item price for this slot (leave empty to use default)')
                ),
                // Récurrence
                array(
                    'type' => 'switch',
                    'label' => $this->l('Recurring'),
                    'name' => 'recurring',
                    'values' => array(
                        array('id' => 'recurring_on', 'value' => 1, 'label' => $this->l('Yes')),
                        array('id' => 'recurring_off', 'value' => 0, 'label' => $this->l('No'))
                    ),
                    'desc' => $this->l('Create this slot repeatedly')
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('Recurring Type'),
                    'name' => 'recurring_type',
                    'options' => array(
                        'query' => array(
                            array('key' => '', 'name' => $this->l('-- Select --')),
                            array('key' => 'daily', 'name' => $this->l('Daily')),
                            array('key' => 'weekly', 'name' => $this->l('Weekly')),
                            array('key' => 'monthly', 'name' => $this->l('Monthly'))
                        ),
                        'id' => 'key',
                        'name' => 'name'
                    ),
                    'desc' => $this->l('How often to repeat this slot')
                ),
                array(
                    'type' => 'date',
                    'label' => $this->l('Recurring End Date'),
                    'name' => 'recurring_end',
                    'desc' => $this->l('Until when to create recurring slots')
                ),
                array(
                    'type' => 'textarea',
                    'label' => $this->l('Notes'),
                    'name' => 'notes',
                    'rows' => 3,
                    'cols' => 40,
                    'desc' => $this->l('Internal notes for this availability slot')
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

        // Ajouter le JavaScript pour la gestion de la récurrence
        $this->addJS(_MODULE_DIR_ . 'booking/views/js/admin-availability.js');

        if (!($obj = $this->loadObject(true))) {
            return;
        }

        return parent::renderForm();
    }

    /**
     * Récupérer la liste des bookers actifs
     */
    private function getBookersList()
    {
        $sql = 'SELECT id_booker, name
                FROM ' . _DB_PREFIX_ . 'booker
                WHERE active = 1
                ORDER BY name';
        
        return Db::getInstance()->executeS($sql);
    }

    /**
     * Traitement après sauvegarde
     */
    public function postProcess()
    {
        if (Tools::isSubmit('submitAdd' . $this->table)) {
            $recurring = (bool)Tools::getValue('recurring');
            
            if ($recurring && Tools::getValue('recurring_type')) {
                $this->createRecurringSlots();
            }
        }
        
        return parent::postProcess();
    }

    /**
     * Créer des créneaux récurrents
     */
    private function createRecurringSlots()
    {
        $id_booker = (int)Tools::getValue('id_booker');
        $date_from = Tools::getValue('date_from');
        $date_to = Tools::getValue('date_to');
        $time_from = Tools::getValue('time_from');
        $time_to = Tools::getValue('time_to');
        $max_bookings = (int)Tools::getValue('max_bookings');
        $price_override = Tools::getValue('price_override');
        $recurring_type = Tools::getValue('recurring_type');
        $recurring_end = Tools::getValue('recurring_end');
        $notes = Tools::getValue('notes');
        
        $start_date = new DateTime($date_from);
        $end_recurring = new DateTime($recurring_end);
        $created_count = 0;
        
        // Déterminer l'intervalle selon le type
        switch ($recurring_type) {
            case 'daily':
                $interval = new DateInterval('P1D');
                break;
            case 'weekly':
                $interval = new DateInterval('P1W');
                break;
            case 'monthly':
                $interval = new DateInterval('P1M');
                break;
            default:
                return;
        }
        
        // Créer les créneaux récurrents
        $current_date = clone $start_date;
        while ($current_date <= $end_recurring && $created_count < 365) { // Limite de sécurité
            // Calculer la date de fin pour ce créneau
            $slot_end_date = clone $current_date;
            if ($date_from != $date_to) {
                $days_diff = (new DateTime($date_to))->diff(new DateTime($date_from))->days;
                $slot_end_date->add(new DateInterval('P' . $days_diff . 'D'));
            }
            
            $data = array(
                'id_booker' => $id_booker,
                'date_from' => $current_date->format('Y-m-d') . ' ' . $time_from . ':00',
                'date_to' => $slot_end_date->format('Y-m-d') . ' ' . $time_to . ':00',
                'time_from' => $time_from,
                'time_to' => $time_to,
                'max_bookings' => $max_bookings,
                'current_bookings' => 0,
                'price_override' => $price_override ? (float)$price_override : null,
                'active' => 1,
                'recurring' => 1,
                'recurring_type' => $recurring_type,
                'recurring_end' => $recurring_end,
                'notes' => pSQL($notes),
                'date_add' => date('Y-m-d H:i:s'),
                'date_upd' => date('Y-m-d H:i:s')
            );
            
            Db::getInstance()->insert('booker_auth', $data);
            $created_count++;
            
            $current_date->add($interval);
        }
        
        $this->confirmations[] = sprintf($this->l('%d recurring slots created successfully'), $created_count);
    }

    /**
     * Action personnalisée : voir les réservations
     */
    public function displayViewReservationsLink($token, $id, $name = null)
    {
        return '<a class="btn btn-default" href="' . self::$currentIndex . '&configure=booking&token=' . $token . '&view_slot_reservations=' . $id . '">
                    <i class="icon-list"></i> ' . $this->l('Reservations') . '
                </a>';
    }

    /**
     * Action personnalisée : dupliquer
     */
    public function displayDuplicateLink($token, $id, $name = null)
    {
        return '<a class="btn btn-default" href="' . self::$currentIndex . '&configure=booking&token=' . $token . '&duplicate_slot=' . $id . '">
                    <i class="icon-copy"></i> ' . $this->l('Duplicate') . '
                </a>';
    }

    /**
     * Traitement de la duplication de créneau
     */
    public function processDuplicateSlot()
    {
        if ($id_auth = (int)Tools::getValue('duplicate_slot')) {
            $auth = new BookerAuth($id_auth);
            if (Validate::isLoadedObject($auth)) {
                $new_auth = clone $auth;
                $new_auth->id_auth = null;
                $new_auth->current_bookings = 0;
                $new_auth->active = 0; // Désactiver la copie par défaut
                
                if ($new_auth->add()) {
                    $this->confirmations[] = $this->l('Availability slot duplicated successfully');
                } else {
                    $this->errors[] = $this->l('Error occurred during duplication');
                }
            }
        }
    }

    /**
     * Traitement en lot pour la récurrence
     */
    public function processBulkRecurring()
    {
        $selection = Tools::getValue($this->table . 'Box');
        
        if (is_array($selection) && count($selection)) {
            foreach ($selection as $id_auth) {
                // Logique pour transformer en récurrent
                $this->convertToRecurring((int)$id_auth);
            }
            
            $this->confirmations[] = sprintf($this->l('%d slots converted to recurring'), count($selection));
        }
    }

    /**
     * Convertir un créneau en récurrent
     */
    private function convertToRecurring($id_auth)
    {
        return Db::getInstance()->update(
            'booker_auth',
            array(
                'recurring' => 1,
                'recurring_type' => 'weekly',
                'recurring_end' => date('Y-m-d', strtotime('+3 months'))
            ),
            'id_auth = ' . (int)$id_auth
        );
    }

    /**
     * Ajouter du CSS et JS personnalisés
     */
    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);
        
        $this->addCSS(_MODULE_DIR_ . 'booking/views/css/admin-availability.css');
        $this->addJS(_MODULE_DIR_ . 'booking/views/js/admin-availability.js');
        
        // Ajouter les librairies de calendrier
        $this->addJS('https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js');
    }

    /**
     * Toolbar avec actions spéciales
     */
    public function initToolbar()
    {
        parent::initToolbar();
        
        $this->toolbar_btn['bulk_create'] = array(
            'href' => self::$currentIndex . '&configure=booking&token=' . $this->token . '&bulk_create=1',
            'desc' => $this->l('Bulk Create Slots'),
            'icon' => 'process-icon-new'
        );
        
        $this->toolbar_btn['calendar_view'] = array(
            'href' => self::$currentIndex . '&configure=booking&token=' . $this->token . '&calendar_view=1',
            'desc' => $this->l('Calendar View'),
            'icon' => 'process-icon-calendar'
        );
    }
}
