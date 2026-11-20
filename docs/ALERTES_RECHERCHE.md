# Alertes de recherche de médicaments — fonctionnement

Quand un patient cherche un médicament, les pharmacies proches sont alertées en temps réel.

## Parcours d'une recherche

```
Patient (site web)                 API (Symfony)                       Pharmacien (mobile)
      │  POST /public/recherche-medicament/new                              │
      ├───────────────────────────►│ valide + limite (20/min/IP)            │
      │                            │ enregistre la recherche (60 s)         │
      │                            │ pharmacies à ≤ 2 km (sinon ≤ 5 km)     │
      │                            │ ── pour CHAQUE pharmacie (1 seule fois)│
      │                            │    ├─ Mercure (privé) ────────────────►│ app ouverte : alerte directe
      │                            │    └─ FCM (data-only, haute priorité) ►│ arrière-plan : sonnerie plein écran
      │  GET  .../{id}/reponses    │                                        │ répond (stock / bientôt / rupture)
      │◄───────────────────────────┤◄───────────── POST /secure/recherche/{id}/repondre
      │  POST .../{id}/stopper     │ ── annulation : Mercure + FCM ────────►│ la sonnerie s'arrête
```

## Position du patient
La recherche part de **la position choisie par le patient**, par ordre de priorité :
1. un **lieu choisi** dans la liste (Google Places) ou le marqueur **déplacé à la main** sur la carte ;
2. un **lieu tapé** sans choisir de suggestion : la meilleure correspondance est prise ;
3. champ vide : **GPS** du téléphone/navigateur. Si le GPS est refusé, la recherche ne part PAS (plus de position par défaut silencieuse) : on invite à saisir un lieu.

La recherche attend les coordonnées d'un lieu en cours de résolution, et change de lieu = anciennes coordonnées effacées (jamais de recherche depuis un lieu périmé).

## Rayon de recherche (vagues)
- **Vague initiale** : le plus petit rayon contenant au moins une pharmacie (2, puis 5, puis 10 km).
- **Élargissement dans le temps** : sans aucune réponse, la recherche s'étend à **5 km après 20 s** puis **10 km après 40 s**. Déclenché par l'interrogation du patient (toutes les 2 s), sans tâche planifiée. Chaque pharmacie n'est alertée qu'une fois (verrou) ; l'élargissement s'arrête dès qu'une pharmacie a répondu.
- Le patient voit « Recherche dans un rayon de N km... ». Annulation et rattrapage ne concernent que les pharmacies réellement alertées.

## Qui reçoit l'alerte ?
Une pharmacie reçoit une recherche si **toutes** ces conditions sont vraies :
1. Elle a une **position GPS** (latitude et longitude). Sans position, elle n'est jamais trouvée.
2. L'option « réception des urgences » (`is_disponible_recherche`) est active.
3. Elle est dans le rayon de la vague en cours (2, 5 ou 10 km). Maximum 40 pharmacies par vague.
4. Au moins un utilisateur lui est rattaché avec un jeton FCM valide (FCM) ou une app ouverte (Mercure).

Le mobile affiche un bandeau orange tant que la pharmacie n'a pas de position.

## Pas de doublon
- **Serveur** : verrou `recherche_sent_{recherche}_{pharmacie}` (10 min) = une recherche n'est envoyée qu'une fois à une pharmacie. Un jeton FCM n'est utilisé qu'une fois par recherche.
- **Téléphone** : chaque alerte porte la clé `offerKey` = `recherche#pharmacie`, identique sur Mercure, FCM et le rattrapage REST. `OfferDeduplicator` ne laisse passer chaque clé qu'une fois (3 min).

## Canaux
| Canal | Quand | Détails |
|---|---|---|
| Mercure | app ouverte | Messages **privés**. Topic `https://pharmaalerte.net/pharmacie/{id}/recherche`. Jeton d'abonnement limité à SA pharmacie : `GET /api/v1/secure/mercure/token` (valable 6 h, redemandé à chaque reconnexion). |
| FCM | arrière-plan / app fermée | Message **data-only** (sans bloc `notification`) pour que l'app garde la main et déclenche sa sonnerie insistante. iOS : alerte via `aps`. TTL 60 s. |
| REST | rattrapage | `GET /api/v1/secure/recherche/active` (recherche en cours, ≤ 5 km, sans réponse de la pharmacie) à chaque (re)connexion Mercure et au retour dans l'app. `GET /secure/recherche/{id}/statut` pour l'écran d'alerte. |

## Configuration
| Variable | Rôle | Local |
|---|---|---|
| `FIREBASE_CREDENTIALS` | clé du compte de service Firebase. **Doit être du même projet que l'app mobile** (`pharmaalerte-c4db9`). | `config/firebase_credentialsj.json` |
| `MERCURE_URL` | hub, vu depuis l'API (réseau Docker) | `http://mercure:80/.well-known/mercure` |
| `MERCURE_PUBLIC_URL` | hub, vu du téléphone | `http://192.168.100.10:3000/.well-known/mercure` |
| `MERCURE_JWT_SECRET` | secret JWT, identique à `MERCURE_PUBLISHER_JWT_KEY` / `MERCURE_SUBSCRIBER_JWT_KEY` du hub (`compose.yaml`) | **à changer en production** |

Mobile : `AppConstants.apiBaseUrl` (`lib/config/constants.dart`). L'URL du hub vient de l'API, pas de l'app.

## Production — à faire
- Remplacer `MERCURE_JWT_SECRET` (≥ 256 bits, aléatoire) dans `.env` **et** `compose.yaml`.
- `MERCURE_PUBLIC_URL` en HTTPS sur un domaine ; `apiBaseUrl` du mobile sur le domaine de production.
- Ne jamais versionner `config/firebase_credentials*.json` (déjà dans `.gitignore`).
- Les clés de `.env` (SMS, AWS...) sont à sortir du dépôt.

## Diagnostiquer « je ne reçois rien »
1. **Position** : la pharmacie a-t-elle latitude/longitude ? (phpMyAdmin → table `pharmacie`, ou bandeau orange dans l'app)
2. **Option urgences** active sur la pharmacie ?
3. **Jeton FCM** : colonne `fcm_token` de l'utilisateur renseignée ?
4. **Logs serveur** (`var/log/dev.log`) : `FCM (nouvelle recherche #N) : X succès, Y échecs` ; `Recherche #N : aucune pharmacie à proximité` ; `Échec Mercure`.
5. **App** : pastille « Alertes en direct » verte ? Journaux `[MERCURE] Connecté au topic ...`.
6. **Réseau local** : le téléphone joint `http://IP-du-PC:8000` et `http://IP-du-PC:3000` (pare-feu Windows).

## Code
- `src/Service/RechercheNotifier.php` : proximité, Mercure, FCM, verrou anti-doublon, nettoyage des jetons morts.
- `src/Controller/Public/PublicRechercheController.php` : endpoints patient (validation, limite, annulation, réservation).
- `src/Controller/Security/ReponseRechercheController.php` : endpoints pharmacien (`active`, `statut`, `repondre`, `mes-reponses`).
- `src/Controller/Security/MercureTokenController.php` : jeton d'abonnement.
- Mobile : `lib/core/services/firebase_messaging_service.dart`, `mercure_service.dart`, `offer_deduplicator.dart`, `lib/presentation/screens/alert/incoming_request_screen.dart`.

## Veille côté mobile : bulle flottante (comme Yacolis Pro)
Quand le pharmacien quitte l'application avec ses alertes actives, une **bulle** (logo de l'app) reste affichée par-dessus les autres applications. Un toucher ramène dans l'app, où le rattrapage Mercure/REST affiche une éventuelle demande en cours.

- Affichée **seulement si** : alertes actives (bascule d'accueil) + bulle activée dans le profil + autorisation Android « Afficher par-dessus les autres applications » + application passée en arrière-plan. Retirée au retour dans l'app, à la déconnexion, ou si on la glisse vers le bas.
- Android uniquement (iOS interdit les fenêtres flottantes).
- Code : `lib/core/services/floating_bubble/` (passerelle `flutter_overlay_window`, contrôleur, observateur de cycle de vie), `lib/presentation/widgets/floating_bubble/` (bulle, proposition d'autorisation, réglage du profil), point d'entrée `overlayMain` dans `main.dart`, service déclaré dans `AndroidManifest.xml`.
- Écran d'alerte : l'écran reste allumé pendant la sonnerie (`wakelock_plus`).
- Constructeurs Android agressifs (Xiaomi, Huawei, Samsung...) : demander au pharmacien de désactiver l'optimisation de batterie pour l'app, sinon le système peut tuer l'app en arrière-plan.

## Performance de l'API en local (Docker sous Windows)
Réglages qui font passer chaque appel de ~1 s à ~0,1 s (voir `compose.yaml` et `docker/php/opcache.ini`) :
- **OPcache actif pour le serveur PHP intégré** (`opcache.enable_cli=1`, désactivé par défaut) : sans lui, tout Symfony est recompilé à chaque requête.
- **`APP_DEBUG=0`** : en debug, Symfony revérifie tous ses fichiers à chaque requête (très lent sur un dossier monté depuis Windows). Conséquence : après avoir ajouté un service, une route ou modifié un fichier de config, lancer `docker compose exec pharma_api php bin/console cache:clear`. Le code PHP des contrôleurs/services est repris tout seul (2 s).
- **`var/` dans un volume Docker** (cache et logs hors du dossier Windows). Les logs se lisent avec `docker compose exec pharma_api sh -c 'tail -n 50 var/log/dev-*.log'`.
