<?php
require_once (dirname(__FILE__). '/../../classes/Booker.php');
require_once (dirname(__FILE__). '/../../classes/BookerAuth.php');
require_once (dirname(__FILE__). '/../../classes/BookerAuthReserved.php');

class AdminBookerAvailabilityCalendarController extends ModuleAdminControllerCore
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
        $this->addJS(_MODULE_DIR_.$this->module->name.'/js/availability-calendar.js');
        $this->addCSS(_MODULE_DIR_.$this->module->name.'/css/calendar.css');
        
        return $this->renderAvailabilityCalendar();
    }

    /**
     * Traitement AJAX pour charger les données du calendrier
     */
    public function ajaxProcessLoadCalendar() {
        $booker_id = (int)Tools::getValue('booker_id');
        $year = (int)Tools::getValue('year', date('Y'));
        $month = (int)Tools::getValue('month', date('m'));
        $view = Tools::getValue('view', 'month'); // month, week, day
        
        $data = $this->getCalendarData($booker_id, $year, $month, $view);
        
        echo json_encode([
            'success' => true,
            'data' => $data,
            'booker_id' => $booker_id,
            'year' => $year,
            'month' => $month,
            'view' => $view
        ]);
        exit;
    }

    /**
     * Traitement AJAX pour sauvegarder les disponibilités
     */
    public function ajaxProcessSaveAvailability() {
        $booker_id = (int)Tools::getValue('booker_id');
        $date_from = Tools::getValue('date_from');
        $date_to = Tools::getValue('date_to');
        $recurring = Tools::getValue('recurring', false);
        $recurring_pattern = Tools::getValue('recurring_pattern', '');
        
        if (!$booker_id || !$date_from || !$date_to) {
            echo json_encode(['success' => false, 'error' => 'Paramètres manquants']);
            exit;
        }

        // Validation des dates
        if (strtotime($date_from) >= strtotime($date_to)) {
            echo json_encode(['success' => false, 'error' => 'La date de début doit être antérieure à la date de fin']);
            exit;
        }

        // Vérifier les conflits
        if ($this->hasAvailabilityConflict($booker_id, $date_from, $date_to)) {
            echo json_encode(['success' => false, 'error' => 'Conflit avec une disponibilité existante']);
            exit;
        }

        // Sauvegarder la disponibilité
        $availability = new BookerAuth();
        $availability->id_booker = $booker_id;
        $availability->date_from = $date_from;
        $availability->date_to = $date_to;
        $availability->active = 1;
        $availability->date_add = date('Y-m-d H:i:s');
        $availability->date_upd = date('Y-m-d H:i:s');

        if ($availability->add()) {
            // Si récurrence demandée, créer les autres occurrences
            if ($recurring && $recurring_pattern) {
                $this->createRecurringAvailabilities($availability, $recurring_pattern);
            }
            
            echo json_encode(['success' => true, 'id' => $availability->id]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Erreur lors de la sauvegarde']);
        }
        exit;
    }

    /**
     * Traitement AJAX pour supprimer une disponibilité
     */
    public function ajaxProcessDeleteAvailability() {
        $id = (int)Tools::getValue('id');
        
        $availability = new BookerAuth($id);
        if (!Validate::isLoadedObject($availability)) {
            echo json_encode(['success' => false, 'error' => 'Disponibilité introuvable']);
            exit;
        }

        // Vérifier s'il y a des réservations associées
        $reservations = Db::getInstance()->getValue('
            SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` 
            WHERE id_booker = ' . (int)$availability->id_booker . '
            AND date_reserved >= "' . pSQL($availability->date_from) . '"
            AND date_reserved <= "' . pSQL($availability->date_to) . '"
            AND status IN (' . BookerAuthReserved::STATUS_ACCEPTED . ', ' . BookerAuthReserved::STATUS_PAID . ')
            AND active = 1
        ');

        if ($reservations > 0) {
            echo json_encode(['success' => false, 'error' => 'Impossible de supprimer : des réservations sont associées']);
            exit;
        }

        if ($availability->delete()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Erreur lors de la suppression']);
        }
        exit;
    }

    /**
     * Traitement AJAX pour la sélection multiple
     */
    public function ajaxProcessBulkAvailability() {
        $action = Tools::getValue('action');
        $booker_id = (int)Tools::getValue('booker_id');
        $dates = Tools::getValue('dates'); // Array of dates
        $time_from = Tools::getValue('time_from', '00:00:00');
        $time_to = Tools::getValue('time_to', '23:59:59');
        
        if (!$action || !$booker_id || !$dates || !is_array($dates)) {
            echo json_encode(['success' => false, 'error' => 'Paramètres invalides']);
            exit;
        }

        $success_count = 0;
        $error_count = 0;
        $errors = [];

        foreach ($dates as $date) {
            $date_from = $date . ' ' . $time_from;
            $date_to = $date . ' ' . $time_to;
            
            try {
                if ($action === 'add') {
                    if (!$this->hasAvailabilityConflict($booker_id, $date_from, $date_to)) {
                        $availability = new BookerAuth();
                        $availability->id_booker = $booker_id;
                        $availability->date_from = $date_from;
                        $availability->date_to = $date_to;
                        $availability->active = 1;
                        $availability->date_add = date('Y-m-d H:i:s');
                        $availability->date_upd = date('Y-m-d H:i:s');
                        
                        if ($availability->add()) {
                            $success_count++;
                        } else {
                            $error_count++;
                            $errors[] = "Erreur pour $date";
                        }
                    } else {
                        $error_count++;
                        $errors[] = "Conflit pour $date";
                    }
                } elseif ($action === 'remove') {
                    // Supprimer les disponibilités pour cette date
                    $availabilities = Db::getInstance()->executeS('
                        SELECT id_auth FROM `' . _DB_PREFIX_ . 'booker_auth`
                        WHERE id_booker = ' . (int)$booker_id . '
                        AND DATE(date_from) = "' . pSQL($date) . '"
                        AND active = 1
                    ');
                    
                    foreach ($availabilities as $avail) {
                        $availability = new BookerAuth($avail['id_auth']);
                        if ($availability->delete()) {
                            $success_count++;
                        } else {
                            $error_count++;
                        }
                    }
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
     * Obtenir les données du calendrier
     */
    private function getCalendarData($booker_id, $year, $month, $view) {
        $data = [];
        
        switch ($view) {
            case 'month':
                $data = $this->getMonthData($booker_id, $year, $month);
                break;
            case 'week':
                $week = (int)Tools::getValue('week', 1);
                $data = $this->getWeekData($booker_id, $year, $week);
                break;
            case 'day':
                $day = Tools::getValue('day', date('Y-m-d'));
                $data = $this->getDayData($booker_id, $day);
                break;
        }
        
        return $data;
    }

    /**
     * Obtenir les données pour la vue mensuelle
     */
    private function getMonthData($booker_id, $year, $month) {
        $first_day = date('Y-m-01', mktime(0, 0, 0, $month, 1, $year));
        $last_day = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));
        
        // Récupérer les disponibilités du mois
        $availabilities = Db::getInstance()->executeS('
            SELECT * FROM `' . _DB_PREFIX_ . 'booker_auth`
            WHERE id_booker = ' . (int)$booker_id . '
            AND active = 1
            AND (
                (DATE(date_from) <= "' . pSQL($last_day) . '" AND DATE(date_to) >= "' . pSQL($first_day) . '")
            )
            ORDER BY date_from ASC
        ');

        // Récupérer les réservations du mois pour information
        $reservations = Db::getInstance()->executeS('
            SELECT * FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`
            WHERE id_booker = ' . (int)$booker_id . '
            AND active = 1
            AND date_reserved >= "' . pSQL($first_day) . '"
            AND date_reserved <= "' . pSQL($last_day) . '"
            ORDER BY date_reserved ASC, hour_from ASC
        ');

        return [
            'availabilities' => $availabilities,
            'reservations' => $reservations,
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
     * Vérifier les conflits de disponibilité
     */
    private function hasAvailabilityConflict($booker_id, $date_from, $date_to, $exclude_id = null) {
        $sql = 'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth`
                WHERE id_booker = ' . (int)$booker_id . '
                AND active = 1
                AND (
                    (date_from <= "' . pSQL($date_from) . '" AND date_to > "' . pSQL($date_from) . '")
                    OR (date_from < "' . pSQL($date_to) . '" AND date_to >= "' . pSQL($date_to) . '")
                    OR (date_from >= "' . pSQL($date_from) . '" AND date_to <= "' . pSQL($date_to) . '")
                )';
        
        if ($exclude_id) {
            $sql .= ' AND id_auth != ' . (int)$exclude_id;
        }
        
        return (bool)Db::getInstance()->getValue($sql);
    }

    /**
     * Créer des disponibilités récurrentes
     */
    private function createRecurringAvailabilities($base_availability, $pattern) {
        // Patterns: daily, weekly, monthly
        // À implémenter selon les besoins
        // Exemple pour récurrence hebdomadaire sur 8 semaines
        if ($pattern === 'weekly') {
            for ($i = 1; $i <= 8; $i++) {
                $new_date_from = date('Y-m-d H:i:s', strtotime($base_availability->date_from . ' +' . $i . ' week'));
                $new_date_to = date('Y-m-d H:i:s', strtotime($base_availability->date_to . ' +' . $i . ' week'));
                
                if (!$this->hasAvailabilityConflict($base_availability->id_booker, $new_date_from, $new_date_to)) {
                    $new_availability = new BookerAuth();
                    $new_availability->id_booker = $base_availability->id_booker;
                    $new_availability->date_from = $new_date_from;
                    $new_availability->date_to = $new_date_to;
                    $new_availability->active = 1;
                    $new_availability->date_add = date('Y-m-d H:i:s');
                    $new_availability->date_upd = date('Y-m-d H:i:s');
                    $new_availability->add();
                }
            }
        }
    }

    /**
     * Rendu du calendrier des disponibilités
     */
    private function renderAvailabilityCalendar() {
        // Récupérer tous les bookers actifs
        $bookers = Booker::getActiveBookers();
        
        $this->context->smarty->assign([
            'bookers' => $bookers,
            'current_year' => date('Y'),
            'current_month' => date('m'),
            'calendar_type' => 'availability',
            'ajax_url' => $this->context->link->getAdminLink('AdminBookerAvailabilityCalendar')
        ]);
        
        return $this->context->smarty->fetch($this->getTemplatePath().'availability_calendar.tpl');
    }
}