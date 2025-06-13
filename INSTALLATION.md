# Guide d'Installation - Module de Réservations v2.1

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

## 🗃️ Configuration base de données

### Vérification des tables créées

```sql
-- Vérifier que les tables ont été créées
SHOW TABLES LIKE 'ps_booker%';
-- Résultat attendu :
-- ps_booker
-- ps_booker_auth
-- ps_booker_auth_reserved
-- ps_booker_product
-- ps_booker_reservation_order
```

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

## 🎨 Personnalisation interface

### 1. Templates personnalisés

```bash
# Copier les templates dans le thème
cp -r modules/booking/views/templates/front/ themes/votre-theme/modules/booking/
```

### 2. Styles CSS personnalisés

```css
/* Dans votre thème : assets/css/booking-custom.css */
.booking-container {
    /* Vos styles personnalisés */
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.fc-event.availability-available {
    background-color: #your-brand-color !important;
}
```

### 3. Traductions personnalisées

```php
// Dans modules/booking/translations/fr.php
$_MODULE['<{booking}prestashop>'] = array(
    'Réserver maintenant' => 'Réserver maintenant',
    'Disponibilités' => 'Créneaux libres',
    // Vos traductions personnalisées
);
```

## 🔧 Configuration avancée

### 1. Performance et cache

```php
// Configuration cache (dans config/config.inc.php)
define('_PS_CACHE_ENABLED_', true);
define('_PS_CACHEFS_DIRECTORY_', _PS_ROOT_DIR_.'/var/cache/');

// Configuration spécifique booking
Configuration::updateValue('BOOKING_CACHE_ENABLED', '1');
Configuration::updateValue('BOOKING_CACHE_DURATION', '3600'); // 1 heure
```

### 2. Intégration avec d'autres modules

```php
// Hook pour synchronisation avec d'autres modules
public function hookActionBookingCreated($params)
{
    $reservation = $params['reservation'];
    
    // Exemple : synchronisation avec newsletter
    if (Module::isEnabled('ps_emailsubscription')) {
        $newsletter = Module::getInstanceByName('ps_emailsubscription');
        $newsletter->subscribeCustomer($reservation->customer_email);
    }
    
    // Exemple : ajout dans CRM
    if (Module::isEnabled('module_crm')) {
        $crm = Module::getInstanceByName('module_crm');
        $crm->addContact($reservation->customer_email, $reservation->customer_firstname, $reservation->customer_lastname);
    }
}
```

### 3. Configuration multi-boutique

```php
// Pour chaque boutique
$shops = Shop::getShops();
foreach ($shops as $shop) {
    Shop::setContext(Shop::CONTEXT_SHOP, $shop['id_shop']);
    
    Configuration::updateValue('BOOKING_DEFAULT_PRICE', '50.00');
    Configuration::updateValue('BOOKING_BUSINESS_HOURS_START', '09:00');
    Configuration::updateValue('BOOKING_BUSINESS_HOURS_END', '17:00');
}
```

## 📧 Configuration des emails

### 1. Templates d'emails personnalisés

```bash
# Copier les templates
mkdir -p mails/fr/
cp modules/booking/mails/fr/* mails/fr/

# Personnaliser les templates
# mails/fr/booking_confirmation.html
# mails/fr/booking_confirmation.txt
# mails/fr/booking_cancellation.html
# mails/fr/booking_cancellation.txt
```

### 2. Variables disponibles dans les emails

```html
<!-- Dans vos templates d'emails -->
<h1>Confirmation de réservation {booking_reference}</h1>
<p>Bonjour {customer_name},</p>
<p>Votre réservation pour {booker_name} est confirmée.</p>
<p><strong>Date :</strong> {date_start} - {date_end}</p>
<p><strong>Prix :</strong> {total_price}€</p>
<p><strong>Référence :</strong> {booking_reference}</p>
```

### 3. Configuration SMTP

```php
// Configuration SMTP pour emails transactionnels
Configuration::updateValue('PS_MAIL_METHOD', '2'); // SMTP
Configuration::updateValue('PS_MAIL_SERVER', 'smtp.votreserveur.com');
Configuration::updateValue('PS_MAIL_USER', 'noreply@votresite.com');
Configuration::updateValue('PS_MAIL_PASSWD', 'votre-mot-de-passe');
Configuration::updateValue('PS_MAIL_SMTP_ENCRYPTION', 'tls');
Configuration::updateValue('PS_MAIL_SMTP_PORT', '587');
```

## 🔒 Sécurité et sauvegarde

### 1. Permissions de sécurité

```bash
# Protéger les fichiers sensibles
chmod 644 modules/booking/config/*
chmod 600 modules/booking/config/config.php

# Protéger les logs
chmod 755 var/logs/
chmod 644 var/logs/booking.log
```

### 2. Sauvegarde automatique

```bash
#!/bin/bash
# Script de sauvegarde : /scripts/backup_booking.sh

DATE=$(date +"%Y%m%d_%H%M%S")
BACKUP_DIR="/backups/booking"
DB_NAME="prestashop"
DB_USER="user"
DB_PASS="password"

# Créer le dossier de sauvegarde
mkdir -p $BACKUP_DIR

# Sauvegarder les données
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME \
    ps_booker ps_booker_auth ps_booker_auth_reserved ps_booker_product ps_booker_reservation_order \
    > $BACKUP_DIR/booking_data_$DATE.sql

# Sauvegarder les fichiers
tar -czf $BACKUP_DIR/booking_files_$DATE.tar.gz \
    modules/booking/ \
    themes/*/modules/booking/ \
    mails/*/booking_*

# Nettoyer les anciennes sauvegardes (garder 30 jours)
find $BACKUP_DIR -name "booking_*" -mtime +30 -delete

echo "Sauvegarde terminée : $DATE"
```

## 🧪 Tests et validation

### 1. Tests fonctionnels

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

### 2. Tests de performance

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

## 🆘 Dépannage courant

### Problème : Module ne s'installe pas

```bash
# Vérifier les permissions
ls -la modules/booking/
chmod -R 755 modules/booking/

# Vérifier les logs
tail -f var/logs/prestashop.log
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

- [ ] Module installé et activé
- [ ] Configuration de base effectuée
- [ ] Premier élément réservable créé
- [ ] Premières disponibilités ajoutées
- [ ] Test de réservation complète
- [ ] Configuration des emails
- [ ] Configuration Stripe (si applicable)
- [ ] Tests des notifications
- [ ] Sauvegarde configurée
- [ ] Monitoring mis en place

**Installation terminée avec succès ! 🎉**

Pour toute question ou problème, consultez :
- [Documentation complète](https://github.com/FastmanTheDuke/prestashop-booking-module/wiki)
- [Issues GitHub](https://github.com/FastmanTheDuke/prestashop-booking-module/issues)
- [Support communautaire](https://discord.gg/booking-module)
