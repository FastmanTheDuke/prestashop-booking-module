<?php
/**
 * Script de nettoyage automatique des réservations expirées
 * À placer dans un cronjob pour exécution quotidienne
 * 
 * Exemple de cronjob : 0 2 * * * /usr/bin/php /path/to/prestashop/modules/quizz/cleanup_expired_reservations.php
 */

// Configuration
$prestashop_root = dirname(dirname(__DIR__));
require_once($prestashop_root . '/config/config.inc.php');
require_once(dirname(__FILE__) . '/classes/BookerAuthReserved.php');

// Vérifier que le script est exécuté en ligne de commande ou par un cronjob autorisé
if (!defined('_PS_VERSION_') || (isset($_SERVER['HTTP_HOST']) && !defined('CRON_ALLOWED'))) {
    exit('Accès interdit');
}

try {
    // Initialiser PrestaShop
    Context::getContext()->employee = new Employee(1); // Admin par défaut
    
    // Vérifier si le nettoyage automatique est activé
    if (!Configuration::get('QUIZZ_CRON_CLEAN_RESERVATIONS')) {
        echo "Nettoyage automatique désactivé.\n";
        exit(0);
    }
    
    // Nettoyer les réservations expirées (plus de 24h)
    $expiry_hours = (int)Configuration::get('QUIZZ_RESERVATION_EXPIRY_HOURS') ?: 24;
    $cleaned = BookerAuthReserved::cancelExpiredReservations($expiry_hours);
    
    if ($cleaned) {
        $message = "Nettoyage des réservations expirées effectué avec succès.";
        echo $message . "\n";
        
        // Log de l'action
        PrestaShopLogger::addLog(
            $message,
            1,
            null,
            'BookerAuthReserved',
            null,
            true
        );
    } else {
        echo "Aucune réservation expirée à nettoyer.\n";
    }
    
    // Statistiques post-nettoyage
    $stats = getReservationStats();
    echo "Statistiques actuelles :\n";
    foreach ($stats as $stat) {
        echo "- " . $stat['label'] . " : " . $stat['count'] . "\n";
    }
    
} catch (Exception $e) {
    $error_message = "Erreur lors du nettoyage des réservations expirées : " . $e->getMessage();
    echo $error_message . "\n";
    
    // Log de l'erreur
    PrestaShopLogger::addLog(
        $error_message,
        3,
        $e->getCode(),
        'BookerAuthReserved',
        null,
        true
    );
    
    exit(1);
}

/**
 * Obtenir les statistiques des réservations
 */
function getReservationStats()
{
    $stats = array();
    $statuses = BookerAuthReserved::getStatuses();
    
    foreach ($statuses as $status_id => $status_label) {
        $count = Db::getInstance()->getValue('
            SELECT COUNT(*) 
            FROM `' . _DB_PREFIX_ . 'booker_auth_reserved` 
            WHERE `status` = ' . (int)$status_id . ' 
            AND `active` = 1
        ');
        
        $stats[] = array(
            'label' => $status_label,
            'count' => (int)$count,
            'status_id' => $status_id
        );
    }
    
    return $stats;
}

/**
 * Fonction pour envoyer des notifications (à implémenter selon les besoins)
 */
function sendNotifications($expired_count)
{
    if ($expired_count > 0) {
        // Envoyer un email à l'admin si nécessaire
        $admin_email = Configuration::get('PS_SHOP_EMAIL');
        if ($admin_email && Configuration::get('QUIZZ_NOTIFY_ADMIN_CLEANUP')) {
            $subject = 'Nettoyage automatique des réservations expirées';
            $message = "Le nettoyage automatique a marqué {$expired_count} réservation(s) comme expirée(s).";
            
            Mail::Send(
                (int)Configuration::get('PS_LANG_DEFAULT'),
                'reservation_cleanup',
                $subject,
                array(
                    '{expired_count}' => $expired_count,
                    '{shop_name}' => Configuration::get('PS_SHOP_NAME')
                ),
                $admin_email,
                null,
                null,
                null,
                null,
                null,
                dirname(__FILE__) . '/mails/'
            );
        }
    }
}

exit(0);
?>