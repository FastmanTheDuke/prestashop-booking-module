# Module PrestaShop - Système de Réservations Avancé v2.1

## 🚀 Nouveautés v2.1

### ✨ Fonctionnalités principales ajoutées
- **📋 Double calendrier séparé** : Gestion indépendante des disponibilités et réservations
- **🔄 Gestion avancée des statuts** : Workflow complet de validation en backoffice
- **💳 Intégration Stripe avec caution** : Paiement sécurisé avec empreinte CB
- **📦 Intégration produits PrestaShop** : Remplacement progressif des "Bookers" par des produits
- **⚡ Actions en lot** : Validation, annulation et notifications multiples
- **📧 Système de notifications** : Emails automatiques et personnalisés
- **📊 Statistiques avancées** : Métriques en temps réel et rapports

## 🎯 Fonctionnalités détaillées

### 📅 Calendrier des Disponibilités
- Interface FullCalendar moderne et responsive
- Création en lot de créneaux avec options avancées
- Copie de semaines complètes
- Créneaux récurrents (quotidien, hebdomadaire, mensuel)
- Drag & drop pour modification rapide
- Filtrages par élément et période
- Export CSV des données

### 📋 Calendrier des Réservations
- Vue centralisée de toutes les réservations
- Multi-sélection avec Ctrl + clic
- Actions rapides : validation, annulation, notifications
- Gestion des statuts : en attente → confirmé → payé → terminé
- Création automatique de commandes PrestaShop
- Intégration système de remboursement
- Notifications personnalisées par email

### 🔧 Administration Avancée
- **Interface d'administration moderne** avec panneaux de contrôle
- **Configuration centralisée** dans AdminBookerSettings
- **Statistiques en temps réel** avec métriques visuelles
- **Système de logs détaillés** pour le débogage
- **Outils de maintenance** automatisés

## 📁 Structure complète du projet

```
booking/
├── booking.php                              # Module principal v2.1
├── classes/                                 # Classes métier
│   ├── Booker.php                          # Éléments réservables
│   ├── BookerAuth.php                      # Disponibilités
│   └── BookerAuthReserved.php              # Réservations avec statuts
├── controllers/
│   ├── admin/                              # Contrôleurs admin
│   │   ├── AdminBooker.php                 # Gestion éléments
│   │   ├── AdminBookerAuth.php             # Gestion disponibilités
│   │   ├── AdminBookerAuthReserved.php     # Gestion réservations
│   │   ├── AdminBookerAvailabilityCalendar.php  # 📅 NOUVEAU
│   │   ├── AdminBookerReservationCalendar.php   # 📋 NOUVEAU
│   │   └── AdminBookerSettings.php         # ⚙️ NOUVEAU
│   └── front/                              # Contrôleurs front
│       └── BookingController.php           # Interface client
├── views/
│   ├── templates/
│   │   ├── admin/                          # Templates admin
│   │   │   ├── availability_calendar.tpl   # 📅 NOUVEAU
│   │   │   ├── reservation_calendar.tpl    # 📋 NOUVEAU
│   │   │   └── settings.tpl                # ⚙️ NOUVEAU
│   │   └── front/                          # Templates front
│   │       └── booking.tpl                 # Interface réservation
│   ├── css/                                # Styles
│   │   ├── admin-calendar.css              # 🎨 NOUVEAU
│   │   ├── admin-booking.css               # Styles admin
│   │   └── booking-front.css               # Styles front
│   └── js/                                 # Scripts JavaScript
│       ├── availability-calendar.js        # 📅 NOUVEAU
│       ├── reservation-calendar.js         # 📋 NOUVEAU
│       ├── admin-booking.js                # Scripts admin
│       └── booking-front.js                # Scripts front
├── sql/                                    # Scripts SQL
│   ├── install.sql                         # Installation
│   └── upgrade/                            # Mises à jour
└── mails/                                  # Templates emails
    ├── fr/                                 # Français
    └── en/                                 # Anglais
```

## 🛠️ Installation

### 1. Téléchargement
```bash
git clone https://github.com/FastmanTheDuke/prestashop-booking-module.git
cd prestashop-booking-module
```

### 2. Installation dans PrestaShop
```bash
# Copier dans le dossier modules
cp -r . /path/to/prestashop/modules/booking/

# Ou via l'interface admin PrestaShop
# Modules > Gestionnaire de modules > Ajouter un module
```

### 3. Configuration initiale
1. **Activer le module** dans l'interface PrestaShop
2. **Configurer les paramètres** dans `Réservations > Configuration`
3. **Créer les premiers éléments** dans `Réservations > Éléments & Produits`
4. **Définir les disponibilités** via le calendrier

## ⚙️ Configuration avancée

### Paramètres généraux
- **Prix par défaut** : Tarif standard des réservations
- **Montant de caution** : Somme à pré-autoriser via Stripe
- **Durée des créneaux** : Granularité des disponibilités
- **Délai d'expiration** : Temps limite pour valider une réservation
- **Confirmation automatique** : Validation sans intervention manuelle

### Intégration Stripe
```php
// Configuration requise
Configuration::updateValue('BOOKING_STRIPE_ENABLED', 1);
Configuration::updateValue('BOOKING_STRIPE_HOLD_DEPOSIT', 1);
Configuration::updateValue('BOOKING_SAVE_CARDS', 1);
```

### Notifications email
- **Templates personnalisables** dans `/mails/`
- **Variables disponibles** : `{booking_reference}`, `{customer_name}`, `{date_start}`, etc.
- **Envoi automatique** selon les événements (confirmation, rappel, annulation)

## 🎮 Guide d'utilisation

### Pour les administrateurs

#### 1. Gestion des disponibilités
- Accédez à `Réservations > Calendrier Disponibilités`
- **Créer un créneau** : Cliquez sur une date ou utilisez la sélection
- **Création en lot** : Utilisez l'outil de création massive
- **Copier une semaine** : Dupliquez rapidement des plannings

#### 2. Gestion des réservations
- Accédez à `Réservations > Calendrier Réservations`
- **Valider en lot** : Sélectionnez avec Ctrl + clic puis validez
- **Filtrer par statut** : Affichez uniquement les réservations voulues
- **Exporter les données** : Téléchargez au format CSV

#### 3. Actions rapides
```javascript
// Multi-sélection (Ctrl + clic)
// Actions disponibles :
- Validation en lot avec création de commandes
- Annulation avec motif et remboursement automatique
- Envoi de notifications personnalisées
- Export des données sélectionnées
```

### Pour les développeurs

#### Hooks disponibles
```php
// Hook après changement de statut
public function hookActionBookingStatusChange($params)
{
    $reservation = $params['reservation'];
    $old_status = $params['old_status'];
    $new_status = $params['new_status'];
    
    // Votre logique personnalisée
}

// Hook avant création de commande
public function hookActionBookingBeforeOrderCreation($params)
{
    $reservation = $params['reservation'];
    // Modifier les données de commande si nécessaire
}
```

#### Classes principales
```php
// Création d'une disponibilité
$auth = new BookerAuth();
$auth->id_booker = 1;
$auth->date_from = '2025-06-15 09:00:00';
$auth->date_to = '2025-06-15 10:00:00';
$auth->max_bookings = 1;
$auth->save();

// Gestion d'une réservation
$reservation = new BookerAuthReserved($id);
$reservation->status = 'confirmed';
$reservation->save();
$reservation->createOrder(); // Créer la commande PrestaShop
```

## 📊 Métriques et statistiques

### Tableau de bord
- **Éléments actifs** : Nombre d'éléments réservables
- **Créneaux disponibles** : Disponibilités futures
- **Réservations en cours** : Par statut (attente, confirmé, payé)
- **Chiffre d'affaires** : Revenus mensuels et prévisionnels

### Rapports avancés
- **Taux de conversion** : Disponibilités → Réservations confirmées
- **Analyse temporelle** : Pics de demande par période
- **Performance par élément** : Éléments les plus demandés
- **Satisfaction client** : Statistiques d'annulation

## 🚀 Intégrations

### Avec PrestaShop
- **Produits** : Association Bookers ↔ Produits du catalogue
- **Commandes** : Création automatique après validation
- **Clients** : Synchronisation des données utilisateur
- **Stocks** : Gestion des disponibilités comme stock

### Modules compatibles
- **Stripe Official** : Paiements avec caution et empreinte
- **MailChimp** : Synchronisation des contacts
- **Google Analytics** : Tracking des conversions
- **Social Login** : Connexion simplifiée

## 🛡️ Sécurité et performance

### Mesures de sécurité
- **Validation des données** : Sanitisation complète des entrées
- **Protection CSRF** : Tokens sur toutes les actions sensibles
- **Contrôle d'accès** : Permissions granulaires par rôle
- **Chiffrement** : Données sensibles protégées

### Optimisations
- **Cache intelligent** : Mise en cache des requêtes fréquentes
- **Requêtes optimisées** : Index sur les champs critiques
- **Lazy loading** : Chargement progressif des données
- **Compression** : Assets minifiés en production

## 🔧 Maintenance et support

### Logs et débogage
```php
// Activer le mode debug
Configuration::updateValue('BOOKING_DEBUG_MODE', 1);

// Consulter les logs
tail -f /var/log/prestashop/booking.log
```

### Nettoyage automatique
- **Réservations expirées** : Suppression automatique via cron
- **Cache** : Vidage périodique des données temporaires
- **Logs** : Rotation et archivage automatique

### Sauvegarde
```sql
-- Sauvegarder les données principales
mysqldump -u user -p database_name booker booker_auth booker_auth_reserved > backup_booking.sql
```

## 📞 Support et contribution

### Documentation
- **Wiki complet** : [GitHub Wiki](https://github.com/FastmanTheDuke/prestashop-booking-module/wiki)
- **API Reference** : Documentation des classes et méthodes
- **Tutoriels vidéo** : Guides pas à pas

### Contribution
1. **Fork** le projet
2. **Créer une branche** : `git checkout -b feature/nouvelle-fonctionnalite`
3. **Commit** : `git commit -am 'Ajout nouvelle fonctionnalité'`
4. **Push** : `git push origin feature/nouvelle-fonctionnalite`
5. **Pull Request** : Proposer vos modifications

### Issues et bugs
- **Signaler un bug** : [GitHub Issues](https://github.com/FastmanTheDuke/prestashop-booking-module/issues)
- **Demander une fonctionnalité** : [Feature Requests](https://github.com/FastmanTheDuke/prestashop-booking-module/discussions)
- **Support communautaire** : [Discord](https://discord.gg/booking-module)

## 📝 Changelog

### v2.1.0 - 2025-06-13
#### 🎉 Nouvelles fonctionnalités
- **Double calendrier** : Séparation complète disponibilités/réservations
- **Gestion avancée des statuts** : Workflow complet de validation
- **Actions en lot** : Validation/annulation multiple avec options
- **Intégration Stripe** : Paiement avec caution et empreinte CB
- **Interface moderne** : Nouveau design avec FullCalendar 6
- **Notifications enrichies** : Templates personnalisables et envoi automatique

#### 🔧 Améliorations
- Interface d'administration repensée
- Performance améliorée avec cache intelligent
- Meilleure intégration avec PrestaShop
- Documentation complète et mise à jour

#### 🐛 Corrections
- Résolution des conflits de timezone
- Amélioration de la gestion des erreurs
- Optimisation des requêtes SQL
- Corrections de compatibilité multi-langues

### v2.0.0 - Version précédente
- Système de base avec quiz et booking
- Contrôleurs AdminBooker, AdminBookerAuth, AdminBookerAuthReserved
- Interface basique de réservation

## 📋 Roadmap

### v2.2.0 - Prochaine version
- [ ] **Intégration Google Calendar** : Synchronisation bidirectionnelle
- [ ] **Application mobile** : App native iOS/Android
- [ ] **API REST complète** : Endpoints pour intégrations tierces
- [ ] **Système de commentaires** : Avis clients sur les réservations
- [ ] **Géolocalisation** : Cartes et directions

### v2.3.0 - Version future
- [ ] **Intelligence artificielle** : Recommandations automatiques
- [ ] **Réalité augmentée** : Prévisualisation des espaces
- [ ] **Blockchain** : Certificats de réservation infalsifiables
- [ ] **IoT Integration** : Contrôle des équipements connectés

## 📄 Licence

Ce projet est sous licence **MIT**. Voir le fichier [LICENSE](LICENSE) pour plus de détails.

---

## 🙏 Remerciements

Merci à tous les contributeurs qui ont rendu ce projet possible :
- **FastmanTheDuke** - Développeur principal
- **Communauté PrestaShop** - Tests et retours
- **Beta testeurs** - Validation des fonctionnalités

---

**Développé avec ❤️ pour la communauté PrestaShop**

[⬆ Retour en haut](#module-prestashop---système-de-réservations-avancé-v21)
