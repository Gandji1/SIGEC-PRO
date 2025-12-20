import React, { useState, useEffect } from "react";
import { Modal } from "./UIComponents";
import { formatCurrency } from "./utils";
import apiClient from "../../services/apiClient";

export function PurchaseModal({
  headers,
  suppliers: initialSuppliers = [],
  products: initialProducts = [],
  warehouseId,
  onClose,
  onSaved,
}) {
  const [form, setForm] = useState({
    supplier_id: "",
    expected_date: "",
    items: [{ product_id: "", qty_ordered: 1, expected_unit_cost: 0 }],
  });
  const [loading, setLoading] = useState(false);
  const [products, setProducts] = useState(initialProducts);
  const [suppliers, setSuppliers] = useState(initialSuppliers);
  const [dataLoading, setDataLoading] = useState(
    initialProducts.length === 0 || initialSuppliers.length === 0
  );

  // Charger les données si pas fournies
  useEffect(() => {
    if (products.length > 0 && suppliers.length > 0) {
      setDataLoading(false);
      return;
    }

    const loadData = async () => {
      try {
        const [prodRes, suppRes] = await Promise.all([
          products.length === 0
            ? apiClient.get("/products?per_page=100")
            : Promise.resolve({ data: products }),
          suppliers.length === 0
            ? apiClient.get("/suppliers?per_page=100")
            : Promise.resolve({ data: suppliers }),
        ]);

        const prods = prodRes.data?.data || prodRes.data || [];
        const supps = suppRes.data?.data || suppRes.data || [];

        if (Array.isArray(prods) && prods.length > 0) setProducts(prods);
        if (Array.isArray(supps) && supps.length > 0) setSuppliers(supps);
        setDataLoading(false);
      } catch (e) {
        console.error("[PurchaseModal] Erreur chargement:", e);
        setDataLoading(false);
      }
    };

    loadData();
  }, []);

  // Afficher le chargement
  if (dataLoading) {
    return (
      <Modal title="Nouvelle Commande d'Achat" onClose={onClose}>
        <div className="text-center py-8">
          <div className="animate-spin w-8 h-8 border-4 border-blue-600 border-t-transparent rounded-full mx-auto mb-4"></div>
          <p className="text-gray-600">Chargement des données...</p>
        </div>
      </Modal>
    );
  }

  // Si pas de produits ou fournisseurs, afficher un message
  if (products.length === 0 || suppliers.length === 0) {
    return (
      <Modal title="Nouvelle Commande d'Achat" onClose={onClose}>
        <div className="text-center py-8">
          <p className="text-red-600 font-medium">Données manquantes</p>
          {products.length === 0 && (
            <p className="text-sm text-gray-500 mt-2">
              Aucun produit disponible. Créez des produits dans le catalogue.
            </p>
          )}
          {suppliers.length === 0 && (
            <p className="text-sm text-gray-500 mt-2">
              Aucun fournisseur disponible. Le propriétaire doit créer des
              fournisseurs.
            </p>
          )}
          <button
            onClick={onClose}
            className="mt-4 px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300"
          >
            Fermer
          </button>
        </div>
      </Modal>
    );
  }

  const addItem = () =>
    setForm({
      ...form,
      items: [
        ...form.items,
        { product_id: "", qty_ordered: 1, expected_unit_cost: 0 },
      ],
    });

  const updateItem = (i, field, value) => {
    const items = [...form.items];
    if (field === "product_id") {
      items[i].product_id = value;
      const p = products.find((x) => x.id == value);
      if (p) {
        // Récupérer le prix d'achat ou le prix de vente comme fallback
        const price =
          p.purchase_price || p.cost_price || p.price || p.sale_price || 0;
        items[i].expected_unit_cost = parseFloat(price) || 0;
        console.log(
          "[PurchaseModal] Produit sélectionné:",
          p.name,
          "Prix:",
          items[i].expected_unit_cost
        );
      }
    } else if (field === "qty_ordered") {
      items[i].qty_ordered = parseInt(value) || 1;
    } else {
      items[i].expected_unit_cost = parseFloat(value) || 0;
    }
    setForm({ ...form, items });
  };

  const removeItem = (i) =>
    setForm({
      ...form,
      items: form.items.filter((_, j) => j !== i),
    });

  const total = () =>
    form.items.reduce((s, i) => s + i.qty_ordered * i.expected_unit_cost, 0);

  const submit = async (e) => {
    e.preventDefault();
    if (!form.supplier_id) return alert("Sélectionnez un fournisseur");
    if (!form.items.some((i) => i.product_id))
      return alert("Ajoutez au moins un produit");

    setLoading(true);
    try {
      const supplier = suppliers.find((s) => s.id == form.supplier_id);

      // Préparer les items avec validation des prix
      const validItems = form.items
        .filter((i) => i.product_id)
        .map((i) => {
          const product = products.find((p) => p.id == i.product_id);
          let price = parseFloat(i.expected_unit_cost) || 0;

          // Si prix = 0, récupérer depuis le produit
          if (price <= 0 && product) {
            price = parseFloat(
              product.purchase_price ||
                product.cost_price ||
                product.price ||
                product.sale_price ||
                0
            );
          }

          return {
            product_id: parseInt(i.product_id),
            qty_ordered: parseInt(i.qty_ordered) || 1,
            expected_unit_cost: price,
          };
        });

      // Vérifier qu'au moins un item a un prix > 0
      const totalValue = validItems.reduce(
        (sum, i) => sum + i.qty_ordered * i.expected_unit_cost,
        0
      );
      if (totalValue <= 0) {
        alert(
          "⚠️ Attention: Le total de la commande est de 0 FCFA. Vérifiez les prix des produits."
        );
      }

      const payload = {
        supplier_id: parseInt(form.supplier_id),
        supplier_name: supplier?.name,
        warehouse_id: warehouseId,
        expected_date: form.expected_date || null,
        items: validItems,
      };

      console.log(
        "[PurchaseModal] Creating purchase:",
        payload,
        "Total:",
        totalValue
      );
      await apiClient.post("/approvisionnement/purchases", payload);
      alert("✅ Commande créée et envoyée au fournisseur!");
      onSaved();
    } catch (err) {
      console.error("[PurchaseModal] Erreur création commande:", err);
      const errorMsg =
        err.response?.data?.error || err.response?.data?.message || err.message;
      alert("❌ Erreur: " + errorMsg);
    }
    setLoading(false);
  };

  return (
    <Modal title="Nouvelle Commande d'Achat" onClose={onClose} wide>
      <form onSubmit={submit} className="space-y-4">
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium mb-1">
              Fournisseur *
            </label>
            <select
              value={form.supplier_id}
              onChange={(e) =>
                setForm({ ...form, supplier_id: e.target.value })
              }
              className="w-full border rounded-lg px-3 py-2"
              required
            >
              <option value="">-- Sélectionnez --</option>
              {suppliers.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.name}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium mb-1">
              Date prévue
            </label>
            <input
              type="date"
              value={form.expected_date}
              onChange={(e) =>
                setForm({ ...form, expected_date: e.target.value })
              }
              className="w-full border rounded-lg px-3 py-2"
            />
          </div>
        </div>

        <div>
          <label className="block text-sm font-medium mb-2">Articles</label>
          <table className="w-full border rounded-lg overflow-hidden">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-3 py-2 text-left text-xs font-semibold">
                  Produit
                </th>
                <th className="px-3 py-2 w-20 text-xs font-semibold">Qté</th>
                <th className="px-3 py-2 w-28 text-xs font-semibold">
                  Prix Unit.
                </th>
                <th className="px-3 py-2 w-28 text-xs font-semibold">Total</th>
                <th className="w-10"></th>
              </tr>
            </thead>
            <tbody>
              {form.items.map((it, i) => (
                <tr key={i} className="border-t">
                  <td className="px-3 py-2">
                    <select
                      value={it.product_id}
                      onChange={(e) =>
                        updateItem(i, "product_id", e.target.value)
                      }
                      className="w-full border rounded px-2 py-1 text-sm"
                      required
                    >
                      <option value="">Produit...</option>
                      {products.map((p) => (
                        <option key={p.id} value={p.id}>
                          {p.name} ({p.code})
                        </option>
                      ))}
                    </select>
                  </td>
                  <td className="px-3 py-2">
                    <input
                      type="number"
                      min="1"
                      value={it.qty_ordered}
                      onChange={(e) =>
                        updateItem(i, "qty_ordered", e.target.value)
                      }
                      className="w-full border rounded px-2 py-1 text-sm text-center"
                    />
                  </td>
                  <td className="px-3 py-2">
                    <input
                      type="number"
                      min="0"
                      value={it.expected_unit_cost}
                      onChange={(e) =>
                        updateItem(i, "expected_unit_cost", e.target.value)
                      }
                      className="w-full border rounded px-2 py-1 text-sm text-right"
                    />
                  </td>
                  <td className="px-3 py-2 text-right font-medium">
                    {formatCurrency(it.qty_ordered * it.expected_unit_cost)}
                  </td>
                  <td className="px-2">
                    {form.items.length > 1 && (
                      <button
                        type="button"
                        onClick={() => removeItem(i)}
                        className="text-red-500 hover:text-red-700"
                      >
                        ✕
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
            <tfoot className="bg-gray-50">
              <tr>
                <td colSpan="3" className="px-3 py-2 text-right font-semibold">
                  Total:
                </td>
                <td className="px-3 py-2 text-right font-bold text-lg">
                  {formatCurrency(total())}
                </td>
                <td></td>
              </tr>
            </tfoot>
          </table>
          <button
            type="button"
            onClick={addItem}
            className="mt-2 text-blue-600 text-sm font-medium hover:underline"
          >
            + Ajouter un article
          </button>
        </div>

        <div className="flex justify-end gap-3 pt-4 border-t">
          <button
            type="button"
            onClick={onClose}
            className="px-4 py-2 bg-gray-100 rounded-lg hover:bg-gray-200"
          >
            Annuler
          </button>
          <button
            type="submit"
            disabled={loading}
            className="px-6 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 disabled:opacity-50"
          >
            {loading ? "Création..." : "Créer la commande"}
          </button>
        </div>
      </form>
    </Modal>
  );
}

export function ReceiveModal({ headers, purchase, onClose, onSaved }) {
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);

  // Charger les items de la commande via API
  useEffect(() => {
    const loadItems = async () => {
      try {
        const res = await apiClient.get(
          `/approvisionnement/purchases/${purchase.id}`
        );
        const data = res.data;
        const purchaseItems = (data.items || []).map((i) => ({
          purchase_item_id: i.id,
          product_name: i.product?.name || "Produit",
          qty_ordered: i.quantity_ordered,
          qty_received: i.quantity_ordered - (i.quantity_received || 0),
          unit_cost: i.unit_price || 0,
        }));
        setItems(purchaseItems);
      } catch (err) {
        console.error("[ReceiveModal] Erreur chargement items:", err);
        alert("❌ Erreur chargement des items");
      } finally {
        setLoading(false);
      }
    };
    loadItems();
  }, [purchase.id]);

  const updateItem = (i, field, value) => {
    const newItems = [...items];
    newItems[i][field] =
      field === "qty_received" ? parseInt(value) || 0 : parseFloat(value) || 0;
    setItems(newItems);
  };

  const submit = async (e) => {
    e.preventDefault();

    // Filtrer les items avec quantité > 0
    const validItems = items.filter(
      (i) => i.qty_received > 0 && i.purchase_item_id
    );

    if (validItems.length === 0) {
      alert("⚠️ Veuillez saisir au moins une quantité à réceptionner");
      return;
    }

    // Préparer le payload
    const payload = {
      received_items: validItems.map((i) => ({
        purchase_item_id: parseInt(i.purchase_item_id),
        qty_received: parseInt(i.qty_received),
        unit_cost: parseFloat(i.unit_cost) || 0,
      })),
    };

    console.log("[ReceiveModal] Envoi réception:", payload);

    setLoading(true);
    try {
      await apiClient.post(
        `/approvisionnement/purchases/${purchase.id}/receive`,
        payload
      );
      alert("✅ Réception enregistrée - Stock et CMP mis à jour");
      onSaved();
    } catch (err) {
      console.error("[ReceiveModal] Erreur:", err);
      console.error("[ReceiveModal] Response:", err.response?.data);
      let errorMsg =
        err.response?.data?.error || err.response?.data?.message || err.message;

      // Afficher les détails de validation si disponibles
      if (err.response?.data?.details) {
        const details = Object.values(err.response.data.details)
          .flat()
          .join(", ");
        errorMsg += " - " + details;
      }

      alert("❌ Erreur: " + errorMsg);
    }
    setLoading(false);
  };

  if (loading && items.length === 0) {
    return (
      <Modal title={`Réception - ${purchase.reference}`} onClose={onClose} wide>
        <div className="text-center py-8">Chargement des articles...</div>
      </Modal>
    );
  }

  return (
    <Modal title={`Réception - ${purchase.reference}`} onClose={onClose} wide>
      <form onSubmit={submit}>
        {items.length === 0 ? (
          <div className="text-center py-8 text-red-500">
            Aucun article trouvé pour cette commande
          </div>
        ) : (
          <table className="w-full border rounded-lg overflow-hidden mb-4">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-3 py-2 text-left text-sm font-semibold">
                  Produit
                </th>
                <th className="px-3 py-2 text-center text-sm font-semibold">
                  Commandé
                </th>
                <th className="px-3 py-2 text-center text-sm font-semibold">
                  Qté Reçue
                </th>
                <th className="px-3 py-2 text-center text-sm font-semibold">
                  Coût Unitaire
                </th>
              </tr>
            </thead>
            <tbody>
              {items.map((it, i) => (
                <tr key={i} className="border-t">
                  <td className="px-3 py-3 font-medium">{it.product_name}</td>
                  <td className="px-3 py-3 text-center text-gray-600">
                    {it.qty_ordered}
                  </td>
                  <td className="px-3 py-3">
                    <input
                      type="number"
                      min="0"
                      max={it.qty_ordered}
                      value={it.qty_received}
                      onChange={(e) =>
                        updateItem(i, "qty_received", e.target.value)
                      }
                      className="w-24 border rounded px-2 py-1 text-center mx-auto block"
                    />
                  </td>
                  <td className="px-3 py-3">
                    <input
                      type="number"
                      min="0"
                      value={it.unit_cost}
                      onChange={(e) =>
                        updateItem(i, "unit_cost", e.target.value)
                      }
                      className="w-28 border rounded px-2 py-1 text-right mx-auto block"
                    />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
        <div className="flex justify-end gap-3">
          <button
            type="button"
            onClick={onClose}
            className="px-4 py-2 bg-gray-100 rounded-lg hover:bg-gray-200"
          >
            Annuler
          </button>
          <button
            type="submit"
            disabled={loading || items.length === 0}
            className="px-6 py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700 disabled:opacity-50"
          >
            {loading ? "Validation..." : "✓ Valider Réception"}
          </button>
        </div>
      </form>
    </Modal>
  );
}

export function RequestModal({
  headers,
  products: initialProducts = [],
  fromWarehouseId: initialFromId,
  toWarehouseId: initialToId,
  onClose,
  onSaved,
}) {
  const [form, setForm] = useState({
    priority: "normal",
    needed_by_date: "",
    items: [{ product_id: "", qty_requested: 1 }],
  });
  const [loading, setLoading] = useState(false);
  const [products, setProducts] = useState(initialProducts);
  const [fromWarehouseId, setFromWarehouseId] = useState(initialFromId);
  const [toWarehouseId, setToWarehouseId] = useState(initialToId);
  const [dataLoading, setDataLoading] = useState(
    initialProducts.length === 0 || !initialFromId || !initialToId
  );

  // Charger les produits et warehouses si pas fournis
  useEffect(() => {
    const loadData = async () => {
      try {
        const promises = [];

        // Charger produits si nécessaire
        if (products.length === 0) {
          promises.push(apiClient.get("/products?per_page=100"));
        } else {
          promises.push(Promise.resolve({ data: products }));
        }

        // Charger warehouses si nécessaire
        if (!fromWarehouseId || !toWarehouseId) {
          promises.push(apiClient.get("/warehouses"));
        } else {
          promises.push(Promise.resolve(null));
        }

        const [prodRes, whRes] = await Promise.all(promises);

        // Produits
        const prods = prodRes.data?.data || prodRes.data || [];
        if (Array.isArray(prods) && prods.length > 0) setProducts(prods);

        // Warehouses
        if (whRes && whRes.data) {
          const whs = whRes.data?.data || whRes.data || [];
          const detailWh = whs.find((w) => w.type === "detail");
          const grosWh = whs.find((w) => w.type === "gros");
          if (detailWh && !fromWarehouseId) setFromWarehouseId(detailWh.id);
          if (grosWh && !toWarehouseId) setToWarehouseId(grosWh.id);
          console.log("[RequestModal] Warehouses loaded:", {
            detail: detailWh?.id,
            gros: grosWh?.id,
          });
        }

        setDataLoading(false);
      } catch (e) {
        console.error("[RequestModal] Erreur chargement:", e);
        setDataLoading(false);
      }
    };

    loadData();
  }, []);

  // Afficher le chargement
  if (dataLoading) {
    return (
      <Modal title="Nouvelle Demande de Stock" onClose={onClose}>
        <div className="text-center py-8">
          <div className="animate-spin w-8 h-8 border-4 border-green-600 border-t-transparent rounded-full mx-auto mb-4"></div>
          <p className="text-gray-600">Chargement des produits...</p>
        </div>
      </Modal>
    );
  }

  // Si toujours pas de produits après chargement
  if (products.length === 0) {
    return (
      <Modal title="Nouvelle Demande de Stock" onClose={onClose}>
        <div className="text-center py-8">
          <p className="text-red-600 font-medium">
            Aucun produit disponible. Veuillez d'abord créer des produits dans
            la section Gestion des Produits.
          </p>
          <button
            onClick={onClose}
            className="mt-4 px-4 py-2 bg-gray-200 rounded-lg"
          >
            Fermer
          </button>
        </div>
      </Modal>
    );
  }

  const addItem = () =>
    setForm({
      ...form,
      items: [...form.items, { product_id: "", qty_requested: 1 }],
    });

  const updateItem = (i, field, value) => {
    const items = [...form.items];
    items[i][field] = field === "qty_requested" ? parseInt(value) || 1 : value;
    setForm({ ...form, items });
  };

  const removeItem = (i) =>
    setForm({
      ...form,
      items: form.items.filter((_, j) => j !== i),
    });

  const submit = async (e) => {
    e.preventDefault();
    if (!form.items.some((i) => i.product_id))
      return alert("Ajoutez au moins un produit");

    setLoading(true);
    try {
      const payload = {
        from_warehouse_id: fromWarehouseId,
        to_warehouse_id: toWarehouseId,
        priority: form.priority,
        needed_by_date: form.needed_by_date || null,
        items: form.items.filter((i) => i.product_id),
      };
      console.log("[RequestModal] Creating request:", payload);
      const res = await apiClient.post("/approvisionnement/requests", payload);
      const requestId = res.data?.id;

      // Auto-submit
      if (requestId) {
        await apiClient.post(`/approvisionnement/requests/${requestId}/submit`);
      }
      alert("✅ Demande créée et soumise");
      onSaved();
    } catch (err) {
      console.error("[RequestModal] Erreur:", err);
      alert(
        "❌ Erreur: " +
          (err.response?.data?.error ||
            err.response?.data?.message ||
            err.message)
      );
    }
    setLoading(false);
  };

  return (
    <Modal title="Nouvelle Demande de Stock" onClose={onClose} wide>
      <form onSubmit={submit} className="space-y-4">
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium mb-1">Priorité</label>
            <select
              value={form.priority}
              onChange={(e) => setForm({ ...form, priority: e.target.value })}
              className="w-full border rounded-lg px-3 py-2"
            >
              <option value="low">Basse</option>
              <option value="normal">Normale</option>
              <option value="high">Haute</option>
              <option value="urgent">Urgente</option>
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium mb-1">
              Date besoin
            </label>
            <input
              type="date"
              value={form.needed_by_date}
              onChange={(e) =>
                setForm({ ...form, needed_by_date: e.target.value })
              }
              className="w-full border rounded-lg px-3 py-2"
            />
          </div>
        </div>

        <div>
          <label className="block text-sm font-medium mb-2">
            Produits demandés
          </label>
          {form.items.map((it, i) => (
            <div key={i} className="flex gap-2 mb-2">
              <select
                value={it.product_id}
                onChange={(e) => updateItem(i, "product_id", e.target.value)}
                className="flex-1 border rounded-lg px-3 py-2"
                required
              >
                <option value="">Sélectionnez un produit...</option>
                {products.map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.name} ({p.code})
                  </option>
                ))}
              </select>
              <input
                type="number"
                min="1"
                value={it.qty_requested}
                onChange={(e) => updateItem(i, "qty_requested", e.target.value)}
                className="w-24 border rounded-lg px-3 py-2 text-center"
                placeholder="Qté"
              />
              {form.items.length > 1 && (
                <button
                  type="button"
                  onClick={() => removeItem(i)}
                  className="text-red-500 px-2 hover:text-red-700"
                >
                  ✕
                </button>
              )}
            </div>
          ))}
          <button
            type="button"
            onClick={addItem}
            className="text-green-600 text-sm font-medium hover:underline"
          >
            + Ajouter un produit
          </button>
        </div>

        <div className="flex justify-end gap-3 pt-4 border-t">
          <button
            type="button"
            onClick={onClose}
            className="px-4 py-2 bg-gray-100 rounded-lg hover:bg-gray-200"
          >
            Annuler
          </button>
          <button
            type="submit"
            disabled={loading}
            className="px-6 py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700 disabled:opacity-50"
          >
            {loading ? "Envoi..." : "Soumettre la demande"}
          </button>
        </div>
      </form>
    </Modal>
  );
}

export function ServeModal({ headers, order, warehouseId, onClose, onSaved }) {
  const [items, setItems] = useState(
    (order.items || []).map((i) => ({
      item_id: i.id,
      product_name: i.product?.name || "Produit",
      qty_ordered: i.quantity || i.quantity_ordered || 0,
      qty_served:
        (i.quantity || i.quantity_ordered || 0) - (i.quantity_served || 0),
    }))
  );
  const [loading, setLoading] = useState(false);

  const updateItem = (i, value) => {
    const newItems = [...items];
    newItems[i].qty_served = parseInt(value) || 0;
    setItems(newItems);
  };

  const submit = async (e) => {
    e.preventDefault();
    setLoading(true);
    try {
      await apiClient.post(`/approvisionnement/orders/${order.id}/serve`, {
        warehouse_id: warehouseId,
        items_served: items.filter((i) => i.qty_served > 0),
      });
      alert("✅ Commande servie");
      onSaved();
    } catch (err) {
      console.error("[ServeModal] Erreur:", err);
      alert(
        "❌ Erreur: " +
          (err.response?.data?.error ||
            err.response?.data?.message ||
            err.message)
      );
    }
    setLoading(false);
  };

  return (
    <Modal title={`Servir - ${order.reference}`} onClose={onClose}>
      <form onSubmit={submit}>
        <table className="w-full border rounded-lg overflow-hidden mb-4">
          <thead className="bg-gray-50">
            <tr>
              <th className="px-3 py-2 text-left text-sm font-semibold">
                Produit
              </th>
              <th className="px-3 py-2 text-center text-sm font-semibold">
                Commandé
              </th>
              <th className="px-3 py-2 text-center text-sm font-semibold">
                À servir
              </th>
            </tr>
          </thead>
          <tbody>
            {items.map((it, i) => (
              <tr key={i} className="border-t">
                <td className="px-3 py-3 font-medium">{it.product_name}</td>
                <td className="px-3 py-3 text-center text-gray-600">
                  {it.qty_ordered}
                </td>
                <td className="px-3 py-3">
                  <input
                    type="number"
                    min="0"
                    max={it.qty_ordered}
                    value={it.qty_served}
                    onChange={(e) => updateItem(i, e.target.value)}
                    className="w-20 border rounded px-2 py-1 text-center mx-auto block"
                  />
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        <div className="flex justify-end gap-3">
          <button
            type="button"
            onClick={onClose}
            className="px-4 py-2 bg-gray-100 rounded-lg hover:bg-gray-200"
          >
            Annuler
          </button>
          <button
            type="submit"
            disabled={loading}
            className="px-6 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 disabled:opacity-50"
          >
            {loading ? "En cours..." : "🍽️ Servir"}
          </button>
        </div>
      </form>
    </Modal>
  );
}
