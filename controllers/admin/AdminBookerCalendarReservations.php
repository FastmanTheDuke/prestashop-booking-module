<?php
/**
 * Contrôleur pour le calendrier de gestion des réservations
 * Nouveau contrôleur dédié séparé des disponibilités
 */

require_once(dirname(__FILE__) . '/../../classes/Booker.php');
require_once(dirname(__FILE__) . '/../../classes/BookerAuth.php');
require_once(dirname(__FILE__) . '/../../classes/BookerAuthReserved.php');

class AdminBookerCalendarReservationsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->display = 'view';
        $this->bootstrap = true;
        parent::__construct();
        
        $this->context->smarty->assign([
            'page_title' => $this->l('Calendrier des Réservations'),
            'page_subtitle' => $this->l('Gérer et valider les réservations clients')
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
            $this->addJS(_MODULE_DIR_ . $this->module->name . '/js/calendar-reservations.js');
            $this->addCSS(_MODULE_DIR_ . $this->module->name . '/css/admin-calendar-reservations.css');
            
            // Récupération des bookers actifs
            $bookers = $this->getActiveBookers();
            
            // Statuts des réservations
            $statuses = BookerAuthReserved::getStatuses();
            
            // URLs AJAX pour les actions
            $ajax_urls = [
                'get_reservations' => $this->context->link->getAdminLink('AdminBookerCalendarReservations') . '&ajax=1&action=getReservations',
                'update_status' => $this->context->link->getAdminLink('AdminBookerCalendarReservations') . '&ajax=1&action=updateStatus',
                'validate_reservation' => $this->context->link->getAdminLink('AdminBookerCalendarReservations') . '&ajax=1&action=validateReservation',
                'cancel_reservation' => $this->context->link->getAdminLink('AdminBookerCalendarReservations') . '&ajax=1&action=cancelReservation',
                'create_order' => $this->context->link->getAdminLink('AdminBookerCalendarReservations') . '&ajax=1&action=createOrder',
                'bulk_validate' => $this->context->link->getAdminLink('AdminBookerCalendarReservations') . '&ajax=1&action=bulkValidate',
                'bulk_cancel' => $this->context->link->getAdminLink('AdminBookerCalendarReservations') . '&ajax=1&action=bulkCancel',
                'send_notification' => $this->context->link->getAdminLink('AdminBookerCalendarReservations') . '&ajax=1&action=sendNotification'
            ];
            
            // Configuration JavaScript
            Media::addJSDef([
                'ReservationCalendar' => [
                    'config' => [
                        'locale' => $this->context->language->iso_code,
                        'business_hours' => [
                            'daysOfWeek' => explode(',', Configuration::get('BOOKING_ALLOWED_DAYS') ?: '1,2,3,4,5,6'),
                            'startTime' => Configuration::get('BOOKING_BUSINESS_HOURS_START') ?: '08:00',
                            'endTime' => Configuration::get('BOOKING_BUSINESS_HOURS_END') ?: '18:00'
                        ],
                        'selectable' => false, // Pas de sélection sur le calendrier des réservations
                        'selectMirror' => false,
                        'dayMaxEvents' => true,
                        'weekends' => true,
                        'eventClick' => true,
                        'eventMouseEnter' => true
                    ],
                    'ajax_urls' => $ajax_urls,
                    'bookers' => $bookers,
                    'statuses' => $statuses,
                    'current_date' => date('Y-m-d'),
                    'texts' => [
                        'loading' => $this->l('Chargement...'),
                        'no_events' => $this->l('Aucune réservation'),
                        'reservation_details' => $this->l('Détails de la réservation'),
                        'validate_reservation' => $this->l('Valider la réservation'),
                        'cancel_reservation' => $this->l('Annuler la réservation'),
                        'create_order' => $this->l('Créer la commande'),
                        'send_notification' => $this->l('Envoyer une notification'),
                        'bulk_validate_confirm' => $this->l('Valider les réservations sélectionnées ?'),
                        'bulk_cancel_confirm' => $this->l('Annuler les réservations sélectionnées ?'),
                        'validate_confirm' => $this->l('Confirmer la validation de cette réservation ?'),
                        'cancel_confirm' => $this->l('Confirmer l\'annulation de cette réservation ?'),
                        'save' => $this->l('Enregistrer'),
                        'cancel' => $this->l('Annuler'),
                        'validate' => $this->l('Valider'),
                        'customer' => $this->l('Client'),
                        'phone' => $this->l('Téléphone'),
                        'email' => $this->l('Email'),
                        'status' => $this->l('Statut'),
                        'total_price' => $this->l('Prix total'),
                        'deposit_paid' => $this->l('Caution versée'),
                        'notes' => $this->l('Notes client'),
                        'admin_notes' => $this->l('Notes admin'),
                        'booking_reference' => $this->l('Référence'),
                        'date_reserved' => $this->l('Date de réservation'),
                        'date_expiry' => $this->l('Date d\'expiration')
                    ]
                ]
            ]);
            
            // Variables Smarty
            $this->context->smarty->assign([
                'bookers' => $bookers,
                'statuses' => $statuses,
                'ajax_urls' => $ajax_urls,
                'current_date' => date('Y-m-d'),
                'module_dir' => _MODULE_DIR_ . $this->module->name . '/',
                'reservation_stats' => $this->getReservationStats()
            ]);
            
            $template_path = _PS_MODULE_DIR_ . $this->module->name . '/views/templates/admin/calendar_reservations.tpl';
            
            if (file_exists($template_path)) {
                return $this->context->smarty->fetch($template_path);
            } else {
                return $this->generateDefaultView();
            }
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog('AdminBookerCalendarReservations::renderView() - Erreur: ' . $e->getMessage(), 3);
            return $this->displayError($this->l('Erreur lors du chargement du calendrier des réservations'));
        }
    }
    
    /**
     * Récupération des bookers actifs
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
                    'product_reference' => $row['product_reference']
                ];
            }
            
            return $bookers;
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog('AdminBookerCalendarReservations::getActiveBookers() - Erreur: ' . $e->getMessage(), 3);
            return [];
        }
    }
    
    /**
     * Statistiques des réservations
     */
    private function getReservationStats()
    {
        try {
            return [
                'pending' => (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` WHERE status = 0'),
                'validated' => (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` WHERE status = 1'),
                'paid' => (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` WHERE status = 2'),
                'cancelled' => (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` WHERE status = 3'),
                'expired' => (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` WHERE status = 4'),
                'today' => (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` WHERE DATE(date_start) = CURDATE()'),
                'upcoming' => (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` WHERE date_start > NOW() AND status IN (1,2)')
            ];
        } catch (Exception $e) {
            PrestaShopLogger::addLog('AdminBookerCalendarReservations - Erreur stats: ' . $e->getMessage(), 3);
            return [];
        }
    }
    
    /**
     * Vue par défaut si le template n'existe pas
     */
    private function generateDefaultView()
    {
        $stats = $this->getReservationStats();
        
        return '
        <div class="row">
            <div class="col-lg-12">
                <div class="panel">
                    <div class="panel-heading">
                        <i class="icon-bar-chart"></i> ' . $this->l('Aperçu des réservations') . '
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-lg-2 col-md-4 col-sm-6">
                                <div class="alert alert-warning text-center">
                                    <div style="font-size: 1.5em; font-weight: bold;">' . ($stats['pending'] ?? 0) . '</div>
                                    <small>' . $this->l('En attente') . '</small>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-4 col-sm-6">
                                <div class="alert alert-info text-center">
                                    <div style="font-size: 1.5em; font-weight: bold;">' . ($stats['validated'] ?? 0) . '</div>
                                    <small>' . $this->l('Validées') . '</small>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-4 col-sm-6">
                                <div class="alert alert-success text-center">
                                    <div style="font-size: 1.5em; font-weight: bold;">' . ($stats['paid'] ?? 0) . '</div>
                                    <small>' . $this->l('Payées') . '</small>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-4 col-sm-6">
                                <div class="alert alert-primary text-center">
                                    <div style="font-size: 1.5em; font-weight: bold;">' . ($stats['today'] ?? 0) . '</div>
                                    <small>' . $this->l('Aujourd\'hui') . '</small>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-4 col-sm-6">
                                <div class="alert alert-default text-center">
                                    <div style="font-size: 1.5em; font-weight: bold;">' . ($stats['upcoming'] ?? 0) . '</div>
                                    <small>' . $this->l('À venir') . '</small>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-4 col-sm-6">
                                <div class="alert alert-danger text-center">
                                    <div style="font-size: 1.5em; font-weight: bold;">' . ($stats['cancelled'] ?? 0) . '</div>
                                    <small>' . $this->l('Annulées') . '</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-calendar"></i> ' . $this->l('Calendrier des Réservations') . '
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-lg-3">
                        <div class="form-group">
                            <label>' . $this->l('Élément réservé') . '</label>
                            <select id="booker-filter" class="form-control">
                                <option value="">' . $this->l('Tous les éléments') . '</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>' . $this->l('Statut') . '</label>
                            <select id="status-filter" class="form-control">
                                <option value="">' . $this->l('Tous les statuts') . '</option>
                                <option value="0">' . $this->l('En attente') . '</option>
                                <option value="1">' . $this->l('Validées') . '</option>
                                <option value="2">' . $this->l('Payées') . '</option>
                                <option value="3">' . $this->l('Annulées') . '</option>
                                <option value="4">' . $this->l('Expirées') . '</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <button type="button" id="btn-bulk-validate" class="btn btn-success btn-block">
                                <i class="icon-check"></i> ' . $this->l('Valider les sélectionnées') . '
                            </button>
                        </div>
                        <div class="form-group">
                            <button type="button" id="btn-bulk-cancel" class="btn btn-danger btn-block">
                                <i class="icon-remove"></i> ' . $this->l('Annuler les sélectionnées') . '
                            </button>
                        </div>
                        <div class="form-group">
                            <button type="button" id="btn-refresh" class="btn btn-default btn-block">
                                <i class="icon-refresh"></i> ' . $this->l('Actualiser') . '
                            </button>
                        </div>
                        
                        <!-- Légende des couleurs -->
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h4 class="panel-title">' . $this->l('Légende') . '</h4>
                            </div>
                            <div class="panel-body">
                                <div class="legend-item">
                                    <span class="legend-color" style="background-color: #ffc107;"></span>
                                    ' . $this->l('En attente') . '
                                </div>
                                <div class="legend-item">
                                    <span class="legend-color" style="background-color: #17a2b8;"></span>
                                    ' . $this->l('Validée') . '
                                </div>
                                <div class="legend-item">
                                    <span class="legend-color" style="background-color: #28a745;"></span>
                                    ' . $this->l('Payée') . '
                                </div>
                                <div class="legend-item">
                                    <span class="legend-color" style="background-color: #dc3545;"></span>
                                    ' . $this->l('Annulée') . '
                                </div>
                                <div class="legend-item">
                                    <span class="legend-color" style="background-color: #6c757d;"></span>
                                    ' . $this->l('Expirée') . '
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-9">
                        <div id="reservations-calendar"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Modal pour les détails de réservation -->
        <div class="modal fade" id="reservation-details-modal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title">' . $this->l('Détails de la réservation') . '</h4>
                    </div>
                    <div class="modal-body">
                        <div id="reservation-details-content">
                            <!-- Le contenu sera chargé dynamiquement -->
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">' . $this->l('Fermer') . '</button>
                        <button type="button" id="btn-validate-reservation" class="btn btn-success">
                            <i class="icon-check"></i> ' . $this->l('Valider') . '
                        </button>
                        <button type="button" id="btn-cancel-reservation" class="btn btn-danger">
                            <i class="icon-remove"></i> ' . $this->l('Annuler') . '
                        </button>
                        <button type="button" id="btn-create-order" class="btn btn-primary">
                            <i class="icon-shopping-cart"></i> ' . $this->l('Créer commande') . '
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .legend-item {
            margin-bottom: 5px;
            font-size: 12px;
        }
        .legend-color {
            display: inline-block;
            width: 12px;
            height: 12px;
            margin-right: 8px;
            border-radius: 2px;
        }
        #reservations-calendar {
            min-height: 600px;
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
            case 'getReservations':
                $this->ajaxProcessGetReservations();
                break;
            case 'updateStatus':
                $this->ajaxProcessUpdateStatus();
                break;
            case 'validateReservation':
                $this->ajaxProcessValidateReservation();
                break;
            case 'cancelReservation':
                $this->ajaxProcessCancelReservation();
                break;
            case 'createOrder':
                $this->ajaxProcessCreateOrder();
                break;
            case 'bulkValidate':
                $this->ajaxProcessBulkValidate();
                break;
            case 'bulkCancel':
                $this->ajaxProcessBulkCancel();
                break;
            case 'sendNotification':
                $this->ajaxProcessSendNotification();
                break;
            default:
                $this->ajaxDie('Action non reconnue');
        }
    }
    
    /**
     * Récupérer les réservations pour le calendrier
     */
    private function ajaxProcessGetReservations()
    {
        try {
            $id_booker = (int)Tools::getValue('id_booker');
            $status = Tools::getValue('status');
            $start = Tools::getValue('start');
            $end = Tools::getValue('end');
            
            $sql = 'SELECT r.*, b.name as booker_name, b.location, c.firstname, c.lastname
                    FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` r
                    LEFT JOIN `' . _DB_PREFIX_ . 'booker` b ON r.id_booker = b.id_booker
                    LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON r.id_customer = c.id_customer
                    WHERE r.date_start >= "' . pSQL($start) . '"
                    AND r.date_end <= "' . pSQL($end) . '"';
                    
            if ($id_booker > 0) {
                $sql .= ' AND r.id_booker = ' . $id_booker;
            }
            
            if ($status !== '' && $status !== null) {
                $sql .= ' AND r.status = ' . (int)$status;
            }
            
            $sql .= ' ORDER BY r.date_start ASC';
            
            $reservations = Db::getInstance()->executeS($sql);
            $events = [];
            
            $statusColors = [
                0 => ['bg' => '#ffc107', 'border' => '#ffc107'], // En attente - Jaune
                1 => ['bg' => '#17a2b8', 'border' => '#17a2b8'], // Validée - Bleu
                2 => ['bg' => '#28a745', 'border' => '#28a745'], // Payée - Vert
                3 => ['bg' => '#dc3545', 'border' => '#dc3545'], // Annulée - Rouge
                4 => ['bg' => '#6c757d', 'border' => '#6c757d'], // Expirée - Gris
                5 => ['bg' => '#fd7e14', 'border' => '#fd7e14']  // Remboursée - Orange
            ];
            
            foreach ($reservations as $reservation) {
                $customer_name = $reservation['customer_firstname'] && $reservation['customer_lastname'] 
                    ? $reservation['customer_firstname'] . ' ' . $reservation['customer_lastname']
                    : $reservation['customer_firstname'] . ' ' . $reservation['customer_lastname'];
                    
                $color = $statusColors[$reservation['status']] ?? $statusColors[0];
                
                $events[] = [
                    'id' => 'res_' . $reservation['id_reserved'],
                    'title' => $reservation['booker_name'] . ' - ' . $customer_name,
                    'start' => $reservation['date_start'],
                    'end' => $reservation['date_end'],
                    'backgroundColor' => $color['bg'],
                    'borderColor' => $color['border'],
                    'textColor' => '#ffffff',
                    'extendedProps' => [
                        'type' => 'reservation',
                        'id_reserved' => (int)$reservation['id_reserved'],
                        'id_booker' => (int)$reservation['id_booker'],
                        'booking_reference' => $reservation['booking_reference'],
                        'booker_name' => $reservation['booker_name'],
                        'location' => $reservation['location'],
                        'customer_name' => $customer_name,
                        'customer_email' => $reservation['customer_email'],
                        'customer_phone' => $reservation['customer_phone'],
                        'status' => (int)$reservation['status'],
                        'status_label' => BookerAuthReserved::getStatusLabel($reservation['status']),
                        'total_price' => $reservation['total_price'] ? (float)$reservation['total_price'] : null,
                        'deposit_paid' => $reservation['deposit_paid'] ? (float)$reservation['deposit_paid'] : 0,
                        'notes' => $reservation['notes'],
                        'admin_notes' => $reservation['admin_notes'],
                        'date_reserved' => $reservation['date_reserved'],
                        'date_expiry' => $reservation['date_expiry'],
                        'date_confirmed' => $reservation['date_confirmed']
                    ]
                ];
            }
            
            $this->ajaxDie(json_encode($events));
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog('AdminBookerCalendarReservations::ajaxProcessGetReservations() - Erreur: ' . $e->getMessage(), 3);
            $this->ajaxDie(json_encode(['error' => $this->l('Erreur lors du chargement des réservations')]));
        }
    }
    
    /**
     * Valider une réservation
     */
    private function ajaxProcessValidateReservation()
    {
        try {
            $id_reserved = (int)Tools::getValue('id_reserved');
            $admin_notes = Tools::getValue('admin_notes', '');
            
            if (!$id_reserved) {
                $this->ajaxDie(json_encode(['error' => $this->l('ID de réservation manquant')]));
            }
            
            $reservation = new BookerAuthReserved($id_reserved);
            if (!Validate::isLoadedObject($reservation)) {
                $this->ajaxDie(json_encode(['error' => $this->l('Réservation introuvable')]));
            }
            
            // Mise à jour du statut
            $reservation->status = 1; // Validée
            $reservation->admin_notes = $admin_notes;
            $reservation->date_confirmed = date('Y-m-d H:i:s');
            $reservation->date_upd = date('Y-m-d H:i:s');
            
            if ($reservation->save()) {
                // Créer automatiquement une commande si configuré
                if (Configuration::get('BOOKING_AUTO_CREATE_ORDER')) {
                    $this->createOrderForReservation($reservation);
                }
                
                // Envoyer notification au client
                $this->sendValidationNotification($reservation);
                
                $this->ajaxDie(json_encode(['success' => $this->l('Réservation validée avec succès')]));
            } else {
                $this->ajaxDie(json_encode(['error' => $this->l('Erreur lors de la validation')]));
            }
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog('AdminBookerCalendarReservations::ajaxProcessValidateReservation() - Erreur: ' . $e->getMessage(), 3);
            $this->ajaxDie(json_encode(['error' => $this->l('Erreur lors de la validation')]));
        }
    }
    
    /**
     * Annuler une réservation
     */
    private function ajaxProcessCancelReservation()
    {
        try {
            $id_reserved = (int)Tools::getValue('id_reserved');
            $admin_notes = Tools::getValue('admin_notes', '');
            
            if (!$id_reserved) {
                $this->ajaxDie(json_encode(['error' => $this->l('ID de réservation manquant')]));
            }
            
            $reservation = new BookerAuthReserved($id_reserved);
            if (!Validate::isLoadedObject($reservation)) {
                $this->ajaxDie(json_encode(['error' => $this->l('Réservation introuvable')]));
            }
            
            // Mise à jour du statut
            $reservation->status = 3; // Annulée
            $reservation->admin_notes = $admin_notes;
            $reservation->date_upd = date('Y-m-d H:i:s');
            
            if ($reservation->save()) {
                // Libérer le créneau
                $this->releaseTimeSlot($reservation);
                
                // Envoyer notification au client
                $this->sendCancellationNotification($reservation);
                
                $this->ajaxDie(json_encode(['success' => $this->l('Réservation annulée avec succès')]));
            } else {
                $this->ajaxDie(json_encode(['error' => $this->l('Erreur lors de l\'annulation')]));
            }
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog('AdminBookerCalendarReservations::ajaxProcessCancelReservation() - Erreur: ' . $e->getMessage(), 3);
            $this->ajaxDie(json_encode(['error' => $this->l('Erreur lors de l\'annulation')]));
        }
    }
    
    // Méthodes utilitaires à implémenter
    private function createOrderForReservation($reservation) {
        // À implémenter : création automatique de commande
    }
    
    private function sendValidationNotification($reservation) {
        // À implémenter : envoi de notification
    }
    
    private function sendCancellationNotification($reservation) {
        // À implémenter : envoi de notification
    }
    
    private function releaseTimeSlot($reservation) {
        // À implémenter : libération du créneau
    }
}