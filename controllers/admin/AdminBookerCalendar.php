<?php
/**
 * Contrôleur administrateur pour la gestion des disponibilités en vue calendrier
 * Calendrier dédié à la création et modification des créneaux de disponibilité
 */

require_once(dirname(__FILE__) . '/../../classes/Booker.php');
require_once(dirname(__FILE__) . '/../../classes/BookerAuth.php');
require_once(dirname(__FILE__) . '/../../classes/BookerAuthReserved.php');

class AdminBookerCalendarController extends ModuleAdminController
{
    protected $_module = NULL;
    public $controller_type = 'admin';   

    public function __construct()
    {		
        $this->display = 'options';
        $this->displayName = 'Calendrier des Disponibilités';
        $this->bootstrap = true;
        parent::__construct();
    }

    public function renderOptions()
    {
        // Ajouter les ressources CSS/JS nécessaires
       // Charger FullCalendar depuis CDN
		$this->context->controller->addJS('https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/5.11.5/main.min.js');
		$this->context->controller->addCSS('https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/5.11.5/main.min.css');
        $this->addJS(_MODULE_DIR_ . $this->module->name . '/js/availability-calendar.js');
        $this->addCSS(_MODULE_DIR_ . $this->module->name . '/css/admin-calendar.css');
        
        // Récupérer la liste des bookers
        $bookers = $this->getActiveBookers();
        
        // URLs AJAX pour les actions
        $ajax_urls = array(
            'get_availabilities' => $this->context->link->getAdminLink('AdminBookerCalendar') . '&ajax=1&action=getAvailabilities',
            'create_availability' => $this->context->link->getAdminLink('AdminBookerCalendar') . '&ajax=1&action=createAvailability',
            'update_availability' => $this->context->link->getAdminLink('AdminBookerCalendar') . '&ajax=1&action=updateAvailability',
            'delete_availability' => $this->context->link->getAdminLink('AdminBookerCalendar') . '&ajax=1&action=deleteAvailability',
            'bulk_create' => $this->context->link->getAdminLink('AdminBookerCalendar') . '&ajax=1&action=bulkCreate',
            'copy_week' => $this->context->link->getAdminLink('AdminBookerCalendar') . '&ajax=1&action=copyWeek'
        );
        
        // Configuration par défaut
        $default_config = array(
            'business_hours' => array(
                'start' => '08:00',
                'end' => '19:00',
                'dow' => [1, 2, 3, 4, 5, 6] // Lundi à samedi
            ),
            'slot_duration' => '01:00:00',
            'default_view' => 'timeGridWeek',
            'locale' => $this->context->language->iso_code
        );
        
        // Statistiques des disponibilités
        $stats = $this->getAvailabilityStats();
        
        $this->context->smarty->assign(array(
            'bookers' => $bookers,
            'ajax_urls' => $ajax_urls,
            'default_config' => $default_config,
            'stats' => $stats,
            'token' => $this->token,
            'current_date' => date('Y-m-d'),
            'preset_times' => $this->getPresetTimes()
        ));
        
        $this->setTemplate('availability_calendar.tpl');
        return '';
    }
    
    /**
     * Gestion des requêtes AJAX
     */
    public function ajaxProcess()
    {
        $action = Tools::getValue('action');
        
        switch ($action) {
            case 'getAvailabilities':
                $this->ajaxProcessGetAvailabilities();
                break;
                
            case 'createAvailability':
                $this->ajaxProcessCreateAvailability();
                break;
                
            case 'updateAvailability':
                $this->ajaxProcessUpdateAvailability();
                break;
                
            case 'deleteAvailability':
                $this->ajaxProcessDeleteAvailability();
                break;
                
            case 'bulkCreate':
                $this->ajaxProcessBulkCreate();
                break;
                
            case 'copyWeek':
                $this->ajaxProcessCopyWeek();
                break;
                
            default:
                $this->ajaxDie(json_encode(array(
                    'success' => false,
                    'message' => 'Action inconnue'
                )));
        }
    }
    
    /**
     * Récupérer les disponibilités pour le calendrier
     */
    private function ajaxProcessGetAvailabilities()
    {
        $start_date = Tools::getValue('start');
        $end_date = Tools::getValue('end');
        $booker_filter = Tools::getValue('booker_id');
        
        if (!$start_date || !$end_date) {
            $this->ajaxDie(json_encode(array()));
        }
        
        $where_conditions = array();
        $where_conditions[] = 'a.date_from <= "' . pSQL($end_date) . '"';
        $where_conditions[] = 'a.date_to >= "' . pSQL($start_date) . '"';
        $where_conditions[] = 'a.active = 1';
        
        if ($booker_filter && $booker_filter !== 'all') {
            $where_conditions[] = 'a.id_booker = ' . (int)$booker_filter;
        }
        
        $sql = 'SELECT a.*, b.name as booker_name, b.price as booker_price
                FROM `' . _DB_PREFIX_ . 'booker_auth` a
                LEFT JOIN `' . _DB_PREFIX_ . 'booker` b ON (a.id_booker = b.id_booker)
                WHERE ' . implode(' AND ', $where_conditions) . '
                ORDER BY a.date_from ASC';
        
        $availabilities = Db::getInstance()->executeS($sql);
        $events = array();
        
        foreach ($availabilities as $availability) {
            $events[] = $this->formatAvailabilityForCalendar($availability, $start_date, $end_date);
        }
        
        // Aplatir le tableau car chaque disponibilité peut générer plusieurs événements
        $flat_events = array();
        foreach ($events as $event_group) {
            if (is_array($event_group) && isset($event_group[0])) {
                $flat_events = array_merge($flat_events, $event_group);
            } else {
                $flat_events[] = $event_group;
            }
        }
        
        $this->ajaxDie(json_encode($flat_events));
    }
    
    /**
     * Formater une disponibilité pour le calendrier
     */
    private function formatAvailabilityForCalendar($availability, $start_date, $end_date)
    {
        $events = array();
        
        // Créer un événement par jour dans la période de disponibilité
        $current_date = max($availability['date_from'], $start_date);
        $end_date_limit = min($availability['date_to'], $end_date);
        
        while ($current_date <= $end_date_limit) {
            // Vérifier qu'il n'y a pas de réservations conflictuelles pour cette date
            $reservations_count = $this->getReservationsCountForDate($availability['id_booker'], $current_date);
            
            $event = array(
                'id' => 'avail_' . $availability['id_auth'] . '_' . $current_date,
                'title' => $availability['booker_name'] . ($reservations_count > 0 ? ' (' . $reservations_count . ' rés.)' : ''),
                'start' => $current_date,
                'end' => date('Y-m-d', strtotime($current_date . ' +1 day')),
                'allDay' => true,
                'backgroundColor' => $reservations_count > 0 ? '#ffc107' : '#28a745', // Jaune si réservations, vert sinon
                'borderColor' => $reservations_count > 0 ? '#ffc107' : '#28a745',
                'textColor' => $reservations_count > 0 ? '#212529' : '#ffffff',
                'extendedProps' => array(
                    'type' => 'availability',
                    'availability_id' => $availability['id_auth'],
                    'booker_id' => $availability['id_booker'],
                    'booker_name' => $availability['booker_name'],
                    'date_from' => $availability['date_from'],
                    'date_to' => $availability['date_to'],
                    'reservations_count' => $reservations_count,
                    'price' => $availability['booker_price']
                )
            );
            
            $events[] = $event;
            $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
        }
        
        return $events;
    }
    
    /**
     * Créer une nouvelle disponibilité
     */
    private function ajaxProcessCreateAvailability()
    {
        $booker_id = (int)Tools::getValue('booker_id');
        $date_from = Tools::getValue('date_from');
        $date_to = Tools::getValue('date_to');
        
        // Validations
        if (!$booker_id || !$date_from || !$date_to) {
            $this->ajaxDie(json_encode(array(
                'success' => false,
                'message' => 'Champs obligatoires manquants'
            )));
        }
        
        if ($date_from > $date_to) {
            $this->ajaxDie(json_encode(array(
                'success' => false,
                'message' => 'La date de fin doit être postérieure à la date de début'
            )));
        }
        
        // Vérifier qu'il n'y a pas de chevauchement
        if ($this->checkAvailabilityOverlap($booker_id, $date_from, $date_to)) {
            $this->ajaxDie(json_encode(array(
                'success' => false,
                'message' => 'Il existe déjà une disponibilité sur cette période'
            )));
        }
        
        // Créer la disponibilité
        $availability = new BookerAuth();
        $availability->id_booker = $booker_id;
        $availability->date_from = $date_from;
        $availability->date_to = $date_to;
        $availability->active = 1;
        
        $success = $availability->add();
        
        if ($success) {
            $this->ajaxDie(json_encode(array(
                'success' => true,
                'message' => 'Disponibilité créée',
                'availability_id' => $availability->id
            )));
        } else {
            $this->ajaxDie(json_encode(array(
                'success' => false,
                'message' => 'Erreur lors de la création'
            )));
        }
    }
    
    /**
     * Mettre à jour une disponibilité
     */
    private function ajaxProcessUpdateAvailability()
    {
        $availability_id = (int)Tools::getValue('availability_id');
        $new_date_from = Tools::getValue('new_date_from');
        $new_date_to = Tools::getValue('new_date_to');
        
        if (!$availability_id) {
            $this->ajaxDie(json_encode(array(
                'success' => false,
                'message' => 'ID de disponibilité manquant'
            )));
        }
        
        $availability = new BookerAuth($availability_id);
        
        if (!Validate::isLoadedObject($availability)) {
            $this->ajaxDie(json_encode(array(
                'success' => false,
                'message' => 'Disponibilité introuvable'
            )));
        }
        
        // Vérifier qu'il n'y a pas de réservations qui seraient en conflit
        if ($new_date_from && $new_date_to) {
            $conflicts = $this->checkReservationConflicts($availability->id_booker, $new_date_from, $new_date_to);
            
            if ($conflicts > 0) {
                $this->ajaxDie(json_encode(array(
                    'success' => false,
                    'message' => 'Il existe ' . $conflicts . ' réservation(s) en conflit avec cette période'
                )));
            }
        }
        
        // Mettre à jour
        if ($new_date_from) {
            $availability->date_from = $new_date_from;
        }
        if ($new_date_to) {
            $availability->date_to = $new_date_to;
        }
        
        $success = $availability->update();
        
        $this->ajaxDie(json_encode(array(
            'success' => $success,
            'message' => $success ? 'Disponibilité mise à jour' : 'Erreur lors de la mise à jour'
        )));
    }
    
    /**
     * Supprimer une disponibilité
     */
    private function ajaxProcessDeleteAvailability()
    {
        $availability_id = (int)Tools::getValue('availability_id');
        
        if (!$availability_id) {
            $this->ajaxDie(json_encode(array(
                'success' => false,
                'message' => 'ID manquant'
            )));
        }
        
        $availability = new BookerAuth($availability_id);
        
        if (!Validate::isLoadedObject($availability)) {
            $this->ajaxDie(json_encode(array(
                'success' => false,
                'message' => 'Disponibilité introuvable'
            )));
        }
        
        // Vérifier qu'il n'y a pas de réservations associées
        $reservations_count = $this->getReservationsCountForAvailability($availability_id);
        
        if ($reservations_count > 0) {
            $this->ajaxDie(json_encode(array(
                'success' => false,
                'message' => 'Impossible de supprimer : ' . $reservations_count . ' réservation(s) associée(s)'
            )));
        }
        
        $success = $availability->delete();
        
        $this->ajaxDie(json_encode(array(
            'success' => $success,
            'message' => $success ? 'Disponibilité supprimée' : 'Erreur lors de la suppression'
        )));
    }
    
    /**
     * Création en lot de disponibilités
     */
    private function ajaxProcessBulkCreate()
    {
        $booker_id = (int)Tools::getValue('booker_id');
        $start_date = Tools::getValue('start_date');
        $end_date = Tools::getValue('end_date');
        $selected_days = Tools::getValue('selected_days'); // Array of day numbers (0=Sunday, 1=Monday, etc.)
        $duration_weeks = (int)Tools::getValue('duration_weeks', 1);
        
        if (!$booker_id || !$start_date || !$selected_days || !is_array($selected_days)) {
            $this->ajaxDie(json_encode(array(
                'success' => false,
                'message' => 'Paramètres manquants'
            )));
        }
        
        $created_count = 0;
        $error_count = 0;
        
        // Créer les disponibilités pour chaque semaine
        for ($week = 0; $week < $duration_weeks; $week++) {
            $week_start = date('Y-m-d', strtotime($start_date . ' +' . ($week * 7) . ' days'));
            
            foreach ($selected_days as $day_num) {
                // Calculer la date pour ce jour de la semaine
                $target_date = date('Y-m-d', strtotime($week_start . ' +' . $day_num . ' days'));
                
                // Vérifier qu'on ne dépasse pas la date de fin si spécifiée
                if ($end_date && $target_date > $end_date) {
                    continue;
                }
                
                // Vérifier qu'il n'existe pas déjà une disponibilité pour cette date
                if (!$this->checkAvailabilityOverlap($booker_id, $target_date, $target_date)) {
                    $availability = new BookerAuth();
                    $availability->id_booker = $booker_id;
                    $availability->date_from = $target_date;
                    $availability->date_to = $target_date;
                    $availability->active = 1;
                    
                    if ($availability->add()) {
                        $created_count++;
                    } else {
                        $error_count++;
                    }
                }
            }
        }
        
        $this->ajaxDie(json_encode(array(
            'success' => $created_count > 0,
            'message' => $created_count . ' disponibilité(s) créée(s), ' . $error_count . ' erreur(s)',
            'created_count' => $created_count,
            'error_count' => $error_count
        )));
    }
    
    /**
     * Copier les disponibilités d'une semaine vers une autre
     */
    private function ajaxProcessCopyWeek()
    {
        $booker_id = (int)Tools::getValue('booker_id');
        $source_week = Tools::getValue('source_week'); // Format YYYY-MM-DD (lundi de la semaine source)
        $target_week = Tools::getValue('target_week'); // Format YYYY-MM-DD (lundi de la semaine cible)
        
        if (!$booker_id || !$source_week || !$target_week) {
            $this->ajaxDie(json_encode(array(
                'success' => false,
                'message' => 'Paramètres manquants'
            )));
        }
        
        // Récupérer les disponibilités de la semaine source
        $source_availabilities = $this->getWeekAvailabilities($booker_id, $source_week);
        
        if (empty($source_availabilities)) {
            $this->ajaxDie(json_encode(array(
                'success' => false,
                'message' => 'Aucune disponibilité trouvée pour la semaine source'
            )));
        }
        
        $created_count = 0;
        $error_count = 0;
        
        foreach ($source_availabilities as $source_avail) {
            // Calculer le décalage en jours
            $day_offset = (strtotime($source_avail['date_from']) - strtotime($source_week)) / (24 * 3600);
            $target_date = date('Y-m-d', strtotime($target_week . ' +' . $day_offset . ' days'));
            
            // Vérifier qu'il n'existe pas déjà une disponibilité
            if (!$this->checkAvailabilityOverlap($booker_id, $target_date, $target_date)) {
                $availability = new BookerAuth();
                $availability->id_booker = $booker_id;
                $availability->date_from = $target_date;
                $availability->date_to = $target_date;
                $availability->active = 1;
                
                if ($availability->add()) {
                    $created_count++;
                } else {
                    $error_count++;
                }
            }
        }
        
        $this->ajaxDie(json_encode(array(
            'success' => $created_count > 0,
            'message' => $created_count . ' disponibilité(s) copiée(s), ' . $error_count . ' erreur(s)',
            'created_count' => $created_count,
            'error_count' => $error_count
        )));
    }
    
    /**
     * Vérifier les chevauchements de disponibilités
     */
    private function checkAvailabilityOverlap($booker_id, $date_from, $date_to, $exclude_id = null)
    {
        $where = 'id_booker = ' . (int)$booker_id . '
                  AND active = 1
                  AND ((date_from <= "' . pSQL($date_to) . '" AND date_to >= "' . pSQL($date_from) . '"))';
        
        if ($exclude_id) {
            $where .= ' AND id_auth != ' . (int)$exclude_id;
        }
        
        $count = Db::getInstance()->getValue('
            SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth`
            WHERE ' . $where
        );
        
        return $count > 0;
    }
    
    /**
     * Vérifier les conflits avec des réservations existantes
     */
    private function checkReservationConflicts($booker_id, $date_from, $date_to)
    {
        return (int)Db::getInstance()->getValue('
            SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`
            WHERE id_booker = ' . (int)$booker_id . '
            AND active = 1
            AND status IN (' . BookerAuthReserved::STATUS_ACCEPTED . ', ' . BookerAuthReserved::STATUS_PAID . ')
            AND date_reserved BETWEEN "' . pSQL($date_from) . '" AND "' . pSQL($date_to) . '"
        ');
    }
    
    /**
     * Compter les réservations pour une date donnée
     */
    private function getReservationsCountForDate($booker_id, $date)
    {
        return (int)Db::getInstance()->getValue('
            SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`
            WHERE id_booker = ' . (int)$booker_id . '
            AND date_reserved = "' . pSQL($date) . '"
            AND active = 1
            AND status IN (' . BookerAuthReserved::STATUS_PENDING . ', ' . BookerAuthReserved::STATUS_ACCEPTED . ', ' . BookerAuthReserved::STATUS_PAID . ')
        ');
    }
    
    /**
     * Compter les réservations pour une disponibilité
     */
    private function getReservationsCountForAvailability($availability_id)
    {
        $availability = new BookerAuth($availability_id);
        
        if (!Validate::isLoadedObject($availability)) {
            return 0;
        }
        
        return (int)Db::getInstance()->getValue('
            SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`
            WHERE id_booker = ' . (int)$availability->id_booker . '
            AND date_reserved BETWEEN "' . pSQL($availability->date_from) . '" AND "' . pSQL($availability->date_to) . '"
            AND active = 1
            AND status IN (' . BookerAuthReserved::STATUS_PENDING . ', ' . BookerAuthReserved::STATUS_ACCEPTED . ', ' . BookerAuthReserved::STATUS_PAID . ')
        ');
    }
    
    /**
     * Récupérer les disponibilités d'une semaine
     */
    private function getWeekAvailabilities($booker_id, $week_start)
    {
        $week_end = date('Y-m-d', strtotime($week_start . ' +6 days'));
        
        return Db::getInstance()->executeS('
            SELECT * FROM `' . _DB_PREFIX_ . 'booker_auth`
            WHERE id_booker = ' . (int)$booker_id . '
            AND active = 1
            AND date_from >= "' . pSQL($week_start) . '"
            AND date_to <= "' . pSQL($week_end) . '"
            ORDER BY date_from ASC
        ');
    }
    
    /**
     * Récupérer les bookers actifs
     */
    private function getActiveBookers()
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'booker`
                WHERE active = 1
                ORDER BY name ASC';
        
        return Db::getInstance()->executeS($sql);
    }
    
    /**
     * Statistiques des disponibilités
     */
    private function getAvailabilityStats()
    {
        return array(
            'total_availabilities' => $this->getTotalAvailabilities(),
            'active_availabilities' => $this->getActiveAvailabilities(),
            'future_availabilities' => $this->getFutureAvailabilities(),
            'occupancy_rate' => $this->getOccupancyRate()
        );
    }
    
    private function getTotalAvailabilities()
    {
        return (int)Db::getInstance()->getValue('
            SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth`
        ');
    }
    
    private function getActiveAvailabilities()
    {
        return (int)Db::getInstance()->getValue('
            SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth`
            WHERE active = 1
        ');
    }
    
    private function getFutureAvailabilities()
    {
        return (int)Db::getInstance()->getValue('
            SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth`
            WHERE active = 1 AND date_to >= CURDATE()
        ');
    }
    
    private function getOccupancyRate()
    {
        // Calculer le taux d'occupation global (réservations / disponibilités)
        $total_available_days = Db::getInstance()->getValue('
            SELECT SUM(DATEDIFF(date_to, date_from) + 1) 
            FROM `' . _DB_PREFIX_ . 'booker_auth`
            WHERE active = 1 AND date_to >= CURDATE()
        ');
        
        if (!$total_available_days) {
            return 0;
        }
        
        $total_reserved_days = Db::getInstance()->getValue('
            SELECT COUNT(DISTINCT CONCAT(id_booker, "-", date_reserved))
            FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`
            WHERE active = 1 
            AND status IN (' . BookerAuthReserved::STATUS_ACCEPTED . ', ' . BookerAuthReserved::STATUS_PAID . ')
            AND date_reserved >= CURDATE()
        ');
        
        return round(($total_reserved_days / $total_available_days) * 100, 1);
    }
    
    /**
     * Créneaux horaires prédéfinis
     */
    private function getPresetTimes()
    {
        return array(
            '08:00' => '8h - 17h (Journée complète)',
            '09:00' => '9h - 18h (Journée bureau)',
            '14:00' => '14h - 17h (Après-midi)',
            '19:00' => '19h - 22h (Soirée)',
            'custom' => 'Personnalisé'
        );
    }
}