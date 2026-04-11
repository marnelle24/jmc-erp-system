# Sales module

## Purpose

Manage the **sell** side: customer **sales orders**, fulfillment that affects inventory, **invoicing**, and **payment** tracking.

## Typical chain

**Sales order → Inventory (fulfillment) → Invoice → AR → Payment**

See [Sales flow](../flows/sales.md).

## Boundaries

- **Customers** are master data (also in [CRM](crm.md)).
- Inventory decrements or reservations follow your defined rules but should remain traceable via **movements** where stock is physical.
