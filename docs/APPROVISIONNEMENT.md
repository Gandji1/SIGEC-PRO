# Module Approvisionnement - SIGEC

## Vue d'ensemble

Le module Approvisionnement gere les flux de stock entre les differents magasins (Gros et Detail) ainsi que les commandes d'achat et les transferts internes.

## Architecture

### Entrepots (Warehouses)
- **Magasin Gros** : Entrepot principal, reception des achats fournisseurs
- **Magasin Detail** : Magasin de vente, approvisionne par le Gros
- **POS** : Points de vente (Option B uniquement)

### Flux de stock
```
Fournisseur -> [Achat] -> Magasin Gros -> [Transfert] -> Magasin Detail -> [Vente] -> Client
```

## Fonctionnalites

### Magasin Gros

#### Dashboard
- Valeur totale du stock (CMP * quantite)
- Mouvements du jour/semaine/mois
- Produits en stock bas
- Commandes en attente
- Demandes a traiter

#### Commandes d'Achat (Purchases)
- Creation de commande (brouillon)
- Soumission au fournisseur
- Reception avec calcul CMP
- Ecritures comptables automatiques

#### Reception
- Saisie des quantites recues
- Mise a jour du cout unitaire
- Calcul automatique du CMP
- Creation des mouvements de stock

#### Inventaire
- Comptage physique
- Calcul des ecarts
- Ajustements automatiques
- Validation par le gerant

#### Demandes de Stock
- Visualisation des demandes du Detail
- Approbation/Rejet
- Creation automatique de transfert

### Magasin Detail

#### Dashboard
- Stock disponible (SDU)
- Commandes POS en attente
- Demandes vers Gros
- Ventes preparees aujourd'hui

#### Demandes
- Creation de demande vers Gros
- Suivi du statut
- Priorite (basse/normale/haute/urgente)

#### Reception Transferts
- Reception des marchandises
- Enregistrement des ecarts
- Validation

#### Servir Commandes POS
- Liste des commandes a preparer
- Action "Servir" (deduction stock)
- Action "Valider" (finalisation)

## API Endpoints

### Dashboards
```
GET /api/approvisionnement/gros/dashboard?from=YYYY-MM-DD&to=YYYY-MM-DD
GET /api/approvisionnement/detail/dashboard?from=YYYY-MM-DD&to=YYYY-MM-DD
```

### Achats
```
GET    /api/approvisionnement/purchases
POST   /api/approvisionnement/purchases
GET    /api/approvisionnement/purchases/{id}
POST   /api/approvisionnement/purchases/{id}/submit
POST   /api/approvisionnement/purchases/{id}/receive
```

Payload creation:
```json
{
  "supplier_id": 1,
  "supplier_name": "Fournisseur Alpha",
  "warehouse_id": 1,
  "expected_date": "2025-12-01",
  "items": [
    {"product_id": 1, "qty_ordered": 50, "expected_unit_cost": 1000}
  ]
}
```

Payload reception:
```json
{
  "received_items": [
    {"purchase_item_id": 1, "qty_received": 50, "unit_cost": 1000, "sale_price": 1500}
  ]
}
```

### Demandes de Stock
```
GET    /api/approvisionnement/requests
POST   /api/approvisionnement/requests
GET    /api/approvisionnement/requests/{id}
POST   /api/approvisionnement/requests/{id}/submit
POST   /api/approvisionnement/requests/{id}/approve
POST   /api/approvisionnement/requests/{id}/reject
```

Payload creation:
```json
{
  "from_warehouse_id": 2,
  "to_warehouse_id": 1,
  "priority": "high",
  "needed_by_date": "2025-12-01",
  "items": [
    {"product_id": 1, "qty_requested": 20}
  ]
}
```

### Transferts
```
GET    /api/approvisionnement/transfers
GET    /api/approvisionnement/transfers/{id}
POST   /api/approvisionnement/transfers/{id}/execute
POST   /api/approvisionnement/transfers/{id}/receive
POST   /api/approvisionnement/transfers/{id}/validate
```

### Inventaires
```
GET    /api/approvisionnement/inventories
POST   /api/approvisionnement/inventories
POST   /api/approvisionnement/inventories/{id}/validate
```

### Mouvements de Stock
```
GET /api/approvisionnement/movements?product_id=1&warehouse_id=1&type=purchase&from=YYYY-MM-DD&to=YYYY-MM-DD
```

### Commandes POS
```
GET    /api/approvisionnement/orders
POST   /api/approvisionnement/orders
GET    /api/approvisionnement/orders/{id}
POST   /api/approvisionnement/orders/{id}/serve
POST   /api/approvisionnement/orders/{id}/validate
```

## Calcul du CMP (Cout Moyen Pondere)

Le CMP est calcule uniquement lors des receptions dans le Magasin Gros:

```
Nouveau CMP = (Ancien Stock * Ancien CMP + Quantite Recue * Cout Unitaire) / (Ancien Stock + Quantite Recue)
```

Exemple:
- Stock actuel: 100 unites a 1000 FCFA (CMP = 1000)
- Reception: 50 unites a 1200 FCFA
- Nouveau CMP = (100 * 1000 + 50 * 1200) / 150 = 134000 / 150 = 1066.67 FCFA

## Mouvements de Stock

Chaque modification de quantite cree un enregistrement dans `stock_movements`:

| Type | Description |
|------|-------------|
| purchase | Reception d'achat |
| transfer | Transfert entre entrepots |
| sale | Vente/Remise |
| adjustment | Ajustement inventaire |
| return | Retour |

## Permissions RBAC

| Role | Permissions |
|------|-------------|
| owner/admin | Toutes les operations |
| manager | Achats, transferts, inventaires, approbations |
| magasinier_gros | Reception, transferts sortants, inventaire gros |
| magasinier_detail | Reception transferts, servir commandes, inventaire detail |
| caissier | Encaissement uniquement |
| pos_server | Creation commandes POS |

## Ecritures Comptables

### Reception Achat
- Debit: 3100 Stock Marchandises
- Credit: 4010 Fournisseurs

### Transfert Interne
- Debit: 3100 Stock (destination)
- Credit: 3100 Stock (source)

### Vente
- Debit: 5200 Caisse
- Credit: 7010 Ventes
- Debit: 6030 Cout des ventes
- Credit: 3100 Stock

### Ajustement Inventaire
- Surplus: Debit Stock / Credit Produits exceptionnels
- Manquant: Debit Charges exceptionnelles / Credit Stock

## Tests

Executer les tests:
```bash
php artisan test --filter=ApprovisionnementTest
php artisan test --filter=StockServiceTest
```

## Seeder

Charger les donnees de demonstration:
```bash
php artisan db:seed --class=ApprovisionnementSeeder
```

Donnees creees:
- 3 fournisseurs
- 8 produits
- Stock initial dans Gros et Detail
- 1 commande d'achat en attente
- 1 demande de stock
- 1 transfert execute
