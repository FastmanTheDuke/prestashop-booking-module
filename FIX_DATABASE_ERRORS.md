# Instructions pour corriger les erreurs de base de données

## Problème identifié
Les tables de votre base de données utilisent l'ancien schéma avec des noms de colonnes incorrects. C'est pourquoi vous avez ces erreurs :
- `Unknown column 'b.id_booker'` 
- `Unknown column 'a.date_reserved'`
- `Unknown column 'a.id_booker'`

## Solution rapide (5 minutes)

### Option 1 : Via phpMyAdmin (Recommandé)

1. **Accédez à phpMyAdmin** de votre serveur
2. **Sélectionnez votre base de données** PrestaShop
3. **Cliquez sur l'onglet SQL**
4. **Copiez et exécutez ce script** :

```sql
-- ATTENTION : Remplacez 'ps_' par votre préfixe de table si différent

-- 1. Supprimer toutes les tables du module
DROP TABLE IF EXISTS `ps_booking_activity_log`;
DROP TABLE IF EXISTS `ps_booker_reservation_order`;
DROP TABLE IF EXISTS `ps_booker_product`;
DROP TABLE IF EXISTS `ps_booker_auth_reserved`;
DROP TABLE IF EXISTS `ps_booker_auth`;
DROP TABLE IF EXISTS `ps_booker_lang`;
DROP TABLE IF EXISTS `ps_booker`;

-- Message de confirmation
SELECT 'Tables supprimées avec succès. Réinstallez maintenant le module depuis PrestaShop.' as Message;
```

5. **Réinstallez le module** depuis le back-office PrestaShop

### Option 2 : Script SQL complet

J'ai créé un fichier `fix_database_structure.sql` dans le repository qui :
1. Supprime toutes les anciennes tables
2. Recrée les tables avec la bonne structure
3. Peut optionnellement créer un booker de test

**Pour l'utiliser** :
1. Téléchargez le fichier : [fix_database_structure.sql](https://github.com/FastmanTheDuke/prestashop-booking-module/blob/main/fix_database_structure.sql)
2. Exécutez-le dans phpMyAdmin
3. Le module sera prêt à l'emploi

## Vérification

Après avoir exécuté le script et réinstallé le module, vérifiez que :
1. La page **AdminBooker** s'affiche sans erreur
2. Vous pouvez créer un nouvel élément à réserver
3. La page **AdminBookerAuth** s'affiche sans erreur
4. Le calendrier fonctionne

## Structure correcte des tables

Les tables doivent avoir ces colonnes clés :
- `ps_booker` : `id_booker` (pas `id`)
- `ps_booker_auth` : `id_auth`, `id_booker`
- `ps_booker_auth_reserved` : `id_reserved`, `date_reserved`

## En cas de problème persistant

Si les erreurs persistent après cette procédure :
1. Vérifiez le préfixe de vos tables (peut être différent de `ps_`)
2. Assurez-vous d'avoir désinstallé complètement le module avant de le réinstaller
3. Videz le cache PrestaShop après réinstallation

Le problème vient du fait que les anciennes tables n'ont pas été correctement supprimées lors de la désinstallation précédente. Cette procédure résout définitivement le problème.
