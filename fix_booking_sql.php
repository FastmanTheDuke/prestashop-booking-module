<?php
/**
 * Script de correction des erreurs SQL du module Booking
 * À exécuter si vous avez des erreurs de colonnes manquantes
 * 
 * UTILISATION:
 * 1. Copiez ce fichier dans le dossier racine de PrestaShop
 * 2. Exécutez-le via votre navigateur : http://votre-site.com/fix_booking_sql.php
 * 3. Supprimez le fichier après utilisation pour des raisons de sécurité
 */

// Configuration - MODIFIEZ CES VALEURS SELON VOTRE INSTALLATION
define('_DB_SERVER_', 'localhost');
define('_DB_NAME_', 'votre_base_de_donnees');  // CHANGEZ ICI
define('_DB_USER_', 'votre_utilisateur');       // CHANGEZ ICI  
define('_DB_PASSWD_', 'votre_mot_de_passe');    // CHANGEZ ICI
define('_DB_PREFIX_', 'ps_');                   // CHANGEZ SI NÉCESSAIRE
define('_MYSQL_ENGINE_', 'InnoDB');

// Sécurité de base
if (isset($_GET['execute']) && $_GET['execute'] === 'fix') {
    $execute_fixes = true;
} else {
    $execute_fixes = false;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Fix Booking Module SQL Errors</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .success { color: green; background: #dfd; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: red; background: #fdd; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .warning { color: orange; background: #ffd; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { color: blue; background: #ddf; padding: 10px; border-radius: 5px; margin: 10px 0; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto; }
        .btn { display: inline-block; padding: 10px 20px; background: #007cba; color: white; text-decoration: none; border-radius: 5px; }
        .btn:hover { background: #005a87; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Booking Module SQL Errors Fix</h1>
        
        <div class="warning">
            <strong>⚠️ ATTENTION :</strong> Ce script va modifier votre base de données. 
            Faites une sauvegarde complète avant de continuer !
        </div>

<?php
if (!$execute_fixes) {
?>
        <div class="info">
            <h3>📋 Ce script va corriger les erreurs suivantes :</h3>
            <ul>
                <li><strong>Unknown column 'b.id_booker' in 'on clause'</strong> → Vérification/correction de la structure de la table booker</li>
                <li><strong>Unknown column 'a.date_reserved' in 'order clause'</strong> → Vérification/correction de la structure de la table booker_auth_reserved</li>
                <li><strong>Unknown column 'a.id_booker' in 'order clause'</strong> → Vérification/correction de la table booker</li>
            </ul>
        </div>

        <h3>🔍 Première étape : Diagnostic</h3>
        <p>Cliquez sur le bouton ci-dessous pour diagnostiquer les problèmes :</p>
        <a href="?execute=diagnose" class="btn">Diagnostiquer les problèmes</a>
        
        <hr style="margin: 30px 0;">
        
        <h3>⚡ Correction automatique</h3>
        <p><strong>Après avoir fait une sauvegarde</strong>, cliquez pour appliquer les corrections :</p>
        <a href="?execute=fix" class="btn btn-danger">🚨 CORRIGER LES ERREURS SQL</a>

<?php
} else {
    // Connexion à la base de données
    try {
        $pdo = new PDO(
            'mysql:host=' . _DB_SERVER_ . ';dbname=' . _DB_NAME_ . ';charset=utf8',
            _DB_USER_,
            _DB_PASSWD_,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        echo '<div class="success">✅ Connexion à la base de données réussie</div>';
        
        // Liste des corrections à appliquer
        $fixes = [
            // 1. Vérifier/corriger la table booker
            [
                'name' => 'Table booker - Structure complète',
                'check' => "SHOW COLUMNS FROM `" . _DB_PREFIX_ . "booker` LIKE 'id_booker'",
                'sql' => "
                CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "booker` (
                    `id_booker` int(11) NOT NULL AUTO_INCREMENT,
                    `id_product` int(11) DEFAULT NULL,
                    `name` varchar(255) NOT NULL,
                    `description` text,
                    `location` varchar(255) DEFAULT NULL,
                    `price` decimal(10,2) DEFAULT 0.00,
                    `capacity` int(11) DEFAULT 1,
                    `booking_duration` int(11) DEFAULT 60,
                    `min_booking_time` int(11) DEFAULT 24,
                    `max_booking_days` int(11) DEFAULT 30,
                    `deposit_required` tinyint(1) DEFAULT 0,
                    `deposit_amount` decimal(10,2) DEFAULT 0.00,
                    `auto_confirm` tinyint(1) DEFAULT 0,
                    `google_account` varchar(255) DEFAULT NULL,
                    `active` tinyint(1) DEFAULT 1,
                    `date_add` datetime NOT NULL,
                    `date_upd` datetime NOT NULL,
                    PRIMARY KEY (`id_booker`),
                    KEY `idx_product` (`id_product`),
                    KEY `idx_active` (`active`)
                ) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=utf8"
            ],
            
            // 2. Vérifier/corriger la table booker_auth
            [
                'name' => 'Table booker_auth - Structure complète',
                'check' => "SHOW COLUMNS FROM `" . _DB_PREFIX_ . "booker_auth` LIKE 'time_from'",
                'sql' => "
                CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "booker_auth` (
                    `id_auth` int(11) NOT NULL AUTO_INCREMENT,
                    `id_booker` int(11) NOT NULL,
                    `date_from` datetime NOT NULL,
                    `date_to` datetime NOT NULL,
                    `time_from` time NOT NULL,
                    `time_to` time NOT NULL,
                    `max_bookings` int(11) DEFAULT 1,
                    `current_bookings` int(11) DEFAULT 0,
                    `price_override` decimal(10,2) DEFAULT NULL,
                    `active` tinyint(1) DEFAULT 1,
                    `recurring` tinyint(1) DEFAULT 0,
                    `recurring_type` enum('daily','weekly','monthly') DEFAULT NULL,
                    `recurring_end` date DEFAULT NULL,
                    `notes` text,
                    `date_add` datetime NOT NULL,
                    `date_upd` datetime NOT NULL,
                    PRIMARY KEY (`id_auth`),
                    KEY `idx_booker` (`id_booker`),
                    KEY `idx_date_range` (`date_from`, `date_to`),
                    KEY `idx_active` (`active`)
                ) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=utf8"
            ],
            
            // 3. Vérifier/corriger la table booker_auth_reserved
            [
                'name' => 'Table booker_auth_reserved - Structure complète',
                'check' => "SHOW COLUMNS FROM `" . _DB_PREFIX_ . "booker_auth_reserved` LIKE 'date_reserved'",
                'sql' => "
                CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "booker_auth_reserved` (
                    `id_reserved` int(11) NOT NULL AUTO_INCREMENT,
                    `id_auth` int(11) NOT NULL,
                    `id_booker` int(11) NOT NULL,
                    `id_customer` int(11) DEFAULT NULL,
                    `id_order` int(11) DEFAULT NULL,
                    `booking_reference` varchar(50) NOT NULL,
                    `customer_firstname` varchar(100) NOT NULL,
                    `customer_lastname` varchar(100) NOT NULL,
                    `customer_email` varchar(150) NOT NULL,
                    `customer_phone` varchar(50) DEFAULT NULL,
                    `date_reserved` date NOT NULL,
                    `date_to` date DEFAULT NULL,
                    `hour_from` int(11) NOT NULL,
                    `hour_to` int(11) NOT NULL,
                    `total_price` decimal(10,2) DEFAULT 0.00,
                    `deposit_paid` decimal(10,2) DEFAULT 0.00,
                    `status` int(11) DEFAULT 0,
                    `payment_status` enum('pending','authorized','captured','cancelled','refunded') DEFAULT 'pending',
                    `stripe_payment_intent_id` varchar(255) DEFAULT NULL,
                    `stripe_deposit_intent_id` varchar(255) DEFAULT NULL,
                    `notes` text,
                    `admin_notes` text,
                    `date_expiry` datetime DEFAULT NULL,
                    `date_add` datetime NOT NULL,
                    `date_upd` datetime NOT NULL,
                    PRIMARY KEY (`id_reserved`),
                    UNIQUE KEY `idx_reference` (`booking_reference`),
                    KEY `idx_auth` (`id_auth`),
                    KEY `idx_booker` (`id_booker`),
                    KEY `idx_customer` (`id_customer`),
                    KEY `idx_order` (`id_order`),
                    KEY `idx_status` (`status`),
                    KEY `idx_date_range` (`date_reserved`, `date_to`)
                ) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=utf8"
            ]
        ];
        
        echo '<h3>🔧 Application des corrections :</h3>';
        
        $success_count = 0;
        $error_count = 0;
        
        foreach ($fixes as $fix) {
            echo '<h4>🛠️ ' . $fix['name'] . '</h4>';
            
            try {
                // Vérifier si la correction est nécessaire
                $check_result = $pdo->query($fix['check']);
                $needs_fix = $check_result->rowCount() == 0;
                
                if ($needs_fix) {
                    // Appliquer la correction
                    $pdo->exec($fix['sql']);
                    echo '<div class="success">✅ Correction appliquée avec succès</div>';
                    $success_count++;
                } else {
                    echo '<div class="info">ℹ️ Structure déjà correcte, aucune correction nécessaire</div>';
                }
                
            } catch (PDOException $e) {
                echo '<div class="error">❌ Erreur : ' . $e->getMessage() . '</div>';
                $error_count++;
            }
        }
        
        // Résumé final
        echo '<hr>';
        echo '<h3>📊 Résumé des corrections :</h3>';
        echo '<div class="' . ($error_count > 0 ? 'warning' : 'success') . '">';
        echo '<strong>Corrections appliquées :</strong> ' . $success_count . '<br>';
        echo '<strong>Erreurs rencontrées :</strong> ' . $error_count;
        echo '</div>';
        
        if ($error_count == 0) {
            echo '<div class="success">';
            echo '<h4>🎉 Toutes les corrections ont été appliquées avec succès !</h4>';
            echo '<p>Vous pouvez maintenant :</p>';
            echo '<ol>';
            echo '<li>Retourner dans votre interface d\'administration PrestaShop</li>';
            echo '<li>Aller dans le menu "Réservations"</li>';
            echo '<li>Vérifier que les listes s\'affichent correctement</li>';
            echo '<li><strong>Supprimer ce fichier</strong> pour des raisons de sécurité</li>';
            echo '</ol>';
            echo '</div>';
        }
        
    } catch (PDOException $e) {
        echo '<div class="error">❌ Erreur de connexion à la base de données : ' . $e->getMessage() . '</div>';
        echo '<div class="warning">Vérifiez vos paramètres de connexion en haut de ce fichier.</div>';
    }
}

// Mode diagnostic
if (isset($_GET['execute']) && $_GET['execute'] === 'diagnose') {
    echo '<h3>🔍 Diagnostic des tables</h3>';
    
    try {
        $pdo = new PDO(
            'mysql:host=' . _DB_SERVER_ . ';dbname=' . _DB_NAME_ . ';charset=utf8',
            _DB_USER_,
            _DB_PASSWD_,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        $tables_to_check = [
            _DB_PREFIX_ . 'booker',
            _DB_PREFIX_ . 'booker_auth', 
            _DB_PREFIX_ . 'booker_auth_reserved'
        ];
        
        foreach ($tables_to_check as $table) {
            echo '<h4>📋 Table : ' . $table . '</h4>';
            
            try {
                $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($columns) > 0) {
                    echo '<div class="success">✅ Table existe (' . count($columns) . ' colonnes)</div>';
                    echo '<details><summary>Voir les colonnes</summary>';
                    echo '<table><tr><th>Colonne</th><th>Type</th><th>Null</th><th>Clé</th><th>Défaut</th></tr>';
                    foreach ($columns as $col) {
                        echo '<tr>';
                        echo '<td>' . $col['Field'] . '</td>';
                        echo '<td>' . $col['Type'] . '</td>';
                        echo '<td>' . $col['Null'] . '</td>';
                        echo '<td>' . $col['Key'] . '</td>';
                        echo '<td>' . $col['Default'] . '</td>';
                        echo '</tr>';
                    }
                    echo '</table></details>';
                } else {
                    echo '<div class="warning">⚠️ Table vide ou problème de structure</div>';
                }
                
            } catch (PDOException $e) {
                echo '<div class="error">❌ Table n\'existe pas ou erreur : ' . $e->getMessage() . '</div>';
            }
        }
        
        echo '<hr>';
        echo '<a href="?execute=fix" class="btn btn-danger">Appliquer les corrections</a>';
        
    } catch (PDOException $e) {
        echo '<div class="error">❌ Erreur de connexion : ' . $e->getMessage() . '</div>';
    }
}
?>

        <hr style="margin: 40px 0;">
        
        <div class="warning">
            <h4>🔒 Sécurité importante</h4>
            <p><strong>Supprimez ce fichier après utilisation</strong> pour éviter tout risque de sécurité.</p>
        </div>
        
        <div class="info">
            <h4>💡 Si vous avez encore des problèmes</h4>
            <ol>
                <li>Vérifiez que toutes les classes PHP du module sont à jour</li>
                <li>Videz le cache de PrestaShop (Paramètres avancés > Performances)</li>
                <li>Vérifiez les logs d'erreur de PrestaShop dans le dossier /var/logs/</li>
                <li>En cas de problème persistant, désinstallez et réinstallez le module</li>
            </ol>
        </div>
    </div>
</body>
</html>