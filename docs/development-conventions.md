# Development conventions

These conventions align day-to-day coding with the architecture in [Technical architecture](technical-architecture.md).

## PHP

- **PSR-12** formatting.
- **Typed properties** and explicit types on methods where practical.

## Laravel

- **Form requests** for input validation.
- **Policies** (and gates where appropriate) for authorization.
- **Eloquent** relationships defined explicitly; **avoid N+1** via eager loading.
- **Database transactions** for operations that must succeed or fail together (posting documents, inventory updates, payment allocation, and similar).
- **DTOs** where they clarify boundaries between layers or external integrations.

## Livewire

- **Livewire 4** single-file components.
- Keep **presentation and minimal UI state** in the component; **delegate** calculations and side effects to **services**.

## Database

- **Foreign keys** and **indexes** on lookup and join columns.
- Table names: plural **`snake_case`**. Model names: singular **`PascalCase`**. Service classes: **action-oriented** names (for example, `CreatePurchaseOrder`, `PostGoodsReceipt`).

## Security and SaaS

- **Validate** all input at the boundary (form requests / API).
- **Never** commit or expose secrets; configuration stays in environment and secure stores.

## Collaboration roles (optional)

For larger changes, it can help to label work by focus: **architect** (modules, tenancy), **backend** (Laravel, migrations, services), **frontend** (Livewire), **QA** (flows and edge cases), **refactor** (performance and clarity).
