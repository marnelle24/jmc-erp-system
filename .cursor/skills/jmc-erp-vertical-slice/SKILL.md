---
name: jmc-erp-vertical-slice
description: >-
  Implement an end-to-end JMC ERP feature (migration through UI) with tenant
  scoping, service layer, and inventory rules. Use when adding modules,
  documents, stock-related behavior, or API surfaces aligned with docs/.
---

# JMC ERP vertical slice

## When to apply

Use this skill when building or extending **tenant-scoped** ERP behavior: new tables, models, services, policies, Livewire screens, or HTTP endpoints. Do **not** skip steps for “quick” prototypes—tenancy and inventory rules are non-negotiable.

## Read first (project context)

Skim the relevant areas in `docs/` before coding:

| Topic | Location |
|-------|----------|
| Stack and layers | `docs/technical-architecture.md` |
| Conventions | `docs/development-conventions.md` |
| Entities and relationships | `docs/data-model.md` |
| Module boundaries | `docs/modules/overview.md` and the specific module file under `docs/modules/` |
| Business sequence | `docs/flows/` when the feature crosses procurement or sales |

Project rules in `.cursor/rules/` (`erp-project-context`, `erp-architecture`, `erp-laravel-livewire`) always apply.

## Implementation order

Work **bottom-up** so the domain is correct before UI.

1. **Migration**
   - Every persisted table that belongs to a tenant includes **`tenant_id`** (FK where a `tenants` table exists).
   - Add **foreign keys** and **indexes** for `tenant_id`, status/date filters, and join columns used in lists.
2. **Model**
   - Singular `PascalCase` model; table plural `snake_case`.
   - Define **Eloquent relationships** explicitly.
   - Apply **tenant scoping** (global scope, policy, or consistent explicit `where`—match existing app patterns once introduced).
3. **Authorization**
   - **Policy** (or equivalent) for view/create/update/delete aligned with tenant and roles.
4. **Validation**
   - **Form request** class(es) for HTTP/Livewire boundaries; validate all input.
5. **Business logic**
   - **Service class** (action-oriented name) for rules and side effects.
   - Use **DB transactions** for multi-step writes (documents + lines + inventory + accounting hooks).
   - **No business logic** in controllers or Livewire components beyond orchestration.
6. **HTTP / routing**
   - Thin controller: validate → authorize → delegate to service → return response.
7. **UI (Livewire 4)**
   - Single-file components; **minimal** logic; call services for heavy work.

Place domain code under **`/app/Domains/`** as the codebase grows, mirroring module boundaries in `docs/modules/`.

## Inventory and stock

- **Authoritative** quantity changes go through **`inventory_movements`** (and related models)—not silent updates to a `qty` column alone.
- Receiving, shipping, adjustments, and transfers (if modeled) **create movement rows** inside the same **transaction** as the operational document when applicable.
- If the feature does not touch stock, skip movement writes but **do not** break this pattern elsewhere.

## API (if applicable)

Align with `docs/api-outline.md`: tenant context on every request, consistent error shape, pagination/filter conventions for lists.

## Done checklist

- [ ] `tenant_id` present and scoped on all new tenant data
- [ ] Policies + form requests in use for the feature
- [ ] Service owns rules; controller/Livewire stay thin
- [ ] Transactions around critical multi-table writes
- [ ] N+1 avoided (eager load where lists join relations)
- [ ] Stock-impacting flows use **movements**, not ad hoc quantity edits
- [ ] `docs/data-model.md` or module doc updated if the schema meaningfully changed
