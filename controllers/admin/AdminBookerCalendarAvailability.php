<?php
/**
 * Contrôleur pour le calendrier de gestion des disponibilités
 * Nouveau contrôleur dédié séparé des réservations
 */

require_once(dirname(__FILE__) . '/../../classes/Booker.php');
require_once(dirname(__FILE__) . '/../../classes/BookerAuth.php');

class AdminBookerCalendarAvailabilityController extends ModuleAdminController
{
    public function __construct()
    {
        $this->display = 'view';
        $this->bootstrap = true;
        parent::__construct();
        
        $this->context->smarty->assign([
            'page_title' => $this->l('Calendrier des Disponibilités'),
            'page_subtitle' => $this->l('Gérer les créneaux de disponibilité')
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
            $this->addJS(_MODULE_DIR_ . $this->module->name . '/js/calendar-availability.js');
            $this->addCSS(_MODULE_DIR_ . $this->module->name . '/css/admin-calendar-availability.css');
            
            // Récupération des bookers actifs
            $bookers = $this->getActiveBookers();
            
            // URLs AJAX pour les actions
            $ajax_urls = [
                'get_availabilities' => $this->context->link->getAdminLink('AdminBookerCalendarAvailability') . '&ajax=1&action=getAvailabilities',
                'create_availability' => $this->context->link->getAdminLink('AdminBookerCalendarAvailability') . '&ajax=1&action=createAvailability',
                'update_availability' => $this->context->link->getAdminLink('AdminBookerCalendarAvailability') . '&ajax=1&action=updateAvailability',
                'delete_availability' => $this->context->link->getAdminLink('AdminBookerCalendarAvailability') . '&ajax=1&action=deleteAvailability',
                'bulk_create' => $this->context->link->getAdminLink('AdminBookerCalendarAvailability') . '&ajax=1&action=bulkCreate',
                'bulk_delete' => $this->context->link->getAdminLink('AdminBookerCalendarAvailability') . '&ajax=1&action=bulkDelete',
                'duplicate_week' => $this->context->link->getAdminLink('AdminBookerCalendarAvailability') . '&ajax=1&action=duplicateWeek',
                'generate_recurring' => $this->context->link->getAdminLink('AdminBookerCalendarAvailability') . '&ajax=1&action=generateRecurring'
            ];
            
            // Configuration JavaScript
            Media::addJSDef([
                'AvailabilityCalendar' => [
                    'config' => [
                        'locale' => $this->context->language->iso_code,
                        'business_hours' => [
                            'daysOfWeek' => explode(',', Configuration::get('BOOKING_ALLOWED_DAYS') ?: '1,2,3,4,5,6'),
                            'startTime' => Configuration::get('BOOKING_BUSINESS_HOURS_START') ?: '08:00',
                            'endTime' => Configuration::get('BOOKING_BUSINESS_HOURS_END') ?: '18:00'
                        ],
                        'selectable' => true,
                        'selectMirror' => true,
                        'dayMaxEvents' => true,
                        'weekends' => true,
                        'slotMinTime' => '06:00:00',
                        'slotMaxTime' => '22:00:00',
                        'slotDuration' => '00:30:00',
                        'snapDuration' => '00:15:00'
                    ],
                    'ajax_urls' => $ajax_urls,
                    'bookers' => $bookers,
                    'current_date' => date('Y-m-d'),
                    'texts' => [
                        'loading' => $this->l('Chargement...'),
                        'no_events' => $this->l('Aucune disponibilité'),
                        'create_availability' => $this->l('Créer une disponibilité'),
                        'edit_availability' => $this->l('Modifier la disponibilité'),
                        'delete_confirm' => $this->l('Confirmer la suppression ?'),
                        'bulk_create_confirm' => $this->l('Créer les disponibilités sélectionnées ?'),
                        'bulk_delete_confirm' => $this->l('Supprimer les disponibilités sélectionnées ?'),
                        'duplicate_week_confirm' => $this->l('Dupliquer cette semaine ?'),
                        'select_dates' => $this->l('Sélectionner les dates'),
                        'save' => $this->l('Enregistrer'),
                        'cancel' => $this->l('Annuler'),
                        'delete' => $this->l('Supprimer'),
                        'duplicate' => $this->l('Dupliquer'),
                        'start_time' => $this->l('Heure début'),
                        'end_time' => $this->l('Heure fin'),
                        'max_bookings' => $this->l('Réservations max'),
                        'price_override' => $this->l('Prix spécial'),
                        'notes' => $this->l('Notes'),
                        'element' => $this->l('Élément'),
                        'recurring_options' => $this->l('Options récurrentes'),
                        'generate_recurring' => $this->l('Générer récurrent'),
                        'repeat_weekly' => $this->l('Répéter chaque semaine'),
                        'repeat_count' => $this->l('Nombre de répétitions'),
                        'selected_days' => $this->l('Jours sélectionnés')
                    ]
                ]
            ]);
            
            // Variables Smarty
            $this->context->smarty->assign([
                'bookers' => $bookers,
                'ajax_urls' => $ajax_urls,
                'current_date' => date('Y-m-d'),
                'module_dir' => _MODULE_DIR_ . $this->module->name . '/',
                'default_duration' => Configuration::get('BOOKING_DEFAULT_DURATION') ?: 60,
                'business_hours_start' => Configuration::get('BOOKING_BUSINESS_HOURS_START') ?: '08:00',
                'business_hours_end' => Configuration::get('BOOKING_BUSINESS_HOURS_END') ?: '18:00',
                'availability_stats' => $this->getAvailabilityStats()
            ]);
            
            $template_path = _PS_MODULE_DIR_ . $this->module->name . '/views/templates/admin/calendar_availability.tpl';
            
            if (file_exists($template_path)) {
                return $this->context->smarty->fetch($template_path);
            } else {
                return $this->generateDefaultView();
            }
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog('AdminBookerCalendarAvailability::renderView() - Erreur: ' . $e->getMessage(), 3);
            return $this->displayError($this->l('Erreur lors du chargement du calendrier des disponibilités'));
        }
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
                    ORDER BY b.name ASC';
                    
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
                    'product_reference' => $row['product_reference'],
                    'booking_duration' => (int)$row['booking_duration']
                ];
            }
            
            return $bookers;
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog('AdminBookerCalendarAvailability::getActiveBookers() - Erreur: ' . $e->getMessage(), 3);
            return [];
        }
    }
    
    /**
     * Statistiques des disponibilités
     */
    private function getAvailabilityStats()
    {
        try {
            return [
                'total_slots' => (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth` WHERE is_available = 1'),
                'future_slots' => (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth` WHERE is_available = 1 AND date_start > NOW()'),
                'occupied_slots' => (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth` WHERE current_bookings >= max_bookings'),
                'this_week' => (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth` WHERE WEEK(date_start) = WEEK(NOW()) AND YEAR(date_start) = YEAR(NOW())'),
                'next_week' => (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth` WHERE WEEK(date_start) = WEEK(NOW()) + 1 AND YEAR(date_start) = YEAR(NOW())'),
                'this_month' => (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth` WHERE MONTH(date_start) = MONTH(NOW()) AND YEAR(date_start) = YEAR(NOW())')
            ];
        } catch (Exception $e) {
            PrestaShopLogger::addLog('AdminBookerCalendarAvailability - Erreur stats: ' . $e->getMessage(), 3);
            return [];
        }
    }
    
    /**
     * Vue par défaut si le template n'existe pas
     */
    private function generateDefaultView()
    {
        $stats = $this->getAvailabilityStats();
        
        return '
        <div class="row">
            <div class="col-lg-12">
                <div class="panel">
                    <div class="panel-heading">
                        <i class="icon-bar-chart"></i> ' . $this->l('Aperçu des disponibilités') . '
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-lg-2 col-md-4 col-sm-6">
                                <div class="alert alert-info text-center">
                                    <div style="font-size: 1.5em; font-weight: bold;">' . ($stats['total_slots'] ?? 0) . '</div>
                                    <small>' . $this->l('Total créneaux') . '</small>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-4 col-sm-6">
                                <div class="alert alert-success text-center">
                                    <div style="font-size: 1.5em; font-weight: bold;">' . ($stats['future_slots'] ?? 0) . '</div>
                                    <small>' . $this->l('À venir') . '</small>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-4 col-sm-6">
                                <div class="alert alert-warning text-center">
                                    <div style="font-size: 1.5em; font-weight: bold;">' . ($stats['occupied_slots'] ?? 0) . '</div>
                                    <small>' . $this->l('Complets') . '</small>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-4 col-sm-6">
                                <div class="alert alert-primary text-center">
                                    <div style="font-size: 1.5em; font-weight: bold;">' . ($stats['this_week'] ?? 0) . '</div>
                                    <small>' . $this->l('Cette semaine') . '</small>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-4 col-sm-6">
                                <div class="alert alert-default text-center">
                                    <div style="font-size: 1.5em; font-weight: bold;">' . ($stats['next_week'] ?? 0) . '</div>
                                    <small>' . $this->l('Semaine prochaine') . '</small>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-4 col-sm-6">
                                <div class="alert alert-secondary text-center">
                                    <div style="font-size: 1.5em; font-weight: bold;">' . ($stats['this_month'] ?? 0) . '</div>
                                    <small>' . $this->l('Ce mois') . '</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-calendar"></i> ' . $this->l('Calendrier des Disponibilités') . '
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-lg-3">
                        <div class="form-group">
                            <label>' . $this->l('Élément à réserver') . '</label>
                            <select id="booker-filter" class="form-control">
                                <option value="">' . $this->l('Tous les éléments') . '</option>
                            </select>
                        </div>
                        
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h4 class="panel-title">' . $this->l('Actions rapides') . '</h4>
                            </div>
                            <div class="panel-body">
                                <button type="button" id="btn-bulk-create" class="btn btn-success btn-block btn-sm">
                                    <i class="icon-plus"></i> ' . $this->l('Création en lot') . '
                                </button>
                                <button type="button" id="btn-duplicate-week" class="btn btn-info btn-block btn-sm">
                                    <i class="icon-copy"></i> ' . $this->l('Dupliquer semaine') . '
                                </button>
                                <button type="button" id="btn-generate-recurring" class="btn btn-warning btn-block btn-sm">
                                    <i class="icon-repeat"></i> ' . $this->l('Générer récurrent') . '
                                </button>
                                <button type="button" id="btn-bulk-delete" class="btn btn-danger btn-block btn-sm">
                                    <i class="icon-trash"></i> ' . $this->l('Suppression en lot') . '
                                </button>
                                <hr>
                                <button type="button" id="btn-refresh" class="btn btn-default btn-block btn-sm">
                                    <i class="icon-refresh"></i> ' . $this->l('Actualiser') . '
                                </button>
                            </div>
                        </div>
                        
                        <!-- Légende des couleurs -->
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h4 class="panel-title">' . $this->l('Légende') . '</h4>
                            </div>
                            <div class="panel-body">
                                <div class="legend-item">
                                    <span class="legend-color" style="background-color: #28a745;"></span>
                                    ' . $this->l('Disponible') . '
                                </div>
                                <div class="legend-item">
                                    <span class="legend-color" style="background-color: #ffc107;"></span>
                                    ' . $this->l('Partiellement réservé') . '
                                </div>
                                <div class="legend-item">
                                    <span class="legend-color" style="background-color: #dc3545;"></span>
                                    ' . $this->l('Complet') . '
                                </div>
                                <div class="legend-item">
                                    <span class="legend-color" style="background-color: #6c757d;"></span>
                                    ' . $this->l('Indisponible') . '
                                </div>
                            </div>
                        </div>
                        
                        <!-- Outils de sélection -->
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h4 class="panel-title">' . $this->l('Sélection') . '</h4>
                            </div>
                            <div class="panel-body">
                                <div class="form-group">
                                    <label>' . $this->l('Mode de sélection') . '</label>
                                    <select id="selection-mode" class="form-control">
                                        <option value="single">' . $this->l('Simple') . '</option>
                                        <option value="multiple">' . $this->l('Multiple') . '</option>
                                        <option value="range">' . $this->l('Plage') . '</option>
                                    </select>
                                </div>
                                <div id="selected-slots-info">
                                    <small class="text-muted">' . $this->l('Aucun créneau sélectionné') . '</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-9">
                        <div id="availability-calendar" style="min-height: 600px;"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Modal pour créer/modifier une disponibilité -->
        <div class="modal fade" id="availability-modal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title"></h4>
                    </div>
                    <div class="modal-body">
                        <form id="availability-form">
                            <input type="hidden" id="availability-id" name="id_auth">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>' . $this->l('Élément') . ' <span class="text-danger">*</span></label>
                                        <select id="availability-booker" name="id_booker" class="form-control" required>
                                            <option value="">' . $this->l('Sélectionner un élément') . '</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>' . $this->l('Réservations maximum') . ' <span class="text-danger">*</span></label>
                                        <input type="number" id="availability-max" name="max_bookings" class="form-control" min="1" value="1" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>' . $this->l('Date/Heure début') . ' <span class="text-danger">*</span></label>
                                        <input type="datetime-local" id="availability-start" name="date_start" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>' . $this->l('Date/Heure fin') . ' <span class="text-danger">*</span></label>
                                        <input type="datetime-local" id="availability-end" name="date_end" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>' . $this->l('Prix spécial') . ' (€)</label>
                                        <input type="number" id="availability-price" name="price_override" class="form-control" step="0.01" min="0" placeholder="' . $this->l('Laisser vide pour prix par défaut') . '">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" id="availability-available" name="is_available" value="1" checked>
                                            ' . $this->l('Disponible') . '
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>' . $this->l('Notes') . '</label>
                                <textarea id="availability-notes" name="notes" class="form-control" rows="3" placeholder="' . $this->l('Notes internes sur cette disponibilité') . '"></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">' . $this->l('Annuler') . '</button>
                        <button type="button" id="btn-save-availability" class="btn btn-primary">' . $this->l('Enregistrer') . '</button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Modal pour génération récurrente -->
        <div class="modal fade" id="recurring-modal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title">' . $this->l('Générer des disponibilités récurrentes') . '</h4>
                    </div>
                    <div class="modal-body">
                        <form id="recurring-form">
                            <div class="form-group">
                                <label>' . $this->l('Élément') . ' <span class="text-danger">*</span></label>
                                <select id="recurring-booker" name="id_booker" class="form-control" required>
                                    <option value="">' . $this->l('Sélectionner un élément') . '</option>
                                </select>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>' . $this->l('Date de début') . '</label>
                                        <input type="date" id="recurring-start-date" name="start_date" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>' . $this->l('Date de fin') . '</label>
                                        <input type="date" id="recurring-end-date" name="end_date" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>' . $this->l('Heure de début') . '</label>
                                        <input type="time" id="recurring-start-time" name="start_time" class="form-control" value="08:00" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>' . $this->l('Heure de fin') . '</label>
                                        <input type="time" id="recurring-end-time" name="end_time" class="form-control" value="18:00" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>' . $this->l('Jours de la semaine') . '</label>
                                <div class="checkbox-group">
                                    <label class="checkbox-inline"><input type="checkbox" name="days[]" value="1"> ' . $this->l('Lundi') . '</label>
                                    <label class="checkbox-inline"><input type="checkbox" name="days[]" value="2"> ' . $this->l('Mardi') . '</label>
                                    <label class="checkbox-inline"><input type="checkbox" name="days[]" value="3"> ' . $this->l('Mercredi') . '</label>
                                    <label class="checkbox-inline"><input type="checkbox" name="days[]" value="4"> ' . $this->l('Jeudi') . '</label>
                                    <label class="checkbox-inline"><input type="checkbox" name="days[]" value="5"> ' . $this->l('Vendredi') . '</label>
                                    <label class="checkbox-inline"><input type="checkbox" name="days[]" value="6"> ' . $this->l('Samedi') . '</label>
                                    <label class="checkbox-inline"><input type="checkbox" name="days[]" value="0"> ' . $this->l('Dimanche') . '</label>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>' . $this->l('Durée de chaque créneau (minutes)') . '</label>
                                        <select id="recurring-duration" name="slot_duration" class="form-control">
                                            <option value="30">30 minutes</option>
                                            <option value="60" selected>1 heure</option>
                                            <option value="90">1h30</option>
                                            <option value="120">2 heures</option>
                                            <option value="180">3 heures</option>
                                            <option value="240">4 heures</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>' . $this->l('Réservations max par créneau') . '</label>
                                        <input type="number" id="recurring-max" name="max_bookings" class="form-control" min="1" value="1" required>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">' . $this->l('Annuler') . '</button>
                        <button type="button" id="btn-generate-recurring" class="btn btn-success">' . $this->l('Générer') . '</button>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .legend-item {
            margin-bottom: 8px;
            font-size: 12px;
            display: flex;
            align-items: center;
        }
        .legend-color {
            display: inline-block;
            width: 16px;
            height: 16px;
            margin-right: 8px;
            border-radius: 3px;
            border: 1px solid #ddd;
        }
        #availability-calendar {
            min-height: 600px;
        }
        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .checkbox-inline {
            margin-right: 0;
        }
        .btn-block.btn-sm {
            margin-bottom: 5px;
        }
        </style>';
    }
    
    /**
     * Traitement des requêtes AJAX
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
            case 'bulkDelete':
                $this->ajaxProcessBulkDelete();
                break;
            case 'duplicateWeek':
                $this->ajaxProcessDuplicateWeek();
                break;
            case 'generateRecurring':
                $this->ajaxProcessGenerateRecurring();
                break;
            default:
                $this->ajaxDie('Action non reconnue');
        }
    }
    
    /**
     * Récupérer les disponibilités pour le calendrier
     */
    private function ajaxProcessGetAvailabilities()
    {
        try {
            $id_booker = (int)Tools::getValue('id_booker');
            $start = Tools::getValue('start');
            $end = Tools::getValue('end');
            
            $sql = 'SELECT ba.*, b.name as booker_name, b.location, b.capacity
                    FROM `' . _DB_PREFIX_ . 'booker_auth` ba
                    LEFT JOIN `' . _DB_PREFIX_ . 'booker` b ON ba.id_booker = b.id_booker
                    WHERE ba.date_start >= "' . pSQL($start) . '"
                    AND ba.date_end <= "' . pSQL($end) . '"';
                    
            if ($id_booker > 0) {
                $sql .= ' AND ba.id_booker = ' . $id_booker;
            }
            
            $sql .= ' ORDER BY ba.date_start ASC';
            
            $availabilities = Db::getInstance()->executeS($sql);
            $events = [];
            
            foreach ($availabilities as $availability) {
                // Déterminer la couleur selon l'occupation
                $occupation_rate = $availability['max_bookings'] > 0 
                    ? $availability['current_bookings'] / $availability['max_bookings'] 
                    : 0;
                    
                if (!$availability['is_available']) {
                    $color = ['bg' => '#6c757d', 'border' => '#6c757d']; // Gris - Indisponible
                } elseif ($occupation_rate >= 1) {
                    $color = ['bg' => '#dc3545', 'border' => '#dc3545']; // Rouge - Complet
                } elseif ($occupation_rate > 0) {
                    $color = ['bg' => '#ffc107', 'border' => '#ffc107']; // Jaune - Partiellement réservé
                } else {
                    $color = ['bg' => '#28a745', 'border' => '#28a745']; // Vert - Disponible
                }
                
                $title = $availability['booker_name'];
                if ($availability['max_bookings'] > 1) {
                    $title .= ' (' . $availability['current_bookings'] . '/' . $availability['max_bookings'] . ')';
                }
                
                $events[] = [
                    'id' => 'avail_' . $availability['id_auth'],
                    'title' => $title,
                    'start' => $availability['date_start'],
                    'end' => $availability['date_end'],
                    'backgroundColor' => $color['bg'],
                    'borderColor' => $color['border'],
                    'textColor' => '#ffffff',
                    'extendedProps' => [
                        'type' => 'availability',
                        'id_auth' => (int)$availability['id_auth'],
                        'id_booker' => (int)$availability['id_booker'],
                        'booker_name' => $availability['booker_name'],
                        'location' => $availability['location'],
                        'capacity' => (int)$availability['capacity'],
                        'max_bookings' => (int)$availability['max_bookings'],
                        'current_bookings' => (int)$availability['current_bookings'],
                        'is_available' => (bool)$availability['is_available'],
                        'price_override' => $availability['price_override'] ? (float)$availability['price_override'] : null,
                        'notes' => $availability['notes'],
                        'occupation_rate' => $occupation_rate
                    ]
                ];
            }
            
            $this->ajaxDie(json_encode($events));
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog('AdminBookerCalendarAvailability::ajaxProcessGetAvailabilities() - Erreur: ' . $e->getMessage(), 3);
            $this->ajaxDie(json_encode(['error' => $this->l('Erreur lors du chargement des disponibilités')]));
        }
    }
    
    /**
     * Créer une nouvelle disponibilité
     */
    private function ajaxProcessCreateAvailability()
    {
        try {
            $id_booker = (int)Tools::getValue('id_booker');
            $date_start = Tools::getValue('date_start');
            $date_end = Tools::getValue('date_end');
            $max_bookings = (int)Tools::getValue('max_bookings', 1);
            $price_override = Tools::getValue('price_override');
            $is_available = (bool)Tools::getValue('is_available', true);
            $notes = Tools::getValue('notes', '');
            
            if (!$id_booker || !$date_start || !$date_end) {
                $this->ajaxDie(json_encode(['error' => $this->l('Données manquantes')]));
            }
            
            // Vérifier que le booker existe
            $booker = new Booker($id_booker);
            if (!Validate::isLoadedObject($booker)) {
                $this->ajaxDie(json_encode(['error' => $this->l('Élément introuvable')]));
            }
            
            // Vérifier les conflits
            if ($this->hasConflict($id_booker, $date_start, $date_end)) {
                $this->ajaxDie(json_encode(['error' => $this->l('Conflit détecté avec une disponibilité existante')]));
            }
            
            // Créer la disponibilité
            $availability = new BookerAuth();
            $availability->id_booker = $id_booker;
            $availability->date_start = $date_start;
            $availability->date_end = $date_end;
            $availability->max_bookings = $max_bookings;
            $availability->current_bookings = 0;
            $availability->is_available = $is_available;
            $availability->price_override = $price_override ? (float)$price_override : null;
            $availability->notes = $notes;
            $availability->date_add = date('Y-m-d H:i:s');
            $availability->date_upd = date('Y-m-d H:i:s');
            
            if ($availability->save()) {
                $this->ajaxDie(json_encode(['success' => $this->l('Disponibilité créée avec succès')]));
            } else {
                $this->ajaxDie(json_encode(['error' => $this->l('Erreur lors de la création')]));
            }
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog('AdminBookerCalendarAvailability::ajaxProcessCreateAvailability() - Erreur: ' . $e->getMessage(), 3);
            $this->ajaxDie(json_encode(['error' => $this->l('Erreur lors de la création')]));
        }
    }
    
    /**
     * Générer des disponibilités récurrentes
     */
    private function ajaxProcessGenerateRecurring()
    {
        try {
            $id_booker = (int)Tools::getValue('id_booker');
            $start_date = Tools::getValue('start_date');
            $end_date = Tools::getValue('end_date');
            $start_time = Tools::getValue('start_time');
            $end_time = Tools::getValue('end_time');
            $days = Tools::getValue('days', []);
            $slot_duration = (int)Tools::getValue('slot_duration', 60);
            $max_bookings = (int)Tools::getValue('max_bookings', 1);
            
            if (!$id_booker || !$start_date || !$end_date || !$start_time || !$end_time || empty($days)) {
                $this->ajaxDie(json_encode(['error' => $this->l('Données manquantes')]));
            }
            
            $created_count = 0;
            $start = new DateTime($start_date);
            $end = new DateTime($end_date);
            
            // Parcourir chaque jour de la période
            for ($date = clone $start; $date <= $end; $date->modify('+1 day')) {
                $day_of_week = $date->format('w'); // 0 = dimanche, 1 = lundi, etc.
                
                if (in_array($day_of_week, $days)) {
                    // Générer les créneaux pour cette journée
                    $current_time = new DateTime($date->format('Y-m-d') . ' ' . $start_time);
                    $day_end_time = new DateTime($date->format('Y-m-d') . ' ' . $end_time);
                    
                    while ($current_time < $day_end_time) {
                        $slot_end = clone $current_time;
                        $slot_end->modify('+' . $slot_duration . ' minutes');
                        
                        if ($slot_end <= $day_end_time) {
                            // Vérifier les conflits
                            if (!$this->hasConflict($id_booker, $current_time->format('Y-m-d H:i:s'), $slot_end->format('Y-m-d H:i:s'))) {
                                // Créer le créneau
                                $availability = new BookerAuth();
                                $availability->id_booker = $id_booker;
                                $availability->date_start = $current_time->format('Y-m-d H:i:s');
                                $availability->date_end = $slot_end->format('Y-m-d H:i:s');
                                $availability->max_bookings = $max_bookings;
                                $availability->current_bookings = 0;
                                $availability->is_available = true;
                                $availability->date_add = date('Y-m-d H:i:s');
                                $availability->date_upd = date('Y-m-d H:i:s');
                                
                                if ($availability->save()) {
                                    $created_count++;
                                }
                            }
                        }
                        
                        $current_time->modify('+' . $slot_duration . ' minutes');
                    }
                }
            }
            
            $this->ajaxDie(json_encode([
                'success' => sprintf($this->l('%d créneaux créés avec succès'), $created_count)
            ]));
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog('AdminBookerCalendarAvailability::ajaxProcessGenerateRecurring() - Erreur: ' . $e->getMessage(), 3);
            $this->ajaxDie(json_encode(['error' => $this->l('Erreur lors de la génération')]));
        }
    }
    
    /**
     * Vérifier les conflits de disponibilité
     */
    private function hasConflict($id_booker, $date_start, $date_end, $exclude_id = null)
    {
        $sql = 'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth` 
                WHERE id_booker = ' . (int)$id_booker . '
                AND (
                    (date_start <= "' . pSQL($date_start) . '" AND date_end > "' . pSQL($date_start) . '")
                    OR (date_start < "' . pSQL($date_end) . '" AND date_end >= "' . pSQL($date_end) . '")
                    OR (date_start >= "' . pSQL($date_start) . '" AND date_end <= "' . pSQL($date_end) . '")
                )';
                
        if ($exclude_id) {
            $sql .= ' AND id_auth != ' . (int)$exclude_id;
        }
        
        return (bool)Db::getInstance()->getValue($sql);
    }
    
    // Autres méthodes AJAX à implémenter...
    private function ajaxProcessUpdateAvailability() {
        // À implémenter
    }
    
    private function ajaxProcessDeleteAvailability() {
        // À implémenter
    }
    
    private function ajaxProcessBulkCreate() {
        // À implémenter
    }
    
    private function ajaxProcessBulkDelete() {
        // À implémenter
    }
    
    private function ajaxProcessDuplicateWeek() {
        // À implémenter
    }
}