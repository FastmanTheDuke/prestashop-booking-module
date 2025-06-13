# Guide d'Installation - Module de Réservations v2.1

## 🚨 PROBLÈME D'INSTALLATION RÉSOLU ✅

**MISE À JOUR DU 13/06/2025** : Le problème critique d'installation a été **complètement corrigé** !

### 🔧 Corrections apportées :

#### ✅ Méthode `installDB()` réécrite
- **Problème** : La méthode était incomplète et s'arrêtait brutalement
- **Solution** : Réécriture complète avec toutes les requêtes SQL fonctionnelles
- **Résultat** : 6 tables créées automatiquement lors de l'installation

#### ✅ Gestion d'erreurs renforcée
- **Problème** : Aucun diagnostic en cas d'échec
- **Solution** : Logs détaillés avec `PrestaShopLogger::addLog()`
- **Résultat** : Diagnostic précis des erreurs d'installation

#### ✅ Installation des onglets admin
- **Problème** : Menu d'administration non créé
- **Solution** : Méthode `installTab()` complète avec hiérarchie
- **Résultat** : Menu complet sous `Améliorer > Réservations`

#### ✅ Configuration par défaut
- **Problème** : Paramètres non initialisés
- **Solution** : Configuration automatique avec valeurs par défaut
- **Résultat** : Module prêt à l'emploi après installation

---

## 📋 Prérequis

### Configuration serveur
- **PrestaShop** : Version 1.7.x ou 8.x
- **PHP** : Version 7.4 minimum (8.1 recommandé)
- **MySQL** : Version 5.7 minimum (8.0 recommandé)
- **Extensions PHP requises** :
  - `php-curl` (pour intégrations externes)
  - `php-json` (traitement données)
  - `php-mbstring` (gestion caractères spéciaux)
  - `php-gd` (traitement images)

### Permissions serveur
```bash
# Dossier modules
chmod 755 /var/www/prestashop/modules/
chmod -R 755 /var/www/prestashop/modules/booking/

# Dossier uploads (si nécessaire)
chmod 777 /var/www/prestashop/upload/
```

---

## 🚀 Installation

### Méthode 1 : Installation via GitHub

```bash
# 1. Cloner le repository
cd /var/www/prestashop/modules/
git clone https://github.com/FastmanTheDuke/prestashop-booking-module.git booking

# 2. Vérifier les permissions
chmod -R 755 booking/
chown -R www-data:www-data booking/

# 3. Accéder à l'admin PrestaShop
# Modules > Gestionnaire de modules > Rechercher "booking"
```

### Méthode 2 : Installation via ZIP

```bash
# 1. Télécharger et extraire
wget https://github.com/FastmanTheDuke/prestashop-booking-module/archive/main.zip
unzip main.zip
mv prestashop-booking-module-main /var/www/prestashop/modules/booking

# 2. Ajuster les permissions
chmod -R 755 /var/www/prestashop/modules/booking/
chown -R www-data:www-data /var/www/prestashop/modules/booking/
```

### Méthode 3 : Via l'interface PrestaShop

1. **Télécharger** le fichier ZIP depuis GitHub
2. **Accéder** à l'admin PrestaShop
3. **Aller** dans `Modules > Gestionnaire de modules`
4. **Cliquer** sur "Ajouter un module"
5. **Glisser-déposer** le fichier ZIP
6. **Installer** le module

---

## ✅ Vérification d'installation

### 1. Tables de base de données créées

Après installation, vérifiez que les **6 tables** ont été créées :

```sql
-- Commande de vérification
SHOW TABLES LIKE 'ps_booker%';
SHOW TABLES LIKE 'ps_booking%';

-- Résultat attendu :
-- ps_booker                    (éléments réservables)
-- ps_booker_auth               (créneaux de disponibilité)
-- ps_booker_auth_reserved      (réservations clients)
-- ps_booker_product            (liaison avec produits PrestaShop)
-- ps_booker_reservation_order  (liaison avec commandes)
-- ps_booking_activity_log      (logs d'activité système)
```

### 2. Menu d'administration créé

Vérifiez la présence du menu dans le back-office :

```
PrestaShop Admin > Améliorer > 📅 Réservations
├── 📋 Éléments & Produits      (AdminBooker)
├── ⏰ Disponibilités           (AdminBookerAuth)
├── 🎫 Réservations             (AdminBookerAuthReserved)
└── 📅 Calendriers              (AdminBookerView)
```

### 3. Configuration initialisée

Vérifiez les paramètres par défaut :

```sql
-- Vérifier la configuration
SELECT * FROM ps_configuration WHERE name LIKE 'BOOKING_%';

-- Configuration attendue :
-- BOOKING_DEFAULT_PRICE = '50.00'
-- BOOKING_DEPOSIT_AMOUNT = '20.00'
-- BOOKING_AUTO_CONFIRM = '0'
-- etc.
```

---

## ⚙️ Configuration initiale

### 1. Activation du module

```bash
# Via l'interface admin
Modules > Gestionnaire de modules > Rechercher "Système de Réservations" > Installer

# Via CLI (si disponible)
php bin/console prestashop:module install booking
```

### 2. Configuration de base

Après installation, accédez à :
`Réservations > Configuration`

#### Paramètres généraux
```php
Configuration::updateValue('BOOKING_DEFAULT_PRICE', '50.00');          // Prix par défaut
Configuration::updateValue('BOOKING_DEPOSIT_AMOUNT', '20.00');         // Montant caution
Configuration::updateValue('BOOKING_DEFAULT_DURATION', '60');          // Durée créneaux (min)
Configuration::updateValue('BOOKING_EXPIRY_HOURS', '24');              // Expiration réservations
Configuration::updateValue('BOOKING_AUTO_CONFIRM', '0');               // Validation manuelle
Configuration::updateValue('BOOKING_MULTI_SELECT', '1');               // Multi-sélection
```

#### Heures d'ouverture
```php
Configuration::updateValue('BOOKING_BUSINESS_HOURS_START', '08:00');   // Ouverture
Configuration::updateValue('BOOKING_BUSINESS_HOURS_END', '18:00');     // Fermeture
Configuration::updateValue('BOOKING_ALLOWED_DAYS', '1,2,3,4,5,6,7');   // Jours actifs
```

### 3. Configuration des notifications

```php
Configuration::updateValue('BOOKING_NOTIFICATIONS_ENABLED', '1');       // Activer notifications
Configuration::updateValue('BOOKING_AUTO_CONFIRMATION_EMAIL', '1');     // Email auto confirmation
Configuration::updateValue('BOOKING_AUTO_REMINDERS', '1');              // Rappels automatiques
Configuration::updateValue('BOOKING_REMINDER_HOURS', '24');             // Délai rappel
Configuration::updateValue('BOOKING_ADMIN_EMAIL', 'admin@votresite.fr'); // Email admin
```

---

## 💳 Configuration Stripe (optionnel)

### 1. Installation du module Stripe officiel

```bash
# Via PrestaShop
Modules > Gestionnaire de modules > Rechercher "Stripe" > Installer
```

### 2. Configuration des clés Stripe

```php
// Mode test
Configuration::updateValue('STRIPE_TEST_PUBLISHABLE_KEY', 'pk_test_...');
Configuration::updateValue('STRIPE_TEST_SECRET_KEY', 'sk_test_...');

// Mode production
Configuration::updateValue('STRIPE_LIVE_PUBLISHABLE_KEY', 'pk_live_...');
Configuration::updateValue('STRIPE_LIVE_SECRET_KEY', 'sk_live_...');

// Activation dans le module booking
Configuration::updateValue('BOOKING_STRIPE_ENABLED', '1');
Configuration::updateValue('BOOKING_STRIPE_HOLD_DEPOSIT', '1');
Configuration::updateValue('BOOKING_SAVE_CARDS', '1');
```

### 3. Configuration webhook Stripe

URL à configurer dans Stripe Dashboard :
```
https://votresite.com/modules/booking/webhook/stripe.php
```

Événements à écouter :
- `payment_intent.succeeded`
- `payment_intent.payment_failed`
- `payment_intent.canceled`

---

## 🗃️ Configuration base de données

### Index recommandés (optimisation)

```sql
-- Index supplémentaires pour les performances
ALTER TABLE ps_booker_auth ADD INDEX idx_date_active (date_from, date_to, active);
ALTER TABLE ps_booker_auth_reserved ADD INDEX idx_status_date (status, date_start);
ALTER TABLE ps_booker_auth_reserved ADD INDEX idx_customer_email (customer_email);
```

### Nettoyage périodique (cron recommandé)

```bash
# Ajouter dans crontab
# Nettoyage quotidien des réservations expirées à 2h du matin
0 2 * * * /usr/bin/php /var/www/prestashop/modules/booking/cron/cleanup.php

# Sauvegarde hebdomadaire des données
0 3 * * 0 mysqldump -u user -p database ps_booker ps_booker_auth ps_booker_auth_reserved > /backups/booking_$(date +\%Y\%m\%d).sql
```

---

## 🎨 Personnalisation interface

### 1. Templates personnalisés

```bash
# Copier les templates dans le thème
cp -r modules/booking/views/templates/front/ themes/votre-theme/modules/booking/
```

### 2. Styles CSS personnalisés

```css
/* Personnalisation du calendrier */
.booking-calendar {
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

/* Personnalisation des boutons */
.booking-btn-primary {
    background: linear-gradient(45deg, #007bff, #0056b3);
    border: none;
    border-radius: 6px;
}
```

---

## 🧪 Tests fonctionnels

### 1. Test d'installation

```bash
# Script de test : tests/installation_test.sh
#!/bin/bash

echo "Test 1: Vérification des tables"
mysql -u user -p database -e "SHOW TABLES LIKE 'ps_booker%';"

echo "Test 2: Vérification de la configuration"  
mysql -u user -p database -e "SELECT * FROM ps_configuration WHERE name LIKE 'BOOKING_%';"

echo "Test 3: Vérification des onglets admin"
mysql -u user -p database -e "SELECT * FROM ps_tab WHERE class_name LIKE 'AdminBooker%';"
```

### 2. Tests fonctionnels

```bash
# Script de test : tests/functional_test.sh
#!/bin/bash

echo "Test 1: Création d'un élément réservable"
# Test création booker

echo "Test 2: Création de disponibilités"
# Test création availabilities

echo "Test 3: Processus de réservation complet"
# Test réservation end-to-end

echo "Test 4: Validation et paiement"
# Test workflow complet

echo "Test 5: Notifications"
# Test envoi emails
```

### 3. Tests de performance

```sql
-- Test de performance sur la recherche de disponibilités
EXPLAIN SELECT ba.* FROM ps_booker_auth ba
WHERE ba.active = 1 
AND ba.date_from >= NOW()
AND ba.current_bookings < ba.max_bookings
ORDER BY ba.date_from ASC;

-- Vérifier que les index sont utilisés
-- Key: idx_date_active (recommended)
```

---

## 🆘 Dépannage courant

### Problème : Module ne s'installe pas

```bash
# Vérifier les permissions
ls -la modules/booking/
chmod -R 755 modules/booking/

# Vérifier les logs
tail -f var/logs/prestashop.log
```

### Problème : Tables non créées

```bash
# Vérifier les logs d'installation
tail -f var/logs/prestashop.log | grep "Booking"

# Tester la connexion à la base
mysql -u user -p -e "SHOW PROCESSLIST;"
```

### Problème : Calendrier ne s'affiche pas

```bash
# Vérifier que FullCalendar est chargé
curl -I https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.8/index.global.min.js

# Vérifier les erreurs JavaScript
# F12 > Console dans le navigateur
```

### Problème : Emails non envoyés

```php
// Test d'envoi email
$test_result = Mail::Send(
    1, // id_lang
    'test',
    'Test email',
    array(),
    'test@example.com',
    'Test User'
);
var_dump($test_result);
```

### Problème : Réservations non sauvegardées

```sql
-- Vérifier la structure des tables
DESCRIBE ps_booker_auth_reserved;

-- Vérifier les contraintes
SHOW CREATE TABLE ps_booker_auth_reserved;
```

---

## 📞 Support et maintenance

### Logs à surveiller

```bash
# Logs PrestaShop
tail -f var/logs/prestashop.log

# Logs spécifiques booking
tail -f var/logs/booking.log

# Logs serveur web
tail -f /var/log/apache2/error.log
# ou
tail -f /var/log/nginx/error.log
```

### Maintenance préventive

```bash
# Script de maintenance hebdomadaire
#!/bin/bash
# maintenance_booking.sh

# 1. Nettoyer les réservations expirées
php modules/booking/cron/cleanup_expired.php

# 2. Optimiser les tables
mysql -u user -p database -e "OPTIMIZE TABLE ps_booker, ps_booker_auth, ps_booker_auth_reserved;"

# 3. Vérifier l'intégrité des données
php modules/booking/tools/data_integrity_check.php

# 4. Génération rapport hebdomadaire
php modules/booking/tools/weekly_report.php
```

---

## ✅ Checklist post-installation

- [ ] ✅ Module installé et activé
- [ ] ✅ 6 tables de base de données créées
- [ ] ✅ Menu d'administration présent
- [ ] ✅ Configuration de base effectuée
- [ ] [ ] Premier élément réservable créé
- [ ] [ ] Premières disponibilités ajoutées
- [ ] [ ] Test de réservation complète
- [ ] [ ] Configuration des emails
- [ ] [ ] Configuration Stripe (si applicable)
- [ ] [ ] Tests des notifications
- [ ] [ ] Sauvegarde configurée
- [ ] [ ] Monitoring mis en place

**Installation terminée avec succès ! 🎉**

---

## 🔄 Mise à jour depuis version précédente

Si vous aviez une version antérieure avec le problème d'installation :

### 1. Désinstaller l'ancienne version
```bash
# Via l'interface admin
Modules > Gestionnaire de modules > "Système de Réservations" > Désinstaller
```

### 2. Nettoyer les résidus (optionnel)
```sql
-- Supprimer les tables incomplètes (si nécessaire)
DROP TABLE IF EXISTS ps_booker;
DROP TABLE IF EXISTS ps_booker_auth;
DROP TABLE IF EXISTS ps_booker_auth_reserved;
```

### 3. Installer la nouvelle version
```bash
# Télécharger la version corrigée
git pull origin main

# Ou re-télécharger depuis GitHub
# Puis procéder à l'installation normale
```

---

**Le problème d'installation est maintenant définitivement résolu ! ✅**
