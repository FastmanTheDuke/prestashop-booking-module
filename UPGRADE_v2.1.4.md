# Guide de mise à jour vers la version 2.1.4

Ce guide vous accompagne dans la mise à jour de votre module de réservations vers la version 2.1.4, qui introduit le système avancé de cautions Stripe.

## 🎯 Nouvelles fonctionnalités v2.1.4

### 🏦 Gestion avancée des cautions Stripe
- **Empreinte de carte bancaire** pour sécuriser les réservations
- **Pré-autorisation automatique** sans débit immédiat
- **Capture/libération intelligente** selon le statut de la réservation
- **Gestion des remboursements** automatiques
- **Interface d'administration** complète pour les cautions
- **Webhooks Stripe** pour synchronisation en temps réel

### 🎨 Interface moderne
- **Template front-end** responsive et moderne
- **Processus de réservation** en 4 étapes claires
- **JavaScript avancé** avec gestion d'erreurs
- **CSS moderne** avec animations et transitions

### 👨‍💼 Administration avancée
- **Nouveau contrôleur** AdminBookerDeposits
- **Vue détaillée** des cautions avec historique
- **Actions en lot** pour la gestion multiple
- **Statistiques** et rapports détaillés

---

## 🔧 Prérequis

### Version PrestaShop
- PrestaShop 1.7.6+ ou 8.x
- PHP 7.4+ (recommandé : PHP 8.1)
- MySQL 5.7+ ou MariaDB 10.2+

### Extensions PHP requises
```bash
# Vérifier les extensions PHP
php -m | grep -E "(curl|json|openssl|mbstring|zip)"
```

### Compte Stripe
- Compte Stripe actif (test ou live)
- Clés API Stripe configurées
- Webhook endpoint configuré

---

## 📦 Étapes de mise à jour

### 1. Sauvegarde complète

⚠️ **IMPORTANT** : Effectuez toujours une sauvegarde avant mise à jour !

```bash
# Sauvegarde de la base de données
mysqldump -u [user] -p [database] > backup_booking_$(date +%Y%m%d_%H%M%S).sql

# Sauvegarde du module
tar -czf backup_booking_module_$(date +%Y%m%d_%H%M%S).tar.gz modules/booking/
```

### 2. Téléchargement du module

```bash
# Méthode Git (recommandée)
cd modules/booking/
git pull origin main
git checkout v2.1.4

# Ou téléchargement direct
wget https://github.com/FastmanTheDuke/prestashop-booking-module/archive/v2.1.4.zip
```

### 3. Mise à jour des fichiers

```bash
# Copier les nouveaux fichiers
cp -r prestashop-booking-module-2.1.4/* modules/booking/

# Définir les permissions
chmod 755 modules/booking/
chmod 644 modules/booking/*.php
chmod 755 modules/booking/webhook/
chmod 644 modules/booking/webhook/*.php
```

### 4. Exécution du script de migration

```bash
# Se connecter à MySQL
mysql -u [user] -p [database]

# Exécuter le script de migration
source modules/booking/sql/install_deposit_system.sql;

# Ou via interface web
# Aller dans Modules > Booking > Configuration > Mise à jour
```

### 5. Configuration Stripe

#### 5.1 Récupérer les clés API
1. Connectez-vous à votre [Dashboard Stripe](https://dashboard.stripe.com)
2. Allez dans **Développeurs > Clés API**
3. Notez vos clés publiques et secrètes (test et live)

#### 5.2 Configurer les webhooks
1. Dans Stripe Dashboard : **Développeurs > Webhooks**
2. Cliquez sur **Ajouter un endpoint**
3. URL : `https://votresite.com/modules/booking/webhook/stripe_handler.php`
4. Événements à écouter :
   ```
   setup_intent.succeeded
   setup_intent.setup_failed
   payment_intent.requires_capture
   payment_intent.succeeded
   payment_intent.payment_failed
   payment_intent.canceled
   charge.succeeded
   charge.captured
   charge.dispute.created
   charge.refunded
   ```
5. Notez le **secret de signature** généré

#### 5.3 Configuration dans PrestaShop
1. **Back-office > Modules > Booking > Configuration**
2. Onglet **Paiements Stripe** :
   ```
   Mode test : Activé (pour débuter)
   Clé publique test : pk_test_...
   Clé secrète test : sk_test_...
   Secret webhook : whsec_...
   ```

### 6. Configuration des cautions

#### 6.1 Paramètres globaux
```
Cautions activées : Oui
Taux de caution : 30% (modifiable)
Montant minimum : 50€
Montant maximum : 2000€
Délai capture auto : 24h
Délai libération auto : 168h (7 jours)
```

#### 6.2 Configuration par élément (optionnel)
Chaque booker peut avoir ses propres paramètres de caution via l'interface d'administration.

---

## 🧪 Tests post-installation

### 1. Test des fonctionnalités de base

#### Test de réservation simple
1. Aller sur le front-office
2. Sélectionner un élément réservable
3. Effectuer une réservation complète
4. Vérifier en back-office que la réservation apparaît

#### Test du système de caution
1. Effectuer une réservation avec caution
2. Utiliser une carte de test Stripe : `4242424242424242`
3. Vérifier que la caution est pré-autorisée
4. Tester les actions admin (capture, libération)

### 2. Test des webhooks

```bash
# Test avec l'outil CLI Stripe
stripe listen --forward-to https://votresite.com/modules/booking/webhook/stripe_handler.php
```

### 3. Vérification des logs

```bash
# Vérifier les logs du module
tail -f modules/booking/logs/stripe_webhooks.log

# Vérifier les logs PrestaShop
tail -f var/logs/prod.log
```

---

## 🛠️ Résolution des problèmes

### Problème : Webhooks non reçus

**Symptômes :**
- Les statuts de caution ne se mettent pas à jour automatiquement
- Pas d'entrées dans les logs de webhooks

**Solutions :**
1. Vérifier l'URL du webhook dans Stripe Dashboard
2. Tester l'URL manuellement :
   ```bash
   curl -X POST https://votresite.com/modules/booking/webhook/stripe_handler.php \
        -H "Content-Type: application/json" \
        -d '{"test": true}'
   ```
3. Vérifier les permissions du fichier webhook
4. Contrôler les logs d'erreur du serveur web

### Problème : Erreur de signature webhook

**Symptômes :**
- Erreur "Invalid signature" dans les logs
- Webhooks marqués comme échoués dans Stripe

**Solutions :**
1. Vérifier le secret de webhook dans la configuration
2. S'assurer que le secret commence par `whsec_`
3. Régénérer le secret dans Stripe si nécessaire

### Problème : Cautions non autorisées

**Symptômes :**
- Erreur lors de la création de l'empreinte carte
- PaymentMethod non créé

**Solutions :**
1. Vérifier les clés API Stripe (publique et secrète)
2. Contrôler que les clés correspondent au bon environnement (test/live)
3. Vérifier la configuration du compte Stripe

### Problème : Base de données

**Symptômes :**
- Erreurs SQL lors de l'installation
- Tables manquantes

**Solutions :**
1. Exécuter manuellement le script SQL :
   ```sql
   -- Remplacer PREFIX_ par le préfixe de votre base
   source modules/booking/sql/install_deposit_system.sql;
   ```
2. Vérifier les permissions MySQL
3. Contrôler l'espace disque disponible

---

## 📋 Checklist post-installation

### ✅ Configuration de base
- [ ] Module activé et fonctionnel
- [ ] Base de données mise à jour
- [ ] Permissions fichiers correctes
- [ ] Logs accessibles en écriture

### ✅ Configuration Stripe
- [ ] Clés API configurées
- [ ] Webhook endpoint ajouté
- [ ] Secret webhook configuré
- [ ] Test de paiement réussi

### ✅ Tests fonctionnels
- [ ] Réservation simple OK
- [ ] Réservation avec caution OK
- [ ] Autorisation de caution OK
- [ ] Capture/libération OK
- [ ] Webhooks reçus et traités
- [ ] Notifications emails OK

### ✅ Interface d'administration
- [ ] Nouvel onglet "Cautions" visible
- [ ] Liste des cautions accessible
- [ ] Actions admin fonctionnelles
- [ ] Statistiques affichées

---

## 🆕 Nouvelles fonctionnalités disponibles

### Pour les administrateurs

#### Gestion des cautions
- **Menu** : Modules > Booking > Cautions
- **Actions** : Autoriser, capturer, libérer, rembourser
- **Vue détaillée** : Historique complet des transactions
- **Actions en lot** : Traitement multiple

#### Configuration avancée
- **Paramètres globaux** : Taux, montants min/max
- **Configuration par élément** : Paramètres spécifiques
- **Templates d'emails** : Personnalisation des notifications

### Pour les clients

#### Processus de réservation amélioré
1. **Sélection** : Calendrier interactif avec disponibilités
2. **Informations** : Formulaire optimisé avec validation
3. **Caution** : Interface Stripe sécurisée avec empreinte CB
4. **Confirmation** : Récapitulatif complet avec statuts

#### Notifications automatiques
- **Autorisation** : Confirmation de la pré-autorisation
- **Libération** : Notification de libération de caution
- **Capture** : Information en cas de retenue
- **Remboursement** : Confirmation de remboursement

---

## 🔮 Prochaines étapes recommandées

### Configuration production
1. **Passer en mode live** Stripe après tests complets
2. **Configurer la surveillance** avec alertes automatiques
3. **Optimiser les paramètres** selon vos besoins métier

### Personnalisation
1. **Adapter les templates** aux couleurs de votre marque
2. **Personnaliser les emails** avec vos messages
3. **Configurer les règles métier** spécifiques

### Monitoring
1. **Surveiller les logs** régulièrement
2. **Analyser les statistiques** de conversion
3. **Optimiser le processus** selon les retours clients

---

## 📞 Support et assistance

### Documentation
- **Wiki GitHub** : https://github.com/FastmanTheDuke/prestashop-booking-module/wiki
- **Documentation Stripe** : https://stripe.com/docs
- **PrestaShop Docs** : https://devdocs.prestashop.com/

### Support technique
- **Issues GitHub** : https://github.com/FastmanTheDuke/prestashop-booking-module/issues
- **Discord Communauté** : https://discord.gg/booking-module
- **Email** : support@booking-module.com

### Stripe Support
- **Dashboard** : https://dashboard.stripe.com/support
- **Documentation** : https://stripe.com/docs/webhooks
- **Statut** : https://status.stripe.com/

---

*Version 2.1.4 - Développée avec ❤️ pour la communauté PrestaShop*
