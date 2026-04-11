# Procurement flow

## Narrative

Sourcing leads to a **purchase order**. **Receiving** confirms what arrived and drives **inventory** increases. Supplier obligations roll into **accounts payable** when invoices are matched and approved.

## Diagram

```mermaid
flowchart LR
  RFQ[RFQ] --> PO[Purchase order]
  PO --> REC[Receiving]
  REC --> INV[Inventory movements]
  REC --> AP[Accounts payable]
```

## Implementation notes

- Receiving should create **inventory movements** (not silent stock bumps).
- AP entries should trace to **documented** procurement activity where possible.
