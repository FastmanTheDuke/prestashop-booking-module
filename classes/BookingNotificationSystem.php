<?php
/**
 * Système de notifications pour le module de réservation
 * Gestion des notifications email, SMS et push pour les administrateurs
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once(dirname(__FILE__) . '/BookerAuthReserved.php');
require_once(dirname(__FILE__) . '/Booker.php');

class BookingNotificationSystem
{
    private $context;
    private $module;
    
    // Types de notifications
    const NOTIFICATION_NEW_BOOKING = 'new_booking';
    const NOTIFICATION_BOOKING_CANCELLED = 'booking_cancelled';
    const NOTIFICATION_BOOKING_PAID = 'booking_paid';
    const NOTIFICATION_BOOKING_EXPIRED = 'booking_expired';
    const NOTIFICATION_DAILY_SUMMARY = 'daily_summary';
    const NOTIFICATION_WEEKLY_SUMMARY = 'weekly_summary';
    
    // Méthodes de notification
    const METHOD_EMAIL = 'email';
    const METHOD_SMS = 'sms';
    const METHOD_PUSH = 'push';
    const METHOD_SLACK = 'slack';
    
    public function __construct($module = null)
    {
        $this->context = Context::getContext();
        $this->module = $module;
    }
    
    /**
     * Envoyer une notification de nouvelle réservation
     */
    public function sendNewBookingNotification(BookerAuthReserved $reservation)
    {
        if (!Configuration::get('BOOKING_ADMIN_NOTIFICATIONS')) {
            return false;
        }
        
        $booker = new Booker($reservation->id_booker);
        
        $data = [
            'reservation' => $reservation,
            'booker' => $booker,
            'customer_name' => $reservation->customer_firstname . ' ' . $reservation->customer_lastname,
            'formatted_date' => date('d/m/Y', strtotime($reservation->date_reserved)),
            'formatted_time' => $reservation->hour_from . 'h - ' . $reservation->hour_to . 'h',
            'admin_url' => $this->getAdminReservationUrl($reservation->id)
        ];
        
        // Envoyer selon les méthodes configurées
        $methods = $this->getNotificationMethods(self::NOTIFICATION_NEW_BOOKING);
        
        $results = [];
        foreach ($methods as $method) {
            switch ($method) {
                case self::METHOD_EMAIL:
                    $results[$method] = $this->sendEmailNotification(self::NOTIFICATION_NEW_BOOKING, $data);
                    break;
                    
                case self::METHOD_SMS:
                    $results[$method] = $this->sendSMSNotification(self::NOTIFICATION_NEW_BOOKING, $data);
                    break;
                    
                case self::METHOD_SLACK:
                    $results[$method] = $this->sendSlackNotification(self::NOTIFICATION_NEW_BOOKING, $data);
                    break;
                    
                case self::METHOD_PUSH:
                    $results[$method] = $this->sendPushNotification(self::NOTIFICATION_NEW_BOOKING, $data);
                    break;
            }
        }
        
        // Log de l'envoi
        PrestaShopLogger::addLog(
            'Notifications envoyées pour nouvelle réservation: ' . $reservation->booking_reference,
            1,
            null,
            'BookingNotificationSystem'
        );
        
        return $results;
    }
    
    /**
     * Envoyer une notification de paiement
     */
    public function sendPaymentNotification(BookerAuthReserved $reservation)
    {
        if (!Configuration::get('BOOKING_PAYMENT_NOTIFICATIONS')) {
            return false;
        }
        
        $booker = new Booker($reservation->id_booker);
        
        $data = [
            'reservation' => $reservation,
            'booker' => $booker,
            'customer_name' => $reservation->customer_firstname . ' ' . $reservation->customer_lastname,
            'amount' => $reservation->total_price + $reservation->deposit_amount,
            'formatted_date' => date('d/m/Y', strtotime($reservation->date_reserved)),
            'admin_url' => $this->getAdminReservationUrl($reservation->id)
        ];
        
        $methods = $this->getNotificationMethods(self::NOTIFICATION_BOOKING_PAID);
        
        $results = [];
        foreach ($methods as $method) {
            switch ($method) {
                case self::METHOD_EMAIL:
                    $results[$method] = $this->sendEmailNotification(self::NOTIFICATION_BOOKING_PAID, $data);
                    break;
                    
                case self::METHOD_SLACK:
                    $results[$method] = $this->sendSlackNotification(self::NOTIFICATION_BOOKING_PAID, $data);
                    break;
            }
        }
        
        return $results;
    }
    
    /**
     * Envoyer une notification d'annulation
     */
    public function sendCancellationNotification(BookerAuthReserved $reservation, $reason = '')
    {
        if (!Configuration::get('BOOKING_CANCELLATION_NOTIFICATIONS')) {
            return false;
        }
        
        $booker = new Booker($reservation->id_booker);
        
        $data = [
            'reservation' => $reservation,
            'booker' => $booker,
            'customer_name' => $reservation->customer_firstname . ' ' . $reservation->customer_lastname,
            'reason' => $reason,
            'formatted_date' => date('d/m/Y', strtotime($reservation->date_reserved)),
            'admin_url' => $this->getAdminReservationUrl($reservation->id)
        ];
        
        $methods = $this->getNotificationMethods(self::NOTIFICATION_BOOKING_CANCELLED);
        
        $results = [];
        foreach ($methods as $method) {
            switch ($method) {
                case self::METHOD_EMAIL:
                    $results[$method] = $this->sendEmailNotification(self::NOTIFICATION_BOOKING_CANCELLED, $data);
                    break;
                    
                case self::METHOD_SLACK:
                    $results[$method] = $this->sendSlackNotification(self::NOTIFICATION_BOOKING_CANCELLED, $data);
                    break;
            }
        }
        
        return $results;
    }
    
    /**
     * Envoyer le résumé quotidien
     */
    public function sendDailySummary()
    {
        if (!Configuration::get('BOOKING_DAILY_SUMMARY')) {
            return false;
        }
        
        $today = date('Y-m-d');
        
        // Statistiques du jour
        $stats = [
            'new_bookings' => $this->getTodayBookingsCount(),
            'paid_bookings' => $this->getTodayPaidBookingsCount(),
            'cancelled_bookings' => $this->getTodayCancelledBookingsCount(),
            'revenue' => $this->getTodayRevenue(),
            'upcoming_today' => $this->getTodayUpcomingReservations(),
            'pending_count' => $this->getPendingBookingsCount()
        ];
        
        $data = [
            'date' => date('d/m/Y'),
            'stats' => $stats,
            'admin_url' => $this->getAdminDashboardUrl()
        ];
        
        return $this->sendEmailNotification(self::NOTIFICATION_DAILY_SUMMARY, $data);
    }
    
    /**
     * Envoyer le résumé hebdomadaire
     */
    public function sendWeeklySummary()
    {
        if (!Configuration::get('BOOKING_WEEKLY_SUMMARY')) {
            return false;
        }
        
        $stats = [
            'week_bookings' => $this->getWeekBookingsCount(),
            'week_revenue' => $this->getWeekRevenue(),
            'occupancy_rate' => $this->getWeekOccupancyRate(),
            'top_bookers' => $this->getTopBookersThisWeek(),
            'avg_booking_value' => $this->getAverageBookingValue()
        ];
        
        $data = [
            'week_start' => date('d/m/Y', strtotime('monday this week')),
            'week_end' => date('d/m/Y', strtotime('sunday this week')),
            'stats' => $stats,
            'admin_url' => $this->getAdminDashboardUrl()
        ];
        
        return $this->sendEmailNotification(self::NOTIFICATION_WEEKLY_SUMMARY, $data);
    }
    
    /**
     * Envoyer une notification par email
     */
    private function sendEmailNotification($type, $data)
    {
        $recipients = $this->getNotificationRecipients($type);
        
        if (empty($recipients)) {
            return false;
        }
        
        $template = $this->getEmailTemplate($type);
        $subject = $this->getEmailSubject($type, $data);
        
        $templateVars = $this->prepareEmailVariables($type, $data);
        
        $results = [];
        foreach ($recipients as $recipient) {
            try {
                $result = Mail::Send(
                    $this->context->language->id,
                    $template,
                    $subject,
                    $templateVars,
                    $recipient['email'],
                    $recipient['name'],
                    Configuration::get('PS_SHOP_EMAIL'),
                    Configuration::get('PS_SHOP_NAME'),
                    null,
                    null,
                    dirname(__FILE__) . '/../mails/'
                );
                
                $results[$recipient['email']] = $result;
                
            } catch (Exception $e) {
                PrestaShopLogger::addLog(
                    'Erreur envoi email notification: ' . $e->getMessage(),
                    3,
                    null,
                    'BookingNotificationSystem'
                );
                $results[$recipient['email']] = false;
            }
        }
        
        return $results;
    }
    
    /**
     * Envoyer une notification Slack
     */
    private function sendSlackNotification($type, $data)
    {
        $webhook_url = Configuration::get('BOOKING_SLACK_WEBHOOK');
        
        if (!$webhook_url) {
            return false;
        }
        
        $message = $this->prepareSlackMessage($type, $data);
        
        $payload = json_encode([
            'text' => $message['text'],
            'attachments' => $message['attachments'] ?? [],
            'username' => 'Booking System',
            'icon_emoji' => ':calendar:'
        ]);
        
        $ch = curl_init($webhook_url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $http_code === 200;
    }
    
    /**
     * Envoyer une notification SMS
     */
    private function sendSMSNotification($type, $data)
    {
        $sms_service = Configuration::get('BOOKING_SMS_SERVICE');
        $sms_recipients = explode(',', Configuration::get('BOOKING_SMS_RECIPIENTS'));
        
        if (!$sms_service || empty($sms_recipients)) {
            return false;
        }
        
        $message = $this->prepareSMSMessage($type, $data);
        
        // Intégration avec différents services SMS
        switch ($sms_service) {
            case 'twilio':
                return $this->sendTwilioSMS($sms_recipients, $message);
                
            case 'ovh':
                return $this->sendOvhSMS($sms_recipients, $message);
                
            default:
                return false;
        }
    }
    
    /**
     * Envoyer une notification push
     */
    private function sendPushNotification($type, $data)
    {
        // Intégration avec des services comme Firebase, OneSignal, etc.
        // À implémenter selon les besoins
        return false;
    }
    
    /**
     * Préparer les variables pour les templates email
     */
    private function prepareEmailVariables($type, $data)
    {
        $baseVars = [
            '{shop_name}' => Configuration::get('PS_SHOP_NAME'),
            '{shop_email}' => Configuration::get('PS_SHOP_EMAIL'),
            '{date}' => date('d/m/Y H:i'),
            '{admin_url}' => $data['admin_url'] ?? ''
        ];
        
        switch ($type) {
            case self::NOTIFICATION_NEW_BOOKING:
                return array_merge($baseVars, [
                    '{booking_reference}' => $data['reservation']->booking_reference,
                    '{customer_name}' => $data['customer_name'],
                    '{booker_name}' => $data['booker']->name,
                    '{reservation_date}' => $data['formatted_date'],
                    '{reservation_time}' => $data['formatted_time'],
                    '{customer_email}' => $data['reservation']->customer_email,
                    '{customer_phone}' => $data['reservation']->customer_phone ?? 'Non renseigné'
                ]);
                
            case self::NOTIFICATION_BOOKING_PAID:
                return array_merge($baseVars, [
                    '{booking_reference}' => $data['reservation']->booking_reference,
                    '{customer_name}' => $data['customer_name'],
                    '{amount}' => number_format($data['amount'], 2) . ' €',
                    '{booker_name}' => $data['booker']->name,
                    '{reservation_date}' => $data['formatted_date']
                ]);
                
            case self::NOTIFICATION_DAILY_SUMMARY:
                return array_merge($baseVars, [
                    '{summary_date}' => $data['date'],
                    '{new_bookings_count}' => $data['stats']['new_bookings'],
                    '{paid_bookings_count}' => $data['stats']['paid_bookings'],
                    '{revenue}' => number_format($data['stats']['revenue'], 2) . ' €',
                    '{pending_count}' => $data['stats']['pending_count']
                ]);
                
            default:
                return $baseVars;
        }
    }
    
    /**
     * Préparer un message Slack
     */
    private function prepareSlackMessage($type, $data)
    {
        switch ($type) {
            case self::NOTIFICATION_NEW_BOOKING:
                return [
                    'text' => '🆕 Nouvelle réservation reçue !',
                    'attachments' => [
                        [
                            'color' => 'good',
                            'fields' => [
                                [
                                    'title' => 'Client',
                                    'value' => $data['customer_name'],
                                    'short' => true
                                ],
                                [
                                    'title' => 'Référence',
                                    'value' => $data['reservation']->booking_reference,
                                    'short' => true
                                ],
                                [
                                    'title' => 'Élément',
                                    'value' => $data['booker']->name,
                                    'short' => true
                                ],
                                [
                                    'title' => 'Date/Heure',
                                    'value' => $data['formatted_date'] . ' à ' . $data['formatted_time'],
                                    'short' => true
                                ]
                            ],
                            'actions' => [
                                [
                                    'type' => 'button',
                                    'text' => 'Voir dans l\'admin',
                                    'url' => $data['admin_url']
                                ]
                            ]
                        ]
                    ]
                ];
                
            case self::NOTIFICATION_BOOKING_PAID:
                return [
                    'text' => '💰 Paiement reçu !',
                    'attachments' => [
                        [
                            'color' => '#28a745',
                            'fields' => [
                                [
                                    'title' => 'Montant',
                                    'value' => number_format($data['amount'], 2) . ' €',
                                    'short' => true
                                ],
                                [
                                    'title' => 'Référence',
                                    'value' => $data['reservation']->booking_reference,
                                    'short' => true
                                ]
                            ]
                        ]
                    ]
                ];
                
            default:
                return ['text' => 'Notification du système de réservation'];
        }
    }
    
    /**
     * Préparer un message SMS
     */
    private function prepareSMSMessage($type, $data)
    {
        switch ($type) {
            case self::NOTIFICATION_NEW_BOOKING:
                return sprintf(
                    "Nouvelle réservation: %s pour %s le %s. Ref: %s",
                    $data['customer_name'],
                    $data['booker']->name,
                    $data['formatted_date'],
                    $data['reservation']->booking_reference
                );
                
            case self::NOTIFICATION_BOOKING_PAID:
                return sprintf(
                    "Paiement reçu: %.2f€ pour réservation %s",
                    $data['amount'],
                    $data['reservation']->booking_reference
                );
                
            default:
                return "Notification du système de réservation";
        }
    }
    
    /**
     * Récupérer les destinataires des notifications
     */
    private function getNotificationRecipients($type)
    {
        $recipients = [];
        
        // Administrateurs par défaut
        $admin_emails = explode(',', Configuration::get('BOOKING_ADMIN_EMAILS'));
        
        foreach ($admin_emails as $email) {
            $email = trim($email);
            if (Validate::isEmail($email)) {
                $recipients[] = [
                    'email' => $email,
                    'name' => 'Administrateur'
                ];
            }
        }
        
        // Destinataires spécifiques selon le type
        $specific_config = 'BOOKING_' . strtoupper($type) . '_RECIPIENTS';
        $specific_emails = Configuration::get($specific_config);
        
        if ($specific_emails) {
            foreach (explode(',', $specific_emails) as $email) {
                $email = trim($email);
                if (Validate::isEmail($email)) {
                    $recipients[] = [
                        'email' => $email,
                        'name' => 'Destinataire spécifique'
                    ];
                }
            }
        }
        
        return array_unique($recipients, SORT_REGULAR);
    }
    
    /**
     * Récupérer les méthodes de notification configurées
     */
    private function getNotificationMethods($type)
    {
        $config_key = 'BOOKING_' . strtoupper($type) . '_METHODS';
        $methods = Configuration::get($config_key, self::METHOD_EMAIL);
        
        if (is_string($methods)) {
            return explode(',', $methods);
        }
        
        return [self::METHOD_EMAIL];
    }
    
    /**
     * Récupérer le template email
     */
    private function getEmailTemplate($type)
    {
        $templates = [
            self::NOTIFICATION_NEW_BOOKING => 'admin_new_booking',
            self::NOTIFICATION_BOOKING_PAID => 'admin_booking_paid',
            self::NOTIFICATION_BOOKING_CANCELLED => 'admin_booking_cancelled',
            self::NOTIFICATION_DAILY_SUMMARY => 'admin_daily_summary',
            self::NOTIFICATION_WEEKLY_SUMMARY => 'admin_weekly_summary'
        ];
        
        return $templates[$type] ?? 'admin_notification';
    }
    
    /**
     * Récupérer le sujet de l'email
     */
    private function getEmailSubject($type, $data)
    {
        switch ($type) {
            case self::NOTIFICATION_NEW_BOOKING:
                return 'Nouvelle réservation: ' . $data['reservation']->booking_reference;
                
            case self::NOTIFICATION_BOOKING_PAID:
                return 'Paiement reçu: ' . $data['reservation']->booking_reference;
                
            case self::NOTIFICATION_BOOKING_CANCELLED:
                return 'Réservation annulée: ' . $data['reservation']->booking_reference;
                
            case self::NOTIFICATION_DAILY_SUMMARY:
                return 'Résumé quotidien des réservations - ' . $data['date'];
                
            case self::NOTIFICATION_WEEKLY_SUMMARY:
                return 'Résumé hebdomadaire des réservations';
                
            default:
                return 'Notification du système de réservation';
        }
    }
    
    /**
     * Récupérer l'URL d'administration d'une réservation
     */
    private function getAdminReservationUrl($reservation_id)
    {
        return $this->context->link->getAdminLink('AdminBookerAuthReserved') . '&viewbooker_auth_reserved&id_reserved=' . $reservation_id;
    }
    
    /**
     * Récupérer l'URL du dashboard admin
     */
    private function getAdminDashboardUrl()
    {
        return $this->context->link->getAdminLink('AdminBookerView');
    }
    
    /**
     * Méthodes de statistiques pour les résumés
     */
    private function getTodayBookingsCount()
    {
        return (int)Db::getInstance()->getValue('
            SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`
            WHERE DATE(date_add) = CURDATE() AND active = 1
        ');
    }
    
    private function getTodayPaidBookingsCount()
    {
        return (int)Db::getInstance()->getValue('
            SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`
            WHERE DATE(date_add) = CURDATE() AND status = ' . BookerAuthReserved::STATUS_PAID . ' AND active = 1
        ');
    }
    
    private function getTodayCancelledBookingsCount()
    {
        return (int)Db::getInstance()->getValue('
            SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`
            WHERE DATE(date_upd) = CURDATE() AND status = ' . BookerAuthReserved::STATUS_CANCELLED . ' AND active = 1
        ');
    }
    
    private function getTodayRevenue()
    {
        $result = Db::getInstance()->getValue('
            SELECT SUM(total_price) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`
            WHERE DATE(date_add) = CURDATE() AND status = ' . BookerAuthReserved::STATUS_PAID . ' AND active = 1
        ');
        
        return $result ? (float)$result : 0;
    }
    
    private function getTodayUpcomingReservations()
    {
        return Db::getInstance()->executeS('
            SELECT r.*, b.name as booker_name
            FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` r
            LEFT JOIN `' . _DB_PREFIX_ . 'booker` b ON (r.id_booker = b.id_booker)
            WHERE r.date_reserved = CURDATE() 
            AND r.status IN (' . BookerAuthReserved::STATUS_ACCEPTED . ', ' . BookerAuthReserved::STATUS_PAID . ')
            AND r.active = 1
            ORDER BY r.hour_from ASC
        ');
    }
    
    private function getPendingBookingsCount()
    {
        return (int)Db::getInstance()->getValue('
            SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`
            WHERE status = ' . BookerAuthReserved::STATUS_PENDING . ' AND active = 1
        ');
    }
    
    private function getWeekBookingsCount()
    {
        return (int)Db::getInstance()->getValue('
            SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`
            WHERE WEEK(date_add) = WEEK(CURDATE()) AND YEAR(date_add) = YEAR(CURDATE()) AND active = 1
        ');
    }
    
    private function getWeekRevenue()
    {
        $result = Db::getInstance()->getValue('
            SELECT SUM(total_price) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`
            WHERE WEEK(date_add) = WEEK(CURDATE()) AND YEAR(date_add) = YEAR(CURDATE()) 
            AND status = ' . BookerAuthReserved::STATUS_PAID . ' AND active = 1
        ');
        
        return $result ? (float)$result : 0;
    }
    
    private function getWeekOccupancyRate()
    {
        // Calcul du taux d'occupation de la semaine
        // À implémenter selon la logique métier
        return 0;
    }
    
    private function getTopBookersThisWeek()
    {
        return Db::getInstance()->executeS('
            SELECT b.name, COUNT(*) as bookings_count
            FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` r
            LEFT JOIN `' . _DB_PREFIX_ . 'booker` b ON (r.id_booker = b.id_booker)
            WHERE WEEK(r.date_add) = WEEK(CURDATE()) AND YEAR(r.date_add) = YEAR(CURDATE()) AND r.active = 1
            GROUP BY r.id_booker
            ORDER BY bookings_count DESC
            LIMIT 5
        ');
    }
    
    private function getAverageBookingValue()
    {
        $result = Db::getInstance()->getValue('
            SELECT AVG(total_price) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`
            WHERE WEEK(date_add) = WEEK(CURDATE()) AND YEAR(date_add) = YEAR(CURDATE()) 
            AND status = ' . BookerAuthReserved::STATUS_PAID . ' AND active = 1
        ');
        
        return $result ? (float)$result : 0;
    }
}