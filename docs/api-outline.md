# API outline

JMC ERP may expose **HTTP APIs** for integrations (mobile clients, partner systems, scripts). This document groups **contract areas** by domain; concrete routes, payloads, and versioning should be defined in implementation (OpenAPI or similar).

## Cross-cutting

- **Authentication and tenant context** on every request (token or session bound to a tenant).
- **Consistent error shape** (for example, validation errors vs. domain errors vs. system errors).
- **Pagination and filtering** conventions for list endpoints.

## Procurement

- RFQ lifecycle (create, send, compare, award—where applicable).
- Purchase orders (CRUD, submit, cancel, receive against PO).
- Supplier master data (scoped to tenant).

## Sales

- Sales orders (CRUD, confirm, fulfill, cancel).
- Customer master data.
- Invoices and payment intent (aligned with accounting).

## Inventory

- Stock queries by product and location (if locations are modeled).
- Movement history and controlled **adjustment** operations (permissions-heavy).

## Accounting

- AP: bills/invoices due to suppliers, allocation to payments.
- AR: customer invoices, allocation to receipts.

## CRM

- Supplier and customer profiles, contacts, and linkage to operational documents.

When APIs are implemented, replace this outline with **versioned** route lists and request/response schemas.
