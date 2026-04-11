# Technical architecture

## Stack

| Layer | Choice |
|-------|--------|
| Backend | Laravel 13 |
| UI | Livewire 4 |
| Database | MySQL |

## Application structure

- **Domain-oriented layout:** Feature and domain code lives under `/app/Domains/`, grouped by bounded area (procurement, inventory, sales, and so on) as the codebase grows.
- **Request flow:** **Controllers → Services → Models.** HTTP controllers and Livewire components orchestrate; **business logic belongs in service classes**, not in UI layers.
- **Inventory:** Stock changes are represented through **`inventory_movements`** (and related rules), keeping auditability and consistency.

## Multi-tenancy

- **Every persisted table** that holds tenant-owned data includes **`tenant_id`**.
- **Queries** for tenant data are **always** scoped by tenant (global scopes, policies, or explicit query constraints—choose one consistent approach per layer and document it in code reviews).

## Typical implementation artifact order

When adding a feature end to end:

1. Migration and model (with relationships and tenant fields)
2. Service(s) encapsulating rules and transactions
3. Form requests / validation and policies as needed
4. Livewire component(s) for the UI

This order keeps the domain correct before wiring the interface.
