# Accounting module

## Purpose

Track **accounts payable** (money owed to suppliers) and **accounts receivable** (money owed by customers), aligned with operational documents.

## Scope in MVP

- **AP** driven by procurement-related invoices and payments.
- **AR** driven by sales invoices and receipts.

## Boundaries

- Operational modules (procurement, sales, inventory) remain the **source of operational truth**; accounting posts summarize and reconcile those events according to policy.

## MVP implementation (schema)

- **AR:** an `accounts_receivable` row is created when a **sales invoice** is issued (totals from invoice lines). **Customer payments** allocate to one or more open receivables; balances and `open` / `partial` / `paid` status update in the same transaction.
- **AP:** an `accounts_payable` row is created when accounting **posts** a **posted goods receipt** (amount from receipt quantities × PO line `unit_cost`). **Supplier payments** allocate to open payables the same way.

## Accounts payable modernization

- `accounts_payable` now stores invoice-level commercial terms (`invoice_number`, `invoice_date`, `due_date`, `payment_terms_days`, `priority`) for operations-grade filtering and aging.
- AP aging and due prioritization should use `due_date` when present and fall back to `posted_at` for legacy rows.

## Payment run center

- New batch payment orchestration tables:
  - `supplier_payment_runs`
  - `supplier_payment_run_items`
- Lifecycle states: `draft` -> `approved` -> `processing` -> `completed` (or `cancelled`).
- Execution uses transactional supplier payment creation and allocation logic, preserving existing open-item controls and row-level balance checks.
