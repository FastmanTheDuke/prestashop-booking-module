<?php
/**
 * AdminBookerDepositsController - Gestion avancée des cautions Stripe
 * Version 2.1.5 - Avec empreinte CB, autorisation, capture et libération
 */

require_once _PS_MODULE_DIR_ . 'booking/classes/StripeDepositManager.php';
require_once _PS_MODULE_DIR_ . 'booking/classes/BookerAuthReserved.php';

class AdminBookerDepositsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'booking_deposits';
        $this->className = 'BookingDeposit';
        $this->identifier = 'id_deposit';
        $this->lang = false;
        
        parent::__construct();
        
        $this->addRowAction('view_details');
        $this->addRowAction('capture');
        $this->addRowAction('release');
        $this->addRowAction('refund');
        $this->addRowAction('view_history');
        
        $this->bulk_actions = array(
            'capture_selection' => array(
                'text' => $this->l('Capture selected'),
                'icon' => 'icon-credit-card text-success',
                'confirm' => $this->l('Capture the selected deposits?')
            ),
            'release_selection' => array(
                'text' => $this->l('Release selected'),
                'icon' => 'icon-unlock text-info',
                'confirm' => $this->l('Release the selected deposits?')
            ),
            'export_csv' => array(
                'text' => $this->l('Export CSV'),
                'icon' => 'icon-download'
            )
        );
        
        $this->fields_list = array(
            'id_deposit' => array(
                'title' => $this->l('ID'),
                'align' => 'center',
                'class' => 'fixed-width-xs'
            ),
            'booking_reference' => array(
                'title' => $this->l('Booking Reference'),
                'width' => 140,
                'align' => 'center'
            ),
            'customer_info' => array(
                'title' => $this->l('Customer'),
                'width' => 180,
                'orderby' => false,
                'search' => false,
                'callback' => 'formatCustomerInfo'
            ),
            'booker_name' => array(
                'title' => $this->l('Item'),
                'width' => 150,
                'orderby' => false,
                'search' => false
            ),
            'deposit_amount_display' => array(
                'title' => $this->l('Deposit Amount'),
                'width' => 120,
                'align' => 'text-right',
                'orderby' => false,
                'search' => false,
                'callback' => 'formatDepositAmount'
            ),
            'captured_amount_display' => array(
                'title' => $this->l('Captured'),
                'width' => 100,
                'align' => 'text-right',
                'orderby' => false,
                'search' => false,
                'callback' => 'formatCapturedAmount'
            ),
            'status_badge' => array(
                'title' => $this->l('Status'),
                'width' => 120,
                'align' => 'center',
                'orderby' => false,
                'search' => false,
                'callback' => 'formatStatusBadge'
            ),
            'stripe_info' => array(
                'title' => $this->l('Stripe Info'),
                'width' => 200,
                'align' => 'center',
                'orderby' => false,
                'search' => false,
                'callback' => 'formatStripeInfo'
            ),
            'date_authorized' => array(
                'title' => $this->l('Authorized'),
                'width' => 130,
                'type' => 'datetime',
                'align' => 'center'
            ),
            'date_captured' => array(
                'title' => $this->l('Captured'),
                'width' => 130,
                'type' => 'datetime',
                'align' => 'center'
            ),
            'date_released' => array(
                'title' => $this->l('Released'),
                'width' => 130,
                'type' => 'datetime',
                'align' => 'center'
            ),
            'actions' => array(
                'title' => $this->l('Quick Actions'),
                'width' => 150,
                'orderby' => false,
                'search' => false,
                'callback' => 'formatQuickActions'
            )
        );
        
        $this->_select = 'r.booking_reference, r.customer_firstname, r.customer_lastname, r.customer_email,
                         b.name as booker_name, r.date_reserved';
        $this->_join = 'LEFT JOIN `' . _DB_PREFIX_ . 'booker_auth_reserved` r ON (a.id_reservation = r.id_reserved)
                       LEFT JOIN `' . _DB_PREFIX_ . 'booker` b ON (r.id_booker = b.id_booker)';
        
        $this->shopLinkType = 'shop';
        $this->multishop_context = Shop::CONTEXT_ALL;
    }

    /**
     * Override de la liste pour ajouter les informations calculées
     */
    public function getList($id_lang, $order_by = null, $order_way = null, $start = 0, $limit = null, $id_lang_shop = false)
    {
        parent::getList($id_lang, $order_by, $order_way, $start, $limit, $id_lang_shop);
        
        if (!empty($this->_list)) {
            foreach ($this->_list as &$row) {
                // Calculer les montants en euros
                $row['deposit_amount_display'] = $row['deposit_amount'] / 100;
                $row['captured_amount_display'] = $row['captured_amount'] / 100;
                $row['refunded_amount_display'] = $row['refunded_amount'] / 100;
                
                // Informations client combinées
                $row['customer_info'] = trim($row['customer_firstname'] . ' ' . $row['customer_lastname']);
                if (empty($row['customer_info'])) {
                    $row['customer_info'] = $row['customer_email'];
                }
            }
        }
    }

    /**
     * Formatter pour les informations client
     */
    public static function formatCustomerInfo($customer_info, $row)
    {
        $name = trim($customer_info);
        $email = $row['customer_email'];
        
        return '<strong>' . $name . '</strong><br><small class="text-muted">' . $email . '</small>';
    }

    /**
     * Formatter pour le montant de caution
     */
    public static function formatDepositAmount($amount, $row)
    {
        $amount_euros = $amount;
        $currency = Context::getContext()->currency->sign;
        
        $color = 'info';
        if ($row['status'] == 'captured') {
            $color = 'danger';
        } elseif ($row['status'] == 'released') {
            $color = 'success';
        }
        
        return '<span class="label label-' . $color . '">' . $amount_euros . ' ' . $currency . '</span>';
    }

    /**
     * Formatter pour le montant capturé
     */
    public static function formatCapturedAmount($amount, $row)
    {
        $amount_euros = $amount;
        $currency = Context::getContext()->currency->sign;
        
        if ($amount_euros > 0) {
            return '<span class="label label-danger">' . $amount_euros . ' ' . $currency . '</span>';
        }
        
        return '<span class="text-muted">-</span>';
    }

    /**
     * Formatter pour le badge de statut
     */
    public static function formatStatusBadge($status, $row)
    {
        $statuses = array(
            'pending' => array('label' => 'Pending', 'color' => 'warning'),
            'authorized' => array('label' => 'Authorized', 'color' => 'info'),
            'captured' => array('label' => 'Captured', 'color' => 'danger'),
            'released' => array('label' => 'Released', 'color' => 'success'),
            'failed' => array('label' => 'Failed', 'color' => 'danger'),
            'cancelled' => array('label' => 'Cancelled', 'color' => 'default')
        );
        
        $status_info = isset($statuses[$row['status']]) ? $statuses[$row['status']] : array('label' => 'Unknown', 'color' => 'default');
        
        return '<span class="label label-' . $status_info['color'] . '">' . $status_info['label'] . '</span>';
    }

    /**
     * Formatter pour les informations Stripe
     */
    public static function formatStripeInfo($stripe_info, $row)
    {
        $html = '';
        
        if (!empty($row['setup_intent_id'])) {
            $html .= '<small title="Setup Intent"><i class="icon-cog"></i> ' . substr($row['setup_intent_id'], -8) . '</small><br>';
        }
        
        if (!empty($row['payment_intent_id'])) {
            $html .= '<small title="Payment Intent"><i class="icon-credit-card"></i> ' . substr($row['payment_intent_id'], -8) . '</small><br>';
        }
        
        if (!empty($row['payment_method_id'])) {
            $html .= '<small title="Payment Method"><i class="icon-lock"></i> ' . substr($row['payment_method_id'], -8) . '</small>';
        }
        
        return $html ?: '<span class="text-muted">No Stripe data</span>';
    }

    /**
     * Formatter pour les actions rapides
     */
    public static function formatQuickActions($actions, $row)
    {
        $html = '';
        $status = $row['status'];
        $id_deposit = $row['id_deposit'];
        
        $base_url = Context::getContext()->link->getAdminLink('AdminBookerDeposits');
        
        switch ($status) {
            case 'authorized':
                $html .= '<a href="' . $base_url . '&capture_deposit=' . $id_deposit . '" class="btn btn-xs btn-success" title="Capture" onclick="return confirm(\'Capture this deposit?\')">
                            <i class="icon-credit-card"></i>
                          </a> ';
                $html .= '<a href="' . $base_url . '&release_deposit=' . $id_deposit . '" class="btn btn-xs btn-info" title="Release" onclick="return confirm(\'Release this deposit?\')">
                            <i class="icon-unlock"></i>
                          </a>';
                break;
                
            case 'captured':
                $html .= '<a href="' . $base_url . '&refund_deposit=' . $id_deposit . '" class="btn btn-xs btn-warning" title="Refund" onclick="return confirm(\'Refund this deposit?\')">
                            <i class="icon-undo"></i>
                          </a>';
                break;
                
            case 'pending':
            case 'failed':
                $html .= '<a href="' . $base_url . '&retry_deposit=' . $id_deposit . '" class="btn btn-xs btn-primary" title="Retry" onclick="return confirm(\'Retry this deposit?\')">
                            <i class="icon-refresh"></i>
                          </a>';
                break;
                
            default:
                $html .= '<span class="text-muted">No actions</span>';
        }
        
        return $html;
    }

    /**
     * Formulaire de détails d'une caution
     */
    public function renderView()
    {
        $id_deposit = (int)Tools::getValue('id_deposit');
        
        if (!$id_deposit) {
            $this->errors[] = $this->l('Invalid deposit ID');
            return;
        }
        
        // Récupérer les détails de la caution
        $deposit = $this->getDepositDetails($id_deposit);
        if (!$deposit) {
            $this->errors[] = $this->l('Deposit not found');
            return;
        }
        
        // Récupérer l'historique
        $history = $this->getDepositHistory($id_deposit);
        
        // Récupérer les métadonnées Stripe
        $stripe_metadata = json_decode($deposit['metadata'], true);
        
        $this->context->smarty->assign(array(
            'deposit' => $deposit,
            'history' => $history,
            'stripe_metadata' => $stripe_metadata,
            'currency_sign' => $this->context->currency->sign,
            'can_capture' => $deposit['status'] == 'authorized',
            'can_release' => $deposit['status'] == 'authorized',
            'can_refund' => $deposit['status'] == 'captured',
            'stripe_dashboard_url' => $this->getStripeDashboardUrl($deposit),
            'module_dir' => _MODULE_DIR_ . 'booking/'
        ));
        
        return $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'booking/views/templates/admin/deposit_details.tpl');
    }

    /**
     * Récupérer les détails complets d'une caution
     */
    private function getDepositDetails($id_deposit)
    {
        $sql = 'SELECT d.*, r.booking_reference, r.customer_firstname, r.customer_lastname, 
                       r.customer_email, r.date_reserved, r.total_price, r.status as reservation_status,
                       b.name as booker_name, b.location
                FROM ' . _DB_PREFIX_ . 'booking_deposits d
                LEFT JOIN ' . _DB_PREFIX_ . 'booker_auth_reserved r ON d.id_reservation = r.id_reserved
                LEFT JOIN ' . _DB_PREFIX_ . 'booker b ON r.id_booker = b.id_booker
                WHERE d.id_deposit = ' . (int)$id_deposit;
        
        $result = Db::getInstance()->getRow($sql);
        
        if ($result) {
            // Convertir les montants en euros
            $result['deposit_amount_euros'] = $result['deposit_amount'] / 100;
            $result['captured_amount_euros'] = $result['captured_amount'] / 100;
            $result['refunded_amount_euros'] = $result['refunded_amount'] / 100;
        }
        
        return $result;
    }

    /**
     * Récupérer l'historique d'une caution
     */
    private function getDepositHistory($id_deposit)
    {
        $sql = 'SELECT h.*, e.firstname as employee_firstname, e.lastname as employee_lastname
                FROM ' . _DB_PREFIX_ . 'booking_deposit_history h
                LEFT JOIN ' . _DB_PREFIX_ . 'employee e ON h.id_employee = e.id_employee
                WHERE h.id_deposit = ' . (int)$id_deposit . '
                ORDER BY h.date_add DESC';
        
        $results = Db::getInstance()->executeS($sql);
        
        foreach ($results as &$row) {
            $row['amount_euros'] = $row['amount'] ? $row['amount'] / 100 : 0;
            $row['employee_name'] = trim($row['employee_firstname'] . ' ' . $row['employee_lastname']);
            if (empty($row['employee_name'])) {
                $row['employee_name'] = 'System';
            }
        }
        
        return $results;
    }

    /**
     * Générer l'URL du dashboard Stripe
     */
    private function getStripeDashboardUrl($deposit)
    {
        $test_mode = Configuration::get('BOOKING_STRIPE_TEST_MODE');
        $base_url = $test_mode ? 'https://dashboard.stripe.com/test' : 'https://dashboard.stripe.com';
        
        if (!empty($deposit['payment_intent_id'])) {
            return $base_url . '/payments/' . $deposit['payment_intent_id'];
        } elseif (!empty($deposit['setup_intent_id'])) {
            return $base_url . '/setup_intents/' . $deposit['setup_intent_id'];
        }
        
        return $base_url;
    }

    /**
     * Traitement des actions sur les cautions
     */
    public function postProcess()
    {
        if (Tools::isSubmit('capture_deposit')) {
            $this->processCaptureDeposit();
        } elseif (Tools::isSubmit('release_deposit')) {
            $this->processReleaseDeposit();
        } elseif (Tools::isSubmit('refund_deposit')) {
            $this->processRefundDeposit();
        } elseif (Tools::isSubmit('retry_deposit')) {
            $this->processRetryDeposit();
        }
        
        return parent::postProcess();
    }

    /**
     * Capturer une caution
     */
    private function processCaptureDeposit()
    {
        $id_deposit = (int)Tools::getValue('capture_deposit');
        
        if (!$id_deposit) {
            $this->errors[] = $this->l('Invalid deposit ID');
            return;
        }
        
        try {
            $depositManager = new StripeDepositManager();
            $result = $depositManager->captureDepositById($id_deposit);
            
            if ($result['success']) {
                $this->confirmations[] = $this->l('Deposit captured successfully');
                
                // Logger l'action
                $this->logDepositAction($id_deposit, 'manual_capture', 'Captured manually by admin');
            } else {
                $this->errors[] = $this->l('Failed to capture deposit: ') . $result['error'];
            }
            
        } catch (Exception $e) {
            $this->errors[] = $this->l('Error capturing deposit: ') . $e->getMessage();
        }
    }

    /**
     * Libérer une caution
     */
    private function processReleaseDeposit()
    {
        $id_deposit = (int)Tools::getValue('release_deposit');
        
        if (!$id_deposit) {
            $this->errors[] = $this->l('Invalid deposit ID');
            return;
        }
        
        try {
            $depositManager = new StripeDepositManager();
            $result = $depositManager->releaseDepositById($id_deposit);
            
            if ($result['success']) {
                $this->confirmations[] = $this->l('Deposit released successfully');
                
                // Logger l'action
                $this->logDepositAction($id_deposit, 'manual_release', 'Released manually by admin');
            } else {
                $this->errors[] = $this->l('Failed to release deposit: ') . $result['error'];
            }
            
        } catch (Exception $e) {
            $this->errors[] = $this->l('Error releasing deposit: ') . $e->getMessage();
        }
    }

    /**
     * Rembourser une caution
     */
    private function processRefundDeposit()
    {
        $id_deposit = (int)Tools::getValue('refund_deposit');
        $refund_amount = Tools::getValue('refund_amount');
        $refund_reason = Tools::getValue('refund_reason', 'Manual refund by admin');
        
        if (!$id_deposit) {
            $this->errors[] = $this->l('Invalid deposit ID');
            return;
        }
        
        try {
            $depositManager = new StripeDepositManager();
            $result = $depositManager->refundDeposit($id_deposit, $refund_amount, $refund_reason);
            
            if ($result['success']) {
                $this->confirmations[] = $this->l('Deposit refunded successfully');
                
                // Logger l'action
                $this->logDepositAction($id_deposit, 'manual_refund', 'Refunded manually by admin: ' . $refund_reason);
            } else {
                $this->errors[] = $this->l('Failed to refund deposit: ') . $result['error'];
            }
            
        } catch (Exception $e) {
            $this->errors[] = $this->l('Error refunding deposit: ') . $e->getMessage();
        }
    }

    /**
     * Réessayer une caution
     */
    private function processRetryDeposit()
    {
        $id_deposit = (int)Tools::getValue('retry_deposit');
        
        if (!$id_deposit) {
            $this->errors[] = $this->l('Invalid deposit ID');
            return;
        }
        
        try {
            $depositManager = new StripeDepositManager();
            $result = $depositManager->retryDeposit($id_deposit);
            
            if ($result['success']) {
                $this->confirmations[] = $this->l('Deposit retry initiated successfully');
                
                // Logger l'action
                $this->logDepositAction($id_deposit, 'manual_retry', 'Retried manually by admin');
            } else {
                $this->errors[] = $this->l('Failed to retry deposit: ') . $result['error'];
            }
            
        } catch (Exception $e) {
            $this->errors[] = $this->l('Error retrying deposit: ') . $e->getMessage();
        }
    }

    /**
     * Logger une action sur une caution
     */
    private function logDepositAction($id_deposit, $action_type, $details)
    {
        $deposit = $this->getDepositDetails($id_deposit);
        if (!$deposit) {
            return;
        }
        
        $data = array(
            'id_deposit' => $id_deposit,
            'id_reservation' => $deposit['id_reservation'],
            'action_type' => pSQL($action_type),
            'old_status' => pSQL($deposit['status']),
            'new_status' => pSQL($deposit['status']), // Sera mis à jour par le StripeDepositManager
            'details' => pSQL($details),
            'id_employee' => isset($this->context->employee) ? (int)$this->context->employee->id : null,
            'date_add' => date('Y-m-d H:i:s')
        );
        
        return Db::getInstance()->insert('booking_deposit_history', $data);
    }

    /**
     * Actions en lot sur les cautions
     */
    public function processBulkCaptureSelection()
    {
        $selection = Tools::getValue($this->table . 'Box');
        
        if (is_array($selection) && count($selection)) {
            $processed = 0;
            $depositManager = new StripeDepositManager();
            
            foreach ($selection as $id_deposit) {
                try {
                    $result = $depositManager->captureDepositById((int)$id_deposit);
                    if ($result['success']) {
                        $this->logDepositAction($id_deposit, 'bulk_capture', 'Captured in bulk action');
                        $processed++;
                    }
                } catch (Exception $e) {
                    // Log l'erreur mais continue
                    PrestaShopLogger::addLog('Bulk capture error for deposit ' . $id_deposit . ': ' . $e->getMessage(), 3);
                }
            }
            
            $this->confirmations[] = sprintf($this->l('%d deposits captured successfully'), $processed);
        }
    }

    /**
     * Actions en lot - libération
     */
    public function processBulkReleaseSelection()
    {
        $selection = Tools::getValue($this->table . 'Box');
        
        if (is_array($selection) && count($selection)) {
            $processed = 0;
            $depositManager = new StripeDepositManager();
            
            foreach ($selection as $id_deposit) {
                try {
                    $result = $depositManager->releaseDepositById((int)$id_deposit);
                    if ($result['success']) {
                        $this->logDepositAction($id_deposit, 'bulk_release', 'Released in bulk action');
                        $processed++;
                    }
                } catch (Exception $e) {
                    PrestaShopLogger::addLog('Bulk release error for deposit ' . $id_deposit . ': ' . $e->getMessage(), 3);
                }
            }
            
            $this->confirmations[] = sprintf($this->l('%d deposits released successfully'), $processed);
        }
    }

    /**
     * Export CSV des cautions
     */
    public function processBulkExportCsv()
    {
        $selection = Tools::getValue($this->table . 'Box');
        
        if (is_array($selection) && count($selection)) {
            $this->exportDepositsCSV($selection);
        } else {
            $this->exportDepositsCSV(); // Export toutes les cautions
        }
    }

    /**
     * Exporter les cautions en CSV
     */
    private function exportDepositsCSV($ids = null)
    {
        $where = '';
        if ($ids && is_array($ids)) {
            $where = 'WHERE d.id_deposit IN (' . implode(',', array_map('intval', $ids)) . ')';
        }
        
        $sql = 'SELECT d.*, r.booking_reference, r.customer_firstname, r.customer_lastname, 
                       r.customer_email, r.date_reserved, b.name as booker_name
                FROM ' . _DB_PREFIX_ . 'booking_deposits d
                LEFT JOIN ' . _DB_PREFIX_ . 'booker_auth_reserved r ON d.id_reservation = r.id_reserved
                LEFT JOIN ' . _DB_PREFIX_ . 'booker b ON r.id_booker = b.id_booker
                ' . $where . '
                ORDER BY d.date_add DESC';
        
        $deposits = Db::getInstance()->executeS($sql);
        
        if (empty($deposits)) {
            $this->errors[] = $this->l('No deposits to export');
            return;
        }
        
        // Générer le CSV
        $filename = 'booking_deposits_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        $output = fopen('php://output', 'w');
        
        // En-têtes CSV
        fputcsv($output, array(
            'Deposit ID',
            'Booking Reference',
            'Customer Name',
            'Customer Email',
            'Booker Name',
            'Date Reserved',
            'Deposit Amount (€)',
            'Captured Amount (€)',
            'Refunded Amount (€)',
            'Status',
            'Stripe Payment Intent',
            'Date Authorized',
            'Date Captured',
            'Date Released',
            'Date Created'
        ));
        
        // Données
        foreach ($deposits as $deposit) {
            fputcsv($output, array(
                $deposit['id_deposit'],
                $deposit['booking_reference'],
                trim($deposit['customer_firstname'] . ' ' . $deposit['customer_lastname']),
                $deposit['customer_email'],
                $deposit['booker_name'],
                $deposit['date_reserved'],
                number_format($deposit['deposit_amount'] / 100, 2),
                number_format($deposit['captured_amount'] / 100, 2),
                number_format($deposit['refunded_amount'] / 100, 2),
                $deposit['status'],
                $deposit['payment_intent_id'],
                $deposit['date_authorized'],
                $deposit['date_captured'],
                $deposit['date_released'],
                $deposit['date_add']
            ));
        }
        
        fclose($output);
        exit;
    }

    /**
     * Ajouter CSS et JS pour les cautions
     */
    public function setMedia()
    {
        parent::setMedia();
        
        $this->addCSS(_MODULE_DIR_ . 'booking/views/css/admin-deposits.css');
        $this->addJS(_MODULE_DIR_ . 'booking/views/js/admin-deposits.js');
        
        // Configuration JavaScript
        Media::addJsDef(array(
            'bookingDepositsConfig' => array(
                'ajax_url' => $this->context->link->getAdminLink('AdminBookerDeposits'),
                'token' => $this->token,
                'stripe_test_mode' => Configuration::get('BOOKING_STRIPE_TEST_MODE'),
                'currency_sign' => $this->context->currency->sign,
                'translations' => array(
                    'confirm_capture' => $this->l('Are you sure you want to capture this deposit?'),
                    'confirm_release' => $this->l('Are you sure you want to release this deposit?'),
                    'confirm_refund' => $this->l('Are you sure you want to refund this deposit?'),
                    'processing' => $this->l('Processing...'),
                    'success' => $this->l('Action completed successfully'),
                    'error' => $this->l('An error occurred')
                )
            )
        ));
    }

    /**
     * Toolbar avec statistiques
     */
    public function initToolbar()
    {
        parent::initToolbar();
        
        $this->toolbar_btn['stats'] = array(
            'href' => self::$currentIndex . '&configure=booking&token=' . $this->token . '&deposit_stats=1',
            'desc' => $this->l('Deposit Statistics'),
            'icon' => 'process-icon-stats'
        );
        
        $this->toolbar_btn['stripe_dashboard'] = array(
            'href' => $this->getStripeDashboardUrl(array()),
            'desc' => $this->l('Stripe Dashboard'),
            'icon' => 'process-icon-external-link',
            'target' => '_blank'
        );
        
        $this->toolbar_btn['sync_stripe'] = array(
            'href' => self::$currentIndex . '&configure=booking&token=' . $this->token . '&sync_stripe=1',
            'desc' => $this->l('Sync with Stripe'),
            'icon' => 'process-icon-refresh'
        );
    }
}
