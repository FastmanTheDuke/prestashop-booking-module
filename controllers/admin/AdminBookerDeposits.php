<?php
/**
 * Contrôleur AdminBookerDeposits - Gestion des cautions Stripe
 * 
 * Interface d'administration pour :
 * - Visualiser toutes les cautions
 * - Gérer les pré-autorisations, captures et remboursements
 * - Configurer les paramètres de caution
 * - Traiter les actions en lot
 * - Monitoring des transactions Stripe
 */

require_once(_PS_MODULE_DIR_ . 'booking/classes/StripeDepositManager.php');
require_once(_PS_MODULE_DIR_ . 'booking/classes/BookerAuthReserved.php');

class AdminBookerDepositsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'booking_deposits';
        $this->className = 'BookingDeposit';
        $this->identifier = 'id_deposit';
        $this->lang = false;
        
        $this->context = Context::getContext();
        
        // Actions autorisées
        $this->actions = array('view', 'delete');
        $this->bulk_actions = array(
            'authorize' => array(
                'text' => 'Autoriser les cautions',
                'icon' => 'icon-credit-card',
                'confirm' => 'Êtes-vous sûr de vouloir autoriser ces cautions ?'
            ),
            'capture' => array(
                'text' => 'Capturer les cautions',
                'icon' => 'icon-money',
                'confirm' => 'Êtes-vous sûr de vouloir capturer ces cautions ?'
            ),
            'release' => array(
                'text' => 'Libérer les cautions',
                'icon' => 'icon-unlock',
                'confirm' => 'Êtes-vous sûr de vouloir libérer ces cautions ?'
            )
        );
        
        // Configuration des colonnes de la liste
        $this->fields_list = array(
            'id_deposit' => array(
                'title' => 'ID',
                'align' => 'center',
                'class' => 'fixed-width-xs'
            ),
            'booking_reference' => array(
                'title' => 'Référence',
                'width' => 120,
                'search' => true
            ),
            'customer_name' => array(
                'title' => 'Client',
                'width' => 150,
                'search' => false
            ),
            'booker_name' => array(
                'title' => 'Élément',
                'width' => 120,
                'search' => false
            ),
            'deposit_amount_formatted' => array(
                'title' => 'Montant caution',
                'align' => 'right',
                'width' => 100,
                'search' => false
            ),
            'status_badge' => array(
                'title' => 'Statut',
                'align' => 'center',
                'width' => 100,
                'search' => false,
                'orderby' => false
            ),
            'reservation_status_badge' => array(
                'title' => 'Statut réservation',
                'align' => 'center',
                'width' => 120,
                'search' => false,
                'orderby' => false
            ),
            'date_add' => array(
                'title' => 'Date création',
                'align' => 'center',
                'type' => 'datetime',
                'width' => 120
            )
        );
        
        parent::__construct();
        
        $this->depositManager = new StripeDepositManager();
    }
    
    /**
     * Initialiser le contenu
     */
    public function initContent()
    {
        // Statistiques en en-tête
        $this->context->smarty->assign(array(
            'deposit_stats' => $this->getDepositStats(),
            'show_toolbar' => true,
            'toolbar_title' => 'Gestion des Cautions Stripe'
        ));
        
        parent::initContent();
    }
    
    /**
     * Personnaliser la liste des dépôts
     */
    public function getList($id_lang, $order_by = null, $order_way = null, $start = 0, $limit = null, $id_lang_shop = false)
    {
        $this->_select = '\n            r.booking_reference,\n            CONCAT(r.customer_firstname, \" \", r.customer_lastname) as customer_name,\n            b.name as booker_name,\n            r.status as reservation_status,\n            (a.deposit_amount / 100) as deposit_amount_formatted\n        ';\n        \n        $this->_join = '\n            LEFT JOIN `' . _DB_PREFIX_ . 'booker_auth_reserved` r ON (a.id_reservation = r.id_reserved)\n            LEFT JOIN `' . _DB_PREFIX_ . 'booker` b ON (r.id_booker = b.id_booker)\n        ';\n        \n        $this->_where = 'AND r.id_reserved IS NOT NULL';\n        \n        parent::getList($id_lang, $order_by, $order_way, $start, $limit, $id_lang_shop);\n        \n        // Formatter les données pour l'affichage\n        foreach ($this->_list as &$row) {\n            // Badge de statut de caution\n            $row['status_badge'] = $this->getStatusBadge($row['status']);\n            \n            // Badge de statut de réservation\n            $row['reservation_status_badge'] = $this->getReservationStatusBadge($row['reservation_status']);\n            \n            // Formater le montant\n            $row['deposit_amount_formatted'] = Tools::displayPrice($row['deposit_amount_formatted']);\n        }\n    }\n    \n    /**\n     * Générer un badge de statut pour les cautions\n     */\n    private function getStatusBadge($status)\n    {\n        $badges = array(\n            'pending' => array('class' => 'badge-warning', 'text' => 'En attente'),\n            'authorized' => array('class' => 'badge-info', 'text' => 'Autorisée'),\n            'captured' => array('class' => 'badge-danger', 'text' => 'Capturée'),\n            'released' => array('class' => 'badge-success', 'text' => 'Libérée'),\n            'refunded' => array('class' => 'badge-default', 'text' => 'Remboursée'),\n            'failed' => array('class' => 'badge-important', 'text' => 'Échec')\n        );\n        \n        $badge = isset($badges[$status]) ? $badges[$status] : array('class' => 'badge-default', 'text' => $status);\n        \n        return '<span class=\"badge ' . $badge['class'] . '\">' . $badge['text'] . '</span>';\n    }\n    \n    /**\n     * Générer un badge de statut pour les réservations\n     */\n    private function getReservationStatusBadge($status)\n    {\n        $statuses = BookerAuthReserved::getStatuses();\n        $status_text = isset($statuses[$status]) ? $statuses[$status] : 'Inconnu';\n        \n        $badges = array(\n            BookerAuthReserved::STATUS_PENDING => 'badge-warning',\n            BookerAuthReserved::STATUS_ACCEPTED => 'badge-info',\n            BookerAuthReserved::STATUS_PAID => 'badge-success',\n            BookerAuthReserved::STATUS_COMPLETED => 'badge-success',\n            BookerAuthReserved::STATUS_CANCELLED => 'badge-important',\n            BookerAuthReserved::STATUS_EXPIRED => 'badge-default',\n            BookerAuthReserved::STATUS_REFUNDED => 'badge-default'\n        );\n        \n        $badge_class = isset($badges[$status]) ? $badges[$status] : 'badge-default';\n        \n        return '<span class=\"badge ' . $badge_class . '\">' . $status_text . '</span>';\n    }\n    \n    /**\n     * Actions sur les cautions\n     */\n    public function postProcess()\n    {\n        // Traiter les actions individuelles\n        if (Tools::isSubmit('authorize_deposit')) {\n            $this->processAuthorizeDeposit();\n        } elseif (Tools::isSubmit('capture_deposit')) {\n            $this->processCaptureDeposit();\n        } elseif (Tools::isSubmit('release_deposit')) {\n            $this->processReleaseDeposit();\n        } elseif (Tools::isSubmit('refund_deposit')) {\n            $this->processRefundDeposit();\n        }\n        \n        // Actions en lot\n        if (Tools::isSubmit('submitBulkauthorize' . $this->table)) {\n            $this->processBulkAuthorize();\n        } elseif (Tools::isSubmit('submitBulkcapture' . $this->table)) {\n            $this->processBulkCapture();\n        } elseif (Tools::isSubmit('submitBulkrelease' . $this->table)) {\n            $this->processBulkRelease();\n        }\n        \n        parent::postProcess();\n    }\n    \n    /**\n     * Autoriser une caution\n     */\n    private function processAuthorizeDeposit()\n    {\n        $id_reservation = (int)Tools::getValue('id_reservation');\n        if (!$id_reservation) {\n            $this->errors[] = 'ID de réservation manquant';\n            return;\n        }\n        \n        $result = $this->depositManager->authorizeDeposit($id_reservation);\n        \n        if ($result['success']) {\n            $this->confirmations[] = 'Caution autorisée avec succès';\n        } else {\n            $this->errors[] = 'Erreur lors de l\\'autorisation : ' . $result['error'];\n        }\n    }\n    \n    /**\n     * Capturer une caution\n     */\n    private function processCaptureDeposit()\n    {\n        $id_reservation = (int)Tools::getValue('id_reservation');\n        $amount = Tools::getValue('capture_amount');\n        \n        if (!$id_reservation) {\n            $this->errors[] = 'ID de réservation manquant';\n            return;\n        }\n        \n        $result = $this->depositManager->captureDeposit($id_reservation, $amount ? (float)$amount : null);\n        \n        if ($result['success']) {\n            $this->confirmations[] = 'Caution capturée avec succès';\n        } else {\n            $this->errors[] = 'Erreur lors de la capture : ' . $result['error'];\n        }\n    }\n    \n    /**\n     * Libérer une caution\n     */\n    private function processReleaseDeposit()\n    {\n        $id_reservation = (int)Tools::getValue('id_reservation');\n        if (!$id_reservation) {\n            $this->errors[] = 'ID de réservation manquant';\n            return;\n        }\n        \n        $result = $this->depositManager->releaseDeposit($id_reservation);\n        \n        if ($result['success']) {\n            $this->confirmations[] = 'Caution libérée avec succès';\n        } else {\n            $this->errors[] = 'Erreur lors de la libération : ' . $result['error'];\n        }\n    }\n    \n    /**\n     * Rembourser une caution\n     */\n    private function processRefundDeposit()\n    {\n        $id_reservation = (int)Tools::getValue('id_reservation');\n        $amount = Tools::getValue('refund_amount');\n        $reason = Tools::getValue('refund_reason');\n        \n        if (!$id_reservation) {\n            $this->errors[] = 'ID de réservation manquant';\n            return;\n        }\n        \n        $result = $this->depositManager->refundDeposit(\n            $id_reservation, \n            $amount ? (float)$amount : null,\n            $reason\n        );\n        \n        if ($result['success']) {\n            $this->confirmations[] = 'Caution remboursée avec succès';\n        } else {\n            $this->errors[] = 'Erreur lors du remboursement : ' . $result['error'];\n        }\n    }\n    \n    /**\n     * Actions en lot - Autoriser\n     */\n    private function processBulkAuthorize()\n    {\n        $ids = Tools::getValue($this->table . 'Box');\n        if (!is_array($ids) || empty($ids)) {\n            $this->errors[] = 'Aucune caution sélectionnée';\n            return;\n        }\n        \n        $success_count = 0;\n        $error_count = 0;\n        \n        foreach ($ids as $id_deposit) {\n            // Récupérer l'ID de réservation\n            $id_reservation = Db::getInstance()->getValue('\n                SELECT id_reservation FROM `' . _DB_PREFIX_ . 'booking_deposits`\n                WHERE id_deposit = ' . (int)$id_deposit\n            );\n            \n            if ($id_reservation) {\n                $result = $this->depositManager->authorizeDeposit($id_reservation);\n                if ($result['success']) {\n                    $success_count++;\n                } else {\n                    $error_count++;\n                }\n            }\n        }\n        \n        $this->confirmations[] = \"$success_count caution(s) autorisée(s) avec succès\";\n        if ($error_count > 0) {\n            $this->errors[] = \"$error_count erreur(s) lors de l'autorisation\";\n        }\n    }\n    \n    /**\n     * Actions en lot - Capturer\n     */\n    private function processBulkCapture()\n    {\n        $ids = Tools::getValue($this->table . 'Box');\n        if (!is_array($ids) || empty($ids)) {\n            $this->errors[] = 'Aucune caution sélectionnée';\n            return;\n        }\n        \n        $success_count = 0;\n        $error_count = 0;\n        \n        foreach ($ids as $id_deposit) {\n            // Récupérer l'ID de réservation\n            $id_reservation = Db::getInstance()->getValue('\n                SELECT id_reservation FROM `' . _DB_PREFIX_ . 'booking_deposits`\n                WHERE id_deposit = ' . (int)$id_deposit\n            );\n            \n            if ($id_reservation) {\n                $result = $this->depositManager->captureDeposit($id_reservation);\n                if ($result['success']) {\n                    $success_count++;\n                } else {\n                    $error_count++;\n                }\n            }\n        }\n        \n        $this->confirmations[] = \"$success_count caution(s) capturée(s) avec succès\";\n        if ($error_count > 0) {\n            $this->errors[] = \"$error_count erreur(s) lors de la capture\";\n        }\n    }\n    \n    /**\n     * Actions en lot - Libérer\n     */\n    private function processBulkRelease()\n    {\n        $ids = Tools::getValue($this->table . 'Box');\n        if (!is_array($ids) || empty($ids)) {\n            $this->errors[] = 'Aucune caution sélectionnée';\n            return;\n        }\n        \n        $success_count = 0;\n        $error_count = 0;\n        \n        foreach ($ids as $id_deposit) {\n            // Récupérer l'ID de réservation\n            $id_reservation = Db::getInstance()->getValue('\n                SELECT id_reservation FROM `' . _DB_PREFIX_ . 'booking_deposits`\n                WHERE id_deposit = ' . (int)$id_deposit\n            );\n            \n            if ($id_reservation) {\n                $result = $this->depositManager->releaseDeposit($id_reservation);\n                if ($result['success']) {\n                    $success_count++;\n                } else {\n                    $error_count++;\n                }\n            }\n        }\n        \n        $this->confirmations[] = \"$success_count caution(s) libérée(s) avec succès\";\n        if ($error_count > 0) {\n            $this->errors[] = \"$error_count erreur(s) lors de la libération\";\n        }\n    }\n    \n    /**\n     * Page de vue détaillée d'une caution\n     */\n    public function renderView()\n    {\n        $id_deposit = (int)Tools::getValue('id_deposit');\n        \n        // Récupérer les détails de la caution\n        $deposit = Db::getInstance()->getRow('\n            SELECT d.*, r.*, b.name as booker_name\n            FROM `' . _DB_PREFIX_ . 'booking_deposits` d\n            LEFT JOIN `' . _DB_PREFIX_ . 'booker_auth_reserved` r ON d.id_reservation = r.id_reserved\n            LEFT JOIN `' . _DB_PREFIX_ . 'booker` b ON r.id_booker = b.id_booker\n            WHERE d.id_deposit = ' . $id_deposit\n        );\n        \n        if (!$deposit) {\n            $this->errors[] = 'Caution non trouvée';\n            return parent::renderList();\n        }\n        \n        // Récupérer l'historique\n        $history = Db::getInstance()->executeS('\n            SELECT h.*, e.firstname, e.lastname\n            FROM `' . _DB_PREFIX_ . 'booking_deposit_history` h\n            LEFT JOIN `' . _DB_PREFIX_ . 'employee` e ON h.id_employee = e.id_employee\n            WHERE h.id_deposit = ' . $id_deposit . '\n            ORDER BY h.date_add DESC\n        ');\n        \n        $this->context->smarty->assign(array(\n            'deposit' => $deposit,\n            'history' => $history,\n            'statuses' => BookerAuthReserved::getStatuses(),\n            'current_url' => self::$currentIndex . '&token=' . $this->token\n        ));\n        \n        return $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'booking/views/templates/admin/deposits/view.tpl');\n    }\n    \n    /**\n     * Boutons d'action personnalisés\n     */\n    public function initToolbar()\n    {\n        parent::initToolbar();\n        \n        $this->page_header_toolbar_btn['config'] = array(\n            'href' => self::$currentIndex . '&configure&token=' . $this->token,\n            'desc' => 'Configuration des cautions',\n            'icon' => 'process-icon-cogs'\n        );\n        \n        $this->page_header_toolbar_btn['stats'] = array(\n            'href' => self::$currentIndex . '&stats&token=' . $this->token,\n            'desc' => 'Statistiques des cautions',\n            'icon' => 'process-icon-stats'\n        );\n    }\n    \n    /**\n     * Obtenir les statistiques des cautions\n     */\n    private function getDepositStats()\n    {\n        $sql = '\n            SELECT \n                COUNT(*) as total_deposits,\n                SUM(CASE WHEN status = \"pending\" THEN 1 ELSE 0 END) as pending_count,\n                SUM(CASE WHEN status = \"authorized\" THEN 1 ELSE 0 END) as authorized_count,\n                SUM(CASE WHEN status = \"captured\" THEN 1 ELSE 0 END) as captured_count,\n                SUM(CASE WHEN status = \"released\" THEN 1 ELSE 0 END) as released_count,\n                SUM(CASE WHEN status = \"failed\" THEN 1 ELSE 0 END) as failed_count,\n                SUM(deposit_amount) / 100 as total_amount,\n                SUM(captured_amount) / 100 as total_captured,\n                SUM(refunded_amount) / 100 as total_refunded\n            FROM `' . _DB_PREFIX_ . 'booking_deposits`\n            WHERE date_add >= DATE_SUB(NOW(), INTERVAL 30 DAY)\n        ';\n        \n        return Db::getInstance()->getRow($sql);\n    }\n    \n    /**\n     * Rendu de la page de configuration\n     */\n    public function renderConfiguration()\n    {\n        $fields_form = array(\n            'form' => array(\n                'legend' => array(\n                    'title' => 'Configuration des cautions Stripe',\n                    'icon' => 'icon-cogs'\n                ),\n                'input' => array(\n                    array(\n                        'type' => 'switch',\n                        'label' => 'Activer les cautions',\n                        'name' => 'BOOKING_STRIPE_DEPOSIT_ENABLED',\n                        'is_bool' => true,\n                        'values' => array(\n                            array('id' => 'active_on', 'value' => 1),\n                            array('id' => 'active_off', 'value' => 0)\n                        )\n                    ),\n                    array(\n                        'type' => 'text',\n                        'label' => 'Taux de caution (%)',\n                        'name' => 'BOOKING_DEPOSIT_RATE',\n                        'suffix' => '%',\n                        'class' => 'fixed-width-sm'\n                    ),\n                    array(\n                        'type' => 'text',\n                        'label' => 'Montant minimum',\n                        'name' => 'BOOKING_DEPOSIT_MIN_AMOUNT',\n                        'suffix' => '€',\n                        'class' => 'fixed-width-sm'\n                    ),\n                    array(\n                        'type' => 'text',\n                        'label' => 'Montant maximum',\n                        'name' => 'BOOKING_DEPOSIT_MAX_AMOUNT',\n                        'suffix' => '€',\n                        'class' => 'fixed-width-sm'\n                    )\n                ),\n                'submit' => array(\n                    'title' => 'Sauvegarder',\n                    'class' => 'btn btn-default pull-right'\n                )\n            )\n        );\n        \n        $helper = new HelperForm();\n        $helper->show_toolbar = false;\n        $helper->table = $this->table;\n        $helper->module = $this->module;\n        $helper->identifier = $this->identifier;\n        $helper->submit_action = 'submitConfiguration';\n        $helper->currentIndex = self::$currentIndex;\n        $helper->token = $this->token;\n        \n        // Charger les valeurs actuelles\n        $helper->fields_value = array(\n            'BOOKING_STRIPE_DEPOSIT_ENABLED' => Configuration::get('BOOKING_STRIPE_DEPOSIT_ENABLED'),\n            'BOOKING_DEPOSIT_RATE' => Configuration::get('BOOKING_DEPOSIT_RATE'),\n            'BOOKING_DEPOSIT_MIN_AMOUNT' => Configuration::get('BOOKING_DEPOSIT_MIN_AMOUNT'),\n            'BOOKING_DEPOSIT_MAX_AMOUNT' => Configuration::get('BOOKING_DEPOSIT_MAX_AMOUNT')\n        );\n        \n        return $helper->generateForm(array($fields_form));\n    }\n}\n