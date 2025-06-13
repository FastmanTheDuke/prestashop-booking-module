<?php
/**
 * Système de tâches automatisées pour le module de réservation
 * Gestion des tâches cron : nettoyage, notifications, statistiques
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once(dirname(__FILE__) . '/BookerAuthReserved.php');
require_once(dirname(__FILE__) . '/BookingNotificationSystem.php');

class BookingCronSystem
{
    private $module;
    private $context;
    private $notification_system;
    
    // Fréquences des tâches (en secondes)
    const FREQUENCY_HOURLY = 3600;
    const FREQUENCY_DAILY = 86400;
    const FREQUENCY_WEEKLY = 604800;
    const FREQUENCY_MONTHLY = 2592000;
    
    public function __construct($module = null)
    {
        $this->module = $module;
        $this->context = Context::getContext();
        $this->notification_system = new BookingNotificationSystem($module);
    }
    
    /**
     * Exécuter toutes les tâches cron
     */
    public function runAllTasks()
    {
        $results = [];
        
        try {
            // Vérifier si les tâches cron sont activées
            if (!Configuration::get('BOOKING_CRON_ENABLED', 1)) {
                return ['status' => 'disabled', 'message' => 'Tâches cron désactivées'];
            }
            
            // Tâches horaires
            if ($this->shouldRunTask('hourly', self::FREQUENCY_HOURLY)) {
                $results['hourly'] = $this->runHourlyTasks();
                $this->updateLastRun('hourly');
            }
            
            // Tâches quotidiennes
            if ($this->shouldRunTask('daily', self::FREQUENCY_DAILY)) {
                $results['daily'] = $this->runDailyTasks();
                $this->updateLastRun('daily');
            }
            
            // Tâches hebdomadaires
            if ($this->shouldRunTask('weekly', self::FREQUENCY_WEEKLY)) {
                $results['weekly'] = $this->runWeeklyTasks();
                $this->updateLastRun('weekly');
            }
            
            // Tâches mensuelles
            if ($this->shouldRunTask('monthly', self::FREQUENCY_MONTHLY)) {
                $results['monthly'] = $this->runMonthlyTasks();
                $this->updateLastRun('monthly');
            }
            
            PrestaShopLogger::addLog(
                'Tâches cron exécutées avec succès: ' . json_encode($results),
                1,
                null,
                'BookingCronSystem'
            );
            
            return ['status' => 'success', 'results' => $results];
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'Erreur lors de l\'exécution des tâches cron: ' . $e->getMessage(),
                3,
                null,
                'BookingCronSystem'
            );
            
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Exécuter les tâches horaires
     */
    private function runHourlyTasks()
    {
        $results = [];
        
        try {
            // 1. Marquer les réservations expirées
            if (Configuration::get('BOOKING_AUTO_EXPIRE_ENABLED', 1)) {
                $expired_count = $this->markExpiredReservations();
                $results['expired_reservations'] = $expired_count;
            }
            
            // 2. Nettoyer les sessions de paiement expirées
            if (Configuration::get('BOOKING_CLEAN_SESSIONS_ENABLED', 1)) {
                $cleaned_sessions = $this->cleanExpiredPaymentSessions();
                $results['cleaned_sessions'] = $cleaned_sessions;
            }
            
            // 3. Vérifier les réservations à venir (rappels)
            if (Configuration::get('BOOKING_UPCOMING_REMINDERS_ENABLED', 0)) {
                $reminders_sent = $this->sendUpcomingReminders();
                $results['reminders_sent'] = $reminders_sent;
            }
            
            return $results;
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'Erreur tâches horaires: ' . $e->getMessage(),
                3,
                null,
                'BookingCronSystem'
            );
            throw $e;
        }
    }
    
    /**
     * Exécuter les tâches quotidiennes
     */
    private function runDailyTasks()
    {
        $results = [];
        
        try {
            // 1. Envoyer le résumé quotidien
            if (Configuration::get('BOOKING_DAILY_SUMMARY_ENABLED', 0)) {
                $summary_sent = $this->notification_system->sendDailySummary();
                $results['daily_summary'] = $summary_sent;
            }
            
            // 2. Nettoyer les anciennes réservations annulées
            if (Configuration::get('BOOKING_CLEAN_OLD_CANCELLED_ENABLED', 1)) {
                $cleaned_cancelled = $this->cleanOldCancelledReservations();
                $results['cleaned_cancelled'] = $cleaned_cancelled;
            }
            
            // 3. Archiver les anciennes réservations
            if (Configuration::get('BOOKING_ARCHIVE_OLD_ENABLED', 0)) {
                $archived_count = $this->archiveOldReservations();
                $results['archived_reservations'] = $archived_count;
            }
            
            // 4. Optimiser les tables de base de données
            if (Configuration::get('BOOKING_OPTIMIZE_DB_ENABLED', 0)) {
                $optimized = $this->optimizeDatabaseTables();
                $results['db_optimization'] = $optimized;
            }
            
            // 5. Générer les statistiques quotidiennes
            if (Configuration::get('BOOKING_GENERATE_STATS_ENABLED', 1)) {
                $stats_generated = $this->generateDailyStatistics();
                $results['stats_generated'] = $stats_generated;
            }
            
            return $results;
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'Erreur tâches quotidiennes: ' . $e->getMessage(),
                3,
                null,
                'BookingCronSystem'
            );
            throw $e;
        }
    }
    
    /**
     * Exécuter les tâches hebdomadaires
     */
    private function runWeeklyTasks()
    {
        $results = [];
        
        try {
            // 1. Envoyer le résumé hebdomadaire
            if (Configuration::get('BOOKING_WEEKLY_SUMMARY_ENABLED', 0)) {
                $summary_sent = $this->notification_system->sendWeeklySummary();
                $results['weekly_summary'] = $summary_sent;
            }
            
            // 2. Analyser les tendances de réservation
            if (Configuration::get('BOOKING_TREND_ANALYSIS_ENABLED', 0)) {
                $trends_analyzed = $this->analyzeTrends();
                $results['trend_analysis'] = $trends_analyzed;
            }
            
            // 3. Vérifier la santé du système
            if (Configuration::get('BOOKING_HEALTH_CHECK_ENABLED', 1)) {
                $health_check = $this->performHealthCheck();
                $results['health_check'] = $health_check;
            }
            
            // 4. Sauvegarder les configurations critiques
            if (Configuration::get('BOOKING_BACKUP_CONFIG_ENABLED', 0)) {
                $backup_created = $this->backupCriticalConfig();
                $results['config_backup'] = $backup_created;
            }
            
            return $results;
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'Erreur tâches hebdomadaires: ' . $e->getMessage(),
                3,
                null,
                'BookingCronSystem'
            );
            throw $e;
        }
    }
    
    /**
     * Exécuter les tâches mensuelles
     */
    private function runMonthlyTasks()
    {
        $results = [];
        
        try {
            // 1. Générer le rapport mensuel
            if (Configuration::get('BOOKING_MONTHLY_REPORT_ENABLED', 0)) {
                $report_generated = $this->generateMonthlyReport();
                $results['monthly_report'] = $report_generated;
            }
            
            // 2. Nettoyer les logs anciens
            if (Configuration::get('BOOKING_CLEAN_OLD_LOGS_ENABLED', 1)) {
                $logs_cleaned = $this->cleanOldLogs();
                $results['logs_cleaned'] = $logs_cleaned;
            }
            
            // 3. Optimiser les performances
            if (Configuration::get('BOOKING_PERFORMANCE_OPTIMIZATION_ENABLED', 0)) {
                $optimizations = $this->performanceOptimization();
                $results['performance_optimization'] = $optimizations;
            }
            
            return $results;
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'Erreur tâches mensuelles: ' . $e->getMessage(),
                3,
                null,
                'BookingCronSystem'
            );
            throw $e;
        }
    }
    
    /**
     * Marquer les réservations expirées
     */
    private function markExpiredReservations()
    {
        return BookerAuthReserved::markExpiredReservations();
    }
    
    /**
     * Nettoyer les sessions de paiement expirées
     */
    private function cleanExpiredPaymentSessions()
    {
        $expiry_hours = Configuration::get('BOOKING_SESSION_EXPIRY_HOURS', 2);
        
        // Nettoyer les données de session expirées
        $count = Db::getInstance()->execute('
            DELETE FROM `' . _DB_PREFIX_ . 'booking_payment_sessions`
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ' . (int)$expiry_hours . ' HOUR)
        ');
        
        return $count;
    }
    
    /**
     * Envoyer des rappels pour les réservations à venir
     */
    private function sendUpcomingReminders()
    {
        $reminder_hours = Configuration::get('BOOKING_REMINDER_HOURS', 24);
        
        // Récupérer les réservations dans les prochaines heures
        $upcoming_reservations = Db::getInstance()->executeS('
            SELECT r.*, b.name as booker_name
            FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` r
            LEFT JOIN `' . _DB_PREFIX_ . 'booker` b ON (r.id_booker = b.id_booker)
            WHERE r.status IN (' . BookerAuthReserved::STATUS_ACCEPTED . ', ' . BookerAuthReserved::STATUS_PAID . ')
            AND r.active = 1
            AND CONCAT(r.date_reserved, " ", LPAD(r.hour_from, 2, "0"), ":00:00") 
                BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL ' . (int)$reminder_hours . ' HOUR)
            AND r.reminder_sent = 0
        ');
        
        $sent_count = 0;
        
        foreach ($upcoming_reservations as $reservation_data) {
            try {
                // Envoyer le rappel par email
                $result = $this->sendReminderEmail($reservation_data);
                
                if ($result) {
                    // Marquer le rappel comme envoyé
                    Db::getInstance()->execute('
                        UPDATE `' . _DB_PREFIX_ . 'booker_auth_reserved`
                        SET reminder_sent = 1
                        WHERE id_reserved = ' . (int)$reservation_data['id_reserved']
                    );
                    
                    $sent_count++;
                }
                
            } catch (Exception $e) {
                PrestaShopLogger::addLog(
                    'Erreur envoi rappel réservation ' . $reservation_data['booking_reference'] . ': ' . $e->getMessage(),
                    2,
                    null,
                    'BookingCronSystem'
                );
            }
        }
        
        return $sent_count;
    }
    
    /**
     * Nettoyer les anciennes réservations annulées
     */
    private function cleanOldCancelledReservations()
    {
        $retention_days = Configuration::get('BOOKING_CANCELLED_RETENTION_DAYS', 90);
        
        $count = Db::getInstance()->execute('
            DELETE FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`
            WHERE status = ' . BookerAuthReserved::STATUS_CANCELLED . '
            AND date_upd < DATE_SUB(NOW(), INTERVAL ' . (int)$retention_days . ' DAY)
        ');
        
        return $count;
    }
    
    /**
     * Archiver les anciennes réservations
     */
    private function archiveOldReservations()
    {
        $archive_days = Configuration::get('BOOKING_ARCHIVE_AFTER_DAYS', 365);
        
        // Créer la table d'archive si elle n'existe pas
        $this->createArchiveTable();
        
        // Copier les anciennes réservations vers l'archive
        $archived = Db::getInstance()->execute('
            INSERT INTO `' . _DB_PREFIX_ . 'booker_auth_reserved_archive`
            SELECT *, NOW() as archived_at
            FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`
            WHERE date_reserved < DATE_SUB(NOW(), INTERVAL ' . (int)$archive_days . ' DAY)
            AND status IN (' . BookerAuthReserved::STATUS_PAID . ', ' . BookerAuthReserved::STATUS_CANCELLED . ')
        ');
        
        if ($archived) {
            // Supprimer les réservations archivées de la table principale
            Db::getInstance()->execute('
                DELETE FROM `' . _DB_PREFIX_ . 'booker_auth_reserved`
                WHERE date_reserved < DATE_SUB(NOW(), INTERVAL ' . (int)$archive_days . ' DAY)
                AND status IN (' . BookerAuthReserved::STATUS_PAID . ', ' . BookerAuthReserved::STATUS_CANCELLED . ')
            ');
        }
        
        return $archived;
    }
    
    /**
     * Optimiser les tables de base de données
     */
    private function optimizeDatabaseTables()
    {
        $tables = [
            _DB_PREFIX_ . 'booker_auth_reserved',
            _DB_PREFIX_ . 'booker_auth',
            _DB_PREFIX_ . 'booker'
        ];
        
        $optimized = 0;
        
        foreach ($tables as $table) {
            try {
                Db::getInstance()->execute('OPTIMIZE TABLE `' . $table . '`');
                $optimized++;
            } catch (Exception $e) {
                PrestaShopLogger::addLog(
                    'Erreur optimisation table ' . $table . ': ' . $e->getMessage(),
                    2,
                    null,
                    'BookingCronSystem'
                );
            }
        }
        
        return $optimized;
    }
    
    /**
     * Générer les statistiques quotidiennes
     */
    private function generateDailyStatistics()
    {
        try {
            $stats = BookerAuthReserved::getReservationStats('today');
            
            // Sauvegarder les stats dans une table dédiée
            $this->saveDailyStats(date('Y-m-d'), $stats);
            
            return true;
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'Erreur génération statistiques: ' . $e->getMessage(),
                3,
                null,
                'BookingCronSystem'
            );
            return false;
        }
    }
    
    /**
     * Analyser les tendances
     */
    private function analyzeTrends()
    {
        try {
            // Analyser les tendances de réservation sur 4 semaines
            $trends = [];
            
            for ($i = 0; $i < 4; $i++) {
                $week_start = date('Y-m-d', strtotime("-$i weeks monday"));
                $week_end = date('Y-m-d', strtotime("-$i weeks sunday"));
                
                $week_stats = $this->getWeekStats($week_start, $week_end);
                $trends[] = $week_stats;
            }
            
            // Sauvegarder l'analyse des tendances
            $this->saveTrendAnalysis($trends);
            
            return true;
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'Erreur analyse tendances: ' . $e->getMessage(),
                3,
                null,
                'BookingCronSystem'
            );
            return false;
        }
    }
    
    /**
     * Vérifier la santé du système
     */
    private function performHealthCheck()
    {
        $checks = [];
        
        try {
            // 1. Vérifier la connectivité base de données
            $checks['database'] = $this->checkDatabaseHealth();
            
            // 2. Vérifier les permissions de fichiers
            $checks['file_permissions'] = $this->checkFilePermissions();
            
            // 3. Vérifier l'espace disque
            $checks['disk_space'] = $this->checkDiskSpace();
            
            // 4. Vérifier les configurations critiques
            $checks['configuration'] = $this->checkCriticalConfiguration();
            
            // 5. Vérifier l'intégration Stripe
            $checks['stripe_integration'] = $this->checkStripeIntegration();
            
            // Si des problèmes sont détectés, envoyer une alerte
            $issues = array_filter($checks, function($check) {
                return $check['status'] !== 'ok';
            });
            
            if (!empty($issues)) {
                $this->sendHealthCheckAlert($issues);
            }
            
            return $checks;
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'Erreur vérification santé système: ' . $e->getMessage(),
                3,
                null,
                'BookingCronSystem'
            );
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Sauvegarder la configuration critique
     */
    private function backupCriticalConfig()
    {
        try {
            $critical_configs = [
                'BOOKING_STRIPE_ENABLED',
                'BOOKING_ADMIN_EMAILS',
                'BOOKING_AUTO_CONFIRM',
                'BOOKING_EXPIRY_HOURS',
                'BOOKING_DEFAULT_PRICE',
                'BOOKING_DEPOSIT_AMOUNT'
            ];
            
            $backup_data = [];
            foreach ($critical_configs as $config) {
                $backup_data[$config] = Configuration::get($config);
            }
            
            $backup_file = _PS_MODULE_DIR_ . 'booking/backups/config_' . date('Y-m-d_H-i-s') . '.json';
            
            // Créer le dossier de sauvegarde s'il n'existe pas
            $backup_dir = dirname($backup_file);
            if (!is_dir($backup_dir)) {
                mkdir($backup_dir, 0755, true);
            }
            
            file_put_contents($backup_file, json_encode($backup_data, JSON_PRETTY_PRINT));
            
            // Nettoyer les anciennes sauvegardes (garder seulement les 10 dernières)
            $this->cleanOldBackups($backup_dir);
            
            return true;
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'Erreur sauvegarde configuration: ' . $e->getMessage(),
                3,
                null,
                'BookingCronSystem'
            );
            return false;
        }
    }
    
    /**
     * Générer le rapport mensuel
     */
    private function generateMonthlyReport()
    {
        try {
            $report_data = [
                'period' => date('Y-m'),
                'stats' => BookerAuthReserved::getReservationStats('month'),
                'generated_at' => date('Y-m-d H:i:s')
            ];
            
            $report_file = _PS_MODULE_DIR_ . 'booking/reports/monthly_' . date('Y-m') . '.json';
            
            // Créer le dossier de rapports s'il n'existe pas
            $report_dir = dirname($report_file);
            if (!is_dir($report_dir)) {
                mkdir($report_dir, 0755, true);
            }
            
            file_put_contents($report_file, json_encode($report_data, JSON_PRETTY_PRINT));
            
            return true;
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'Erreur génération rapport mensuel: ' . $e->getMessage(),
                3,
                null,
                'BookingCronSystem'
            );
            return false;
        }
    }
    
    /**
     * Nettoyer les anciens logs
     */
    private function cleanOldLogs()
    {
        $retention_days = Configuration::get('BOOKING_LOG_RETENTION_DAYS', 90);
        
        $count = Db::getInstance()->execute('
            DELETE FROM `' . _DB_PREFIX_ . 'log`
            WHERE object_type = "BookingCronSystem"
            AND date_add < DATE_SUB(NOW(), INTERVAL ' . (int)$retention_days . ' DAY)
        ');
        
        return $count;
    }
    
    /**
     * Optimisations de performance
     */
    private function performanceOptimization()
    {
        $optimizations = [];
        
        try {
            // 1. Reconstruire les index
            $optimizations['indexes_rebuilt'] = $this->rebuildIndexes();
            
            // 2. Nettoyer le cache
            $optimizations['cache_cleared'] = $this->clearBookingCache();
            
            // 3. Optimiser les requêtes lentes
            $optimizations['slow_queries_optimized'] = $this->optimizeSlowQueries();
            
            return $optimizations;
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'Erreur optimisation performance: ' . $e->getMessage(),
                3,
                null,
                'BookingCronSystem'
            );
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Vérifier si une tâche doit être exécutée
     */
    private function shouldRunTask($type, $frequency)
    {
        $last_run = Configuration::get('BOOKING_CRON_LAST_RUN_' . strtoupper($type), 0);
        return (time() - $last_run) >= $frequency;
    }
    
    /**
     * Mettre à jour l'heure de dernière exécution
     */
    private function updateLastRun($type)
    {
        Configuration::updateValue('BOOKING_CRON_LAST_RUN_' . strtoupper($type), time());
    }
    
    /**
     * Créer la table d'archive
     */
    private function createArchiveTable()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'booker_auth_reserved_archive` LIKE `' . _DB_PREFIX_ . 'booker_auth_reserved`';
        Db::getInstance()->execute($sql);
        
        $sql = 'ALTER TABLE `' . _DB_PREFIX_ . 'booker_auth_reserved_archive` ADD COLUMN IF NOT EXISTS `archived_at` DATETIME';
        Db::getInstance()->execute($sql);
    }
    
    /**
     * Sauvegarder les statistiques quotidiennes
     */
    private function saveDailyStats($date, $stats)
    {
        $data = json_encode($stats);
        
        Db::getInstance()->execute('
            INSERT INTO `' . _DB_PREFIX_ . 'booking_daily_stats` (date, stats_data, created_at)
            VALUES ("' . pSQL($date) . '", "' . pSQL($data) . '", NOW())
            ON DUPLICATE KEY UPDATE stats_data = "' . pSQL($data) . '"
        ');
    }
    
    /**
     * Envoyer un email de rappel
     */
    private function sendReminderEmail($reservation_data)
    {
        $templateVars = [
            '{booking_reference}' => $reservation_data['booking_reference'],
            '{customer_firstname}' => $reservation_data['customer_firstname'],
            '{customer_lastname}' => $reservation_data['customer_lastname'],
            '{booker_name}' => $reservation_data['booker_name'],
            '{reservation_date}' => date('d/m/Y', strtotime($reservation_data['date_reserved'])),
            '{reservation_time}' => $reservation_data['hour_from'] . 'h - ' . $reservation_data['hour_to'] . 'h',
            '{shop_name}' => Configuration::get('PS_SHOP_NAME')
        ];
        
        return Mail::Send(
            $this->context->language->id,
            'booking_reminder',
            'Rappel de votre réservation - ' . $reservation_data['booking_reference'],
            $templateVars,
            $reservation_data['customer_email'],
            $reservation_data['customer_firstname'] . ' ' . $reservation_data['customer_lastname'],
            Configuration::get('PS_SHOP_EMAIL'),
            Configuration::get('PS_SHOP_NAME'),
            null,
            null,
            dirname(__FILE__) . '/../mails/'
        );
    }
    
    /**
     * Méthodes auxiliaires pour les vérifications de santé
     */
    private function checkDatabaseHealth()
    {
        try {
            Db::getInstance()->getValue('SELECT 1');
            return ['status' => 'ok', 'message' => 'Base de données accessible'];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => 'Erreur base de données: ' . $e->getMessage()];
        }
    }
    
    private function checkFilePermissions()
    {
        $critical_dirs = [
            _PS_MODULE_DIR_ . 'booking/mails/',
            _PS_MODULE_DIR_ . 'booking/backups/',
            _PS_MODULE_DIR_ . 'booking/reports/'
        ];
        
        foreach ($critical_dirs as $dir) {
            if (!is_writable($dir)) {
                return ['status' => 'warning', 'message' => 'Permissions insuffisantes: ' . $dir];
            }
        }
        
        return ['status' => 'ok', 'message' => 'Permissions correctes'];
    }
    
    private function checkDiskSpace()
    {
        $free_space = disk_free_space(_PS_ROOT_DIR_);
        $total_space = disk_total_space(_PS_ROOT_DIR_);
        $used_percent = (($total_space - $free_space) / $total_space) * 100;
        
        if ($used_percent > 90) {
            return ['status' => 'error', 'message' => 'Espace disque critique: ' . round($used_percent, 2) . '%'];
        } elseif ($used_percent > 80) {
            return ['status' => 'warning', 'message' => 'Espace disque faible: ' . round($used_percent, 2) . '%'];
        }
        
        return ['status' => 'ok', 'message' => 'Espace disque suffisant: ' . round($used_percent, 2) . '%'];
    }
    
    private function checkCriticalConfiguration()
    {
        $critical_configs = [
            'BOOKING_STRIPE_ENABLED',
            'BOOKING_ADMIN_EMAILS'
        ];
        
        foreach ($critical_configs as $config) {
            if (!Configuration::get($config)) {
                return ['status' => 'warning', 'message' => 'Configuration manquante: ' . $config];
            }
        }
        
        return ['status' => 'ok', 'message' => 'Configuration complète'];
    }
    
    private function checkStripeIntegration()
    {
        if (!Configuration::get('BOOKING_STRIPE_ENABLED')) {
            return ['status' => 'info', 'message' => 'Stripe désactivé'];
        }
        
        try {
            $stripe_payment = new StripeBookingPayment();
            return ['status' => 'ok', 'message' => 'Stripe fonctionnel'];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => 'Erreur Stripe: ' . $e->getMessage()];
        }
    }
    
    // ... autres méthodes auxiliaires ...
    
    /**
     * Point d'entrée public pour l'exécution via URL
     */
    public static function executeFromUrl()
    {
        // Vérifier le token de sécurité
        $token = Tools::getValue('token');
        $expected_token = Configuration::get('BOOKING_CRON_TOKEN');
        
        if (!$expected_token || $token !== $expected_token) {
            die('Token invalide');
        }
        
        $cron = new self();
        $results = $cron->runAllTasks();
        
        header('Content-Type: application/json');
        echo json_encode($results);
        exit;
    }
}