<?php
/**
 * AdminBookerCalendarAvailability - Calendrier de gestion des disponibilités
 * Nouveau controller pour gérer visuellement les disponibilités avec multiselect
 */

require_once (dirname(__FILE__). '/../../classes/BookerAuth.php');
require_once (dirname(__FILE__). '/../../classes/Booker.php');

class AdminBookerCalendarAvailabilityController extends ModuleAdminController
{
    protected $_module = NULL;
    public $controller_type = 'admin';
    
    public function __construct()
    {
        $this->bootstrap = true;
        $this->context = Context::getContext();
        $this->display = 'view';
        
        parent::__construct();
    }
    
    public function initContent()
    {
        parent::initContent();
        
        // Charger les assets nécessaires
        $this->addCSS($this->module->getPathUri() . 'views/css/fullcalendar.min.css');
        $this->addJS($this->module->getPathUri() . 'views/js/moment.min.js');
        $this->addJS($this->module->getPathUri() . 'views/js/fullcalendar.min.js');
        $this->addJS($this->module->getPathUri() . 'views/js/fullcalendar-fr.js');
        $this->addJS($this->module->getPathUri() . 'views/js/booking-calendar-availability.js');
        
        // Récupérer les bookers pour le multiselect
        $bookers = $this->getActiveBookers();
        
        $this->context->smarty->assign(array(
            'bookers' => $bookers,
            'ajax_url' => $this->context->link->getAdminLink('AdminBookerCalendarAvailability'),
            'token' => $this->token,
            'default_duration' => Configuration::get('BOOKING_DEFAULT_DURATION', 60),
            'min_time' => Configuration::get('BOOKING_CALENDAR_MIN_TIME', '08:00'),
            'max_time' => Configuration::get('BOOKING_CALENDAR_MAX_TIME', '20:00'),
            'slot_duration' => Configuration::get('BOOKING_SLOT_DURATION', '00:30:00')
        ));
        
        $this->setTemplate('booking/calendar_availability.tpl');
    }
    
    /**
     * Gestion des requêtes AJAX
     */
    public function postProcess()
    {
        if (Tools::isSubmit('ajax')) {
            $action = Tools::getValue('action');
            
            switch ($action) {
                case 'getAvailabilities':
                    $this->ajaxGetAvailabilities();
                    break;
                    
                case 'createAvailability':
                    $this->ajaxCreateAvailability();
                    break;
                    
                case 'updateAvailability':
                    $this->ajaxUpdateAvailability();
                    break;
                    
                case 'deleteAvailability':
                    $this->ajaxDeleteAvailability();
                    break;
                    
                case 'createRecurring':
                    $this->ajaxCreateRecurringAvailability();
                    break;
                    
                case 'copyWeek':
                    $this->ajaxCopyWeek();
                    break;
            }
        }
        
        parent::postProcess();
    }
    
    /**
     * Récupérer les disponibilités pour le calendrier
     */
    private function ajaxGetAvailabilities()
    {
        $start = Tools::getValue('start');
        $end = Tools::getValue('end');
        $booker_ids = Tools::getValue('booker_ids');
        
        if (!is_array($booker_ids)) {
            $booker_ids = array($booker_ids);
        }
        
        $sql = 'SELECT ba.*, b.name as booker_name, b.price as booker_price
                FROM `' . _DB_PREFIX_ . 'booker_auth` ba
                LEFT JOIN `' . _DB_PREFIX_ . 'booker` b ON ba.id_booker = b.id_booker
                WHERE ba.date_from >= "' . pSQL($start) . '"
                AND ba.date_to <= "' . pSQL($end) . '"
                AND ba.id_booker IN (' . implode(',', array_map('intval', $booker_ids)) . ')
                AND ba.active = 1
                ORDER BY ba.date_from ASC';
        
        $availabilities = Db::getInstance()->executeS($sql);
        
        // Formatter pour FullCalendar
        $events = array();
        foreach ($availabilities as $availability) {
            $events[] = $this->formatAvailabilityForCalendar($availability);
        }
        
        header('Content-Type: application/json');
        die(json_encode($events));
    }
    
    /**
     * Créer une nouvelle disponibilité
     */
    private function ajaxCreateAvailability()
    {
        $auth = new BookerAuth();
        $auth->id_booker = (int)Tools::getValue('id_booker');
        $auth->date_from = Tools::getValue('date_from');
        $auth->date_to = Tools::getValue('date_to');
        $auth->time_from = Tools::getValue('time_from', '00:00:00');
        $auth->time_to = Tools::getValue('time_to', '23:59:59');
        $auth->max_bookings = (int)Tools::getValue('max_bookings', 1);
        $auth->price_override = Tools::getValue('price_override') ?: null;
        $auth->active = 1;
        $auth->date_add = date('Y-m-d H:i:s');
        $auth->date_upd = date('Y-m-d H:i:s');
        
        $response = array();
        
        if ($auth->save()) {
            $response['success'] = true;
            $response['message'] = 'Disponibilité créée avec succès';
            $response['event'] = $this->formatAvailabilityForCalendar(array_merge(
                (array)$auth,
                array('booker_name' => $this->getBookerName($auth->id_booker))
            ));
        } else {
            $response['success'] = false;
            $response['message'] = 'Erreur lors de la création de la disponibilité';
        }
        
        header('Content-Type: application/json');
        die(json_encode($response));
    }
    
    /**
     * Mettre à jour une disponibilité (drag & drop, resize)
     */
    private function ajaxUpdateAvailability()
    {
        $id_auth = (int)Tools::getValue('id_auth');
        $auth = new BookerAuth($id_auth);
        
        if (!Validate::isLoadedObject($auth)) {
            die(json_encode(array('success' => false, 'message' => 'Disponibilité introuvable')));
        }
        
        // Mise à jour des dates si fournies
        if (Tools::getValue('date_from')) {
            $auth->date_from = Tools::getValue('date_from');
        }
        if (Tools::getValue('date_to')) {
            $auth->date_to = Tools::getValue('date_to');
        }
        if (Tools::getValue('time_from')) {
            $auth->time_from = Tools::getValue('time_from');
        }
        if (Tools::getValue('time_to')) {
            $auth->time_to = Tools::getValue('time_to');
        }
        
        $auth->date_upd = date('Y-m-d H:i:s');
        
        $response = array();
        
        if ($auth->update()) {
            $response['success'] = true;
            $response['message'] = 'Disponibilité mise à jour';
        } else {
            $response['success'] = false;
            $response['message'] = 'Erreur lors de la mise à jour';
        }
        
        header('Content-Type: application/json');
        die(json_encode($response));
    }
    
    /**
     * Supprimer une disponibilité
     */
    private function ajaxDeleteAvailability()
    {
        $id_auth = (int)Tools::getValue('id_auth');
        $auth = new BookerAuth($id_auth);
        
        if (!Validate::isLoadedObject($auth)) {
            die(json_encode(array('success' => false, 'message' => 'Disponibilité introuvable')));
        }
        
        // Vérifier qu'il n'y a pas de réservations
        $reservations_count = Db::getInstance()->getValue('
            SELECT COUNT(*) 
            FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`
            WHERE id_auth = ' . (int)$id_auth . '
            AND status NOT IN (' . BookerAuthReserved::STATUS_CANCELLED . ', ' . BookerAuthReserved::STATUS_EXPIRED . ')'
        );
        
        if ($reservations_count > 0) {
            die(json_encode(array(
                'success' => false, 
                'message' => 'Impossible de supprimer : des réservations existent sur ce créneau'
            )));
        }
        
        $response = array();
        
        if ($auth->delete()) {
            $response['success'] = true;
            $response['message'] = 'Disponibilité supprimée';
        } else {
            $response['success'] = false;
            $response['message'] = 'Erreur lors de la suppression';
        }
        
        header('Content-Type: application/json');
        die(json_encode($response));
    }
    
    /**
     * Créer des disponibilités récurrentes
     */
    private function ajaxCreateRecurringAvailability()
    {
        $id_booker = (int)Tools::getValue('id_booker');
        $start_date = Tools::getValue('start_date');
        $end_date = Tools::getValue('end_date');
        $days_of_week = Tools::getValue('days_of_week'); // Array des jours (0-6)
        $time_slots = Tools::getValue('time_slots'); // Array des créneaux horaires
        $max_bookings = (int)Tools::getValue('max_bookings', 1);
        
        $created = 0;
        $errors = 0;
        
        // Parcourir toutes les dates entre start et end
        $current_date = new DateTime($start_date);
        $end = new DateTime($end_date);
        
        while ($current_date <= $end) {
            $day_of_week = $current_date->format('w');
            
            // Si ce jour est sélectionné
            if (in_array($day_of_week, $days_of_week)) {
                // Créer une disponibilité pour chaque créneau horaire
                foreach ($time_slots as $slot) {
                    $auth = new BookerAuth();
                    $auth->id_booker = $id_booker;
                    $auth->date_from = $current_date->format('Y-m-d') . ' ' . $slot['start'];
                    $auth->date_to = $current_date->format('Y-m-d') . ' ' . $slot['end'];
                    $auth->time_from = $slot['start'];
                    $auth->time_to = $slot['end'];
                    $auth->max_bookings = $max_bookings;
                    $auth->recurring = 1;
                    $auth->recurring_type = 'weekly';
                    $auth->active = 1;
                    $auth->date_add = date('Y-m-d H:i:s');
                    $auth->date_upd = date('Y-m-d H:i:s');
                    
                    if ($auth->save()) {
                        $created++;
                    } else {
                        $errors++;
                    }
                }
            }
            
            $current_date->modify('+1 day');
        }
        
        $response = array(
            'success' => $errors === 0,
            'message' => sprintf('%d disponibilités créées, %d erreurs', $created, $errors),
            'created' => $created,
            'errors' => $errors
        );
        
        header('Content-Type: application/json');
        die(json_encode($response));
    }
    
    /**
     * Copier les disponibilités d'une semaine
     */
    private function ajaxCopyWeek()
    {
        $source_week_start = Tools::getValue('source_week');
        $target_week_start = Tools::getValue('target_week');
        $id_booker = (int)Tools::getValue('id_booker');
        
        // Récupérer les disponibilités de la semaine source
        $source_end = date('Y-m-d', strtotime($source_week_start . ' +6 days'));
        
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'booker_auth`
                WHERE id_booker = ' . (int)$id_booker . '
                AND date_from >= "' . pSQL($source_week_start) . '"
                AND date_from <= "' . pSQL($source_end) . '"
                AND active = 1';
        
        $availabilities = Db::getInstance()->executeS($sql);
        
        $created = 0;
        $week_diff = (strtotime($target_week_start) - strtotime($source_week_start)) / 86400;
        
        foreach ($availabilities as $availability) {
            $auth = new BookerAuth();
            
            // Copier toutes les propriétés sauf l'ID
            foreach ($availability as $key => $value) {
                if ($key !== 'id_auth') {
                    $auth->$key = $value;
                }
            }
            
            // Ajuster les dates
            $auth->date_from = date('Y-m-d H:i:s', strtotime($availability['date_from'] . ' +' . $week_diff . ' days'));
            $auth->date_to = date('Y-m-d H:i:s', strtotime($availability['date_to'] . ' +' . $week_diff . ' days'));
            $auth->date_add = date('Y-m-d H:i:s');
            $auth->date_upd = date('Y-m-d H:i:s');
            $auth->current_bookings = 0; // Réinitialiser les réservations
            
            if ($auth->save()) {
                $created++;
            }
        }
        
        $response = array(
            'success' => true,
            'message' => $created . ' disponibilités copiées',
            'created' => $created
        );
        
        header('Content-Type: application/json');
        die(json_encode($response));
    }
    
    /**
     * Formater une disponibilité pour FullCalendar
     */
    private function formatAvailabilityForCalendar($availability)
    {
        $remaining = $availability['max_bookings'] - $availability['current_bookings'];
        $color = '#28a745'; // Vert par défaut
        
        if ($remaining == 0) {
            $color = '#dc3545'; // Rouge si complet
        } elseif ($remaining <= 2) {
            $color = '#ffc107'; // Orange si presque complet
        }
        
        return array(
            'id' => $availability['id_auth'],
            'title' => $availability['booker_name'] . ' (' . $remaining . '/' . $availability['max_bookings'] . ')',
            'start' => $availability['date_from'],
            'end' => $availability['date_to'],
            'color' => $color,
            'allDay' => false,
            'editable' => true,
            'resourceId' => $availability['id_booker'],
            'extendedProps' => array(
                'id_booker' => $availability['id_booker'],
                'max_bookings' => $availability['max_bookings'],
                'current_bookings' => $availability['current_bookings'],
                'price_override' => $availability['price_override'],
                'time_from' => $availability['time_from'],
                'time_to' => $availability['time_to']
            )
        );
    }
    
    /**
     * Récupérer les bookers actifs
     */
    private function getActiveBookers()
    {
        return Db::getInstance()->executeS('
            SELECT id_booker, name, price, capacity
            FROM `' . _DB_PREFIX_ . 'booker`
            WHERE active = 1
            ORDER BY name ASC
        ');
    }
    
    /**
     * Récupérer le nom d'un booker
     */
    private function getBookerName($id_booker)
    {
        return Db::getInstance()->getValue('
            SELECT name FROM `' . _DB_PREFIX_ . 'booker`
            WHERE id_booker = ' . (int)$id_booker
        );
    }
    
    public function initPageHeaderToolbar()
    {
        $this->page_header_toolbar_title = 'Calendrier des disponibilités';
        
        $this->page_header_toolbar_btn['back'] = array(
            'href' => $this->context->link->getAdminLink('AdminBookerAuth'),
            'desc' => 'Retour à la liste',
            'icon' => 'process-icon-back'
        );
        
        parent::initPageHeaderToolbar();
    }
}
