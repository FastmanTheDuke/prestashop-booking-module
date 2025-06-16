<?php
/**
 * Gestionnaire des paiements Stripe pour le module de réservations
 * Gestion des paiements, cautions et empreintes CB
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once(dirname(__FILE__) . '/BookerAuthReserved.php');

class StripePaymentManager
{
    private $stripe_secret_key;
    private $stripe_publishable_key;
    private $is_test_mode;
    private $webhook_secret;
    
    public function __construct()
    {
        $this->is_test_mode = !Configuration::get('BOOKING_STRIPE_LIVE_MODE');
        
        if ($this->is_test_mode) {
            $this->stripe_secret_key = Configuration::get('BOOKING_STRIPE_TEST_SECRET_KEY');
            $this->stripe_publishable_key = Configuration::get('BOOKING_STRIPE_TEST_PUBLISHABLE_KEY');
            $this->webhook_secret = Configuration::get('BOOKING_STRIPE_TEST_WEBHOOK_SECRET');
        } else {
            $this->stripe_secret_key = Configuration::get('BOOKING_STRIPE_LIVE_SECRET_KEY');
            $this->stripe_publishable_key = Configuration::get('BOOKING_STRIPE_LIVE_PUBLISHABLE_KEY');
            $this->webhook_secret = Configuration::get('BOOKING_STRIPE_LIVE_WEBHOOK_SECRET');
        }
        
        // Initialiser Stripe (nécessite l'API Stripe)
        if (class_exists('Stripe\Stripe')) {
            \Stripe\Stripe::setApiKey($this->stripe_secret_key);
        }
    }
    
    /**
     * Créer un Payment Intent pour une réservation avec caution optionnelle
     */
    public function createPaymentIntent($reservation_data, $include_deposit = false)
    {
        try {
            if (!class_exists('Stripe\PaymentIntent')) {
                throw new Exception('API Stripe non disponible');
            }
            
            $amount = (float)$reservation_data['total_price'] * 100; // Stripe utilise les centimes
            $deposit_amount = $include_deposit ? (float)$reservation_data['deposit_amount'] * 100 : 0;
            
            $payment_intent_data = [
                'amount' => $amount,
                'currency' => 'eur',
                'payment_method_types' => ['card'],
                'capture_method' => 'manual', // Capture manuelle pour validation
                'confirmation_method' => 'manual',
                'confirm' => false,
                'metadata' => [
                    'booking_reference' => $reservation_data['booking_reference'],
                    'id_reservation' => $reservation_data['id_reserved'],
                    'customer_email' => $reservation_data['customer_email'],
                    'booker_name' => $reservation_data['booker_name'],
                    'date_reserved' => $reservation_data['date_reserved'],
                    'module' => 'prestashop_booking'
                ]
            ];
            
            // Si une caution est requise, créer un setup intent séparé
            $setup_intent = null;
            if ($include_deposit) {
                $setup_intent = $this->createDepositSetupIntent($reservation_data);
                $payment_intent_data['metadata']['deposit_setup_intent'] = $setup_intent['id'];
                $payment_intent_data['metadata']['deposit_amount'] = $deposit_amount;
            }
            
            $payment_intent = \Stripe\PaymentIntent::create($payment_intent_data);
            
            // Enregistrer les informations dans la base de données
            $this->saveStripeSession([
                'id_reservation' => $reservation_data['id_reserved'],
                'payment_intent_id' => $payment_intent->id,
                'setup_intent_id' => $setup_intent ? $setup_intent['id'] : null,
                'amount' => $amount,
                'deposit_amount' => $deposit_amount,
                'status' => 'created',
                'metadata' => json_encode($payment_intent_data['metadata'])
            ]);
            
            return [
                'success' => true,
                'payment_intent' => [
                    'id' => $payment_intent->id,
                    'client_secret' => $payment_intent->client_secret,
                    'amount' => $amount,
                    'currency' => 'eur'
                ],
                'setup_intent' => $setup_intent,
                'publishable_key' => $this->stripe_publishable_key
            ];
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog('StripePaymentManager::createPaymentIntent() - Erreur: ' . $e->getMessage(), 3);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Créer un Setup Intent pour la caution (empreinte CB)
     */
    public function createDepositSetupIntent($reservation_data)
    {
        try {
            $setup_intent = \Stripe\SetupIntent::create([
                'payment_method_types' => ['card'],
                'usage' => 'off_session',
                'metadata' => [
                    'type' => 'deposit',
                    'booking_reference' => $reservation_data['booking_reference'],
                    'id_reservation' => $reservation_data['id_reserved'],
                    'customer_email' => $reservation_data['customer_email'],
                    'deposit_amount' => (float)$reservation_data['deposit_amount'] * 100
                ]
            ]);
            
            return [
                'id' => $setup_intent->id,
                'client_secret' => $setup_intent->client_secret
            ];
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog('StripePaymentManager::createDepositSetupIntent() - Erreur: ' . $e->getMessage(), 3);
            throw $e;
        }
    }
    
    /**
     * Confirmer le paiement côté serveur
     */
    public function confirmPayment($payment_intent_id, $payment_method_id = null)
    {
        try {
            $payment_intent = \Stripe\PaymentIntent::retrieve($payment_intent_id);
            
            $confirm_data = [];
            if ($payment_method_id) {
                $confirm_data['payment_method'] = $payment_method_id;
            }
            
            $payment_intent = $payment_intent->confirm($confirm_data);
            
            // Mettre à jour le statut dans la base de données
            $this->updateStripeSessionStatus($payment_intent_id, 'confirmed');
            
            return [
                'success' => true,
                'payment_intent' => $payment_intent,
                'requires_action' => $payment_intent->status === 'requires_action',
                'client_secret' => $payment_intent->client_secret
            ];
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog('StripePaymentManager::confirmPayment() - Erreur: ' . $e->getMessage(), 3);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Capturer le paiement (après validation de la réservation)
     */
    public function capturePayment($payment_intent_id, $amount_to_capture = null)
    {
        try {
            $payment_intent = \Stripe\PaymentIntent::retrieve($payment_intent_id);
            
            $capture_data = [];
            if ($amount_to_capture) {
                $capture_data['amount_to_capture'] = $amount_to_capture;
            }
            
            $payment_intent = $payment_intent->capture($capture_data);
            
            // Mettre à jour la réservation et les statuts
            $this->updateReservationPaymentStatus($payment_intent_id, 'captured');
            $this->updateStripeSessionStatus($payment_intent_id, 'captured');
            
            return [
                'success' => true,
                'payment_intent' => $payment_intent,
                'amount_captured' => $payment_intent->amount_received
            ];
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog('StripePaymentManager::capturePayment() - Erreur: ' . $e->getMessage(), 3);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Prélever la caution en cas de besoin
     */
    public function chargeDeposit($setup_intent_id, $amount, $reason = 'Frais de caution')
    {
        try {
            // Récupérer le Setup Intent et le payment method
            $setup_intent = \Stripe\SetupIntent::retrieve($setup_intent_id);
            $payment_method = $setup_intent->payment_method;
            
            // Créer un Payment Intent pour la caution
            $payment_intent = \Stripe\PaymentIntent::create([
                'amount' => $amount,
                'currency' => 'eur',
                'payment_method' => $payment_method,
                'confirmation_method' => 'manual',
                'confirm' => true,
                'off_session' => true,
                'description' => $reason,
                'metadata' => [
                    'type' => 'deposit_charge',
                    'setup_intent_id' => $setup_intent_id,
                    'original_booking_ref' => $setup_intent->metadata['booking_reference'] ?? ''
                ]
            ]);
            
            return [
                'success' => true,
                'payment_intent' => $payment_intent,
                'amount_charged' => $amount
            ];
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog('StripePaymentManager::chargeDeposit() - Erreur: ' . $e->getMessage(), 3);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Traitement des webhooks Stripe
     */
    public function handleWebhook($payload, $signature)
    {
        try {
            $event = \Stripe\Webhook::constructEvent($payload, $signature, $this->webhook_secret);
            
            switch ($event->type) {
                case 'payment_intent.succeeded':
                    $this->handlePaymentIntentSucceeded($event->data->object);
                    break;
                    
                case 'payment_intent.payment_failed':
                    $this->handlePaymentIntentFailed($event->data->object);
                    break;
                    
                case 'payment_intent.canceled':
                    $this->handlePaymentIntentCanceled($event->data->object);
                    break;
                    
                case 'setup_intent.succeeded':
                    $this->handleSetupIntentSucceeded($event->data->object);
                    break;
                    
                default:
                    PrestaShopLogger::addLog('StripePaymentManager - Événement webhook non géré: ' . $event->type, 1);
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog('StripePaymentManager::handleWebhook() - Erreur: ' . $e->getMessage(), 3);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Gérer le succès du paiement
     */
    private function handlePaymentIntentSucceeded($payment_intent)
    {
        $id_reservation = $payment_intent->metadata['id_reservation'] ?? null;
        
        if ($id_reservation) {
            // Mettre à jour le statut de la réservation
            $this->updateReservationPaymentStatus($payment_intent->id, 'succeeded');
            
            // Envoyer email de confirmation si configuré
            if (Configuration::get('BOOKING_SEND_CONFIRMATION_EMAIL')) {
                $this->sendPaymentConfirmationEmail($id_reservation, $payment_intent);
            }
            
            // Logger l'activité
            $this->logActivity($id_reservation, 'payment_succeeded', [
                'payment_intent_id' => $payment_intent->id,
                'amount' => $payment_intent->amount / 100
            ]);
        }
    }
    
    /**
     * Gérer l'échec du paiement
     */
    private function handlePaymentIntentFailed($payment_intent)
    {
        $id_reservation = $payment_intent->metadata['id_reservation'] ?? null;
        
        if ($id_reservation) {
            $this->updateReservationPaymentStatus($payment_intent->id, 'failed');
            
            $this->logActivity($id_reservation, 'payment_failed', [
                'payment_intent_id' => $payment_intent->id,
                'error' => $payment_intent->last_payment_error->message ?? 'Erreur inconnue'
            ]);
        }
    }
    
    /**
     * Gérer l'annulation du paiement
     */
    private function handlePaymentIntentCanceled($payment_intent)
    {
        $id_reservation = $payment_intent->metadata['id_reservation'] ?? null;
        
        if ($id_reservation) {
            $this->updateReservationPaymentStatus($payment_intent->id, 'canceled');
            
            $this->logActivity($id_reservation, 'payment_canceled', [
                'payment_intent_id' => $payment_intent->id
            ]);
        }
    }
    
    /**
     * Gérer le succès de l'empreinte CB
     */
    private function handleSetupIntentSucceeded($setup_intent)
    {
        $id_reservation = $setup_intent->metadata['id_reservation'] ?? null;
        
        if ($id_reservation && $setup_intent->metadata['type'] === 'deposit') {
            // Marquer l'empreinte CB comme réussie
            $sql = 'UPDATE `' . _DB_PREFIX_ . 'booker_auth_reserved` 
                    SET stripe_deposit_intent_id = "' . pSQL($setup_intent->id) . '"
                    WHERE id_reserved = ' . (int)$id_reservation;
            
            Db::getInstance()->execute($sql);
            
            $this->logActivity($id_reservation, 'deposit_setup_succeeded', [
                'setup_intent_id' => $setup_intent->id
            ]);
        }
    }
    
    /**
     * Sauvegarder une session Stripe
     */
    private function saveStripeSession($data)
    {
        $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'booking_stripe_sessions` 
                (id_reservation, session_id, payment_intent_id, status, date_add, date_upd)
                VALUES (
                    ' . (int)$data['id_reservation'] . ',
                    "' . pSQL($data['payment_intent_id']) . '",
                    "' . pSQL($data['payment_intent_id']) . '",
                    "' . pSQL($data['status']) . '",
                    NOW(),
                    NOW()
                )';
        
        return Db::getInstance()->execute($sql);
    }
    
    /**
     * Mettre à jour le statut d'une session Stripe
     */
    private function updateStripeSessionStatus($payment_intent_id, $status)
    {
        $sql = 'UPDATE `' . _DB_PREFIX_ . 'booking_stripe_sessions` 
                SET status = "' . pSQL($status) . '", date_upd = NOW()
                WHERE payment_intent_id = "' . pSQL($payment_intent_id) . '"';
        
        return Db::getInstance()->execute($sql);
    }
    
    /**
     * Mettre à jour le statut de paiement d'une réservation
     */
    private function updateReservationPaymentStatus($payment_intent_id, $payment_status)
    {
        // Récupérer l'ID de réservation via les métadonnées Stripe
        try {
            $payment_intent = \Stripe\PaymentIntent::retrieve($payment_intent_id);
            $id_reservation = $payment_intent->metadata['id_reservation'] ?? null;
            
            if ($id_reservation) {
                $new_status = 2; // Status "Payée"
                if ($payment_status === 'failed' || $payment_status === 'canceled') {
                    $new_status = 3; // Status "Annulée"
                }
                
                $sql = 'UPDATE `' . _DB_PREFIX_ . 'booker_auth_reserved` 
                        SET payment_status = "' . pSQL($payment_status) . '",
                            status = ' . (int)$new_status . ',
                            stripe_payment_intent_id = "' . pSQL($payment_intent_id) . '"
                        WHERE id_reserved = ' . (int)$id_reservation;
                
                return Db::getInstance()->execute($sql);
            }
        } catch (Exception $e) {
            PrestaShopLogger::addLog('Erreur mise à jour statut réservation: ' . $e->getMessage(), 3);
        }
        
        return false;
    }
    
    /**
     * Envoyer un email de confirmation de paiement
     */
    private function sendPaymentConfirmationEmail($id_reservation, $payment_intent)
    {
        try {
            $reservation = new BookerAuthReserved($id_reservation);
            
            if (Validate::isLoadedObject($reservation)) {
                $template_vars = [
                    '{booking_reference}' => $reservation->booking_reference,
                    '{customer_name}' => $reservation->customer_firstname . ' ' . $reservation->customer_lastname,
                    '{amount_paid}' => number_format($payment_intent->amount / 100, 2) . ' €',
                    '{payment_id}' => $payment_intent->id
                ];
                
                Mail::Send(
                    Context::getContext()->language->id,
                    'booking_payment_confirmation',
                    'Confirmation de paiement - Réservation ' . $reservation->booking_reference,
                    $template_vars,
                    $reservation->customer_email,
                    $reservation->customer_firstname . ' ' . $reservation->customer_lastname,
                    null,
                    null,
                    null,
                    null,
                    dirname(__FILE__) . '/../mails/',
                    false,
                    Context::getContext()->shop->id
                );
            }
        } catch (Exception $e) {
            PrestaShopLogger::addLog('Erreur envoi email confirmation paiement: ' . $e->getMessage(), 3);
        }
    }
    
    /**
     * Logger une activité
     */
    private function logActivity($id_reservation, $action, $details = [])
    {
        if (Db::getInstance()->getValue('SHOW TABLES LIKE "' . _DB_PREFIX_ . 'booking_activity_log"')) {
            $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'booking_activity_log` 
                    (id_reservation, action, details, date_add)
                    VALUES (
                        ' . (int)$id_reservation . ',
                        "' . pSQL($action) . '",
                        "' . pSQL(json_encode($details)) . '",
                        NOW()
                    )';
            
            Db::getInstance()->execute($sql);
        }
    }
    
    /**
     * Obtenir les informations publiques pour le front-end
     */
    public function getPublicConfig()
    {
        return [
            'publishable_key' => $this->stripe_publishable_key,
            'is_test_mode' => $this->is_test_mode,
            'currency' => 'eur',
            'enabled' => Configuration::get('BOOKING_STRIPE_ENABLED', 0)
        ];
    }
    
    /**
     * Vérifier si Stripe est correctement configuré
     */
    public function isConfigured()
    {
        return !empty($this->stripe_secret_key) && !empty($this->stripe_publishable_key);
    }
}