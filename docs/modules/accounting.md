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
