<?php
/**
 * Contrôleur pour le calendrier de gestion des réservations
 * Interface de validation et gestion des statuts avec actions en lot
 */

require_once(dirname(__FILE__) . '/../../classes/Booker.php');
require_once(dirname(__FILE__) . '/../../classes/BookerAuth.php');
require_once(dirname(__FILE__) . '/../../classes/BookerAuthReserved.php');

class AdminBookerReservationCalendarController extends ModuleAdminController
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
            $this->addJS(_MODULE_DIR_ . $this->module->name . '/views/js/reservation-calendar.js');
            $this->addCSS(_MODULE_DIR_ . $this->module->name . '/views/css/admin-calendar.css');
            
            // Récupération des bookers actifs
            $bookers = $this->getActiveBookers();
            
            // Statuts des réservations
            $statuses = BookerAuthReserved::getStatuses();
            
            // URLs AJAX pour les actions
            $ajax_urls = [
                'get_reservations' => $this->context->link->getAdminLink('AdminBookerReservationCalendar') . '&ajax=1&action=getReservations',
                'get_reservation_details' => $this->context->link->getAdminLink('AdminBookerReservationCalendar') . '&ajax=1&action=getReservationDetails',
                'update_status' => $this->context->link->getAdminLink('AdminBookerReservationCalendar') . '&ajax=1&action=updateStatus',
                'validate_reservation' => $this->context->link->getAdminLink('AdminBookerReservationCalendar') . '&ajax=1&action=validateReservation',
                'cancel_reservation' => $this->context->link->getAdminLink('AdminBookerReservationCalendar') . '&ajax=1&action=cancelReservation',
                'create_order' => $this->context->link->getAdminLink('AdminBookerReservationCalendar') . '&ajax=1&action=createOrder',
                'bulk_validate' => $this->context->link->getAdminLink('AdminBookerReservationCalendar') . '&ajax=1&action=bulkValidate',
                'bulk_cancel' => $this->context->link->getAdminLink('AdminBookerReservationCalendar') . '&ajax=1&action=bulkCancel',
                'send_notification' => $this->context->link->getAdminLink('AdminBookerReservationCalendar') . '&ajax=1&action=sendNotification',
                'bulk_send_notifications' => $this->context->link->getAdminLink('AdminBookerReservationCalendar') . '&ajax=1&action=bulkSendNotifications',
                'export_reservations' => $this->context->link->getAdminLink('AdminBookerReservationCalendar') . '&ajax=1&action=exportReservations'
            ];
            
            // Configuration par défaut
            $default_config = [
                'locale' => 'fr',
                'business_hours' => [
                    'startTime' => Configuration::get('BOOKING_BUSINESS_HOURS_START') ?: '08:00',
                    'endTime' => Configuration::get('BOOKING_BUSINESS_HOURS_END') ?: '18:00',
                    'daysOfWeek' => [1, 2, 3, 4, 5, 6, 7]
                ],
                'default_view' => 'timeGridWeek'
            ];
            
            // Variables pour le template
            $this->context->smarty->assign([
                'bookers' => $bookers,
                'statuses' => $statuses,
                'ajax_urls' => $ajax_urls,
                'current_date' => date('Y-m-d'),
                'default_config' => $default_config,
                'module_dir' => _MODULE_DIR_ . $this->module->name . '/',
                'business_hours_start' => Configuration::get('BOOKING_BUSINESS_HOURS_START') ?: '08:00',
                'business_hours_end' => Configuration::get('BOOKING_BUSINESS_HOURS_END') ?: '18:00',
                'reservation_stats' => $this->getReservationStats(),
                'token' => $this->token
            ]);
            
            $template_path = _PS_MODULE_DIR_ . $this->module->name . '/views/templates/admin/reservation_calendar.tpl';
            
            if (file_exists($template_path)) {
                return $this->context->smarty->fetch($template_path);
            } else {
                return $this->generateDefaultView();
            }
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog('AdminBookerReservationCalendar::renderView() - Erreur: ' . $e->getMessage(), 3);
            return $this->displayError($this->l('Erreur lors du chargement du calendrier des réservations'));
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
            PrestaShopLogger::addLog('AdminBookerReservationCalendar::getActiveBookers() - Erreur: ' . $e->getMessage(), 3);
            return [];
        }
    }
    
    /**
     * Statistiques des réservations
     */
    private function getReservationStats()
    {
        try {
            $stats = [
                'pending' => 0,
                'confirmed' => 0,
                'paid' => 0,
                'cancelled' => 0,
                'completed' => 0,
                'revenue' => 0
            ];
            
            // Compter par statut
            $status_counts = Db::getInstance()->executeS('
                SELECT status, COUNT(*) as count
                FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`
                WHERE date_start >= CURDATE()
                GROUP BY status
            ');
            
            foreach ($status_counts as $stat) {
                if (isset($stats[$stat['status']])) {
                    $stats[$stat['status']] = (int)$stat['count'];
                }
            }
            
            // Calculer le CA du mois
            $stats['revenue'] = (float)Db::getInstance()->getValue('
                SELECT SUM(total_price) 
                FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`
                WHERE status IN ("paid", "completed") 
                AND MONTH(date_add) = MONTH(NOW()) 
                AND YEAR(date_add) = YEAR(NOW())
            ') ?: 0;
            
            return $stats;
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog('AdminBookerReservationCalendar::getReservationStats() - Erreur: ' . $e->getMessage(), 3);
            return ['pending' => 0, 'confirmed' => 0, 'paid' => 0, 'cancelled' => 0, 'completed' => 0, 'revenue' => 0];
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
                case 'getReservations':
                    $this->ajaxProcessGetReservations();
                    break;
                    
                case 'getReservationDetails':
                    $this->ajaxProcessGetReservationDetails();
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
                    
                case 'bulkSendNotifications':
                    $this->ajaxProcessBulkSendNotifications();
                    break;
                    
                case 'exportReservations':
                    $this->ajaxProcessExportReservations();
                    break;
                    
                default:
                    $this->ajaxDie(json_encode(['success' => false, 'message' => 'Action non reconnue']));
            }
        } catch (Exception $e) {
            PrestaShopLogger::addLog('AdminBookerReservationCalendar::ajaxProcess() - Erreur: ' . $e->getMessage(), 3);
            $this->ajaxDie(json_encode(['success' => false, 'message' => $e->getMessage()]));
        }
    }
    
    /**
     * Récupérer les réservations pour le calendrier
     */
    private function ajaxProcessGetReservations()
    {
        $start = Tools::getValue('start');
        $end = Tools::getValue('end');
        $booker_id = (int)Tools::getValue('booker_id');
        $status_filter = Tools::getValue('status');
        
        try {
            $sql = 'SELECT bar.id, bar.id_booker, bar.booking_reference, bar.customer_firstname, bar.customer_lastname,
                           bar.customer_email, bar.customer_phone, bar.date_start, bar.date_end, bar.total_price,
                           bar.status, bar.payment_status, bar.notes, bar.admin_notes,
                           b.name as booker_name, b.price as booker_price,
                           ba.max_bookings
                    FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` bar
                    LEFT JOIN `' . _DB_PREFIX_ . 'booker` b ON b.id = bar.id_booker
                    LEFT JOIN `' . _DB_PREFIX_ . 'booker_auth` ba ON ba.id = bar.id_auth
                    WHERE bar.date_start <= "' . pSQL($end) . '" AND bar.date_end >= "' . pSQL($start) . '"';
            
            if ($booker_id > 0) {
                $sql .= ' AND bar.id_booker = ' . (int)$booker_id;
            }
            
            if ($status_filter && $status_filter !== 'all') {
                $sql .= ' AND bar.status = "' . pSQL($status_filter) . '"';
            }
            
            $sql .= ' ORDER BY bar.date_start ASC';
            
            $reservations = Db::getInstance()->executeS($sql);
            $events = [];
            
            foreach ($reservations as $reservation) {
                // Déterminer la couleur selon le statut
                $color = $this->getReservationColor($reservation['status']);
                
                // Construire l'événement pour FullCalendar
                $event = [
                    'id' => 'reservation_' . $reservation['id'],
                    'title' => $reservation['booker_name'] . ' - ' . $reservation['customer_firstname'] . ' ' . $reservation['customer_lastname'],
                    'start' => $reservation['date_start'],
                    'end' => $reservation['date_end'],
                    'backgroundColor' => $color,
                    'borderColor' => $color,
                    'textColor' => $this->getTextColor($color),
                    'extendedProps' => [
                        'type' => 'reservation',
                        'reservation_id' => $reservation['id'],
                        'booker_id' => $reservation['id_booker'],
                        'booker_name' => $reservation['booker_name'],
                        'booking_reference' => $reservation['booking_reference'],
                        'customer_name' => $reservation['customer_firstname'] . ' ' . $reservation['customer_lastname'],
                        'customer_email' => $reservation['customer_email'],
                        'customer_phone' => $reservation['customer_phone'],
                        'total_price' => $reservation['total_price'],
                        'status' => $reservation['status'],
                        'payment_status' => $reservation['payment_status'],
                        'notes' => $reservation['notes'],
                        'admin_notes' => $reservation['admin_notes']
                    ]
                ];
                
                $events[] = $event;
            }
            
            $this->ajaxDie(json_encode($events));
            
        } catch (Exception $e) {
            $this->ajaxDie(json_encode(['error' => 'Erreur lors du chargement des réservations: ' . $e->getMessage()]));
        }
    }
    
    /**
     * Déterminer la couleur d'une réservation selon son statut
     */
    private function getReservationColor($status)
    {
        $colors = [
            'pending' => '#ffc107',     // Jaune - En attente
            'confirmed' => '#17a2b8',   // Bleu - Confirmé
            'paid' => '#28a745',        // Vert - Payé
            'cancelled' => '#dc3545',   // Rouge - Annulé
            'completed' => '#6f42c1',   // Violet - Terminé
            'refunded' => '#fd7e14'     // Orange - Remboursé
        ];
        
        return $colors[$status] ?? '#6c757d'; // Gris par défaut
    }
    
    /**
     * Déterminer la couleur du texte selon la couleur de fond
     */
    private function getTextColor($backgroundColor)
    {
        // Couleurs claires qui nécessitent du texte noir
        $lightColors = ['#ffc107', '#fd7e14'];
        
        return in_array($backgroundColor, $lightColors) ? '#000000' : '#ffffff';
    }
    
    /**
     * Récupérer les détails d'une réservation
     */
    private function ajaxProcessGetReservationDetails()
    {
        $id = (int)Tools::getValue('id');
        
        try {
            if (!$id) {
                throw new Exception('ID de réservation manquant');
            }
            
            $sql = 'SELECT bar.*, b.name as booker_name, b.price as booker_price,
                           ba.max_bookings, ba.date_from as availability_start, ba.date_to as availability_end
                    FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` bar
                    LEFT JOIN `' . _DB_PREFIX_ . 'booker` b ON b.id = bar.id_booker
                    LEFT JOIN `' . _DB_PREFIX_ . 'booker_auth` ba ON ba.id = bar.id_auth
                    WHERE bar.id = ' . (int)$id;
            
            $reservation = Db::getInstance()->getRow($sql);
            
            if (!$reservation) {
                throw new Exception('Réservation non trouvée');
            }
            
            // Ajouter les informations de commande si elle existe
            if ($reservation['id_order']) {
                $order = new Order($reservation['id_order']);
                if (Validate::isLoadedObject($order)) {
                    $reservation['order_reference'] = $order->reference;
                    $reservation['order_status'] = $order->getCurrentState();
                }
            }
            
            $this->ajaxDie(json_encode([
                'success' => true,
                'data' => $reservation
            ]));
            
        } catch (Exception $e) {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));
        }
    }
    
    /**
     * Mettre à jour le statut d'une réservation
     */
    private function ajaxProcessUpdateStatus()
    {
        $id = (int)Tools::getValue('id');
        $status = Tools::getValue('status');
        $payment_status = Tools::getValue('payment_status');
        $admin_notes = Tools::getValue('admin_notes');
        
        try {
            if (!$id || !$status) {
                throw new Exception('Paramètres manquants');
            }
            
            $reservation = new BookerAuthReserved($id);
            if (!Validate::isLoadedObject($reservation)) {
                throw new Exception('Réservation non trouvée');
            }
            
            $old_status = $reservation->status;
            
            // Mettre à jour les champs
            $reservation->status = $status;
            if ($payment_status) {
                $reservation->payment_status = $payment_status;
            }
            if ($admin_notes !== null) {
                $reservation->admin_notes = $admin_notes;
            }
            
            if ($reservation->save()) {
                // Actions spécifiques selon le changement de statut
                $this->handleStatusChange($reservation, $old_status, $status);
                
                $this->ajaxDie(json_encode([
                    'success' => true,
                    'message' => 'Statut mis à jour avec succès'
                ]));
            } else {
                throw new Exception('Erreur lors de la mise à jour');
            }
            
        } catch (Exception $e) {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));
        }
    }
    
    /**
     * Gérer les actions lors du changement de statut
     */
    private function handleStatusChange($reservation, $old_status, $new_status)
    {
        try {
            // Si passage à "confirmé" depuis "en attente"
            if ($old_status === 'pending' && $new_status === 'confirmed') {
                // Créer automatiquement la commande si configuré
                if (Configuration::get('BOOKING_AUTO_CREATE_ORDER')) {
                    $reservation->createOrder();
                }
                
                // Envoyer notification de confirmation
                if (Configuration::get('BOOKING_AUTO_CONFIRMATION_EMAIL')) {
                    $this->sendNotificationEmail($reservation, 'confirmation');
                }
            }
            
            // Si annulation
            if ($new_status === 'cancelled') {
                // Libérer le créneau
                $this->releaseTimeSlot($reservation->id_auth);
                
                // Envoyer notification d'annulation
                $this->sendNotificationEmail($reservation, 'cancellation');
            }
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog('AdminBookerReservationCalendar::handleStatusChange() - Erreur: ' . $e->getMessage(), 3);
        }
    }
    
    /**
     * Valider une réservation
     */
    private function ajaxProcessValidateReservation()
    {
        $id = (int)Tools::getValue('id');
        $create_order = (bool)Tools::getValue('create_order');
        $send_notification = (bool)Tools::getValue('send_notification');
        
        try {
            if (!$id) {
                throw new Exception('ID de réservation manquant');
            }
            
            $reservation = new BookerAuthReserved($id);
            if (!Validate::isLoadedObject($reservation)) {
                throw new Exception('Réservation non trouvée');
            }
            
            $old_status = $reservation->status;
            $reservation->status = 'confirmed';
            
            if ($reservation->save()) {
                // Créer la commande si demandé
                if ($create_order) {
                    $order_created = $reservation->createOrder();
                    if (!$order_created) {
                        PrestaShopLogger::addLog('Impossible de créer la commande pour la réservation ' . $reservation->id, 2);
                    }
                }
                
                // Envoyer la notification si demandé
                if ($send_notification) {
                    $this->sendNotificationEmail($reservation, 'confirmation');
                }
                
                $this->ajaxDie(json_encode([
                    'success' => true,
                    'message' => 'Réservation validée avec succès'
                ]));
            } else {
                throw new Exception('Erreur lors de la validation');
            }
            
        } catch (Exception $e) {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));
        }
    }
    
    /**
     * Annuler une réservation
     */
    private function ajaxProcessCancelReservation()
    {
        $id = (int)Tools::getValue('id');
        $reason = Tools::getValue('reason');
        $notes = Tools::getValue('notes');
        $send_notification = (bool)Tools::getValue('send_notification');
        $process_refund = (bool)Tools::getValue('process_refund');
        
        try {
            if (!$id) {
                throw new Exception('ID de réservation manquant');
            }
            
            $reservation = new BookerAuthReserved($id);
            if (!Validate::isLoadedObject($reservation)) {
                throw new Exception('Réservation non trouvée');
            }
            
            $reservation->status = 'cancelled';
            if ($notes) {
                $reservation->admin_notes = ($reservation->admin_notes ? $reservation->admin_notes . "\n" : '') . 
                                          'Annulation: ' . $reason . ($notes ? ' - ' . $notes : '');
            }
            
            if ($reservation->save()) {
                // Libérer le créneau
                $this->releaseTimeSlot($reservation->id_auth);
                
                // Traiter le remboursement si demandé
                if ($process_refund && $reservation->payment_status === 'captured') {
                    $refund_processed = $this->processRefund($reservation);
                    if ($refund_processed) {
                        $reservation->payment_status = 'refunded';
                        $reservation->save();
                    }
                }
                
                // Envoyer la notification si demandé
                if ($send_notification) {
                    $this->sendNotificationEmail($reservation, 'cancellation');
                }
                
                $this->ajaxDie(json_encode([
                    'success' => true,
                    'message' => 'Réservation annulée avec succès'
                ]));
            } else {
                throw new Exception('Erreur lors de l\'annulation');
            }
            
        } catch (Exception $e) {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));
        }
    }
    
    /**
     * Créer une commande pour une réservation
     */
    private function ajaxProcessCreateOrder()
    {
        $id = (int)Tools::getValue('id');
        
        try {
            if (!$id) {
                throw new Exception('ID de réservation manquant');
            }
            
            $reservation = new BookerAuthReserved($id);
            if (!Validate::isLoadedObject($reservation)) {
                throw new Exception('Réservation non trouvée');
            }
            
            if ($reservation->id_order) {
                throw new Exception('Une commande existe déjà pour cette réservation');
            }
            
            if ($reservation->createOrder()) {
                $this->ajaxDie(json_encode([
                    'success' => true,
                    'message' => 'Commande créée avec succès',
                    'order_id' => $reservation->id_order
                ]));
            } else {
                throw new Exception('Impossible de créer la commande');
            }
            
        } catch (Exception $e) {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));
        }
    }
    
    /**
     * Validation en lot
     */
    private function ajaxProcessBulkValidate()
    {
        $ids = Tools::getValue('ids');
        $auto_create_order = (bool)Tools::getValue('auto_create_order');
        $send_notification = (bool)Tools::getValue('send_notification');
        
        try {
            if (!$ids || !is_array($ids)) {
                throw new Exception('Aucune réservation sélectionnée');
            }
            
            $validated_count = 0;
            $errors = [];
            
            foreach ($ids as $id) {
                try {
                    $reservation = new BookerAuthReserved((int)$id);
                    if (Validate::isLoadedObject($reservation)) {
                        $reservation->status = 'confirmed';
                        
                        if ($reservation->save()) {
                            $validated_count++;
                            
                            // Créer la commande si demandé
                            if ($auto_create_order) {
                                $reservation->createOrder();
                            }
                            
                            // Envoyer la notification si demandé
                            if ($send_notification) {
                                $this->sendNotificationEmail($reservation, 'confirmation');
                            }
                        }
                    }
                } catch (Exception $e) {
                    $errors[] = 'Réservation #' . $id . ': ' . $e->getMessage();
                }
            }
            
            $message = $validated_count . ' réservation(s) validée(s) avec succès';
            if (!empty($errors)) {
                $message .= '. Erreurs: ' . implode(', ', $errors);
            }
            
            $this->ajaxDie(json_encode([
                'success' => true,
                'message' => $message,
                'validated_count' => $validated_count
            ]));
            
        } catch (Exception $e) {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));
        }
    }
    
    /**
     * Annulation en lot
     */
    private function ajaxProcessBulkCancel()
    {
        $ids = Tools::getValue('ids');
        $cancel_reason = Tools::getValue('cancel_reason');
        $cancel_notes = Tools::getValue('cancel_notes');
        $send_notification = (bool)Tools::getValue('send_notification');
        $process_refund = (bool)Tools::getValue('process_refund');
        
        try {
            if (!$ids || !is_array($ids)) {
                throw new Exception('Aucune réservation sélectionnée');
            }
            
            if (!$cancel_reason) {
                throw new Exception('Motif d\'annulation requis');
            }
            
            $cancelled_count = 0;
            $errors = [];
            
            foreach ($ids as $id) {
                try {
                    $reservation = new BookerAuthReserved((int)$id);
                    if (Validate::isLoadedObject($reservation)) {
                        $reservation->status = 'cancelled';
                        $reservation->admin_notes = ($reservation->admin_notes ? $reservation->admin_notes . "\n" : '') . 
                                                  'Annulation en lot: ' . $cancel_reason . 
                                                  ($cancel_notes ? ' - ' . $cancel_notes : '');
                        
                        if ($reservation->save()) {
                            $cancelled_count++;
                            
                            // Libérer le créneau
                            $this->releaseTimeSlot($reservation->id_auth);
                            
                            // Traiter le remboursement si demandé
                            if ($process_refund && $reservation->payment_status === 'captured') {
                                $this->processRefund($reservation);
                            }
                            
                            // Envoyer la notification si demandé
                            if ($send_notification) {
                                $this->sendNotificationEmail($reservation, 'cancellation');
                            }
                        }
                    }
                } catch (Exception $e) {
                    $errors[] = 'Réservation #' . $id . ': ' . $e->getMessage();
                }
            }
            
            $message = $cancelled_count . ' réservation(s) annulée(s) avec succès';
            if (!empty($errors)) {
                $message .= '. Erreurs: ' . implode(', ', $errors);
            }
            
            $this->ajaxDie(json_encode([
                'success' => true,
                'message' => $message,
                'cancelled_count' => $cancelled_count
            ]));
            
        } catch (Exception $e) {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));
        }
    }
    
    /**
     * Envoyer une notification
     */
    private function ajaxProcessSendNotification()
    {
        $reservation_id = (int)Tools::getValue('reservation_id');
        $notification_type = Tools::getValue('notification_type');
        $custom_message = Tools::getValue('custom_message');
        $send_sms = (bool)Tools::getValue('send_sms');
        
        try {
            if (!$reservation_id || !$notification_type) {
                throw new Exception('Paramètres manquants');
            }
            
            $reservation = new BookerAuthReserved($reservation_id);
            if (!Validate::isLoadedObject($reservation)) {
                throw new Exception('Réservation non trouvée');
            }
            
            if ($notification_type === 'custom' && !$custom_message) {
                throw new Exception('Message personnalisé requis');
            }
            
            $sent = $this->sendNotificationEmail($reservation, $notification_type, $custom_message);
            
            if ($sent) {
                $this->ajaxDie(json_encode([
                    'success' => true,
                    'message' => 'Notification envoyée avec succès'
                ]));
            } else {
                throw new Exception('Erreur lors de l\'envoi de la notification');
            }
            
        } catch (Exception $e) {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));
        }
    }
    
    /**
     * Envoi de notifications en lot
     */
    private function ajaxProcessBulkSendNotifications()
    {
        $ids = Tools::getValue('ids');
        $notification_type = Tools::getValue('notification_type');
        $custom_message = Tools::getValue('custom_message');
        
        try {
            if (!$ids || !is_array($ids) || !$notification_type) {
                throw new Exception('Paramètres manquants');
            }
            
            $sent_count = 0;
            $errors = [];
            
            foreach ($ids as $id) {
                try {
                    $reservation = new BookerAuthReserved((int)$id);
                    if (Validate::isLoadedObject($reservation)) {
                        if ($this->sendNotificationEmail($reservation, $notification_type, $custom_message)) {
                            $sent_count++;
                        }
                    }
                } catch (Exception $e) {
                    $errors[] = 'Réservation #' . $id . ': ' . $e->getMessage();
                }
            }
            
            $message = $sent_count . ' notification(s) envoyée(s) avec succès';
            if (!empty($errors)) {
                $message .= '. Erreurs: ' . implode(', ', $errors);
            }
            
            $this->ajaxDie(json_encode([
                'success' => true,
                'message' => $message,
                'sent_count' => $sent_count
            ]));
            
        } catch (Exception $e) {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));
        }
    }
    
    /**
     * Exporter les réservations
     */
    private function ajaxProcessExportReservations()
    {
        $start = Tools::getValue('start');
        $end = Tools::getValue('end');
        $format = Tools::getValue('format', 'csv');
        
        try {
            $sql = 'SELECT bar.*, b.name as booker_name
                    FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` bar
                    LEFT JOIN `' . _DB_PREFIX_ . 'booker` b ON b.id = bar.id_booker
                    WHERE 1=1';
            
            if ($start) {
                $sql .= ' AND bar.date_start >= "' . pSQL($start) . '"';
            }
            if ($end) {
                $sql .= ' AND bar.date_end <= "' . pSQL($end) . '"';
            }
            
            $sql .= ' ORDER BY bar.date_start ASC';
            
            $reservations = Db::getInstance()->executeS($sql);
            
            if ($format === 'csv') {
                $this->exportToCsv($reservations);
            } else {
                $this->ajaxDie(json_encode([
                    'success' => true,
                    'data' => $reservations
                ]));
            }
            
        } catch (Exception $e) {
            $this->ajaxDie(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));
        }
    }
    
    /**
     * Libérer un créneau de disponibilité
     */
    private function releaseTimeSlot($id_auth)
    {
        try {
            $auth = new BookerAuth($id_auth);
            if (Validate::isLoadedObject($auth) && $auth->current_bookings > 0) {
                $auth->current_bookings--;
                $auth->save();
            }
        } catch (Exception $e) {
            PrestaShopLogger::addLog('AdminBookerReservationCalendar::releaseTimeSlot() - Erreur: ' . $e->getMessage(), 3);
        }
    }
    
    /**
     * Traiter un remboursement
     */
    private function processRefund($reservation)
    {
        // À implémenter selon le module de paiement utilisé
        // Intégration avec Stripe, PayPal, etc.
        try {
            // Exemple pour Stripe
            if (Configuration::get('BOOKING_STRIPE_ENABLED') && $reservation->stripe_payment_intent_id) {
                // Code de remboursement Stripe ici
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            PrestaShopLogger::addLog('AdminBookerReservationCalendar::processRefund() - Erreur: ' . $e->getMessage(), 3);
            return false;
        }
    }
    
    /**
     * Envoyer un email de notification
     */
    private function sendNotificationEmail($reservation, $type, $custom_message = null)
    {
        try {
            if (!Configuration::get('BOOKING_NOTIFICATIONS_ENABLED')) {
                return false;
            }
            
            $templates = [
                'confirmation' => 'booking_confirmation',
                'reminder' => 'booking_reminder',
                'modification' => 'booking_modification',
                'cancellation' => 'booking_cancellation',
                'custom' => 'booking_custom'
            ];
            
            $template = $templates[$type] ?? 'booking_notification';
            
            $variables = [
                '{booking_reference}' => $reservation->booking_reference,
                '{customer_name}' => $reservation->customer_firstname . ' ' . $reservation->customer_lastname,
                '{date_start}' => date('d/m/Y H:i', strtotime($reservation->date_start)),
                '{date_end}' => date('d/m/Y H:i', strtotime($reservation->date_end)),
                '{total_price}' => number_format($reservation->total_price, 2) . '€',
                '{custom_message}' => $custom_message ?: ''
            ];
            
            return Mail::Send(
                $this->context->language->id,
                $template,
                'Réservation - ' . $reservation->booking_reference,
                $variables,
                $reservation->customer_email,
                $reservation->customer_firstname . ' ' . $reservation->customer_lastname,
                null,
                null,
                null,
                null,
                _PS_MODULE_DIR_ . $this->module->name . '/mails/'
            );
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog('AdminBookerReservationCalendar::sendNotificationEmail() - Erreur: ' . $e->getMessage(), 3);
            return false;
        }
    }
    
    /**
     * Exporter au format CSV
     */
    private function exportToCsv($reservations)
    {
        $filename = 'reservations_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // En-têtes
        $headers = [
            'ID', 'Référence', 'Élément', 'Client', 'Email', 'Téléphone',
            'Date début', 'Date fin', 'Prix total', 'Statut', 'Statut paiement',
            'Date création'
        ];
        fputcsv($output, $headers, ';');
        
        // Données
        foreach ($reservations as $reservation) {
            $row = [
                $reservation['id'],
                $reservation['booking_reference'],
                $reservation['booker_name'],
                $reservation['customer_firstname'] . ' ' . $reservation['customer_lastname'],
                $reservation['customer_email'],
                $reservation['customer_phone'],
                $reservation['date_start'],
                $reservation['date_end'],
                $reservation['total_price'],
                $reservation['status'],
                $reservation['payment_status'],
                $reservation['date_add']
            ];
            fputcsv($output, $row, ';');
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Vue par défaut en cas d'absence de template
     */
    private function generateDefaultView()
    {
        return '<div class="alert alert-warning">
            <h4>Calendrier des réservations</h4>
            <p>Le template du calendrier n\'est pas disponible. Veuillez vérifier l\'installation du module.</p>
            <p>Template attendu : ' . _PS_MODULE_DIR_ . $this->module->name . '/views/templates/admin/reservation_calendar.tpl</p>
        </div>';
    }
}
