<?php
/**
 * Contrôleur de configuration centralisé pour le module de réservation
 * Gestion de tous les paramètres : prix, Stripe, notifications, etc.
 */

class AdminBookerSettingsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->display = 'options';
        $this->bootstrap = true;
        parent::__construct();
        
        $this->context->smarty->assign([
            'page_title' => $this->l('Configuration du système de réservations'),
            'page_subtitle' => $this->l('Paramètres généraux, paiements et notifications')
        ]);
    }

    /**
     * Rendu de la configuration
     */
    public function renderOptions()
    {
        $output = '';
        
        // Traitement des formulaires
        if (Tools::isSubmit('submitGeneralSettings')) {
            $this->processGeneralSettings();
        } elseif (Tools::isSubmit('submitPaymentSettings')) {
            $this->processPaymentSettings();
        } elseif (Tools::isSubmit('submitNotificationSettings')) {
            $this->processNotificationSettings();
        } elseif (Tools::isSubmit('submitAdvancedSettings')) {
            $this->processAdvancedSettings();
        }
        
        // Affichage des messages
        if (!empty($this->confirmations)) {
            foreach ($this->confirmations as $confirmation) {
                $output .= $this->displayConfirmation($confirmation);
            }
        }
        
        if (!empty($this->errors)) {
            foreach ($this->errors as $error) {
                $output .= $this->displayError($error);
            }
        }
        
        // Vue d'ensemble et statistiques
        $output .= $this->renderOverview();
        
        // Formulaires de configuration
        $output .= $this->renderGeneralSettings();
        $output .= $this->renderPaymentSettings();
        $output .= $this->renderNotificationSettings();
        $output .= $this->renderAdvancedSettings();
        
        // Outils d'administration
        $output .= $this->renderAdminTools();
        
        return $output;
    }
    
    /**
     * Vue d'ensemble du système
     */
    private function renderOverview()
    {
        try {
            // Statistiques générales
            $stats = [
                'total_bookers' => (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker`'),
                'active_bookers' => (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker` WHERE active = 1'),
                'total_availabilities' => (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth`'),
                'total_reservations' => (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`'),
                'pending_reservations' => (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` WHERE status = 0'),
                'paid_reservations' => (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` WHERE status = 2'),
                'monthly_revenue' => (float)Db::getInstance()->getValue('SELECT SUM(total_price) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` WHERE status = 2 AND MONTH(date_reserved) = MONTH(NOW())'),
                'linked_products' => (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker` WHERE id_product > 0')
            ];
            
            // Vérifications système
            $system_checks = [
                'stripe_configured' => $this->checkStripeConfiguration(),
                'cron_active' => Configuration::get('BOOKING_CRON_CLEAN_RESERVATIONS'),
                'notifications_active' => Configuration::get('BOOKING_NOTIFICATIONS_ENABLED'),
                'products_integration' => $stats['linked_products'] > 0
            ];
            
        } catch (Exception $e) {
            $stats = [];
            $system_checks = [];
        }
        
        $html = '
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-dashboard"></i> ' . $this->l('Vue d\'ensemble du système') . '
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-lg-8">
                        <h4>' . $this->l('Statistiques') . '</h4>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="alert alert-info text-center">
                                    <div style="font-size: 1.8em; font-weight: bold;">' . ($stats['total_bookers'] ?? 0) . '</div>
                                    <small>' . $this->l('Éléments total') . '</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="alert alert-success text-center">
                                    <div style="font-size: 1.8em; font-weight: bold;">' . ($stats['active_bookers'] ?? 0) . '</div>
                                    <small>' . $this->l('Éléments actifs') . '</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="alert alert-warning text-center">
                                    <div style="font-size: 1.8em; font-weight: bold;">' . ($stats['pending_reservations'] ?? 0) . '</div>
                                    <small>' . $this->l('En attente') . '</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="alert alert-primary text-center">
                                    <div style="font-size: 1.8em; font-weight: bold;">' . number_format($stats['monthly_revenue'] ?? 0, 2) . '€</div>
                                    <small>' . $this->l('CA du mois') . '</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <h4>' . $this->l('État du système') . '</h4>
                        <ul class="list-group">
                            <li class="list-group-item">
                                <i class="icon-' . ($system_checks['stripe_configured'] ? 'check text-success' : 'remove text-danger') . '"></i>
                                ' . $this->l('Configuration Stripe') . '
                            </li>
                            <li class="list-group-item">
                                <i class="icon-' . ($system_checks['cron_active'] ? 'check text-success' : 'remove text-danger') . '"></i>
                                ' . $this->l('Tâches automatiques') . '
                            </li>
                            <li class="list-group-item">
                                <i class="icon-' . ($system_checks['notifications_active'] ? 'check text-success' : 'remove text-danger') . '"></i>
                                ' . $this->l('Notifications') . '
                            </li>
                            <li class="list-group-item">
                                <i class="icon-' . ($system_checks['products_integration'] ? 'check text-success' : 'remove text-danger') . '"></i>
                                ' . $this->l('Intégration produits') . '
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>';
        
        return $html;
    }
    
    /**
     * Configuration générale
     */
    private function renderGeneralSettings()
    {
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Paramètres généraux'),
                    'icon' => 'icon-cogs'
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Prix par défaut'),
                        'name' => 'BOOKING_DEFAULT_PRICE',
                        'suffix' => '€',
                        'class' => 'fixed-width-sm',
                        'desc' => $this->l('Prix par défaut pour une nouvelle réservation')
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Montant de caution par défaut'),
                        'name' => 'BOOKING_DEPOSIT_AMOUNT',
                        'suffix' => '€',
                        'class' => 'fixed-width-sm',
                        'desc' => $this->l('Montant de caution par défaut (laisser vide pour 30% du prix)')
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Délai minimum de réservation'),
                        'name' => 'BOOKING_MIN_BOOKING_TIME',
                        'suffix' => 'heures',
                        'class' => 'fixed-width-sm',
                        'desc' => $this->l('Délai minimum entre la réservation et la date d\'utilisation')
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Réservation maximum à l\'avance'),
                        'name' => 'BOOKING_MAX_BOOKING_DAYS',
                        'suffix' => 'jours',
                        'class' => 'fixed-width-sm',
                        'desc' => $this->l('Nombre maximum de jours à l\'avance pour effectuer une réservation')
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Durée par défaut d\'une réservation'),
                        'name' => 'BOOKING_DEFAULT_DURATION',
                        'suffix' => 'minutes',
                        'class' => 'fixed-width-sm',
                        'desc' => $this->l('Durée par défaut d\'une réservation en minutes')
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Délai d\'expiration des réservations'),
                        'name' => 'BOOKING_EXPIRY_HOURS',
                        'suffix' => 'heures',
                        'class' => 'fixed-width-sm',
                        'desc' => $this->l('Délai avant expiration automatique d\'une réservation non confirmée')
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Confirmation automatique'),
                        'name' => 'BOOKING_AUTO_CONFIRM',
                        'values' => [
                            ['id' => 'auto_on', 'value' => 1, 'label' => $this->l('Oui')],
                            ['id' => 'auto_off', 'value' => 0, 'label' => $this->l('Non')]
                        ],
                        'desc' => $this->l('Confirmer automatiquement les nouvelles réservations')
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Sélection multiple dans les calendriers'),
                        'name' => 'BOOKING_MULTI_SELECT',
                        'values' => [
                            ['id' => 'multi_on', 'value' => 1, 'label' => $this->l('Oui')],
                            ['id' => 'multi_off', 'value' => 0, 'label' => $this->l('Non')]
                        ],
                        'desc' => $this->l('Permettre la sélection multiple de créneaux dans les calendriers')
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Téléphone d\'urgence'),
                        'name' => 'BOOKING_EMERGENCY_PHONE',
                        'class' => 'fixed-width-lg',
                        'desc' => $this->l('Numéro de téléphone d\'urgence affiché dans les emails')
                    ]
                ],
                'submit' => [
                    'title' => $this->l('Enregistrer les paramètres généraux'),
                    'name' => 'submitGeneralSettings'
                ]
            ]
        ];

        return $this->generateForm($fields_form, $this->getConfigFieldsValues());
    }
    
    /**
     * Configuration des paiements
     */
    private function renderPaymentSettings()
    {
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Configuration des paiements'),
                    'icon' => 'icon-credit-card'
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->l('Paiements activés'),
                        'name' => 'BOOKING_PAYMENT_ENABLED',
                        'values' => [
                            ['id' => 'payment_on', 'value' => 1, 'label' => $this->l('Oui')],
                            ['id' => 'payment_off', 'value' => 0, 'label' => $this->l('Non')]
                        ],
                        'desc' => $this->l('Activer les paiements en ligne pour les réservations')
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Stripe activé'),
                        'name' => 'BOOKING_STRIPE_ENABLED',
                        'values' => [
                            ['id' => 'stripe_on', 'value' => 1, 'label' => $this->l('Oui')],
                            ['id' => 'stripe_off', 'value' => 0, 'label' => $this->l('Non')]
                        ],
                        'desc' => $this->l('Utiliser Stripe pour les paiements (nécessite le module Stripe)')
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Sauvegarde des cartes'),
                        'name' => 'BOOKING_SAVE_CARDS',
                        'values' => [
                            ['id' => 'save_on', 'value' => 1, 'label' => $this->l('Oui')],
                            ['id' => 'save_off', 'value' => 0, 'label' => $this->l('Non')]
                        ],
                        'desc' => $this->l('Permettre la sauvegarde des cartes bancaires (empreinte CB)')
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Bloquer les cautions'),
                        'name' => 'BOOKING_STRIPE_HOLD_DEPOSIT',
                        'values' => [
                            ['id' => 'hold_on', 'value' => 1, 'label' => $this->l('Oui')],
                            ['id' => 'hold_off', 'value' => 0, 'label' => $this->l('Non')]
                        ],
                        'desc' => $this->l('Bloquer les cautions au lieu de les débiter immédiatement')
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Délai d\'expiration du paiement'),
                        'name' => 'BOOKING_PAYMENT_EXPIRY_MINUTES',
                        'suffix' => 'minutes',
                        'class' => 'fixed-width-sm',
                        'desc' => $this->l('Délai avant expiration d\'une session de paiement Stripe')
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Créer automatiquement les commandes'),
                        'name' => 'BOOKING_AUTO_CREATE_ORDER',
                        'values' => [
                            ['id' => 'create_on', 'value' => 1, 'label' => $this->l('Oui')],
                            ['id' => 'create_off', 'value' => 0, 'label' => $this->l('Non')]
                        ],
                        'desc' => $this->l('Créer automatiquement une commande PrestaShop lors de la validation')
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Statut de commande par défaut'),
                        'name' => 'BOOKING_ORDER_STATUS',
                        'options' => [
                            'query' => $this->getOrderStatuses(),
                            'id' => 'id',
                            'name' => 'name'
                        ],
                        'desc' => $this->l('Statut attribué aux nouvelles commandes de réservation')
                    ]
                ],
                'submit' => [
                    'title' => $this->l('Enregistrer les paramètres de paiement'),
                    'name' => 'submitPaymentSettings'
                ]
            ]
        ];

        return $this->generateForm($fields_form, $this->getConfigFieldsValues());
    }
    
    /**
     * Configuration des notifications
     */
    private function renderNotificationSettings()
    {
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Configuration des notifications'),
                    'icon' => 'icon-envelope'
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->l('Notifications activées'),
                        'name' => 'BOOKING_NOTIFICATIONS_ENABLED',
                        'values' => [
                            ['id' => 'notif_on', 'value' => 1, 'label' => $this->l('Oui')],
                            ['id' => 'notif_off', 'value' => 0, 'label' => $this->l('Non')]
                        ],
                        'desc' => $this->l('Activer l\'envoi de notifications par email')
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Email de confirmation automatique'),
                        'name' => 'BOOKING_AUTO_CONFIRMATION_EMAIL',
                        'values' => [
                            ['id' => 'conf_on', 'value' => 1, 'label' => $this->l('Oui')],
                            ['id' => 'conf_off', 'value' => 0, 'label' => $this->l('Non')]
                        ],
                        'desc' => $this->l('Envoyer automatiquement un email de confirmation après paiement')
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Rappels automatiques'),
                        'name' => 'BOOKING_AUTO_REMINDERS',
                        'values' => [
                            ['id' => 'remind_on', 'value' => 1, 'label' => $this->l('Oui')],
                            ['id' => 'remind_off', 'value' => 0, 'label' => $this->l('Non')]
                        ],
                        'desc' => $this->l('Envoyer des rappels automatiques avant les réservations')
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Délai des rappels'),
                        'name' => 'BOOKING_REMINDER_HOURS',
                        'suffix' => 'heures',
                        'class' => 'fixed-width-sm',
                        'desc' => $this->l('Nombre d\'heures avant la réservation pour envoyer le rappel')
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Notifications admin'),
                        'name' => 'BOOKING_ADMIN_NOTIFICATIONS',
                        'values' => [
                            ['id' => 'admin_on', 'value' => 1, 'label' => $this->l('Oui')],
                            ['id' => 'admin_off', 'value' => 0, 'label' => $this->l('Non')]
                        ],
                        'desc' => $this->l('Envoyer des notifications à l\'administrateur pour les nouvelles réservations')
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Email administrateur'),
                        'name' => 'BOOKING_ADMIN_EMAIL',
                        'class' => 'fixed-width-lg',
                        'desc' => $this->l('Adresse email pour recevoir les notifications admin (laisser vide pour utiliser l\'email de la boutique)')
                    ]
                ],
                'submit' => [
                    'title' => $this->l('Enregistrer les paramètres de notification'),
                    'name' => 'submitNotificationSettings'
                ]
            ]
        ];

        return $this->generateForm($fields_form, $this->getConfigFieldsValues());
    }
    
    /**
     * Configuration avancée
     */
    private function renderAdvancedSettings()
    {
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Paramètres avancés'),
                    'icon' => 'icon-gear'
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->l('Nettoyage automatique'),
                        'name' => 'BOOKING_CRON_CLEAN_RESERVATIONS',
                        'values' => [
                            ['id' => 'cron_on', 'value' => 1, 'label' => $this->l('Oui')],
                            ['id' => 'cron_off', 'value' => 0, 'label' => $this->l('Non')]
                        ],
                        'desc' => $this->l('Nettoyer automatiquement les réservations expirées')
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Synchronisation prix produits'),
                        'name' => 'BOOKING_SYNC_PRODUCT_PRICE',
                        'values' => [
                            ['id' => 'sync_on', 'value' => 1, 'label' => $this->l('Oui')],
                            ['id' => 'sync_off', 'value' => 0, 'label' => $this->l('Non')]
                        ],
                        'desc' => $this->l('Synchroniser automatiquement les prix avec les produits PrestaShop')
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Heures d\'ouverture - Début'),
                        'name' => 'BOOKING_BUSINESS_HOURS_START',
                        'class' => 'fixed-width-sm',
                        'desc' => $this->l('Heure de début des créneaux de réservation (format HH:MM)')
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Heures d\'ouverture - Fin'),
                        'name' => 'BOOKING_BUSINESS_HOURS_END',
                        'class' => 'fixed-width-sm',
                        'desc' => $this->l('Heure de fin des créneaux de réservation (format HH:MM)')
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Jours autorisés'),
                        'name' => 'BOOKING_ALLOWED_DAYS',
                        'class' => 'fixed-width-lg',
                        'desc' => $this->l('Jours de la semaine autorisés pour les réservations (1=Lundi, 2=Mardi, etc.) séparés par des virgules')
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Mode debug'),
                        'name' => 'BOOKING_DEBUG_MODE',
                        'values' => [
                            ['id' => 'debug_on', 'value' => 1, 'label' => $this->l('Oui')],
                            ['id' => 'debug_off', 'value' => 0, 'label' => $this->l('Non')]
                        ],
                        'desc' => $this->l('Activer les logs détaillés pour le débogage')
                    ]
                ],
                'submit' => [
                    'title' => $this->l('Enregistrer les paramètres avancés'),
                    'name' => 'submitAdvancedSettings'
                ]
            ]
        ];

        return $this->generateForm($fields_form, $this->getConfigFieldsValues());
    }
    
    /**
     * Outils d'administration
     */
    private function renderAdminTools()
    {
        $html = '
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-wrench"></i> ' . $this->l('Outils d\'administration') . '
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-lg-6">
                        <h4>' . $this->l('Maintenance') . '</h4>
                        <p>' . $this->l('Outils de maintenance et de diagnostic du système') . '</p>
                        <a href="' . self::$currentIndex . '&cleanExpiredReservations&token=' . $this->token . '" 
                           class="btn btn-warning" onclick="return confirm(\'' . $this->l('Supprimer toutes les réservations expirées ?') . '\')">
                            <i class="icon-trash"></i> ' . $this->l('Nettoyer les réservations expirées') . '
                        </a>
                        <br><br>
                        <a href="' . self::$currentIndex . '&syncAllProducts&token=' . $this->token . '" 
                           class="btn btn-info" onclick="return confirm(\'' . $this->l('Synchroniser tous les éléments avec les produits ?') . '\')">
                            <i class="icon-refresh"></i> ' . $this->l('Synchroniser tous les produits') . '
                        </a>
                    </div>
                    <div class="col-lg-6">
                        <h4>' . $this->l('Diagnostic') . '</h4>
                        <p>' . $this->l('Vérifications et diagnostics du système') . '</p>
                        <a href="' . self::$currentIndex . '&runDiagnostic&token=' . $this->token . '" class="btn btn-primary">
                            <i class="icon-stethoscope"></i> ' . $this->l('Lancer le diagnostic') . '
                        </a>
                        <br><br>
                        <a href="' . self::$currentIndex . '&exportLogs&token=' . $this->token . '" class="btn btn-default">
                            <i class="icon-download"></i> ' . $this->l('Exporter les logs') . '
                        </a>
                    </div>
                </div>
            </div>
        </div>';
        
        return $html;
    }
    
    // Méthodes de traitement des formulaires
    private function processGeneralSettings()
    {
        $this->updateConfigurations([
            'BOOKING_DEFAULT_PRICE',
            'BOOKING_DEPOSIT_AMOUNT',
            'BOOKING_MIN_BOOKING_TIME',
            'BOOKING_MAX_BOOKING_DAYS',
            'BOOKING_DEFAULT_DURATION',
            'BOOKING_EXPIRY_HOURS',
            'BOOKING_AUTO_CONFIRM',
            'BOOKING_MULTI_SELECT',
            'BOOKING_EMERGENCY_PHONE'
        ]);
    }
    
    private function processPaymentSettings()
    {
        $this->updateConfigurations([
            'BOOKING_PAYMENT_ENABLED',
            'BOOKING_STRIPE_ENABLED',
            'BOOKING_SAVE_CARDS',
            'BOOKING_STRIPE_HOLD_DEPOSIT',
            'BOOKING_PAYMENT_EXPIRY_MINUTES',
            'BOOKING_AUTO_CREATE_ORDER',
            'BOOKING_ORDER_STATUS'
        ]);
    }
    
    private function processNotificationSettings()
    {
        $this->updateConfigurations([
            'BOOKING_NOTIFICATIONS_ENABLED',
            'BOOKING_AUTO_CONFIRMATION_EMAIL',
            'BOOKING_AUTO_REMINDERS',
            'BOOKING_REMINDER_HOURS',
            'BOOKING_ADMIN_NOTIFICATIONS',
            'BOOKING_ADMIN_EMAIL'
        ]);
    }
    
    private function processAdvancedSettings()
    {
        $this->updateConfigurations([
            'BOOKING_CRON_CLEAN_RESERVATIONS',
            'BOOKING_SYNC_PRODUCT_PRICE',
            'BOOKING_BUSINESS_HOURS_START',
            'BOOKING_BUSINESS_HOURS_END',
            'BOOKING_ALLOWED_DAYS',
            'BOOKING_DEBUG_MODE'
        ]);
    }
    
    // Méthodes utilitaires
    private function updateConfigurations($config_keys)
    {
        $updated = 0;
        foreach ($config_keys as $key) {
            $value = Tools::getValue($key);
            if (Configuration::updateValue($key, $value)) {
                $updated++;
            }
        }
        
        if ($updated > 0) {
            $this->confirmations[] = sprintf($this->l('%d paramètre(s) mis à jour avec succès'), $updated);
        } else {
            $this->errors[] = $this->l('Aucun paramètre n\'a été modifié');
        }
    }
    
    private function getConfigFieldsValues()
    {
        return [
            'BOOKING_DEFAULT_PRICE' => Configuration::get('BOOKING_DEFAULT_PRICE'),
            'BOOKING_DEPOSIT_AMOUNT' => Configuration::get('BOOKING_DEPOSIT_AMOUNT'),
            'BOOKING_MIN_BOOKING_TIME' => Configuration::get('BOOKING_MIN_BOOKING_TIME'),
            'BOOKING_MAX_BOOKING_DAYS' => Configuration::get('BOOKING_MAX_BOOKING_DAYS'),
            'BOOKING_DEFAULT_DURATION' => Configuration::get('BOOKING_DEFAULT_DURATION'),
            'BOOKING_EXPIRY_HOURS' => Configuration::get('BOOKING_EXPIRY_HOURS'),
            'BOOKING_AUTO_CONFIRM' => Configuration::get('BOOKING_AUTO_CONFIRM'),
            'BOOKING_MULTI_SELECT' => Configuration::get('BOOKING_MULTI_SELECT'),
            'BOOKING_EMERGENCY_PHONE' => Configuration::get('BOOKING_EMERGENCY_PHONE'),
            'BOOKING_PAYMENT_ENABLED' => Configuration::get('BOOKING_PAYMENT_ENABLED'),
            'BOOKING_STRIPE_ENABLED' => Configuration::get('BOOKING_STRIPE_ENABLED'),
            'BOOKING_SAVE_CARDS' => Configuration::get('BOOKING_SAVE_CARDS'),
            'BOOKING_STRIPE_HOLD_DEPOSIT' => Configuration::get('BOOKING_STRIPE_HOLD_DEPOSIT'),
            'BOOKING_PAYMENT_EXPIRY_MINUTES' => Configuration::get('BOOKING_PAYMENT_EXPIRY_MINUTES'),
            'BOOKING_AUTO_CREATE_ORDER' => Configuration::get('BOOKING_AUTO_CREATE_ORDER'),
            'BOOKING_ORDER_STATUS' => Configuration::get('BOOKING_ORDER_STATUS'),
            'BOOKING_NOTIFICATIONS_ENABLED' => Configuration::get('BOOKING_NOTIFICATIONS_ENABLED'),
            'BOOKING_AUTO_CONFIRMATION_EMAIL' => Configuration::get('BOOKING_AUTO_CONFIRMATION_EMAIL'),
            'BOOKING_AUTO_REMINDERS' => Configuration::get('BOOKING_AUTO_REMINDERS'),
            'BOOKING_REMINDER_HOURS' => Configuration::get('BOOKING_REMINDER_HOURS'),
            'BOOKING_ADMIN_NOTIFICATIONS' => Configuration::get('BOOKING_ADMIN_NOTIFICATIONS'),
            'BOOKING_ADMIN_EMAIL' => Configuration::get('BOOKING_ADMIN_EMAIL'),
            'BOOKING_CRON_CLEAN_RESERVATIONS' => Configuration::get('BOOKING_CRON_CLEAN_RESERVATIONS'),
            'BOOKING_SYNC_PRODUCT_PRICE' => Configuration::get('BOOKING_SYNC_PRODUCT_PRICE'),
            'BOOKING_BUSINESS_HOURS_START' => Configuration::get('BOOKING_BUSINESS_HOURS_START'),
            'BOOKING_BUSINESS_HOURS_END' => Configuration::get('BOOKING_BUSINESS_HOURS_END'),
            'BOOKING_ALLOWED_DAYS' => Configuration::get('BOOKING_ALLOWED_DAYS'),
            'BOOKING_DEBUG_MODE' => Configuration::get('BOOKING_DEBUG_MODE')
        ];
    }
    
    private function generateForm($fields_form, $values)
    {
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = 'booking_config';
        $helper->default_form_language = $this->context->language->id;
        $helper->module = $this->module;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $helper->identifier = 'id';
        $helper->currentIndex = self::$currentIndex;
        $helper->token = $this->token;
        $helper->tpl_vars = [
            'fields_value' => $values,
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        ];

        return $helper->generateForm([$fields_form]);
    }
    
    private function getOrderStatuses()
    {
        $statuses = OrderState::getOrderStates($this->context->language->id);
        $options = [];
        
        foreach ($statuses as $status) {
            $options[] = [
                'id' => $status['id_order_state'],
                'name' => $status['name']
            ];
        }
        
        return $options;
    }
    
    private function checkStripeConfiguration()
    {
        return Configuration::get('STRIPE_TEST_SECRET_KEY') || Configuration::get('STRIPE_LIVE_SECRET_KEY');
    }
}