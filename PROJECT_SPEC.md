# Shwe Peti Backend API Project Specification

## 1. Project Overview

Shwe Peti is a Laravel backend API application for managing hotel administration data. The system is designed for an internal admin web dashboard and exposes versioned REST APIs secured with Laravel Sanctum authentication.

The current release focuses on internal staff accounts, room setup, inventory setup, item master data, unit conversions, inventory stock movements, audit logs, soft deletes where needed, and consistent API responses.

## 2. Goals

- Provide a secure Laravel backend API for an internal hotel administration dashboard.
- Manage internal staff users and role-based access.
- Manage room categories with activation control.
- Manage rooms and their operational statuses.
- Manage unit groups, units, item categories, items, and item unit conversions.
- Manage warehouses and inventory stock transactions.
- Maintain inventory ledgers and stock balances from posted stock documents.
- Keep API responses consistent across success, validation, authentication, authorization, and error states.
- Preserve important operational history with audit logs.
- Use API versioning so future dashboard, mobile, or third-party clients can evolve safely.

## 3. Scope

### In Scope

- Internal staff authentication using Laravel Sanctum.
- API versioning, starting with `v1`.
- User management for internal staff.
- Role assignment for supported hotel administrator roles.
- Room category CRUD.
- Room CRUD.
- Room bed setup using `RoomBed` records and `RoomBedTypeEnum`.
- Room status management using `RoomStatusEnum`.
- Unit group CRUD and active toggle.
- Unit CRUD and active toggle.
- Item category CRUD and active toggle.
- Item CRUD, nested item unit conversion sync, and active toggle.
- Item unit conversion CRUD and active toggle.
- Warehouse CRUD and active toggle.
- Stock in, stock out, inventory transfer, and inventory adjustment documents.
- Draft, posted, and cancelled status workflows for inventory documents.
- Inventory ledger retrieval and stock balance retrieval.
- Warehouse-specific item list with stock balance quantity and stock unit.
- Soft delete and restore support where appropriate.
- Audit logging for model create, update, delete, and restore events using `owen-it/laravel-auditing`.
- MySQL database support for local development, testing, and production.
- Backend-only Laravel project with no frontend build tooling.

### Out of Scope for Initial Release

- Public guest/customer registration.
- Public booking website.
- Reservation/booking workflows.
- Payment processing.
- Restaurant ordering.
- Housekeeping task assignment.
- Multi-branch hotel support.
- Mobile app implementation.

These excluded areas may be added in later API versions or feature phases.

## 4. Technology Stack

- Framework: Laravel
- Authentication: Laravel Sanctum
- Database: MySQL
- Auditing: `owen-it/laravel-auditing`
- API Style: REST JSON API
- API Versioning: URI-based versioning, for example `/api/v1/...`
- Backend Type: API-only backend for an admin web dashboard

## 5. User Roles

The system is currently for internal staff only. The following roles are required:

1. Hotel Administrator
2. Front Office Administrator
3. Reservation Administrator
4. Restaurant Administrator
5. Customer Service Administrator
6. Inventory Administrator

### Role Intent

- Hotel Administrator: Full administrative access across users, room categories, rooms, inventory setup, items, item conversions, audit logs, and system settings.
- Front Office Administrator: Manage room availability and day-to-day room status changes.
- Reservation Administrator: View and update room availability/status related to reservation operations.
- Restaurant Administrator: Internal role reserved for future restaurant workflows.
- Customer Service Administrator: View room and category information to support guest service workflows.
- Inventory Administrator: Manage inventory setup, item master data, stock documents, stock balances, and ledgers where permitted.

## 6. Functional Requirements

### 6.1 Authentication

- Staff users must log in using credentials.
- Authentication must use Laravel Sanctum token-based authentication.
- API clients must send authenticated requests with a bearer token.
- The system must support logout by revoking the current access token.
- The system should support a `me` endpoint to return the authenticated staff profile.
- Guest/customer authentication is excluded from the initial release.

### 6.2 User Management

- Hotel Administrators can create internal staff users.
- Hotel Administrators can view a paginated list of users.
- Hotel Administrators can view user details.
- Hotel Administrators can update user profile fields and role assignment.
- Hotel Administrators can deactivate users.
- Hotel Administrators can soft delete users.
- Soft-deleted users should be restorable by Hotel Administrators.
- User create, update, delete, and restore changes should be audited.

Recommended user fields:

- `id`
- `name`
- `email`
- `password`
- `role`
- `is_active`
- `email_verified_at`
- `last_login_at`
- `created_by`
- `updated_by`
- `deleted_by`
- `created_at`
- `updated_at`
- `deleted_at`

### 6.3 Room Category Management

Room categories define the classification of rooms.

Required fields:

- `id`
- `name`
- `description`
- `is_active`
- `created_by`
- `updated_by`
- `deleted_by`
- `created_at`
- `updated_at`
- `deleted_at`

Validation rules:

- `name` is required.
- `name` must be unique among non-deleted room categories.
- `name` must be trimmed before validation and persistence.
- Internal repeated whitespace in `name` should be normalized to a single space.
- `description` is nullable.
- `is_active` must be boolean.

Functional behavior:

- Authorized users can create room categories.
- Authorized users can list room categories.
- Authorized users can filter room categories by `is_active`.
- Authorized users can view a single room category.
- Authorized users can update room categories.
- Authorized users can soft delete room categories.
- Authorized users can restore soft-deleted room categories.
- A room category should not be deleted if active rooms depend on it, unless a force policy is intentionally added later.
- Create and update use one `POST /api/v1/admin/room-categories` endpoint with optional `id`.

### 6.4 Room Management

Rooms represent the hotel’s physical room inventory.

Required fields:

- `id`
- `name`
- `room_category_id`
- `price`
- `status`
- `created_by`
- `updated_by`
- `deleted_by`
- `created_at`
- `updated_at`
- `deleted_at`

Validation rules:

- `name` is required.
- `name` must be unique among non-deleted rooms.
- `name` must be trimmed before validation and persistence.
- Internal repeated whitespace in `name` should be normalized to a single space.
- `room_category_id` is required and must reference an existing active room category unless business rules later allow inactive category assignment.
- `price` is required, must be numeric, and must be greater than or equal to `0`.
- `status` is required and must be one of the values defined by `RoomStatusEnum`.

Room beds:

- Rooms can have one or more `RoomBed` records.
- Each `RoomBed` record stores `room_id`, `bed_type`, and `qty`.
- `bed_type` must be one of the values defined by `RoomBedTypeEnum`.
- `qty` is required, must be an integer, and must be greater than or equal to `1`.
- A room should not have duplicate `RoomBed` records for the same `bed_type`.

Room bed types:

- `king_bed` - King Bed
- `queen_bed` - Queen Bed
- `twin_bed` - Twin Bed
- `single_bed` - Single Bed

Room statuses:

- `available`
- `occupied`
- `reserved`
- `dirty`
- `maintenance`
- `cleaning`
- `out_of_service`

Functional behavior:

- Authorized users can create rooms.
- Authorized users can list rooms.
- Authorized users can filter rooms by category and status.
- Authorized users can search rooms by name.
- Authorized users can view room details.
- Authorized users can update room category assignment.
- Authorized users can update room status.
- Authorized users can soft delete rooms.
- Authorized users can restore soft-deleted rooms.
- Status changes must be captured in audit logs.
- Create and update use one `POST /api/v1/admin/rooms` endpoint with optional `id`.

### 6.5 Inventory Setup

Inventory setup defines reusable units and item categories.

Unit group fields:

- `id`
- `name`
- `slug`
- `is_active`
- `created_at`
- `updated_at`

Unit fields:

- `id`
- `unit_group_id`
- `name`
- `symbol`
- `is_base`
- `is_active`
- `created_at`
- `updated_at`

Item category fields:

- `id`
- `parent_id`
- `name`
- `slug`
- `code`
- `description`
- `is_active`
- `created_at`
- `updated_at`

Functional behavior:

- Unit groups can be listed, searched, created, updated, shown, deleted, and toggled active/inactive.
- Units can be listed, searched, filtered by unit group, filtered by base unit state, created, updated, shown, deleted, and toggled active/inactive.
- Item categories can be listed, searched, filtered by parent, created, updated, shown, deleted, and toggled active/inactive.
- Unit group and item category slugs are generated from `name` when not provided.
- Item category `code` is normalized to uppercase underscore format.
- Non-paginated lists for active-capable resources return active records only.
- Paginated lists use the provided filters and return pagination metadata.
- Create and update use one `POST` endpoint with optional `id`.

Seeded unit groups and units:

- Weight: `kg`, `g`
- Volume: `L`, `ml`
- Quantity: `pcs`, `bottle`, `box`, `pack`, `carton`
- Length: `m`, `cm`

Seeded item categories:

- Raw Materials: Meat, Seafood, Vegetable, Rice & Grain, Spice, Cooking Oil
- Beverages: Soft Drink, Water, Juice, Coffee
- Consumables: Tissue, Takeaway, Packaging, Cleaning
- Accessories: Room Accessories, Kitchen Accessories, Equipment Accessories

### 6.6 Item Management

Items represent inventory master data.

Required item fields:

- `id`
- `item_category_id`
- `name`
- `code`
- `sku`
- `barcode`
- `stock_unit_id`
- `description`
- `min_stock`
- `is_active`
- `created_at`
- `updated_at`

Required item unit conversion fields:

- `id`
- `item_id`
- `from_unit_id`
- `to_unit_id`
- `conversion_factor`
- `is_active`
- `created_at`
- `updated_at`

Validation rules:

- `item_category_id` is required and must reference an existing item category.
- `name` is required and normalized for whitespace.
- `code` is required and must be unique.
- `sku` is nullable and must be unique when present.
- `barcode` is nullable and must be unique when present.
- `stock_unit_id` is required and must reference an existing unit.
- `min_stock` is nullable, numeric, and must be greater than or equal to `0`.
- `is_active` must be boolean when present.
- `conversion_factor` is required for conversions and must be greater than `0`.
- `from_unit_id` and `to_unit_id` must be different.
- A conversion must be unique by `item_id`, `from_unit_id`, and `to_unit_id`.

Functional behavior:

- Items can be listed, searched, filtered by category, filtered by stock unit, created, updated, shown, deleted, and toggled active/inactive.
- Items can accept nested `item_unit_conversions` in the save payload.
- When nested `item_unit_conversions` are provided, missing existing conversions for that item are deleted and submitted conversions are updated or created.
- Item unit conversions can also be managed directly through their own API endpoints.
- Non-paginated item and conversion lists return active records only.
- Create and update use one `POST` endpoint with optional `id`.
- Normal item list and item detail responses do not include stock balance fields.
- Warehouse-specific item lists include `balance_quantity` and a `balance` object with the item's stock unit.

### 6.7 Inventory Transactions

Inventory transactions are represented by draft documents that become stock-affecting only when their status changes to `posted`.

Inventory document statuses:

- `draft`
- `posted`
- `cancelled`

Adjustment types:

- `increase`
- `decrease`

Warehouse fields:

- `id`
- `code`
- `name`
- `description`
- `is_active`
- `created_at`
- `updated_at`

Stock in fields:

- `id`
- `reference_no`
- `warehouse_id`
- `transaction_date`
- `status`
- `remark`
- `created_by`
- `created_at`
- `updated_at`

Stock in item fields:

- `id`
- `stock_in_id`
- `item_id`
- `unit_id`
- `quantity`
- `base_quantity`
- `unit_cost`
- `total_cost`
- `batch_no`
- `expiry_date`
- `remark`
- `created_at`
- `updated_at`

Stock out fields:

- `id`
- `reference_no`
- `warehouse_id`
- `transaction_date`
- `status`
- `reason`
- `remark`
- `created_by`
- `created_at`
- `updated_at`

Stock out item fields:

- `id`
- `stock_out_id`
- `item_id`
- `unit_id`
- `quantity`
- `base_quantity`
- `unit_cost`
- `total_cost`
- `batch_no`
- `remark`
- `created_at`
- `updated_at`

Inventory transfer fields:

- `id`
- `reference_no`
- `from_warehouse_id`
- `to_warehouse_id`
- `transaction_date`
- `status`
- `remark`
- `created_by`
- `created_at`
- `updated_at`

Inventory transfer item fields:

- `id`
- `inventory_transfer_id`
- `item_id`
- `unit_id`
- `quantity`
- `base_quantity`
- `batch_no`
- `expiry_date`
- `remark`
- `created_at`
- `updated_at`

Inventory adjustment fields:

- `id`
- `reference_no`
- `warehouse_id`
- `transaction_date`
- `status`
- `reason`
- `remark`
- `created_by`
- `created_at`
- `updated_at`

Inventory adjustment item fields:

- `id`
- `inventory_adjustment_id`
- `item_id`
- `unit_id`
- `system_quantity`
- `physical_quantity`
- `adjustment_type`
- `adjustment_quantity`
- `base_quantity`
- `batch_no`
- `expiry_date`
- `remark`
- `created_at`
- `updated_at`

Functional behavior:

- Inventory save APIs create or update draft documents only.
- New inventory documents default to `draft`.
- Draft documents can change status to `posted` or `cancelled`.
- Posted and cancelled documents cannot change status again.
- Only draft documents can be edited or deleted.
- `reference_no` is generated by the backend when not provided.
- `batch_no` is generated by the backend for new document lines and preserved when existing lines are updated.
- `base_quantity` is calculated by the backend using the item's stock unit and item unit conversions.
- Clients must not provide `batch_no`, `base_quantity`, adjustment type, or adjustment quantity as source-of-truth values.
- Posting stock in adds stock balance and writes `in` ledger rows.
- Posting stock out subtracts stock balance and writes `out` ledger rows.
- Posting transfers subtracts from the source warehouse and adds to the destination warehouse.
- Transfer ledger rows use `transfer_out` and `transfer_in`.
- Posting adjustments applies increase or decrease movements based on physical count differences.
- Adjustment ledger rows use `adjustment_increase` and `adjustment_decrease`.
- Decrease operations must fail with validation errors when stock is insufficient.
- Inventory ledgers are read-only through API endpoints.
- Inventory stock balances are read-only through API endpoints.

Inventory ledger fields:

- `id`
- `item_id`
- `warehouse_id`
- `transaction_type`
- `reference_type`
- `reference_id`
- `quantity`
- `unit_id`
- `base_quantity`
- `unit_cost`
- `total_cost`
- `batch_no`
- `expiry_date`
- `balance_quantity`
- `transaction_date`
- `remark`
- `created_by`
- `created_at`
- `updated_at`

Inventory stock balance fields:

- `id`
- `item_id`
- `warehouse_id`
- `quantity`
- `reserved_quantity`
- `available_quantity`
- `created_at`
- `updated_at`

Warehouse-specific item list response:

```json
{
  "id": 1,
  "name": "Chicken",
  "stock_unit_id": 1,
  "balance_quantity": "7.000000",
  "balance": {
    "quantity": "7.000000",
    "unit_id": 1,
    "unit": {
      "id": 1,
      "name": "kg",
      "symbol": "kg"
    }
  }
}
```

### 6.8 Audit Logs

The system records audit logs using `owen-it/laravel-auditing`.

Required audited actions:

- User created
- User updated
- User deactivated
- User deleted
- User restored
- Room category created
- Room category updated
- Room category deleted
- Room category restored
- Room created
- Room updated
- Room status changed
- Room deleted
- Room restored
- Unit group created, updated, deleted
- Unit created, updated, deleted
- Item category created, updated, deleted
- Item created, updated, deleted
- Item unit conversion created, updated, deleted
- Warehouse created, updated, deleted
- Stock in created, updated, deleted
- Stock out created, updated, deleted
- Inventory transfer created, updated, deleted
- Inventory adjustment created, updated, deleted

Audit log fields:

- `id`
- `user_type`
- `user_id`
- `event`
- `auditable_type`
- `auditable_id`
- `old_values`
- `new_values`
- `url`
- `ip_address`
- `user_agent`
- `tags`
- `created_at`
- `updated_at`

Polymorphic `auditable_type` values must use the enforced morph map aliases:

- `user`
- `room_category`
- `room`
- `room_bed`
- `unit_group`
- `unit`
- `item_category`
- `item`
- `item_unit_conversion`
- `warehouse`
- `stock_in`
- `stock_in_item`
- `stock_out`
- `stock_out_item`
- `inventory_transfer`
- `inventory_transfer_item`
- `inventory_adjustment`
- `inventory_adjustment_item`

Audit log table UI should display:

- Date / Time
- User
- Event
- Module
- Record ID
- Summary
- IP Address

Expanded/detail view should display:

- URL
- User Agent
- Old Values
- New Values
- Tags

Summary examples:

- `Created item: CHICKEN - Chicken`
- `Updated item: name, min_stock, is_active`
- `Deleted room: Room 101`
- `Restored user: front@example.com`

### 6.9 Date and Time Response Format

All resource date-time fields must be serialized in the application timezone, `Asia/Yangon`, using this format:

```text
YYYY-MM-DD HH:mm:ss
```

Example:

```text
2026-09-15 14:47:41
```

The database date-time values may remain in the configured database/application timezone, but API resources must not return UTC `Z` ISO strings.

### 6.10 Activity History

Activity history is currently represented through audit logs. Separate entity activity endpoints are not implemented yet.

Supported entities:

- Users
- Room categories
- Rooms
- Room beds
- Unit groups
- Units
- Item categories
- Items
- Item unit conversions
- Warehouses
- Stock documents
- Inventory transfers
- Inventory adjustments

Activity history must include:

- Actor
- Action
- Changed fields
- Previous values where applicable
- New values where applicable
- Timestamp

## 7. Non-Functional Requirements

### 7.1 Security

- All protected endpoints must require Sanctum authentication.
- Passwords must be securely hashed using Laravel’s hashing service.
- Tokens must be revocable.
- Authorization must be enforced through policies, gates, middleware, or a role/permission package.
- Validation errors must not expose sensitive implementation details.
- Deleted records must not appear in normal list endpoints unless explicitly requested by authorized users.

### 7.2 API Consistency

All API responses must follow a consistent JSON shape.

Success response:

```json
{
  "success": true,
  "message": "Operation completed successfully.",
  "data": {},
  "meta": {}
}
```

Error response:

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {},
  "meta": {}
}
```

Pagination response should include metadata:

```json
{
  "success": true,
  "message": "Records retrieved successfully.",
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 100,
    "last_page": 7
  }
}
```

### 7.3 Maintainability

- Use Form Request classes for validation.
- Use API Resources for response transformation.
- Use Enums for fixed domain values such as room bed type and room status.
- Use Service classes where business logic grows beyond simple CRUD.
- Use Policies for authorization.
- Keep controllers thin.
- Keep database migrations explicit and reversible.

### 7.4 Performance

- Use pagination for list endpoints.
- Add indexes for searchable and filterable columns.
- Use eager loading to avoid N+1 database queries.
- Keep audit log queries paginated.

### 7.5 Reliability

- Use database transactions for multi-step write operations.
- Ensure audit logging does not break the main workflow when possible.
- Use soft deletes for recoverable administrative mistakes.

### 7.6 Observability

- Record audit logs for sensitive actions.
- Log unexpected exceptions through Laravel logging.
- Include request identifiers in logs if application monitoring is added later.

## 8. Data Model

### 8.1 Users Table

| Column | Type | Notes |
| --- | --- | --- |
| `id` | unsigned big integer | Primary key |
| `name` | string | Staff name |
| `email` | string | Unique |
| `password` | string | Hashed |
| `role` | string or enum | Internal staff role |
| `is_active` | boolean | Defaults to true |
| `email_verified_at` | timestamp nullable | Optional |
| `last_login_at` | timestamp nullable | Updated on login |
| `created_by` | unsigned big integer nullable | User who created record |
| `updated_by` | unsigned big integer nullable | User who last updated record |
| `deleted_by` | unsigned big integer nullable | User who deleted record |
| `remember_token` | string nullable | Laravel default |
| `created_at` | timestamp | Laravel default |
| `updated_at` | timestamp | Laravel default |
| `deleted_at` | timestamp nullable | Soft delete |

### 8.2 Room Categories Table

| Column | Type | Notes |
| --- | --- | --- |
| `id` | unsigned big integer | Primary key |
| `name` | string | Unique among non-deleted records |
| `description` | text nullable | Optional description |
| `is_active` | boolean | Defaults to true |
| `created_by` | unsigned big integer nullable | User who created record |
| `updated_by` | unsigned big integer nullable | User who last updated record |
| `deleted_by` | unsigned big integer nullable | User who deleted record |
| `created_at` | timestamp | Laravel default |
| `updated_at` | timestamp | Laravel default |
| `deleted_at` | timestamp nullable | Soft delete |

### 8.3 Rooms Table

| Column | Type | Notes |
| --- | --- | --- |
| `id` | unsigned big integer | Primary key |
| `name` | string | Unique among non-deleted records |
| `room_category_id` | unsigned big integer | Foreign key to room categories |
| `price` | decimal | Must be greater than or equal to `0` |
| `status` | string | Backed by `RoomStatusEnum` |
| `created_by` | unsigned big integer nullable | User who created record |
| `updated_by` | unsigned big integer nullable | User who last updated record |
| `deleted_by` | unsigned big integer nullable | User who deleted record |
| `created_at` | timestamp | Laravel default |
| `updated_at` | timestamp | Laravel default |
| `deleted_at` | timestamp nullable | Soft delete |

### 8.4 Room Beds Table

| Column | Type | Notes |
| --- | --- | --- |
| `id` | unsigned big integer | Primary key |
| `room_id` | unsigned big integer | Foreign key to rooms |
| `bed_type` | enum | Backed by `RoomBedTypeEnum` |
| `qty` | unsigned integer | Must be greater than or equal to `1` |
| `created_at` | timestamp | Laravel default |
| `updated_at` | timestamp | Laravel default |

### 8.5 Unit Groups Table

| Column | Type | Notes |
| --- | --- | --- |
| `id` | unsigned big integer | Primary key |
| `name` | string | Unit group name |
| `slug` | string | Unique slug |
| `is_active` | boolean | Defaults to true |
| `created_at` | timestamp | Laravel default |
| `updated_at` | timestamp | Laravel default |

### 8.6 Units Table

| Column | Type | Notes |
| --- | --- | --- |
| `id` | unsigned big integer | Primary key |
| `unit_group_id` | unsigned big integer | Foreign key to unit groups |
| `name` | string | Unit name |
| `symbol` | string | Unit symbol |
| `is_base` | boolean | Defaults to false |
| `is_active` | boolean | Defaults to true |
| `created_at` | timestamp | Laravel default |
| `updated_at` | timestamp | Laravel default |

### 8.7 Item Categories Table

| Column | Type | Notes |
| --- | --- | --- |
| `id` | unsigned big integer | Primary key |
| `parent_id` | unsigned big integer nullable | Self-referencing parent category |
| `name` | string | Category name |
| `slug` | string | Unique slug |
| `code` | string | Unique code |
| `description` | text nullable | Optional description |
| `is_active` | boolean | Defaults to true |
| `created_at` | timestamp | Laravel default |
| `updated_at` | timestamp | Laravel default |

### 8.8 Items Table

| Column | Type | Notes |
| --- | --- | --- |
| `id` | unsigned big integer | Primary key |
| `item_category_id` | unsigned big integer | Foreign key to item categories |
| `name` | string | Item name |
| `code` | string | Unique item code |
| `sku` | string nullable | Unique when present |
| `barcode` | string nullable | Unique when present |
| `stock_unit_id` | unsigned big integer | Foreign key to units |
| `description` | text nullable | Optional description |
| `min_stock` | decimal(18, 6) nullable | Minimum stock quantity |
| `is_active` | boolean | Defaults to true |
| `created_at` | timestamp | Laravel default |
| `updated_at` | timestamp | Laravel default |

### 8.9 Item Unit Conversions Table

| Column | Type | Notes |
| --- | --- | --- |
| `id` | unsigned big integer | Primary key |
| `item_id` | unsigned big integer | Foreign key to items |
| `from_unit_id` | unsigned big integer | Source unit |
| `to_unit_id` | unsigned big integer | Destination unit |
| `conversion_factor` | decimal(18, 6) | Conversion multiplier |
| `is_active` | boolean | Defaults to true |
| `created_at` | timestamp | Laravel default |
| `updated_at` | timestamp | Laravel default |

Unique index:

- `item_id`, `from_unit_id`, `to_unit_id`

### 8.10 Warehouses Table

| Column | Type | Notes |
| --- | --- | --- |
| `id` | unsigned big integer | Primary key |
| `code` | string | Unique warehouse code |
| `name` | string | Warehouse name |
| `description` | text nullable | Optional description |
| `is_active` | boolean | Defaults to true |
| `created_at` | timestamp | Laravel default |
| `updated_at` | timestamp | Laravel default |

### 8.11 Stock Ins Table

| Column | Type | Notes |
| --- | --- | --- |
| `id` | unsigned big integer | Primary key |
| `reference_no` | string | Unique, generated by backend |
| `warehouse_id` | unsigned big integer | Receiving warehouse |
| `transaction_date` | dateTime | Transaction date and time |
| `status` | string | `draft`, `posted`, or `cancelled` |
| `remark` | text nullable | Optional remark |
| `created_by` | unsigned big integer nullable | User who created record |
| `created_at` | timestamp | Laravel default |
| `updated_at` | timestamp | Laravel default |

### 8.12 Stock In Items Table

| Column | Type | Notes |
| --- | --- | --- |
| `id` | unsigned big integer | Primary key |
| `stock_in_id` | unsigned big integer | Foreign key to stock ins |
| `item_id` | unsigned big integer | Foreign key to items |
| `unit_id` | unsigned big integer | Input unit |
| `quantity` | decimal(18, 6) | Input quantity |
| `base_quantity` | decimal(18, 6) | Calculated in item stock unit |
| `unit_cost` | decimal(18, 6) nullable | Optional cost |
| `total_cost` | decimal(18, 6) nullable | Optional total cost |
| `batch_no` | string nullable | Generated by backend |
| `expiry_date` | date nullable | Optional expiry date |
| `remark` | text nullable | Optional remark |
| `created_at` | timestamp | Laravel default |
| `updated_at` | timestamp | Laravel default |

### 8.13 Stock Outs Table

| Column | Type | Notes |
| --- | --- | --- |
| `id` | unsigned big integer | Primary key |
| `reference_no` | string | Unique, generated by backend |
| `warehouse_id` | unsigned big integer | Source warehouse |
| `transaction_date` | dateTime | Transaction date and time |
| `status` | string | `draft`, `posted`, or `cancelled` |
| `reason` | string nullable | Optional reason |
| `remark` | text nullable | Optional remark |
| `created_by` | unsigned big integer nullable | User who created record |
| `created_at` | timestamp | Laravel default |
| `updated_at` | timestamp | Laravel default |

### 8.14 Stock Out Items Table

| Column | Type | Notes |
| --- | --- | --- |
| `id` | unsigned big integer | Primary key |
| `stock_out_id` | unsigned big integer | Foreign key to stock outs |
| `item_id` | unsigned big integer | Foreign key to items |
| `unit_id` | unsigned big integer | Input unit |
| `quantity` | decimal(18, 6) | Input quantity |
| `base_quantity` | decimal(18, 6) | Calculated in item stock unit |
| `unit_cost` | decimal(18, 6) nullable | Optional cost |
| `total_cost` | decimal(18, 6) nullable | Optional total cost |
| `batch_no` | string nullable | Generated by backend |
| `remark` | text nullable | Optional remark |
| `created_at` | timestamp | Laravel default |
| `updated_at` | timestamp | Laravel default |

### 8.15 Inventory Transfers Table

| Column | Type | Notes |
| --- | --- | --- |
| `id` | unsigned big integer | Primary key |
| `reference_no` | string | Unique, generated by backend |
| `from_warehouse_id` | unsigned big integer | Source warehouse |
| `to_warehouse_id` | unsigned big integer | Destination warehouse |
| `transaction_date` | dateTime | Transaction date and time |
| `status` | string | `draft`, `posted`, or `cancelled` |
| `remark` | text nullable | Optional remark |
| `created_by` | unsigned big integer nullable | User who created record |
| `created_at` | timestamp | Laravel default |
| `updated_at` | timestamp | Laravel default |

### 8.16 Inventory Transfer Items Table

| Column | Type | Notes |
| --- | --- | --- |
| `id` | unsigned big integer | Primary key |
| `inventory_transfer_id` | unsigned big integer | Foreign key to inventory transfers |
| `item_id` | unsigned big integer | Foreign key to items |
| `unit_id` | unsigned big integer | Input unit |
| `quantity` | decimal(18, 6) | Input quantity |
| `base_quantity` | decimal(18, 6) | Calculated in item stock unit |
| `batch_no` | string nullable | Generated by backend |
| `expiry_date` | date nullable | Optional expiry date |
| `remark` | text nullable | Optional remark |
| `created_at` | timestamp | Laravel default |
| `updated_at` | timestamp | Laravel default |

### 8.17 Inventory Adjustments Table

| Column | Type | Notes |
| --- | --- | --- |
| `id` | unsigned big integer | Primary key |
| `reference_no` | string | Unique, generated by backend |
| `warehouse_id` | unsigned big integer | Warehouse being adjusted |
| `transaction_date` | dateTime | Transaction date and time |
| `status` | string | `draft`, `posted`, or `cancelled` |
| `reason` | string nullable | Optional reason |
| `remark` | text nullable | Optional remark |
| `created_by` | unsigned big integer nullable | User who created record |
| `created_at` | timestamp | Laravel default |
| `updated_at` | timestamp | Laravel default |

### 8.18 Inventory Adjustment Items Table

| Column | Type | Notes |
| --- | --- | --- |
| `id` | unsigned big integer | Primary key |
| `inventory_adjustment_id` | unsigned big integer | Foreign key to inventory adjustments |
| `item_id` | unsigned big integer | Foreign key to items |
| `unit_id` | unsigned big integer | Count unit |
| `system_quantity` | decimal(18, 6) | System stock before adjustment |
| `physical_quantity` | decimal(18, 6) | Actual physical count |
| `adjustment_type` | string | `increase` or `decrease`, calculated by backend |
| `adjustment_quantity` | decimal(18, 6) | Absolute difference, calculated by backend |
| `base_quantity` | decimal(18, 6) | Signed base quantity in item stock unit |
| `batch_no` | string nullable | Generated by backend |
| `expiry_date` | date nullable | Optional expiry date |
| `remark` | text nullable | Optional remark |
| `created_at` | timestamp | Laravel default |
| `updated_at` | timestamp | Laravel default |

### 8.19 Inventory Ledgers Table

| Column | Type | Notes |
| --- | --- | --- |
| `id` | unsigned big integer | Primary key |
| `item_id` | unsigned big integer | Foreign key to items |
| `warehouse_id` | unsigned big integer | Foreign key to warehouses |
| `transaction_type` | string | Movement type |
| `reference_type` | string nullable | Morph map alias |
| `reference_id` | unsigned big integer nullable | Source document id |
| `quantity` | decimal(18, 6) | Input movement quantity |
| `unit_id` | unsigned big integer | Input unit |
| `base_quantity` | decimal(18, 6) | Signed stock-unit movement |
| `unit_cost` | decimal(18, 6) nullable | Optional cost |
| `total_cost` | decimal(18, 6) nullable | Optional total cost |
| `batch_no` | string nullable | Batch number |
| `expiry_date` | date nullable | Optional expiry date |
| `balance_quantity` | decimal(18, 6) | Running balance after movement |
| `transaction_date` | dateTime | Transaction date and time |
| `remark` | text nullable | Optional remark |
| `created_by` | unsigned big integer nullable | User who posted movement |
| `created_at` | timestamp | Laravel default |
| `updated_at` | timestamp | Laravel default |

Ledger transaction types:

- `in`
- `out`
- `transfer_out`
- `transfer_in`
- `adjustment_increase`
- `adjustment_decrease`

### 8.20 Inventory Stock Balances Table

| Column | Type | Notes |
| --- | --- | --- |
| `id` | unsigned big integer | Primary key |
| `item_id` | unsigned big integer | Foreign key to items |
| `warehouse_id` | unsigned big integer | Foreign key to warehouses |
| `quantity` | decimal(18, 6) | On-hand stock quantity |
| `reserved_quantity` | decimal(18, 6) | Reserved stock quantity |
| `available_quantity` | decimal(18, 6) | Available stock quantity |
| `created_at` | timestamp | Laravel default |
| `updated_at` | timestamp | Laravel default |

Unique index:

- `item_id`, `warehouse_id`

### 8.21 Audits Table

| Column | Type | Notes |
| --- | --- | --- |
| `id` | unsigned big integer | Primary key |
| `user_type` | string nullable | User morph type |
| `user_id` | unsigned big integer nullable | Acting user id |
| `event` | string | Audited event |
| `auditable_type` | string | Morph map alias |
| `auditable_id` | unsigned big integer | Audited record id |
| `old_values` | text nullable | JSON previous values |
| `new_values` | text nullable | JSON new values |
| `url` | text nullable | Request URL |
| `ip_address` | string nullable | Request IP |
| `user_agent` | string nullable | Request user agent |
| `tags` | string nullable | Audit tags |
| `created_at` | timestamp | Event time |
| `updated_at` | timestamp | Laravel default |

### 8.22 Relationships

- User has many created room categories.
- User has many updated room categories.
- User has many deleted room categories.
- User has many created rooms.
- User has many updated rooms.
- User has many deleted rooms.
- Room category has many rooms.
- Room belongs to room category.
- Room has many room beds.
- Room bed belongs to room.
- Unit group has many units.
- Unit belongs to unit group.
- Unit has many stock items.
- Item category belongs to parent item category.
- Item category has many child item categories.
- Item category has many items.
- Item belongs to item category.
- Item belongs to stock unit.
- Item has many item unit conversions.
- Item has many inventory stock balances.
- Item unit conversion belongs to item.
- Item unit conversion belongs to from unit.
- Item unit conversion belongs to to unit.
- Warehouse has many stock documents and stock balances.
- Stock in belongs to warehouse and has many stock in items.
- Stock out belongs to warehouse and has many stock out items.
- Inventory transfer belongs to source and destination warehouses and has many transfer items.
- Inventory adjustment belongs to warehouse and has many adjustment items.
- Inventory ledger belongs to item, warehouse, unit, and creator.
- Inventory stock balance belongs to item and warehouse.
- Audit belongs to user.
- Audit morphs to auditable entity.

## 9. API Overview

Base prefix:

```text
/api/v1/admin
```

Authentication header:

```text
Authorization: Bearer {token}
Accept: application/json
```

### 9.1 Auth Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| `POST` | `/api/v1/admin/login` | Log in staff user and issue Sanctum token |
| `POST` | `/api/v1/admin/logout` | Revoke current token |
| `GET` | `/api/v1/admin/me` | Get authenticated user profile |

### 9.2 User Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/api/v1/admin/users` | List users |
| `POST` | `/api/v1/admin/users` | Create or update user |
| `GET` | `/api/v1/admin/users/{user}` | Show user |
| `DELETE` | `/api/v1/admin/users/{user}` | Soft delete user |
| `POST` | `/api/v1/admin/users/{user}/restore` | Restore user |

### 9.3 Room Category Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/api/v1/admin/room-categories` | List room categories |
| `POST` | `/api/v1/admin/room-categories` | Create or update room category |
| `GET` | `/api/v1/admin/room-categories/{room_category}` | Show room category |
| `DELETE` | `/api/v1/admin/room-categories/{room_category}` | Soft delete room category |
| `POST` | `/api/v1/admin/room-categories/{roomCategory}/toggle-active` | Update room category active state |
| `POST` | `/api/v1/admin/room-categories/{roomCategory}/restore` | Restore room category |

### 9.4 Room Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/api/v1/admin/rooms` | List rooms |
| `POST` | `/api/v1/admin/rooms` | Create or update room |
| `GET` | `/api/v1/admin/rooms/{room}` | Show room |
| `POST` | `/api/v1/admin/rooms/{room}/status` | Update room status |
| `DELETE` | `/api/v1/admin/rooms/{room}` | Soft delete room |
| `POST` | `/api/v1/admin/rooms/{room}/restore` | Restore room |

### 9.5 Unit Group Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/api/v1/admin/unit-groups` | List unit groups |
| `POST` | `/api/v1/admin/unit-groups` | Create or update unit group |
| `GET` | `/api/v1/admin/unit-groups/{unit_group}` | Show unit group |
| `DELETE` | `/api/v1/admin/unit-groups/{unit_group}` | Delete unit group |
| `POST` | `/api/v1/admin/unit-groups/{unitGroup}/toggle-active` | Update unit group active state |

### 9.6 Unit Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/api/v1/admin/units` | List units |
| `POST` | `/api/v1/admin/units` | Create or update unit |
| `GET` | `/api/v1/admin/units/{unit}` | Show unit |
| `DELETE` | `/api/v1/admin/units/{unit}` | Delete unit |
| `POST` | `/api/v1/admin/units/{unit}/toggle-active` | Update unit active state |

### 9.7 Item Category Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/api/v1/admin/item-categories` | List item categories |
| `POST` | `/api/v1/admin/item-categories` | Create or update item category |
| `GET` | `/api/v1/admin/item-categories/{item_category}` | Show item category |
| `DELETE` | `/api/v1/admin/item-categories/{item_category}` | Delete item category |
| `POST` | `/api/v1/admin/item-categories/{itemCategory}/toggle-active` | Update item category active state |

### 9.8 Item Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/api/v1/admin/items` | List items |
| `POST` | `/api/v1/admin/items` | Create or update item |
| `GET` | `/api/v1/admin/items/{item}` | Show item |
| `DELETE` | `/api/v1/admin/items/{item}` | Delete item |
| `POST` | `/api/v1/admin/items/{item}/toggle-active` | Update item active state |
| `GET` | `/api/v1/admin/warehouses/{warehouse}/items` | List items with balance for a warehouse |

### 9.9 Item Unit Conversion Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/api/v1/admin/item-unit-conversions` | List item unit conversions |
| `POST` | `/api/v1/admin/item-unit-conversions` | Create or update item unit conversion |
| `GET` | `/api/v1/admin/item-unit-conversions/{item_unit_conversion}` | Show item unit conversion |
| `DELETE` | `/api/v1/admin/item-unit-conversions/{item_unit_conversion}` | Delete item unit conversion |
| `POST` | `/api/v1/admin/item-unit-conversions/{itemUnitConversion}/toggle-active` | Update conversion active state |

### 9.10 Warehouse Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/api/v1/admin/warehouses` | List warehouses |
| `POST` | `/api/v1/admin/warehouses` | Create or update warehouse |
| `GET` | `/api/v1/admin/warehouses/{warehouse}` | Show warehouse |
| `DELETE` | `/api/v1/admin/warehouses/{warehouse}` | Delete warehouse |
| `POST` | `/api/v1/admin/warehouses/{warehouse}/toggle-active` | Update warehouse active state |

### 9.11 Stock In Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/api/v1/admin/stock-ins` | List stock in documents |
| `POST` | `/api/v1/admin/stock-ins` | Create or update draft stock in |
| `GET` | `/api/v1/admin/stock-ins/{stock_in}` | Show stock in |
| `DELETE` | `/api/v1/admin/stock-ins/{stock_in}` | Delete draft stock in |
| `POST` | `/api/v1/admin/stock-ins/{stockIn}/status` | Change draft stock in to posted or cancelled |

### 9.12 Stock Out Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/api/v1/admin/stock-outs` | List stock out documents |
| `POST` | `/api/v1/admin/stock-outs` | Create or update draft stock out |
| `GET` | `/api/v1/admin/stock-outs/{stock_out}` | Show stock out |
| `DELETE` | `/api/v1/admin/stock-outs/{stock_out}` | Delete draft stock out |
| `POST` | `/api/v1/admin/stock-outs/{stockOut}/status` | Change draft stock out to posted or cancelled |

### 9.13 Inventory Transfer Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/api/v1/admin/inventory-transfers` | List inventory transfers |
| `POST` | `/api/v1/admin/inventory-transfers` | Create or update draft transfer |
| `GET` | `/api/v1/admin/inventory-transfers/{inventory_transfer}` | Show transfer |
| `DELETE` | `/api/v1/admin/inventory-transfers/{inventory_transfer}` | Delete draft transfer |
| `POST` | `/api/v1/admin/inventory-transfers/{inventoryTransfer}/status` | Change draft transfer to posted or cancelled |

### 9.14 Inventory Adjustment Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/api/v1/admin/inventory-adjustments` | List inventory adjustments |
| `POST` | `/api/v1/admin/inventory-adjustments` | Create or update draft adjustment |
| `GET` | `/api/v1/admin/inventory-adjustments/{inventory_adjustment}` | Show adjustment |
| `DELETE` | `/api/v1/admin/inventory-adjustments/{inventory_adjustment}` | Delete draft adjustment |
| `POST` | `/api/v1/admin/inventory-adjustments/{inventoryAdjustment}/status` | Change draft adjustment to posted or cancelled |

### 9.15 Inventory Ledger Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/api/v1/admin/inventory-ledgers` | List inventory ledger movements |
| `GET` | `/api/v1/admin/inventory-ledgers/{inventory_ledger}` | Show inventory ledger movement |

Ledger filters:

- `item_id`
- `warehouse_id`
- `transaction_type`
- `reference_type`
- `reference_id`
- `batch_no`
- `date_from`
- `date_to`
- `page`
- `per_page`

### 9.16 Inventory Stock Balance Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/api/v1/admin/inventory-stock-balances` | List stock balances |
| `GET` | `/api/v1/admin/inventory-stock-balances/{inventory_stock_balance}` | Show stock balance |

Stock balance filters:

- `item_id`
- `warehouse_id`
- `page`
- `per_page`

### 9.17 Audit Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/api/v1/admin/audit-logs` | List audit logs |
| `GET` | `/api/v1/admin/audit-logs/{audit_log}` | Show audit log |

Audit filters:

- `event`
- `user_id`
- `auditable_type`
- `auditable_id`
- `date_from`
- `date_to`
- `page`
- `per_page`

## 10. UI Overview

The initial Laravel project is backend-only. The API is intended for an admin web dashboard built separately.

Expected dashboard screens:

- Login screen
- Current user profile screen
- User list screen
- User create/edit screen
- Room category list screen
- Room category create/edit screen
- Room list screen
- Room create/edit screen
- Room status update control
- Unit group list/create/edit screen
- Unit list/create/edit screen
- Item category tree/list/create/edit screen
- Item list/create/edit screen
- Item unit conversion management inside item form and direct conversion screen
- Warehouse list/create/edit screen
- Stock in list/create/edit/status screen
- Stock out list/create/edit/status screen
- Inventory transfer list/create/edit/status screen
- Inventory adjustment list/create/edit/status screen
- Inventory ledger screen
- Inventory stock balance screen
- Warehouse item balance screen
- Audit log screen
- Audit log detail screen

Expected dashboard behaviors:

- Use bearer-token authentication with Sanctum.
- Show server validation errors next to relevant fields.
- Use pagination for user, room category, room, inventory, item, conversion, transaction, ledger, balance, and audit log lists.
- Provide filters for active/inactive setup data, item category, stock unit, room category, room status, audit event, audit module, audit record id, and date range.
- For non-paginated dropdown lists, expect active records only.
- Confirm destructive actions before delete.
- Show restore action only to roles with restore permission.
- Display date/time values as `YYYY-MM-DD HH:mm:ss`.
- Do not ask users to enter backend-generated fields such as `reference_no`, `batch_no`, `base_quantity`, `adjustment_type`, or `adjustment_quantity` unless an approved override is intentionally added later.

## 11. Workflows

### 11.1 Staff Login

1. Staff user submits email and password.
2. API validates credentials.
3. API checks that the user is active.
4. API issues a Sanctum token.
5. API records `last_login_at`.
6. Updating `last_login_at` is captured by model auditing.
7. Dashboard stores the token securely and uses it for future requests.

### 11.2 Create Room Category

1. Authorized user submits category name, optional description, and active state.
2. API trims the name and normalizes repeated spaces.
3. API validates uniqueness among non-deleted room categories.
4. API creates the room category.
5. API records audit log.
6. API returns the created room category using the standard response format.

### 11.3 Create Room

1. Authorized user submits room name, category, price, status, and room beds.
2. API trims the name and normalizes repeated spaces.
3. API validates room name uniqueness.
4. API validates that the category exists and is active.
5. API validates that price is greater than or equal to `0`.
6. API validates status through `RoomStatusEnum`.
7. API validates each room bed's `bed_type` through `RoomBedTypeEnum`.
8. API validates each room bed's `qty` is greater than or equal to `1`.
9. API creates the room and its room bed records.
10. API records audit log.
11. API returns the created room using the standard response format.

### 11.4 Update Room Status

1. Authorized user selects a new room status.
2. API validates the status against `RoomStatusEnum`.
3. API updates the room status.
4. API records old status and new status.
5. API writes audit log.
6. API returns the updated room.

### 11.5 Create Item with Unit Conversions

1. Authorized user submits item data and optional `item_unit_conversions`.
2. API validates category, stock unit, unique code, optional unique SKU, optional unique barcode, and minimum stock.
3. API creates or updates the item in a database transaction.
4. If conversions are provided, API deletes missing existing conversions and updates or creates submitted conversions.
5. API records audit logs for changed models.
6. API returns the item with category, stock unit, and conversions.

### 11.6 Post Stock In

1. Authorized user creates a stock in document through `POST /api/v1/admin/stock-ins`.
2. API creates the document as `draft`.
3. API generates `reference_no` when missing.
4. API calculates each line's `base_quantity`.
5. API generates each new line's `batch_no`.
6. Authorized user posts the document through `POST /api/v1/admin/stock-ins/{stockIn}/status` with `status = posted`.
7. API adds stock balance per item and warehouse.
8. API writes `in` ledger rows.
9. API changes document status to `posted`.

### 11.7 Post Stock Out

1. Authorized user creates a stock out document through `POST /api/v1/admin/stock-outs`.
2. API creates the document as `draft`.
3. API generates `reference_no`, line `batch_no`, and line `base_quantity`.
4. Authorized user posts the document through the status endpoint.
5. API validates sufficient stock.
6. API subtracts stock balance per item and warehouse.
7. API writes `out` ledger rows.
8. API changes document status to `posted`.

### 11.8 Post Inventory Transfer

1. Authorized user creates an inventory transfer document.
2. API validates source and destination warehouses are different.
3. API creates the document as `draft`.
4. API generates `reference_no`, line `batch_no`, and line `base_quantity`.
5. Authorized user posts the transfer through the status endpoint.
6. API validates sufficient stock in the source warehouse.
7. API subtracts stock from the source warehouse and writes `transfer_out` ledger rows.
8. API adds stock to the destination warehouse and writes `transfer_in` ledger rows.
9. API changes transfer status to `posted`.

### 11.9 Post Inventory Adjustment

1. Authorized user creates an inventory adjustment document.
2. API creates the document as `draft`.
3. API generates `reference_no` and line `batch_no`.
4. API calculates `adjustment_type`, `adjustment_quantity`, and signed `base_quantity` from `system_quantity` and `physical_quantity`.
5. Authorized user posts the adjustment through the status endpoint.
6. API adds stock for increases or subtracts stock for decreases.
7. API writes `adjustment_increase` or `adjustment_decrease` ledger rows.
8. API changes adjustment status to `posted`.

### 11.10 Cancel Inventory Document

1. Authorized user creates an inventory document as `draft`.
2. Authorized user sends a status update with `status = cancelled`.
3. API validates the document is still draft.
4. API changes status to `cancelled`.
5. API does not write inventory ledger rows or change stock balances.

### 11.11 Soft Delete and Restore

1. Authorized user requests delete.
2. API verifies permission.
3. API stores `deleted_by`.
4. API soft deletes the record.
5. API records audit log.
6. Authorized user may later request restore.
7. API restores the record and records the restore event.

## 12. Permissions

Permissions should be role-based for the initial release. Granular permissions can be added later if required.

| Capability | Hotel Admin | Front Office Admin | Reservation Admin | Restaurant Admin | Customer Service Admin | Inventory Admin |
| --- | --- | --- | --- | --- | --- | --- |
| Login | Yes | Yes | Yes | Yes | Yes | Yes |
| Manage users | Yes | No | No | No | No | No |
| View users | Yes | No | No | No | No | No |
| Manage room categories | Yes | No | No | No | No | No |
| View room categories | Yes | Yes | Yes | Yes | Yes | Yes |
| Manage rooms | Yes | Yes | Limited | No | No | No |
| View rooms | Yes | Yes | Yes | Yes | Yes | Yes |
| Update room status | Yes | Yes | Limited | No | No | No |
| Manage inventory setup | Yes | No | No | No | No | Limited |
| View inventory setup | Yes | No | No | Yes | No | Yes |
| Manage items | Yes | No | No | No | No | Yes |
| View items | Yes | No | No | Yes | No | Yes |
| Manage warehouses | Yes | No | No | No | No | Yes |
| View warehouses | Yes | No | No | Yes | No | Yes |
| Manage stock documents | Yes | No | No | No | No | Yes |
| View inventory ledgers | Yes | No | No | No | No | Yes |
| View stock balances | Yes | No | No | No | No | Yes |
| View audit logs | Yes | No | No | No | No | No |
| View activity history through audit logs | Yes | No | No | No | No | No |
| Restore deleted records | Yes | No | No | No | No | No |

Limited permissions:

- Reservation Administrator may update room status only when related to reservation operations, such as `available` to `reserved` or `reserved` to `available`.
- Current implemented admin routes are protected by Hotel Administrator role middleware. Inventory Administrator access may be opened later with route-level permission changes.

## 13. Architecture

### 13.1 Application Structure

Recommended Laravel structure:

```text
app/
  Enums/
    AdjustmentStatusEnum.php
    AdjustmentTypeEnum.php
    RoomBedTypeEnum.php
    RoomStatusEnum.php
    StockInStatusEnum.php
    StockOutStatusEnum.php
    TransferStatusEnum.php
    UserRoleEnum.php
  Http/
    Controllers/
      Api/
        V1/
          Admin/
            AuditLogController.php
            AuthController.php
            InventoryAdjustmentController.php
            InventoryLedgerController.php
            InventoryStockBalanceController.php
            InventoryTransferController.php
            ItemCategoryController.php
            ItemController.php
            ItemUnitConversionController.php
            RoomCategoryController.php
            RoomController.php
            StockInController.php
            StockOutController.php
            UnitController.php
            UnitGroupController.php
            UserController.php
            WarehouseController.php
    Requests/
      Auth/
      InventoryAdjustments/
      InventoryTransfers/
      ItemCategories/
      ItemUnitConversions/
      Items/
      RoomCategories/
      Rooms/
      StockIns/
      StockOuts/
      UnitGroups/
      Units/
      Users/
      Warehouses/
    Resources/
      AuditLogs/
        AuditLogResource.php
      Concerns/
        FormatsDateTime.php
      InventoryAdjustments/
        InventoryAdjustmentResource.php
        InventoryAdjustmentItemResource.php
      InventoryLedgers/
        InventoryLedgerResource.php
      InventoryStockBalances/
        InventoryStockBalanceResource.php
      InventoryTransfers/
        InventoryTransferResource.php
        InventoryTransferItemResource.php
      ItemCategories/
        ItemCategoryResource.php
      ItemUnitConversions/
        ItemUnitConversionResource.php
      Items/
        ItemResource.php
        ItemWarehouseBalanceResource.php
      RoomCategories/
        RoomCategoryResource.php
      Rooms/
        RoomResource.php
        RoomBedResource.php
      StockIns/
        StockInResource.php
        StockInItemResource.php
      StockOuts/
        StockOutResource.php
        StockOutItemResource.php
      UnitGroups/
        UnitGroupResource.php
      Units/
        UnitResource.php
      Users/
        UserResource.php
      Warehouses/
        WarehouseResource.php
  Models/
    InventoryAdjustment.php
    InventoryAdjustmentItem.php
    InventoryLedger.php
    InventoryStockBalance.php
    InventoryTransfer.php
    InventoryTransferItem.php
    Item.php
    ItemCategory.php
    ItemUnitConversion.php
    RoomCategory.php
    Room.php
    RoomBed.php
    StockIn.php
    StockInItem.php
    StockOut.php
    StockOutItem.php
    Unit.php
    UnitGroup.php
    User.php
    Warehouse.php
  Policies/
    UserPolicy.php
    RoomCategoryPolicy.php
    RoomPolicy.php
    AuditLogPolicy.php
  Services/
    AuditLogs/
      AuditLogService.php
    Auth/
      AuthService.php
    Inventory/
      InventoryDocumentService.php
    InventoryAdjustments/
      InventoryAdjustmentService.php
    InventoryLedgers/
      InventoryLedgerService.php
    InventoryStockBalances/
      InventoryStockBalanceService.php
    InventoryTransfers/
      InventoryTransferService.php
    ItemCategories/
      ItemCategoryService.php
    ItemUnitConversions/
      ItemUnitConversionService.php
    Items/
      ItemService.php
    RoomCategories/
      RoomCategoryService.php
    Rooms/
      RoomService.php
    StockIns/
      StockInService.php
    StockOuts/
      StockOutService.php
    UnitGroups/
      UnitGroupService.php
    Units/
      UnitService.php
    Users/
      UserService.php
    Warehouses/
      WarehouseService.php
```

### 13.2 API Versioning

- All first-release admin API routes should live under `/api/v1/admin`.
- Admin controllers should be namespaced under `App\Http\Controllers\Api\V1\Admin`.
- Breaking changes should be introduced in a future version, for example `/api/v2`.

### 13.3 Authentication and Authorization

- Sanctum protects all internal API routes except login.
- Role checks may be implemented with custom middleware, policies, or a package such as Spatie Laravel Permission.
- Policies should guard model-level actions.

### 13.4 Validation and Normalization

- Use Form Requests for validation.
- Normalize room category names and room names before validation.
- Normalize unit group names, unit names/symbols, item category names/codes, and item names where appropriate.
- Use unique rules scoped to ignore soft-deleted records.
- Use enum validation for room bed type and room status.
- Validate room bed quantity as an integer greater than or equal to `1`.
- Validate item unit conversions so `from_unit_id` and `to_unit_id` are different.
- Validate inventory document statuses through their enums.
- Validate inventory adjustment status through `AdjustmentStatusEnum`.
- Validate calculated adjustment types through `AdjustmentTypeEnum`.
- Validate stock movement quantities as positive values.
- Validate stock decreases against available stock during posting.

### 13.5 API Response Layer

- Use a shared API response helper or response macro.
- Use Laravel API Resources for entity serialization.
- Serialize date/time fields as `Y-m-d H:i:s` in `Asia/Yangon`.
- Handle exceptions consistently through Laravel exception rendering.

### 13.6 Database

- Use MySQL in production, local development, and tests.
- Add indexes for:
  - `users.email`
  - `users.role`
  - `room_categories.name`
  - `room_categories.is_active`
  - `rooms.name`
  - `rooms.room_category_id`
  - `rooms.status`
  - `room_beds.room_id`
  - `room_beds.bed_type`
  - unique composite index on `room_beds.room_id` and `room_beds.bed_type`
  - `unit_groups.slug`
  - `unit_groups.is_active`
  - `units.unit_group_id`
  - `units.is_base`
  - `units.is_active`
  - unique composite indexes on `units.unit_group_id` with `name` and `symbol`
  - `item_categories.parent_id`
  - `item_categories.slug`
  - `item_categories.code`
  - `item_categories.is_active`
  - `items.item_category_id`
  - `items.stock_unit_id`
  - `items.code`
  - `items.sku`
  - `items.barcode`
  - `items.is_active`
  - `item_unit_conversions.item_id`
  - `item_unit_conversions.from_unit_id`
  - `item_unit_conversions.to_unit_id`
  - `item_unit_conversions.is_active`
  - unique composite index on `item_unit_conversions.item_id`, `from_unit_id`, and `to_unit_id`
  - `warehouses.code`
  - `warehouses.is_active`
  - `stock_ins.reference_no`
  - `stock_ins.warehouse_id`
  - `stock_ins.status`
  - `stock_outs.reference_no`
  - `stock_outs.warehouse_id`
  - `stock_outs.status`
  - `inventory_transfers.reference_no`
  - `inventory_transfers.from_warehouse_id`
  - `inventory_transfers.to_warehouse_id`
  - `inventory_transfers.status`
  - `inventory_adjustments.reference_no`
  - `inventory_adjustments.warehouse_id`
  - `inventory_adjustments.status`
  - `inventory_ledgers.item_id`, `inventory_ledgers.warehouse_id`
  - `inventory_ledgers.reference_type`, `inventory_ledgers.reference_id`
  - `inventory_ledgers.transaction_date`
  - `inventory_ledgers.batch_no`
  - unique composite index on `inventory_stock_balances.item_id` and `warehouse_id`
  - `audits.user_id`, `audits.user_type`
  - `audits.auditable_type`, `audits.auditable_id`
  - `audits.created_at`

### 13.7 Polymorphic Morph Map

Use `Relation::enforceMorphMap()` so polymorphic database values are stable aliases instead of PHP class names.

Required aliases:

- `user`
- `room_category`
- `room`
- `room_bed`
- `unit_group`
- `unit`
- `item_category`
- `item`
- `item_unit_conversion`
- `warehouse`
- `stock_in`
- `stock_in_item`
- `stock_out`
- `stock_out_item`
- `inventory_transfer`
- `inventory_transfer_item`
- `inventory_adjustment`
- `inventory_adjustment_item`

## 14. Implementation Milestones

### Milestone 1: Project Foundation

- Configure MySQL environment variables.
- Add API route file and `/api/v1` route grouping.
- Install and configure Laravel Sanctum.
- Create shared API response helper.
- Add base exception handling for consistent JSON responses.

### Milestone 2: Authentication and Roles

- Create staff login endpoint.
- Create logout endpoint.
- Create authenticated `me` endpoint.
- Add role enum or role constants.
- Add role authorization middleware or policies.
- Seed initial Hotel Administrator account.

### Milestone 3: User Management

- Update user migration/model for role, active state, and audit fields.
- Add user requests, resources, controller, and policy.
- Implement user CRUD.
- Implement user deactivate, soft delete, and restore.
- Add user audit logging.

### Milestone 4: Room Categories

- Create room categories migration and model.
- Add room category requests, resource, controller, and policy.
- Implement trim and whitespace normalization.
- Implement CRUD, soft delete, and restore.
- Add active toggle endpoint.
- Add audit logging.

### Milestone 5: Rooms

- Create `RoomBedTypeEnum` and `RoomStatusEnum`.
- Create rooms migration and model.
- Create room beds migration and model.
- Add room requests, resource, controller, and policy.
- Implement room CRUD.
- Implement room status update endpoint.
- Add audit logging for room changes.

### Milestone 6: Inventory Setup

- Create unit groups migration and model.
- Create units migration and model.
- Create item categories migration and model.
- Seed initial unit groups, units, and item categories.
- Add requests, resources, controllers, and services.
- Implement CRUD and active toggle endpoints.
- Add list filters and active-only non-paginated lists.
- Add audit logging.

### Milestone 7: Items and Unit Conversions

- Create items migration and model.
- Create item unit conversions migration and model.
- Add requests, resources, controllers, and services.
- Implement item CRUD and active toggle endpoint.
- Implement nested item unit conversion syncing inside item save.
- Implement direct item unit conversion CRUD and active toggle endpoint.
- Add audit logging.

### Milestone 8: Audit Logs

- Install and configure `owen-it/laravel-auditing`.
- Create package-standard `audits` migration.
- Enforce morph map aliases for auditable types.
- Implement `AuditLogService`.
- Add audit log list and detail endpoints.
- Add filters by user, entity type, entity id, event, and date range.

### Milestone 9: Inventory Transactions

- Create warehouse migration, model, request, resource, controller, and service.
- Create stock in and stock out migrations, models, requests, resources, controllers, and services.
- Create inventory transfer migrations, models, requests, resources, controller, and service.
- Create inventory adjustment migrations, models, requests, resources, controller, and service.
- Create inventory ledger and stock balance migrations, models, resources, controllers, and services.
- Implement backend-generated `reference_no` and `batch_no`.
- Implement backend-calculated `base_quantity`, adjustment type, and adjustment quantity.
- Implement draft, posted, and cancelled status workflows.
- Implement posting actions that update stock balances and write inventory ledgers.
- Add warehouse-specific item balance list.

### Milestone 10: Testing and Quality

- Add feature tests for authentication.
- Add feature tests for user permissions.
- Add feature tests for room category CRUD.
- Add feature tests for room CRUD and status changes.
- Add feature tests for inventory setup CRUD and toggles.
- Add feature tests for item CRUD, nested conversions, and conversion toggles.
- Add feature tests for warehouse CRUD and active toggles.
- Add feature tests for stock in/out posting and cancellation.
- Add feature tests for inventory transfer posting and cancellation.
- Add feature tests for inventory adjustment posting and cancellation.
- Add tests for inventory ledger and stock balance responses.
- Add tests for backend-generated reference numbers, batch numbers, and base quantities.
- Add tests for consistent API responses.
- Add tests for soft delete and restore behavior.
- Add tests for audit log creation.
- Add tests for date/time response format.

### Milestone 11: Documentation and Handoff

- Document environment setup.
- Document API authentication flow.
- Document API response format.
- Document date/time response format.
- Document inventory setup APIs.
- Document item and conversion APIs.
- Document warehouse and inventory transaction APIs.
- Document inventory posting behavior and ledger transaction types.
- Document backend-generated fields.
- Document audit log filters and morph aliases.
- Document role permissions.
- Provide seed data instructions.
- Prepare dashboard integration notes.

## 15. Open Questions

- Should role-based permissions later become granular permissions such as `room.create`, `room.update`, and `room.delete`?
- Should Room Category deletion be blocked whenever any room exists, or only when active/non-deleted rooms exist?
- Should room `name` represent a room number, a label, or both?
- Should room status changes require a reason/note for maintenance, cleaning, or inactive states?
- Should audit logs be immutable forever, or should retention rules be added later?
- Should the API expose soft-deleted records through `with_trashed` filters for Hotel Administrators?
- Should item categories allow duplicate child names under different parent categories, or should slug stay globally unique?
- Should units enforce only one base unit per unit group?
- Should item unit conversions require `from_unit_id` and `to_unit_id` to belong to the same unit group?
- Should Inventory Administrator get access to inventory transaction APIs now, or remain behind Hotel Administrator middleware until granular permissions are added?
- Should inventory stock out and transfer support FIFO/FEFO batch selection rules later?
- Should cancelled posted stock documents be reversible through a formal reversal document instead of status changes?
