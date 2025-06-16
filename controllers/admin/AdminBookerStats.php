<?php
/**
 * Contrôleur administrateur pour les statistiques de réservations
 * Tableau de bord complet avec graphiques et métriques
 */

require_once(dirname(__FILE__) . '/../../classes/Booker.php');
require_once(dirname(__FILE__) . '/../../classes/BookerAuth.php');
require_once(dirname(__FILE__) . '/../../classes/BookerAuthReserved.php');

class AdminBookerStatsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->display = 'view';
        $this->meta_title = 'Statistiques des réservations';
        
        parent::__construct();
    }

    /**
     * Rendu principal de la page de statistiques
     */
    public function renderView()
    {
        try {
            $this->initPageHeaderToolbar();
            
            // Récupération des données
            $stats = $this->getGeneralStats();
            $revenue_data = $this->getRevenueData();
            $booking_trends = $this->getBookingTrends();
            $popular_bookers = $this->getPopularBookers();
            $customer_stats = $this->getCustomerStats();
            $performance_metrics = $this->getPerformanceMetrics();
            
            // Préparation des données pour les graphiques
            $chart_data = $this->prepareChartData($revenue_data, $booking_trends);
            
            $this->context->smarty->assign(array(
                'stats' => $stats,
                'revenue_data' => $revenue_data,
                'booking_trends' => $booking_trends,
                'popular_bookers' => $popular_bookers,
                'customer_stats' => $customer_stats,
                'performance_metrics' => $performance_metrics,
                'chart_data' => $chart_data,
                'current_date' => date('Y-m-d'),
                'currency_symbol' => $this->context->currency->symbol
            ));
            
            // Chemin du template
            $template_path = dirname(__FILE__) . '/../../views/templates/admin/stats_dashboard.tpl';
            
            if (file_exists($template_path)) {
                return $this->context->smarty->fetch($template_path);
            } else {
                return $this->generateDefaultStatsView();
            }
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog('AdminBookerStats::renderView() - Erreur: ' . $e->getMessage(), 3);
            return $this->displayError($this->l('Erreur lors du chargement des statistiques'));
        }
    }
    
    /**
     * Barre d'outils de la page
     */
    public function initPageHeaderToolbar()
    {
        $this->page_header_toolbar_btn['export_csv'] = array(
            'href' => self::$currentIndex . '&action=exportStats&token=' . $this->token,
            'desc' => $this->l('Exporter CSV'),
            'icon' => 'process-icon-export'
        );
        
        $this->page_header_toolbar_btn['refresh'] = array(
            'href' => self::$currentIndex . '&token=' . $this->token,
            'desc' => $this->l('Actualiser'),
            'icon' => 'process-icon-refresh'
        );
        
        $this->page_header_toolbar_btn['calendar'] = array(
            'href' => $this->context->link->getAdminLink('AdminBookerView'),
            'desc' => $this->l('Calendriers'),
            'icon' => 'process-icon-calendar'
        );
        
        parent::initPageHeaderToolbar();
    }
    
    /**
     * Traitement des actions personnalisées
     */
    public function postProcess()
    {
        if (Tools::getValue('action') === 'exportStats') {
            $this->exportStatsToCSV();
        }
        
        return parent::postProcess();
    }
    
    /**
     * Statistiques générales
     */
    private function getGeneralStats()
    {
        $today = date('Y-m-d');
        $this_month_start = date('Y-m-01');
        $last_month_start = date('Y-m-01', strtotime('-1 month'));
        $last_month_end = date('Y-m-t', strtotime('-1 month'));
        
        return array(
            // Réservations aujourd'hui
            'today_bookings' => $this->getReservationCount($today, $today),
            'today_revenue' => $this->getRevenue($today, $today),
            
            // Réservations ce mois
            'month_bookings' => $this->getReservationCount($this_month_start, $today),
            'month_revenue' => $this->getRevenue($this_month_start, $today),
            
            // Comparaison avec le mois dernier
            'last_month_bookings' => $this->getReservationCount($last_month_start, $last_month_end),
            'last_month_revenue' => $this->getRevenue($last_month_start, $last_month_end),
            
            // Totaux généraux
            'total_bookings' => $this->getTotalReservations(),
            'total_revenue' => $this->getTotalRevenue(),
            'avg_booking_value' => $this->getAverageBookingValue(),
            
            // Statuts
            'pending_bookings' => $this->getReservationsByStatus(0),
            'confirmed_bookings' => $this->getReservationsByStatus(1),
            'paid_bookings' => $this->getReservationsByStatus(2),
            'cancelled_bookings' => $this->getReservationsByStatus(3),
            
            // Taux de conversion
            'conversion_rate' => $this->getConversionRate(),
            'cancellation_rate' => $this->getCancellationRate()
        );
    }
    
    /**
     * Données de revenus par période
     */
    private function getRevenueData()
    {
        $sql = 'SELECT 
                    DATE(date_reserved) as date,
                    COUNT(*) as bookings,
                    SUM(total_price) as revenue,
                    AVG(total_price) as avg_value
                FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`
                WHERE date_reserved >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND status IN (1, 2, 5)
                GROUP BY DATE(date_reserved)
                ORDER BY date ASC';
        
        return Db::getInstance()->executeS($sql) ?: array();
    }
    
    /**
     * Tendances des réservations
     */
    private function getBookingTrends()
    {
        $sql = 'SELECT 
                    HOUR(date_add) as hour,
                    DAYOFWEEK(date_reserved) as day_of_week,
                    COUNT(*) as count
                FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`
                WHERE date_reserved >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY HOUR(date_add), DAYOFWEEK(date_reserved)
                ORDER BY count DESC';
        
        return Db::getInstance()->executeS($sql) ?: array();
    }
    
    /**
     * Bookers les plus populaires
     */
    private function getPopularBookers()
    {
        $sql = 'SELECT 
                    b.id_booker,
                    b.name,
                    b.price,
                    COUNT(r.id_reserved) as booking_count,
                    SUM(r.total_price) as total_revenue,
                    AVG(r.total_price) as avg_price
                FROM `' . _DB_PREFIX_ . 'booker` b
                LEFT JOIN `' . _DB_PREFIX_ . 'booker_auth_reserved` r ON b.id_booker = r.id_booker
                WHERE r.date_reserved >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND r.status IN (1, 2, 5)
                GROUP BY b.id_booker
                ORDER BY booking_count DESC
                LIMIT 10';
        
        return Db::getInstance()->executeS($sql) ?: array();
    }
    
    /**
     * Statistiques clients
     */
    private function getCustomerStats()
    {
        return array(
            'total_customers' => $this->getTotalCustomers(),
            'repeat_customers' => $this->getRepeatCustomers(),
            'new_customers_month' => $this->getNewCustomersThisMonth(),
            'top_customers' => $this->getTopCustomers()
        );
    }
    
    /**
     * Métriques de performance
     */
    private function getPerformanceMetrics()
    {
        return array(
            'avg_booking_lead_time' => $this->getAverageBookingLeadTime(),
            'peak_booking_hours' => $this->getPeakBookingHours(),
            'seasonal_trends' => $this->getSeasonalTrends(),
            'booking_source_breakdown' => $this->getBookingSourceBreakdown()
        );
    }
    
    /**
     * Préparation des données pour les graphiques
     */
    private function prepareChartData($revenue_data, $booking_trends)
    {
        $chart_data = array(
            'revenue_chart' => array(
                'labels' => array(),
                'datasets' => array(
                    array(
                        'label' => 'Revenus',
                        'data' => array(),
                        'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                        'borderColor' => 'rgba(54, 162, 235, 1)',
                        'borderWidth' => 1
                    ),
                    array(
                        'label' => 'Réservations',
                        'data' => array(),
                        'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                        'borderColor' => 'rgba(255, 99, 132, 1)',
                        'borderWidth' => 1,
                        'yAxisID' => 'y1'
                    )
                )
            ),
            'hourly_distribution' => array(
                'labels' => array(),
                'data' => array()
            )
        );
        
        // Données de revenus
        foreach ($revenue_data as $day) {
            $chart_data['revenue_chart']['labels'][] = date('d/m', strtotime($day['date']));
            $chart_data['revenue_chart']['datasets'][0]['data'][] = floatval($day['revenue']);
            $chart_data['revenue_chart']['datasets'][1]['data'][] = intval($day['bookings']);
        }
        
        // Distribution horaire
        $hourly_counts = array_fill(0, 24, 0);
        foreach ($booking_trends as $trend) {
            $hourly_counts[intval($trend['hour'])] += intval($trend['count']);
        }
        
        for ($i = 0; $i < 24; $i++) {
            $chart_data['hourly_distribution']['labels'][] = sprintf('%02d:00', $i);
            $chart_data['hourly_distribution']['data'][] = $hourly_counts[$i];
        }
        
        return $chart_data;
    }
    
    /**
     * Vue par défaut si le template n'existe pas
     */
    private function generateDefaultStatsView()
    {
        $stats = $this->getGeneralStats();
        $popular_bookers = $this->getPopularBookers();
        
        $html = '
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-dashboard"></i> ' . $this->l('Tableau de bord des réservations') . '
            </div>
            <div class="panel-body">
                <!-- Métriques principales -->
                <div class="row">
                    <div class="col-lg-3 col-md-6">
                        <div class="alert alert-info text-center">
                            <i class="icon-calendar" style="font-size: 2em;"></i>
                            <h3>' . $stats['today_bookings'] . '</h3>
                            <p>Réservations aujourd\'hui</p>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="alert alert-success text-center">
                            <i class="icon-money" style="font-size: 2em;"></i>
                            <h3>' . number_format($stats['today_revenue'], 2) . '€</h3>
                            <p>Revenus aujourd\'hui</p>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="alert alert-warning text-center">
                            <i class="icon-time" style="font-size: 2em;"></i>
                            <h3>' . $stats['pending_bookings'] . '</h3>
                            <p>En attente</p>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="alert alert-primary text-center">
                            <i class="icon-check" style="font-size: 2em;"></i>
                            <h3>' . number_format($stats['conversion_rate'], 1) . '%</h3>
                            <p>Taux de conversion</p>
                        </div>
                    </div>
                </div>
                
                <!-- Comparaison mensuelle -->
                <div class="row">
                    <div class="col-lg-6">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h4>Ce mois vs Mois dernier</h4>
                            </div>
                            <div class="panel-body">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Métrique</th>
                                            <th>Ce mois</th>
                                            <th>Mois dernier</th>
                                            <th>Évolution</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Réservations</td>
                                            <td>' . $stats['month_bookings'] . '</td>
                                            <td>' . $stats['last_month_bookings'] . '</td>
                                            <td>' . $this->calculateEvolution($stats['month_bookings'], $stats['last_month_bookings']) . '</td>
                                        </tr>
                                        <tr>
                                            <td>Revenus</td>
                                            <td>' . number_format($stats['month_revenue'], 2) . '€</td>
                                            <td>' . number_format($stats['last_month_revenue'], 2) . '€</td>
                                            <td>' . $this->calculateEvolution($stats['month_revenue'], $stats['last_month_revenue']) . '</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h4>Éléments les plus réservés</h4>
                            </div>
                            <div class="panel-body">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Élément</th>
                                            <th>Réservations</th>
                                            <th>CA généré</th>
                                        </tr>
                                    </thead>
                                    <tbody>';
                                    
        foreach ($popular_bookers as $booker) {
            $html .= '
                                        <tr>
                                            <td>' . htmlspecialchars($booker['name']) . '</td>
                                            <td>' . $booker['booking_count'] . '</td>
                                            <td>' . number_format($booker['total_revenue'], 2) . '€</td>
                                        </tr>';
        }
        
        $html .= '
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Actions rapides -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="alert alert-info">
                            <strong>Actions rapides:</strong>
                            <a href="' . $this->context->link->getAdminLink('AdminBookerReservations') . '" class="btn btn-sm btn-primary">
                                <i class="icon-list"></i> Gérer les réservations
                            </a>
                            <a href="' . $this->context->link->getAdminLink('AdminBookerView') . '" class="btn btn-sm btn-success">
                                <i class="icon-calendar"></i> Vue calendrier
                            </a>
                            <a href="' . self::$currentIndex . '&action=exportStats&token=' . $this->token . '" class="btn btn-sm btn-info">
                                <i class="icon-download"></i> Exporter données
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
        
        return $html;
    }
    
    /**
     * Calculer l'évolution en pourcentage
     */
    private function calculateEvolution($current, $previous)
    {
        if ($previous == 0) {
            return $current > 0 ? '+∞%' : '0%';
        }
        
        $evolution = (($current - $previous) / $previous) * 100;
        $sign = $evolution >= 0 ? '+' : '';
        $class = $evolution >= 0 ? 'text-success' : 'text-danger';
        
        return '<span class="' . $class . '">' . $sign . number_format($evolution, 1) . '%</span>';
    }
    
    /**
     * Exporter les statistiques en CSV
     */
    private function exportStatsToCSV()
    {
        $stats = $this->getGeneralStats();
        $revenue_data = $this->getRevenueData();
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=booking_stats_' . date('Y-m-d') . '.csv');
        
        $output = fopen('php://output', 'w');
        
        // En-têtes
        fputcsv($output, array('Type', 'Métrique', 'Valeur'));
        
        // Statistiques générales
        fputcsv($output, array('Général', 'Réservations aujourd\'hui', $stats['today_bookings']));
        fputcsv($output, array('Général', 'Revenus aujourd\'hui', number_format($stats['today_revenue'], 2)));
        fputcsv($output, array('Général', 'Réservations ce mois', $stats['month_bookings']));
        fputcsv($output, array('Général', 'Revenus ce mois', number_format($stats['month_revenue'], 2)));
        fputcsv($output, array('Général', 'Total réservations', $stats['total_bookings']));
        fputcsv($output, array('Général', 'CA total', number_format($stats['total_revenue'], 2)));
        fputcsv($output, array('Général', 'Taux de conversion', number_format($stats['conversion_rate'], 2) . '%'));
        
        // Données quotidiennes
        fputcsv($output, array()); // Ligne vide
        fputcsv($output, array('Date', 'Réservations', 'Revenus'));
        
        foreach ($revenue_data as $day) {
            fputcsv($output, array(
                $day['date'],
                $day['bookings'],
                number_format($day['revenue'], 2)
            ));
        }
        
        fclose($output);
        exit;
    }
    
    // Méthodes utilitaires pour les statistiques
    private function getReservationCount($start_date, $end_date)
    {
        $sql = 'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` 
                WHERE date_reserved BETWEEN "' . pSQL($start_date) . '" AND "' . pSQL($end_date) . '"
                AND status IN (1, 2, 5)';
        return (int)Db::getInstance()->getValue($sql);
    }
    
    private function getRevenue($start_date, $end_date)
    {
        $sql = 'SELECT SUM(total_price) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` 
                WHERE date_reserved BETWEEN "' . pSQL($start_date) . '" AND "' . pSQL($end_date) . '"
                AND status IN (2, 5)';
        return (float)Db::getInstance()->getValue($sql) ?: 0;
    }
    
    private function getTotalReservations()
    {
        return (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`');
    }
    
    private function getTotalRevenue()
    {
        return (float)Db::getInstance()->getValue('SELECT SUM(total_price) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` WHERE status IN (2, 5)') ?: 0;
    }
    
    private function getAverageBookingValue()
    {
        return (float)Db::getInstance()->getValue('SELECT AVG(total_price) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` WHERE status IN (2, 5)') ?: 0;
    }
    
    private function getReservationsByStatus($status)
    {
        return (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` WHERE status = ' . (int)$status);
    }
    
    private function getConversionRate()
    {
        $total = $this->getTotalReservations();
        $paid = $this->getReservationsByStatus(2) + $this->getReservationsByStatus(5);
        return $total > 0 ? ($paid / $total) * 100 : 0;
    }
    
    private function getCancellationRate()
    {
        $total = $this->getTotalReservations();
        $cancelled = $this->getReservationsByStatus(3);
        return $total > 0 ? ($cancelled / $total) * 100 : 0;
    }
    
    private function getTotalCustomers()
    {
        return (int)Db::getInstance()->getValue('SELECT COUNT(DISTINCT customer_email) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`');
    }
    
    private function getRepeatCustomers()
    {
        $sql = 'SELECT COUNT(*) FROM (
                    SELECT customer_email FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` 
                    GROUP BY customer_email 
                    HAVING COUNT(*) > 1
                ) as repeat_customers';
        return (int)Db::getInstance()->getValue($sql);
    }
    
    private function getNewCustomersThisMonth()
    {
        $sql = 'SELECT COUNT(DISTINCT customer_email) FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` 
                WHERE date_add >= DATE_FORMAT(NOW(), "%Y-%m-01")';
        return (int)Db::getInstance()->getValue($sql);
    }
    
    private function getTopCustomers()
    {
        $sql = 'SELECT 
                    customer_email,
                    CONCAT(customer_firstname, " ", customer_lastname) as customer_name,
                    COUNT(*) as booking_count,
                    SUM(total_price) as total_spent
                FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`
                WHERE status IN (2, 5)
                GROUP BY customer_email
                ORDER BY total_spent DESC
                LIMIT 5';
        return Db::getInstance()->executeS($sql) ?: array();
    }
    
    private function getAverageBookingLeadTime()
    {
        $sql = 'SELECT AVG(DATEDIFF(date_reserved, date_add)) as avg_lead_time 
                FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`
                WHERE date_reserved > date_add';
        return (float)Db::getInstance()->getValue($sql) ?: 0;
    }
    
    private function getPeakBookingHours()
    {
        $sql = 'SELECT 
                    HOUR(date_add) as hour, 
                    COUNT(*) as count 
                FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`
                GROUP BY HOUR(date_add)
                ORDER BY count DESC
                LIMIT 3';
        return Db::getInstance()->executeS($sql) ?: array();
    }
    
    private function getSeasonalTrends()
    {
        $sql = 'SELECT 
                    MONTH(date_reserved) as month,
                    COUNT(*) as booking_count,
                    SUM(total_price) as revenue
                FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`
                WHERE date_reserved >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                GROUP BY MONTH(date_reserved)
                ORDER BY month';
        return Db::getInstance()->executeS($sql) ?: array();
    }
    
    private function getBookingSourceBreakdown()
    {
        // Placeholder - à implémenter selon les sources de réservation
        return array(
            'direct' => 75,
            'mobile_app' => 15,
            'partner' => 10
        );
    }
}