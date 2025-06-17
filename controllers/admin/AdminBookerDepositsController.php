<?php
/**
 * Contrôleur d'administration pour la gestion des cautions Stripe
 * Module de réservations PrestaShop v2.1.5+
 * 
 * @author FastmanTheDuke
 * @version 2.1.5
 */

require_once _PS_MODULE_DIR_ . 'booking/classes/StripeDepositManager.php';

class AdminBookerDepositsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'booking_deposits';
        $this->className = 'BookingDeposit';
        $this->lang = false;
        $this->addRowAction('view');
        $this->addRowAction('capture');
        $this->addRowAction('release');
        $this->addRowAction('refund');
        $this->bulk_actions = array(
            'release' => array(
                'text' => $this->l('Libérer les cautions'),
                'icon' => 'icon-unlock',
                'confirm' => $this->l('Êtes-vous sûr de vouloir libérer ces cautions ?')
            ),
            'capture' => array(
                'text' => $this->l('Capturer les cautions'),
                'icon' => 'icon-credit-card',
                'confirm' => $this->l('Êtes-vous sûr de vouloir capturer ces cautions ?')
            )
        );

        parent::__construct();

        $this->meta_title = $this->l('Gestion des Cautions Stripe');
        $this->toolbar_title = $this->l('Cautions Stripe');

        // Configuration des colonnes du tableau
        $this->fields_list = array(
            'id_deposit' => array(
                'title' => $this->l('ID'),
                'align' => 'text-center',
                'class' => 'fixed-width-xs'
            ),
            'booking_reference' => array(
                'title' => $this->l('Référence'),
                'width' => 140,
                'callback' => 'displayBookingReference'
            ),
            'customer_name' => array(
                'title' => $this->l('Client'),
                'width' => 150,
                'callback' => 'displayCustomerName'
            ),
            'deposit_amount_display' => array(
                'title' => $this->l('Montant caution'),
                'width' => 120,
                'align' => 'text-right',
                'callback' => 'displayDepositAmount'
            ),
            'status' => array(
                'title' => $this->l('Statut'),
                'width' => 100,
                'align' => 'text-center',
                'callback' => 'displayStatus'
            ),
            'date_authorized' => array(
                'title' => $this->l('Date autorisation'),
                'width' => 150,
                'type' => 'datetime'
            ),
            'date_add' => array(
                'title' => $this->l('Date création'),
                'width' => 150,
                'type' => 'datetime'
            ),
            'actions' => array(
                'title' => $this->l('Actions'),
                'width' => 120,
                'align' => 'text-center',
                'callback' => 'displayActions',
                'orderby' => false,
                'search' => false
            )
        );

        // Filtres de recherche
        $this->fields_list['booking_reference']['filter_key'] = 'r!booking_reference';
        $this->fields_list['customer_name']['filter_key'] = 'r!customer_firstname';
    }

    /**
     * Requête SQL personnalisée pour récupérer les données avec jointures
     */
    public function getList($id_lang, $order_by = null, $order_way = null, $start = 0, $limit = null, $id_lang_shop = false)
    {
        // Requête personnalisée avec jointures
        $this->_select = '
            r.booking_reference,
            CONCAT(r.customer_firstname, " ", r.customer_lastname) as customer_name,
            r.customer_email,
            r.date_reserved,
            r.total_price,
            b.name as booker_name,
            (d.deposit_amount / 100) as deposit_amount_display
        ';

        $this->_join = '
            LEFT JOIN `' . _DB_PREFIX_ . 'booker_auth_reserved` r ON (r.id_reserved = a.id_reservation)
            LEFT JOIN `' . _DB_PREFIX_ . 'booker` b ON (b.id_booker = r.id_booker)
        ';

        $this->_where = 'AND r.id_reserved IS NOT NULL';

        // Gestion du tri par défaut
        if (!$order_by) {
            $order_by = 'date_add';
            $order_way = 'DESC';
        }

        parent::getList($id_lang, $order_by, $order_way, $start, $limit, $id_lang_shop);
    }

    /**
     * Afficher la référence de réservation avec lien
     */
    public function displayBookingReference($value, $row)
    {
        $link = $this->context->link->getAdminLink('AdminBookerAuthReserved');
        return '<a href="' . $link . '&id_reserved=' . (int)$row['id_reservation'] . '&viewbooker_auth_reserved" class="btn btn-default btn-xs">
                    <i class="icon-eye"></i> ' . Tools::safeOutput($value) . '
                </a>';
    }

    /**
     * Afficher le nom du client avec email
     */
    public function displayCustomerName($value, $row)
    {
        return '<strong>' . Tools::safeOutput($value) . '</strong><br>
                <small class="text-muted">' . Tools::safeOutput($row['customer_email']) . '</small>';
    }

    /**
     * Afficher le montant de la caution formaté
     */
    public function displayDepositAmount($value, $row)
    {
        $currency = new Currency(Configuration::get('PS_CURRENCY_DEFAULT'));
        return '<span class="badge badge-info">' . 
               Tools::displayPrice($value, $currency) . 
               '</span>';
    }

    /**
     * Afficher le statut avec badge coloré
     */
    public function displayStatus($value, $row)
    {
        $badges = array(
            'pending' => '<span class="badge badge-warning">En attente</span>',
            'authorized' => '<span class="badge badge-success">Autorisée</span>',
            'captured' => '<span class="badge badge-danger">Capturée</span>',
            'released' => '<span class="badge badge-info">Libérée</span>',
            'failed' => '<span class="badge badge-danger">Échec</span>',
            'cancelled' => '<span class="badge badge-default">Annulée</span>'
        );

        return isset($badges[$value]) ? $badges[$value] : '<span class="badge badge-default">' . $value . '</span>';
    }

    /**
     * Afficher les actions disponibles selon le statut
     */
    public function displayActions($value, $row)
    {
        $actions = '';
        $id_deposit = (int)$row['id_deposit'];
        
        // Action de visualisation
        $actions .= '<a href="' . self::$currentIndex . '&id_deposit=' . $id_deposit . '&viewbooking_deposits&token=' . $this->token . '" 
                        class="btn btn-default btn-xs" title="Voir les détails">
                        <i class="icon-eye"></i>
                     </a> ';

        // Actions selon le statut
        switch ($row['status']) {
            case 'authorized':
                $actions .= '<a href="' . self::$currentIndex . '&id_deposit=' . $id_deposit . '&capture_deposit&token=' . $this->token . '" 
                                class="btn btn-warning btn-xs" title="Capturer la caution" 
                                onclick="return confirm(\'Êtes-vous sûr de vouloir capturer cette caution ?\')">
                                <i class="icon-credit-card"></i>
                             </a> ';
                $actions .= '<a href="' . self::$currentIndex . '&id_deposit=' . $id_deposit . '&release_deposit&token=' . $this->token . '" 
                                class="btn btn-success btn-xs" title="Libérer la caution"
                                onclick="return confirm(\'Êtes-vous sûr de vouloir libérer cette caution ?\')">
                                <i class="icon-unlock"></i>
                             </a>';
                break;
                
            case 'captured':
                $actions .= '<a href="' . self::$currentIndex . '&id_deposit=' . $id_deposit . '&refund_deposit&token=' . $this->token . '" 
                                class="btn btn-info btn-xs" title="Rembourser"
                                onclick="return confirm(\'Êtes-vous sûr de vouloir rembourser cette caution ?\')">
                                <i class="icon-undo"></i>
                             </a>';
                break;
        }

        return $actions;
    }

    /**
     * Traitement des actions personnalisées
     */
    public function postProcess()
    {
        // Actions sur les cautions
        if (Tools::isSubmit('capture_deposit')) {
            $this->captureDeposit();
        } elseif (Tools::isSubmit('release_deposit')) {
            $this->releaseDeposit();
        } elseif (Tools::isSubmit('refund_deposit')) {
            $this->refundDeposit();
        } 
        // Actions en lot
        elseif (Tools::isSubmit('submitBulkreleasebooking_deposits')) {
            $this->processBulkRelease();
        } elseif (Tools::isSubmit('submitBulkcapturebooking_deposits')) {
            $this->processBulkCapture();
        }

        parent::postProcess();
    }

    /**
     * Capturer une caution individuelle
     */
    protected function captureDeposit()
    {
        $id_deposit = (int)Tools::getValue('id_deposit');
        
        if (!$id_deposit) {
            $this->errors[] = $this->l('ID de caution invalide');
            return;
        }

        try {
            $depositManager = new StripeDepositManager();
            
            // Récupérer les informations de la réservation
            $deposit = Db::getInstance()->getRow('
                SELECT d.*, r.id_reserved, r.booking_reference 
                FROM `' . _DB_PREFIX_ . 'booking_deposits` d
                LEFT JOIN `' . _DB_PREFIX_ . 'booker_auth_reserved` r ON r.id_reserved = d.id_reservation
                WHERE d.id_deposit = ' . $id_deposit
            );

            if (!$deposit) {
                $this->errors[] = $this->l('Caution introuvable');
                return;
            }

            if ($deposit['status'] !== 'authorized') {
                $this->errors[] = $this->l('Cette caution ne peut pas être capturée (statut: ' . $deposit['status'] . ')');
                return;
            }

            // Demander une raison pour la capture
            $capture_reason = Tools::getValue('capture_reason', 'Capture manuelle par administrateur');

            // Capturer via Stripe
            $result = $depositManager->captureDeposit($deposit['id_reserved'], $capture_reason);

            if ($result['success']) {
                $this->confirmations[] = $this->l('Caution capturée avec succès pour la réservation ') . $deposit['booking_reference'];
                
                // Logger l'activité
                $this->logActivity('deposit_captured', 'Capture manuelle de la caution', $deposit['id_reserved']);
            } else {
                $this->errors[] = $this->l('Erreur lors de la capture : ') . $result['error'];
            }

        } catch (Exception $e) {
            $this->errors[] = $this->l('Erreur technique : ') . $e->getMessage();
        }
    }

    /**
     * Libérer une caution individuelle
     */
    protected function releaseDeposit()
    {
        $id_deposit = (int)Tools::getValue('id_deposit');
        
        if (!$id_deposit) {
            $this->errors[] = $this->l('ID de caution invalide');
            return;
        }

        try {
            $depositManager = new StripeDepositManager();
            
            // Récupérer les informations
            $deposit = Db::getInstance()->getRow('
                SELECT d.*, r.id_reserved, r.booking_reference 
                FROM `' . _DB_PREFIX_ . 'booking_deposits` d
                LEFT JOIN `' . _DB_PREFIX_ . 'booker_auth_reserved` r ON r.id_reserved = d.id_reservation
                WHERE d.id_deposit = ' . $id_deposit
            );

            if (!$deposit) {
                $this->errors[] = $this->l('Caution introuvable');
                return;
            }

            if ($deposit['status'] !== 'authorized') {
                $this->errors[] = $this->l('Cette caution ne peut pas être libérée (statut: ' . $deposit['status'] . ')');
                return;
            }

            // Libérer via Stripe
            $result = $depositManager->releaseDeposit($deposit['id_reserved']);

            if ($result['success']) {
                $this->confirmations[] = $this->l('Caution libérée avec succès pour la réservation ') . $deposit['booking_reference'];
                
                // Logger l'activité
                $this->logActivity('deposit_released', 'Libération manuelle de la caution', $deposit['id_reserved']);
            } else {
                $this->errors[] = $this->l('Erreur lors de la libération : ') . $result['error'];
            }

        } catch (Exception $e) {
            $this->errors[] = $this->l('Erreur technique : ') . $e->getMessage();
        }
    }

    /**
     * Rembourser une caution
     */
    protected function refundDeposit()
    {
        $id_deposit = (int)Tools::getValue('id_deposit');
        
        if (!$id_deposit) {
            $this->errors[] = $this->l('ID de caution invalide');
            return;
        }

        try {
            $depositManager = new StripeDepositManager();
            
            // Récupérer les informations
            $deposit = Db::getInstance()->getRow('
                SELECT d.*, r.id_reserved, r.booking_reference 
                FROM `' . _DB_PREFIX_ . 'booking_deposits` d
                LEFT JOIN `' . _DB_PREFIX_ . 'booker_auth_reserved` r ON r.id_reserved = d.id_reservation
                WHERE d.id_deposit = ' . $id_deposit
            );

            if (!$deposit) {
                $this->errors[] = $this->l('Caution introuvable');
                return;
            }

            if ($deposit['status'] !== 'captured') {
                $this->errors[] = $this->l('Cette caution ne peut pas être remboursée (statut: ' . $deposit['status'] . ')');
                return;
            }

            // Montant et raison du remboursement
            $refund_amount = (int)Tools::getValue('refund_amount', $deposit['captured_amount']);
            $refund_reason = Tools::getValue('refund_reason', 'Remboursement manuel par administrateur');

            // Rembourser via Stripe
            $result = $depositManager->refundDeposit($deposit['id_reserved'], $refund_amount, $refund_reason);

            if ($result['success']) {
                $currency = new Currency(Configuration::get('PS_CURRENCY_DEFAULT'));
                $amount_display = Tools::displayPrice($refund_amount / 100, $currency);
                
                $this->confirmations[] = $this->l('Remboursement de ') . $amount_display . 
                                       $this->l(' effectué avec succès pour la réservation ') . $deposit['booking_reference'];
                
                // Logger l'activité
                $this->logActivity('deposit_refunded', 'Remboursement manuel: ' . $amount_display, $deposit['id_reserved']);
            } else {
                $this->errors[] = $this->l('Erreur lors du remboursement : ') . $result['error'];
            }

        } catch (Exception $e) {
            $this->errors[] = $this->l('Erreur technique : ') . $e->getMessage();
        }
    }

    /**
     * Traitement des actions en lot - Libération
     */
    protected function processBulkRelease()
    {
        $deposit_ids = Tools::getValue('booking_depositsBox');
        
        if (!is_array($deposit_ids) || empty($deposit_ids)) {
            $this->errors[] = $this->l('Aucune caution sélectionnée');
            return;
        }

        $success_count = 0;
        $error_count = 0;
        $depositManager = new StripeDepositManager();

        foreach ($deposit_ids as $id_deposit) {
            try {
                // Récupérer les informations de la caution
                $deposit = Db::getInstance()->getRow('
                    SELECT d.*, r.id_reserved, r.booking_reference 
                    FROM `' . _DB_PREFIX_ . 'booking_deposits` d
                    LEFT JOIN `' . _DB_PREFIX_ . 'booker_auth_reserved` r ON r.id_reserved = d.id_reservation
                    WHERE d.id_deposit = ' . (int)$id_deposit
                );

                if ($deposit && $deposit['status'] === 'authorized') {
                    $result = $depositManager->releaseDeposit($deposit['id_reserved']);
                    
                    if ($result['success']) {
                        $success_count++;
                        $this->logActivity('bulk_deposit_released', 'Libération en lot', $deposit['id_reserved']);
                    } else {
                        $error_count++;
                    }
                } else {
                    $error_count++;
                }
            } catch (Exception $e) {
                $error_count++;
            }
        }

        if ($success_count > 0) {
            $this->confirmations[] = sprintf($this->l('%d caution(s) libérée(s) avec succès'), $success_count);
        }
        
        if ($error_count > 0) {
            $this->errors[] = sprintf($this->l('%d erreur(s) lors de la libération'), $error_count);
        }
    }

    /**
     * Traitement des actions en lot - Capture
     */
    protected function processBulkCapture()
    {
        $deposit_ids = Tools::getValue('booking_depositsBox');
        
        if (!is_array($deposit_ids) || empty($deposit_ids)) {
            $this->errors[] = $this->l('Aucune caution sélectionnée');
            return;
        }

        $success_count = 0;
        $error_count = 0;
        $depositManager = new StripeDepositManager();

        foreach ($deposit_ids as $id_deposit) {
            try {
                // Récupérer les informations de la caution
                $deposit = Db::getInstance()->getRow('
                    SELECT d.*, r.id_reserved, r.booking_reference 
                    FROM `' . _DB_PREFIX_ . 'booking_deposits` d
                    LEFT JOIN `' . _DB_PREFIX_ . 'booker_auth_reserved` r ON r.id_reserved = d.id_reservation
                    WHERE d.id_deposit = ' . (int)$id_deposit
                );

                if ($deposit && $deposit['status'] === 'authorized') {
                    $result = $depositManager->captureDeposit($deposit['id_reserved'], 'Capture en lot par administrateur');
                    
                    if ($result['success']) {
                        $success_count++;
                        $this->logActivity('bulk_deposit_captured', 'Capture en lot', $deposit['id_reserved']);
                    } else {
                        $error_count++;
                    }
                } else {
                    $error_count++;
                }
            } catch (Exception $e) {
                $error_count++;
            }
        }

        if ($success_count > 0) {
            $this->confirmations[] = sprintf($this->l('%d caution(s) capturée(s) avec succès'), $success_count);
        }
        
        if ($error_count > 0) {
            $this->errors[] = sprintf($this->l('%d erreur(s) lors de la capture'), $error_count);
        }
    }

    /**
     * Vue détaillée d'une caution
     */
    public function renderView()
    {
        $id_deposit = (int)Tools::getValue('id_deposit');
        
        if (!$id_deposit) {
            $this->errors[] = $this->l('ID de caution invalide');
            return '';
        }

        // Récupérer toutes les informations de la caution
        $deposit_full = Db::getInstance()->getRow('
            SELECT d.*, r.*, b.name as booker_name,
                   (d.deposit_amount / 100) as deposit_amount_display,
                   (d.captured_amount / 100) as captured_amount_display,
                   (d.refunded_amount / 100) as refunded_amount_display
            FROM `' . _DB_PREFIX_ . 'booking_deposits` d
            LEFT JOIN `' . _DB_PREFIX_ . 'booker_auth_reserved` r ON r.id_reserved = d.id_reservation
            LEFT JOIN `' . _DB_PREFIX_ . 'booker` b ON b.id_booker = r.id_booker
            WHERE d.id_deposit = ' . $id_deposit
        );

        if (!$deposit_full) {
            $this->errors[] = $this->l('Caution introuvable');
            return '';
        }

        // Récupérer l'historique des actions
        $history = Db::getInstance()->executeS('
            SELECT * FROM `' . _DB_PREFIX_ . 'booking_deposit_history`
            WHERE id_deposit = ' . $id_deposit . '
            ORDER BY date_add DESC
        ');

        // Assigner les variables au template
        $this->tpl_view_vars = array(
            'deposit' => $deposit_full,
            'history' => $history,
            'currency' => new Currency(Configuration::get('PS_CURRENCY_DEFAULT'))
        );

        return parent::renderView();
    }

    /**
     * Ajouter CSS et JS spécifiques
     */
    public function setMedia()
    {
        parent::setMedia();
        
        $this->addCSS(_MODULE_DIR_ . 'booking/views/css/admin-deposits.css');
        $this->addJS(_MODULE_DIR_ . 'booking/views/js/admin-deposits.js');
    }

    /**
     * Logger les activités
     */
    protected function logActivity($action, $details = null, $id_reservation = null)
    {
        $employee_id = isset($this->context->employee) ? $this->context->employee->id : null;
        
        return Db::getInstance()->execute('
            INSERT INTO `' . _DB_PREFIX_ . 'booking_activity_log`
            (id_reservation, action, details, id_employee, date_add)
            VALUES (
                ' . ($id_reservation ? (int)$id_reservation : 'NULL') . ',
                "' . pSQL($action) . '",
                ' . ($details ? '"' . pSQL($details) . '"' : 'NULL') . ',
                ' . ($employee_id ? (int)$employee_id : 'NULL') . ',
                NOW()
            )
        ');
    }

    /**
     * Configuration de l'aide contextuelle
     */
    public function renderForm()
    {
        $this->context->smarty->assign([
            'help_box' => $this->l('Cette interface permet de gérer les cautions Stripe des réservations. Vous pouvez capturer, libérer ou rembourser les cautions selon leur statut.')
        ]);

        return parent::renderForm();
    }
}
