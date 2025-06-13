<?php
/**
 * Contrôleur administrateur pour la vue calendrier des réservations
 * Version avec support des réservations multi-jours
 */

require_once(dirname(__FILE__) . '/../../classes/Booker.php');
require_once(dirname(__FILE__) . '/../../classes/BookerAuth.php');
require_once(dirname(__FILE__) . '/../../classes/BookerAuthReserved.php');

class AdminBookerViewController extends ModuleAdminController
{
    public function __construct()
    {		
        $this->display = 'view';
        $this->bootstrap = true;
        parent::__construct();
    }

    public function renderView()
    {
        try {
            // Debug : vérifier que nous arrivons ici
            error_log('AdminBookerView::renderView() - START');
            
            // Charger FullCalendar depuis CDN (plus fiable)
            $this->context->controller->addJS('https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/5.11.5/main.min.js');
            $this->context->controller->addCSS('https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/5.11.5/main.min.css');
            
            // Charger les scripts locaux après FullCalendar
            $this->addJS(_MODULE_DIR_ . $this->module->name . '/js/reservation-calendar.js');
            $this->addCSS(_MODULE_DIR_ . $this->module->name . '/css/admin-calendar.css');
            
            error_log('AdminBookerView::renderView() - Scripts chargés');
            
            // Récupérer la liste des bookers pour le filtre
            $bookers = $this->getActiveBookers();
            error_log('AdminBookerView::renderView() - Bookers: ' . count($bookers));
            
            // URLs AJAX pour les actions
            $ajax_urls = array(
                'get_events' => $this->context->link->getAdminLink('AdminBookerView') . '&ajax=1&action=getEvents',
                'update_reservation' => $this->context->link->getAdminLink('AdminBookerView') . '&ajax=1&action=updateReservation',
                'create_reservation' => $this->context->link->getAdminLink('AdminBookerView') . '&ajax=1&action=createReservation',
                'delete_reservation' => $this->context->link->getAdminLink('AdminBookerView') . '&ajax=1&action=deleteReservation',
                'bulk_action' => $this->context->link->getAdminLink('AdminBookerView') . '&ajax=1&action=bulkAction'
            );
            
            error_log('AdminBookerView::renderView() - URLs AJAX créées');
            
            // Vérifier que la classe BookerAuthReserved existe et a la méthode getStatuses
            $statuses = array();
            if (class_exists('BookerAuthReserved') && method_exists('BookerAuthReserved', 'getStatuses')) {
                $statuses = BookerAuthReserved::getStatuses();
                error_log('AdminBookerView::renderView() - Statuses: ' . count($statuses));
            } else {
                error_log('AdminBookerView::renderView() - ERREUR: BookerAuthReserved::getStatuses() non disponible');
                // Statuses par défaut
                $statuses = array(
                    0 => 'En attente',
                    1 => 'Acceptée',
                    2 => 'Payée',
                    3 => 'Annulée',
                    4 => 'Expirée'
                );
            }
            
            // Ajouter les variables JavaScript nécessaires
            Media::addJSDef(array(
                'BookingCalendar' => array(
                    'config' => array(
                        'locale' => $this->context->language->iso_code,
                        'business_hours' => array(
                            'daysOfWeek' => array(1, 2, 3, 4, 5, 6), // Lundi à Samedi
                            'startTime' => '08:00',
                            'endTime' => '18:00'
                        )
                    ),
                    'currentDate' => date('Y-m-d'),
                    'ajax_urls' => $ajax_urls,
                    'bookers' => $bookers,
                    'statuses' => $statuses,
                    'texts' => array(
                        'loading' => $this->l('Chargement...'),
                        'no_events' => $this->l('Aucune réservation'),
                        'confirm_delete' => $this->l('Confirmer la suppression ?'),
                        'error_occurred' => $this->l('Une erreur est survenue')
                    )
                )
            ));
            
            error_log('AdminBookerView::renderView() - Variables JS définies');
            
            $this->context->smarty->assign([
                'bookers' => $bookers,
                'statuses' => $statuses,
                'ajax_urls' => $ajax_urls,
                'current_date' => date('Y-m-d'),
                'module_dir' => _MODULE_DIR_ . $this->module->name . '/'
            ]);
            
            error_log('AdminBookerView::renderView() - Variables Smarty assignées');
            
            $template_path = _PS_MODULE_DIR_ . $this->module->name . '/views/templates/admin/calendar_reservations.tpl';
            error_log('AdminBookerView::renderView() - Template path: ' . $template_path);
            error_log('AdminBookerView::renderView() - Template exists: ' . (file_exists($template_path) ? 'YES' : 'NO'));
            
            $result = $this->context->smarty->fetch($template_path);
            error_log('AdminBookerView::renderView() - Template fetched, length: ' . strlen($result));
            
            return $result;
            
        } catch (Exception $e) {
            error_log('AdminBookerView::renderView() - EXCEPTION: ' . $e->getMessage());
            error_log('AdminBookerView::renderView() - STACK: ' . $e->getTraceAsString());
            
            // Afficher une erreur simple à l'utilisateur
            return '<div class="alert alert-danger">
                <h4>Erreur de chargement du calendrier</h4>
                <p>Erreur: ' . htmlspecialchars($e->getMessage()) . '</p>
                <p>Vérifiez les logs pour plus de détails.</p>
            </div>';
        }
    }
    
    /**
     * Récupérer les bookers actifs
     */
    private function getActiveBookers()
    {
        try {
            error_log('AdminBookerView::getActiveBookers() - START');
            
            $sql = 'SELECT b.id_booker, b.name
                    FROM `' . _DB_PREFIX_ . 'booker` b
                    WHERE b.active = 1
                    ORDER BY b.name ASC';
            
            error_log('AdminBookerView::getActiveBookers() - SQL: ' . $sql);
            
            $result = Db::getInstance()->executeS($sql);
            
            error_log('AdminBookerView::getActiveBookers() - Result: ' . print_r($result, true));
            
            return $result ? $result : array();
            
        } catch (Exception $e) {
            error_log('AdminBookerView::getActiveBookers() - EXCEPTION: ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Traitement AJAX pour récupérer les événements
     */
    public function ajaxProcessGetEvents()
    {
        try {
            error_log('AdminBookerView::ajaxProcessGetEvents() - START');
            
            $start = Tools::getValue('start');
            $end = Tools::getValue('end');
            $booker_id = (int)Tools::getValue('booker_id');
            
            error_log('AdminBookerView::ajaxProcessGetEvents() - Params: start=' . $start . ', end=' . $end . ', booker_id=' . $booker_id);
            
            $events = $this->getCalendarEvents($start, $end, $booker_id);
            
            error_log('AdminBookerView::ajaxProcessGetEvents() - Events count: ' . count($events));
            
            header('Content-Type: application/json');
            echo json_encode($events);
            exit;
            
        } catch (Exception $e) {
            error_log('AdminBookerView::ajaxProcessGetEvents() - EXCEPTION: ' . $e->getMessage());
            
            header('Content-Type: application/json');
            echo json_encode(array(
                'error' => $e->getMessage(),
                'events' => array()
            ));
            exit;
        }
    }
    
    /**
     * Récupérer les événements pour le calendrier (avec support multi-jours)
     */
    private function getCalendarEvents($start, $end, $booker_id = null)
    {
        try {
            error_log('AdminBookerView::getCalendarEvents() - START');
            
            $where_conditions = array();
            $where_conditions[] = 'r.active = 1';
            
            if ($start) {
                // Pour les réservations multi-jours, on doit vérifier si elles chevauchent avec la période demandée
                $where_conditions[] = '(r.date_reserved <= "' . pSQL($end) . '" AND (r.date_to IS NULL OR r.date_to >= "' . pSQL($start) . '"))';
            }
            
            if ($booker_id) {
                $where_conditions[] = 'r.id_booker = ' . (int)$booker_id;
            }
            
            $sql = 'SELECT r.*, b.name as booker_name
                    FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` r
                    LEFT JOIN `' . _DB_PREFIX_ . 'booker` b ON (r.id_booker = b.id_booker)
                    WHERE ' . implode(' AND ', $where_conditions) . '
                    ORDER BY r.date_reserved ASC, r.hour_from ASC';
            
            error_log('AdminBookerView::getCalendarEvents() - SQL: ' . $sql);
            
            $reservations = Db::getInstance()->executeS($sql);
            $events = array();
            
            if ($reservations) {
                foreach ($reservations as $reservation) {
                    $events[] = $this->formatReservationForCalendar($reservation);
                }
            }
            
            error_log('AdminBookerView::getCalendarEvents() - Events generated: ' . count($events));
            
            return $events;
            
        } catch (Exception $e) {
            error_log('AdminBookerView::getCalendarEvents() - EXCEPTION: ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Formater une réservation pour FullCalendar (avec support multi-jours)
     */
    private function formatReservationForCalendar($reservation)
    {
        $date_start = $reservation['date_reserved'];
        $date_end = $reservation['date_to'] ? $reservation['date_to'] : $reservation['date_reserved'];
        
        // Debug : afficher les données brutes
        error_log('Formatting reservation: ' . print_r($reservation, true));
        
        // Gérer les heures - correction pour les entiers simples
        // Si les heures sont stockées comme des entiers simples (8, 18), les convertir en format HHMM
        $hour_from_int = (int)$reservation['hour_from'];
        $hour_to_int = (int)$reservation['hour_to'];
        
        // Convertir en format HHMM si nécessaire
        if ($hour_from_int < 100) {
            // C'est juste l'heure (8 = 8h00), convertir en 800
            $hour_from_formatted = $hour_from_int * 100;
        } else {
            // C'est déjà au format HHMM (800 = 8h00)
            $hour_from_formatted = $hour_from_int;
        }
        
        if ($hour_to_int < 100) {
            $hour_to_formatted = $hour_to_int * 100;
        } else {
            $hour_to_formatted = $hour_to_int;
        }
        
        $hour_from = str_pad($hour_from_formatted, 4, '0', STR_PAD_LEFT);
        $hour_to = str_pad($hour_to_formatted, 4, '0', STR_PAD_LEFT);
        
        error_log("Hours raw: from={$reservation['hour_from']}, to={$reservation['hour_to']}");
        error_log("Hours converted: from={$hour_from_formatted}, to={$hour_to_formatted}");
        error_log("Hours padded: from={$hour_from}, to={$hour_to}");
        
        $start_time = substr($hour_from, 0, 2) . ':' . substr($hour_from, 2, 2) . ':00';
        $end_time = substr($hour_to, 0, 2) . ':' . substr($hour_to, 2, 2) . ':00';
        
        error_log("Times formatted: start={$start_time}, end={$end_time}");
        
        // Déterminer si c'est une réservation multi-jours
        $is_multiday = ($date_end && $date_end !== $date_start);
        
        if ($is_multiday) {
            error_log("Multi-day reservation detected: {$date_start} to {$date_end}");
            
            // Pour les réservations multi-jours
            if ($reservation['hour_from'] == 0 && $reservation['hour_to'] == 2359) {
                // Réservation "toute la journée" sur plusieurs jours
                $start = $date_start;
                // Pour FullCalendar, la date de fin doit être le jour suivant pour les événements "allDay"
                $end_date_obj = new DateTime($date_end);
                $end_date_obj->add(new DateInterval('P1D'));
                $end = $end_date_obj->format('Y-m-d');
                $all_day = true;
                error_log("All-day multi-day: start={$start}, end={$end}");
            } else {
                // Réservation avec heures spécifiques sur plusieurs jours
                $start = $date_start . 'T' . $start_time;
                $end = $date_end . 'T' . $end_time;
                $all_day = false;
                error_log("Hourly multi-day: start={$start}, end={$end}");
            }
        } else {
            // Réservation sur un seul jour
            $start = $date_start . 'T' . $start_time;
            $end = $date_start . 'T' . $end_time;
            $all_day = false;
            error_log("Single day: start={$start}, end={$end}");
        }
        
        $color = $this->getStatusColor($reservation['status']);
        
        $event = array(
            'id' => $reservation['id_reserved'],
            'title' => $reservation['customer_firstname'] . ' ' . $reservation['customer_lastname'],
            'start' => $start,
            'end' => $end,
            'backgroundColor' => $color,
            'borderColor' => $color,
            'extendedProps' => array(
                'booker_id' => $reservation['id_booker'],
                'booker_name' => $reservation['booker_name'],
                'customer_email' => $reservation['customer_email'],
                'customer_phone' => $reservation['customer_phone'],
                'customer_firstname' => $reservation['customer_firstname'],
                'customer_lastname' => $reservation['customer_lastname'],
                'customer_message' => $reservation['customer_message'],
                'status' => $reservation['status'],
                'booking_reference' => $reservation['booking_reference'],
                'total_price' => $reservation['total_price'],
                'date_reserved' => $reservation['date_reserved'],
                'date_to' => $reservation['date_to'],
                'hour_from' => $reservation['hour_from'],
                'hour_to' => $reservation['hour_to'],
                'is_multiday' => $is_multiday
            )
        );
        
        // Ajouter la propriété allDay si nécessaire
        if (isset($all_day) && $all_day) {
            $event['allDay'] = true;
        }
        
        return $event;
    }
    
    /**
     * Traitement AJAX pour créer une réservation (avec support multi-jours)
     */
    public function ajaxProcessCreateReservation()
    {
        try {
            error_log('AdminBookerView::ajaxProcessCreateReservation() - START');
            
            // Récupérer les données
            $data = array(
                'id_booker' => (int)Tools::getValue('booker_id'),
                'date_reserved' => Tools::getValue('date_reserved'),
                'date_to' => Tools::getValue('date_to'), // Nouveau champ
                'hour_from' => Tools::getValue('hour_from'),
                'hour_to' => Tools::getValue('hour_to'),
                'status' => (int)Tools::getValue('status', 0),
                'customer_firstname' => Tools::getValue('customer_firstname'),
                'customer_lastname' => Tools::getValue('customer_lastname'),
                'customer_email' => Tools::getValue('customer_email'),
                'customer_phone' => Tools::getValue('customer_phone'),
                'customer_message' => Tools::getValue('customer_message'),
                'all_day' => Tools::getValue('all_day') === 'on'
            );
            
            error_log('AdminBookerView::ajaxProcessCreateReservation() - Data: ' . print_r($data, true));
            
            // Validation des données
            $this->validateReservationData($data);
            
            // Créer la réservation
            $reservation = new BookerAuthReserved();
            $this->populateReservationObject($reservation, $data);
            
            // Générer la référence
            $reservation->booking_reference = $this->generateBookingReference($reservation);
            
            if ($reservation->add()) {
                error_log('AdminBookerView::ajaxProcessCreateReservation() - Reservation created with ID: ' . $reservation->id_reserved);
                
                header('Content-Type: application/json');
                echo json_encode(array(
                    'success' => true,
                    'message' => 'Réservation créée avec succès',
                    'reservation' => $this->formatReservationForResponse($reservation)
                ));
            } else {
                throw new Exception('Erreur lors de la sauvegarde en base de données');
            }
            
            exit;
            
        } catch (Exception $e) {
            error_log('AdminBookerView::ajaxProcessCreateReservation() - EXCEPTION: ' . $e->getMessage());
            
            header('Content-Type: application/json');
            echo json_encode(array(
                'success' => false,
                'error' => $e->getMessage()
            ));
            exit;
        }
    }
    
    /**
     * Valider les données de réservation (avec support multi-jours)
     */
    private function validateReservationData($data)
    {
        if (!$data['id_booker']) {
            throw new Exception('Élément à réserver requis');
        }
        
        if (!Validate::isDate($data['date_reserved'])) {
            throw new Exception('Date de réservation invalide');
        }
        
        // Validation de la date de fin si présente
        if ($data['date_to'] && !Validate::isDate($data['date_to'])) {
            throw new Exception('Date de fin invalide');
        }
        
        // La date de fin doit être postérieure ou égale à la date de début
        if ($data['date_to'] && $data['date_to'] < $data['date_reserved']) {
            throw new Exception('La date de fin doit être postérieure ou égale à la date de début');
        }
        
        if (!$data['hour_from'] || !$data['hour_to']) {
            throw new Exception('Heures de début et fin requises');
        }
        
        if ($data['hour_from'] >= $data['hour_to']) {
            throw new Exception('L\'heure de fin doit être postérieure à l\'heure de début');
        }
        
        if (!$data['customer_firstname']) {
            throw new Exception('Prénom du client requis');
        }
        
        if (!$data['customer_lastname']) {
            throw new Exception('Nom du client requis');
        }
        
        if (!$data['customer_email'] || !Validate::isEmail($data['customer_email'])) {
            throw new Exception('Email du client requis et valide');
        }
    }
    
    /**
     * Peupler un objet réservation avec les données (avec support multi-jours)
     */
    private function populateReservationObject($reservation, $data)
    {
        $reservation->id_booker = $data['id_booker'];
        $reservation->date_reserved = $data['date_reserved'];
        
        // Gérer la date de fin
        $reservation->date_to = $data['date_to'] ?: null;
        
        // Gérer les heures (convertir "toute la journée" si nécessaire)
        if ($data['all_day']) {
            $reservation->hour_from = 0;
            $reservation->hour_to = 2359;
        } else {
            $reservation->hour_from = (int)str_replace(':', '', $data['hour_from']);
            $reservation->hour_to = (int)str_replace(':', '', $data['hour_to']);
        }
        
        $reservation->status = $data['status'];
        $reservation->customer_firstname = $data['customer_firstname'];
        $reservation->customer_lastname = $data['customer_lastname'];
        $reservation->customer_email = $data['customer_email'];
        $reservation->customer_phone = $data['customer_phone'];
        $reservation->customer_message = $data['customer_message'];
        $reservation->active = 1;
    }
    
    /**
     * Générer une référence de réservation unique
     */
    private function generateBookingReference($reservation)
    {
        $date = str_replace('-', '', $reservation->date_reserved);
        $random = strtoupper(substr(md5(uniqid(rand(), true)), 0, 4));
        
        return 'BOOK-' . $reservation->id_booker . '-' . $date . '-' . $random;
    }
    
    /**
     * Formater une réservation pour la réponse
     */
    private function formatReservationForResponse($reservation)
    {
        $is_multiday = ($reservation->date_to && $reservation->date_to !== $reservation->date_reserved);
        
        return array(
            'id' => $reservation->id_reserved,
            'booking_reference' => $reservation->booking_reference,
            'customer_name' => $reservation->customer_firstname . ' ' . $reservation->customer_lastname,
            'date_start' => $reservation->date_reserved,
            'date_end' => $reservation->date_to,
            'is_multiday' => $is_multiday,
            'time_slot' => $this->formatTimeSlot($reservation->hour_from, $reservation->hour_to),
            'status' => $reservation->status,
            'status_label' => $reservation->getStatusLabel()
        );
    }
    
    /**
     * Formater un créneau horaire
     */
    private function formatTimeSlot($hour_from, $hour_to)
    {
        // Gérer le cas "toute la journée"
        if ($hour_from == 0 && $hour_to == 2359) {
            return 'Toute la journée';
        }
        
        $from = str_pad($hour_from, 4, '0', STR_PAD_LEFT);
        $to = str_pad($hour_to, 4, '0', STR_PAD_LEFT);
        
        return substr($from, 0, 2) . 'h' . substr($from, 2, 2) . ' - ' . 
               substr($to, 0, 2) . 'h' . substr($to, 2, 2);
    }
    
    /**
     * Obtenir la couleur selon le statut
     */
    private function getStatusColor($status)
    {
        switch ($status) {
            case 0: // STATUS_PENDING
                return '#ffc107'; // Jaune
            case 1: // STATUS_ACCEPTED
                return '#17a2b8'; // Bleu
            case 2: // STATUS_PAID
                return '#28a745'; // Vert
            case 3: // STATUS_CANCELLED
                return '#dc3545'; // Rouge
            case 4: // STATUS_EXPIRED
                return '#6c757d'; // Gris
            default:
                return '#007bff'; // Bleu par défaut
        }
    }
}
?>