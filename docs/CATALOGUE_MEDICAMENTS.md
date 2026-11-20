# Catalogue de médicaments — synchronisation mobile

Le mobile garde le catalogue (≈ 18 000 médicaments) dans **Hive** et le tient à jour par **synchronisation incrémentale** : à chaque ouverture, il ne télécharge que ce qui a changé sur le serveur.

## Parcours
```
Ouverture de l'app
 ├─ 1. lecture de Hive (local, instantané, sans réseau)         → liste utilisable tout de suite
 └─ 2. si connecté : GET /secure/medecine/sync?since=<dernière synchro>
        ├─ rien de nouveau → réponse vide (quelques octets)
        ├─ nouveaux / modifiés → remplacés dans Hive (clé = uuid)
        └─ supprimés → retirés de Hive
```
Première installation (Hive vide) : `since` absent → catalogue complet par pages de 1000 (~110 Ko chacune, ~19 pages), la liste se remplit au fil des pages.

## Endpoint `GET /api/v1/secure/medecine/sync`
| Paramètre | Rôle |
|---|---|
| `since` | `server_time` reçu à la synchro précédente. Absent = catalogue complet (sans les supprimés). |
| `cursor` | dernier `id` reçu (pagination stable par id) |
| `limit` | taille de page (défaut 500, max 1000) |

Réponse : `items[]` (`uuid`, `nom`, `prix`, `deleted`), `next_cursor`, `has_more`, `server_time`, `total`.
- Avec `since`, sont renvoyés les médicaments **créés, modifiés ou supprimés** (suppression logique = `deleted: true`) depuis cette date.
- `server_time` = **horloge du serveur** : le mobile n'utilise jamais sa propre horloge pour la synchro.

## Fiabilité
- Le `server_time` n'est enregistré qu'**après** une synchro complète : une coupure en cours de route refait simplement la synchro suivante (les mises à jour sont idempotentes).
- Le `since` est reculé de 10 s (recouvrement) pour ne rien perdre d'une modification survenue pendant la synchro précédente.
- **Contrôle d'intégrité** : `total` (médicaments actifs côté serveur) est comparé au nombre local ; en cas d'écart (ex. suppression définitive en base, qu'aucun delta ne peut signaler), une resynchronisation complète est lancée automatiquement.
- Hors ligne : le catalogue local reste utilisable, la synchro est simplement reportée à la prochaine ouverture.
- Stockage illisible : la boîte Hive est recréée et retéléchargée.

## Déclencheurs de synchro (mobile)
- Ouverture de l'application (session valide).
- Connexion (première installation, changement de compte).
- Retour dans l'application, au plus toutes les 10 minutes.
- Bouton « actualiser » de l'écran de nouvelle demande (delta + contrôle d'intégrité).

## Code
- Serveur : `MedecineController::sync()`.
- Mobile : `lib/data/providers/medicine_provider.dart` (logique), `lib/data/local/medicine_local_store.dart` (Hive), `lib/data/models/medicine_model.dart`, `MedicineRepository.fetchSyncPage()`.

## Ajouter / modifier des médicaments côté serveur
Tout passe par l'entité `Medecine` (les dates `updated_at` / `deleted_at` sont gérées automatiquement). Un import par SQL direct doit renseigner `updated_at`, sinon le mobile ne verra pas la modification. La commande `app:import-medicaments` ajoute les nouveaux noms ; les mobiles les récupèrent à leur prochaine ouverture.
