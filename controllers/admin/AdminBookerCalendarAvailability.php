<?php
/**
 * Contrôleur pour le calendrier de gestion des disponibilités
 * Interface calendrier dédiée à la création et gestion des créneaux disponibles
 */

require_once(dirname(__FILE__) . '/../../classes/Booker.php');
require_once(dirname(__FILE__) . '/../../classes/BookerAuth.php');

class AdminBookerCalendarAvailabilityController extends ModuleAdminController
{
    protected $_module = NULL;
    public $controller_type = 'admin';

    public function __construct()
    {
        $this->display = 'view';
        $this->context = Context::getContext();
        $this->bootstrap = true;
        $this->table = 'booker_auth';
        $this->identifier = 'id_auth';
        $this->className = 'BookerAuth';
        $this->meta_title = 'Calendrier des Disponibilités';
        
        parent::__construct();
    }

    /**
     * Initialisation du contenu principal
     */
    public function initContent()
    {
        // Charger les ressources nécessaires
        $this->addCSS([
            'https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.8/main.min.css',
            $this->module->getPathUri() . 'views/css/admin-calendar.css'
        ]);
        
        $this->addJS([
            'https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.8/index.global.min.js',
            'https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js',
            $this->module->getPathUri() . 'views/js/availability-calendar.js'
        ]);

        // Récupérer les bookers actifs
        $bookers = $this->getActiveBookers();
        
        // Paramètres de configuration du calendrier
        $calendar_config = [
            'firstDay' => Configuration::get('BOOKING_CALENDAR_FIRST_DAY', 1),
            'minTime' => Configuration::get('BOOKING_CALENDAR_MIN_TIME', '08:00'),
            'maxTime' => Configuration::get('BOOKING_CALENDAR_MAX_TIME', '20:00'),
            'slotDuration' => Configuration::get('BOOKING_SLOT_DURATION', '00:30:00'),
            'businessHours' => [
                'startTime' => Configuration::get('BOOKING_BUSINESS_HOURS_START', '08:00'),
                'endTime' => Configuration::get('BOOKING_BUSINESS_HOURS_END', '18:00'),
                'daysOfWeek' => explode(',', Configuration::get('BOOKING_ALLOWED_DAYS', '1,2,3,4,5,6,7'))
            ]
        ];

        $this->context->smarty->assign([
            'bookers' => $bookers,
            'calendar_config' => $calendar_config,
            'ajax_url' => $this->context->link->getAdminLink('AdminBookerCalendarAvailability'),
            'current_index' => self::$currentIndex,
            'token' => $this->token,
            'module_path' => $this->module->getPathUri(),
            'can_edit' => true,
            'calendar_type' => 'availability'
        ]);

        parent::initContent();
    }

    /**
     * Rendu de la vue du calendrier
     */
    public function renderView()
    {
        $template_path = $this->module->getLocalPath() . 'views/templates/admin/calendar_availability.tpl';
        
        if (!file_exists($template_path)) {
            // Créer le template de base si il n'existe pas
            $this->createDefaultTemplate();
        }
        
        return $this->context->smarty->fetch($template_path);
    }

    /**
     * Récupérer les bookers actifs
     */
    private function getActiveBookers()
    {
        return Db::getInstance()->executeS('
            SELECT b.id_booker, b.name, b.location, b.price, b.capacity
            FROM `' . _DB_PREFIX_ . 'booker` b
            WHERE b.active = 1
            ORDER BY b.sort_order ASC, b.name ASC
        ');
    }

    /**
     * AJAX : Récupérer les disponibilités pour le calendrier
     */
    public function ajaxProcessGetAvailabilities()
    {
        $id_booker = (int)Tools::getValue('id_booker');
        $start = Tools::getValue('start');
        $end = Tools::getValue('end');
        
        $sql = 'SELECT 
            a.id_auth,
            a.id_booker,
            a.date_from,
            a.date_to,
            a.time_from,
            a.time_to,
            a.max_bookings,
            a.current_bookings,
            a.price_override,
            a.active,
            a.recurring,
            a.notes,
            b.name as booker_name,
            b.price as booker_price
        FROM `' . _DB_PREFIX_ . 'booker_auth` a
        LEFT JOIN `' . _DB_PREFIX_ . 'booker` b ON (a.id_booker = b.id_booker)
        WHERE a.active = 1
        AND DATE(a.date_from) >= "' . pSQL($start) . '"
        AND DATE(a.date_to) <= "' . pSQL($end) . '"';
        
        if ($id_booker > 0) {
            $sql .= ' AND a.id_booker = ' . (int)$id_booker;
        }
        
        $sql .= ' ORDER BY a.date_from ASC';
        
        $availabilities = Db::getInstance()->executeS($sql);
        $events = [];
        
        foreach ($availabilities as $availability) {
            $events[] = [
                'id' => 'avail_' . $availability['id_auth'],
                'title' => $availability['booker_name'] . ' (' . $availability['current_bookings'] . '/' . $availability['max_bookings'] . ')',
                'start' => $availability['date_from'],
                'end' => $availability['date_to'],
                'backgroundColor' => $this->getAvailabilityColor($availability),
                'borderColor' => $this->getAvailabilityBorderColor($availability),
                'extendedProps' => [
                    'type' => 'availability',
                    'id_auth' => $availability['id_auth'],
                    'id_booker' => $availability['id_booker'],
                    'booker_name' => $availability['booker_name'],
                    'max_bookings' => $availability['max_bookings'],
                    'current_bookings' => $availability['current_bookings'],
                    'price' => $availability['price_override'] ?: $availability['booker_price'],
                    'notes' => $availability['notes'],
                    'recurring' => $availability['recurring']
                ]
            ];
        }
        
        die(json_encode($events));
    }

    /**
     * AJAX : Créer une nouvelle disponibilité
     */
    public function ajaxProcessCreateAvailability()
    {
        $id_booker = (int)Tools::getValue('id_booker');
        $date_from = Tools::getValue('date_from');
        $date_to = Tools::getValue('date_to');
        $time_from = Tools::getValue('time_from', '08:00');
        $time_to = Tools::getValue('time_to', '18:00');
        $max_bookings = (int)Tools::getValue('max_bookings', 1);
        $price_override = Tools::getValue('price_override');
        $notes = Tools::getValue('notes');
        $recurring = (int)Tools::getValue('recurring', 0);
        $recurring_type = Tools::getValue('recurring_type');
        $recurring_end = Tools::getValue('recurring_end');

        if (!$id_booker || !$date_from || !$date_to) {
            die(json_encode(['success' => false, 'message' => 'Paramètres manquants']));
        }

        // Vérifier que le booker existe
        $booker = new Booker($id_booker);
        if (!Validate::isLoadedObject($booker)) {
            die(json_encode(['success' => false, 'message' => 'Booker introuvable']));
        }

        // Créer la disponibilité
        $availability = new BookerAuth();
        $availability->id_booker = $id_booker;
        $availability->date_from = $date_from . ' ' . $time_from . ':00';
        $availability->date_to = $date_to . ' ' . $time_to . ':00';
        $availability->time_from = $time_from . ':00';
        $availability->time_to = $time_to . ':00';
        $availability->max_bookings = $max_bookings;
        $availability->current_bookings = 0;
        $availability->price_override = $price_override ? (float)$price_override : null;
        $availability->active = 1;
        $availability->recurring = $recurring;
        $availability->recurring_type = $recurring_type;
        $availability->recurring_end = $recurring_end;
        $availability->notes = $notes;
        $availability->date_add = date('Y-m-d H:i:s');
        $availability->date_upd = date('Y-m-d H:i:s');

        if ($availability->add()) {
            // Si récurrence, créer les occurrences
            if ($recurring && $recurring_type && $recurring_end) {
                $this->createRecurringAvailabilities($availability);
            }
            
            die(json_encode([
                'success' => true, 
                'message' => 'Disponibilité créée avec succès',
                'id' => $availability->id
            ]));
        } else {
            die(json_encode(['success' => false, 'message' => 'Erreur lors de la création']));
        }
    }

    /**
     * AJAX : Mettre à jour une disponibilité
     */
    public function ajaxProcessUpdateAvailability()
    {
        $id_auth = (int)Tools::getValue('id_auth');
        $availability = new BookerAuth($id_auth);
        
        if (!Validate::isLoadedObject($availability)) {
            die(json_encode(['success' => false, 'message' => 'Disponibilité introuvable']));
        }

        $availability->date_from = Tools::getValue('date_from') . ' ' . Tools::getValue('time_from', '08:00') . ':00';
        $availability->date_to = Tools::getValue('date_to') . ' ' . Tools::getValue('time_to', '18:00') . ':00';
        $availability->time_from = Tools::getValue('time_from', '08:00') . ':00';
        $availability->time_to = Tools::getValue('time_to', '18:00') . ':00';
        $availability->max_bookings = (int)Tools::getValue('max_bookings', 1);
        $availability->price_override = Tools::getValue('price_override') ? (float)Tools::getValue('price_override') : null;
        $availability->notes = Tools::getValue('notes');
        $availability->date_upd = date('Y-m-d H:i:s');

        if ($availability->update()) {
            die(json_encode(['success' => true, 'message' => 'Disponibilité mise à jour avec succès']));
        } else {
            die(json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']));
        }
    }

    /**
     * AJAX : Supprimer une disponibilité
     */
    public function ajaxProcessDeleteAvailability()
    {
        $id_auth = (int)Tools::getValue('id_auth');
        $availability = new BookerAuth($id_auth);
        
        if (!Validate::isLoadedObject($availability)) {
            die(json_encode(['success' => false, 'message' => 'Disponibilité introuvable']));
        }

        // Vérifier qu'il n'y a pas de réservations actives
        $active_reservations = Db::getInstance()->getValue('
            SELECT COUNT(*) 
            FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` 
            WHERE `id_auth` = ' . (int)$id_auth . ' 
            AND `status` IN (0, 1, 2, 3)
        ');

        if ($active_reservations > 0) {
            die(json_encode(['success' => false, 'message' => 'Impossible de supprimer : des réservations sont actives']));
        }

        if ($availability->delete()) {
            die(json_encode(['success' => true, 'message' => 'Disponibilité supprimée avec succès']));
        } else {
            die(json_encode(['success' => false, 'message' => 'Erreur lors de la suppression']));
        }
    }

    /**
     * AJAX : Actions en lot sur les disponibilités
     */
    public function ajaxProcessBulkAction()
    {
        $action = Tools::getValue('action');
        $ids = Tools::getValue('ids');
        
        if (!is_array($ids) || empty($ids)) {
            die(json_encode(['success' => false, 'message' => 'Aucune sélection']));
        }

        $success_count = 0;
        $error_count = 0;

        switch ($action) {
            case 'activate':
                foreach ($ids as $id) {
                    $availability = new BookerAuth(str_replace('avail_', '', $id));
                    if (Validate::isLoadedObject($availability)) {
                        $availability->active = 1;
                        if ($availability->update()) {
                            $success_count++;
                        } else {
                            $error_count++;
                        }
                    }
                }
                break;

            case 'deactivate':
                foreach ($ids as $id) {
                    $availability = new BookerAuth(str_replace('avail_', '', $id));
                    if (Validate::isLoadedObject($availability)) {
                        $availability->active = 0;
                        if ($availability->update()) {
                            $success_count++;
                        } else {
                            $error_count++;
                        }
                    }
                }
                break;

            case 'delete':
                foreach ($ids as $id) {
                    $availability = new BookerAuth(str_replace('avail_', '', $id));
                    if (Validate::isLoadedObject($availability)) {
                        if ($availability->delete()) {
                            $success_count++;
                        } else {
                            $error_count++;
                        }
                    }
                }
                break;
        }

        die(json_encode([
            'success' => true,
            'message' => "$success_count opération(s) réussie(s), $error_count erreur(s)"
        ]));
    }

    /**
     * Créer des disponibilités récurrentes
     */
    private function createRecurringAvailabilities($original_availability)
    {
        $start_date = new DateTime($original_availability->date_from);
        $end_date = new DateTime($original_availability->recurring_end);
        $current_date = clone $start_date;
        
        $interval_map = [
            'daily' => 'P1D',
            'weekly' => 'P1W',
            'monthly' => 'P1M'
        ];
        
        $interval = new DateInterval($interval_map[$original_availability->recurring_type]);
        
        while ($current_date <= $end_date) {
            $current_date->add($interval);
            
            if ($current_date > $end_date) {
                break;
            }
            
            $new_availability = new BookerAuth();
            $new_availability->id_booker = $original_availability->id_booker;
            $new_availability->date_from = $current_date->format('Y-m-d') . ' ' . $original_availability->time_from;
            $new_availability->date_to = $current_date->format('Y-m-d') . ' ' . $original_availability->time_to;
            $new_availability->time_from = $original_availability->time_from;
            $new_availability->time_to = $original_availability->time_to;
            $new_availability->max_bookings = $original_availability->max_bookings;
            $new_availability->current_bookings = 0;
            $new_availability->price_override = $original_availability->price_override;
            $new_availability->active = 1;
            $new_availability->recurring = 0; // Les occurrences ne sont pas récurrentes
            $new_availability->notes = $original_availability->notes;
            $new_availability->date_add = date('Y-m-d H:i:s');
            $new_availability->date_upd = date('Y-m-d H:i:s');
            
            $new_availability->add();
        }
    }

    /**
     * Obtenir la couleur d'une disponibilité
     */
    private function getAvailabilityColor($availability)
    {
        if (!$availability['active']) {
            return '#cccccc'; // Gris pour inactif
        }
        
        $ratio = $availability['current_bookings'] / $availability['max_bookings'];
        
        if ($ratio == 0) {
            return '#28a745'; // Vert pour libre
        } elseif ($ratio < 0.5) {
            return '#ffc107'; // Jaune pour partiellement réservé
        } elseif ($ratio < 1) {
            return '#fd7e14'; // Orange pour presque complet
        } else {
            return '#dc3545'; // Rouge pour complet
        }
    }

    /**
     * Obtenir la couleur de bordure d'une disponibilité
     */
    private function getAvailabilityBorderColor($availability)
    {
        return $availability['recurring'] ? '#007bff' : $this->getAvailabilityColor($availability);
    }

    /**
     * Créer le template par défaut
     */
    private function createDefaultTemplate()
    {
        $template_dir = $this->module->getLocalPath() . 'views/templates/admin/';
        if (!is_dir($template_dir)) {
            mkdir($template_dir, 0755, true);
        }
        
        $template_content = $this->getDefaultTemplateContent();
        file_put_contents($template_dir . 'calendar_availability.tpl', $template_content);
    }

    /**
     * Contenu du template par défaut
     */
    private function getDefaultTemplateContent()
    {
        return '<div class="booking-calendar-container">
    <div class="panel">
        <div class="panel-heading">
            <h3><i class="icon-calendar"></i> Calendrier des Disponibilités</h3>
        </div>
        <div class="panel-body">
            <div class="calendar-controls mb-3">
                <div class="row">
                    <div class="col-md-4">
                        <select id="booker-filter" class="form-control">
                            <option value="">Tous les éléments</option>
                            {foreach from=$bookers item=booker}
                                <option value="{$booker.id_booker}">{$booker.name}</option>
                            {/foreach}
                        </select>
                    </div>
                    <div class="col-md-8 text-right">
                        <button class="btn btn-primary" id="add-availability">
                            <i class="icon-plus"></i> Ajouter disponibilité
                        </button>
                        <button class="btn btn-info" id="bulk-actions" disabled>
                            <i class="icon-tasks"></i> Actions groupées
                        </button>
                    </div>
                </div>
            </div>
            <div id="calendar"></div>
        </div>
    </div>
</div>

<script>
var bookingCalendarConfig = {$calendar_config|json_encode};
var ajaxUrl = "{$ajax_url}";
var currentToken = "{$token}";
</script>';
    }
}
?>