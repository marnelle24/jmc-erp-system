# Data model

This section describes **directional** entities for the JMC ERP domain. Physical migrations are the source of truth; update this document when the schema meaningfully changes.

## Tenancy

- **`tenants`** holds one row per organization (business) on the platform.
- **`tenant_user`** links users to tenants with a **role** (for example `owner`); a user can exist before they belong to any tenant (sign up without an organization).
- After sign-in, the app requires at least one membership before tenant-scoped ERP routes; **current tenant** is tracked in session (`current_tenant_id`).
- All tenant-owned business tables include **`tenant_id`** (foreign key to `tenants`).

## Core reference entities

| Concept | Purpose |
|---------|---------|
| **Suppliers** | Vendors for procurement and AP |
| **Customers** | Buyers for sales and AR |
| **Products** | Items that can be bought, sold, and stored (no standalone stock quantity column—balances derive from movements) |

## Operational documents

| Concept | Purpose |
|---------|---------|
| **Purchase orders** | Commitments to buy from suppliers |
| **Sales orders** | Commitments to sell to customers |
| **Inventory movements** | **Authoritative** log of quantity changes (receipts, issues, adjustments, transfers as modeled) |

### Physical columns (inventory)

- **`products`:** `tenant_id`, `name`, optional `sku` (unique per tenant), optional `description`.
- **`inventory_movements`:** `tenant_id`, `product_id`, signed `quantity` (decimal), `movement_type` (`receipt`, `issue`, `adjustment`, `transfer`), optional `notes`, optional polymorphic `reference` for future document links (PO, shipment, etc.).

On-hand quantity for a product is the **sum** of `inventory_movements.quantity` for that product (within the tenant).

## Relationships (conceptual)

- Purchase orders and sales orders line up to **products** and affect **inventory** through **movements**.
- Accounting **AP** links to supplier-facing documents (for example, invoices from procurement).
- Accounting **AR** links to customer-facing documents (for example, sales invoices).

## Indexing and integrity

- Use **foreign keys** for relational integrity between tenants, parties, documents, and lines.
- Index **tenant_id** and common filter columns (status, dates, foreign keys used in lists and reports).

For module-specific emphasis, see [Modules](modules/overview.md).
