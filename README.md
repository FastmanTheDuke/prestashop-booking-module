# Module PrestaShop - Système de Réservations Avancé v2.1

![Version](https://img.shields.io/badge/version-2.1.0-blue.svg)
![PrestaShop](https://img.shields.io/badge/PrestaShop-1.7+-green.svg)
![PHP](https://img.shields.io/badge/PHP-7.2+-blue.svg)
![License](https://img.shields.io/badge/license-MIT-green.svg)

## 🚀 Nouveautés v2.1

### ✨ **Calendriers doubles séparés**
- **📅 Calendrier des Disponibilités** : Interface dédiée pour gérer les créneaux disponibles
- **📋 Calendrier des Réservations** : Interface séparée pour valider et gérer les réservations clients
- **🎯 Navigation intuitive** avec FullCalendar 6.x et interface moderne

### 🔧 **Fonctionnalités avancées**
- **⚡ Création en lot** de disponibilités avec récurrence
- **📋 Actions en lot** : validation/annulation de plusieurs réservations
- **📧 Système de notifications** personnalisées avec templates
- **🔄 Copie de semaines** pour dupliquer rapidement les disponibilités
- **💳 Intégration Stripe** avec gestion des cautions et empreinte CB
- **📊 Statistiques avancées** et métriques en temps réel

### 🛠️ **Améliorations techniques**
- **🎨 Interface moderne** responsive avec CSS avancé
- **⚡ Performance optimisée** avec AJAX et cache
- **🔒 Sécurité renforcée** avec validation CSRF et sanitisation
- **📱 Mobile-first** design adaptatif
- **🌐 Multi-langues** et multi-devises

## 📁 Structure du projet

```
booking/
├── 📄 booking.php                      # Module principal v2.1
├── 📁 classes/                         # Classes métier
│   ├── 🔧 Booker.php                  # Éléments réservables  
│   ├── ⏰ BookerAuth.php              # Gestion des disponibilités
│   └── 🎫 BookerAuthReserved.php      # Gestion des réservations
├── 📁 controllers/
│   ├── 📁 admin/                      # Contrôleurs administration
│   │   ├── AdminBooker.php
│   │   ├── AdminBookerAuth.php
│   │   ├── AdminBookerAuthReserved.php
│   │   ├── 📅 AdminBookerAvailabilityCalendar.php  # NOUVEAU
│   │   ├── 📋 AdminBookerReservationCalendar.php   # NOUVEAU  
│   │   └── ⚙️ AdminBookerSettings.php              # NOUVEAU
│   └── 📁 front/                      # Contrôleurs front-office
├── 📁 views/
│   ├── 📁 templates/
│   │   ├── 📁 admin/                  # Templates administration
│   │   │   ├── 📅 availability_calendar.tpl        # NOUVEAU
│   │   │   ├── 📋 reservation_calendar.tpl         # NOUVEAU
│   │   │   └── ⚙️ settings.tpl                    # NOUVEAU
│   │   └── 📁 front/                  # Templates front-office
│   ├── 📁 css/                        # Styles
│   │   ├── 🎨 admin-calendar.css                   # NOUVEAU
│   │   └── 📱 booking-responsive.css               # NOUVEAU
│   └── 📁 js/                         # Scripts JavaScript
│       ├── 📅 availability-calendar.js             # NOUVEAU
│       ├── 📋 reservation-calendar.js              # NOUVEAU
│       └── ⚡ booking-utils.js                     # NOUVEAU
├── 📁 mails/                          # Templates emails
└── 📁 sql/                            # Scripts base de données
```

## 🛠️ Installation

### 1. **Téléchargement**
```bash
git clone https://github.com/FastmanTheDuke/prestashop-booking-module.git
```

### 2. **Installation sur PrestaShop**
```bash
cp -r prestashop-booking-module /path/to/prestashop/modules/booking
```

### 3. **Activation**
1. Aller dans **Modules → Gestionnaire de modules**
2. Rechercher "**Système de Réservations Avancé**"
3. Cliquer sur "**Installer**"

### 4. **Configuration**
- Accéder à **RESERVATIONS** dans le menu administrateur
- Utiliser l'onglet **⚙️ Configuration** pour tous les paramètres

## 🎯 Guide d'utilisation

### 📋 **Gestion des éléments réservables**
1. **RESERVATIONS → Éléments & Produits**
2. Créer vos bateaux/véhicules/services
3. Associer à des produits PrestaShop existants
4. Définir prix, durée et capacité

### 📅 **Calendrier des Disponibilités**
1. **RESERVATIONS → 📅 Calendrier Disponibilités**
2. **Créer des créneaux** : clic sur une date/heure
3. **Actions en lot** : sélection multiple + actions
4. **Copie de semaine** : dupliquer rapidement
5. **Récurrence** : créneaux automatiques

**🎯 Fonctionnalités clés :**
- ✅ Glisser-déposer pour déplacer
- ✅ Redimensionner pour ajuster la durée  
- ✅ Menu contextuel (clic droit)
- ✅ Filtrage par élément
- ✅ Export CSV/Excel

### 📋 **Calendrier des Réservations**
1. **RESERVATIONS → 📋 Calendrier Réservations**
2. **Validation** des réservations en attente
3. **Actions en lot** : Ctrl+clic pour sélectionner
4. **Gestion des statuts** avancés
5. **Notifications** personnalisées

**🎯 Statuts disponibles :**
- 🟡 **En attente** : Nouvelle réservation
- 🔵 **Confirmé** : Validé par admin
- 🟢 **Payé** : Paiement effectué
- 🔴 **Annulé** : Réservation annulée
- 🟣 **Terminé** : Service effectué
- 🟠 **Remboursé** : Montant remboursé

### ⚙️ **Configuration avancée**

#### **Paramètres généraux**
```php
Prix par défaut : 50.00€
Durée standard : 60 minutes
Réservations max : Illimité
Délai minimum : 2 heures
Expiration : 24 heures
```

#### **Paiement et Stripe**
```php
Stripe activé : Oui/Non
Caution obligatoire : Montant
Empreinte CB : Oui/Non
Remboursement automatique : Oui/Non
```

#### **Notifications**
```php
Email confirmation : Automatique
Rappels : 24h avant
Notifications admin : Oui/Non
Templates personnalisés : Disponibles
```

## 💳 Intégration Stripe

### **Configuration**
1. Installer le module **Stripe Payments** officiel
2. Configurer les clés API dans **RESERVATIONS → Configuration**
3. Activer les options de caution

### **Fonctionnalités Stripe**
- 💳 **Empreinte CB** : Autorisation sans débit
- 🔒 **Caution bloquée** : Montant gelé
- ⚡ **Capture différée** : Débit à la validation
- 🔄 **Remboursement automatique** : En cas d'annulation
- 📊 **Reporting intégré** : Suivi des transactions

## 🔧 Développement et personnalisation

### **Hooks disponibles**
```php
// Nouveaux hooks v2.1
actionBookingValidated          // Après validation réservation
actionBookingCancelled          // Après annulation  
actionBookingPaymentReceived    // Après paiement confirmé
displayBookingCalendarHeader    // Dans l'en-tête calendrier
displayBookingReservationForm   // Dans le formulaire réservation
```

### **API REST (à venir v2.2)**
```javascript
// Exemples d'endpoints futurs
GET    /api/bookings/availabilities
POST   /api/bookings/reservations  
PUT    /api/bookings/reservations/{id}
DELETE /api/bookings/reservations/{id}
```

### **Classes principales**
```php
// Gestion des éléments réservables
$booker = new Booker($id);
$booker->name = "Bateau Premium";
$booker->price = 89.50;
$booker->save();

// Gestion des disponibilités  
$availability = new BookerAuth();
$availability->id_booker = $booker->id;
$availability->date_from = "2025-07-15 09:00:00";
$availability->max_bookings = 1;
$availability->save();

// Gestion des réservations
$reservation = new BookerAuthReserved();
$reservation->validate("Réservation approuvée");
$reservation->createOrder(); // Création commande PrestaShop
```

## 📊 Métriques et statistiques

### **Dashboard principal**
- 📈 **Éléments actifs** : Nombre d'éléments disponibles
- ⏰ **Créneaux disponibles** : Total des disponibilités
- 🎫 **Réservations actives** : En cours et confirmées  
- 💰 **CA mensuel** : Chiffre d'affaires du mois

### **Rapports détaillés**
- 📋 **Export CSV** : Toutes les données
- 📊 **Graphiques** : Évolution temporelle
- 🔍 **Filtres avancés** : Par période, statut, élément
- 📧 **Rapports automatiques** : Envoi programmé

## 🚀 Roadmap v2.2

### **Prochaines fonctionnalités**
- 🌐 **API REST complète** : Intégration tierce
- 📱 **Application mobile** : Gestion nomade  
- 🤖 **Intelligence artificielle** : Optimisation automatique
- 🔗 **Synchronisation calendriers** : Google, Outlook
- 💬 **Chat intégré** : Support client en temps réel
- 📋 **Workflow avancé** : Processus personnalisés

### **Améliorations techniques**
- ⚡ **Performance** : Cache Redis, optimisations
- 🔒 **Sécurité** : Audit complet, 2FA
- 🌍 **Internationalisation** : Plus de langues
- 📊 **Analytics** : Métriques détaillées
- 🔧 **Personnalisation** : Thèmes et widgets

## 💡 Cas d'usage

### **🚤 Location de bateaux**
- Gestion flotte de bateaux
- Réservations demi-journée/journée
- Caution obligatoire
- Météo et conditions de mer

### **🚗 Location de véhicules** 
- Parc automobile varié
- Réservations longue durée
- Assurances incluses
- Kilométrage illimité

### **🏠 Hébergements saisonniers**
- Gîtes et chambres d'hôtes  
- Réservations multi-jours
- Nettoyage automatique
- Check-in/check-out flexibles

### **⚽ Terrains de sport**
- Courts de tennis, terrains foot
- Réservations horaires
- Éclairage automatique
- Matériel inclus

### **👨‍⚕️ Consultations médicales**
- Rendez-vous praticiens
- Créneaux personnalisés
- Rappels automatiques
- Téléconsultations

## ❓ FAQ

### **Questions fréquentes**

**Q: Puis-je limiter les réservations par client ?**
R: Oui, dans Configuration → Paramètres avancés → Limites par client

**Q: Comment gérer les annulations ?**
R: Système de délais configurables + remboursement automatique possible

**Q: Le module est-il compatible multi-boutique ?**
R: Oui, gestion complète des contextes multi-boutique PrestaShop

**Q: Puis-je personnaliser les emails ?**  
R: Oui, templates Smarty modifiables dans `/mails/`

**Q: Comment sauvegarder mes données ?**
R: Export automatique possible + intégration solutions de sauvegarde

### **Support technique**

- 📧 **Email** : support@mdxp.io
- 💬 **GitHub Issues** : [Signaler un bug](https://github.com/FastmanTheDuke/prestashop-booking-module/issues)
- 📖 **Documentation** : [Wiki complet](https://github.com/FastmanTheDuke/prestashop-booking-module/wiki)
- 🎥 **Tutoriels vidéo** : [Chaîne YouTube](https://youtube.com/mdxp)

## 🤝 Contribution

### **Comment contribuer**
1. **Fork** le projet
2. Créer une **branche feature** (`git checkout -b feature/amazing-feature`)
3. **Commit** vos changements (`git commit -m 'Add amazing feature'`)
4. **Push** vers la branche (`git push origin feature/amazing-feature`)
5. Ouvrir une **Pull Request**

### **Guidelines**
- 📝 **PSR-12** : Respect des standards PHP
- 🧪 **Tests unitaires** : Coverage minimum 80%
- 📖 **Documentation** : Comments et README à jour
- 🔒 **Sécurité** : Validation et sanitisation
- 🌍 **Accessibilité** : Support WCAG 2.1

## 📄 Licence

Ce projet est sous licence **MIT** - voir le fichier [LICENSE](LICENSE) pour plus de détails.

## 🙏 Remerciements

- **PrestaShop** pour la plateforme exceptionnelle
- **FullCalendar** pour la librairie de calendrier
- **Stripe** pour l'API de paiement
- **Community** pour les retours et contributions

---

<div align="center">

**⭐ Si ce module vous aide, n'hésitez pas à lui donner une étoile !**

[🌟 Donner une étoile](https://github.com/FastmanTheDuke/prestashop-booking-module) • 
[🐛 Signaler un bug](https://github.com/FastmanTheDuke/prestashop-booking-module/issues) • 
[💡 Suggérer une fonctionnalité](https://github.com/FastmanTheDuke/prestashop-booking-module/discussions)

---

**Développé avec ❤️ par [FastmanTheDuke](https://github.com/FastmanTheDuke)**

</div>
