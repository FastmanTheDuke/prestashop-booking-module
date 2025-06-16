<?php
/**
 * Classe StripeDepositManager - Gestion avancée des cautions avec empreinte CB
 * 
 * Cette classe gère :
 * - Création d'empreintes de carte bancaire pour les cautions
 * - Pré-autorisation des montants de caution
 * - Capture/libération des cautions selon le statut de la réservation
 * - Gestion des remboursements automatiques
 * - Intégration avec le module Stripe Payments PrestaShop
 */

require_once(_PS_MODULE_DIR_ . 'booking/classes/StripeBookingPayment.php');

class StripeDepositManager
{
    private $stripe_secret_key;
    private $stripe_public_key;
    private $webhook_secret;
    private $module;
    
    // Montants de caution par défaut
    const DEFAULT_DEPOSIT_RATE = 30; // 30% du montant total
    const MIN_DEPOSIT_AMOUNT = 5000; // 50€ en centimes
    const MAX_DEPOSIT_AMOUNT = 200000; // 2000€ en centimes
    
    // Statuts des cautions
    const DEPOSIT_STATUS_PENDING = 'pending';
    const DEPOSIT_STATUS_AUTHORIZED = 'authorized';
    const DEPOSIT_STATUS_CAPTURED = 'captured';
    const DEPOSIT_STATUS_RELEASED = 'released';
    const DEPOSIT_STATUS_REFUNDED = 'refunded';
    const DEPOSIT_STATUS_FAILED = 'failed';
    
    public function __construct($module = null)
    {
        $this->module = $module ?: Module::getInstanceByName('booking');
        $this->initializeStripeKeys();
        
        // Initialiser Stripe
        if (class_exists('\Stripe\Stripe')) {
            \Stripe\Stripe::setApiKey($this->stripe_secret_key);
        }
    }
    
    /**
     * Initialiser les clés Stripe depuis la configuration
     */
    private function initializeStripeKeys()
    {
        $this->stripe_secret_key = Configuration::get('BOOKING_STRIPE_SECRET_KEY');
        $this->stripe_public_key = Configuration::get('BOOKING_STRIPE_PUBLIC_KEY');
        $this->webhook_secret = Configuration::get('BOOKING_STRIPE_WEBHOOK_SECRET');
        
        // Vérifier si on est en mode test
        $test_mode = Configuration::get('BOOKING_STRIPE_TEST_MODE', 1);
        if ($test_mode) {
            $this->stripe_secret_key = Configuration::get('BOOKING_STRIPE_TEST_SECRET_KEY');
            $this->stripe_public_key = Configuration::get('BOOKING_STRIPE_TEST_PUBLIC_KEY');
        }
    }
    
    /**
     * Créer une empreinte de carte bancaire pour la caution
     */
    public function createCardFingerprint($reservation_id, $card_details = null)
    {
        try {
            $reservation = new BookerAuthReserved($reservation_id);
            if (!Validate::isLoadedObject($reservation)) {
                throw new Exception('Réservation non trouvée');
            }
            
            // Calculer le montant de la caution
            $deposit_amount = $this->calculateDepositAmount($reservation->total_price);
            
            // Créer un PaymentMethod pour stocker les détails de la carte
            $payment_method_data = [
                'type' => 'card',
                'card' => $card_details ?: [
                    'number' => '4242424242424242', // Carte de test
                    'exp_month' => 12,
                    'exp_year' => 2030,
                    'cvc' => '123',
                ],
            ];
            
            if (!$card_details) {
                // En production, ces données viendront du frontend
                $payment_method_data['billing_details'] = [
                    'name' => $reservation->customer_firstname . ' ' . $reservation->customer_lastname,
                    'email' => $reservation->customer_email,
                    'phone' => $reservation->customer_phone,
                ];
            }
            
            $payment_method = \Stripe\PaymentMethod::create($payment_method_data);
            
            // Créer un SetupIntent pour sauvegarder la carte
            $setup_intent = \Stripe\SetupIntent::create([
                'customer' => $this->getOrCreateStripeCustomer($reservation),
                'payment_method' => $payment_method->id,
                'confirm' => true,
                'usage' => 'off_session',
                'metadata' => [
                    'reservation_id' => $reservation_id,
                    'type' => 'deposit_authorization',
                    'deposit_amount' => $deposit_amount,
                ]
            ]);
            
            // Sauvegarder les informations dans la base
            $this->saveDepositInfo($reservation_id, [
                'setup_intent_id' => $setup_intent->id,
                'payment_method_id' => $payment_method->id,
                'deposit_amount' => $deposit_amount,
                'status' => self::DEPOSIT_STATUS_PENDING
            ]);
            
            return [
                'success' => true,
                'setup_intent_id' => $setup_intent->id,
                'client_secret' => $setup_intent->client_secret,
                'deposit_amount' => $deposit_amount,
                'deposit_amount_formatted' => Tools::displayPrice($deposit_amount / 100)
            ];
            
        } catch (Exception $e) {
            $this->logError('Erreur création empreinte CB', $e->getMessage(), $reservation_id);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Pré-autoriser le montant de la caution
     */
    public function authorizeDeposit($reservation_id)
    {
        try {
            $reservation = new BookerAuthReserved($reservation_id);
            if (!Validate::isLoadedObject($reservation)) {
                throw new Exception('Réservation non trouvée');
            }
            
            // Récupérer les infos de caution
            $deposit_info = $this->getDepositInfo($reservation_id);
            if (!$deposit_info) {
                throw new Exception('Informations de caution non trouvées');
            }
            
            // Créer un PaymentIntent pour la pré-autorisation
            $payment_intent = \Stripe\PaymentIntent::create([
                'amount' => $deposit_info['deposit_amount'],
                'currency' => strtolower(Currency::getIsoCodeById(Configuration::get('PS_CURRENCY_DEFAULT'))),
                'payment_method' => $deposit_info['payment_method_id'],
                'customer' => $this->getOrCreateStripeCustomer($reservation),
                'capture_method' => 'manual', // Pré-autorisation seulement
                'confirm' => true,
                'off_session' => true, // Paiement sans interaction client
                'metadata' => [
                    'reservation_id' => $reservation_id,
                    'type' => 'deposit_preauth',
                    'booking_reference' => $reservation->booking_reference,
                ]
            ]);
            
            // Mettre à jour la réservation
            $reservation->stripe_deposit_intent_id = $payment_intent->id;
            $reservation->deposit_paid = $deposit_info['deposit_amount'] / 100;
            $reservation->save();
            
            // Mettre à jour le statut de la caution
            $this->updateDepositStatus($reservation_id, self::DEPOSIT_STATUS_AUTHORIZED, $payment_intent->id);
            
            // Log de succès
            $this->logInfo('Caution pré-autorisée avec succès', $reservation_id, $payment_intent->id);
            
            return [
                'success' => true,
                'payment_intent_id' => $payment_intent->id,
                'amount_authorized' => $deposit_info['deposit_amount']
            ];
            
        } catch (Exception $e) {
            $this->logError('Erreur pré-autorisation caution', $e->getMessage(), $reservation_id);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Capturer la caution (débiter la carte)
     */
    public function captureDeposit($reservation_id, $amount = null)
    {
        try {
            $reservation = new BookerAuthReserved($reservation_id);
            if (!Validate::isLoadedObject($reservation) || !$reservation->stripe_deposit_intent_id) {
                throw new Exception('PaymentIntent de caution non trouvé');
            }
            
            // Récupérer le PaymentIntent
            $payment_intent = \Stripe\PaymentIntent::retrieve($reservation->stripe_deposit_intent_id);
            
            if ($payment_intent->status !== 'requires_capture') {
                throw new Exception('PaymentIntent non prêt pour capture: ' . $payment_intent->status);
            }
            
            // Capturer le montant (total ou partiel)
            $capture_data = [];
            if ($amount !== null) {
                $capture_data['amount_to_capture'] = (int)($amount * 100);
            }
            
            $payment_intent = $payment_intent->capture($capture_data);
            
            // Mettre à jour le statut
            $this->updateDepositStatus($reservation_id, self::DEPOSIT_STATUS_CAPTURED, $payment_intent->id);
            
            $this->logInfo('Caution capturée avec succès', $reservation_id, $payment_intent->id);
            
            return [
                'success' => true,
                'captured_amount' => $payment_intent->amount_received / 100
            ];
            
        } catch (Exception $e) {
            $this->logError('Erreur capture caution', $e->getMessage(), $reservation_id);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Libérer la pré-autorisation (annuler sans débiter)
     */
    public function releaseDeposit($reservation_id)
    {
        try {
            $reservation = new BookerAuthReserved($reservation_id);
            if (!Validate::isLoadedObject($reservation) || !$reservation->stripe_deposit_intent_id) {
                throw new Exception('PaymentIntent de caution non trouvé');
            }
            
            // Annuler le PaymentIntent
            $payment_intent = \Stripe\PaymentIntent::retrieve($reservation->stripe_deposit_intent_id);
            $payment_intent = $payment_intent->cancel();
            
            // Mettre à jour le statut
            $this->updateDepositStatus($reservation_id, self::DEPOSIT_STATUS_RELEASED, $payment_intent->id);
            
            $this->logInfo('Caution libérée avec succès', $reservation_id, $payment_intent->id);
            
            return [
                'success' => true,
                'status' => 'released'
            ];
            
        } catch (Exception $e) {
            $this->logError('Erreur libération caution', $e->getMessage(), $reservation_id);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Rembourser la caution (après capture)
     */
    public function refundDeposit($reservation_id, $amount = null, $reason = null)
    {
        try {
            $reservation = new BookerAuthReserved($reservation_id);
            if (!Validate::isLoadedObject($reservation) || !$reservation->stripe_deposit_intent_id) {
                throw new Exception('PaymentIntent de caution non trouvé');
            }
            
            // Créer le remboursement
            $refund_data = [
                'payment_intent' => $reservation->stripe_deposit_intent_id,
                'metadata' => [
                    'reservation_id' => $reservation_id,
                    'booking_reference' => $reservation->booking_reference,
                    'type' => 'deposit_refund'
                ]
            ];
            
            if ($amount !== null) {
                $refund_data['amount'] = (int)($amount * 100);
            }
            
            if ($reason) {
                $refund_data['reason'] = $reason;
            }
            
            $refund = \Stripe\Refund::create($refund_data);
            
            // Mettre à jour le statut
            $this->updateDepositStatus($reservation_id, self::DEPOSIT_STATUS_REFUNDED, $refund->id);
            
            $this->logInfo('Caution remboursée avec succès', $reservation_id, $refund->id);
            
            return [
                'success' => true,
                'refund_id' => $refund->id,
                'refunded_amount' => $refund->amount / 100
            ];
            
        } catch (Exception $e) {
            $this->logError('Erreur remboursement caution', $e->getMessage(), $reservation_id);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Traitement automatique des cautions selon le statut de réservation
     */
    public function processDepositAutomatically($reservation_id, $old_status, $new_status)
    {
        $reservation = new BookerAuthReserved($reservation_id);
        
        switch ($new_status) {
            case BookerAuthReserved::STATUS_ACCEPTED:
                // Créer la pré-autorisation quand acceptée
                return $this->authorizeDeposit($reservation_id);
                
            case BookerAuthReserved::STATUS_PAID:
                // Garder la pré-autorisation active
                break;
                
            case BookerAuthReserved::STATUS_COMPLETED:
                // Libérer la caution si tout s'est bien passé
                return $this->releaseDeposit($reservation_id);
                
            case BookerAuthReserved::STATUS_CANCELLED:
            case BookerAuthReserved::STATUS_EXPIRED:
                // Libérer ou rembourser selon le cas
                if ($old_status == BookerAuthReserved::STATUS_PAID) {
                    return $this->refundDeposit($reservation_id, null, 'cancelled');
                } else {
                    return $this->releaseDeposit($reservation_id);
                }
                break;
        }
        
        return ['success' => true, 'action' => 'none'];
    }
    
    /**
     * Calculer le montant de la caution
     */
    private function calculateDepositAmount($total_price)
    {
        $deposit_rate = Configuration::get('BOOKING_DEPOSIT_RATE', self::DEFAULT_DEPOSIT_RATE);
        $deposit_amount = ($total_price * $deposit_rate / 100) * 100; // Convertir en centimes
        
        // Appliquer les limites min/max
        $deposit_amount = max($deposit_amount, self::MIN_DEPOSIT_AMOUNT);
        $deposit_amount = min($deposit_amount, self::MAX_DEPOSIT_AMOUNT);
        
        return (int)$deposit_amount;
    }
    
    /**
     * Obtenir ou créer un client Stripe
     */
    private function getOrCreateStripeCustomer($reservation)
    {
        $customer_stripe_id = null;
        
        // Chercher si le client existe déjà
        if ($reservation->id_customer) {
            $customer_stripe_id = Db::getInstance()->getValue('
                SELECT stripe_customer_id FROM `' . _DB_PREFIX_ . 'booking_customers`
                WHERE id_customer = ' . (int)$reservation->id_customer
            );
        }
        
        if (!$customer_stripe_id) {
            // Créer un nouveau client Stripe
            try {
                $customer = \Stripe\Customer::create([
                    'name' => $reservation->customer_firstname . ' ' . $reservation->customer_lastname,
                    'email' => $reservation->customer_email,
                    'phone' => $reservation->customer_phone,
                    'metadata' => [
                        'prestashop_customer_id' => $reservation->id_customer ?: 0,
                        'created_from' => 'booking_module'
                    ]
                ]);
                
                $customer_stripe_id = $customer->id;
                
                // Sauvegarder l'ID Stripe
                if ($reservation->id_customer) {
                    Db::getInstance()->execute('
                        INSERT INTO `' . _DB_PREFIX_ . 'booking_customers` 
                        (id_customer, stripe_customer_id, date_add)
                        VALUES (' . (int)$reservation->id_customer . ', "' . pSQL($customer_stripe_id) . '", NOW())
                        ON DUPLICATE KEY UPDATE stripe_customer_id = "' . pSQL($customer_stripe_id) . '"'
                    );
                }
                
            } catch (Exception $e) {
                $this->logError('Erreur création client Stripe', $e->getMessage());
                throw $e;
            }
        }
        
        return $customer_stripe_id;
    }
    
    /**
     * Sauvegarder les informations de caution
     */
    private function saveDepositInfo($reservation_id, $data)
    {
        return Db::getInstance()->execute('
            INSERT INTO `' . _DB_PREFIX_ . 'booking_deposits`
            (id_reservation, setup_intent_id, payment_method_id, deposit_amount, status, date_add)
            VALUES (
                ' . (int)$reservation_id . ',
                "' . pSQL($data['setup_intent_id']) . '",
                "' . pSQL($data['payment_method_id']) . '",
                ' . (int)$data['deposit_amount'] . ',
                "' . pSQL($data['status']) . '",
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                setup_intent_id = "' . pSQL($data['setup_intent_id']) . '",
                payment_method_id = "' . pSQL($data['payment_method_id']) . '",
                deposit_amount = ' . (int)$data['deposit_amount'] . ',
                status = "' . pSQL($data['status']) . '",
                date_upd = NOW()'
        );
    }
    
    /**
     * Récupérer les informations de caution
     */
    private function getDepositInfo($reservation_id)
    {
        return Db::getInstance()->getRow('
            SELECT * FROM `' . _DB_PREFIX_ . 'booking_deposits`
            WHERE id_reservation = ' . (int)$reservation_id
        );
    }
    
    /**
     * Mettre à jour le statut de la caution
     */
    private function updateDepositStatus($reservation_id, $status, $stripe_id = null)
    {
        $sql = 'UPDATE `' . _DB_PREFIX_ . 'booking_deposits`
                SET status = "' . pSQL($status) . '", date_upd = NOW()';
        
        if ($stripe_id) {
            $sql .= ', stripe_transaction_id = "' . pSQL($stripe_id) . '"';
        }
        
        $sql .= ' WHERE id_reservation = ' . (int)$reservation_id;
        
        return Db::getInstance()->execute($sql);
    }
    
    /**
     * Logger les erreurs
     */
    private function logError($message, $details = '', $reservation_id = null)
    {
        PrestaShopLogger::addLog(
            'StripeDepositManager: ' . $message . ' - ' . $details,
            3, // Niveau ERROR
            null,
            'BookerAuthReserved',
            $reservation_id
        );
    }
    
    /**
     * Logger les informations
     */
    private function logInfo($message, $reservation_id = null, $stripe_id = null)
    {
        PrestaShopLogger::addLog(
            'StripeDepositManager: ' . $message . 
            ($reservation_id ? ' (Reservation: ' . $reservation_id . ')' : '') .
            ($stripe_id ? ' (Stripe: ' . $stripe_id . ')' : ''),
            1, // Niveau INFO
            null,
            'BookerAuthReserved',
            $reservation_id
        );
    }
    
    /**
     * Gérer les webhooks Stripe pour les cautions
     */
    public function handleWebhook($payload, $sig_header)
    {
        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $this->webhook_secret);
            
            switch ($event->type) {
                case 'setup_intent.succeeded':
                    $this->handleSetupIntentSucceeded($event->data->object);
                    break;
                    
                case 'payment_intent.requires_capture':
                    $this->handlePaymentIntentRequiresCapture($event->data->object);
                    break;
                    
                case 'payment_intent.payment_failed':
                    $this->handlePaymentIntentFailed($event->data->object);
                    break;
                    
                case 'charge.dispute.created':
                    $this->handleChargeDispute($event->data->object);
                    break;
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            $this->logError('Erreur traitement webhook', $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Traiter le succès d'un SetupIntent
     */
    private function handleSetupIntentSucceeded($setup_intent)
    {
        $reservation_id = $setup_intent->metadata->reservation_id ?? null;
        if ($reservation_id) {
            $this->updateDepositStatus($reservation_id, self::DEPOSIT_STATUS_AUTHORIZED);
            $this->logInfo('SetupIntent réussi via webhook', $reservation_id, $setup_intent->id);
        }
    }
    
    /**
     * Traiter un PaymentIntent qui nécessite une capture
     */
    private function handlePaymentIntentRequiresCapture($payment_intent)
    {
        $reservation_id = $payment_intent->metadata->reservation_id ?? null;
        if ($reservation_id && $payment_intent->metadata->type === 'deposit_preauth') {
            $this->logInfo('PaymentIntent pré-autorisé via webhook', $reservation_id, $payment_intent->id);
        }
    }
    
    /**
     * Traiter l'échec d'un paiement
     */
    private function handlePaymentIntentFailed($payment_intent)
    {
        $reservation_id = $payment_intent->metadata->reservation_id ?? null;
        if ($reservation_id) {
            $this->updateDepositStatus($reservation_id, self::DEPOSIT_STATUS_FAILED);
            $this->logError('Échec PaymentIntent via webhook', $payment_intent->last_payment_error->message ?? '', $reservation_id);
        }
    }
    
    /**
     * Traiter les litiges
     */
    private function handleChargeDispute($dispute)
    {
        // Rechercher la réservation concernée
        $charge = \Stripe\Charge::retrieve($dispute->charge);
        $payment_intent = \Stripe\PaymentIntent::retrieve($charge->payment_intent);
        
        $reservation_id = $payment_intent->metadata->reservation_id ?? null;
        if ($reservation_id) {
            $this->logError('Litige créé sur la caution', 'Montant: ' . ($dispute->amount / 100) . '€', $reservation_id);
            
            // Notifier l'administrateur
            $this->module->sendAdminNotification(
                'Litige Stripe sur caution',
                'Un litige a été créé pour la réservation #' . $reservation_id . 
                ' pour un montant de ' . ($dispute->amount / 100) . '€'
            );
        }
    }
}
