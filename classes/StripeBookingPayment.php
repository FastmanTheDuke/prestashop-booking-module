<?php
/**
 * Intégration Stripe complète pour le module de réservation
 * Gestion des paiements, webhooks et empreintes CB
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once(dirname(__FILE__) . '/Booker.php');
require_once(dirname(__FILE__) . '/BookerAuthReserved.php');

class StripeBookingPayment
{
    private $stripe_module;
    private $context;
    private $stripe_client;
    
    public function __construct()
    {
        $this->context = Context::getContext();
        $this->stripe_module = Module::getInstanceByName('stripe_official');
        
        if (!$this->stripe_module || !$this->stripe_module->active) {
            throw new PrestaShopException('Module Stripe non installé ou inactif');
        }
        
        // Initialiser le client Stripe
        $this->initializeStripe();
    }
    
    /**
     * Initialiser Stripe avec les clés API
     */
    private function initializeStripe()
    {
        if (!class_exists('\Stripe\Stripe')) {
            throw new PrestaShopException('Stripe SDK non disponible');
        }
        
        \Stripe\Stripe::setApiKey($this->getStripeSecretKey());
        \Stripe\Stripe::setApiVersion('2023-10-16');
        
        $this->stripe_client = new \Stripe\StripeClient($this->getStripeSecretKey());
    }
    
    /**
     * Récupérer la clé secrète Stripe
     */
    private function getStripeSecretKey()
    {
        $test_mode = Configuration::get('STRIPE_TEST_MODE');
        
        if ($test_mode) {
            return Configuration::get('STRIPE_TEST_SECRET_KEY');
        } else {
            return Configuration::get('STRIPE_LIVE_SECRET_KEY');
        }
    }
    
    /**
     * Récupérer la clé publique Stripe
     */
    public function getStripePublicKey()
    {
        $test_mode = Configuration::get('STRIPE_TEST_MODE');
        
        if ($test_mode) {
            return Configuration::get('STRIPE_TEST_PUBLISHABLE_KEY');
        } else {
            return Configuration::get('STRIPE_LIVE_PUBLISHABLE_KEY');
        }
    }
    
    /**
     * Créer une session de paiement Stripe Checkout
     */
    public function createPaymentSession(BookerAuthReserved $reservation)
    {
        if (!Configuration::get('BOOKING_STRIPE_ENABLED')) {
            throw new PrestaShopException('Paiements Stripe désactivés');
        }
        
        try {
            $booker = new Booker($reservation->id_booker);
            
            if (!Validate::isLoadedObject($booker)) {
                throw new PrestaShopException('Élément de réservation introuvable');
            }
            
            // Calculer les montants en centimes
            $main_amount = (int)($reservation->total_price * 100);
            $deposit_amount = (int)($reservation->deposit_amount * 100);
            $total_amount = $main_amount + $deposit_amount;
            
            // Créer les line items
            $line_items = [];
            
            // Article principal (réservation)
            $line_items[] = [
                'price_data' => [
                    'currency' => strtolower($this->context->currency->iso_code),
                    'product_data' => [
                        'name' => 'Réservation - ' . $booker->name,
                        'description' => sprintf(
                            'Réservation du %s de %sh à %sh',
                            date('d/m/Y', strtotime($reservation->date_reserved)),
                            $reservation->hour_from,
                            $reservation->hour_to
                        ),
                        'images' => $this->getBookerImages($booker),
                        'metadata' => [
                            'booking_reference' => $reservation->booking_reference,
                            'booker_id' => $booker->id,
                            'reservation_date' => $reservation->date_reserved
                        ]
                    ],
                    'unit_amount' => $main_amount,
                ],
                'quantity' => 1,
            ];
            
            // Caution si applicable
            if ($deposit_amount > 0) {
                $line_items[] = [
                    'price_data' => [
                        'currency' => strtolower($this->context->currency->iso_code),
                        'product_data' => [
                            'name' => 'Caution - ' . $booker->name,
                            'description' => 'Caution remboursable après utilisation',
                            'metadata' => [
                                'type' => 'deposit',
                                'booking_reference' => $reservation->booking_reference
                            ]
                        ],
                        'unit_amount' => $deposit_amount,
                    ],
                    'quantity' => 1,
                ];
            }
            
            // URLs de retour
            $base_url = $this->context->link->getModuleLink('booking', 'payment');
            $success_url = $base_url . '?action=success&booking_ref=' . $reservation->booking_reference . '&session_id={CHECKOUT_SESSION_ID}';
            $cancel_url = $base_url . '?action=cancel&booking_ref=' . $reservation->booking_reference;
            
            // Configuration de la session
            $session_config = [
                'payment_method_types' => ['card'],
                'line_items' => $line_items,
                'mode' => 'payment',
                'success_url' => $success_url,
                'cancel_url' => $cancel_url,
                'client_reference_id' => $reservation->booking_reference,
                'customer_email' => $reservation->customer_email,
                'metadata' => [
                    'booking_reference' => $reservation->booking_reference,
                    'reservation_id' => $reservation->id,
                    'booker_id' => $booker->id,
                    'customer_id' => $this->context->customer->id ?? 0,
                    'shop_id' => $this->context->shop->id,
                    'module' => 'booking'
                ],
                'payment_intent_data' => [
                    'metadata' => [
                        'booking_reference' => $reservation->booking_reference,
                        'reservation_id' => $reservation->id,
                        'module' => 'booking'
                    ],
                    'setup_future_usage' => Configuration::get('BOOKING_SAVE_CARDS') ? 'on_session' : null
                ],
                'billing_address_collection' => 'required',
                'shipping_address_collection' => [
                    'allowed_countries' => ['FR', 'BE', 'CH', 'LU', 'MC', 'ES', 'IT', 'DE']
                ],
                'locale' => $this->getStripeLocale(),
                'expires_at' => time() + (Configuration::get('BOOKING_PAYMENT_EXPIRY_MINUTES', 30) * 60)
            ];
            
            // Configuration de la caution si applicable
            if ($deposit_amount > 0 && Configuration::get('BOOKING_STRIPE_HOLD_DEPOSIT')) {
                $session_config['payment_intent_data']['capture_method'] = 'manual';
            }
            
            // Créer la session
            $session = $this->stripe_client->checkout->sessions->create($session_config);
            
            // Log de la création
            PrestaShopLogger::addLog(
                'Session Stripe créée pour réservation: ' . $reservation->booking_reference . ' (Session: ' . $session->id . ')',
                1,
                null,
                'StripeBookingPayment'
            );
            
            return $session;
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            PrestaShopLogger::addLog(
                'Erreur Stripe API: ' . $e->getMessage(),
                3,
                null,
                'StripeBookingPayment'
            );
            throw new PrestaShopException('Erreur lors de la création du paiement: ' . $e->getMessage());
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'Erreur création session paiement: ' . $e->getMessage(),
                3,
                null,
                'StripeBookingPayment'
            );
            throw $e;
        }
    }
    
    /**
     * Vérifier le statut d'une session de paiement
     */
    public function verifyPaymentSession($session_id)
    {
        try {
            $session = $this->stripe_client->checkout->sessions->retrieve($session_id);
            return $session->payment_status;
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            PrestaShopLogger::addLog(
                'Erreur vérification session Stripe: ' . $e->getMessage(),
                3,
                null,
                'StripeBookingPayment'
            );
            return 'error';
        }
    }
    
    /**
     * Vérifier et traiter un webhook Stripe
     */
    public function verifyWebhook($payload, $sig_header)
    {
        $webhook_secret = Configuration::get('STRIPE_WEBHOOK_SECRET');
        
        if (!$webhook_secret) {
            throw new Exception('Webhook secret non configuré');
        }
        
        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $webhook_secret);
            return $event;
            
        } catch (\UnexpectedValueException $e) {
            throw new Exception('Payload webhook invalide');
            
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            throw new Exception('Signature webhook invalide');
        }
    }
    
    /**
     * Créer une empreinte de carte bancaire
     */
    public function createCardSetupIntent($customer_email, $booking_reference)
    {
        if (!Configuration::get('BOOKING_SAVE_CARDS')) {
            throw new PrestaShopException('Sauvegarde des cartes désactivée');
        }
        
        try {
            // Créer ou récupérer le customer Stripe
            $customer = $this->getOrCreateStripeCustomer($customer_email);
            
            // Créer le Setup Intent
            $setup_intent = $this->stripe_client->setupIntents->create([
                'customer' => $customer->id,
                'payment_method_types' => ['card'],
                'usage' => 'off_session',
                'metadata' => [
                    'booking_reference' => $booking_reference,
                    'module' => 'booking',
                    'customer_email' => $customer_email
                ]
            ]);
            
            return $setup_intent;
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            PrestaShopLogger::addLog(
                'Erreur création Setup Intent: ' . $e->getMessage(),
                3,
                null,
                'StripeBookingPayment'
            );
            throw new PrestaShopException('Erreur lors de la création de l\'empreinte: ' . $e->getMessage());
        }
    }
    
    /**
     * Effectuer un paiement avec une carte sauvegardée
     */
    public function chargeWithSavedCard($payment_method_id, BookerAuthReserved $reservation)
    {
        try {
            $amount = (int)(($reservation->total_price + $reservation->deposit_amount) * 100);
            
            $payment_intent = $this->stripe_client->paymentIntents->create([
                'amount' => $amount,
                'currency' => strtolower($this->context->currency->iso_code),
                'payment_method' => $payment_method_id,
                'customer' => $this->getStripeCustomerByEmail($reservation->customer_email),
                'confirmation_method' => 'manual',
                'confirm' => true,
                'off_session' => true,
                'metadata' => [
                    'booking_reference' => $reservation->booking_reference,
                    'reservation_id' => $reservation->id,
                    'module' => 'booking'
                ]
            ]);
            
            return $payment_intent;
            
        } catch (\Stripe\Exception\CardException $e) {
            // Carte refusée
            throw new PrestaShopException('Paiement refusé: ' . $e->getDeclineCode());
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            PrestaShopLogger::addLog(
                'Erreur paiement carte sauvegardée: ' . $e->getMessage(),
                3,
                null,
                'StripeBookingPayment'
            );
            throw new PrestaShopException('Erreur lors du paiement: ' . $e->getMessage());
        }
    }
    
    /**
     * Rembourser un paiement
     */
    public function refundPayment($payment_intent_id, $amount = null, $reason = 'requested_by_customer')
    {
        try {
            $refund_data = [
                'payment_intent' => $payment_intent_id,
                'reason' => $reason,
                'metadata' => [
                    'module' => 'booking',
                    'refund_date' => date('Y-m-d H:i:s')
                ]
            ];
            
            if ($amount) {
                $refund_data['amount'] = (int)($amount * 100);
            }
            
            $refund = $this->stripe_client->refunds->create($refund_data);
            
            PrestaShopLogger::addLog(
                'Remboursement créé: ' . $refund->id . ' pour PaymentIntent: ' . $payment_intent_id,
                1,
                null,
                'StripeBookingPayment'
            );
            
            return $refund;
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            PrestaShopLogger::addLog(
                'Erreur remboursement: ' . $e->getMessage(),
                3,
                null,
                'StripeBookingPayment'
            );
            throw new PrestaShopException('Erreur lors du remboursement: ' . $e->getMessage());
        }
    }
    
    /**
     * Envoyer un email de confirmation de réservation
     */
    public function sendConfirmationEmail(BookerAuthReserved $reservation)
    {
        try {
            $booker = new Booker($reservation->id_booker);
            
            if (!Validate::isLoadedObject($booker)) {
                return false;
            }
            
            // Variables pour le template email
            $templateVars = [
                '{booking_reference}' => $reservation->booking_reference,
                '{customer_firstname}' => $reservation->customer_firstname,
                '{customer_lastname}' => $reservation->customer_lastname,
                '{booker_name}' => $booker->name,
                '{reservation_date}' => date('d/m/Y', strtotime($reservation->date_reserved)),
                '{reservation_time}' => $reservation->hour_from . 'h - ' . $reservation->hour_to . 'h',
                '{total_amount}' => number_format($reservation->total_price + $reservation->deposit_amount, 2) . ' €',
                '{shop_name}' => Configuration::get('PS_SHOP_NAME'),
                '{shop_email}' => Configuration::get('PS_SHOP_EMAIL'),
                '{customer_service_phone}' => Configuration::get('BOOKING_EMERGENCY_PHONE', '')
            ];
            
            // Envoyer l'email
            return Mail::Send(
                $this->context->language->id,
                'booking_confirmation',
                'Confirmation de votre réservation ' . $reservation->booking_reference,
                $templateVars,
                $reservation->customer_email,
                $reservation->customer_firstname . ' ' . $reservation->customer_lastname,
                Configuration::get('PS_SHOP_EMAIL'),
                Configuration::get('PS_SHOP_NAME'),
                null,
                null,
                dirname(__FILE__) . '/../mails/'
            );
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'Erreur envoi email confirmation: ' . $e->getMessage(),
                3,
                null,
                'StripeBookingPayment'
            );
            return false;
        }
    }
    
    /**
     * Récupérer ou créer un customer Stripe
     */
    private function getOrCreateStripeCustomer($email)
    {
        try {
            // Chercher un customer existant
            $customers = $this->stripe_client->customers->all([
                'email' => $email,
                'limit' => 1
            ]);
            
            if (!empty($customers->data)) {
                return $customers->data[0];
            }
            
            // Créer un nouveau customer
            $customer_data = [
                'email' => $email,
                'metadata' => [
                    'module' => 'booking',
                    'shop_id' => $this->context->shop->id,
                    'created_from' => 'booking_module'
                ]
            ];
            
            // Ajouter des infos supplémentaires si disponibles
            if ($this->context->customer->isLogged()) {
                $customer_data['name'] = $this->context->customer->firstname . ' ' . $this->context->customer->lastname;
                $customer_data['metadata']['prestashop_customer_id'] = $this->context->customer->id;
            }
            
            return $this->stripe_client->customers->create($customer_data);
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            PrestaShopLogger::addLog(
                'Erreur création customer Stripe: ' . $e->getMessage(),
                3,
                null,
                'StripeBookingPayment'
            );
            throw $e;
        }
    }
    
    /**
     * Récupérer un customer Stripe par email
     */
    private function getStripeCustomerByEmail($email)
    {
        try {
            $customers = $this->stripe_client->customers->all([
                'email' => $email,
                'limit' => 1
            ]);
            
            return !empty($customers->data) ? $customers->data[0]->id : null;
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return null;
        }
    }
    
    /**
     * Récupérer les images d'un booker pour Stripe
     */
    private function getBookerImages($booker)
    {
        $images = [];
        
        // Logique pour récupérer les images du booker
        // À adapter selon la structure de votre système d'images
        
        return $images;
    }
    
    /**
     * Récupérer la locale Stripe appropriée
     */
    private function getStripeLocale()
    {
        $locale_map = [
            'fr' => 'fr',
            'en' => 'en',
            'es' => 'es',
            'de' => 'de',
            'it' => 'it',
            'nl' => 'nl'
        ];
        
        $lang_iso = $this->context->language->iso_code;
        return isset($locale_map[$lang_iso]) ? $locale_map[$lang_iso] : 'auto';
    }
    
    /**
     * Traiter un événement webhook
     */
    public function processWebhookEvent($event)
    {
        switch ($event->type) {
            case 'checkout.session.completed':
                return $this->handleCheckoutSessionCompleted($event->data->object);
                
            case 'payment_intent.succeeded':
                return $this->handlePaymentIntentSucceeded($event->data->object);
                
            case 'payment_intent.payment_failed':
                return $this->handlePaymentIntentFailed($event->data->object);
                
            case 'setup_intent.succeeded':
                return $this->handleSetupIntentSucceeded($event->data->object);
                
            case 'invoice.payment_failed':
                return $this->handleInvoicePaymentFailed($event->data->object);
                
            default:
                PrestaShopLogger::addLog(
                    'Webhook Stripe non géré: ' . $event->type,
                    1,
                    null,
                    'StripeBookingPayment'
                );
                return true;
        }
    }
    
    /**
     * Traiter la complétion d'une session de checkout
     */
    private function handleCheckoutSessionCompleted($session)
    {
        $booking_reference = $session->metadata->booking_reference ?? null;
        
        if (!$booking_reference) {
            PrestaShopLogger::addLog(
                'Référence de réservation manquante dans le webhook checkout.session.completed',
                2,
                null,
                'StripeBookingPayment'
            );
            return false;
        }
        
        $reservation = BookerAuthReserved::getByBookingReference($booking_reference);
        
        if (!$reservation) {
            PrestaShopLogger::addLog(
                'Réservation introuvable pour la référence: ' . $booking_reference,
                2,
                null,
                'StripeBookingPayment'
            );
            return false;
        }
        
        // Mettre à jour le statut de la réservation
        $reservation->status = BookerAuthReserved::STATUS_PAID;
        $reservation->payment_status = BookerAuthReserved::PAYMENT_COMPLETED;
        
        if ($reservation->update()) {
            // Envoyer l'email de confirmation
            $this->sendConfirmationEmail($reservation);
            
            // Déclencher un hook pour les autres modules
            Hook::exec('actionBookingPaymentSuccess', [
                'reservation' => $reservation,
                'session' => $session
            ]);
            
            PrestaShopLogger::addLog(
                'Paiement confirmé pour réservation: ' . $booking_reference,
                1,
                null,
                'StripeBookingPayment'
            );
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Traiter le succès d'un Payment Intent
     */
    private function handlePaymentIntentSucceeded($payment_intent)
    {
        // Logique similaire à handleCheckoutSessionCompleted
        // mais pour les paiements directs avec Payment Intent
        
        $booking_reference = $payment_intent->metadata->booking_reference ?? null;
        
        if ($booking_reference) {
            $reservation = BookerAuthReserved::getByBookingReference($booking_reference);
            
            if ($reservation && $reservation->status != BookerAuthReserved::STATUS_PAID) {
                $reservation->status = BookerAuthReserved::STATUS_PAID;
                $reservation->payment_status = BookerAuthReserved::PAYMENT_COMPLETED;
                $reservation->update();
                
                $this->sendConfirmationEmail($reservation);
                
                Hook::exec('actionBookingPaymentSuccess', [
                    'reservation' => $reservation,
                    'payment_intent' => $payment_intent
                ]);
            }
        }
        
        return true;
    }
    
    /**
     * Traiter l'échec d'un Payment Intent
     */
    private function handlePaymentIntentFailed($payment_intent)
    {
        $booking_reference = $payment_intent->metadata->booking_reference ?? null;
        
        if ($booking_reference) {
            $reservation = BookerAuthReserved::getByBookingReference($booking_reference);
            
            if ($reservation) {
                // Ne pas changer le statut de PAID à PENDING
                if ($reservation->status != BookerAuthReserved::STATUS_PAID) {
                    $reservation->status = BookerAuthReserved::STATUS_PENDING;
                    $reservation->payment_status = BookerAuthReserved::PAYMENT_PENDING;
                    $reservation->update();
                }
                
                Hook::exec('actionBookingPaymentFailed', [
                    'reservation' => $reservation,
                    'payment_intent' => $payment_intent
                ]);
            }
        }
        
        return true;
    }
    
    /**
     * Traiter le succès d'un Setup Intent (carte sauvegardée)
     */
    private function handleSetupIntentSucceeded($setup_intent)
    {
        // Logique pour traiter la sauvegarde réussie d'une carte
        PrestaShopLogger::addLog(
            'Carte sauvegardée avec succès: ' . $setup_intent->id,
            1,
            null,
            'StripeBookingPayment'
        );
        
        return true;
    }
    
    /**
     * Traiter l'échec de paiement d'une facture
     */
    private function handleInvoicePaymentFailed($invoice)
    {
        // Logique pour traiter les échecs de paiement de factures récurrentes
        PrestaShopLogger::addLog(
            'Échec de paiement de facture: ' . $invoice->id,
            2,
            null,
            'StripeBookingPayment'
        );
        
        return true;
    }
}