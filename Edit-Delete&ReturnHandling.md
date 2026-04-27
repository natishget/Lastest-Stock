# 🔒 Copilot Prompt – Safe Edit/Delete, Reversal & Return System

## 📌 System Context

This is a **financially accurate inventory system** built with:

- Laravel (Backend)
- React (Frontend)
- MySQL
- UUID (CHAR(36)) primary keys

The system is **ledger-based**:

- Inventory comes from `inventory_transactions`
- COGS comes from `cogs_entries`
- Cost layers exist in `inventory_cost_layers`

⚠️ Financial data must NEVER be corrupted.

---

# 🎯 Goal

Implement a **safe correction system** for:

- Sales
- Purchases

WITHOUT using unsafe edit/delete.

---

# 🚨 CRITICAL RULE

❌ NEVER hard delete transactions
❌ NEVER directly edit quantity, cost, or variant after posting

✅ ALWAYS use:

- Reversal (void)
- Returns
- Adjustment entries

---

# 🧱 DATABASE CHANGES

## 1. Add Status Field

### sales

- status ENUM('POSTED', 'VOIDED')

### purchases

- status ENUM('POSTED', 'VOIDED')

---

## 2. Optional Reference Linking

Add:

- reference_id (for reversal linkage)
- reference_type ('SALE', 'PURCHASE', 'RETURN', 'VOID')

---

# ⚙️ BACKEND IMPLEMENTATION (Laravel)

## 1. Create Services

- SalesService
- PurchaseService
- CorrectionService (NEW)

---

# 🔁 2. VOID (REVERSAL) LOGIC

## A. Void Sale

Method: voidSale($saleId)

Steps:

1. Fetch sale + sale_items
2. Ensure status = POSTED
3. Mark sale as VOIDED

---

4. Reverse Inventory:

Insert into `inventory_transactions`:

- Same variant_id
- Same warehouse_id
- quantity = POSITIVE (return stock)
- transaction_type = 'SALE_RETURN'

---

5. Reverse COGS:

- Find related `cogs_entries`
- Insert reverse entries OR mark reversed

---

6. Revenue impact:

- Either:

    - subtract from reporting
    - or mark sale excluded

---

## B. Void Purchase

Method: voidPurchase($purchaseId)

Steps:

1. Mark as VOIDED

2. Reverse Inventory:

- quantity = NEGATIVE
- transaction_type = 'PURCHASE_RETURN'

3. Reverse cost layers:

- Reduce or remove corresponding `inventory_cost_layers`

---

# 🔄 3. RETURN SYSTEM (SEPARATE FROM VOID)

## A. Sales Return

Method: createSalesReturn()

Steps:

- Accept returned quantity per item

Then:

1. Add stock back:

inventory_transactions → +quantity

2. Reverse COGS proportionally

3. Adjust revenue

---

## B. Purchase Return

Method: createPurchaseReturn()

Steps:

1. Reduce stock:

inventory_transactions → -quantity

2. Adjust cost layers

---

# ✏️ 4. SAFE EDITING RULES

## Allowed:

- customer_name
- supplier_name
- notes

## NOT allowed:

- quantity
- variant_id
- cost
- price

---

# 🧠 OPTIONAL: DRAFT MODE (RECOMMENDED)

Allow:

- Edit ONLY when status = DRAFT

Once POSTED:

- Lock financial fields

---

# 📊 REPORTING RULES

When calculating:

## Revenue

Exclude VOIDED sales

## COGS

Use only valid `cogs_entries`

## Inventory

Always derived from `inventory_transactions`

---

# ⚡ PERFORMANCE + CONSISTENCY

- Use DB transactions for all operations
- Ensure atomic operations (all or nothing)
- Add indexes on:

    - reference_id
    - variant_id
    - transaction_type

---

# 🎨 FRONTEND (React)

## Actions per Sale/Purchase

- View
- Void
- Return
- Edit (limited fields only)

---

## UX Rules

- Confirmation before void
- Warning: "This will reverse stock and financial impact"
- Show status badge:

    - POSTED
    - VOIDED

---

# 🚨 EDGE CASES

- Partial returns
- Multi-item sales
- FIFO layers already consumed
- Reversals must respect costing method

---

# 🎯 EXPECTED OUTPUT

Copilot should generate:

- Migration updates (status fields)
- Services:

    - voidSale()
    - voidPurchase()
    - returnSale()
    - returnPurchase()

- Proper inventory + COGS reversal logic
- API endpoints
- Frontend actions (buttons + flows)

---

# 🧠 FINAL INSTRUCTION

Follow strictly:

- Ledger-based system
- No destructive operations
- Full auditability
- Financial correctness over simplicity

Do NOT simplify logic.
