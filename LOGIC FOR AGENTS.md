# 🏗️ Stock Management System – Copilot Guideline

## 📌 Overview

This system is a **stock (inventory) and financial tracking system** for an aluminum trading company.

The company:

- Buys products (Purchase)
- Sells products (Sales)
- Does NOT manufacture
- Tracks stock based on transactions
- Calculates:

    - Revenue
    - Cost of Goods Sold (COGS)
    - Gross Profit

Products are handled as **variants**, meaning:

- Same product with different **color** = different item
- Same product with different **origin (LOCAL / IMPORTED)** = different item

---

# 🧠 Core System Principles

## 1. Inventory is NOT stored directly

There is **no “stock” column**.

Stock is calculated from:

inventory = SUM(inventory_transactions.quantity)

---

## 2. Ledger-Based System

All stock changes are recorded in:

inventory_transactions

This is the **single source of truth**.

---

## 3. Financial Accuracy Rule

We NEVER recalculate past cost dynamically.

Instead:

- COGS is stored at the time of sale
- This ensures historical accuracy

---

# 👤 User Responsibility

Every transaction must track:

- created_by (user_id)

Users have roles:

- ADMIN
- SALES
- AUDITOR

---

# 💰 Financial Concepts (VERY IMPORTANT)

## 🔹 Revenue

Revenue is the total money from sales.

Formula:

Revenue = SUM(sale_items.total_price)

---

## 🔹 COGS (Cost of Goods Sold)

COGS is the cost of the products that were sold.

It depends on the costing method.

We support:

- FIFO (First In First Out)
- LIFO (Last In First Out)
- Weighted Average

COGS is stored in:

cogs_entries

---

## 🔹 Gross Profit

Formula:

Gross Profit = Revenue - COGS

---

# 🔄 Costing Methods (CRITICAL LOGIC)

## 1. FIFO (First In First Out)

- Oldest inventory is sold first
- Uses inventory_cost_layers ordered by oldest

Example:

- Buy 10 units @ 100
- Buy 10 units @ 120
- Sell 15 units

COGS:

- 10 × 100
- 5 × 120

---

## 2. LIFO (Last In First Out)

- Newest inventory is sold first

Example:

- Sell 15 units

COGS:

- 10 × 120
- 5 × 100

---

## 3. Weighted Average

Formula:

avg_cost = total_cost / total_quantity

- Every sale uses the same average cost

---

# 🧾 Core Tables Responsibility

## inventory_transactions

- Tracks ALL stock movement
- Used for stock calculation

## inventory_cost_layers

- Used ONLY for FIFO / LIFO
- Each purchase creates a layer

## inventory_valuation

- Used ONLY for Weighted Average

## cogs_entries

- Stores cost of each sale
- MUST be created during sale

## sale_items

- Stores revenue data

---

# ⚙️ Application Logic Flow

## ✅ Purchase Flow

1. Create purchase

2. Create purchase_items

3. Insert inventory_transactions (+quantity)

4. IF FIFO/LIFO:
   → Create inventory_cost_layers

5. IF Weighted Average:
   → Update inventory_valuation

---

## ✅ Sales Flow

1. Create sale

2. Create sale_items

3. Based on costing method:

### FIFO / LIFO:

- Read inventory_cost_layers
- Deduct quantity
- Create cogs_entries per layer used

### Weighted Average:

- Read avg_unit_cost from inventory_valuation
- Multiply by quantity
- Create cogs_entries

4. Insert inventory_transactions (-quantity)

---

# ⚠️ Critical Rules

- NEVER delete financial records
- NEVER update past COGS
- ALWAYS insert new records for adjustments

---

# 🧱 Laravel Implementation Guidelines

## Models to Create

- User
- Product
- ProductVariant
- Warehouse
- Purchase
- PurchaseItem
- Sale
- SaleItem
- InventoryTransaction
- InventoryCostLayer
- InventoryValuation
- CogsEntry
- CostingMethod
- SystemSetting

---

## Migration Rules

- Use UUID (CHAR(36)) as primary keys
- Use foreign keys for all relations
- Add indexes on:

    - variant_id
    - warehouse_id
    - transaction_date

---

## Relationships (Eloquent)

Example:

Product hasMany ProductVariant
Sale hasMany SaleItem
Purchase hasMany PurchaseItem

---

## Service Layer (IMPORTANT)

Do NOT put business logic in controllers.

Create services:

- PurchaseService
- SalesService
- InventoryService
- CostingService

---

## Costing Service Responsibilities

- Handle FIFO / LIFO layer consumption
- Handle Weighted Average calculation
- Create cogs_entries

---

# 🚨 Common Mistakes to Avoid

- ❌ Storing stock in a column
- ❌ Calculating COGS on the fly
- ❌ Not storing COGS per sale
- ❌ Mixing variants incorrectly
- ❌ Ignoring transaction history

---

# 📊 Reporting Logic

## Revenue

SUM(sale_items.total_price)

## COGS

SUM(cogs_entries.total_cost)

## Gross Profit

Revenue - COGS

---

# ✅ Final Notes

This system is designed to be:

- Scalable
- Auditable
- Financially accurate

Copilot should prioritize:

- Data integrity
- Clear separation of concerns
- Service-based architecture

---
