# 🏪 Copilot Prompt – Warehouse Module (Aligned with Inventory & COGS System)

## 📌 System Context

This is a **stock and financial management system** built with:

- Laravel (Backend)
- React (Frontend - Laravel starter kit)
- MySQL (Database)
- UUID (CHAR(36)) primary keys

The system is **ledger-based**, meaning:

- Inventory is NOT stored directly
- Stock is calculated from `inventory_transactions`
- All purchases and sales use `product_variants` (NOT products)

---

## 🎯 Goal

Implement a **Warehouse Management Module** that:

- Manages warehouses (CRUD)
- Displays real-time stock per warehouse
- Fully integrates with `inventory_transactions`

---

# 🧱 DATABASE ALIGNMENT (IMPORTANT)

Use existing schema:

Table: `warehouses`

- id (CHAR(36), PK)
- name (VARCHAR)
- location (TEXT)

Table: `inventory_transactions`

- warehouse_id (FK)
- variant_id (FK)
- quantity (positive/negative)

Table: `product_variants`

- id
- product_id
- color
- origin

Table: `products`

- id
- name

---

# 🧠 BACKEND (Laravel)

## 1. Model

Create `Warehouse` model:

- Primary key: id (non-incrementing, string UUID)
- Relationship:

```php
public function inventoryTransactions()
{
    return $this->hasMany(InventoryTransaction::class);
}
```

---

## 2. Migration

Use existing schema definition:

- id → CHAR(36)
- name → required
- location → nullable

---

## 3. Controller: WarehouseController

### Methods:

### index()

Return all warehouses

---

### store()

Create warehouse

Validation:

- name → required, unique
- location → nullable

---

### show($id)

Return warehouse details

---

### update($id)

Update warehouse

---

### destroy($id)

❗ IMPORTANT RULE:

Do NOT allow delete if warehouse is used in `inventory_transactions`

Check:

```sql
SELECT COUNT(*) FROM inventory_transactions WHERE warehouse_id = ?
```

If exists → return error

---

## 4. STOCK ENDPOINT (CRITICAL)

Create endpoint:

GET /api/warehouses/{id}/stock

---

### Logic:

Calculate stock per variant using:

```sql
SELECT
    pv.id AS variant_id,
    p.name AS product_name,
    pv.color,
    pv.origin,
    SUM(it.quantity) AS total_stock
FROM inventory_transactions it
JOIN product_variants pv ON pv.id = it.variant_id
JOIN products p ON p.id = pv.product_id
WHERE it.warehouse_id = ?
GROUP BY pv.id, p.name, pv.color, pv.origin
HAVING total_stock != 0
```

---

### Response:

Return:

- variant_id
- product_name
- color
- origin
- total_stock

---

## 5. IMPORTANT RULES

- NEVER store stock in warehouses table
- ALWAYS calculate from inventory_transactions
- ALWAYS use variant_id (not product_id)

---

# 🎨 FRONTEND (React)

## Page: WarehousePage.jsx

---

## 1. Warehouse List

Table columns:

- Name
- Location
- Actions:

    - View
    - Edit
    - Delete

---

## 2. Create / Edit Form

Fields:

- Name (required)
- Location (textarea)

Use modal or drawer UI

---

## 3. Warehouse Detail View

When user clicks "View":

---

### 📦 Stock Table

Columns:

- Product Name
- Color
- Origin
- Available Quantity

---

## 4. API Calls

Use:

- GET /api/warehouses
- POST /api/warehouses
- PUT /api/warehouses/{id}
- DELETE /api/warehouses/{id}
- GET /api/warehouses/{id}/stock

---

## 5. UX Rules

- Confirm before delete
- Show error if warehouse has transactions
- Show loading state
- Show empty state if no stock

---

# ⚙️ BUSINESS INTEGRATION

- Purchases and Sales MUST use warehouse_id
- Inventory is tracked per warehouse
- This module supports:

    - COGS calculation (indirectly)
    - Stock validation before sales

---

# 🚨 CRITICAL SYSTEM RULES

- Do NOT allow warehouse deletion if used
- Do NOT store stock manually
- Do NOT use products directly for inventory
- Always rely on inventory_transactions

---

# 🎯 EXPECTED OUTPUT

Copilot should generate:

- Warehouse model
- Migration (if not exists)
- Controller with CRUD
- Stock endpoint
- API routes
- React page:

    - Table
    - Form
    - Stock detail view

---

# 🧠 FINAL INSTRUCTION TO COPILOT

Follow this specification strictly and align with:

- Ledger-based inventory system
- Variant-based stock tracking
- Existing database schema

Do not simplify logic.

---
