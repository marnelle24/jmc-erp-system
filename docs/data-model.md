# Data model

This section describes **directional** entities for the JMC ERP domain. Physical migrations are the source of truth; update this document when the schema meaningfully changes.

## Tenancy

- All tenant-owned tables include **`tenant_id`** (and typically a foreign key to a `tenants` or equivalent table).

## Core reference entities

| Concept | Purpose |
|---------|---------|
| **Suppliers** | Vendors for procurement and AP |
| **Customers** | Buyers for sales and AR |
| **Products** | Items that can be bought, sold, and stored |

## Operational documents

| Concept | Purpose |
|---------|---------|
| **Purchase orders** | Commitments to buy from suppliers |
| **Sales orders** | Commitments to sell to customers |
| **Inventory movements** | **Authoritative** log of quantity changes (receipts, issues, adjustments, transfers as modeled) |

## Relationships (conceptual)

- Purchase orders and sales orders line up to **products** and affect **inventory** through **movements**.
- Accounting **AP** links to supplier-facing documents (for example, invoices from procurement).
- Accounting **AR** links to customer-facing documents (for example, sales invoices).

## Indexing and integrity

- Use **foreign keys** for relational integrity between tenants, parties, documents, and lines.
- Index **tenant_id** and common filter columns (status, dates, foreign keys used in lists and reports).

For module-specific emphasis, see [Modules](modules/overview.md).
