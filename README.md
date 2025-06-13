# Module PrestaShop - Système de Réservations Avancé v2

## 🚀 Fonctionnalités principales

### ✅ Fonctionnalités actuelles
- **Gestion des éléments réservables** : Intégration avec les produits PrestaShop
- **Calendrier de disponibilités** : Interface intuitive pour définir les créneaux
- **Système de réservations** : Gestion complète des demandes clients
- **Statuts avancés** : Workflow de validation en backoffice
- **Interface utilisateur** : Calendriers interactifs avec FullCalendar

### 🔄 Améliorations en cours
- **Double calendrier** : Séparation disponibilités/réservations
- **Intégration produits** : Remplacement des "Bookers" par des produits PrestaShop
- **Paiement Stripe** : Intégration avec caution et empreinte CB
- **Validation backoffice** : Système d'acceptation des réservations
- **Statuts de commande** : Création automatique de commandes

## 📁 Structure du projet

```
booking/
├── booking.php                 # Module principal
├── classes/                    # Classes métier
│   ├── Booker.php             # Éléments réservables
│   ├── BookerAuth.php         # Disponibilités
│   └── BookerAuthReserved.php # Réservations
├── controllers/
│   ├── admin/                 # Contrôleurs admin
│   │   ├── AdminBooker.php
│   │   ├── AdminBookerAuth.php
│   │   ├── AdminBookerAuthReserved.php
│   │   ├── AdminBookerCalendarAvailability.php  # Nouveau
│   │   └── AdminBookerCalendarReservations.php  # Nouveau
│   └── front/                 # Contrôleurs front
├── views/
│   ├── templates/
│   │   ├── admin/             # Templates admin
│   │   └── front/             # Templates front
│   ├── css/                   # Styles
│   └── js/                    # Scripts JavaScript
└── sql/                       # Scripts SQL
```

## 🛠️ Installation

1. **Cloner le repository**
   ```bash
   git clone https://github.com/FastmanTheDuke/prestashop-booking-module.git
   ```

2. **Copier dans PrestaShop**
   ```bash
   cp -r prestashop-booking-module /path/to/prestashop/modules/booking
   ```

3. **Installer via BO PrestaShop**
   - Aller dans Modules > Gestionnaire de modules
   - Chercher "Système de Réservations Avancé"
   - Cliquer sur "Installer"

## 🎯 Roadmap v2.0

### Phase 1 : Refactoring Structure ✅
- [x] Création repository GitHub
- [x] Structure initiale améliorée
- [ ] Séparation calendriers disponibilités/réservations
- [ ] Nouveaux contrôleurs admin

### Phase 2 : Intégration Produits
- [ ] Liaison Booker ↔ Produit PrestaShop
- [ ] Gestion stock et disponibilités
- [ ] Interface de sélection produits

### Phase 3 : Workflow Validation
- [ ] Système de statuts avancés
- [ ] Notifications admin/client
- [ ] Création automatique commandes

### Phase 4 : Paiement & Caution
- [ ] Intégration Stripe
- [ ] Gestion des cautions
- [ ] Empreinte CB sécurisée

## 🔧 Configuration

### Variables importantes
- `BOOKING_DEFAULT_PRICE` : Prix par défaut
- `BOOKING_DEPOSIT_AMOUNT` : Montant de la caution
- `BOOKING_STRIPE_ENABLED` : Activation Stripe
- `BOOKING_AUTO_CONFIRM` : Confirmation automatique
- `BOOKING_EXPIRY_HOURS` : Délai d'expiration réservations

## 📊 Base de données

### Tables principales
- `ps_booker` : Éléments réservables (liés aux produits)
- `ps_booker_auth` : Créneaux de disponibilité
- `ps_booker_auth_reserved` : Réservations clients

### Statuts de réservation
- `0` : En attente de validation
- `1` : Acceptée (en attente de paiement)
- `2` : Payée et confirmée
- `3` : Annulée
- `4` : Expirée
- `5` : Remboursée

## 🤝 Contribution

1. Fork le projet
2. Créer une branche feature (`git checkout -b feature/AmazingFeature`)
3. Commit les changements (`git commit -m 'Add some AmazingFeature'`)
4. Push sur la branche (`git push origin feature/AmazingFeature`)
5. Ouvrir une Pull Request

## 📄 License

Ce projet est sous licence MIT - voir le fichier [LICENSE.md](LICENSE.md) pour plus de détails.

## 🆘 Support

Pour toute question ou problème :
- Ouvrir une [Issue](https://github.com/FastmanTheDuke/prestashop-booking-module/issues)
- Consulter la [Documentation](https://github.com/FastmanTheDuke/prestashop-booking-module/wiki)

---

**Version actuelle :** 2.0.0-dev  
**Compatibilité :** PrestaShop 1.7+  
**Auteur :** BBb  
**Dernière mise à jour :** Juin 2025
