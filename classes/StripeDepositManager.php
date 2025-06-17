<?php
/**
 * StripeDepositManager - Gestionnaire intelligent des cautions Stripe
 * Version 2.1.5 - Avec empreinte CB, autorisation, capture et libération automatiques
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class StripeDepositManager
{
    private $stripe_api_key;
    private $test_mode;
    
    public function __construct()
    {
        $this->test_mode = (bool)Configuration::get('BOOKING_STRIPE_TEST_MODE', 1);
        $this->stripe_api_key = $this->test_mode ? 
                               Configuration::get('BOOKING_STRIPE_TEST_SECRET_KEY') : 
                               Configuration::get('BOOKING_STRIPE_LIVE_SECRET_KEY');
        
        if (!$this->stripe_api_key) {
            throw new Exception('Stripe API key not configured');
        }
        
        // Initialiser Stripe
        if (!class_exists('Stripe\Stripe')) {
            require_once _PS_MODULE_DIR_ . 'booking/vendor/stripe/stripe-php/init.php';
        }
        
        \Stripe\Stripe::setApiKey($this->stripe_api_key);
    }

    /**
     * Créer une empreinte de carte pour caution (Setup Intent)
     * 
     * @param array $reservation_data Données de la réservation
     * @param array $customer_data Données du client
     * @return array Résultat avec setup_intent_id et client_secret
     */
    public function createDepositSetup($reservation_data, $customer_data)
    {
        try {
            // Créer ou récupérer le client Stripe
            $stripe_customer = $this->getOrCreateStripeCustomer($customer_data);
            
            // Calculer le montant de la caution
            $deposit_config = $this->getDepositConfig($reservation_data['id_booker'] ?? null);
            $deposit_amount = $this->calculateDepositAmount($reservation_data['total_price'], $deposit_config);
            
            // Créer le Setup Intent pour l'empreinte
            $setup_intent = \Stripe\SetupIntent::create([
                'customer' => $stripe_customer->id,
                'payment_method_types' => ['card'],
                'usage' => 'off_session',
                'metadata' => [
                    'booking_reference' => $reservation_data['booking_reference'],
                    'id_reservation' => $reservation_data['id_reservation'],
                    'deposit_amount' => $deposit_amount,
                    'customer_email' => $customer_data['email'],
                    'module' => 'prestashop_booking'
                ]
            ]);
            
            // Enregistrer dans la base de données
            $deposit_id = $this->saveDepositRecord([
                'id_reservation' => $reservation_data['id_reservation'],
                'setup_intent_id' => $setup_intent->id,
                'deposit_amount' => $deposit_amount * 100, // En centimes
                'status' => 'pending',
                'metadata' => json_encode($setup_intent->metadata->toArray())
            ]);
            
            return [
                'success' => true,
                'setup_intent_id' => $setup_intent->id,
                'client_secret' => $setup_intent->client_secret,
                'deposit_id' => $deposit_id,
                'deposit_amount' => $deposit_amount,
                'stripe_customer_id' => $stripe_customer->id
            ];
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $this->logError('Setup Intent creation failed', $e->getMessage(), $reservation_data);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_code' => $e->getStripeCode()
            ];
        }
    }

    /**
     * Confirmer l'empreinte et créer la pré-autorisation
     * 
     * @param string $setup_intent_id ID du Setup Intent
     * @param string $payment_method_id ID de la méthode de paiement
     * @return array Résultat de la pré-autorisation
     */
    public function authorizeDeposit($setup_intent_id, $payment_method_id)
    {
        try {
            // Récupérer les informations de la caution
            $deposit = $this->getDepositBySetupIntent($setup_intent_id);
            if (!$deposit) {
                throw new Exception('Deposit not found for setup intent: ' . $setup_intent_id);
            }
            
            // Créer le Payment Intent pour la pré-autorisation
            $payment_intent = \Stripe\PaymentIntent::create([
                'amount' => $deposit['deposit_amount'], // Montant en centimes
                'currency' => strtolower(Context::getContext()->currency->iso_code),
                'payment_method' => $payment_method_id,
                'customer' => $this->getStripeCustomerFromDeposit($deposit),
                'capture_method' => 'manual', // Pré-autorisation uniquement
                'confirmation_method' => 'manual',
                'confirm' => true,
                'return_url' => Context::getContext()->link->getModuleLink('booking', 'confirmation'),
                'metadata' => json_decode($deposit['metadata'], true)
            ]);
            
            // Mettre à jour l'enregistrement de caution
            $this->updateDepositRecord($deposit['id_deposit'], [
                'payment_method_id' => $payment_method_id,
                'payment_intent_id' => $payment_intent->id,
                'status' => $payment_intent->status,
                'date_authorized' => $payment_intent->status === 'requires_capture' ? date('Y-m-d H:i:s') : null
            ]);
            
            // Enregistrer dans l'historique
            $this->addDepositHistory($deposit['id_deposit'], 'authorized', $deposit['status'], $payment_intent->status, [
                'stripe_id' => $payment_intent->id,
                'amount' => $deposit['deposit_amount'],
                'details' => 'Deposit pre-authorized successfully'
            ]);
            
            // Mettre à jour le statut de la réservation
            if ($payment_intent->status === 'requires_capture') {
                $this->updateReservationDepositStatus($deposit['id_reservation'], 'authorized');
            }
            
            return [
                'success' => true,
                'payment_intent_id' => $payment_intent->id,
                'status' => $payment_intent->status,
                'requires_action' => $payment_intent->status === 'requires_action',
                'client_secret' => $payment_intent->client_secret
            ];
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $this->logError('Deposit authorization failed', $e->getMessage(), ['setup_intent_id' => $setup_intent_id]);
            
            // Mettre à jour le statut en cas d'échec
            if (isset($deposit)) {
                $this->updateDepositRecord($deposit['id_deposit'], [
                    'status' => 'failed',
                    'failure_reason' => $e->getMessage()
                ]);
                
                $this->addDepositHistory($deposit['id_deposit'], 'failed', $deposit['status'], 'failed', [
                    'details' => 'Authorization failed: ' . $e->getMessage()
                ]);
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_code' => $e->getStripeCode()
            ];
        }
    }

    /**
     * Capturer une caution pré-autorisée
     * 
     * @param int $id_reservation ID de la réservation
     * @param float|null $amount Montant à capturer (null = montant total)
     * @param string $reason Raison de la capture
     * @return array Résultat de la capture
     */
    public function captureDeposit($id_reservation, $amount = null, $reason = 'Deposit capture for booking completion')
    {
        try {
            $deposit = $this->getDepositByReservation($id_reservation);
            if (!$deposit || $deposit['status'] !== 'authorized') {
                throw new Exception('No authorized deposit found for reservation: ' . $id_reservation);
            }
            
            return $this->captureDepositById($deposit['id_deposit'], $amount, $reason);
            
        } catch (Exception $e) {
            $this->logError('Deposit capture failed', $e->getMessage(), ['id_reservation' => $id_reservation]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Capturer une caution par son ID
     * 
     * @param int $id_deposit ID de la caution
     * @param float|null $amount Montant à capturer
     * @param string $reason Raison de la capture
     * @return array Résultat de la capture
     */
    public function captureDepositById($id_deposit, $amount = null, $reason = 'Deposit capture')
    {
        try {
            $deposit = $this->getDepositById($id_deposit);
            if (!$deposit || $deposit['status'] !== 'authorized') {
                throw new Exception('Deposit not found or not in authorized status');
            }
            
            // Déterminer le montant à capturer
            $capture_amount = $amount ? (int)($amount * 100) : $deposit['deposit_amount'];
            
            // Capturer le Payment Intent
            $payment_intent = \Stripe\PaymentIntent::retrieve($deposit['payment_intent_id']);
            $payment_intent->capture([
                'amount_to_capture' => $capture_amount
            ]);
            
            // Mettre à jour l'enregistrement
            $this->updateDepositRecord($id_deposit, [
                'status' => 'captured',
                'captured_amount' => $capture_amount,
                'date_captured' => date('Y-m-d H:i:s')
            ]);
            
            // Historique
            $this->addDepositHistory($id_deposit, 'captured', 'authorized', 'captured', [
                'stripe_id' => $payment_intent->id,
                'amount' => $capture_amount,
                'details' => $reason
            ]);
            
            // Mettre à jour la réservation
            $this->updateReservationDepositStatus($deposit['id_reservation'], 'captured', $capture_amount / 100);
            
            return [
                'success' => true,
                'captured_amount' => $capture_amount / 100,
                'charge_id' => $payment_intent->charges->data[0]->id ?? null
            ];
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $this->logError('Deposit capture failed', $e->getMessage(), ['id_deposit' => $id_deposit]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Libérer une caution pré-autorisée
     * 
     * @param int $id_reservation ID de la réservation
     * @param string $reason Raison de la libération
     * @return array Résultat de la libération
     */
    public function releaseDeposit($id_reservation, $reason = 'Deposit released - booking completed successfully')
    {
        try {
            $deposit = $this->getDepositByReservation($id_reservation);
            if (!$deposit || $deposit['status'] !== 'authorized') {
                throw new Exception('No authorized deposit found for reservation: ' . $id_reservation);
            }
            
            return $this->releaseDepositById($deposit['id_deposit'], $reason);
            
        } catch (Exception $e) {
            $this->logError('Deposit release failed', $e->getMessage(), ['id_reservation' => $id_reservation]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Libérer une caution par son ID
     * 
     * @param int $id_deposit ID de la caution
     * @param string $reason Raison de la libération
     * @return array Résultat de la libération
     */
    public function releaseDepositById($id_deposit, $reason = 'Deposit released')
    {
        try {
            $deposit = $this->getDepositById($id_deposit);
            if (!$deposit || $deposit['status'] !== 'authorized') {
                throw new Exception('Deposit not found or not in authorized status');
            }
            
            // Annuler le Payment Intent pour libérer la pré-autorisation
            $payment_intent = \Stripe\PaymentIntent::retrieve($deposit['payment_intent_id']);
            $payment_intent->cancel();
            
            // Mettre à jour l'enregistrement
            $this->updateDepositRecord($id_deposit, [
                'status' => 'released',
                'date_released' => date('Y-m-d H:i:s')
            ]);
            
            // Historique
            $this->addDepositHistory($id_deposit, 'released', 'authorized', 'released', [
                'stripe_id' => $payment_intent->id,
                'details' => $reason
            ]);
            
            // Mettre à jour la réservation
            $this->updateReservationDepositStatus($deposit['id_reservation'], 'released');
            
            return [
                'success' => true,
                'message' => 'Deposit released successfully'
            ];
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $this->logError('Deposit release failed', $e->getMessage(), ['id_deposit' => $id_deposit]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Rembourser une caution capturée
     * 
     * @param int $id_deposit ID de la caution
     * @param float|null $amount Montant à rembourser (null = montant total)
     * @param string $reason Raison du remboursement
     * @return array Résultat du remboursement
     */
    public function refundDeposit($id_deposit, $amount = null, $reason = 'Deposit refund')
    {
        try {
            $deposit = $this->getDepositById($id_deposit);
            if (!$deposit || $deposit['status'] !== 'captured') {
                throw new Exception('Deposit not found or not in captured status');
            }
            
            // Déterminer le montant à rembourser
            $refund_amount = $amount ? (int)($amount * 100) : $deposit['captured_amount'];
            
            // Créer le remboursement
            $payment_intent = \Stripe\PaymentIntent::retrieve($deposit['payment_intent_id']);
            $charge_id = $payment_intent->charges->data[0]->id;
            
            $refund = \Stripe\Refund::create([
                'charge' => $charge_id,
                'amount' => $refund_amount,
                'reason' => 'requested_by_customer',
                'metadata' => [
                    'reason' => $reason,
                    'id_deposit' => $id_deposit
                ]
            ]);
            
            // Mettre à jour l'enregistrement
            $new_refunded_amount = $deposit['refunded_amount'] + $refund_amount;
            $new_status = $new_refunded_amount >= $deposit['captured_amount'] ? 'refunded' : 'captured';
            
            $this->updateDepositRecord($id_deposit, [
                'status' => $new_status,
                'refunded_amount' => $new_refunded_amount,
                'date_refunded' => date('Y-m-d H:i:s')
            ]);
            
            // Historique
            $this->addDepositHistory($id_deposit, 'refunded', 'captured', $new_status, [
                'stripe_id' => $refund->id,
                'amount' => $refund_amount,
                'details' => $reason
            ]);
            
            return [
                'success' => true,
                'refund_id' => $refund->id,
                'refunded_amount' => $refund_amount / 100,
                'total_refunded' => $new_refunded_amount / 100
            ];
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $this->logError('Deposit refund failed', $e->getMessage(), ['id_deposit' => $id_deposit]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Récupérer ou créer un client Stripe
     */
    private function getOrCreateStripeCustomer($customer_data)
    {
        // Vérifier si le client existe déjà
        $existing_customer = $this->getBookingCustomerByEmail($customer_data['email']);
        
        if ($existing_customer && !empty($existing_customer['stripe_customer_id'])) {
            try {
                return \Stripe\Customer::retrieve($existing_customer['stripe_customer_id']);
            } catch (\Stripe\Exception\InvalidRequestException $e) {
                // Le client Stripe n'existe plus, on en crée un nouveau
            }
        }
        
        // Créer un nouveau client Stripe
        $stripe_customer = \Stripe\Customer::create([
            'email' => $customer_data['email'],
            'name' => trim(($customer_data['firstname'] ?? '') . ' ' . ($customer_data['lastname'] ?? '')),
            'phone' => $customer_data['phone'] ?? null,
            'metadata' => [
                'prestashop_customer_id' => $customer_data['id_customer'] ?? null,
                'module' => 'prestashop_booking'
            ]
        ]);
        
        // Sauvegarder la liaison
        $this->saveBookingCustomer([
            'id_customer' => $customer_data['id_customer'] ?? 0,
            'stripe_customer_id' => $stripe_customer->id
        ]);
        
        return $stripe_customer;
    }

    /**
     * Calculer le montant de la caution
     */
    private function calculateDepositAmount($total_price, $deposit_config)
    {
        // Montant fixe si défini
        if (!empty($deposit_config['deposit_amount']) && $deposit_config['deposit_amount'] > 0) {
            return (float)$deposit_config['deposit_amount'];
        }
        
        // Sinon, calculer en pourcentage
        $rate = $deposit_config['deposit_rate'] ?? 30;
        $amount = ($total_price * $rate) / 100;
        
        // Appliquer les limites min/max
        $min_amount = $deposit_config['min_deposit_amount'] ?? 50;
        $max_amount = $deposit_config['max_deposit_amount'] ?? 2000;
        
        return max($min_amount, min($max_amount, $amount));
    }

    /**
     * Récupérer la configuration de caution pour un booker
     */
    private function getDepositConfig($id_booker = null)
    {
        if ($id_booker) {
            // Configuration spécifique au booker
            $config = Db::getInstance()->getRow('
                SELECT * FROM ' . _DB_PREFIX_ . 'booking_deposit_config 
                WHERE id_booker = ' . (int)$id_booker
            );
            
            if ($config) {
                return $config;
            }
        }
        
        // Configuration globale
        return Db::getInstance()->getRow('
            SELECT * FROM ' . _DB_PREFIX_ . 'booking_deposit_config 
            WHERE id_booker IS NULL
        ') ?: [
            'deposit_rate' => Configuration::get('BOOKING_DEPOSIT_RATE', 30),
            'min_deposit_amount' => Configuration::get('BOOKING_DEPOSIT_MIN_AMOUNT', 50),
            'max_deposit_amount' => Configuration::get('BOOKING_DEPOSIT_MAX_AMOUNT', 2000)
        ];
    }

    /**
     * Sauvegarder un enregistrement de caution
     */
    private function saveDepositRecord($data)
    {
        $data['date_add'] = date('Y-m-d H:i:s');
        $data['date_upd'] = date('Y-m-d H:i:s');
        
        if (Db::getInstance()->insert('booking_deposits', $data)) {
            return Db::getInstance()->Insert_ID();
        }
        
        throw new Exception('Failed to save deposit record');
    }

    /**
     * Mettre à jour un enregistrement de caution
     */
    private function updateDepositRecord($id_deposit, $data)
    {
        $data['date_upd'] = date('Y-m-d H:i:s');
        
        return Db::getInstance()->update(
            'booking_deposits',
            $data,
            'id_deposit = ' . (int)$id_deposit
        );
    }

    /**
     * Ajouter une entrée dans l'historique des cautions
     */
    private function addDepositHistory($id_deposit, $action_type, $old_status, $new_status, $details = [])
    {
        $deposit = $this->getDepositById($id_deposit);
        if (!$deposit) {
            return false;
        }
        
        $data = [
            'id_deposit' => $id_deposit,
            'id_reservation' => $deposit['id_reservation'],
            'action_type' => pSQL($action_type),
            'old_status' => pSQL($old_status),
            'new_status' => pSQL($new_status),
            'amount' => $details['amount'] ?? null,
            'stripe_id' => $details['stripe_id'] ?? null,
            'details' => pSQL($details['details'] ?? ''),
            'id_employee' => null, // Sera rempli par le contrôleur si applicable
            'date_add' => date('Y-m-d H:i:s')
        ];
        
        return Db::getInstance()->insert('booking_deposit_history', $data);
    }

    /**
     * Mettre à jour le statut de caution de la réservation
     */
    private function updateReservationDepositStatus($id_reservation, $status, $amount = null)
    {
        $data = [
            'deposit_status' => pSQL($status),
            'date_upd' => date('Y-m-d H:i:s')
        ];
        
        if ($amount !== null) {
            $data['deposit_paid'] = (float)$amount;
        }
        
        return Db::getInstance()->update(
            'booker_auth_reserved',
            $data,
            'id_reserved = ' . (int)$id_reservation
        );
    }

    /**
     * Récupérer une caution par ID de réservation
     */
    private function getDepositByReservation($id_reservation)
    {
        return Db::getInstance()->getRow('
            SELECT * FROM ' . _DB_PREFIX_ . 'booking_deposits 
            WHERE id_reservation = ' . (int)$id_reservation
        );
    }

    /**
     * Récupérer une caution par ID
     */
    private function getDepositById($id_deposit)
    {
        return Db::getInstance()->getRow('
            SELECT * FROM ' . _DB_PREFIX_ . 'booking_deposits 
            WHERE id_deposit = ' . (int)$id_deposit
        );
    }

    /**
     * Récupérer une caution par Setup Intent
     */
    private function getDepositBySetupIntent($setup_intent_id)
    {
        return Db::getInstance()->getRow('
            SELECT * FROM ' . _DB_PREFIX_ . 'booking_deposits 
            WHERE setup_intent_id = "' . pSQL($setup_intent_id) . '"'
        );
    }

    /**
     * Récupérer un client booking par email
     */
    private function getBookingCustomerByEmail($email)
    {
        return Db::getInstance()->getRow('
            SELECT bc.*, c.email 
            FROM ' . _DB_PREFIX_ . 'booking_customers bc
            LEFT JOIN ' . _DB_PREFIX_ . 'customer c ON bc.id_customer = c.id_customer
            WHERE c.email = "' . pSQL($email) . '"
            OR bc.id_customer = 0
        ');
    }

    /**
     * Sauvegarder un client booking
     */
    private function saveBookingCustomer($data)
    {
        $data['date_add'] = date('Y-m-d H:i:s');
        $data['date_upd'] = date('Y-m-d H:i:s');
        
        return Db::getInstance()->insert('booking_customers', $data);
    }

    /**
     * Récupérer l'ID client Stripe depuis une caution
     */
    private function getStripeCustomerFromDeposit($deposit)
    {
        // Récupérer depuis les métadonnées du Setup Intent
        try {
            $setup_intent = \Stripe\SetupIntent::retrieve($deposit['setup_intent_id']);
            return $setup_intent->customer;
        } catch (\Stripe\Exception\ApiErrorException $e) {
            throw new Exception('Cannot retrieve Stripe customer: ' . $e->getMessage());
        }
    }

    /**
     * Logger les erreurs
     */
    private function logError($action, $message, $context = [])
    {
        $log_data = [
            'action' => $action,
            'message' => $message,
            'context' => $context,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        PrestaShopLogger::addLog(
            'StripeDepositManager Error: ' . $action . ' - ' . $message . ' - Context: ' . json_encode($context),
            3,
            null,
            'StripeDepositManager',
            null,
            true
        );
    }

    /**
     * Webhook handler pour les événements Stripe
     */
    public function handleWebhook($payload, $signature)
    {
        $webhook_secret = Configuration::get('BOOKING_STRIPE_WEBHOOK_SECRET');
        if (!$webhook_secret) {
            throw new Exception('Webhook secret not configured');
        }
        
        try {
            $event = \Stripe\Webhook::constructEvent($payload, $signature, $webhook_secret);
        } catch (\UnexpectedValueException $e) {
            throw new Exception('Invalid payload');
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            throw new Exception('Invalid signature');
        }
        
        // Vérifier si l'événement a déjà été traité
        if ($this->isWebhookProcessed($event->id)) {
            return ['success' => true, 'message' => 'Event already processed'];
        }
        
        // Enregistrer le webhook
        $this->saveWebhookEvent($event);
        
        // Traiter l'événement
        switch ($event->type) {
            case 'setup_intent.succeeded':
                return $this->handleSetupIntentSucceeded($event->data->object);
                
            case 'setup_intent.setup_failed':
                return $this->handleSetupIntentFailed($event->data->object);
                
            case 'payment_intent.succeeded':
                return $this->handlePaymentIntentSucceeded($event->data->object);
                
            case 'payment_intent.payment_failed':
                return $this->handlePaymentIntentFailed($event->data->object);
                
            case 'charge.captured':
                return $this->handleChargeCaptured($event->data->object);
                
            case 'charge.refunded':
                return $this->handleChargeRefunded($event->data->object);
                
            default:
                return ['success' => true, 'message' => 'Event type not handled'];
        }
    }

    /**
     * Vérifier si un webhook a déjà été traité
     */
    private function isWebhookProcessed($event_id)
    {
        return Db::getInstance()->getValue('
            SELECT id_webhook FROM ' . _DB_PREFIX_ . 'booking_webhooks 
            WHERE stripe_event_id = "' . pSQL($event_id) . '"
        ');
    }

    /**
     * Sauvegarder un événement webhook
     */
    private function saveWebhookEvent($event)
    {
        $data = [
            'stripe_event_id' => pSQL($event->id),
            'event_type' => pSQL($event->type),
            'processed' => 0,
            'payload' => pSQL(json_encode($event)),
            'date_received' => date('Y-m-d H:i:s')
        ];
        
        return Db::getInstance()->insert('booking_webhooks', $data);
    }

    /**
     * Marquer un webhook comme traité
     */
    private function markWebhookProcessed($event_id, $result = null)
    {
        return Db::getInstance()->update(
            'booking_webhooks',
            [
                'processed' => 1,
                'processing_result' => $result ? pSQL(json_encode($result)) : null,
                'date_processed' => date('Y-m-d H:i:s')
            ],
            'stripe_event_id = "' . pSQL($event_id) . '"'
        );
    }

    /**
     * Gérer l'événement setup_intent.succeeded
     */
    private function handleSetupIntentSucceeded($setup_intent)
    {
        $deposit = $this->getDepositBySetupIntent($setup_intent->id);
        if (!$deposit) {
            return ['success' => false, 'error' => 'Deposit not found'];
        }
        
        // Le Setup Intent est prêt, on peut maintenant créer la pré-autorisation
        $result = $this->authorizeDeposit($setup_intent->id, $setup_intent->payment_method);
        
        $this->markWebhookProcessed($setup_intent->id, $result);
        return $result;
    }

    /**
     * Gérer l'événement setup_intent.setup_failed
     */
    private function handleSetupIntentFailed($setup_intent)
    {
        $deposit = $this->getDepositBySetupIntent($setup_intent->id);
        if (!$deposit) {
            return ['success' => false, 'error' => 'Deposit not found'];
        }
        
        // Marquer la caution comme échouée
        $this->updateDepositRecord($deposit['id_deposit'], [
            'status' => 'failed',
            'failure_reason' => $setup_intent->last_setup_error->message ?? 'Setup failed'
        ]);
        
        $this->addDepositHistory($deposit['id_deposit'], 'failed', $deposit['status'], 'failed', [
            'details' => 'Setup Intent failed: ' . ($setup_intent->last_setup_error->message ?? 'Unknown error')
        ]);
        
        $result = ['success' => true, 'message' => 'Setup failure processed'];
        $this->markWebhookProcessed($setup_intent->id, $result);
        return $result;
    }

    /**
     * Gérer l'événement payment_intent.succeeded
     */
    private function handlePaymentIntentSucceeded($payment_intent)
    {
        // Rechercher la caution par payment_intent_id
        $deposit = Db::getInstance()->getRow('
            SELECT * FROM ' . _DB_PREFIX_ . 'booking_deposits 
            WHERE payment_intent_id = "' . pSQL($payment_intent->id) . '"'
        );
        
        if (!$deposit) {
            return ['success' => false, 'error' => 'Deposit not found'];
        }
        
        // Mettre à jour le statut selon le Payment Intent
        $new_status = $payment_intent->status === 'requires_capture' ? 'authorized' : 'captured';
        
        $this->updateDepositRecord($deposit['id_deposit'], [
            'status' => $new_status,
            'date_authorized' => $new_status === 'authorized' ? date('Y-m-d H:i:s') : $deposit['date_authorized'],
            'date_captured' => $new_status === 'captured' ? date('Y-m-d H:i:s') : $deposit['date_captured']
        ]);
        
        $this->addDepositHistory($deposit['id_deposit'], $new_status, $deposit['status'], $new_status, [
            'stripe_id' => $payment_intent->id,
            'details' => 'Payment Intent succeeded via webhook'
        ]);
        
        // Mettre à jour la réservation
        $this->updateReservationDepositStatus($deposit['id_reservation'], $new_status);
        
        $result = ['success' => true, 'message' => 'Payment Intent success processed'];
        $this->markWebhookProcessed($payment_intent->id, $result);
        return $result;
    }

    /**
     * Gérer l'événement payment_intent.payment_failed
     */
    private function handlePaymentIntentFailed($payment_intent)
    {
        $deposit = Db::getInstance()->getRow('
            SELECT * FROM ' . _DB_PREFIX_ . 'booking_deposits 
            WHERE payment_intent_id = "' . pSQL($payment_intent->id) . '"'
        );
        
        if (!$deposit) {
            return ['success' => false, 'error' => 'Deposit not found'];
        }
        
        $this->updateDepositRecord($deposit['id_deposit'], [
            'status' => 'failed',
            'failure_reason' => $payment_intent->last_payment_error->message ?? 'Payment failed'
        ]);
        
        $this->addDepositHistory($deposit['id_deposit'], 'failed', $deposit['status'], 'failed', [
            'stripe_id' => $payment_intent->id,
            'details' => 'Payment Intent failed: ' . ($payment_intent->last_payment_error->message ?? 'Unknown error')
        ]);
        
        $this->updateReservationDepositStatus($deposit['id_reservation'], 'failed');
        
        $result = ['success' => true, 'message' => 'Payment Intent failure processed'];
        $this->markWebhookProcessed($payment_intent->id, $result);
        return $result;
    }

    /**
     * Gérer l'événement charge.captured
     */
    private function handleChargeCaptured($charge)
    {
        // Rechercher par payment_intent_id
        $deposit = Db::getInstance()->getRow('
            SELECT * FROM ' . _DB_PREFIX_ . 'booking_deposits 
            WHERE payment_intent_id = "' . pSQL($charge->payment_intent) . '"'
        );
        
        if (!$deposit) {
            return ['success' => false, 'error' => 'Deposit not found'];
        }
        
        $this->updateDepositRecord($deposit['id_deposit'], [
            'status' => 'captured',
            'captured_amount' => $charge->amount,
            'date_captured' => date('Y-m-d H:i:s')
        ]);
        
        $this->addDepositHistory($deposit['id_deposit'], 'captured', $deposit['status'], 'captured', [
            'stripe_id' => $charge->id,
            'amount' => $charge->amount,
            'details' => 'Charge captured via webhook'
        ]);
        
        $this->updateReservationDepositStatus($deposit['id_reservation'], 'captured', $charge->amount / 100);
        
        $result = ['success' => true, 'message' => 'Charge capture processed'];
        $this->markWebhookProcessed($charge->id, $result);
        return $result;
    }

    /**
     * Gérer l'événement charge.refunded
     */
    private function handleChargeRefunded($charge)
    {
        $deposit = Db::getInstance()->getRow('
            SELECT * FROM ' . _DB_PREFIX_ . 'booking_deposits 
            WHERE payment_intent_id = "' . pSQL($charge->payment_intent) . '"'
        );
        
        if (!$deposit) {
            return ['success' => false, 'error' => 'Deposit not found'];
        }
        
        $total_refunded = 0;
        foreach ($charge->refunds->data as $refund) {
            $total_refunded += $refund->amount;
        }
        
        $new_status = $total_refunded >= $charge->amount ? 'refunded' : 'captured';
        
        $this->updateDepositRecord($deposit['id_deposit'], [
            'status' => $new_status,
            'refunded_amount' => $total_refunded,
            'date_refunded' => date('Y-m-d H:i:s')
        ]);
        
        $this->addDepositHistory($deposit['id_deposit'], 'refunded', $deposit['status'], $new_status, [
            'stripe_id' => $charge->id,
            'amount' => $total_refunded,
            'details' => 'Charge refunded via webhook'
        ]);
        
        $result = ['success' => true, 'message' => 'Charge refund processed'];
        $this->markWebhookProcessed($charge->id, $result);
        return $result;
    }
}
