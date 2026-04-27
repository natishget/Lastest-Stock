# 📊 Copilot Prompt – Financial Dashboard (Revenue, COGS, Gross Profit)

## 📌 System Context

This is a **financially accurate inventory system** built with:

- Laravel (Backend)
- React (Frontend)
- MySQL
- UUID (CHAR(36))

The system uses:

- `sale_items` → Revenue source
- `cogs_entries` → COGS source
- `sales.sale_date` → date reference
- `product_variants` → product tracking

⚠️ Important:

- Do NOT calculate COGS dynamically from inventory
- Always use `cogs_entries` for financial reporting

---

# 🎯 Goal

Implement a **Dashboard Page** that includes:

### 1. 📈 Line Chart (12 Months)

- Revenue
- COGS
- Gross Profit

### 2. 📊 Horizontal Bar Chart

- Top 10–15 product variants by sales

---

# 🧠 FISCAL YEAR REQUIREMENT (CRITICAL)

The system uses Ethiopian fiscal year:

👉 July 1 → June 30

Example:

- 2025 Fiscal Year = July 1, 2025 → June 30, 2026

---

## Logic

If user selects year = 2025:

Start:

```text id="sfy1"
2025-07-01
```

End:

```text id="sfy2"
2026-06-30
```

---

# ⚙️ BACKEND IMPLEMENTATION (Laravel)

## 1. Create DashboardController

---

## 2. Endpoint: Monthly Financial Data

### GET /api/dashboard/financials

### Query Params:

- fiscal_year (e.g., 2025)

---

## 3. SQL Logic (Monthly Aggregation)

```sql id="m8o4yl"
SELECT
    DATE_FORMAT(s.sale_date, '%Y-%m') AS month,

    SUM(si.total_price) AS revenue,
    SUM(ce.total_cost) AS cogs,
    SUM(si.total_price) - SUM(ce.total_cost) AS gross_profit

FROM sales s
JOIN sale_items si ON si.sale_id = s.id
LEFT JOIN cogs_entries ce ON ce.sale_item_id = si.id

WHERE s.sale_date BETWEEN ? AND ?
AND s.status = 'POSTED'

GROUP BY month
ORDER BY month ASC;
```

---

## 4. Normalize Months (IMPORTANT)

Ensure ALL 12 months exist:

Even if no data → return 0

Example output:

```json id="ex1"
[
  { "month": "2025-07", "revenue": 0, "cogs": 0, "gross_profit": 0 },
  ...
]
```

---

## 5. Endpoint: Top Products

### GET /api/dashboard/top-products

Params:

- fiscal_year
- limit (default 10–15)

---

## SQL

```sql id="g7r8jk"
SELECT
    pv.id,
    p.name,
    pv.color,
    pv.origin,

    SUM(si.quantity) AS total_quantity,
    SUM(si.total_price) AS revenue

FROM sale_items si
JOIN sales s ON s.id = si.sale_id
JOIN product_variants pv ON pv.id = si.variant_id
JOIN products p ON p.id = pv.product_id

WHERE s.sale_date BETWEEN ? AND ?
AND s.status = 'POSTED'

GROUP BY pv.id, p.name, pv.color, pv.origin
ORDER BY total_quantity DESC
LIMIT ?;
```

---

# ⚡ PERFORMANCE REQUIREMENTS

- Add indexes on:

    - sales.sale_date
    - sale_items.variant_id
    - cogs_entries.sale_item_id

- Avoid recalculating COGS

- Use aggregation queries only

- Optionally cache results (Redis)

---

# 🎨 FRONTEND (React)

## Page: Dashboard.jsx

---

## 1. Line Chart (Financial Trends)

Use library (Recharts or Chart.js)

### Data:

- X-axis → Month
- Y-axis:

    - Revenue
    - COGS
    - Gross Profit

---

## 2. Horizontal Bar Chart (Top Products)

### Y-axis:

- Product Variant Name:
  format:
  "Aluminum Panel - Blue - Imported"

### X-axis:

- Quantity sold OR Revenue

---

## 3. Controls

- Fiscal Year Selector (dropdown)
- Auto-load current fiscal year

---

## 4. UX Requirements

- Loading states
- Smooth transitions
- Tooltip with values
- Legend for Revenue / COGS / Profit

---

# ⚠️ CRITICAL RULES

- Always use `sale_items` for revenue
- Always use `cogs_entries` for COGS
- Never compute cost from inventory directly
- Always filter by `sales.sale_date`
- Exclude VOIDED sales

---

# 🎯 EXPECTED OUTPUT

Copilot should generate:

- DashboardController
- 2 API endpoints:

    - financials (monthly)
    - top-products

- Optimized SQL queries
- React Dashboard page:

    - Line chart (12 months)
    - Horizontal bar chart (Top products)
    - Fiscal year selector

---

# 🧠 FINAL INSTRUCTION

Follow strictly:

- Ethiopian fiscal year logic
- Ledger-based accounting
- Stored COGS (not recalculated)
- Performance optimized queries

Do NOT simplify logic.
