---
name: AP Module Modernization
overview: "Modernize Accounts Payable into a market-standard operations module and add a significant, high-value feature: Payment Run Center (batch proposal, review, and execution) while preserving tenant-safe accounting controls."
todos:
  - id: schema-foundation
    content: Design and add tenant-scoped AP due-date and payment-run schema (migrations, enums, indexes, foreign keys).
    status: completed
  - id: service-layer
    content: Implement payment-run services and integrate execution with existing supplier payment/allocation transaction patterns.
    status: completed
  - id: ap-ui-modernization
    content: Redesign payables Livewire workspace with KPI cards, advanced filters, tabbed views, and bulk actions.
    status: completed
  - id: payment-run-ui
    content: Build payment run index/show Livewire pages for draft, approval, execution, and audit visibility.
    status: completed
  - id: auth-routing-nav
    content: Add routes, sidebar navigation, and policy gates for payment run actions and AP governance.
    status: completed
  - id: tests-docs
    content: Add/expand feature and Livewire tests for lifecycle, tenant isolation, and edge cases; update accounting module docs.
    status: completed
isProject: false
---

# Accounts Payable Modernization + Payment Run Center

## Goal
Transform `accounting/payables` from a basic posting/list page into a professional AP workspace with strong operational UX, actionable analytics, lifecycle controls, and a significant new capability: **batch payment run management**.

## Why This Feature First
The current code already has robust tenant-safe payment allocation and transactional controls, making a Payment Run Center the fastest path to significant business value (cash planning, AP throughput, governance) with lower implementation risk.

## Current Baseline (from code)
- AP page is currently a single Livewire page with pending GR posting + open items list: [/Users/marnelleapat/Documents/jmc-erp-system/resources/views/pages/accounting/payables/index.blade.php](/Users/marnelleapat/Documents/jmc-erp-system/resources/views/pages/accounting/payables/index.blade.php)
- Existing supplier payment orchestration is transactional and lock-safe: [/Users/marnelleapat/Documents/jmc-erp-system/app/Domains/Accounting/Services/RecordSupplierPaymentService.php](/Users/marnelleapat/Documents/jmc-erp-system/app/Domains/Accounting/Services/RecordSupplierPaymentService.php)
- AP creation from GR is already in service layer: [/Users/marnelleapat/Documents/jmc-erp-system/app/Domains/Accounting/Services/PostAccountsPayableFromGoodsReceiptService.php](/Users/marnelleapat/Documents/jmc-erp-system/app/Domains/Accounting/Services/PostAccountsPayableFromGoodsReceiptService.php)
- Aging uses `posted_at` only (no due-date/terms yet): [/Users/marnelleapat/Documents/jmc-erp-system/app/Domains/Crm/Services/GetSupplierDashboardMetricsService.php](/Users/marnelleapat/Documents/jmc-erp-system/app/Domains/Crm/Services/GetSupplierDashboardMetricsService.php)

## Target Module Experience
- AP command center with KPI cards (open AP, overdue, due-this-week, payment-ready amount)
- Professional list UX: search, sort, filter chips, supplier filter, aging bucket filter, status filter, due-date range, export
- AP detail drawer/page: source docs, balances, allocations, timeline of financial events
- Payment Run Center:
  - Generate run proposals from due/overdue payables by date/method/supplier filters
  - Review/edit selection, enforce caps and validation
  - Execute run to create supplier payments + allocations in controlled transactions
  - Track run status (`draft`, `approved`, `processing`, `completed`, `cancelled`) and audit fields
- Add due-date/terms support so aging and payment priority are market-standard

## Data & Domain Changes
- Add AP commercial fields on payables (`invoice_number`, `invoice_date`, `due_date`, `payment_terms_days`, optional `priority`)
- Introduce payment-run entities (tenant-scoped):
  - `supplier_payment_runs`
  - `supplier_payment_run_items`
- Add appropriate indexes and FKs (`tenant_id`, `status`, `due_date`, `supplier_id`)
- Add enum for payment run lifecycle + policy coverage for approval/execution actions

## Service Architecture
- New services under [/Users/marnelleapat/Documents/jmc-erp-system/app/Domains/Accounting/Services](/Users/marnelleapat/Documents/jmc-erp-system/app/Domains/Accounting/Services):
  - `BuildSupplierPaymentRunService` (collect eligible AP items)
  - `ApproveSupplierPaymentRunService` (governance gate)
  - `ExecuteSupplierPaymentRunService` (create payments + allocations via transaction, reuse existing allocation logic)
  - `CancelSupplierPaymentRunService` (pre-execution cancellation)
- Reuse and extend existing patterns:
  - status math utility: [/Users/marnelleapat/Documents/jmc-erp-system/app/Domains/Accounting/Support/OpenItemStatusResolver.php](/Users/marnelleapat/Documents/jmc-erp-system/app/Domains/Accounting/Support/OpenItemStatusResolver.php)
  - tenant validation style from existing Form Requests

## UI/Livewire Modernization Plan
- Upgrade AP index component at [/Users/marnelleapat/Documents/jmc-erp-system/resources/views/pages/accounting/payables/index.blade.php](/Users/marnelleapat/Documents/jmc-erp-system/resources/views/pages/accounting/payables/index.blade.php):
  - convert to workspace layout with KPI strip, advanced filters, tabbed views (`All`, `Due`, `Overdue`, `Payment Ready`, `Runs`)
  - add bulk selection and “Add to payment run” action
- Create new Livewire pages for run lifecycle:
  - `resources/views/pages/accounting/payment-runs/index.blade.php`
  - `resources/views/pages/accounting/payment-runs/show.blade.php`
- Borrow polished list/filter/export interaction patterns from:
  - [/Users/marnelleapat/Documents/jmc-erp-system/resources/views/pages/inventory/movements/index.blade.php](/Users/marnelleapat/Documents/jmc-erp-system/resources/views/pages/inventory/movements/index.blade.php)
  - [/Users/marnelleapat/Documents/jmc-erp-system/resources/views/pages/procurement/purchase-orders/index.blade.php](/Users/marnelleapat/Documents/jmc-erp-system/resources/views/pages/procurement/purchase-orders/index.blade.php)

## Flow Diagram
```mermaid
flowchart LR
apItems[OpenPayablesDueItems] --> proposeRun[BuildSupplierPaymentRunService]
proposeRun --> runDraft[PaymentRunDraft]
runDraft --> approveRun[ApproveSupplierPaymentRunService]
approveRun --> executeRun[ExecuteSupplierPaymentRunService]
executeRun --> supplierPayment[SupplierPaymentRecords]
supplierPayment --> allocations[SupplierPaymentAllocations]
allocations --> payableStatus[OpenItemStatusResolverUpdate]
payableStatus --> runComplete[PaymentRunCompleted]
```

## Routes, Permissions, and Navigation
- Extend routes in [/Users/marnelleapat/Documents/jmc-erp-system/routes/web.php](/Users/marnelleapat/Documents/jmc-erp-system/routes/web.php) for payment run pages/actions
- Add menu links in [/Users/marnelleapat/Documents/jmc-erp-system/resources/views/layouts/app/sidebar.blade.php](/Users/marnelleapat/Documents/jmc-erp-system/resources/views/layouts/app/sidebar.blade.php)
- Add/extend policies near:
  - [/Users/marnelleapat/Documents/jmc-erp-system/app/Policies/AccountsPayablePolicy.php](/Users/marnelleapat/Documents/jmc-erp-system/app/Policies/AccountsPayablePolicy.php)
  - [/Users/marnelleapat/Documents/jmc-erp-system/app/Policies/SupplierPaymentPolicy.php](/Users/marnelleapat/Documents/jmc-erp-system/app/Policies/SupplierPaymentPolicy.php)

## QA & Hardening
- Add feature tests for payment run lifecycle and AP filters:
  - follow patterns from [/Users/marnelleapat/Documents/jmc-erp-system/tests/Feature/Accounting/AccountingFlowTest.php](/Users/marnelleapat/Documents/jmc-erp-system/tests/Feature/Accounting/AccountingFlowTest.php)
  - and Livewire list/filter tests from [/Users/marnelleapat/Documents/jmc-erp-system/tests/Feature/Inventory/InventoryLedgerTest.php](/Users/marnelleapat/Documents/jmc-erp-system/tests/Feature/Inventory/InventoryLedgerTest.php)
- Validate tenant isolation, concurrency safety, allocation consistency, and status transitions
- Update accounting docs in [/Users/marnelleapat/Documents/jmc-erp-system/docs/modules/accounting.md](/Users/marnelleapat/Documents/jmc-erp-system/docs/modules/accounting.md)

## Delivery Phasing
1) Foundations: schema + enums + policies + service skeletons
2) AP workspace redesign + advanced filtering and due-date support
3) Payment Run Center (build/review/approve/execute)
4) Export/reporting polish + tests + docs