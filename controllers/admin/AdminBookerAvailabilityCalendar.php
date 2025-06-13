<?php
/**
 * Contrôleur pour le calendrier de gestion des disponibilités
 * Interface avancée avec FullCalendar et outils de création en lot
 */

require_once(dirname(__FILE__) . '/../../classes/Booker.php');
require_once(dirname(__FILE__) . '/../../classes/BookerAuth.php');
require_once(dirname(__FILE__) . '/../../classes/BookerAuthReserved.php');

class AdminBookerAvailabilityCalendarController extends ModuleAdminController
{
    public function __construct()
    {
        $this->display = 'view';
        $this->bootstrap = true;
        parent::__construct();
        
        $this->context->smarty->assign([
            'page_title' => $this->l('Calendrier des Disponibilités'),
            'page_subtitle' => $this->l('Gérer les créneaux disponibles pour les réservations')
        ]);
    }

    /**
     * Rendu de la vue principale
     */
    public function renderView()
    {
        try {
            // Chargement des ressources FullCalendar
            $this->context->controller->addJS('https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.8/index.global.min.js');
            $this->context->controller->addJS('https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.8/locales-all.global.min.js');
            
            // Scripts et styles locaux
            $this->addJS(_MODULE_DIR_ . $this->module->name . '/views/js/availability-calendar.js');
            $this->addCSS(_MODULE_DIR_ . $this->module->name . '/views/css/admin-calendar.css');
            
            // Récupération des bookers actifs
            $bookers = $this->getActiveBookers();
            
            // URLs AJAX pour les actions
            $ajax_urls = [
                'get_availabilities' => $this->context->link->getAdminLink('AdminBookerAvailabilityCalendar') . '&ajax=1&action=getAvailabilities',
                'save_availability' => $this->context->link->getAdminLink('AdminBookerAvailabilityCalendar') . '&ajax=1&action=saveAvailability',
                'delete_availability' => $this->context->link->getAdminLink('AdminBookerAvailabilityCalendar') . '&ajax=1&action=deleteAvailability',
                'bulk_create' => $this->context->link->getAdminLink('AdminBookerAvailabilityCalendar') . '&ajax=1&action=bulkCreate',
                'copy_week' => $this->context->link->getAdminLink('AdminBookerAvailabilityCalendar') . '&ajax=1&action=copyWeek',
                'get_availability_details' => $this->context->link->getAdminLink('AdminBookerAvailabilityCalendar') . '&ajax=1&action=getAvailabilityDetails',
                'update_availability' => $this->context->link->getAdminLink('AdminBookerAvailabilityCalendar') . '&ajax=1&action=updateAvailability'
            ];
            
            // Configuration par défaut
            $default_config = [
                'locale' => 'fr',
                'business_hours' => [
                    'startTime' => Configuration::get('BOOKING_BUSINESS_HOURS_START') ?: '08:00',
                    'endTime' => Configuration::get('BOOKING_BUSINESS_HOURS_END') ?: '18:00',
                    'daysOfWeek' => [1, 2, 3, 4, 5]
                ],
                'default_view' => 'timeGridWeek',
                'slot_duration' => Configuration::get('BOOKING_DEFAULT_DURATION') ?: 60
            ];
            
            // Variables pour le template
            $this->context->smarty->assign([
                'bookers' => $bookers,
                'ajax_urls' => $ajax_urls,
                'current_date' => date('Y-m-d'),
                'default_config' => $default_config,
                'module_dir' => _MODULE_DIR_ . $this->module->name . '/',
                'default_duration' => Configuration::get('BOOKING_DEFAULT_DURATION') ?: 60,
                'business_hours_start' => Configuration::get('BOOKING_BUSINESS_HOURS_START') ?: '08:00',
                'business_hours_end' => Configuration::get('BOOKING_BUSINESS_HOURS_END') ?: '18:00',
                'availability_stats' => $this->getAvailabilityStats(),
                'token' => $this->token
            ]);
            
            $template_path = _PS_MODULE_DIR_ . $this->module->name . '/views/templates/admin/availability_calendar.tpl';
            
            if (file_exists($template_path)) {
                return $this->context->smarty->fetch($template_path);
            } else {
                return $this->generateDefaultView();
            }
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog('AdminBookerAvailabilityCalendar::renderView() - Erreur: ' . $e->getMessage(), 3);
            return $this->displayError($this->l('Erreur lors du chargement du calendrier des disponibilités'));
        }
    }
    
    /**
     * Récupérer les bookers actifs
     */
    private function getActiveBookers()
    {
        try {
            $sql = 'SELECT b.id, b.name, b.description, b.price, b.duration, b.max_bookings, b.active
                    FROM `' . _DB_PREFIX_ . 'booker` b
                    WHERE b.active = 1
                    ORDER BY b.sort_order ASC, b.name ASC';
            
            $result = Db::getInstance()->executeS($sql);
            
            return $result ?: [];
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog('AdminBookerAvailabilityCalendar::getActiveBookers() - Erreur: ' . $e->getMessage(), 3);
            return [];
        }
    }
    
    /**
     * Statistiques des disponibilités
     */
    private function getAvailabilityStats()
    {
        try {
            $stats = [
                'total_slots' => 0,
                'available_slots' => 0,
                'partial_slots' => 0,
                'full_slots' => 0
            ];
            
            // Créneaux total
            $stats['total_slots'] = (int)Db::getInstance()->getValue('
                SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth` 
                WHERE active = 1 AND date_from >= CURDATE()
            ');
            
            // Créneaux disponibles (aucune réservation)
            $stats['available_slots'] = (int)Db::getInstance()->getValue('
                SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth` ba
                WHERE ba.active = 1 AND ba.date_from >= CURDATE() 
                AND ba.current_bookings = 0
            ');
            
            // Créneaux partiellement réservés
            $stats['partial_slots'] = (int)Db::getInstance()->getValue('
                SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth` ba
                WHERE ba.active = 1 AND ba.date_from >= CURDATE() 
                AND ba.current_bookings > 0 AND ba.current_bookings < ba.max_bookings
            ');
            
            // Créneaux complets
            $stats['full_slots'] = (int)Db::getInstance()->getValue('
                SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth` ba
                WHERE ba.active = 1 AND ba.date_from >= CURDATE() 
                AND ba.current_bookings >= ba.max_bookings
            ');
            
            return $stats;
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog('AdminBookerAvailabilityCalendar::getAvailabilityStats() - Erreur: ' . $e->getMessage(), 3);
            return ['total_slots' => 0, 'available_slots' => 0, 'partial_slots' => 0, 'full_slots' => 0];
        }
    }
    
    /**
     * Traitement des requêtes AJAX
     */
    public function ajaxProcess()
    {
        $action = Tools::getValue('action');
        
        try {
            switch ($action) {
                case 'getAvailabilities':
                    $this->ajaxProcessGetAvailabilities();
                    break;
                    
                case 'saveAvailability':
                    $this->ajaxProcessSaveAvailability();
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
                    
                case 'getAvailabilityDetails':
                    $this->ajaxProcessGetAvailabilityDetails();
                    break;
                    
                case 'updateAvailability':
                    $this->ajaxProcessUpdateAvailability();
                    break;
                    
                default:
                    $this->ajaxDie(json_encode(['success' => false, 'message' => 'Action non reconnue']));
            }
        } catch (Exception $e) {
            PrestaShopLogger::addLog('AdminBookerAvailabilityCalendar::ajaxProcess() - Erreur: ' . $e->getMessage(), 3);
            $this->ajaxDie(json_encode(['success' => false, 'message' => $e->getMessage()]));
        }
    }
    
    /**
     * Récupérer les disponibilités pour le calendrier
     */
    private function ajaxProcessGetAvailabilities()
    {
        $start = Tools::getValue('start');
        $end = Tools::getValue('end');
        $booker_id = (int)Tools::getValue('booker_id');
        
        try {
            $sql = 'SELECT ba.id, ba.id_booker, ba.date_from, ba.date_to, ba.time_from, ba.time_to,
                           ba.max_bookings, ba.current_bookings, ba.price_override, ba.active, ba.notes,
                           b.name as booker_name, b.price as booker_price
                    FROM `' . _DB_PREFIX_ . 'booker_auth` ba
                    LEFT JOIN `' . _DB_PREFIX_ . 'booker` b ON b.id = ba.id_booker
                    WHERE ba.date_from <= "' . pSQL($end) . '" AND ba.date_to >= "' . pSQL($start) . '"';
            
            if ($booker_id > 0) {
                $sql .= ' AND ba.id_booker = ' . (int)$booker_id;
            }
            
            $sql .= ' ORDER BY ba.date_from ASC, ba.time_from ASC';
            
            $availabilities = Db::getInstance()->executeS($sql);
            $events = [];
            
            foreach ($availabilities as $availability) {
                // Déterminer la couleur selon le statut
                $color = $this->getAvailabilityColor($availability);
                
                // Construire l'événement pour FullCalendar
                $event = [
                    'id' => 'availability_' . $availability['id'],
                    'title' => $availability['booker_name'] . ' (' . $availability['current_bookings'] . '/' . $availability['max_bookings'] . ')',
                    'start' => $availability['date_from'] . 'T' . $availability['time_from'],
                    'end' => $availability['date_to'] . 'T' . $availability['time_to'],
                    'backgroundColor' => $color,
                    'borderColor' => $color,
                    'extendedProps' => [
                        'type' => 'availability',
                        'availability_id' => $availability['id'],
                        'booker_id' => $availability['id_booker'],
                        'booker_name' => $availability['booker_name'],
                        'max_bookings' => $availability['max_bookings'],
                        'current_bookings' => $availability['current_bookings'],
                        'price_override' => $availability['price_override'],
                        'booker_price' => $availability['booker_price'],
                        'active' => $availability['active'],
                        'notes' => $availability['notes']
                    ]
                ];
                
                $events[] = $event;
            }
            
            $this->ajaxDie(json_encode($events));
            
        } catch (Exception $e) {
            $this->ajaxDie(json_encode(['error' => 'Erreur lors du chargement des disponibilités: ' . $e->getMessage()]));
        }
    }
    
    /**
     * Déterminer la couleur d'une disponibilité
     */
    private function getAvailabilityColor($availability)
    {
        if (!$availability['active']) {
            return '#6c757d'; // Gris - Inactif
        }
        
        if ($availability['current_bookings'] == 0) {
            return '#28a745'; // Vert - Disponible
        } elseif ($availability['current_bookings'] < $availability['max_bookings']) {
            return '#ffc107'; // Jaune - Partiellement réservé
        } else {
            return '#dc3545'; // Rouge - Complet
        }
    }
    
    /**
     * Sauvegarder une disponibilité
     */
    private function ajaxProcessSaveAvailability()
    {
        $id = (int)Tools::getValue('id');
        $id_booker = (int)Tools::getValue('id_booker');
        $date_from = Tools::getValue('date_from');
        $date_to = Tools::getValue('date_to');
        $time_from = Tools::getValue('time_from');
        $time_to = Tools::getValue('time_to');
        $max_bookings = (int)Tools::getValue('max_bookings') ?: 1;
        $price_override = Tools::getValue('price_override') ? (float)Tools::getValue('price_override') : null;
        $active = (int)Tools::getValue('active') ?: 1;
        $notes = Tools::getValue('notes');
        $recurring = (int)Tools::getValue('recurring') ?: 0;
        $recurring_type = Tools::getValue('recurring_type');
        $recurring_end = Tools::getValue('recurring_end');
        
        try {
            // Validation
            if (!$id_booker || !$date_from || !$date_to || !$time_from || !$time_to) {
                throw new Exception('Tous les champs requis doivent être remplis');
            }
            
            if ($date_to < $date_from) {
                throw new Exception('La date de fin doit être postérieure à la date de début');
            }
            
            if ($time_to <= $time_from) {
                throw new Exception('L\'heure de fin doit être postérieure à l\'heure de début');
            }
            
            // Vérifier que le booker existe
            $booker = new Booker($id_booker);
            if (!Validate::isLoadedObject($booker)) {
                throw new Exception('Élément réservable non trouvé');
            }
            
            if ($id > 0) {
                // Mise à jour
                $auth = new BookerAuth($id);
                if (!Validate::isLoadedObject($auth)) {
                    throw new Exception('Disponibilité non trouvée');
                }
            } else {
                // Création
                $auth = new BookerAuth();
                $auth->date_add = date('Y-m-d H:i:s');
            }
            
            // Remplir les données
            $auth->id_booker = $id_booker;
            $auth->date_from = $date_from . ' ' . $time_from;
            $auth->date_to = $date_to . ' ' . $time_to;
            $auth->time_from = $time_from;
            $auth->time_to = $time_to;
            $auth->max_bookings = $max_bookings;
            $auth->price_override = $price_override;
            $auth->active = $active;
            $auth->notes = $notes;
            $auth->recurring = $recurring;
            $auth->recurring_type = $recurring_type;
            $auth->recurring_end = $recurring_end;
            $auth->date_upd = date('Y-m-d H:i:s');
            
            if ($auth->save()) {
                // Si récurrent, créer les occurrences
                if ($recurring && $recurring_type && $recurring_end) {
                    $this->createRecurringAvailabilities($auth);
                }
                
                $this->ajaxDie(json_encode([
                    'success' => true,
                    'message' => 'Disponibilité enregistrée avec succès',
                    'id' => $auth->id
                ]));
            } else {
                throw new Exception('Erreur lors de l\'enregistrement');
            }
            
        } catch (Exception $e) {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));
        }
    }
    
    /**
     * Créer des disponibilités récurrentes
     */
    private function createRecurringAvailabilities($base_auth)
    {
        try {
            $start_date = new DateTime($base_auth->date_from);
            $end_date = new DateTime($base_auth->recurring_end);
            $current_date = clone $start_date;
            
            $interval_map = [
                'daily' => 'P1D',
                'weekly' => 'P1W',
                'monthly' => 'P1M'
            ];
            
            if (!isset($interval_map[$base_auth->recurring_type])) {
                return;
            }
            
            $interval = new DateInterval($interval_map[$base_auth->recurring_type]);
            $count = 0;
            $max_occurrences = 100; // Limite de sécurité
            
            while ($current_date <= $end_date && $count < $max_occurrences) {
                $current_date->add($interval);
                
                if ($current_date > $end_date) {
                    break;
                }
                
                // Créer la nouvelle occurrence
                $new_auth = new BookerAuth();
                $new_auth->id_booker = $base_auth->id_booker;
                $new_auth->date_from = $current_date->format('Y-m-d') . ' ' . $base_auth->time_from;
                $new_auth->date_to = $current_date->format('Y-m-d') . ' ' . $base_auth->time_to;
                $new_auth->time_from = $base_auth->time_from;
                $new_auth->time_to = $base_auth->time_to;
                $new_auth->max_bookings = $base_auth->max_bookings;
                $new_auth->price_override = $base_auth->price_override;
                $new_auth->active = $base_auth->active;
                $new_auth->notes = $base_auth->notes;
                $new_auth->recurring = 0; // Les occurrences ne sont pas récurrentes
                $new_auth->date_add = date('Y-m-d H:i:s');
                $new_auth->date_upd = date('Y-m-d H:i:s');
                
                $new_auth->save();
                $count++;
            }
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog('AdminBookerAvailabilityCalendar::createRecurringAvailabilities() - Erreur: ' . $e->getMessage(), 3);
        }
    }
    
    /**
     * Supprimer une disponibilité
     */
    private function ajaxProcessDeleteAvailability()
    {
        $id = (int)Tools::getValue('id');
        
        try {
            if (!$id) {
                throw new Exception('ID de disponibilité manquant');
            }
            
            $auth = new BookerAuth($id);
            if (!Validate::isLoadedObject($auth)) {
                throw new Exception('Disponibilité non trouvée');
            }
            
            // Vérifier qu'il n'y a pas de réservations
            $reservation_count = (int)Db::getInstance()->getValue('
                SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` 
                WHERE id_auth = ' . (int)$id . ' AND status NOT IN ("cancelled")
            ');
            
            if ($reservation_count > 0) {
                throw new Exception('Impossible de supprimer une disponibilité avec des réservations actives');
            }
            
            if ($auth->delete()) {
                $this->ajaxDie(json_encode([
                    'success' => true,
                    'message' => 'Disponibilité supprimée avec succès'
                ]));
            } else {
                throw new Exception('Erreur lors de la suppression');
            }
            
        } catch (Exception $e) {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));
        }
    }
    
    /**
     * Création en lot de disponibilités
     */
    private function ajaxProcessBulkCreate()
    {
        $bookers = Tools::getValue('bookers');
        $days = Tools::getValue('days');
        $date_start = Tools::getValue('date_start');
        $date_end = Tools::getValue('date_end');
        $time_start = Tools::getValue('time_start');
        $time_end = Tools::getValue('time_end');
        $slot_duration = (int)Tools::getValue('slot_duration') ?: 60;
        $max_bookings = (int)Tools::getValue('max_bookings') ?: 1;
        
        try {
            if (!$bookers || !$days || !$date_start || !$date_end || !$time_start || !$time_end) {
                throw new Exception('Tous les champs requis doivent être remplis');
            }
            
            $created_count = 0;
            $start_date = new DateTime($date_start);
            $end_date = new DateTime($date_end);
            $current_date = clone $start_date;
            
            while ($current_date <= $end_date) {
                $day_of_week = $current_date->format('w'); // 0 = dimanche
                
                if (in_array($day_of_week, $days)) {
                    foreach ($bookers as $booker_id) {
                        $created_count += $this->createTimeSlotsForDay($booker_id, $current_date, $time_start, $time_end, $slot_duration, $max_bookings);
                    }
                }
                
                $current_date->add(new DateInterval('P1D'));
            }
            
            $this->ajaxDie(json_encode([
                'success' => true,
                'message' => $created_count . ' créneaux créés avec succès',
                'count' => $created_count
            ]));
            
        } catch (Exception $e) {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));
        }
    }
    
    /**
     * Créer les créneaux horaires pour une journée
     */
    private function createTimeSlotsForDay($booker_id, $date, $time_start, $time_end, $slot_duration, $max_bookings)
    {
        $created = 0;
        $start_time = new DateTime($date->format('Y-m-d') . ' ' . $time_start);
        $end_time = new DateTime($date->format('Y-m-d') . ' ' . $time_end);
        $slot_interval = new DateInterval('PT' . $slot_duration . 'M');
        
        while ($start_time < $end_time) {
            $slot_end = clone $start_time;
            $slot_end->add($slot_interval);
            
            if ($slot_end > $end_time) {
                break;
            }
            
            // Vérifier si le créneau existe déjà
            $existing = Db::getInstance()->getValue('
                SELECT id FROM `' . _DB_PREFIX_ . 'booker_auth`
                WHERE id_booker = ' . (int)$booker_id . '
                AND date_from = "' . $start_time->format('Y-m-d H:i:s') . '"
                AND date_to = "' . $slot_end->format('Y-m-d H:i:s') . '"
            ');
            
            if (!$existing) {
                $auth = new BookerAuth();
                $auth->id_booker = $booker_id;
                $auth->date_from = $start_time->format('Y-m-d H:i:s');
                $auth->date_to = $slot_end->format('Y-m-d H:i:s');
                $auth->time_from = $start_time->format('H:i:s');
                $auth->time_to = $slot_end->format('H:i:s');
                $auth->max_bookings = $max_bookings;
                $auth->active = 1;
                $auth->date_add = date('Y-m-d H:i:s');
                $auth->date_upd = date('Y-m-d H:i:s');
                
                if ($auth->save()) {
                    $created++;
                }
            }
            
            $start_time->add($slot_interval);
        }
        
        return $created;
    }
    
    /**
     * Copier une semaine
     */
    private function ajaxProcessCopyWeek()
    {
        $source_week = Tools::getValue('source_week');
        $target_weeks = Tools::getValue('target_weeks');
        
        try {
            if (!$source_week || !$target_weeks) {
                throw new Exception('Paramètres manquants pour la copie');
            }
            
            // Convertir la semaine ISO en dates
            $source_start = new DateTime();
            $source_start->setISODate(substr($source_week, 0, 4), substr($source_week, 6, 2));
            $source_end = clone $source_start;
            $source_end->add(new DateInterval('P6D'));
            
            // Récupérer les disponibilités de la semaine source
            $source_availabilities = Db::getInstance()->executeS('
                SELECT * FROM `' . _DB_PREFIX_ . 'booker_auth`
                WHERE date_from >= "' . $source_start->format('Y-m-d') . ' 00:00:00"
                AND date_to <= "' . $source_end->format('Y-m-d') . ' 23:59:59"
                AND active = 1
            ');
            
            if (empty($source_availabilities)) {
                throw new Exception('Aucune disponibilité trouvée pour la semaine source');
            }
            
            $copied_count = 0;
            
            foreach ($target_weeks as $target_week) {
                $target_start = new DateTime();
                $target_start->setISODate(substr($target_week, 0, 4), substr($target_week, 6, 2));
                
                $week_diff = $target_start->diff($source_start)->days;
                if ($source_start > $target_start) {
                    $week_diff = -$week_diff;
                }
                
                foreach ($source_availabilities as $source_auth) {
                    $new_start = new DateTime($source_auth['date_from']);
                    $new_end = new DateTime($source_auth['date_to']);
                    
                    if ($week_diff > 0) {
                        $new_start->add(new DateInterval('P' . $week_diff . 'D'));
                        $new_end->add(new DateInterval('P' . $week_diff . 'D'));
                    } elseif ($week_diff < 0) {
                        $new_start->sub(new DateInterval('P' . abs($week_diff) . 'D'));
                        $new_end->sub(new DateInterval('P' . abs($week_diff) . 'D'));
                    }
                    
                    // Vérifier si la disponibilité existe déjà
                    $existing = Db::getInstance()->getValue('
                        SELECT id FROM `' . _DB_PREFIX_ . 'booker_auth`
                        WHERE id_booker = ' . (int)$source_auth['id_booker'] . '
                        AND date_from = "' . $new_start->format('Y-m-d H:i:s') . '"
                        AND date_to = "' . $new_end->format('Y-m-d H:i:s') . '"
                    ');
                    
                    if (!$existing) {
                        $new_auth = new BookerAuth();
                        $new_auth->id_booker = $source_auth['id_booker'];
                        $new_auth->date_from = $new_start->format('Y-m-d H:i:s');
                        $new_auth->date_to = $new_end->format('Y-m-d H:i:s');
                        $new_auth->time_from = $source_auth['time_from'];
                        $new_auth->time_to = $source_auth['time_to'];
                        $new_auth->max_bookings = $source_auth['max_bookings'];
                        $new_auth->price_override = $source_auth['price_override'];
                        $new_auth->active = $source_auth['active'];
                        $new_auth->notes = $source_auth['notes'];
                        $new_auth->date_add = date('Y-m-d H:i:s');
                        $new_auth->date_upd = date('Y-m-d H:i:s');
                        
                        if ($new_auth->save()) {
                            $copied_count++;
                        }
                    }
                }
            }
            
            $this->ajaxDie(json_encode([
                'success' => true,
                'message' => $copied_count . ' disponibilités copiées avec succès',
                'count' => $copied_count
            ]));
            
        } catch (Exception $e) {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));
        }
    }
    
    /**
     * Récupérer les détails d'une disponibilité
     */
    private function ajaxProcessGetAvailabilityDetails()
    {
        $id = (int)Tools::getValue('id');
        
        try {
            if (!$id) {
                throw new Exception('ID de disponibilité manquant');
            }
            
            $sql = 'SELECT ba.*, b.name as booker_name, b.price as booker_price
                    FROM `' . _DB_PREFIX_ . 'booker_auth` ba
                    LEFT JOIN `' . _DB_PREFIX_ . 'booker` b ON b.id = ba.id_booker
                    WHERE ba.id = ' . (int)$id;
            
            $availability = Db::getInstance()->getRow($sql);
            
            if (!$availability) {
                throw new Exception('Disponibilité non trouvée');
            }
            
            // Récupérer les réservations associées
            $reservations = Db::getInstance()->executeS('
                SELECT bar.*, CONCAT(bar.customer_firstname, " ", bar.customer_lastname) as customer_name
                FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` bar
                WHERE bar.id_auth = ' . (int)$id . '
                ORDER BY bar.date_add DESC
            ');
            
            $availability['reservations'] = $reservations;
            
            $this->ajaxDie(json_encode([
                'success' => true,
                'data' => $availability
            ]));
            
        } catch (Exception $e) {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));
        }
    }
    
    /**
     * Mettre à jour une disponibilité
     */
    private function ajaxProcessUpdateAvailability()
    {
        // Réutilise la logique de saveAvailability
        $this->ajaxProcessSaveAvailability();
    }
    
    /**
     * Vue par défaut en cas d'absence de template
     */
    private function generateDefaultView()
    {
        return '<div class="alert alert-warning">
            <h4>Calendrier des disponibilités</h4>
            <p>Le template du calendrier n\'est pas disponible. Veuillez vérifier l\'installation du module.</p>
            <p>Template attendu : ' . _PS_MODULE_DIR_ . $this->module->name . '/views/templates/admin/availability_calendar.tpl</p>
        </div>';
    }
}
