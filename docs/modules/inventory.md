# Inventory module

## Purpose

Maintain an accurate, auditable **quantity ledger** for products the organization buys, sells, or holds.

## Core rule

**All stock changes** go through **`inventory_movements`** (or equivalent movement records), including receipts, shipments, adjustments, and transfers if modeled.

## Boundaries

- **Procurement** and **sales** modules trigger movements through controlled operations (receiving, shipping, returns).
- Reporting and valuation policies (FIFO, average cost, and so on) build on movement history.
