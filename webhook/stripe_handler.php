<?php
/**
 * Webhook handler pour les événements Stripe
 * Version 2.1.4 - Traitement automatique des événements de caution
 * 
 * Ce script doit être accessible publiquement pour recevoir les webhooks Stripe.
 * URL recommandée : https://votresite.com/modules/booking/webhook/stripe_handler.php
 */

// Configuration et includes
define('_PS_ADMIN_DIR_', getcwd());
include(dirname(__FILE__) . '/../../../config/config.inc.php');
include(dirname(__FILE__) . '/../classes/StripeDepositManager.php');
include(dirname(__FILE__) . '/../classes/BookerAuthReserved.php');

class StripeWebhookHandler
{
    private $depositManager;
    private $webhook_secret;
    private $log_file;
    
    public function __construct()
    {
        $this->depositManager = new StripeDepositManager();
        $this->webhook_secret = Configuration::get('BOOKING_STRIPE_WEBHOOK_SECRET');
        $this->log_file = _PS_MODULE_DIR_ . 'booking/logs/stripe_webhooks.log';
        
        // Créer le dossier de logs s'il n'existe pas
        $log_dir = dirname($this->log_file);
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
    }
    
    /**
     * Traiter le webhook reçu
     */
    public function handle()
    {
        try {
            // Récupérer les données du webhook
            $payload = file_get_contents('php://input');
            $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
            
            if (empty($payload) || empty($sig_header)) {
                throw new Exception('Payload ou signature manquante');
            }
            
            // Vérifier la signature et parser l'événement
            $event = $this->verifyWebhookSignature($payload, $sig_header);
            
            if (!$event) {
                throw new Exception('Impossible de vérifier la signature Stripe');
            }
            
            // Logger l'événement reçu
            $this->logWebhook($event, 'received');
            
            // Vérifier si l'événement a déjà été traité
            if ($this->isEventAlreadyProcessed($event->id)) {
                $this->logWebhook($event, 'already_processed');
                $this->respondSuccess('Event already processed');
                return;
            }
            
            // Enregistrer l'événement en base
            $this->saveWebhookEvent($event);
            
            // Traiter l'événement selon son type
            $result = $this->processEvent($event);
            
            // Marquer comme traité
            $this->markEventAsProcessed($event->id, $result);
            
            // Logger le résultat
            $this->logWebhook($event, 'processed', $result);
            
            $this->respondSuccess('Event processed successfully');
            
        } catch (Exception $e) {
            $this->logError('Erreur traitement webhook: ' . $e->getMessage());
            $this->respondError($e->getMessage(), 400);
        }
    }
    
    /**
     * Vérifier la signature du webhook
     */
    private function verifyWebhookSignature($payload, $sig_header)
    {
        if (!class_exists('\Stripe\Webhook')) {
            throw new Exception('Stripe Webhook class not available');
        }
        
        try {
            return \Stripe\Webhook::constructEvent(
                $payload,
                $sig_header,
                $this->webhook_secret
            );
        } catch (\UnexpectedValueException $e) {
            throw new Exception('Invalid payload: ' . $e->getMessage());
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            throw new Exception('Invalid signature: ' . $e->getMessage());
        }
    }
    
    /**
     * Traiter un événement selon son type
     */
    private function processEvent($event)
    {
        $this->logInfo("Processing event: {$event->type} - {$event->id}");
        
        switch ($event->type) {
            // Événements Setup Intent (empreinte carte)
            case 'setup_intent.succeeded':
                return $this->handleSetupIntentSucceeded($event->data->object);
            
            case 'setup_intent.setup_failed':
                return $this->handleSetupIntentFailed($event->data->object);
            
            // Événements Payment Intent (pré-autorisation)
            case 'payment_intent.created':
                return $this->handlePaymentIntentCreated($event->data->object);
            
            case 'payment_intent.requires_capture':
                return $this->handlePaymentIntentRequiresCapture($event->data->object);
            
            case 'payment_intent.succeeded':
                return $this->handlePaymentIntentSucceeded($event->data->object);
            
            case 'payment_intent.payment_failed':
                return $this->handlePaymentIntentFailed($event->data->object);
            
            case 'payment_intent.canceled':
                return $this->handlePaymentIntentCanceled($event->data->object);
            
            // Événements Charge (transactions)
            case 'charge.succeeded':
                return $this->handleChargeSucceeded($event->data->object);
            
            case 'charge.failed':
                return $this->handleChargeFailed($event->data->object);
            
            case 'charge.captured':
                return $this->handleChargeCaptured($event->data->object);
            
            case 'charge.dispute.created':
                return $this->handleChargeDisputeCreated($event->data->object);
            
            // Événements Refund (remboursements)
            case 'charge.refunded':
                return $this->handleChargeRefunded($event->data->object);
            
            case 'refund.created':
                return $this->handleRefundCreated($event->data->object);
            
            case 'refund.updated':
                return $this->handleRefundUpdated($event->data->object);
            
            default:
                $this->logInfo("Event type {$event->type} not handled");
                return ['status' => 'ignored', 'message' => 'Event type not handled'];
        }
    }
    
    /**
     * Gérer le succès d'un SetupIntent
     */
    private function handleSetupIntentSucceeded($setup_intent)
    {
        $reservation_id = $setup_intent->metadata->reservation_id ?? null;
        
        if (!$reservation_id) {
            return ['status' => 'ignored', 'message' => 'No reservation_id in metadata'];
        }
        
        // Mettre à jour le statut de la caution
        Db::getInstance()->execute('
            UPDATE `' . _DB_PREFIX_ . 'booking_deposits`
            SET status = "authorized", 
                date_authorized = NOW(), 
                date_upd = NOW(),
                payment_method_id = "' . pSQL($setup_intent->payment_method) . '"
            WHERE id_reservation = ' . (int)$reservation_id
        );
        
        // Ajouter à l'historique
        $this->addDepositHistory($reservation_id, 'authorized', 'pending', 'authorized', null, $setup_intent->id);
        
        $this->logInfo("SetupIntent succeeded for reservation {$reservation_id}");
        
        return ['status' => 'processed', 'message' => 'SetupIntent marked as authorized'];
    }
    
    /**
     * Gérer l'échec d'un SetupIntent
     */
    private function handleSetupIntentFailed($setup_intent)
    {
        $reservation_id = $setup_intent->metadata->reservation_id ?? null;
        
        if (!$reservation_id) {
            return ['status' => 'ignored', 'message' => 'No reservation_id in metadata'];
        }
        
        $error_message = $setup_intent->last_setup_error->message ?? 'Unknown error';
        
        // Mettre à jour le statut de la caution
        Db::getInstance()->execute('
            UPDATE `' . _DB_PREFIX_ . 'booking_deposits`
            SET status = "failed", 
                failure_reason = "' . pSQL($error_message) . '",
                date_upd = NOW()
            WHERE id_reservation = ' . (int)$reservation_id
        );
        
        // Ajouter à l'historique
        $this->addDepositHistory($reservation_id, 'failed', 'pending', 'failed', null, $setup_intent->id, $error_message);
        
        // Marquer la réservation comme expirée
        Db::getInstance()->execute('
            UPDATE `' . _DB_PREFIX_ . 'booker_auth_reserved`
            SET status = ' . BookerAuthReserved::STATUS_EXPIRED . ',
                date_upd = NOW()
            WHERE id_reserved = ' . (int)$reservation_id
        );
        
        $this->logError("SetupIntent failed for reservation {$reservation_id}: {$error_message}");
        
        return ['status' => 'processed', 'message' => 'SetupIntent marked as failed'];
    }
    
    /**
     * Gérer la création d'un PaymentIntent
     */
    private function handlePaymentIntentCreated($payment_intent)
    {
        $reservation_id = $payment_intent->metadata->reservation_id ?? null;
        
        if (!$reservation_id || $payment_intent->metadata->type !== 'deposit_preauth') {
            return ['status' => 'ignored', 'message' => 'Not a deposit payment intent'];
        }
        
        // Mettre à jour l'ID du PaymentIntent
        Db::getInstance()->execute('
            UPDATE `' . _DB_PREFIX_ . 'booking_deposits`
            SET payment_intent_id = "' . pSQL($payment_intent->id) . '",
                date_upd = NOW()
            WHERE id_reservation = ' . (int)$reservation_id
        );
        
        $this->logInfo("PaymentIntent created for reservation {$reservation_id}: {$payment_intent->id}");
        
        return ['status' => 'processed', 'message' => 'PaymentIntent ID recorded'];
    }
    
    /**
     * Gérer un PaymentIntent qui nécessite une capture
     */
    private function handlePaymentIntentRequiresCapture($payment_intent)
    {
        $reservation_id = $payment_intent->metadata->reservation_id ?? null;
        
        if (!$reservation_id) {
            return ['status' => 'ignored', 'message' => 'No reservation_id in metadata'];
        }
        
        // Mettre à jour le statut
        Db::getInstance()->execute('
            UPDATE `' . _DB_PREFIX_ . 'booking_deposits`
            SET status = "authorized",
                date_authorized = NOW(),
                date_upd = NOW()
            WHERE id_reservation = ' . (int)$reservation_id
        );
        
        // Ajouter à l'historique
        $this->addDepositHistory($reservation_id, 'authorized', 'pending', 'authorized', $payment_intent->amount, $payment_intent->id);
        
        $this->logInfo("PaymentIntent requires capture for reservation {$reservation_id}");
        
        return ['status' => 'processed', 'message' => 'PaymentIntent marked as authorized'];
    }
    
    /**
     * Gérer le succès d'un PaymentIntent
     */
    private function handlePaymentIntentSucceeded($payment_intent)
    {
        $reservation_id = $payment_intent->metadata->reservation_id ?? null;
        
        if (!$reservation_id) {
            return ['status' => 'ignored', 'message' => 'No reservation_id in metadata'];
        }
        
        // Si c'est une capture (amount_received > 0)
        if ($payment_intent->amount_received > 0) {
            Db::getInstance()->execute('
                UPDATE `' . _DB_PREFIX_ . 'booking_deposits`
                SET status = "captured",
                    captured_amount = ' . (int)$payment_intent->amount_received . ',
                    date_captured = NOW(),
                    date_upd = NOW()
                WHERE id_reservation = ' . (int)$reservation_id
            );
            
            $this->addDepositHistory($reservation_id, 'captured', 'authorized', 'captured', $payment_intent->amount_received, $payment_intent->id);
        }
        
        $this->logInfo("PaymentIntent succeeded for reservation {$reservation_id}");
        
        return ['status' => 'processed', 'message' => 'PaymentIntent success processed'];
    }
    
    /**
     * Gérer l'échec d'un PaymentIntent
     */
    private function handlePaymentIntentFailed($payment_intent)
    {
        $reservation_id = $payment_intent->metadata->reservation_id ?? null;
        
        if (!$reservation_id) {
            return ['status' => 'ignored', 'message' => 'No reservation_id in metadata'];
        }
        
        $error_message = $payment_intent->last_payment_error->message ?? 'Payment failed';
        
        // Mettre à jour le statut
        Db::getInstance()->execute('
            UPDATE `' . _DB_PREFIX_ . 'booking_deposits`
            SET status = "failed",
                failure_reason = "' . pSQL($error_message) . '",
                date_upd = NOW()
            WHERE id_reservation = ' . (int)$reservation_id
        );
        
        // Ajouter à l'historique
        $this->addDepositHistory($reservation_id, 'failed', 'authorized', 'failed', null, $payment_intent->id, $error_message);
        
        $this->logError("PaymentIntent failed for reservation {$reservation_id}: {$error_message}");
        
        return ['status' => 'processed', 'message' => 'PaymentIntent failure processed'];
    }
    
    /**
     * Gérer l'annulation d'un PaymentIntent
     */
    private function handlePaymentIntentCanceled($payment_intent)
    {
        $reservation_id = $payment_intent->metadata->reservation_id ?? null;
        
        if (!$reservation_id) {
            return ['status' => 'ignored', 'message' => 'No reservation_id in metadata'];
        }
        
        // Mettre à jour le statut
        Db::getInstance()->execute('
            UPDATE `' . _DB_PREFIX_ . 'booking_deposits`
            SET status = "released",
                date_released = NOW(),
                date_upd = NOW()
            WHERE id_reservation = ' . (int)$reservation_id
        );
        
        // Ajouter à l'historique
        $this->addDepositHistory($reservation_id, 'released', 'authorized', 'released', null, $payment_intent->id);
        
        $this->logInfo("PaymentIntent canceled for reservation {$reservation_id}");
        
        return ['status' => 'processed', 'message' => 'PaymentIntent cancellation processed'];
    }
    
    /**
     * Gérer le succès d'un Charge
     */
    private function handleChargeSucceeded($charge)
    {
        // Récupérer le PaymentIntent associé
        if (!$charge->payment_intent) {
            return ['status' => 'ignored', 'message' => 'No payment_intent in charge'];
        }
        
        try {
            $payment_intent = \Stripe\PaymentIntent::retrieve($charge->payment_intent);
            $reservation_id = $payment_intent->metadata->reservation_id ?? null;
            
            if (!$reservation_id) {
                return ['status' => 'ignored', 'message' => 'No reservation_id in payment_intent metadata'];
            }
            
            $this->logInfo("Charge succeeded for reservation {$reservation_id}: {$charge->id}");
            
            return ['status' => 'processed', 'message' => 'Charge success logged'];
            
        } catch (Exception $e) {
            $this->logError("Error retrieving PaymentIntent for charge {$charge->id}: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Error processing charge'];
        }
    }
    
    /**
     * Gérer l'échec d'un Charge
     */
    private function handleChargeFailed($charge)
    {
        $failure_message = $charge->failure_message ?? 'Charge failed';
        $this->logError("Charge failed: {$charge->id} - {$failure_message}");
        
        return ['status' => 'processed', 'message' => 'Charge failure logged'];
    }
    
    /**
     * Gérer la capture d'un Charge
     */
    private function handleChargeCaptured($charge)
    {
        try {
            $payment_intent = \Stripe\PaymentIntent::retrieve($charge->payment_intent);
            $reservation_id = $payment_intent->metadata->reservation_id ?? null;
            
            if (!$reservation_id) {
                return ['status' => 'ignored', 'message' => 'No reservation_id in metadata'];
            }
            
            // Mettre à jour la caution
            Db::getInstance()->execute('
                UPDATE `' . _DB_PREFIX_ . 'booking_deposits`
                SET status = "captured",
                    captured_amount = ' . (int)$charge->amount_captured . ',
                    date_captured = NOW(),
                    date_upd = NOW()
                WHERE id_reservation = ' . (int)$reservation_id
            );
            
            $this->addDepositHistory($reservation_id, 'captured', 'authorized', 'captured', $charge->amount_captured, $charge->id);
            
            $this->logInfo("Charge captured for reservation {$reservation_id}: {$charge->amount_captured} cents");
            
            return ['status' => 'processed', 'message' => 'Charge capture processed'];
            
        } catch (Exception $e) {
            $this->logError("Error processing charge capture: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Error processing charge capture'];
        }
    }
    
    /**
     * Gérer la création d'un litige
     */
    private function handleChargeDisputeCreated($dispute)
    {
        try {
            $charge = \Stripe\Charge::retrieve($dispute->charge);
            $payment_intent = \Stripe\PaymentIntent::retrieve($charge->payment_intent);
            $reservation_id = $payment_intent->metadata->reservation_id ?? null;
            
            if ($reservation_id) {
                // Notifier l'administrateur
                $this->sendAdminNotification(
                    'Litige Stripe créé',
                    "Un litige a été créé pour la réservation #{$reservation_id}\n\n" .
                    "Montant: " . ($dispute->amount / 100) . "€\n" .
                    "Raison: {$dispute->reason}\n" .
                    "ID Litige: {$dispute->id}\n\n" .
                    "Veuillez vérifier votre dashboard Stripe pour plus de détails."
                );
                
                $this->logError("Dispute created for reservation {$reservation_id}: {$dispute->id} - Amount: {$dispute->amount} cents");
            } else {
                $this->logError("Dispute created but no reservation found: {$dispute->id}");
            }
            
            return ['status' => 'processed', 'message' => 'Dispute notification sent'];
            
        } catch (Exception $e) {
            $this->logError("Error processing dispute: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Error processing dispute'];
        }
    }
    
    /**
     * Gérer le remboursement d'un Charge
     */
    private function handleChargeRefunded($charge)
    {
        try {
            $payment_intent = \Stripe\PaymentIntent::retrieve($charge->payment_intent);
            $reservation_id = $payment_intent->metadata->reservation_id ?? null;
            
            if (!$reservation_id) {
                return ['status' => 'ignored', 'message' => 'No reservation_id in metadata'];
            }
            
            // Mettre à jour la caution
            Db::getInstance()->execute('
                UPDATE `' . _DB_PREFIX_ . 'booking_deposits`
                SET status = "refunded",
                    refunded_amount = ' . (int)$charge->amount_refunded . ',
                    date_refunded = NOW(),
                    date_upd = NOW()
                WHERE id_reservation = ' . (int)$reservation_id
            );
            
            $this->addDepositHistory($reservation_id, 'refunded', 'captured', 'refunded', $charge->amount_refunded, $charge->id);
            
            $this->logInfo("Charge refunded for reservation {$reservation_id}: {$charge->amount_refunded} cents");
            
            return ['status' => 'processed', 'message' => 'Refund processed'];
            
        } catch (Exception $e) {
            $this->logError("Error processing refund: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Error processing refund'];
        }
    }
    
    /**
     * Gérer la création d'un Refund
     */
    private function handleRefundCreated($refund)
    {
        $this->logInfo("Refund created: {$refund->id} - Amount: {$refund->amount} cents");
        return ['status' => 'processed', 'message' => 'Refund creation logged'];
    }
    
    /**
     * Gérer la mise à jour d'un Refund
     */
    private function handleRefundUpdated($refund)
    {
        $this->logInfo("Refund updated: {$refund->id} - Status: {$refund->status}");
        return ['status' => 'processed', 'message' => 'Refund update logged'];
    }
    
    /**
     * Ajouter une entrée à l'historique des cautions
     */
    private function addDepositHistory($reservation_id, $action_type, $old_status, $new_status, $amount = null, $stripe_id = null, $details = null)
    {
        // Récupérer l'ID de la caution
        $id_deposit = Db::getInstance()->getValue('
            SELECT id_deposit FROM `' . _DB_PREFIX_ . 'booking_deposits`
            WHERE id_reservation = ' . (int)$reservation_id
        );
        
        if (!$id_deposit) {
            $this->logError("No deposit found for reservation {$reservation_id}");
            return false;
        }
        
        return Db::getInstance()->execute('
            INSERT INTO `' . _DB_PREFIX_ . 'booking_deposit_history`
            (id_deposit, id_reservation, action_type, old_status, new_status, amount, stripe_id, details, date_add)
            VALUES (
                ' . (int)$id_deposit . ',
                ' . (int)$reservation_id . ',
                "' . pSQL($action_type) . '",
                ' . ($old_status ? '"' . pSQL($old_status) . '"' : 'NULL') . ',
                "' . pSQL($new_status) . '",
                ' . ($amount ? (int)$amount : 'NULL') . ',
                ' . ($stripe_id ? '"' . pSQL($stripe_id) . '"' : 'NULL') . ',
                ' . ($details ? '"' . pSQL($details) . '"' : 'NULL') . ',
                NOW()
            )
        ');
    }
    
    /**
     * Vérifier si un événement a déjà été traité
     */
    private function isEventAlreadyProcessed($event_id)
    {
        return (bool)Db::getInstance()->getValue('
            SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booking_webhooks`
            WHERE stripe_event_id = "' . pSQL($event_id) . '"
            AND processed = 1
        ');
    }
    
    /**
     * Sauvegarder l'événement webhook en base
     */
    private function saveWebhookEvent($event)
    {
        return Db::getInstance()->execute('
            INSERT INTO `' . _DB_PREFIX_ . 'booking_webhooks`
            (stripe_event_id, event_type, payload, date_received)
            VALUES (
                "' . pSQL($event->id) . '",
                "' . pSQL($event->type) . '",
                "' . pSQL(json_encode($event)) . '",
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                event_type = "' . pSQL($event->type) . '",
                payload = "' . pSQL(json_encode($event)) . '"
        ');
    }
    
    /**
     * Marquer un événement comme traité
     */
    private function markEventAsProcessed($event_id, $result)
    {
        return Db::getInstance()->execute('
            UPDATE `' . _DB_PREFIX_ . 'booking_webhooks`
            SET processed = 1,
                processing_result = "' . pSQL(json_encode($result)) . '",
                date_processed = NOW()
            WHERE stripe_event_id = "' . pSQL($event_id) . '"
        ');
    }
    
    /**
     * Envoyer une notification à l'administrateur
     */
    private function sendAdminNotification($subject, $message)
    {
        $admin_email = Configuration::get('PS_SHOP_EMAIL');
        if ($admin_email) {
            Mail::Send(
                Configuration::get('PS_LANG_DEFAULT'),
                'admin_notification',
                $subject,
                array('{message}' => $message),
                $admin_email,
                null,
                null,
                null,
                null,
                null,
                _PS_MODULE_DIR_ . 'booking/mails/',
                false,
                null
            );
        }
    }
    
    /**
     * Logger un webhook
     */
    private function logWebhook($event, $status, $result = null)
    {
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event_id' => $event->id,
            'event_type' => $event->type,
            'status' => $status,
            'result' => $result
        ];
        
        file_put_contents(
            $this->log_file,
            json_encode($log_entry) . "\n",
            FILE_APPEND | LOCK_EX
        );
    }
    
    /**
     * Logger une information
     */
    private function logInfo($message)
    {
        $this->writeLog('INFO', $message);
    }
    
    /**
     * Logger une erreur
     */
    private function logError($message)
    {
        $this->writeLog('ERROR', $message);
    }
    
    /**
     * Écrire dans le log
     */
    private function writeLog($level, $message)
    {
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => $level,
            'message' => $message,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        file_put_contents(
            $this->log_file,
            json_encode($log_entry) . "\n",
            FILE_APPEND | LOCK_EX
        );
        
        // Logger aussi dans PrestaShop
        PrestaShopLogger::addLog(
            'StripeWebhook: ' . $message,
            $level === 'ERROR' ? 3 : 1,
            null,
            'Booking'
        );
    }
    
    /**
     * Répondre avec succès
     */
    private function respondSuccess($message = 'OK')
    {
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => $message]);
        exit;
    }
    
    /**
     * Répondre avec erreur
     */
    private function respondError($message, $code = 500)
    {
        http_response_code($code);
        echo json_encode(['status' => 'error', 'message' => $message]);
        exit;
    }
}

// Vérifier que c'est bien une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed';
    exit;
}

// Vérifier que le module est activé
if (!Module::isEnabled('booking')) {
    http_response_code(503);
    echo 'Module not available';
    exit;
}

// Traiter le webhook
$handler = new StripeWebhookHandler();
$handler->handle();
