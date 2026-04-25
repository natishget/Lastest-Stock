# 💰 Copilot Prompt – COGS Module (FIFO, LIFO, Weighted Average)

## 📌 System Context

You are working on a **financially accurate inventory system** built with:

* Laravel (Backend)
* React (Frontend)
* MySQL (Database)
* UUID (CHAR(36)) primary keys

The system is **ledger-based**:

* Inventory is derived from `inventory_transactions`
* Products are handled via `product_variants`
* COGS must be stored in `cogs_entries` (NOT calculated on the fly for historical data)

---

# 🎯 Goal

Implement a **COGS Calculation Module + Page** that:

1. Calculates **COGS per product variant**
2. Supports:

   * FIFO (default)
   * LIFO
   * Weighted Average
3. Displays:

   * Revenue
   * COGS
   * Gross Profit
4. Is **accurate, auditable, and performant**

---

# 🧱 DATABASE ALIGNMENT (USE EXISTING TABLES)

* `sale_items` → revenue source
* `cogs_entries` → stored COGS (truth)
* `inventory_cost_layers` → FIFO/LIFO source
* `inventory_valuation` → Weighted Average
* `inventory_transactions` → stock ledger
* `product_variants`, `products`

---

# 🧠 CORE FINANCIAL RULES

## Revenue

SUM(sale_items.total_price)

## COGS

SUM(cogs_entries.total_cost)

## Gross Profit

Revenue - COGS

---

# 🔥 CRITICAL DESIGN DECISION

COGS must be:

✅ Calculated at **sale time** (not on report load)
✅ Stored in `cogs_entries`
✅ Linked to `sale_items`

---

# ⚙️ BACKEND IMPLEMENTATION (Laravel)

## 1. Create Service: CostingService

Responsibilities:

* Handle all costing methods
* Generate COGS entries during sale
* Support FIFO, LIFO, Weighted Average

---

## 2. FIFO Implementation

Use:

inventory_cost_layers

Logic:

* Order by created_at ASC
* Deduct quantity from oldest layers
* For each deduction:
  → create cogs_entries row

---

## 3. LIFO Implementation

Same as FIFO but:

* Order by created_at DESC

---

## 4. Weighted Average Implementation

Use:

inventory_valuation

Formula:

avg_cost = total_cost / total_quantity

COGS:

cogs = quantity × avg_cost

---

## 5. COGS Entry Creation (MANDATORY)

For every sale_item:

Insert into `cogs_entries`:

* sale_item_id
* variant_id
* quantity
* unit_cost
* total_cost
* costing_method
* source_layer_id (NULL for weighted avg)

---

## 6. PERFORMANCE OPTIMIZATION (VERY IMPORTANT)

Since dataset can grow large:

### Use:

* Proper indexing:

  * variant_id
  * warehouse_id
  * created_at

### Avoid:

* Recalculating COGS from raw transactions on every request

### Strategy:

* Use `cogs_entries` as source of truth
* Aggregate using SQL GROUP BY

### Optional Optimization:

* Cache results (Redis or Laravel cache)
* Precompute summaries (daily/monthly)

---

# 📊 API ENDPOINT

## GET /api/cogs

Query params:

* start_date
* end_date
* costing_method (FIFO | LIFO | WEIGHTED_AVERAGE)

---

## Logic

IF costing_method == default (FIFO):

→ Use stored `cogs_entries`

IF user switches method:

→ Dynamically recalculate using:

* inventory_cost_layers (FIFO/LIFO)
* inventory_valuation (Weighted)

⚠️ Do NOT overwrite stored data

---

## SQL (Base Aggregation)

```sql
SELECT 
    pv.id AS variant_id,
    p.name AS product_name,
    pv.color,
    pv.origin,

    SUM(si.total_price) AS revenue,
    SUM(ce.total_cost) AS cogs,
    SUM(si.total_price) - SUM(ce.total_cost) AS gross_profit

FROM sale_items si
JOIN sales s ON s.id = si.sale_id
JOIN product_variants pv ON pv.id = si.variant_id
JOIN products p ON p.id = pv.product_id
LEFT JOIN cogs_entries ce ON ce.sale_item_id = si.id

WHERE s.sale_date BETWEEN ? AND ?

GROUP BY pv.id, p.name, pv.color, pv.origin
```

---

# 🎨 FRONTEND (React)

## Page: COGSPage.jsx

---

## 1. Controls

* Date range picker
* Costing method selector:

  * FIFO (default)
  * LIFO
  * Weighted Average

---

## 2. Table

Columns:

* Product Name
* Color
* Origin
* Quantity Sold
* Revenue
* COGS
* Gross Profit
* Profit Margin %

---

## 3. UX REQUIREMENTS

* Show loading spinner (calculation may be heavy)
* Show warning when switching costing method
* Debounce requests
* Use pagination for large datasets

---

## 4. Performance UX

* Cache last result
* Avoid unnecessary reloads
* Show “Calculating…” indicator

---

# ⚠️ CRITICAL BUSINESS RULES

* NEVER recalculate stored FIFO COGS
* NEVER overwrite cogs_entries
* ALWAYS tie COGS to sale_items
* ALWAYS use product_variants (not products)

---

# 🚨 EDGE CASES

* Partial layer consumption
* Zero stock prevention
* Sales spanning multiple cost layers
* Switching costing methods dynamically

---

# 🎯 EXPECTED OUTPUT

Copilot should generate:

* CostingService (core logic)
* FIFO/LIFO/Weighted algorithms
* API endpoint for COGS
* Optimized SQL queries
* React page with:

  * Filters
  * Table
  * Method switcher

---

# 🧠 FINAL INSTRUCTION

Follow this strictly:

* Ledger-based inventory
* Variant-based tracking
* Stored COGS for accuracy
* Efficient queries for scalability

Do NOT simplify logic.
