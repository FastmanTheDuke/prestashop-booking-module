<?php
/**
 * AdminBookerViewController - Double calendrier interactif pour disponibilités et réservations
 * Version 2.1.5 - Avec multiselect et actions en lot
 */

require_once _PS_MODULE_DIR_ . 'booking/classes/Booker.php';
require_once _PS_MODULE_DIR_ . 'booking/classes/BookerAuth.php';
require_once _PS_MODULE_DIR_ . 'booking/classes/BookerAuthReserved.php';

class AdminBookerViewController extends ModuleAdminController
{
    public $calendar_type = 'availability'; // 'availability' ou 'reservations'
    
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'booker';
        $this->className = 'Booker';
        $this->identifier = 'id_booker';
        $this->lang = false;
        
        parent::__construct();
        
        // Déterminer le type de calendrier
        $this->calendar_type = Tools::getValue('calendar_type', 'availability');
        
        $this->meta_title = $this->calendar_type == 'availability' ? 
                           $this->l('Availability Calendar') : 
                           $this->l('Reservations Calendar');
    }

    /**
     * Affichage principal du calendrier
     */
    public function renderView()
    {
        $calendar_type = $this->calendar_type;
        $bookers = $this->getActiveBookers();
        $selected_booker = (int)Tools::getValue('id_booker', 0);
        
        // Configuration du calendrier
        $calendar_config = array(
            'calendar_type' => $calendar_type,
            'selected_booker' => $selected_booker,
            'ajax_url' => $this->context->link->getAdminLink('AdminBookerView'),
            'token' => $this->token,
            'multiselect_enabled' => true,
            'drag_and_drop' => $calendar_type == 'availability',
            'min_time' => Configuration::get('BOOKING_CALENDAR_MIN_TIME', '08:00'),
            'max_time' => Configuration::get('BOOKING_CALENDAR_MAX_TIME', '20:00'),
            'slot_duration' => Configuration::get('BOOKING_SLOT_DURATION', '00:30:00'),
            'first_day' => Configuration::get('BOOKING_CALENDAR_FIRST_DAY', 1),
            'week_numbers' => Configuration::get('BOOKING_CALENDAR_WEEK_NUMBERS', 0)
        );
        
        $this->context->smarty->assign(array(
            'calendar_type' => $calendar_type,
            'bookers_list' => $bookers,
            'selected_booker' => $selected_booker,
            'calendar_config' => $calendar_config,
            'ajax_url' => $this->context->link->getAdminLink('AdminBookerView'),
            'token' => $this->token,
            'multiselect_actions' => $this->getMultiselectActions(),
            'calendar_views' => $this->getCalendarViews(),
            'current_date' => date('Y-m-d'),
            'module_dir' => _MODULE_DIR_ . 'booking/'
        ));
        
        return $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'booking/views/templates/admin/calendar_view.tpl');
    }

    /**
     * Actions AJAX pour le calendrier
     */
    public function ajaxProcess()
    {
        $action = Tools::getValue('ajax_action');
        
        switch ($action) {
            case 'get_events':
                $this->ajaxGetEvents();
                break;
                
            case 'create_availability':
                $this->ajaxCreateAvailability();
                break;
                
            case 'update_availability':
                $this->ajaxUpdateAvailability();
                break;
                
            case 'delete_availability':
                $this->ajaxDeleteAvailability();
                break;
                
            case 'bulk_availability':
                $this->ajaxBulkAvailability();
                break;
                
            case 'update_reservation':
                $this->ajaxUpdateReservation();
                break;
                
            case 'bulk_reservations':
                $this->ajaxBulkReservations();
                break;
                
            case 'get_booker_info':
                $this->ajaxGetBookerInfo();
                break;
                
            default:
                $this->ajaxDie(json_encode(array('error' => 'Unknown action')));
        }
    }

    /**
     * Récupérer les événements pour le calendrier
     */
    private function ajaxGetEvents()
    {
        $calendar_type = Tools::getValue('calendar_type', 'availability');
        $id_booker = (int)Tools::getValue('id_booker', 0);
        $start = Tools::getValue('start');
        $end = Tools::getValue('end');
        
        if ($calendar_type == 'availability') {
            $events = $this->getAvailabilityEvents($id_booker, $start, $end);
        } else {
            $events = $this->getReservationEvents($id_booker, $start, $end);
        }
        
        $this->ajaxDie(json_encode($events));
    }

    /**
     * Récupérer les événements de disponibilité
     */
    private function getAvailabilityEvents($id_booker = 0, $start = null, $end = null)
    {
        $where_booker = $id_booker > 0 ? 'AND ba.id_booker = ' . (int)$id_booker : '';
        $where_date = '';
        
        if ($start && $end) {
            $where_date = 'AND ba.date_from >= "' . pSQL($start) . '" AND ba.date_to <= "' . pSQL($end) . '"';
        }
        
        $sql = 'SELECT ba.*, b.name as booker_name, b.capacity
                FROM ' . _DB_PREFIX_ . 'booker_auth ba
                LEFT JOIN ' . _DB_PREFIX_ . 'booker b ON ba.id_booker = b.id_booker
                WHERE ba.active = 1 ' . $where_booker . ' ' . $where_date . '
                ORDER BY ba.date_from';
        
        $results = Db::getInstance()->executeS($sql);
        $events = array();
        
        foreach ($results as $row) {
            // Calculer le taux d'occupation
            $occupation_rate = $row['max_bookings'] > 0 ? 
                              ($row['current_bookings'] / $row['max_bookings']) * 100 : 0;
            
            // Couleur selon le taux d'occupation
            $color = '#28a745'; // Vert par défaut
            if ($occupation_rate >= 100) {
                $color = '#dc3545'; // Rouge complet
            } elseif ($occupation_rate >= 80) {
                $color = '#fd7e14'; // Orange presque complet
            } elseif ($occupation_rate >= 50) {
                $color = '#ffc107'; // Jaune moitié
            }
            
            $events[] = array(
                'id' => 'avail_' . $row['id_auth'],
                'title' => $row['booker_name'] . ' (' . $row['current_bookings'] . '/' . $row['max_bookings'] . ')',
                'start' => $row['date_from'],
                'end' => $row['date_to'],
                'backgroundColor' => $color,
                'borderColor' => $color,
                'extendedProps' => array(
                    'type' => 'availability',
                    'id_auth' => $row['id_auth'],
                    'id_booker' => $row['id_booker'],
                    'booker_name' => $row['booker_name'],
                    'max_bookings' => $row['max_bookings'],
                    'current_bookings' => $row['current_bookings'],
                    'price_override' => $row['price_override'],
                    'notes' => $row['notes'],
                    'recurring' => $row['recurring'],
                    'recurring_type' => $row['recurring_type'],
                    'occupation_rate' => round($occupation_rate, 1)
                )
            );
        }
        
        return $events;
    }

    /**
     * Récupérer les événements de réservation
     */
    private function getReservationEvents($id_booker = 0, $start = null, $end = null)
    {
        $where_booker = $id_booker > 0 ? 'AND r.id_booker = ' . (int)$id_booker : '';
        $where_date = '';
        
        if ($start && $end) {
            $where_date = 'AND r.date_reserved >= "' . pSQL($start) . '" AND r.date_reserved <= "' . pSQL($end) . '"';
        }
        
        $sql = 'SELECT r.*, b.name as booker_name,
                       CONCAT(r.customer_firstname, " ", r.customer_lastname) as customer_name
                FROM ' . _DB_PREFIX_ . 'booker_auth_reserved r
                LEFT JOIN ' . _DB_PREFIX_ . 'booker b ON r.id_booker = b.id_booker
                WHERE 1 ' . $where_booker . ' ' . $where_date . '
                ORDER BY r.date_reserved, r.hour_from';
        
        $results = Db::getInstance()->executeS($sql);
        $events = array();
        
        foreach ($results as $row) {
            // Couleur selon le statut
            $colors = array(
                0 => '#ffc107', // Pending - Jaune
                1 => '#17a2b8', // Confirmed - Bleu
                2 => '#28a745', // Paid - Vert
                3 => '#dc3545', // Cancelled - Rouge
                4 => '#6f42c1', // Completed - Violet
                5 => '#fd7e14'  // Refunded - Orange
            );
            
            $color = isset($colors[$row['status']]) ? $colors[$row['status']] : '#6c757d';
            
            // Construire la date/heure de début et fin
            $start_datetime = $row['date_reserved'] . ' ' . str_pad($row['hour_from'], 2, '0', STR_PAD_LEFT) . ':00:00';
            $end_datetime = $row['date_reserved'] . ' ' . str_pad($row['hour_to'], 2, '0', STR_PAD_LEFT) . ':00:00';
            
            // Titre avec informations essentielles
            $title = $row['booker_name'] . ' - ' . $row['customer_name'];
            if ($row['status'] == 3) { // Cancelled
                $title = '[CANCELLED] ' . $title;
            }
            
            $events[] = array(
                'id' => 'reserv_' . $row['id_reserved'],
                'title' => $title,
                'start' => $start_datetime,
                'end' => $end_datetime,
                'backgroundColor' => $color,
                'borderColor' => $color,
                'extendedProps' => array(
                    'type' => 'reservation',
                    'id_reserved' => $row['id_reserved'],
                    'id_booker' => $row['id_booker'],
                    'id_auth' => $row['id_auth'],
                    'booking_reference' => $row['booking_reference'],
                    'booker_name' => $row['booker_name'],
                    'customer_name' => $row['customer_name'],
                    'customer_email' => $row['customer_email'],
                    'customer_phone' => $row['customer_phone'],
                    'total_price' => $row['total_price'],
                    'deposit_paid' => $row['deposit_paid'],
                    'status' => $row['status'],
                    'payment_status' => $row['payment_status'],
                    'deposit_status' => $row['deposit_status'],
                    'notes' => $row['notes'],
                    'admin_notes' => $row['admin_notes']
                )
            );
        }
        
        return $events;
    }

    /**
     * Créer une nouvelle disponibilité via AJAX
     */
    private function ajaxCreateAvailability()
    {
        $id_booker = (int)Tools::getValue('id_booker');
        $date_start = Tools::getValue('date_start');
        $date_end = Tools::getValue('date_end');
        $time_start = Tools::getValue('time_start', '09:00');
        $time_end = Tools::getValue('time_end', '17:00');
        $max_bookings = (int)Tools::getValue('max_bookings', 1);
        
        if (!$id_booker || !$date_start) {
            $this->ajaxDie(json_encode(array('error' => 'Missing required parameters')));
        }
        
        $auth = new BookerAuth();
        $auth->id_booker = $id_booker;
        $auth->date_from = $date_start . ' ' . $time_start . ':00';
        $auth->date_to = ($date_end ?: $date_start) . ' ' . $time_end . ':00';
        $auth->time_from = $time_start;
        $auth->time_to = $time_end;
        $auth->max_bookings = $max_bookings;
        $auth->current_bookings = 0;
        $auth->active = 1;
        $auth->date_add = date('Y-m-d H:i:s');
        $auth->date_upd = date('Y-m-d H:i:s');
        
        if ($auth->add()) {
            $this->ajaxDie(json_encode(array(
                'success' => true,
                'id_auth' => $auth->id,
                'message' => 'Availability created successfully'
            )));
        } else {
            $this->ajaxDie(json_encode(array('error' => 'Failed to create availability')));
        }
    }

    /**
     * Mettre à jour une disponibilité via AJAX
     */
    private function ajaxUpdateAvailability()
    {
        $id_auth = (int)Tools::getValue('id_auth');
        $date_start = Tools::getValue('date_start');
        $date_end = Tools::getValue('date_end');
        $time_start = Tools::getValue('time_start');
        $time_end = Tools::getValue('time_end');
        
        if (!$id_auth) {
            $this->ajaxDie(json_encode(array('error' => 'Missing availability ID')));
        }
        
        $auth = new BookerAuth($id_auth);
        if (!Validate::isLoadedObject($auth)) {
            $this->ajaxDie(json_encode(array('error' => 'Availability not found')));
        }
        
        if ($date_start) {
            $auth->date_from = $date_start . ' ' . ($time_start ?: date('H:i', strtotime($auth->date_from))) . ':00';
        }
        if ($date_end) {
            $auth->date_to = $date_end . ' ' . ($time_end ?: date('H:i', strtotime($auth->date_to))) . ':00';
        }
        if ($time_start) {
            $auth->time_from = $time_start;
        }
        if ($time_end) {
            $auth->time_to = $time_end;
        }
        
        $auth->date_upd = date('Y-m-d H:i:s');
        
        if ($auth->update()) {
            $this->ajaxDie(json_encode(array(
                'success' => true,
                'message' => 'Availability updated successfully'
            )));
        } else {
            $this->ajaxDie(json_encode(array('error' => 'Failed to update availability')));
        }
    }

    /**
     * Supprimer une disponibilité via AJAX
     */
    private function ajaxDeleteAvailability()
    {
        $id_auth = (int)Tools::getValue('id_auth');
        
        if (!$id_auth) {
            $this->ajaxDie(json_encode(array('error' => 'Missing availability ID')));
        }
        
        $auth = new BookerAuth($id_auth);
        if (!Validate::isLoadedObject($auth)) {
            $this->ajaxDie(json_encode(array('error' => 'Availability not found')));
        }
        
        // Vérifier s'il y a des réservations
        $reservations_count = Db::getInstance()->getValue('
            SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'booker_auth_reserved 
            WHERE id_auth = ' . (int)$id_auth . ' AND status NOT IN (3, 5)'
        );
        
        if ($reservations_count > 0) {
            $this->ajaxDie(json_encode(array('error' => 'Cannot delete availability with active reservations')));
        }
        
        if ($auth->delete()) {
            $this->ajaxDie(json_encode(array(
                'success' => true,
                'message' => 'Availability deleted successfully'
            )));
        } else {
            $this->ajaxDie(json_encode(array('error' => 'Failed to delete availability')));
        }
    }

    /**
     * Actions en lot sur les disponibilités
     */
    private function ajaxBulkAvailability()
    {
        $action = Tools::getValue('bulk_action');
        $ids = Tools::getValue('selected_ids');
        
        if (!is_array($ids) || empty($ids)) {
            $this->ajaxDie(json_encode(array('error' => 'No items selected')));
        }
        
        $processed = 0;
        
        switch ($action) {
            case 'delete':
                foreach ($ids as $id_auth) {
                    $auth = new BookerAuth((int)$id_auth);
                    if (Validate::isLoadedObject($auth)) {
                        // Vérifier les réservations actives
                        $reservations_count = Db::getInstance()->getValue('
                            SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'booker_auth_reserved 
                            WHERE id_auth = ' . (int)$id_auth . ' AND status NOT IN (3, 5)'
                        );
                        
                        if ($reservations_count == 0 && $auth->delete()) {
                            $processed++;
                        }
                    }
                }
                break;
                
            case 'activate':
                foreach ($ids as $id_auth) {
                    if (Db::getInstance()->update('booker_auth', array('active' => 1), 'id_auth = ' . (int)$id_auth)) {
                        $processed++;
                    }
                }
                break;
                
            case 'deactivate':
                foreach ($ids as $id_auth) {
                    if (Db::getInstance()->update('booker_auth', array('active' => 0), 'id_auth = ' . (int)$id_auth)) {
                        $processed++;
                    }
                }
                break;
                
            case 'duplicate':
                foreach ($ids as $id_auth) {
                    $auth = new BookerAuth((int)$id_auth);
                    if (Validate::isLoadedObject($auth)) {
                        $new_auth = clone $auth;
                        $new_auth->id = null;
                        $new_auth->current_bookings = 0;
                        $new_auth->active = 0;
                        
                        if ($new_auth->add()) {
                            $processed++;
                        }
                    }
                }
                break;
        }
        
        $this->ajaxDie(json_encode(array(
            'success' => true,
            'processed' => $processed,
            'message' => sprintf('%d items processed successfully', $processed)
        )));
    }

    /**
     * Actions en lot sur les réservations
     */
    private function ajaxBulkReservations()
    {
        $action = Tools::getValue('bulk_action');
        $ids = Tools::getValue('selected_ids');
        
        if (!is_array($ids) || empty($ids)) {
            $this->ajaxDie(json_encode(array('error' => 'No items selected')));
        }
        
        $processed = 0;
        
        foreach ($ids as $id_reserved) {
            $reservation = new BookerAuthReserved((int)$id_reserved);
            if (!Validate::isLoadedObject($reservation)) {
                continue;
            }
            
            switch ($action) {
                case 'validate':
                    if ($reservation->status == BookerAuthReserved::STATUS_PENDING) {
                        $reservation->status = BookerAuthReserved::STATUS_CONFIRMED;
                        $reservation->date_upd = date('Y-m-d H:i:s');
                        
                        if ($reservation->update()) {
                            $processed++;
                        }
                    }
                    break;
                    
                case 'cancel':
                    if ($reservation->status != BookerAuthReserved::STATUS_CANCELLED) {
                        $reservation->status = BookerAuthReserved::STATUS_CANCELLED;
                        $reservation->date_upd = date('Y-m-d H:i:s');
                        
                        if ($reservation->update()) {
                            // Libérer le créneau
                            Db::getInstance()->execute('
                                UPDATE ' . _DB_PREFIX_ . 'booker_auth 
                                SET current_bookings = current_bookings - 1
                                WHERE id_auth = ' . (int)$reservation->id_auth . '
                                AND current_bookings > 0'
                            );
                            $processed++;
                        }
                    }
                    break;
                    
                case 'send_reminder':
                    // Envoyer email de rappel
                    if ($this->sendReminderEmail($reservation)) {
                        $processed++;
                    }
                    break;
            }
        }
        
        $this->ajaxDie(json_encode(array(
            'success' => true,
            'processed' => $processed,
            'message' => sprintf('%d reservations processed successfully', $processed)
        )));
    }

    /**
     * Récupérer les informations d'un booker
     */
    private function ajaxGetBookerInfo()
    {
        $id_booker = (int)Tools::getValue('id_booker');
        
        if (!$id_booker) {
            $this->ajaxDie(json_encode(array('error' => 'Missing booker ID')));
        }
        
        $booker = new Booker($id_booker);
        if (!Validate::isLoadedObject($booker)) {
            $this->ajaxDie(json_encode(array('error' => 'Booker not found')));
        }
        
        // Statistiques
        $stats = array(
            'total_availabilities' => Db::getInstance()->getValue('
                SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'booker_auth 
                WHERE id_booker = ' . (int)$id_booker
            ),
            'active_availabilities' => Db::getInstance()->getValue('
                SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'booker_auth 
                WHERE id_booker = ' . (int)$id_booker . ' AND active = 1'
            ),
            'total_reservations' => Db::getInstance()->getValue('
                SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'booker_auth_reserved 
                WHERE id_booker = ' . (int)$id_booker
            ),
            'pending_reservations' => Db::getInstance()->getValue('
                SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'booker_auth_reserved 
                WHERE id_booker = ' . (int)$id_booker . ' AND status = 0'
            ),
            'confirmed_reservations' => Db::getInstance()->getValue('
                SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'booker_auth_reserved 
                WHERE id_booker = ' . (int)$id_booker . ' AND status IN (1, 2)'
            )
        );
        
        $this->ajaxDie(json_encode(array(
            'success' => true,
            'booker' => array(
                'id_booker' => $booker->id,
                'name' => $booker->name,
                'description' => $booker->description,
                'location' => $booker->location,
                'price' => $booker->price,
                'capacity' => $booker->capacity,
                'booking_duration' => $booker->booking_duration,
                'deposit_required' => $booker->deposit_required,
                'deposit_rate' => $booker->deposit_rate
            ),
            'stats' => $stats
        )));
    }

    /**
     * Récupérer la liste des bookers actifs
     */
    private function getActiveBookers()
    {
        return Db::getInstance()->executeS('
            SELECT id_booker, name, location, capacity
            FROM ' . _DB_PREFIX_ . 'booker
            WHERE active = 1
            ORDER BY name'
        );
    }

    /**
     * Actions de multi-sélection disponibles
     */
    private function getMultiselectActions()
    {
        if ($this->calendar_type == 'availability') {
            return array(
                'activate' => $this->l('Activate selected'),
                'deactivate' => $this->l('Deactivate selected'),
                'duplicate' => $this->l('Duplicate selected'),
                'delete' => $this->l('Delete selected')
            );
        } else {
            return array(
                'validate' => $this->l('Validate selected'),
                'cancel' => $this->l('Cancel selected'),
                'send_reminder' => $this->l('Send reminder'),
                'export' => $this->l('Export selected')
            );
        }
    }

    /**
     * Vues de calendrier disponibles
     */
    private function getCalendarViews()
    {
        return array(
            'dayGridMonth' => $this->l('Month'),
            'dayGridWeek' => $this->l('Week'),
            'timeGridWeek' => $this->l('Week (Timeline)'),
            'timeGridDay' => $this->l('Day'),
            'listWeek' => $this->l('List')
        );
    }

    /**
     * Envoyer un email de rappel
     */
    private function sendReminderEmail($reservation)
    {
        return Mail::Send(
            (int)Configuration::get('PS_LANG_DEFAULT'),
            'booking_reminder',
            $this->l('Booking Reminder'),
            array(
                '{booking_reference}' => $reservation->booking_reference,
                '{customer_name}' => $reservation->customer_firstname . ' ' . $reservation->customer_lastname,
                '{date_reserved}' => $reservation->date_reserved
            ),
            $reservation->customer_email,
            $reservation->customer_firstname . ' ' . $reservation->customer_lastname,
            null,
            null,
            null,
            null,
            _PS_MODULE_DIR_ . 'booking/mails/'
        );
    }

    /**
     * Ajouter CSS et JS pour les calendriers
     */
    public function setMedia()
    {
        parent::setMedia();
        
        // FullCalendar
        $this->addCSS('https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.8/main.min.css');
        $this->addJS('https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.8/index.global.min.js');
        
        // Styles et scripts personnalisés
        $this->addCSS(_MODULE_DIR_ . 'booking/views/css/admin-calendar.css');
        $this->addJS(_MODULE_DIR_ . 'booking/views/js/admin-calendar.js');
        
        // Configuration JavaScript
        Media::addJsDef(array(
            'bookingCalendarConfig' => array(
                'ajax_url' => $this->context->link->getAdminLink('AdminBookerView'),
                'token' => $this->token,
                'calendar_type' => $this->calendar_type,
                'multiselect_enabled' => true,
                'translations' => array(
                    'confirm_delete' => $this->l('Are you sure you want to delete this item?'),
                    'bulk_confirm' => $this->l('Apply action to selected items?'),
                    'no_selection' => $this->l('Please select at least one item'),
                    'success' => $this->l('Action completed successfully'),
                    'error' => $this->l('An error occurred')
                )
            )
        ));
    }
}
