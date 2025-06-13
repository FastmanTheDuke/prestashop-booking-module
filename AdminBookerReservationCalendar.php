<?php
require_once (dirname(__FILE__). '/../../classes/Booker.php');
require_once (dirname(__FILE__). '/../../classes/BookerAuth.php');
require_once (dirname(__FILE__). '/../../classes/BookerAuthReserved.php');

class AdminBookerReservationCalendarController extends ModuleAdminController
{
    protected $_module = NULL;
    public $controller_type = 'admin';   

    public function __construct()
    {		
        $this->display = 'options';
        $this->displayName = 'Calendrier des Réservations';
        $this->bootstrap = true;
        parent::__construct();
    }

    public function renderOptions()
    {
		// Charger FullCalendar depuis CDN
		$this->context->controller->addJS('https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/5.11.5/main.min.js');
		$this->context->controller->addCSS('https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/5.11.5/main.min.css');
        $this->addJS(_MODULE_DIR_.$this->module->name.'/js/reservation-calendar.js');
        $this->addCSS(_MODULE_DIR_.$this->module->name.'/css/calendar.css');
        
        return $this->renderReservationCalendar();
    }

    /**
     * Traitement AJAX pour charger les données du calendrier
     */
    public function ajaxProcessLoadCalendar() {
        $booker_id = (int)Tools::getValue('booker_id');
        $year = (int)Tools::getValue('year', date('Y'));
        $month = (int)Tools::getValue('month', date('m'));
        $view = Tools::getValue('view', 'month');
        $status_filter = Tools::getValue('status_filter', 'all');
        
        $data = $this->getCalendarData($booker_id, $year, $month, $view, $status_filter);
        
        echo json_encode([
            'success' => true,
            'data' => $data,
            'booker_id' => $booker_id,
            'year' => $year,
            'month' => $month,
            'view' => $view,
            'status_filter' => $status_filter
        ]);
        exit;
    }

    /**
     * Traitement AJAX pour créer une réservation manuelle
     */
    public function ajaxProcessCreateReservation() {
        $booker_id = (int)Tools::getValue('booker_id');
        $date_reserved = Tools::getValue('date_reserved');
        $hour_from = (int)Tools::getValue('hour_from');
        $hour_to = (int)Tools::getValue('hour_to');
        $customer_name = Tools::getValue('customer_name');
        $customer_email = Tools::getValue('customer_email');
        $customer_phone = Tools::getValue('customer_phone');
        $notes = Tools::getValue('notes', '');
        $status = (int)Tools::getValue('status', BookerAuthReserved::STATUS_PENDING);
        
        if (!$booker_id || !$date_reserved || !$hour_from || !$hour_to) {
            echo json_encode(['success' => false, 'error' => 'Paramètres manquants']);
            exit;
        }

        // Vérifier que le créneau est disponible
        if (!$this->isSlotAvailable($booker_id, $date_reserved, $hour_from, $hour_to)) {
            echo json_encode(['success' => false, 'error' => 'Créneau non disponible']);
            exit;
        }

        // Créer la réservation
        $reservation = new BookerAuthReserved();
        $reservation->id_booker = $booker_id;
        $reservation->date_reserved = $date_reserved;
        $reservation->hour_from = $hour_from;
        $reservation->hour_to = $hour_to;
        $reservation->status = $status;
        $reservation->active = 1;
        $reservation->date_add = date('Y-m-d H:i:s');
        $reservation->date_upd = date('Y-m-d H:i:s');

        if ($reservation->add()) {
            // Stocker les informations client (à adapter selon votre structure)
            $this->saveCustomerInfo($reservation->id, $customer_name, $customer_email, $customer_phone, $notes);
            
            echo json_encode([
                'success' => true, 
                'id' => $reservation->id,
                'reservation' => $this->formatReservationForCalendar($reservation)
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Erreur lors de la création']);
        }
        exit;
    }

    /**
     * Traitement AJAX pour changer le statut d'une réservation
     */
    public function ajaxProcessChangeStatus() {
        $id = (int)Tools::getValue('id');
        $new_status = (int)Tools::getValue('status');
        
        $reservation = new BookerAuthReserved($id);
        if (!Validate::isLoadedObject($reservation)) {
            echo json_encode(['success' => false, 'error' => 'Réservation introuvable']);
            exit;
        }

        // Vérifier les conflits si on accepte la réservation
        if (in_array($new_status, [BookerAuthReserved::STATUS_ACCEPTED, BookerAuthReserved::STATUS_PAID])) {
            $reservation->status = $new_status;
            if ($reservation->hasConflict()) {
                echo json_encode(['success' => false, 'error' => 'Conflit avec une autre réservation']);
                exit;
            }
        }

        if ($reservation->changeStatus($new_status)) {
            // Si réservation acceptée ou payée, créer une commande PrestaShop
            if ($new_status === BookerAuthReserved::STATUS_ACCEPTED) {
                $this->createPendingOrder($reservation);
            }
            
            echo json_encode([
                'success' => true,
                'reservation' => $this->formatReservationForCalendar($reservation)
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Erreur lors du changement de statut']);
        }
        exit;
    }

    /**
     * Traitement AJAX pour la sélection multiple de réservations
     */
    public function ajaxProcessBulkReservations() {
        $action = Tools::getValue('action');
        $reservation_ids = Tools::getValue('reservation_ids');
        
        if (!$action || !$reservation_ids || !is_array($reservation_ids)) {
            echo json_encode(['success' => false, 'error' => 'Paramètres invalides']);
            exit;
        }

        $success_count = 0;
        $error_count = 0;
        $errors = [];

        foreach ($reservation_ids as $id) {
            $reservation = new BookerAuthReserved((int)$id);
            if (!Validate::isLoadedObject($reservation)) {
                $error_count++;
                $errors[] = "Réservation $id introuvable";
                continue;
            }

            try {
                switch ($action) {
                    case 'accept':
                        if ($reservation->changeStatus(BookerAuthReserved::STATUS_ACCEPTED)) {
                            $this->createPendingOrder($reservation);
                            $success_count++;
                        } else {
                            $error_count++;
                            $errors[] = "Erreur acceptation $id";
                        }
                        break;
                        
                    case 'cancel':
                        if ($reservation->changeStatus(BookerAuthReserved::STATUS_CANCELLED)) {
                            $success_count++;
                        } else {
                            $error_count++;
                            $errors[] = "Erreur annulation $id";
                        }
                        break;
                        
                    case 'delete':
                        if ($reservation->delete()) {
                            $success_count++;
                        } else {
                            $error_count++;
                            $errors[] = "Erreur suppression $id";
                        }
                        break;
                }
            } catch (Exception $e) {
                $error_count++;
                $errors[] = $e->getMessage();
            }
        }

        echo json_encode([
            'success' => $error_count === 0,
            'success_count' => $success_count,
            'error_count' => $error_count,
            'errors' => $errors
        ]);
        exit;
    }

    /**
     * Traitement AJAX pour obtenir les créneaux disponibles
     */
    public function ajaxProcessGetAvailableSlots() {
        $booker_id = (int)Tools::getValue('booker_id');
        $date = Tools::getValue('date');
        
        if (!$booker_id || !$date) {
            echo json_encode(['success' => false, 'error' => 'Paramètres manquants']);
            exit;
        }

        $slots = BookerAuthReserved::getAvailableSlots($booker_id, $date);
        
        echo json_encode([
            'success' => true,
            'slots' => $slots
        ]);
        exit;
    }

    /**
     * Obtenir les données du calendrier
     */
    private function getCalendarData($booker_id, $year, $month, $view, $status_filter = 'all') {
        $data = [];
        
        switch ($view) {
            case 'month':
                $data = $this->getMonthReservations($booker_id, $year, $month, $status_filter);
                break;
            case 'week':
                $week = (int)Tools::getValue('week', 1);
                $data = $this->getWeekReservations($booker_id, $year, $week, $status_filter);
                break;
            case 'day':
                $day = Tools::getValue('day', date('Y-m-d'));
                $data = $this->getDayReservations($booker_id, $day, $status_filter);
                break;
        }
        
        return $data;
    }

    /**
     * Obtenir les réservations pour la vue mensuelle
     */
    private function getMonthReservations($booker_id, $year, $month, $status_filter) {
        $first_day = date('Y-m-01', mktime(0, 0, 0, $month, 1, $year));
        $last_day = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));
        
        $sql = 'SELECT r.*, c.customer_name, c.customer_email, c.customer_phone, c.notes
                FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` r
                LEFT JOIN `' . _DB_PREFIX_ . 'booker_reservation_customer` c ON (r.id_reserved = c.id_reservation)
                WHERE r.id_booker = ' . (int)$booker_id . '
                AND r.active = 1
                AND r.date_reserved >= "' . pSQL($first_day) . '"
                AND r.date_reserved <= "' . pSQL($last_day) . '"';
        
        if ($status_filter !== 'all') {
            $sql .= ' AND r.status = ' . (int)$status_filter;
        }
        
        $sql .= ' ORDER BY r.date_reserved ASC, r.hour_from ASC';
        
        $reservations = Db::getInstance()->executeS($sql);
        
        // Récupérer aussi les disponibilités pour affichage
        $availabilities = Db::getInstance()->executeS('
            SELECT * FROM `' . _DB_PREFIX_ . 'booker_auth`
            WHERE id_booker = ' . (int)$booker_id . '
            AND active = 1
            AND (
                (DATE(date_from) <= "' . pSQL($last_day) . '" AND DATE(date_to) >= "' . pSQL($first_day) . '")
            )
            ORDER BY date_from ASC
        ');

        return [
            'reservations' => array_map([$this, 'formatReservationForCalendar'], $reservations),
            'availabilities' => $availabilities,
            'month_info' => [
                'year' => $year,
                'month' => $month,
                'first_day' => $first_day,
                'last_day' => $last_day,
                'days_in_month' => date('t', mktime(0, 0, 0, $month, 1, $year))
            ]
        ];
    }

    /**
     * Vérifier si un créneau est disponible
     */
    private function isSlotAvailable($booker_id, $date, $hour_from, $hour_to) {
        // 1. Vérifier qu'il y a une disponibilité pour ce booker à cette date
        $availability_count = Db::getInstance()->getValue('
            SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth`
            WHERE id_booker = ' . (int)$booker_id . '
            AND active = 1
            AND DATE(date_from) <= "' . pSQL($date) . '"
            AND DATE(date_to) >= "' . pSQL($date) . '"
        ');
        
        if (!$availability_count) {
            return false;
        }
        
        // 2. Vérifier qu'il n'y a pas de conflit avec d'autres réservations
        $conflict_count = Db::getInstance()->getValue('
            SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`
            WHERE id_booker = ' . (int)$booker_id . '
            AND date_reserved = "' . pSQL($date) . '"
            AND status IN (' . BookerAuthReserved::STATUS_ACCEPTED . ', ' . BookerAuthReserved::STATUS_PAID . ')
            AND active = 1
            AND hour_from < ' . (int)$hour_to . '
            AND hour_to > ' . (int)$hour_from
        );
        
        return !$conflict_count;
    }

    /**
     * Sauvegarder les informations client
     */
    private function saveCustomerInfo($reservation_id, $name, $email, $phone, $notes) {
        // Créer une table pour les infos clients si elle n'existe pas
        Db::getInstance()->execute('
            CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'booker_reservation_customer` (
                `id_reservation` int(10) unsigned NOT NULL,
                `customer_name` varchar(255) DEFAULT NULL,
                `customer_email` varchar(255) DEFAULT NULL,
                `customer_phone` varchar(50) DEFAULT NULL,
                `notes` text DEFAULT NULL,
                PRIMARY KEY (`id_reservation`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8
        ');
        
        return Db::getInstance()->insert('booker_reservation_customer', [
            'id_reservation' => (int)$reservation_id,
            'customer_name' => pSQL($name),
            'customer_email' => pSQL($email),
            'customer_phone' => pSQL($phone),
            'notes' => pSQL($notes)
        ]);
    }

    /**
     * Créer une commande en attente de paiement
     */
    private function createPendingOrder($reservation) {
        // À implémenter : création d'une commande PrestaShop
        // Ceci dépendra de votre logique métier pour associer les bookers aux produits
        
        // Exemple basique :
        // 1. Créer un panier
        // 2. Ajouter le produit correspondant au booker
        // 3. Créer la commande avec statut "en attente de paiement"
        // 4. Associer la réservation à la commande
        
        return true; // Placeholder
    }

    /**
     * Formater une réservation pour l'affichage dans le calendrier
     */
    private function formatReservationForCalendar($reservation) {
        if (is_array($reservation)) {
            $reservation = (object)$reservation;
        }
        
        return [
            'id' => $reservation->id_reserved,
            'booker_id' => $reservation->id_booker,
            'date' => $reservation->date_reserved,
            'hour_from' => $reservation->hour_from,
            'hour_to' => $reservation->hour_to,
            'status' => $reservation->status,
            'status_label' => BookerAuthReserved::getStatusLabel($reservation->status),
            'customer_name' => isset($reservation->customer_name) ? $reservation->customer_name : '',
            'customer_email' => isset($reservation->customer_email) ? $reservation->customer_email : '',
            'customer_phone' => isset($reservation->customer_phone) ? $reservation->customer_phone : '',
            'notes' => isset($reservation->notes) ? $reservation->notes : '',
            'date_add' => $reservation->date_add,
            'css_class' => $this->getStatusCssClass($reservation->status)
        ];
    }

    /**
     * Obtenir la classe CSS selon le statut
     */
    private function getStatusCssClass($status) {
        switch ($status) {
            case BookerAuthReserved::STATUS_PENDING:
                return 'reservation-pending';
            case BookerAuthReserved::STATUS_ACCEPTED:
                return 'reservation-accepted';
            case BookerAuthReserved::STATUS_PAID:
                return 'reservation-paid';
            case BookerAuthReserved::STATUS_CANCELLED:
                return 'reservation-cancelled';
            case BookerAuthReserved::STATUS_EXPIRED:
                return 'reservation-expired';
            default:
                return 'reservation-unknown';
        }
    }

    /**
     * Rendu du calendrier des réservations
     */
    private function renderReservationCalendar() {
        // Récupérer tous les bookers actifs
        $bookers = Booker::getActiveBookers();
        
        // Récupérer les statuts pour les filtres
        $statuses = BookerAuthReserved::getStatuses();
        
        $this->context->smarty->assign([
            'bookers' => $bookers,
            'statuses' => $statuses,
            'current_year' => date('Y'),
            'current_month' => date('m'),
            'calendar_type' => 'reservation',
            'ajax_url' => $this->context->link->getAdminLink('AdminBookerReservationCalendar')
        ]);
        
        return $this->context->smarty->fetch($this->getTemplatePath().'reservation_calendar.tpl');
    }
}