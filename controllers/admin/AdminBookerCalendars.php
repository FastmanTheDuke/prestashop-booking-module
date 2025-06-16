<?php
/**
 * Contrôleur pour les calendriers doubles - Disponibilités et Réservations
 * Interface moderne avec FullCalendar et fonctionnalités avancées
 */

require_once(dirname(__FILE__) . '/../../classes/Booker.php');
require_once(dirname(__FILE__) . '/../../classes/BookerAuth.php');
require_once(dirname(__FILE__) . '/../../classes/BookerAuthReserved.php');

class AdminBookerCalendarsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->display = 'view';
        $this->meta_title = 'Calendriers - Disponibilités et Réservations';
        
        parent::__construct();
    }

    /**
     * Rendu principal de la page des calendriers
     */
    public function renderView()
    {
        try {
            $this->initPageHeaderToolbar();
            
            // Récupération des données
            $bookers = $this->getActiveBookers();
            $availability_events = $this->getAvailabilityEvents();
            $reservation_events = $this->getReservationEvents();
            $calendar_config = $this->getCalendarConfig();
            
            // Préparation des données pour les calendriers
            $this->context->smarty->assign(array(
                'bookers' => $bookers,
                'availability_events' => json_encode($availability_events),
                'reservation_events' => json_encode($reservation_events),
                'calendar_config' => $calendar_config,
                'ajax_url' => $this->context->link->getAdminLink('AdminBookerCalendars'),
                'current_booker' => Tools::getValue('id_booker', 0),
                'view_mode' => Tools::getValue('view_mode', 'month'),
                'show_legend' => true,
                'enable_multiselect' => Configuration::get('BOOKING_MULTI_SELECT', 1),
                'business_hours' => $this->getBusinessHours(),
                'time_slots' => $this->getTimeSlots()
            ));
            
            // Ajouter les CSS et JS spécifiques
            $this->addCalendarAssets();
            
            // Chemin du template
            $template_path = dirname(__FILE__) . '/../../views/templates/admin/calendars_double.tpl';
            
            if (file_exists($template_path)) {
                return $this->context->smarty->fetch($template_path);
            } else {
                return $this->generateDefaultCalendarView();
            }
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog('AdminBookerCalendars::renderView() - Erreur: ' . $e->getMessage(), 3);
            return $this->displayError($this->l('Erreur lors du chargement des calendriers'));
        }
    }
    
    /**
     * Barre d'outils de la page
     */
    public function initPageHeaderToolbar()
    {
        $this->page_header_toolbar_btn['new_availability'] = array(
            'href' => '#',
            'desc' => $this->l('Nouvelle disponibilité'),
            'icon' => 'process-icon-new',
            'class' => 'btn-success',
            'js' => 'openAvailabilityModal()'
        );
        
        $this->page_header_toolbar_btn['sync_calendar'] = array(
            'href' => self::$currentIndex . '&action=syncCalendar&token=' . $this->token,
            'desc' => $this->l('Synchroniser'),
            'icon' => 'process-icon-refresh'
        );
        
        $this->page_header_toolbar_btn['export_calendar'] = array(
            'href' => self::$currentIndex . '&action=exportCalendar&token=' . $this->token,
            'desc' => $this->l('Exporter'),
            'icon' => 'process-icon-export'
        );
        
        $this->page_header_toolbar_btn['settings'] = array(
            'href' => $this->context->link->getAdminLink('AdminBookerSettings'),
            'desc' => $this->l('Paramètres'),
            'icon' => 'process-icon-cogs'
        );
        
        parent::initPageHeaderToolbar();
    }
    
    /**
     * Traitement des actions AJAX
     */
    public function postProcess()
    {
        if (Tools::isSubmit('ajax')) {
            $action = Tools::getValue('action');
            
            switch ($action) {
                case 'getEvents':
                    $this->ajaxGetEvents();
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
                case 'updateReservation':
                    $this->ajaxUpdateReservation();
                    break;
                case 'getBookerDetails':
                    $this->ajaxGetBookerDetails();
                    break;
                case 'bulkAvailability':
                    $this->ajaxBulkAvailability();
                    break;
                default:
                    $this->ajaxReturn(array('error' => 'Action inconnue'));
            }
        }
        
        // Actions non-AJAX
        if (Tools::getValue('action') === 'syncCalendar') {
            $this->syncCalendar();
        } elseif (Tools::getValue('action') === 'exportCalendar') {
            $this->exportCalendar();
        }
        
        return parent::postProcess();
    }
    
    /**
     * Récupérer les bookers actifs
     */
    private function getActiveBookers()
    {
        try {
            $sql = 'SELECT b.*, p.reference as product_reference, pl.name as product_name 
                    FROM `' . _DB_PREFIX_ . 'booker` b
                    LEFT JOIN `' . _DB_PREFIX_ . 'product` p ON b.id_product = p.id_product
                    LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON (p.id_product = pl.id_product AND pl.id_lang = ' . (int)$this->context->language->id . ')
                    WHERE b.active = 1
                    ORDER BY b.sort_order ASC, b.name ASC';
                    
            $results = Db::getInstance()->executeS($sql);
            
            $bookers = [];
            foreach ($results as $row) {
                $bookers[] = [
                    'id_booker' => (int)$row['id_booker'],
                    'id_product' => (int)$row['id_product'],
                    'name' => $row['name'] ?: $row['product_name'],
                    'description' => $row['description'],
                    'location' => $row['location'],
                    'capacity' => (int)$row['capacity'],
                    'price' => (float)$row['price'],
                    'duration' => (int)$row['booking_duration'],
                    'auto_confirm' => (bool)$row['auto_confirm'],
                    'color' => $this->generateBookerColor($row['id_booker']),
                    'product_reference' => $row['product_reference']
                ];
            }
            
            return $bookers;
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog('AdminBookerCalendars::getActiveBookers() - Erreur: ' . $e->getMessage(), 3);
            return [];
        }
    }
    
    /**
     * Récupérer les événements de disponibilité
     */
    private function getAvailabilityEvents()
    {
        $start_date = Tools::getValue('start', date('Y-m-01'));
        $end_date = Tools::getValue('end', date('Y-m-t', strtotime('+2 months')));
        $id_booker = Tools::getValue('id_booker', 0);
        
        $where_booker = $id_booker ? ' AND ba.id_booker = ' . (int)$id_booker : '';
        
        $sql = 'SELECT ba.*, b.name as booker_name, b.color
                FROM `' . _DB_PREFIX_ . 'booker_auth` ba
                LEFT JOIN `' . _DB_PREFIX_ . 'booker` b ON ba.id_booker = b.id_booker
                WHERE ba.active = 1
                AND ba.date_from >= "' . pSQL($start_date) . '"
                AND ba.date_to <= "' . pSQL($end_date) . '"
                ' . $where_booker . '
                ORDER BY ba.date_from ASC';
        
        $results = Db::getInstance()->executeS($sql);
        $events = [];
        
        foreach ($results as $row) {
            $events[] = [
                'id' => 'availability_' . $row['id_auth'],
                'title' => 'Disponible - ' . $row['booker_name'],
                'start' => $row['date_from'],
                'end' => $row['date_to'],
                'allDay' => false,
                'backgroundColor' => $this->lightenColor($row['color'] ?: '#28a745', 0.7),
                'borderColor' => $row['color'] ?: '#28a745',
                'textColor' => '#000',
                'classNames' => ['availability-event'],
                'extendedProps' => [
                    'type' => 'availability',
                    'id_auth' => $row['id_auth'],
                    'id_booker' => $row['id_booker'],
                    'booker_name' => $row['booker_name'],
                    'max_bookings' => $row['max_bookings'],
                    'current_bookings' => $row['current_bookings'],
                    'price_override' => $row['price_override'],
                    'notes' => $row['notes']
                ]
            ];
        }
        
        return $events;
    }
    
    /**
     * Récupérer les événements de réservation
     */
    private function getReservationEvents()
    {
        $start_date = Tools::getValue('start', date('Y-m-01'));
        $end_date = Tools::getValue('end', date('Y-m-t', strtotime('+2 months')));
        $id_booker = Tools::getValue('id_booker', 0);
        
        $where_booker = $id_booker ? ' AND r.id_booker = ' . (int)$id_booker : '';
        
        $sql = 'SELECT r.*, b.name as booker_name, 
                       CONCAT(r.customer_firstname, " ", r.customer_lastname) as customer_name
                FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` r
                LEFT JOIN `' . _DB_PREFIX_ . 'booker` b ON r.id_booker = b.id_booker
                WHERE r.date_reserved >= "' . pSQL($start_date) . '"
                AND r.date_reserved <= "' . pSQL($end_date) . '"
                ' . $where_booker . '
                ORDER BY r.date_reserved ASC';
        
        $results = Db::getInstance()->executeS($sql);
        $events = [];
        
        foreach ($results as $row) {
            $status_colors = [
                0 => '#ffc107', // En attente - Jaune
                1 => '#17a2b8', // Acceptée - Bleu
                2 => '#28a745', // Payée - Vert
                3 => '#dc3545', // Annulée - Rouge
                4 => '#6c757d', // Expirée - Gris
                5 => '#007bff'  // Terminée - Bleu foncé
            ];
            
            $status_labels = [
                0 => 'En attente',
                1 => 'Acceptée',
                2 => 'Payée',
                3 => 'Annulée',
                4 => 'Expirée',
                5 => 'Terminée'
            ];
            
            $start_datetime = $row['date_reserved'] . 'T' . sprintf('%02d:00:00', $row['hour_from']);
            $end_datetime = $row['date_reserved'] . 'T' . sprintf('%02d:00:00', $row['hour_to']);
            
            $events[] = [
                'id' => 'reservation_' . $row['id_reserved'],
                'title' => $row['customer_name'] . ' - ' . $row['booker_name'],
                'start' => $start_datetime,
                'end' => $end_datetime,
                'allDay' => false,
                'backgroundColor' => $status_colors[$row['status']] ?? '#6c757d',
                'borderColor' => $status_colors[$row['status']] ?? '#6c757d',
                'textColor' => '#fff',
                'classNames' => ['reservation-event', 'status-' . $row['status']],
                'extendedProps' => [
                    'type' => 'reservation',
                    'id_reserved' => $row['id_reserved'],
                    'id_booker' => $row['id_booker'],
                    'booker_name' => $row['booker_name'],
                    'customer_name' => $row['customer_name'],
                    'customer_email' => $row['customer_email'],
                    'customer_phone' => $row['customer_phone'],
                    'booking_reference' => $row['booking_reference'],
                    'status' => $row['status'],
                    'status_label' => $status_labels[$row['status']] ?? 'Inconnu',
                    'total_price' => $row['total_price'],
                    'payment_status' => $row['payment_status'],
                    'notes' => $row['notes']
                ]
            ];
        }
        
        return $events;
    }
    
    /**
     * Configuration du calendrier
     */
    private function getCalendarConfig()
    {
        return [
            'locale' => 'fr',
            'firstDay' => 1, // Lundi
            'weekNumbers' => true,
            'weekNumberFormat' => ['week' => 'numeric'],
            'timeZone' => 'Europe/Paris',
            'slotMinTime' => Configuration::get('BOOKING_CALENDAR_MIN_TIME', '08:00:00'),
            'slotMaxTime' => Configuration::get('BOOKING_CALENDAR_MAX_TIME', '20:00:00'),
            'slotDuration' => Configuration::get('BOOKING_SLOT_DURATION', '00:30:00'),
            'businessHours' => $this->getBusinessHours(),
            'selectable' => true,
            'selectMirror' => true,
            'editable' => true,
            'dayMaxEvents' => true,
            'weekends' => true,
            'nowIndicator' => true,
            'height' => 600
        ];
    }
    
    /**
     * Heures d'ouverture
     */
    private function getBusinessHours()
    {
        $allowed_days = explode(',', Configuration::get('BOOKING_ALLOWED_DAYS', '1,2,3,4,5,6,7'));
        $start_time = Configuration::get('BOOKING_BUSINESS_HOURS_START', '08:00');
        $end_time = Configuration::get('BOOKING_BUSINESS_HOURS_END', '18:00');
        
        return [
            'daysOfWeek' => array_map('intval', $allowed_days),
            'startTime' => $start_time,
            'endTime' => $end_time
        ];
    }
    
    /**
     * Créneaux horaires
     */
    private function getTimeSlots()
    {
        $slots = [];
        $start = 8; // 8h
        $end = 20;  // 20h
        $interval = 30; // 30 minutes
        
        for ($hour = $start; $hour < $end; $hour++) {
            for ($minute = 0; $minute < 60; $minute += $interval) {
                $time = sprintf('%02d:%02d', $hour, $minute);
                $slots[] = [
                    'value' => $time,
                    'label' => $time
                ];
            }
        }
        
        return $slots;
    }
    
    /**
     * Ajouter les assets CSS/JS pour les calendriers
     */
    private function addCalendarAssets()
    {
        // CSS
        $this->context->controller->addCSS([
            'https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.8/main.min.css',
            $this->module->getPathUri() . 'views/css/calendars-double.css'
        ]);
        
        // JavaScript
        $this->context->controller->addJS([
            'https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.8/index.global.min.js',
            'https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.8/locales/fr.global.min.js',
            $this->module->getPathUri() . 'views/js/calendars-double.js'
        ]);
    }
    
    /**
     * Vue par défaut si le template n'existe pas
     */
    private function generateDefaultCalendarView()
    {
        $bookers = $this->getActiveBookers();
        
        $html = '
        <div class="row">
            <div class="col-lg-12">
                <div class="panel">
                    <div class="panel-heading">
                        <i class="icon-calendar"></i> ' . $this->l('Calendriers de Réservations') . '
                        <div class="panel-actions">
                            <div class="btn-group">
                                <button class="btn btn-sm btn-default dropdown-toggle" data-toggle="dropdown">
                                    <i class="icon-filter"></i> Filtrer par élément <span class="caret"></span>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a href="#" data-booker="0">Tous les éléments</a></li>';
                                    
        foreach ($bookers as $booker) {
            $html .= '<li><a href="#" data-booker="' . $booker['id_booker'] . '">' . htmlspecialchars($booker['name']) . '</a></li>';
        }
        
        $html .= '
                                </ul>
                            </div>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-success" onclick="openAvailabilityModal()">
                                    <i class="icon-plus"></i> Nouvelle disponibilité
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="panel-body">
                        <!-- Calendrier des disponibilités -->
                        <div class="row">
                            <div class="col-lg-6">
                                <h4><i class="icon-time"></i> Disponibilités</h4>
                                <div id="availability-calendar" style="height: 500px; border: 1px solid #ddd; border-radius: 4px;"></div>
                            </div>
                            <div class="col-lg-6">
                                <h4><i class="icon-calendar-check-o"></i> Réservations</h4>
                                <div id="reservation-calendar" style="height: 500px; border: 1px solid #ddd; border-radius: 4px;"></div>
                            </div>
                        </div>
                        
                        <!-- Légende -->
                        <div class="row" style="margin-top: 20px;">
                            <div class="col-lg-12">
                                <div class="alert alert-info">
                                    <strong>Légende:</strong>
                                    <span class="label" style="background-color: #28a745; margin-left: 10px;">Disponibilité</span>
                                    <span class="label" style="background-color: #ffc107; margin-left: 10px;">En attente</span>
                                    <span class="label" style="background-color: #17a2b8; margin-left: 10px;">Acceptée</span>
                                    <span class="label" style="background-color: #28a745; margin-left: 10px;">Payée</span>
                                    <span class="label" style="background-color: #dc3545; margin-left: 10px;">Annulée</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Modales -->
        <div id="availability-modal" class="modal fade" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title">Ajouter une disponibilité</h4>
                    </div>
                    <div class="modal-body">
                        <form id="availability-form">
                            <div class="form-group">
                                <label>Élément à réserver</label>
                                <select name="id_booker" class="form-control" required>';
                                
        foreach ($bookers as $booker) {
            $html .= '<option value="' . $booker['id_booker'] . '">' . htmlspecialchars($booker['name']) . '</option>';
        }
        
        $html .= '
                                </select>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Date de début</label>
                                        <input type="date" name="date_from" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Date de fin</label>
                                        <input type="date" name="date_to" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Heure de début</label>
                                        <input type="time" name="time_from" class="form-control" value="08:00" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Heure de fin</label>
                                        <input type="time" name="time_to" class="form-control" value="18:00" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Nombre maximum de réservations</label>
                                <input type="number" name="max_bookings" class="form-control" value="1" min="1" required>
                            </div>
                            <div class="form-group">
                                <label>Prix de substitution (optionnel)</label>
                                <input type="number" name="price_override" class="form-control" step="0.01" placeholder="Laisser vide pour utiliser le prix par défaut">
                            </div>
                            <div class="form-group">
                                <label>Notes</label>
                                <textarea name="notes" class="form-control" rows="3" placeholder="Notes internes..."></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Annuler</button>
                        <button type="button" class="btn btn-success" onclick="saveAvailability()">Sauvegarder</button>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        // Initialisation basique des calendriers
        document.addEventListener("DOMContentLoaded", function() {
            // Placeholder pour le calendrier FullCalendar
            $("#availability-calendar").html("<div class=\"alert alert-info text-center\"><i class=\"icon-info\"></i><br>Calendrier des disponibilités<br><small>Implémentation FullCalendar en cours...</small></div>");
            $("#reservation-calendar").html("<div class=\"alert alert-info text-center\"><i class=\"icon-info\"></i><br>Calendrier des réservations<br><small>Implémentation FullCalendar en cours...</small></div>");
        });
        
        function openAvailabilityModal() {
            $("#availability-modal").modal("show");
        }
        
        function saveAvailability() {
            // Placeholder pour la sauvegarde
            alert("Fonctionnalité de sauvegarde en cours d\'implémentation");
            $("#availability-modal").modal("hide");
        }
        </script>';
        
        return $html;
    }
    
    /**
     * Actions AJAX
     */
    public function ajaxGetEvents()
    {
        $type = Tools::getValue('type', 'all');
        $start = Tools::getValue('start');
        $end = Tools::getValue('end');
        $id_booker = Tools::getValue('id_booker', 0);
        
        $events = [];
        
        if ($type === 'availability' || $type === 'all') {
            $events = array_merge($events, $this->getAvailabilityEvents());
        }
        
        if ($type === 'reservation' || $type === 'all') {
            $events = array_merge($events, $this->getReservationEvents());
        }
        
        $this->ajaxReturn(['events' => $events]);
    }
    
    /**
     * Utilitaires
     */
    private function generateBookerColor($id_booker)
    {
        $colors = ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1', '#fd7e14', '#20c997', '#6c757d'];
        return $colors[$id_booker % count($colors)];
    }
    
    private function lightenColor($color, $percent)
    {
        $hex = str_replace('#', '', $color);
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        $r = min(255, $r + ($percent * (255 - $r)));
        $g = min(255, $g + ($percent * (255 - $g)));
        $b = min(255, $b + ($percent * (255 - $b)));
        
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
    
    private function ajaxReturn($data)
    {
        header('Content-Type: application/json');
        die(json_encode($data));
    }
}