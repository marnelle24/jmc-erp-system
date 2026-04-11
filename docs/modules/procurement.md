# Procurement module

## Purpose

Manage the **buy** side: sourcing, committing spend via **purchase orders**, and confirming receipt of goods or services.

## Typical chain

**RFQ → Purchase order → Receiving → Inventory (+ AP)**

See [Procurement flow](../flows/procurement.md).

## Boundaries

- **Suppliers** are master data (also surfaced in [CRM](crm.md)).
- **Inventory** increases from receiving are expressed as **movements**, not manual quantity edits.
- **AP** consumes finalized supplier invoices tied to procurement activity.
