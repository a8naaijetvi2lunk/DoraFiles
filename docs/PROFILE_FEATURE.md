# Fonctionnalité Profil Utilisateur

## 📋 Vue d'ensemble

Cette fonctionnalité ajoute un système complet de gestion de profil utilisateur à Dora Files, incluant :

- **Gestion Multi-FTP** : Connexion à plusieurs serveurs FTP
- **Historique d'activité** : Traçabilité complète des actions
- **Paramètres du compte** : Modification email et mot de passe
- **Statistiques personnelles** : Vue d'ensemble de l'utilisation

## 🚀 Installation

### 1. Exécuter la migration

```bash
php migrate-profile.php
```

Cette migration va :
- Ajouter les colonnes nécessaires à la table `users`
- Créer la table `ftp_connections` pour la gestion multi-FTP
- Créer la table `activity_logs` pour l'historique
- Migrer automatiquement les connexions FTP existantes

### 2. Configuration (optionnel)

Ajoutez ces variables à votre fichier `.env` :

```env
# Nombre de jours de rétention des logs d'activité (défaut: 90)
ACTIVITY_LOG_RETENTION_DAYS=90
```

### 3. Nettoyage automatique des logs

Pour éviter l'accumulation des logs, configurez une tâche cron :

```bash
# Exécuter le nettoyage tous les jours à 2h du matin
0 2 * * * /usr/bin/php /path/to/htdocs/files.regaletoimagl.fr/cleanup-logs.php
```

## 📚 Structure des nouvelles tables

### Table `ftp_connections`

Stocke les connexions FTP multiples par utilisateur.

```sql
- id (INT, PRIMARY KEY)
- user_id (INT, FOREIGN KEY)
- connection_name (VARCHAR 100)
- ftp_host (TEXT, encrypted)
- ftp_port (TEXT, encrypted)
- ftp_username (TEXT, encrypted)
- ftp_password (TEXT, encrypted)
- ftp_base_path (TEXT, encrypted)
- is_default (BOOLEAN)
- last_used_at (TIMESTAMP)
- created_at (TIMESTAMP)
- updated_at (TIMESTAMP)
```

### Table `activity_logs`

Enregistre toutes les actions des utilisateurs.

```sql
- id (BIGINT, PRIMARY KEY)
- user_id (INT, FOREIGN KEY)
- action (VARCHAR 50)
- entity_type (VARCHAR 50)
- entity_name (VARCHAR 255)
- details (TEXT)
- ip_address (VARCHAR 45)
- user_agent (TEXT)
- created_at (TIMESTAMP)
```

### Colonnes ajoutées à `users`

```sql
- active_ftp_connection_id (INT)
- last_login_at (TIMESTAMP)
- last_login_ip (VARCHAR 45)
```

## 🎯 Fonctionnalités

### 1. Gestion Multi-FTP

**Accès** : `/profile.php?tab=ftp-connections`

- Créer plusieurs connexions FTP
- Basculer entre les connexions
- Modifier les paramètres de connexion
- Tester la connexion avant sauvegarde
- Supprimer les connexions (minimum 1 requise)

**Limites** :
- Maximum 10 connexions par utilisateur
- Test de connexion automatique avant création/modification
- Validation des ports (1-65535)
- Protection contre path traversal

### 2. Historique d'activité

**Accès** : `/profile.php?tab=activity`

**Actions tracées** :
- Authentification (login, logout, échecs)
- Gestion utilisateur (email, mot de passe, suppression)
- Connexions FTP (création, modification, suppression, switch)
- Fichiers (upload, download, delete, rename, move)
- Dossiers (création, suppression)
- Liens de partage (création, suppression, accès)

**Informations enregistrées** :
- Horodatage précis
- Adresse IP
- User agent
- Détails de l'action

**Pagination** : 20 entrées par page

### 3. Paramètres du compte

**Accès** : `/profile.php?tab=settings`

**Modification email** :
- Validation du format email
- Vérification de l'unicité
- Mise à jour de la session

**Modification mot de passe** :
- Vérification de l'ancien mot de passe
- Politique de sécurité stricte :
  - Minimum 8 caractères
  - Au moins 1 majuscule
  - Au moins 1 minuscule
  - Au moins 1 chiffre
  - Différent de l'ancien mot de passe

**Suppression du compte** :
- Confirmation par mot de passe requise
- Double confirmation UI
- Suppression en cascade (connexions, logs, liens)

### 4. Vue d'ensemble

**Accès** : `/profile.php` ou `/profile.php?tab=overview`

**Statistiques affichées** :
- Nombre de liens actifs
- Total des téléchargements
- Nombre de connexions FTP
- Activités des 30 derniers jours

**Actions rapides** :
- Accès direct aux fichiers
- Gestion des connexions
- Liens de partage
- Paramètres

## 🔒 Sécurité

### Mesures de sécurité implémentées

1. **Protection CSRF** : Token sur tous les formulaires
2. **Validation des entrées** : Filtrage et sanitization
3. **Chiffrement** : Credentials FTP chiffrés (AES-256-CBC)
4. **Tests de connexion** : Validation avant sauvegarde FTP
5. **Limites** :
   - 10 connexions FTP max par utilisateur
   - Pagination pour éviter la surcharge
   - Rate limiting IP existant
6. **SQL Injection** : Requêtes préparées (PDO)
7. **XSS** : Échappement HTML (fonction `e()`)
8. **Path Traversal** : Validation des chemins FTP
9. **Session** : Configuration sécurisée existante
10. **Logs** : Traçabilité complète des actions

### Recommandations supplémentaires

- Activer HTTPS (déjà configuré)
- Configurer le nettoyage automatique des logs
- Surveiller les tentatives de login échouées
- Considérer l'ajout de 2FA (future feature)

## 🛠️ Services backend

### `UserService.php`

```php
- getProfile($userId)              // Récupère le profil + stats
- getUserStatistics($userId)       // Calcule les statistiques
- updateEmail($userId, $newEmail)  // Modifie l'email
- updatePassword($userId, ...)     // Change le mot de passe
- updateLastLogin($userId, $ip)    // Met à jour connexion
- deleteAccount($userId, $pass)    // Supprime le compte
```

### `FTPConnectionService.php`

```php
- getUserConnections($userId)                // Liste connexions
- getConnection($connId, $userId)            // Récupère une connexion
- createConnection(...)                      // Crée connexion + test
- updateConnection(...)                      // Modifie connexion
- deleteConnection($connId, $userId)         // Supprime connexion
- switchConnection($connId, $userId)         // Active connexion
- testConnection(...)                        // Teste connexion FTP (privé)
- validateConnectionData(...)                // Valide données (privé)
```

### `ActivityLogService.php`

```php
- log($userId, $action, ...)                 // Enregistre une action
- getUserActivity($userId, $page, ...)       // Récupère logs paginés
- getRecentActivity($userId, $limit)         // Logs récents
- getActivityStatistics($userId, $days)      // Stats période
- cleanupOldLogs($daysToKeep)               // Nettoie vieux logs
- getActionDescription($action)              // Description action (static)
- getActionIcon($action)                     // Icône action (static)
```

## 📝 Intégration avec le code existant

### Modifications apportées

1. **`app/Services/AuthService.php`** :
   - Intégration des logs (login, logout, register)
   - Support du multi-FTP (chargement connexion active)
   - Mise à jour last_login

2. **`views/partials/header.php`** :
   - Ajout du lien "Profil" dans la navigation

3. **Migration automatique** :
   - Les connexions FTP existantes sont migrées automatiquement
   - Pas de perte de données

## 🎨 Interface utilisateur

### Navigation par onglets

- **Vue d'ensemble** : Informations et statistiques
- **Connexions FTP** : Gestion multi-serveurs
- **Historique** : Logs d'activité paginés
- **Paramètres** : Email, mot de passe, suppression

### Design

- Interface cohérente avec le reste de l'application
- Responsive design
- Modales pour créer/modifier connexions
- Confirmations pour actions critiques
- Messages d'erreur/succès clairs

## 🧪 Tests recommandés

### Tests fonctionnels

- [ ] Créer une nouvelle connexion FTP
- [ ] Tester une connexion invalide (doit échouer)
- [ ] Basculer entre connexions
- [ ] Modifier une connexion existante
- [ ] Supprimer une connexion (garder minimum 1)
- [ ] Modifier l'email
- [ ] Changer le mot de passe (tester validations)
- [ ] Voir l'historique d'activité
- [ ] Tester la pagination
- [ ] Supprimer un compte

### Tests de sécurité

- [ ] Tenter d'accéder au profil d'un autre utilisateur
- [ ] Tester injection SQL dans les formulaires
- [ ] Tester XSS dans les champs texte
- [ ] Vérifier tokens CSRF
- [ ] Tester path traversal dans base_path
- [ ] Vérifier chiffrement credentials FTP
- [ ] Tester limite 10 connexions
- [ ] Vérifier validation mot de passe fort

## 📞 Support

En cas de problème :

1. Vérifier les logs PHP
2. Vérifier les logs de la base de données
3. Consulter la table `activity_logs` pour le debug
4. Vérifier les permissions fichiers/dossiers

## 🔄 Migration retour (rollback)

Si nécessaire, pour annuler la migration :

```sql
-- Supprimer les nouvelles tables
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS ftp_connections;

-- Supprimer les colonnes ajoutées
ALTER TABLE users DROP COLUMN active_ftp_connection_id;
ALTER TABLE users DROP COLUMN last_login_at;
ALTER TABLE users DROP COLUMN last_login_ip;
```

**Attention** : Cela supprimera toutes les données de profil !

## 📈 Améliorations futures possibles

- Export de l'historique (CSV, PDF)
- Filtres avancés sur l'historique
- Notifications email (changements critiques)
- Authentification 2FA
- Synchronisation automatique entre FTP
- Statistiques graphiques (charts)
- API REST pour intégrations externes
- Webhook sur événements
- Rôles utilisateurs (admin, user)
- Quotas de stockage par utilisateur
