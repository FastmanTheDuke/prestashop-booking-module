# 🎯 Module de Réservations PrestaShop v2.1.5

<div align="center">

![PrestaShop Booking Module](https://img.shields.io/badge/PrestaShop-1.7.6%2B%20%7C%208.x-blue?style=for-the-badge&logo=prestashop)
![PHP Version](https://img.shields.io/badge/PHP-7.4%2B%20%7C%208.x-purple?style=for-the-badge&logo=php)
![Stripe Integration](https://img.shields.io/badge/Stripe-v3%20API-green?style=for-the-badge&logo=stripe)
![License](https://img.shields.io/badge/License-MIT-yellow?style=for-the-badge)

**Module complet de gestion de réservations avec cautions Stripe intelligentes**

[📥 Télécharger](https://github.com/FastmanTheDuke/prestashop-booking-module/releases) • [📚 Documentation](https://github.com/FastmanTheDuke/prestashop-booking-module/wiki) • [🐛 Issues](https://github.com/FastmanTheDuke/prestashop-booking-module/issues) • [💬 Discord](https://discord.gg/booking-module)

</div>

---

## 🚨 CORRECTION MAJEURE v2.1.5 - Installation Corrigée ✅

### 🔧 **Problème résolu : Erreur d'installation table `booking_deposit_history`**

**MISE À JOUR DU 17/06/2025** : Le problème critique d'installation lié à la table `booking_deposit_history` a été **définitivement corrigé** !

#### ✅ **Corrections apportées dans v2.1.5** :

- **✅ Structure SQL optimisée** : Création des tables dans le bon ordre sans dépendances circulaires
- **✅ Contraintes de clé étrangère** : Ajoutées APRÈS la création des tables pour éviter les échecs
- **✅ Gestion d'erreurs robuste** : Logs détaillés avec `PrestaShopLogger::addLog()` pour diagnostic
- **✅ Installation étape par étape** : Processus en 8 étapes claires et sécurisées
- **✅ Désinstallation propre** : Suppression des tables avec `FOREIGN_KEY_CHECKS = 0`
- **✅ Tests d'installation** : Validés sur PrestaShop 1.7.8+ et 8.x

#### 🏗️ **Améliorations d'architecture** :

- **Installation modulaire** : Séparation claire des étapes d'installation
- **Contraintes différées** : Ajout des foreign keys après création complète des tables  
- **Logging amélioré** : Traçabilité complète des opérations d'installation
- **Rollback sécurisé** : Désinstallation propre même en cas d'installation partielle

---

## 🚀 Nouveautés v2.1.5 - Installation Bulletproof

### 🛠️ **Installation 100% Fiable**
- **Processus en 8 étapes** : Tables principales → Cautions → Historique → Contraintes
- **Gestion d'erreurs complète** : Chaque étape est vérifiée et loggée
- **Installation résiliente** : Gestion des interruptions et reprises
- **Tests automatisés** : Validation de l'intégrité après installation

### 💳 **Système de Cautions Intelligent**
- **Empreinte CB sécurisée** sans stockage de données sensibles
- **Pré-autorisation automatique** avec capture différée
- **Gestion intelligente** des libérations et remboursements
- **Webhooks Stripe** pour synchronisation temps réel
- **Interface admin complète** avec historique détaillé

### 💡 **Processus Client Simplifié**
1. **Sélection** - Calendrier interactif avec disponibilités
2. **Informations** - Formulaire optimisé et validation
3. **Caution** - Interface Stripe Elements sécurisée
4. **Confirmation** - Récapitulatif et suivi de statut

### 🎨 **Interface Moderne**
- **Design responsive** adaptatif mobile/tablette/desktop
- **CSS moderne** avec animations fluides
- **JavaScript ES6+** avec gestion d'état avancée
- **Expérience utilisateur** optimisée et intuitive

---

## 📋 Table des matières

- [🌟 Fonctionnalités](#-fonctionnalités)
- [🎯 Démonstration](#-démonstration)
- [⚡ Installation rapide](#-installation-rapide)
- [🔧 Configuration](#-configuration)
- [📊 Interface d'administration](#-interface-dadministration)
- [🛡️ Sécurité et conformité](#️-sécurité-et-conformité)
- [🔗 API et intégrations](#-api-et-intégrations)
- [🧪 Tests et développement](#-tests-et-développement)
- [💡 Cas d'usage](#-cas-dusage)
- [🎨 Personnalisation](#-personnalisation)
- [📞 Support](#-support)
- [🤝 Contribution](#-contribution)

---

## 🌟 Fonctionnalités

### 🏆 **Fonctionnalités Premium v2.1.5**

#### 💰 **Gestion des Cautions Stripe**
- ✅ **Empreinte de carte bancaire** sécurisée (PCI DSS)
- ✅ **Pré-autorisation** sans débit immédiat
- ✅ **Capture automatique** ou manuelle selon configuration
- ✅ **Libération intelligente** après réservation réussie
- ✅ **Remboursements** automatiques avec gestion des raisons
- ✅ **Multi-devises** et support international
- ✅ **SCA/3DS compliance** pour DSP2

#### 📅 **Calendriers Interactifs Doubles**
- ✅ **Calendrier disponibilités** avec gestion avancée des créneaux
- ✅ **Calendrier réservations** avec vue consolidée
- ✅ **Créneaux récurrents** (quotidien, hebdomadaire, mensuel)
- ✅ **Drag & drop** pour modifications rapides
- ✅ **Vue multi-éléments** simultanée
- ✅ **Export iCal** pour synchronisation externe

#### 🎛️ **Administration Avancée**
- ✅ **Interface moderne** avec tableaux de bord interactifs
- ✅ **Gestion en lot** pour actions multiples
- ✅ **Historique complet** avec audit trail
- ✅ **Statistiques temps réel** avec graphiques
- ✅ **Export de données** (CSV, PDF, Excel)
- ✅ **Notifications automatiques** personnalisables

#### 🔄 **Intégration E-commerce**
- ✅ **Liaison produits** PrestaShop automatique
- ✅ **Synchronisation prix** bidirectionnelle
- ✅ **Gestion stocks** comme disponibilités
- ✅ **Commandes automatiques** après validation
- ✅ **Facturation intégrée** avec TVA
- ✅ **Hooks PrestaShop** pour modules tiers

### 🎯 **Fonctionnalités Core**

#### 📱 **Interface Client Moderne**
- ✅ **Design responsive** adaptatif tous écrans
- ✅ **Processus simplifié** en 4 étapes claires
- ✅ **Validation temps réel** des formulaires
- ✅ **Messages d'erreur** localisés et clairs
- ✅ **Accessibilité WCAG** niveau AA
- ✅ **PWA ready** pour expérience mobile

#### 🔐 **Sécurité et Performance**
- ✅ **Chiffrement des données** sensibles
- ✅ **Protection CSRF** sur toutes les actions
- ✅ **Rate limiting** contre les abus
- ✅ **Cache intelligent** avec invalidation
- ✅ **Logs sécurisés** avec rotation
- ✅ **Monitoring santé** du système

#### 🌍 **Multi-langue et Localisation**
- ✅ **Support complet** des langues PrestaShop
- ✅ **Formats régionaux** (dates, devises, heures)
- ✅ **Templates d'emails** multi-langues
- ✅ **Interface admin** traduite
- ✅ **RTL support** pour langues droite-à-gauche

---

## 🎯 Démonstration

### 🖼️ **Captures d'écran**

<table>
<tr>
<td width="33%">

**🏠 Interface Client**
![Interface Client](https://via.placeholder.com/300x200/3498db/white?text=Interface+Moderne)
*Design responsive avec processus en 4 étapes*

</td>
<td width="33%">

**💳 Caution Stripe**
![Caution Stripe](https://via.placeholder.com/300x200/27ae60/white?text=Caution+S%C3%A9curis%C3%A9e)
*Empreinte CB avec Stripe Elements*

</td>
<td width="33%">

**📊 Dashboard Admin**
![Dashboard Admin](https://via.placeholder.com/300x200/e74c3c/white?text=Dashboard+Admin)
*Statistiques et gestion avancée*

</td>
</tr>
</table>

### 🎬 **Vidéo de démonstration**

[![Démonstration v2.1.5](https://img.youtube.com/vi/VIDEO_ID/maxresdefault.jpg)](https://www.youtube.com/watch?v=VIDEO_ID)

*Découvrez toutes les fonctionnalités en 5 minutes*

### 🌐 **Démo en ligne**

- **🏪 Boutique démo** : [demo.booking-module.com](https://demo.booking-module.com)
- **👨‍💼 Admin démo** : [admin.booking-module.com](https://admin.booking-module.com) 
  - Login : `demo@booking.com` | Pass : `DemoBooking2025`

---

## ⚡ Installation rapide

### 📋 **Prérequis**
- ✅ PrestaShop 1.7.6+ ou 8.x
- ✅ PHP 7.4+ (recommandé : 8.1+)
- ✅ MySQL 5.7+ ou MariaDB 10.2+
- ✅ Extensions PHP : `curl`, `json`, `openssl`, `mbstring`
- ✅ Compte Stripe (test ou live)

### 🚀 **Installation en 3 étapes - VERSION CORRIGÉE v2.1.5**

#### 1️⃣ **Téléchargement et upload**
```bash
# Télécharger la dernière version corrigée
wget https://github.com/FastmanTheDuke/prestashop-booking-module/archive/v2.1.5.zip

# Ou cloner le repository
git clone https://github.com/FastmanTheDuke/prestashop-booking-module.git
```

#### 2️⃣ **Installation via PrestaShop - 100% FIABLE**
1. 📁 Copier le dossier dans `/modules/booking/`
2. 🎛️ Aller dans **Modules > Gestionnaire de modules**
3. 🔍 Rechercher "Booking" et cliquer **Installer**
4. ✅ **L'installation se déroule automatiquement en 8 étapes sécurisées**

#### 3️⃣ **Vérification post-installation - NOUVEAU**
```sql
-- Vérifier que toutes les tables ont été créées
SHOW TABLES LIKE 'ps_booking%';
SHOW TABLES LIKE 'ps_booker%';

-- Résultat attendu : 11 tables créées
-- ps_booker, ps_booker_auth, ps_booker_auth_reserved
-- ps_booker_product, ps_booker_reservation_order, ps_booker_lang
-- ps_booking_customers, ps_booking_deposits, ps_booking_deposit_history
-- ps_booking_webhooks, ps_booking_deposit_config, ps_booking_activity_log
```

#### 🆘 **En cas de problème d'installation**
```bash
# Consulter les logs d'installation
tail -f var/logs/prestashop.log | grep "Booking"

# Réinstaller proprement
1. Désinstaller le module
2. Supprimer le dossier modules/booking/
3. Réinstaller avec la v2.1.5
```

### ⚡ **Installation automatique**
```bash
# Script d'installation automatique (Linux/macOS)
curl -sSL https://raw.githubusercontent.com/FastmanTheDuke/prestashop-booking-module/main/scripts/install.sh | bash
```

---

## 🔧 Configuration

### 🎛️ **Configuration de base**

#### **Paramètres généraux**
- **Prix par défaut** : Tarif de base pour les réservations
- **Durée par défaut** : Durée standard des créneaux (60 min)
- **Délai minimum** : Délai avant réservation (24h)
- **Confirmation automatique** : Validation sans intervention manuelle

#### **Horaires d'ouverture**
- **Jours autorisés** : Lundi à dimanche configurables
- **Heures d'ouverture** : 8h00 - 20h00 par défaut
- **Créneaux** : Durée minimale des réservations
- **Pauses** : Gestion des créneaux indisponibles

### 💳 **Configuration Stripe avancée**

#### **🔐 Clés API**
```php
// Mode test (développement)
BOOKING_STRIPE_TEST_MODE = true
BOOKING_STRIPE_TEST_PUBLIC_KEY = "pk_test_..."
BOOKING_STRIPE_TEST_SECRET_KEY = "sk_test_..."

// Mode live (production)
BOOKING_STRIPE_LIVE_PUBLIC_KEY = "pk_live_..."
BOOKING_STRIPE_LIVE_SECRET_KEY = "sk_live_..."
```

#### **🏦 Paramètres des cautions**
- **Taux de caution** : 30% du montant total (configurable)
- **Montant minimum** : 50€ (protection minimale)
- **Montant maximum** : 2000€ (limite réglementaire)
- **Délai de capture** : 24h avant réservation
- **Délai de libération** : 7 jours après réservation

#### **🔗 Configuration webhook**
1. **Dashboard Stripe** > Développeurs > Webhooks
2. **URL endpoint** : `https://votresite.com/modules/booking/webhook/stripe_handler.php`
3. **Événements à écouter** :
   ```
   setup_intent.succeeded
   setup_intent.setup_failed
   payment_intent.requires_capture
   payment_intent.succeeded
   payment_intent.payment_failed
   payment_intent.canceled
   charge.captured
   charge.dispute.created
   charge.refunded
   ```

### 📧 **Configuration des notifications**

#### **Templates d'emails personnalisables**
- ✉️ **Confirmation de réservation** avec détails
- ✉️ **Rappel automatique** 24h avant
- ✉️ **Caution autorisée** avec explications
- ✉️ **Caution libérée** avec confirmation
- ✉️ **Annulation** avec conditions de remboursement

#### **Variables disponibles**
```smarty
{booking_reference}     # Référence unique
{customer_name}         # Nom du client
{date_reserved}         # Date de réservation
{hour_from} - {hour_to} # Créneaux horaires
{total_price}           # Montant total
{deposit_amount}        # Montant de caution
{booker_name}           # Nom de l'élément réservé
{status}                # Statut actuel
```

---

## 📊 Interface d'administration

### 🏠 **Tableau de bord principal**

#### **📈 Métriques temps réel**
- 📊 **Réservations du jour** avec évolution
- 💰 **Chiffre d'affaires** mensuel et annuel
- 🏦 **Cautions en cours** avec statuts
- 📈 **Taux de conversion** par élément
- ⏰ **Créneaux disponibles** par période

#### **🚨 Alertes et notifications**
- ⚠️ **Cautions en attente** de traitement
- 🔄 **Webhooks en échec** à reprendre
- 📅 **Réservations expirées** à nettoyer
- 💳 **Problèmes de paiement** à résoudre

### 🗂️ **Modules d'administration**

<table>
<tr>
<th>Module</th>
<th>Description</th>
<th>Fonctionnalités clés</th>
</tr>
<tr>
<td><strong>🏪 Éléments & Produits</strong></td>
<td>Gestion des éléments réservables</td>
<td>• Création/édition<br>• Liaison produits<br>• Synchronisation prix</td>
</tr>
<tr>
<td><strong>📅 Disponibilités</strong></td>
<td>Gestion des créneaux disponibles</td>
<td>• Créneaux récurrents<br>• Actions en lot<br>• Import/Export</td>
</tr>
<tr>
<td><strong>📝 Réservations</strong></td>
<td>Suivi des réservations clients</td>
<td>• Validation/Annulation<br>• Historique complet<br>• Communication client</td>
</tr>
<tr>
<td><strong>🏦 Cautions Stripe</strong></td>
<td>Gestion des cautions intelligentes</td>
<td>• Vue détaillée<br>• Actions manuelles<br>• Historique transactions</td>
</tr>
<tr>
<td><strong>📊 Statistiques</strong></td>
<td>Analytics et rapports</td>
<td>• Graphiques interactifs<br>• Export de données<br>• Analyse de performance</td>
</tr>
</table>

### 📱 **Interface responsive**
- 💻 **Desktop** : Interface complète avec tous les outils
- 📱 **Mobile** : Interface adaptée pour gestion nomade
- 📟 **Tablette** : Optimisée pour consultations terrain

---

## 🛡️ Sécurité et conformité

### 🔒 **Sécurité des données**

#### **Chiffrement et protection**
- 🔐 **Chiffrement AES-256** pour données sensibles
- 🛡️ **Hachage bcrypt** pour mots de passe
- 🔑 **Tokens CSRF** sur toutes les actions
- 🚫 **Échappement SQL** systématique
- 🔍 **Validation stricte** des entrées

#### **Conformité PCI DSS**
- ✅ **Aucune donnée CB stockée** localement
- ✅ **Empreinte uniquement** via Stripe
- ✅ **HTTPS obligatoire** pour paiements
- ✅ **Logs sécurisés** sans données sensibles
- ✅ **Audit trail** complet des actions

### 📋 **Conformité réglementaire**

#### **RGPD (Europe)**
- ✅ **Consentement explicite** pour traitement données
- ✅ **Droit à l'oubli** avec suppression complète
- ✅ **Portabilité des données** avec export
- ✅ **Minimisation** des données collectées
- ✅ **Registre des traitements** intégré

#### **DSP2 (Strong Customer Authentication)**
- ✅ **3D Secure** automatique si requis
- ✅ **Exemptions** intelligentes (montants faibles)
- ✅ **Fallback** en cas d'échec SCA
- ✅ **Compliance** totale réglementations

### 🔍 **Monitoring et logs**

#### **Logs sécurisés**
- 📝 **Audit trail** complet des actions admin
- 🔍 **Logs de connexion** avec détection anomalies
- 💳 **Traçabilité paiements** sans données sensibles
- 🚨 **Alertes automatiques** sur événements suspects
- 🗃️ **Rétention configurée** selon réglementations

---

## 🔗 API et intégrations

### 🔌 **Intégrations natives**

#### **PrestaShop**
- 🛒 **Produits** : Synchronisation bidirectionnelle
- 👥 **Clients** : Intégration comptes existants
- 📦 **Commandes** : Création automatique après validation
- 💰 **Paiements** : Hooks natifs PrestaShop
- 📧 **Emails** : Templates système PrestaShop

#### **Stripe**
- 💳 **Payments API** : Paiements et cautions
- 🔗 **Webhooks** : Synchronisation temps réel
- 🌍 **Connect** : Marketplace (prévu v2.2)
- 📊 **Reporting** : Accès données via API
- 🔐 **Elements** : Interface sécurisée

### 🚀 **API REST (prévu v2.2)**

#### **Endpoints prévus**
```bash
# Gestion des réservations
GET    /api/bookings              # Liste des réservations
POST   /api/bookings              # Nouvelle réservation
GET    /api/bookings/{id}         # Détail réservation
PUT    /api/bookings/{id}         # Modification
DELETE /api/bookings/{id}         # Annulation

# Gestion des disponibilités
GET    /api/availability/{id}     # Disponibilités élément
POST   /api/availability          # Nouvelle disponibilité

# Gestion des cautions
GET    /api/deposits              # Liste des cautions
POST   /api/deposits/{id}/capture # Capturer caution
POST   /api/deposits/{id}/release # Libérer caution
```

### 🔗 **Intégrations tierces**

#### **Google Calendar** (prévu v2.2)
- 📅 **Synchronisation bidirectionnelle** réservations
- 🔄 **Mise à jour temps réel** disponibilités
- 👥 **Calendriers multiples** par élément
- 🌍 **Fuseaux horaires** automatiques

#### **Zapier/IFTTT** (prévu v2.3)
- ⚡ **Triggers** sur nouveaux événements
- 🔄 **Actions** automatisées
- 📧 **Notifications** multi-canaux
- 📊 **Reporting** vers outils BI

---

## 🧪 Tests et développement

### 🔬 **Tests automatisés**

#### **Tests unitaires**
```bash
# Installation des dépendances de test
composer install --dev

# Exécution des tests
./vendor/bin/phpunit tests/

# Tests avec couverture
./vendor/bin/phpunit --coverage-html coverage/
```

#### **Tests d'intégration Stripe**
```bash
# Tests avec cartes de test Stripe
npm run test:stripe

# Test webhook local
stripe listen --forward-to localhost/modules/booking/webhook/stripe_handler.php
```

### 🛠️ **Environnement de développement**

#### **Configuration Docker**
```yaml
# docker-compose.yml
services:
  prestashop:
    image: prestashop/prestashop:latest
    environment:
      - DB_SERVER=mysql
      - PS_INSTALL_AUTO=1
    volumes:
      - ./booking:/var/www/html/modules/booking
  
  mysql:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: prestashop
      MYSQL_ROOT_PASSWORD: root
```

#### **Outils de développement**
- 🔍 **Xdebug** : Débogage PHP pas-à-pas
- 📝 **PHPStan** : Analyse statique du code
- 🎨 **PHP-CS-Fixer** : Formatage automatique
- 📊 **PHPMetrics** : Métriques de qualité

### 🧪 **Tests de charge**

#### **Simulation de charge**
```bash
# Test de montée en charge avec Apache Bench
ab -n 1000 -c 10 http://localhost/modules/booking/

# Test avec K6 pour scénarios complexes
k6 run tests/load/booking-scenario.js
```

---

## 💡 Cas d'usage

### 🏨 **Hôtellerie et restauration**

#### **Restaurant avec réservations de tables**
- 🍽️ **Tables** comme éléments réservables
- ⏰ **Services** : déjeuner (12h-14h), dîner (19h-22h)
- 💳 **Caution** : 20€ par personne pour groupes 8+
- 📧 **Rappel** : 2h avant réservation
- 🚫 **Annulation** : gratuite jusqu'à 6h avant

#### **Hôtel avec chambres**
- 🛏️ **Chambres** par type et étage
- 📅 **Nuitées** avec check-in/check-out
- 💰 **Caution** : 100€ pour dommages
- 🔄 **Synchronisation** avec PMS existant
- 📊 **Reporting** occupation par période

### 🚗 **Location et services**

#### **Location de véhicules**
- 🚗 **Véhicules** avec caractéristiques
- 📍 **Agences** multiples de retrait
- 💳 **Caution** : selon catégorie véhicule
- 📋 **État des lieux** avant/après
- 🛡️ **Assurance** options complémentaires

#### **Coworking et bureaux**
- 🏢 **Espaces** : bureaux, salles réunion
- ⏰ **Créneaux** : heure, demi-journée, journée
- 💳 **Caution** : équipements techniques
- 🔑 **Accès** : codes temporaires
- 📊 **Facturation** automatique

### 🎓 **Formation et événements**

#### **Centre de formation**
- 📚 **Salles** avec capacités différentes
- 👨‍🏫 **Formateurs** et disponibilités
- 💻 **Équipements** : projecteurs, ordinateurs
- 📅 **Planning** : sessions récurrentes
- 📝 **Certificats** : génération automatique

#### **Organisateur d'événements**
- 🎪 **Espaces** modulables selon événement
- 🎤 **Prestataires** : traiteur, DJ, déco
- 💰 **Devis** : complexes avec options
- 📋 **Planning** : préparation et démontage
- 📸 **Portfolio** : galerie de réalisations

### 💪 **Sport et bien-être**

#### **Salle de sport avec cours**
- 🏋️ **Cours collectifs** avec instructeurs
- 👥 **Capacité limitée** par cours
- 💳 **Caution** : pour matériel spécialisé
- 📊 **Suivi** : assiduité et progression
- 💰 **Abonnements** : intégration crédits

#### **Spa et centre de bien-être**
- 💆 **Soins** avec thérapeutes spécialisés
- 🛁 **Équipements** : sauna, hammam, jacuzzi
- ⏰ **Durées variables** selon prestations
- 🎁 **Packages** : combinaisons de soins
- 💝 **Cartes cadeaux** : intégration native

---

## 🎨 Personnalisation

### 🖌️ **Customisation de l'interface**

#### **CSS personnalisé**
```css
/* Personnalisation des couleurs */
:root {
    --booking-primary: #your-brand-color;
    --booking-secondary: #your-secondary-color;
    --booking-accent: #your-accent-color;
}

/* Customisation du calendrier */
.fc-event-booking {
    background: linear-gradient(45deg, #your-color1, #your-color2);
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}
```

#### **Templates Smarty**
```smarty
{* Template personnalisé pour réservation *}
{extends file='page.tpl'}

{block name='page_content'}
    <div class="my-custom-booking-interface">
        {include file='module:booking/views/templates/front/booking_custom.tpl'}
    </div>
{/block}
```

### ⚙️ **Extensions et hooks**

#### **Hooks personnalisés**
```php
// Hook avant création réservation
public function hookBeforeBookingCreate($params) {
    $reservation = $params['reservation'];
    
    // Logique métier personnalisée
    if ($this->validateCustomRules($reservation)) {
        return true;
    }
    
    return false;
}

// Hook après paiement caution
public function hookAfterDepositPayment($params) {
    $deposit = $params['deposit'];
    
    // Intégration système tiers
    $this->notifyExternalSystem($deposit);
}
```

#### **Classes d'extension**
```php
// Extension de la classe Booker
class CustomBooker extends Booker {
    
    public function getAvailabilityWithCustomRules($date_from, $date_to) {
        $availability = parent::getAvailability($date_from, $date_to);
        
        // Règles métier spécifiques
        return $this->applyBusinessRules($availability);
    }
}
```

### 🔌 **Intégrations personnalisées**

#### **API externe**
```php
// Synchronisation avec système externe
class ExternalSystemSync {
    
    public function syncReservation($reservation) {
        $api_client = new ExternalApiClient();
        
        return $api_client->createBooking([
            'reference' => $reservation->booking_reference,
            'customer' => $reservation->getCustomerData(),
            'dates' => $reservation->getDateRange(),
            'amount' => $reservation->total_price
        ]);
    }
}
```

#### **Notifications personnalisées**
```php
// Système de notifications avancé
class CustomNotificationSystem extends BookingNotificationSystem {
    
    public function sendCustomNotification($type, $data) {
        switch ($type) {
            case 'sms':
                return $this->sendSMS($data);
            case 'slack':
                return $this->sendSlackMessage($data);
            case 'webhook':
                return $this->callWebhook($data);
        }
    }
}
```

---

## 📞 Support

### 🆘 **Support technique**

#### **Canaux de support**
- 📧 **Email** : support@booking-module.com
- 💬 **Discord** : [Rejoindre la communauté](https://discord.gg/booking-module)
- 🐛 **GitHub Issues** : [Signaler un bug](https://github.com/FastmanTheDuke/prestashop-booking-module/issues)
- 📞 **Support premium** : support-premium@booking-module.com

#### **Heures de support**
- 🕐 **Communautaire** : 24h/7j via Discord et GitHub
- 🕘 **Email** : Lun-Ven 9h-18h (GMT+1)
- ⚡ **Premium** : Lun-Ven 8h-20h, Sam 10h-16h

### 📚 **Ressources**

#### **Documentation**
- 📖 **Wiki complet** : [GitHub Wiki](https://github.com/FastmanTheDuke/prestashop-booking-module/wiki)
- 🎥 **Tutoriels vidéo** : [YouTube Channel](https://youtube.com/channel/booking-module)
- 📝 **Blog technique** : [blog.booking-module.com](https://blog.booking-module.com)
- 🔧 **Guide développeur** : [docs.booking-module.com](https://docs.booking-module.com)

#### **Formation**
- 🎓 **Webinaires gratuits** : Chaque mardi 14h
- 🏫 **Formation sur site** : Disponible sur demande
- 💻 **Certification** : Programme certifiant officiel
- 👥 **Communauté** : Forum d'entraide active

### 🚨 **Résolution de problèmes v2.1.5**

#### **Problèmes fréquents**

<details>
<summary><strong>🔧 Problème d'installation corrigé v2.1.5</strong></summary>

**Problème** : Erreur "Table 'booking_deposit_history' doesn't exist"

**Solution v2.1.5** : 
1. Le problème a été **définitivement corrigé** dans la v2.1.5
2. L'installation se fait maintenant en 8 étapes sécurisées
3. Les contraintes de clé étrangère sont ajoutées après création des tables

```bash
# Vérifier l'installation réussie
mysql -u user -p database -e "SHOW TABLES LIKE 'ps_booking%';"

# Résultat attendu : 11 tables
```
</details>

<details>
<summary><strong>🔧 Configuration Stripe</strong></summary>

**Problème** : Erreur "Invalid API key"
```bash
# Vérifier la configuration
php bin/console booking:stripe:test

# Vérifier les logs
tail -f modules/booking/logs/stripe.log
```

**Solution** : 
1. Vérifier que les clés correspondent à l'environnement (test/live)
2. S'assurer que les clés ne contiennent pas d'espaces
3. Vérifier les permissions du compte Stripe
</details>

<details>
<summary><strong>📡 Webhooks non reçus</strong></summary>

**Problème** : Les statuts de caution ne se mettent pas à jour

**Solution** :
1. Vérifier l'URL du webhook dans Stripe Dashboard
2. Tester manuellement : `curl -X POST https://votresite.com/modules/booking/webhook/stripe_handler.php`
3. Vérifier les logs du serveur web
4. Confirmer que le secret webhook est correct
</details>

<details>
<summary><strong>🗄️ Erreurs de base de données - RÉSOLU v2.1.5</strong></summary>

**Problème** : Tables manquantes après installation

**Solution v2.1.5** :
```sql
-- La v2.1.5 créé automatiquement toutes les tables
-- Vérifier avec :
SHOW TABLES LIKE 'ps_booking%';
SHOW TABLES LIKE 'ps_booker%';

-- Si problème, réinstaller le module v2.1.5
```
</details>

---

## 🤝 Contribution

### 👨‍💻 **Comment contribuer**

#### **Types de contributions**
- 🐛 **Rapports de bugs** avec reproduction détaillée
- ✨ **Nouvelles fonctionnalités** avec spécifications
- 📚 **Documentation** et amélioration des guides
- 🌍 **Traductions** dans nouvelles langues
- 🧪 **Tests** et amélioration de la couverture

#### **Processus de contribution**
1. 🍴 **Fork** le repository
2. 🌿 **Créer une branche** pour votre fonctionnalité
3. 💻 **Développer** avec tests appropriés
4. 📝 **Documenter** les changements
5. 🔄 **Pull Request** avec description détaillée

### 📋 **Guidelines de développement**

#### **Standards de code**
```bash
# Vérification de la qualité
composer run-script check-quality

# Format automatique
composer run-script fix-style

# Tests avant commit
composer run-script test-all
```

#### **Convention des commits**
```
type(scope): description

Types: feat, fix, docs, style, refactor, test, chore
Scopes: stripe, calendar, admin, front, core

Exemples:
feat(stripe): add automatic deposit capture
fix(calendar): resolve timezone display issue
fix(install): resolve booking_deposit_history table creation
docs(readme): update installation instructions
```

### 🏆 **Contributeurs**

#### **Hall of Fame**
<table>
<tr>
<td align="center">
<img src="https://github.com/FastmanTheDuke.png" width="60px" alt="FastmanTheDuke"/>
<br><strong>FastmanTheDuke</strong>
<br>🏗️ Architecture & Core
</td>
<td align="center">
<img src="https://github.com/contributor2.png" width="60px" alt="Contributor"/>
<br><strong>Contributor 2</strong>
<br>🎨 UI/UX Design
</td>
<td align="center">
<img src="https://github.com/contributor3.png" width="60px" alt="Contributor"/>
<br><strong>Contributor 3</strong>
<br>🧪 Testing & QA
</td>
</tr>
</table>

#### **Remerciements spéciaux**
- 💝 **Communauté PrestaShop** pour les retours constants
- 🎯 **Beta testeurs** pour leur patience et feedback
- 🔧 **Équipe Stripe** pour le support technique excellent
- 🌟 **Tous les utilisateurs** qui font vivre ce projet

---

## 📜 License

Ce projet est sous licence **MIT** - voir le fichier [LICENSE](LICENSE) pour plus de détails.

### 📄 **Termes principaux**
- ✅ **Usage commercial** autorisé
- ✅ **Modification** et redistribution autorisées
- ✅ **Usage privé** libre
- ❌ **Aucune garantie** fournie
- ❌ **Aucune responsabilité** de l'auteur

---

## 🔄 **Changelog et versions**

### 📅 **Historique des versions**
- **v2.1.5** (2025-06-17) - 🔧 **CORRECTION MAJEURE** : Installation bulletproof - Table booking_deposit_history corrigée
- **v2.1.4** (2025-06-16) - 🏦 Système de cautions Stripe
- **v2.1.3** (2025-06-15) - 🔧 Optimisations et corrections
- **v2.1.2** (2025-06-14) - 📊 Tableaux de bord avancés
- **v2.1.0** (2025-01-15) - 📅 Double calendrier séparé

### 🔮 **Roadmap**
- **v2.2.0** (Q3 2025) - 🔗 API REST complète
- **v2.3.0** (Q1 2026) - 🤖 Intelligence artificielle
- **v3.0.0** (Q3 2026) - 🌍 Multi-tenant et marketplace

---

<div align="center">

### 💙 **Fait avec amour pour la communauté PrestaShop**

[![GitHub stars](https://img.shields.io/github/stars/FastmanTheDuke/prestashop-booking-module?style=social)](https://github.com/FastmanTheDuke/prestashop-booking-module/stargazers)
[![GitHub forks](https://img.shields.io/github/forks/FastmanTheDuke/prestashop-booking-module?style=social)](https://github.com/FastmanTheDuke/prestashop-booking-module/network/members)
[![GitHub watchers](https://img.shields.io/github/watchers/FastmanTheDuke/prestashop-booking-module?style=social)](https://github.com/FastmanTheDuke/prestashop-booking-module/watchers)

**⭐ N'oubliez pas de donner une étoile si ce projet vous aide !**

[📥 Télécharger v2.1.5](https://github.com/FastmanTheDuke/prestashop-booking-module/releases/latest) • [📚 Documentation](https://github.com/FastmanTheDuke/prestashop-booking-module/wiki) • [💬 Discord](https://discord.gg/booking-module) • [🐛 Issues](https://github.com/FastmanTheDuke/prestashop-booking-module/issues)

</div>
