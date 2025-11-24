# Changelog - Dora Files

Toutes les modifications notables du projet seront documentées dans ce fichier.

## [Version 2.1.0] - 2025-11-24

### Sécurité
- **Protection contre l'injection de commandes** : Validation stricte des chemins FTP avec `validatePath()` dans `FTPService`
- **Correction de la fixation de session** : Régénération de l'ID de session lors de la connexion (`session_regenerate_id(true)`)
- **Rate limiting renforcé** : Protection contre les attaques par force brute sur login, register et download
- **Protection IDOR** : Vérification de propriété des ressources (liens, connexions FTP)
- **Validation robuste des mots de passe** : Minimum 8 caractères, majuscule, minuscule et chiffre requis
- **Chiffrement sécurisé** : Ajout de HMAC pour l'intégrité des données chiffrées avec `encryptWithHmac()` et `decryptWithHmac()`
- **Protection contre l'injection d'en-têtes** : Validation des noms de fichiers dans les téléchargements
- **En-têtes de sécurité HTTP** : X-Content-Type-Options, X-Frame-Options, X-XSS-Protection, Content-Security-Policy, Referrer-Policy, Permissions-Policy

### Amélioré
- **Performance des requêtes SQL** : Index optimisés intégrés nativement dans les migrations
  - `shared_links` : idx_token, idx_user_id, idx_ftp_connection_id, idx_expires_at, idx_created_at
  - `zip_jobs` : idx_token, idx_user_status, idx_expires_at, idx_status, idx_expires_status
  - `rate_limits` : idx_ip_action, idx_window, idx_rate_limit_check
  - `ftp_connections` : idx_user_id, idx_is_default
  - `activity_logs` : idx_user_id, idx_action, idx_created_at, idx_user_created

### Code
- **Harmonisation du style PHP** : Tous les services refactorisés avec PHP 8 (typed properties, return types, null coalescing)
  - `LinkService`, `ZipJobService`, `FTPService`, `AuthService`, `UserService`
  - `FTPConnectionService`, `ActivityLogService`, `TwoFactorService`, `RateLimitService`
- **SecurityMiddleware** : Nouvelle classe centralisant toutes les protections de sécurité

### UI/UX
- **Classes utilitaires CSS** : Système complet de classes utilitaires type Tailwind
  - Flexbox : `.flex`, `.items-center`, `.justify-between`, `.gap-1` à `.gap-4`
  - Spacing : `.py-4`, `.py-6`, `.mt-2` à `.mt-6`, `.mb-2` à `.mb-6`
  - Typography : `.text-xs` à `.text-2xl`, `.font-medium`, `.font-semibold`
  - Display : `.inline-block`, `.hidden`, `.opacity-40` à `.opacity-70`
  - Width : `.w-full`, `.max-w-md`, `.max-w-lg`
- **Page d'inscription harmonisée** : Logo, header et placeholders cohérents avec la page de connexion
- **Page de téléchargement** : Styles inline remplacés par classes CSS dédiées
- **Profil utilisateur** : Styles migrés des fichiers vers le CSS global
- **Formulaires** : Layout grid unifié avec `.form-row` et `.form-group-small`
- **Checkboxes** : Style unifié avec `.checkbox-label` et `.checkbox-input`
- **Bug corrigé** : Double attribut `class` sur les boutons de suppression dans le file browser
- **Responsive** : Media queries ajoutées pour danger zone et form-row

## [Version 2.0.0] - 2025-11-21

### Ajouté
- **Système de profils utilisateur** : Gestion complète des profils avec avatar, bio et informations personnelles
- **Authentification à deux facteurs (2FA)** : Sécurité renforcée avec TOTP
- **Gestion des connexions FTP** : Interface pour gérer les connexions FTP
- **Journal d'activité** : Traçabilité complète des actions utilisateur
- **Patch Notes** : Affichage des mises à jour dans un modal

### Amélioré
- Interface utilisateur modernisée avec un thème sombre
- Performance de téléchargement optimisée
- Gestion améliorée des liens de partage

### Sécurité
- Ajout de la limitation de débit (rate limiting)
- Protection CSRF renforcée
- Validation des entrées utilisateur améliorée

## [Version 1.5.0] - 2025-10-15

### Ajouté
- Système de liens partagés avec expiration
- Tableau de bord statistiques
- Gestion des fichiers par glisser-déposer

### Corrigé
- Correction des problèmes de téléchargement de gros fichiers
- Amélioration de la compatibilité mobile

## [Version 1.0.0] - 2025-09-01

### Ajouté
- Première version stable
- Système d'authentification de base
- Upload et téléchargement de fichiers
- Navigation dans les dossiers
- Gestion des permissions
