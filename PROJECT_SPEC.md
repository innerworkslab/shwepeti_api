# Shwe Peti Backend API Project Specification

## 1. Project Overview

Shwe Peti is a Laravel backend API application for managing hotel administration and front-office POS operations. The system exposes separate Admin Portal and POS Portal REST APIs secured with one Laravel Sanctum user system.

The current release focuses on internal staff accounts, portal-based access control, room setup, booking and check-in/check-out flows, booking payments, food ordering, amenity and laundry service orders, inventory setup, item master data, unit conversions, inventory stock movements, cashbook management, fixed asset management, audit logs, soft deletes where needed, and consistent API responses.

## 2. Goals

- Provide secure Laravel backend APIs for the internal Admin Portal and POS Portal.
- Manage internal staff users with role-based access and portal access control.
- Manage room categories with activation control.
- Manage rooms and their operational statuses.
- Manage front-office room availability, reservations, walk-in check-ins, check-outs, and cancellations.
- Support day-based and session-based booking charges.
- Support optional reservation deposits, partial payments, and final checkout payments.
- Support POS food orders from menu records.
- Support POS amenities and laundry service orders using item categories.
- Manage unit groups, units, item categories, items, and item unit conversions.
- Manage warehouses and inventory stock transactions.
- Manage menu categories, menus, menu availability, and menu recipes for food ordering.
- Maintain inventory ledgers and stock balances from posted stock documents.
- Manage hierarchical asset categories and fixed asset purchases.
- Record each posted fixed asset purchase as exactly one cashbook expense transaction.
- Keep API responses consistent across success, validation, authentication, authorization, and error states.
- Preserve important operational history with audit logs.
- Use API versioning so future dashboard, mobile, or third-party clients can evolve safely.

## 3. Scope

### In Scope

- Internal staff authentication using Laravel Sanctum.
- One user table and one authentication system for both Admin and POS portal access.
- Portal access values using `UserPortalAccessEnum`: `admin`, `pos`, and `both`.
- API versioning, starting with `v1`.
- User management for internal staff.
- Role assignment for supported hotel administrator roles.
- Separate Admin Portal and POS Portal route files.
- Room category CRUD.
- Room CRUD.
- Room bed setup using `RoomBed` records and `RoomBedTypeEnum`.
- Room status management using `RoomStatusEnum`.
- POS room list, available-room list, and room detail APIs.
- Booking dashboard calendar data by date range, room category, room, availability, and booking bars.
- Booking creation for reservations and walk-ins.
- Booking status workflows for reserved, checked-in, checked-out, and cancelled bookings.
- Booking type values using `BookingTypeEnum`: `reservation` and `walk_in`.
- Booking charge type values using `BookingChargeTypeEnum`: `day` and `session`.
- Optional reservation deposit capture.
- Partial booking payments before checkout.
- Checkout with final payment and room status update.
- Food menu list and food order workflows.
- Amenity and laundry item list and service order workflows.
- One `service_orders` table for amenities and laundry.
- Unit group CRUD and active toggle.
- Unit CRUD and active toggle.
- Item category CRUD and active toggle.
- Item CRUD, nested item unit conversion sync, and active toggle.
- Item unit conversion CRUD and active toggle.
- Menu category CRUD and active toggle.
- Menu CRUD, availability toggle, active toggle, and recipe sync.
- Warehouse CRUD and active toggle.
- Stock in, stock out, inventory transfer, and inventory adjustment documents.
- Draft, posted, and cancelled status workflows for inventory documents.
- Inventory ledger retrieval and stock balance retrieval.
- Warehouse-specific item list with stock balance quantity and stock unit.
- Cashbook and cashbook transaction management.
- Hierarchical asset category CRUD and active toggle.
- Fixed asset draft, posted, and cancelled workflows.
- Fixed asset document metadata stored as JSON.
- Idempotent asset category seed data for hotel operations.
- Soft delete and restore support where appropriate.
- Audit logging for model create, update, delete, and restore events using `owen-it/laravel-auditing`.
- MySQL database support for local development, testing, and production.
- Backend-only Laravel project with no frontend build tooling.

### Out of Scope for Initial Release

- Public guest/customer registration.
- Public booking website.
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
- Backend Type: API-only backend for Admin Portal and POS Portal clients

## 5. User Roles

The system is currently for internal staff only. Staff users are stored in one `users` table and may be allowed into the Admin Portal, the POS Portal, or both.

Portal access values:

- `admin` - may authenticate to Admin Portal endpoints.
- `pos` - may authenticate to POS Portal endpoints.
- `both` - may authenticate to both Admin and POS Portal endpoints.

The following roles are required:

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
- Restaurant Administrator: Manage POS food order workflows where permitted.
- Customer Service Administrator: View room and category information to support guest service workflows.
- Inventory Administrator: Manage inventory setup, item master data, stock documents, stock balances, and ledgers where permitted.

## 6. Functional Requirements

### 6.1 Authentication

- Staff users must log in using credentials.
- Authentication must use Laravel Sanctum token-based authentication.
- API clients must send authenticated requests with a bearer token.
- Admin Portal login uses `POST /api/v1/admin/login` and protected Admin Portal routes require `portal:admin`.
- POS Portal login uses `POST /api/v1/pos/login` and protected POS Portal routes require `portal:pos`.
- Users with `portal_access = both` may use both portals with the same account.
- The system must support logout by revoking the current access token.
- The system should support a `me` endpoint to return the authenticated staff profile.
- Guest/customer authentication is excluded from the initial release.

### 6.2 User Management

- Hotel Administrators can create internal staff users.
- Hotel Administrators can view a paginated list of users.
- Hotel Administrators can view user details.
- Hotel Administrators can update user profile fields and role assignment.
- Hotel Administrators can assign `portal_access` as `admin`, `pos`, or `both`.
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
- `portal_access`
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
- Services: Amenities, Laundry

Seeded POS/service items:

- Amenities: Extra Towel, Extra Blanket, Guest Amenity Kit
- Laundry: Laundry Shirt, Laundry Pants, Laundry Dress

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

### 6.7 Menu Management

Menus define the food items that can be ordered from POS food order workflows.

Menu category fields:

- `id`
- `name`
- `slug`
- `code`
- `description`
- `sort_order`
- `is_active`
- `created_at`
- `updated_at`

Menu fields:

- `id`
- `menu_category_id`
- `name`
- `code`
- `description`
- `price`
- `cost_price`
- `image_url`
- `is_available`
- `is_active`
- `created_at`
- `updated_at`

Menu recipe fields:

- `id`
- `menu_id`
- `item_id`
- `unit_id`
- `quantity`
- `base_quantity`
- `remark`

Functional behavior:

- Menu categories can be listed, searched, created, updated, shown, deleted, and toggled active/inactive.
- Menus can be listed, searched, filtered by category, filtered by active state, filtered by availability, created, updated, shown, deleted, toggled active/inactive, and toggled available/unavailable.
- Menus can accept nested `recipes` in the save payload.
- Menu recipes connect menus to inventory items and units for future stock usage logic.
- POS menu list returns active and available menus only.
- Create and update use one `POST` endpoint with optional `id`.

### 6.8 Inventory Transactions

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

### 6.9 Audit Logs

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

### 6.10 Date and Time Response Format

All resource date-time fields must be serialized in the application timezone, `Asia/Yangon`, using this format:

```text
YYYY-MM-DD HH:mm:ss
```

Example:

```text
2026-09-15 14:47:41
```

The database date-time values may remain in the configured database/application timezone, but API resources must not return UTC `Z` ISO strings.

### 6.11 Activity History

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
- Asset categories
- Fixed assets

Activity history must include:

- Actor
- Action
- Changed fields
- Previous values where applicable
- New values where applicable
- Timestamp

### 6.12 Asset and Cashbook Management

Asset categories organize fixed assets and may be nested through an optional `parent_id`.

Asset category fields:

- `parent_id`, nullable
- `name`
- `slug`
- `code`
- `description`, nullable
- `is_active`

Asset category rules:

- `name` is trimmed and repeated whitespace is normalized.
- `slug` is generated from `name` when omitted and must be unique.
- `code` is normalized to uppercase snake case and must be unique.
- A category cannot be its own parent.
- A category with child categories or assigned fixed assets cannot be deleted.
- Non-paginated lists return active categories only.
- `GET /api/v1/admin/asset-categories?parent_id=` returns active top-level categories for parent dropdowns.
- `GET /api/v1/admin/asset-categories?parent_id={id}` returns active children of that category.

Fixed asset fields:

- `reference_no`, generated when omitted
- `asset_code`, unique
- `asset_category_id`
- `cashbook_id`
- `name`
- `serial_number`, nullable
- `location`, nullable
- `purchase_date`
- `purchase_amount`
- `warranty_expiry_date`, nullable
- `documents`, nullable JSON array
- `status`
- `remark`, nullable
- `created_by`, nullable

Fixed asset statuses:

- `draft`
- `posted`
- `cancelled`

Fixed asset rules:

- New fixed assets default to `draft`.
- `asset_code` is normalized to uppercase with spaces converted to hyphens.
- `purchase_amount` must be greater than `0`.
- `warranty_expiry_date`, when provided, must not be earlier than `purchase_date`.
- `documents` accepts a JSON array of document metadata, such as `name` and `path` values.
- Only draft fixed assets may be updated, deleted, posted, or cancelled.
- Cancelling a draft fixed asset does not affect its cashbook.
- Posting a draft fixed asset creates exactly one `expense` cashbook transaction for `purchase_amount`.
- The cashbook transaction uses the fixed asset as its polymorphic reference.
- Fixed asset posting, cashbook balance update, cashbook transaction creation, and status update occur atomically in one database transaction.
- The fixed asset row and cashbook row are locked during posting to prevent duplicate posting and balance races.
- Posting fails when the cashbook is inactive or has insufficient balance; the fixed asset remains draft and no cashbook transaction is created.
- A posted fixed asset cannot be posted again, updated, or deleted.

Asset category seed data:

- `AssetCategorySeeder` is idempotent and creates 18 active categories.
- Parent groups are Property, Furniture & Fixtures, Equipment, and Vehicles.

### 6.13 POS Booking, Payment, Food, and Service Operations

POS Portal operations are used by front-office staff after authentication through `/api/v1/pos/login`.

Booking fields:

- `booking_no`, generated when omitted
- `room_id`
- `booking_type`: `reservation` or `walk_in`
- `charge_type`: `day` or `session`
- guest name, phone, email, NRC, and address fields
- `expected_check_in_at`
- `expected_check_out_at`
- `checked_in_at`, nullable
- `checked_out_at`, nullable
- `status`: `reserved`, `checked_in`, `checked_out`, or `cancelled`
- `guest_count`
- `room_rate`
- `session_hours`, required when `charge_type = session`
- `session_rate`, required when `charge_type = session`
- `subtotal`, `discount_amount`, `tax_amount`, `total_amount`, `paid_amount`, and `balance_amount`
- `note`

Booking rules:

- Reservations create a `reserved` booking and set the room status to `reserved`.
- Walk-in bookings create a `checked_in` booking immediately, set `checked_in_at` to the current time, record the check-in user, and set the room status to `occupied`.
- `check_in_now = true` may be sent for walk-in clients, but walk-in bookings are checked in by default.
- Day bookings calculate from the room rate and expected stay dates.
- Session bookings require `session_hours`, `session_rate`, and `expected_check_out_at`.
- `session_rate` is the total session/package price for the session booking.
- Reserved bookings may be checked in through the check-in endpoint, which sets the room to `occupied`.
- Checked-in bookings may be checked out through the checkout endpoint.
- Checkout with payment requires the submitted amount to cover the current effective balance.
- Checked-out bookings set the room status to `dirty`.
- Bookings may be cancelled only while business rules allow cancellation; cancellation releases the room back to `available`.

Payment rules:

- Booking payments are stored in `booking_payments`.
- Payment types are `deposit`, `partial`, `checkout`, and `refund`.
- Payment methods are `cash`, `kpay`, `wavepay`, `bank_transfer`, and `card`.
- `cashbook_id` is required for recorded booking payments.
- Reservation deposits are optional and may be included in the booking creation payload as `deposit`.
- Deposits are allowed only for `booking_type = reservation`.
- Deposit amount cannot exceed the booking total.
- Partial payments are allowed only for checked-in bookings and cannot exceed the current effective balance.
- Checkout payment is allowed only for checked-in bookings and must cover the current effective balance.
- Booking payable amount includes room or session charges plus non-cancelled food orders and non-cancelled service orders.

Food order rules:

- Food orders use menu records and are tied to a checked-in booking.
- Food order creation requires `booking_id`; the API derives the room from the booking.
- Food orders are allowed only when the booking is `checked_in` and the room is `occupied`.
- Food order statuses are `pending`, `preparing`, `served`, and `cancelled`.
- Allowed food status transitions are `pending -> preparing`, `pending -> cancelled`, and `preparing -> served`.
- Food orders cannot be cancelled after they are preparing or served.

Service order rules:

- Amenities and laundry use one `service_orders` table with type `amenity` or `laundry`.
- Service order creation requires `booking_id`; the API derives the room from the booking.
- Service orders are allowed only when the booking is `checked_in` and the room is `occupied`.
- Amenity service order items must belong to the `AMENITIES` item category.
- Laundry service order items must belong to the `LAUNDRY` item category.
- Service order statuses are `pending`, `processing`, `completed`, and `cancelled`.
- Allowed service status transitions are `pending -> processing`, `pending -> cancelled`, and `processing -> completed`.
- Service orders cannot be cancelled after processing or completion.

Booking detail responses:

- `GET /api/v1/pos/bookings/{booking}` includes room, payments, food orders, and service orders.
- Calculated response totals include `room_total_amount`, `food_order_amount`, `service_order_amount`, `order_amount`, `grand_total_amount`, and `balance_amount`.

Booking dashboard rules:

- The dashboard endpoint accepts `start_date` and `end_date`.
- Optional filters include `room_category_id` and `status`.
- The response groups rooms by category, returns date columns, availability counts, and booking bars suitable for a calendar-style room dashboard.
- Booking overlap logic should include bookings whose expected stay intersects the requested date range.

Core seeders:

- `UserSeeder` creates admin, POS, and both-portal users for local testing.
- `RoomCategorySeeder` creates Standard, Deluxe, and Suite room categories.
- `RoomSeeder` creates sample rooms 101, 102, 201, 202, and 301.
- `ItemCategorySeeder` includes Amenities and Laundry service categories.
- `ItemSeeder` includes sample amenity and laundry service items.

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
| `portal_access` | string | `admin`, `pos`, or `both` |
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

### 8.22 Cashbooks Table

| Column | Type | Notes |
| --- | --- | --- |
| `id` | unsigned big integer | Primary key |
| `code` | string | Unique cashbook code |
| `name` | string | Display name |
| `type` | string | Cash or bank enum value |
| `opening_balance` | decimal(18, 2) | Initial balance |
| `current_balance` | decimal(18, 2) | Current balance after posted transactions |
| `description` | text nullable | Optional description |
| `is_active` | boolean | Active state |
| `created_at` | timestamp | Laravel default |
| `updated_at` | timestamp | Laravel default |

### 8.23 Cashbook Transactions Table

| Column | Type | Notes |
| --- | --- | --- |
| `id` | unsigned big integer | Primary key |
| `reference_no` | string | Unique generated transaction reference |
| `cashbook_id` | unsigned big integer | Foreign key to cashbooks |
| `transaction_type` | string | `income` or `expense` |
| `amount` | decimal(18, 2) | Positive transaction amount |
| `balance_after` | decimal(18, 2) | Cashbook balance after this transaction |
| `transaction_date` | date-time | Effective transaction date |
| `reference_type` | string nullable | Polymorphic reference alias |
| `reference_id` | unsigned big integer nullable | Polymorphic reference id |
| `remark` | text nullable | Transaction description |
| `created_by` | unsigned big integer nullable | Foreign key to users |
| `created_at` | timestamp | Laravel default |
| `updated_at` | timestamp | Laravel default |

### 8.24 Asset Categories Table

| Column | Type | Notes |
| --- | --- | --- |
| `id` | unsigned big integer | Primary key |
| `parent_id` | unsigned big integer nullable | Self-referencing parent category |
| `name` | string | Category name |
| `slug` | string | Unique slug |
| `code` | string | Unique normalized code |
| `description` | text nullable | Optional description |
| `is_active` | boolean | Active state |
| `created_at` | timestamp | Laravel default |
| `updated_at` | timestamp | Laravel default |

### 8.25 Fixed Assets Table

| Column | Type | Notes |
| --- | --- | --- |
| `id` | unsigned big integer | Primary key |
| `reference_no` | string | Unique generated document reference |
| `asset_code` | string | Unique normalized asset code |
| `asset_category_id` | unsigned big integer | Foreign key to asset categories |
| `cashbook_id` | unsigned big integer | Foreign key to cashbooks |
| `name` | string | Asset name |
| `serial_number` | string nullable | Manufacturer serial number |
| `location` | string nullable | Current asset location |
| `purchase_date` | date-time | Purchase transaction date |
| `purchase_amount` | decimal(18, 2) | Cash purchase amount |
| `warranty_expiry_date` | date nullable | Warranty expiration date |
| `documents` | JSON nullable | Array of document metadata |
| `status` | string | `draft`, `posted`, or `cancelled` |
| `remark` | text nullable | Optional notes |
| `created_by` | unsigned big integer nullable | Foreign key to users |
| `created_at` | timestamp | Laravel default |
| `updated_at` | timestamp | Laravel default |

### 8.26 Menu Categories Table

| Column | Type | Notes |
| --- | --- | --- |
| `id` | unsigned big integer | Primary key |
| `name` | string | Menu category name |
| `slug` | string | Unique slug |
| `code` | string | Unique normalized code |
| `description` | text nullable | Optional description |
| `sort_order` | unsigned integer | Display order |
| `is_active` | boolean | Active state |
| `created_at` | timestamp | Laravel default |
| `updated_at` | timestamp | Laravel default |

### 8.27 Menus Table

| Column | Type | Notes |
| --- | --- | --- |
| `id` | unsigned big integer | Primary key |
| `menu_category_id` | unsigned big integer | Foreign key to menu categories |
| `name` | string | Menu item name |
| `code` | string | Unique menu code |
| `description` | text nullable | Optional description |
| `price` | decimal(18, 2) | Selling price |
| `cost_price` | decimal(18, 2) nullable | Optional cost price |
| `image_url` | string nullable | Optional image URL |
| `is_available` | boolean | POS availability |
| `is_active` | boolean | Active state |
| `created_at` | timestamp | Laravel default |
| `updated_at` | timestamp | Laravel default |

### 8.28 Menu Recipes Table

| Column | Type | Notes |
| --- | --- | --- |
| `id` | unsigned big integer | Primary key |
| `menu_id` | unsigned big integer | Foreign key to menus |
| `item_id` | unsigned big integer | Foreign key to inventory items |
| `unit_id` | unsigned big integer | Foreign key to units |
| `quantity` | decimal(18, 6) | Recipe quantity |
| `base_quantity` | decimal(18, 6) | Quantity converted to item stock unit |
| `remark` | text nullable | Optional remark |
| `created_at` | timestamp | Laravel default |
| `updated_at` | timestamp | Laravel default |

### 8.29 Bookings Table

| Column | Type | Notes |
| --- | --- | --- |
| `id` | unsigned big integer | Primary key |
| `booking_no` | string | Unique generated booking number |
| `room_id` | unsigned big integer | Foreign key to rooms |
| `booking_type` | string | `reservation` or `walk_in` |
| `charge_type` | string | `day` or `session` |
| `guest_name` | string | Guest display name |
| `guest_phone` | string nullable | Guest phone |
| `guest_email` | string nullable | Guest email |
| `expected_check_in_at` | date-time | Planned check-in |
| `expected_check_out_at` | date-time nullable | Required for session bookings |
| `checked_in_at` | date-time nullable | Actual check-in |
| `checked_out_at` | date-time nullable | Actual check-out |
| `status` | string | `reserved`, `checked_in`, `checked_out`, or `cancelled` |
| `guest_count` | integer | Number of guests |
| `room_rate` | decimal(18, 2) | Room rate snapshot |
| `session_hours` | integer nullable | Required for session charge |
| `session_rate` | decimal(18, 2) nullable | Required for session charge |
| `subtotal` | decimal(18, 2) | Booking room/session subtotal |
| `discount_amount` | decimal(18, 2) | Discount amount |
| `tax_amount` | decimal(18, 2) | Tax amount |
| `total_amount` | decimal(18, 2) | Stored room/session total |
| `paid_amount` | decimal(18, 2) | Stored paid amount |
| `balance_amount` | decimal(18, 2) | Stored booking balance |
| `note` | text nullable | Internal note |
| `created_by` | unsigned big integer nullable | User who created booking |
| `checked_in_by` | unsigned big integer nullable | User who checked in |
| `checked_out_by` | unsigned big integer nullable | User who checked out |
| `cancelled_by` | unsigned big integer nullable | User who cancelled |
| `created_at` | timestamp | Laravel default |
| `updated_at` | timestamp | Laravel default |

### 8.30 Booking Payments Table

| Column | Type | Notes |
| --- | --- | --- |
| `id` | unsigned big integer | Primary key |
| `booking_id` | unsigned big integer | Foreign key to bookings |
| `cashbook_id` | unsigned big integer | Foreign key to cashbooks |
| `payment_no` | string | Unique generated payment number |
| `payment_type` | string | `deposit`, `partial`, `checkout`, or `refund` |
| `payment_method` | string | `cash`, `kpay`, `wavepay`, `bank_transfer`, or `card` |
| `amount` | decimal(18, 2) | Payment amount |
| `paid_at` | date-time | Payment date and time |
| `note` | text nullable | Payment note |
| `created_by` | unsigned big integer nullable | User who recorded payment |
| `created_at` | timestamp | Laravel default |
| `updated_at` | timestamp | Laravel default |

### 8.31 Food Orders and Food Order Items Tables

Food orders store booking-level menu orders. Food order items store menu snapshots, quantity, unit price, line total, and note values.

Core food order fields:

- `booking_id`
- `room_id`
- `order_no`
- `status`
- `subtotal`
- `discount_amount`
- `tax_amount`
- `total_amount`
- `note`
- `created_by`

### 8.32 Service Orders and Service Order Items Tables

Service orders store amenity and laundry requests in one table. Service order items store item snapshots, quantity, unit price, line total, and note values.

Core service order fields:

- `booking_id`
- `room_id`
- `order_no`
- `type`
- `status`
- `subtotal`
- `discount_amount`
- `tax_amount`
- `total_amount`
- `note`
- `created_by`

### 8.33 Relationships

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
- Menu category has many menus.
- Menu belongs to menu category.
- Menu has many menu recipes and food order items.
- Menu recipe belongs to menu, item, and unit.
- Warehouse has many stock documents and stock balances.
- Stock in belongs to warehouse and has many stock in items.
- Stock out belongs to warehouse and has many stock out items.
- Inventory transfer belongs to source and destination warehouses and has many transfer items.
- Inventory adjustment belongs to warehouse and has many adjustment items.
- Inventory ledger belongs to item, warehouse, unit, and creator.
- Inventory stock balance belongs to item and warehouse.
- Cashbook has many cashbook transactions.
- Asset category belongs to an optional parent asset category.
- Asset category has many child asset categories and fixed assets.
- Fixed asset belongs to an asset category, cashbook, and creator.
- Fixed asset has one polymorphically linked cashbook transaction after posting.
- User has many bookings, booking payments, food orders, and service orders.
- Room has many bookings, food orders, and service orders.
- Booking belongs to room and has many booking payments.
- Booking has many food orders and service orders.
- Booking payment belongs to booking, cashbook, and creator.
- Food order belongs to booking and room and has many food order items.
- Food order item belongs to food order and menu.
- Service order belongs to booking and room and has many service order items.
- Service order item belongs to service order and item.
- Audit belongs to user.
- Audit morphs to auditable entity.

## 9. API Overview

Base prefix:

```text
Admin Portal: /api/v1/admin
POS Portal: /api/v1/pos
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

Admin protected routes require an authenticated user with `portal_access = admin` or `portal_access = both`.

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

### 9.10 Menu Category Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/api/v1/admin/menu-categories` | List menu categories |
| `POST` | `/api/v1/admin/menu-categories` | Create or update menu category |
| `GET` | `/api/v1/admin/menu-categories/{menu_category}` | Show menu category |
| `DELETE` | `/api/v1/admin/menu-categories/{menu_category}` | Delete menu category |
| `POST` | `/api/v1/admin/menu-categories/{menuCategory}/toggle-active` | Update menu category active state |

### 9.11 Menu Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/api/v1/admin/menus` | List menus |
| `POST` | `/api/v1/admin/menus` | Create or update menu and recipes |
| `GET` | `/api/v1/admin/menus/{menu}` | Show menu |
| `DELETE` | `/api/v1/admin/menus/{menu}` | Delete menu |
| `POST` | `/api/v1/admin/menus/{menu}/toggle-active` | Update menu active state |
| `POST` | `/api/v1/admin/menus/{menu}/toggle-available` | Update POS availability |

### 9.12 Warehouse Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/api/v1/admin/warehouses` | List warehouses |
| `POST` | `/api/v1/admin/warehouses` | Create or update warehouse |
| `GET` | `/api/v1/admin/warehouses/{warehouse}` | Show warehouse |
| `DELETE` | `/api/v1/admin/warehouses/{warehouse}` | Delete warehouse |
| `POST` | `/api/v1/admin/warehouses/{warehouse}/toggle-active` | Update warehouse active state |

### 9.13 Stock In Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/api/v1/admin/stock-ins` | List stock in documents |
| `POST` | `/api/v1/admin/stock-ins` | Create or update draft stock in |
| `GET` | `/api/v1/admin/stock-ins/{stock_in}` | Show stock in |
| `DELETE` | `/api/v1/admin/stock-ins/{stock_in}` | Delete draft stock in |
| `POST` | `/api/v1/admin/stock-ins/{stockIn}/status` | Change draft stock in to posted or cancelled |

### 9.14 Stock Out Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/api/v1/admin/stock-outs` | List stock out documents |
| `POST` | `/api/v1/admin/stock-outs` | Create or update draft stock out |
| `GET` | `/api/v1/admin/stock-outs/{stock_out}` | Show stock out |
| `DELETE` | `/api/v1/admin/stock-outs/{stock_out}` | Delete draft stock out |
| `POST` | `/api/v1/admin/stock-outs/{stockOut}/status` | Change draft stock out to posted or cancelled |

### 9.15 Inventory Transfer Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/api/v1/admin/inventory-transfers` | List inventory transfers |
| `POST` | `/api/v1/admin/inventory-transfers` | Create or update draft transfer |
| `GET` | `/api/v1/admin/inventory-transfers/{inventory_transfer}` | Show transfer |
| `DELETE` | `/api/v1/admin/inventory-transfers/{inventory_transfer}` | Delete draft transfer |
| `POST` | `/api/v1/admin/inventory-transfers/{inventoryTransfer}/status` | Change draft transfer to posted or cancelled |

### 9.16 Inventory Adjustment Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/api/v1/admin/inventory-adjustments` | List inventory adjustments |
| `POST` | `/api/v1/admin/inventory-adjustments` | Create or update draft adjustment |
| `GET` | `/api/v1/admin/inventory-adjustments/{inventory_adjustment}` | Show adjustment |
| `DELETE` | `/api/v1/admin/inventory-adjustments/{inventory_adjustment}` | Delete draft adjustment |
| `POST` | `/api/v1/admin/inventory-adjustments/{inventoryAdjustment}/status` | Change draft adjustment to posted or cancelled |

### 9.17 Inventory Ledger Endpoints

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

### 9.18 Inventory Stock Balance Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/api/v1/admin/inventory-stock-balances` | List stock balances |
| `GET` | `/api/v1/admin/inventory-stock-balances/{inventory_stock_balance}` | Show stock balance |

Stock balance filters:

- `item_id`
- `warehouse_id`
- `page`
- `per_page`

### 9.19 Audit Endpoints

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

### 9.20 Cashbook Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/api/v1/admin/cashbooks` | List cashbooks |
| `POST` | `/api/v1/admin/cashbooks` | Create or update a cashbook; include `id` to update |
| `GET` | `/api/v1/admin/cashbooks/{cashbook}` | Show cashbook |
| `DELETE` | `/api/v1/admin/cashbooks/{cashbook}` | Delete cashbook when allowed |
| `POST` | `/api/v1/admin/cashbooks/{cashbook}/toggle-active` | Change active state |

Cashbook filters:

- `search`
- `type`
- `is_active`
- `page`
- `per_page`

### 9.21 Cashbook Transaction Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/api/v1/admin/cashbook-transactions` | List cashbook transactions and balance summary |
| `POST` | `/api/v1/admin/cashbook-transactions` | Post a direct income or expense transaction |
| `GET` | `/api/v1/admin/cashbook-transactions/{cashbook_transaction}` | Show cashbook transaction |

Cashbook transaction filters:

- `search`
- `cashbook_id`
- `transaction_type`
- `date_from`
- `date_to`
- `page`
- `per_page`

### 9.22 Asset Category Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/api/v1/admin/asset-categories` | List active categories without pagination or all matching categories with pagination |
| `POST` | `/api/v1/admin/asset-categories` | Create or update a category; include `id` to update |
| `GET` | `/api/v1/admin/asset-categories/{asset_category}` | Show category |
| `DELETE` | `/api/v1/admin/asset-categories/{asset_category}` | Delete category when it has no children or fixed assets |
| `POST` | `/api/v1/admin/asset-categories/{assetCategory}/toggle-active` | Change active state |

Asset category filters:

- `search`
- `parent_id`; send an empty value to list top-level parent categories
- `is_active`
- `page`
- `per_page`

Create asset category payload example:

```json
{
  "parent_id": 3,
  "name": "Kitchen Equipment",
  "code": "KITCHEN_EQUIPMENT",
  "description": "Fixed equipment used in hotel kitchens",
  "is_active": true
}
```

### 9.23 Fixed Asset Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/api/v1/admin/fixed-assets` | List fixed assets |
| `POST` | `/api/v1/admin/fixed-assets` | Create or update a draft fixed asset; include `id` to update |
| `GET` | `/api/v1/admin/fixed-assets/{fixed_asset}` | Show fixed asset and linked cashbook transaction |
| `DELETE` | `/api/v1/admin/fixed-assets/{fixed_asset}` | Delete a draft fixed asset |
| `POST` | `/api/v1/admin/fixed-assets/{fixedAsset}/status` | Post or cancel a draft fixed asset |

Fixed asset filters:

- `search`; matches reference number, asset code, name, or serial number
- `asset_category_id`
- `cashbook_id`
- `status`
- `date_from`
- `date_to`
- `page`
- `per_page`

Create fixed asset payload example:

```json
{
  "asset_code": "FA-001",
  "asset_category_id": 3,
  "cashbook_id": 1,
  "name": "Commercial Refrigerator",
  "serial_number": "SN-10001",
  "location": "Main Kitchen",
  "purchase_date": "2026-09-27 10:00:00",
  "purchase_amount": 2500000,
  "warranty_expiry_date": "2027-09-27",
  "documents": [
    {
      "name": "Purchase Invoice",
      "path": "fixed-assets/fa-001/invoice.pdf"
    }
  ],
  "remark": "Purchased from ABC Equipment"
}
```

Post fixed asset payload:

```json
{
  "status": "posted"
}
```

Cancel fixed asset payload:

```json
{
  "status": "cancelled"
}
```

### 9.24 POS Auth Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| `POST` | `/api/v1/pos/login` | Log in POS staff user and issue Sanctum token |
| `POST` | `/api/v1/pos/logout` | Revoke current token |
| `GET` | `/api/v1/pos/me` | Get authenticated POS user profile |

POS protected routes require an authenticated user with `portal_access = pos` or `portal_access = both`.

### 9.25 POS Room Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/api/v1/pos/rooms` | List rooms for POS |
| `GET` | `/api/v1/pos/rooms/available` | List available rooms for booking |
| `GET` | `/api/v1/pos/rooms/{room}` | Show POS room detail |

### 9.26 POS Booking Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/api/v1/pos/booking-dashboard` | Calendar-style room booking dashboard |
| `GET` | `/api/v1/pos/bookings` | List POS bookings |
| `POST` | `/api/v1/pos/bookings` | Create reservation or walk-in booking |
| `GET` | `/api/v1/pos/bookings/{booking}` | Show booking with payments, food orders, service orders, and calculated totals |
| `POST` | `/api/v1/pos/bookings/{booking}/payments` | Add partial payment |
| `POST` | `/api/v1/pos/bookings/{booking}/check-in` | Check in a reserved booking |
| `POST` | `/api/v1/pos/bookings/{booking}/checkout` | Check out with final payment |
| `POST` | `/api/v1/pos/bookings/{booking}/check-out` | Legacy checkout without payment payload |
| `POST` | `/api/v1/pos/bookings/{booking}/cancel` | Cancel booking |

Reservation payload example with optional deposit:

```json
{
  "booking_type": "reservation",
  "charge_type": "day",
  "room_id": 1,
  "guest_name": "Mg Mg",
  "guest_phone": "09123456789",
  "expected_check_in_at": "2026-10-08 14:00:00",
  "expected_check_out_at": "2026-10-09 12:00:00",
  "guest_count": 2,
  "discount_amount": 0,
  "tax_amount": 0,
  "note": "Deposit received",
  "deposit": {
    "cashbook_id": 1,
    "payment_method": "cash",
    "amount": 15000,
    "paid_at": "2026-10-08 12:00:00",
    "note": "Reservation deposit"
  }
}
```

Walk-in payload example:

```json
{
  "booking_type": "walk_in",
  "charge_type": "session",
  "room_id": 1,
  "guest_name": "Aye Aye",
  "guest_phone": "09987654321",
  "expected_check_in_at": "2026-10-08 10:00:00",
  "expected_check_out_at": "2026-10-08 14:00:00",
  "session_hours": 4,
  "session_rate": 40000,
  "guest_count": 2,
  "check_in_now": true,
  "note": "Walk-in session"
}
```

Partial payment payload example:

```json
{
  "cashbook_id": 1,
  "payment_method": "cash",
  "amount": 15000,
  "paid_at": "2026-10-08 12:00:00",
  "note": "Partial payment"
}
```

Checkout payload example:

```json
{
  "cashbook_id": 1,
  "payment_method": "cash",
  "amount": 15000,
  "paid_at": "2026-10-08 12:00:00",
  "note": "Final payment"
}
```

### 9.27 POS Food Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/api/v1/pos/menus` | List active food menus |
| `GET` | `/api/v1/pos/food-orders` | List food orders |
| `POST` | `/api/v1/pos/food-orders` | Create food order for a checked-in booking |
| `GET` | `/api/v1/pos/food-orders/{foodOrder}` | Show food order |
| `POST` | `/api/v1/pos/food-orders/{foodOrder}/status` | Change food order status |

Food order payload example:

```json
{
  "booking_id": 1,
  "note": "Serve to room",
  "items": [
    {
      "menu_id": 1,
      "qty": 2,
      "note": "No chili"
    }
  ]
}
```

### 9.28 POS Service Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/api/v1/pos/items?category=amenity` | List amenity service items |
| `GET` | `/api/v1/pos/items?category=laundry` | List laundry service items |
| `GET` | `/api/v1/pos/service-orders` | List service orders |
| `POST` | `/api/v1/pos/service-orders` | Create amenity or laundry service order |
| `GET` | `/api/v1/pos/service-orders/{serviceOrder}` | Show service order |
| `POST` | `/api/v1/pos/service-orders/{serviceOrder}/status` | Change service order status |

Service order payload example:

```json
{
  "booking_id": 1,
  "type": "laundry",
  "note": "Return before checkout",
  "items": [
    {
      "item_id": 12,
      "qty": 3,
      "note": "Shirts"
    }
  ]
}
```

## 10. UI Overview

The Laravel project is backend-only. The APIs are intended for Admin Portal and POS Portal clients built separately.

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
- Cashbook list/create/edit screen
- Cashbook transaction list and balance summary screen
- Asset category tree/list/create/edit screen
- Fixed asset list/create/edit/status screen
- Fixed asset detail screen with linked cashbook transaction and documents
- Audit log screen
- Audit log detail screen
- POS login screen
- POS room availability screen
- POS booking dashboard calendar screen
- POS reservation and walk-in booking screens
- POS booking detail screen with room, payments, food orders, service orders, and totals
- POS check-in, partial payment, checkout, and cancellation controls
- POS food order screen
- POS amenities and laundry service order screen

Expected dashboard behaviors:

- Use bearer-token authentication with Sanctum.
- Show server validation errors next to relevant fields.
- Use pagination for user, room category, room, inventory, item, conversion, transaction, ledger, balance, cashbook, asset, and audit log lists.
- Provide filters for active/inactive setup data, item category, stock unit, room category, room status, audit event, audit module, audit record id, and date range.
- For non-paginated dropdown lists, expect active records only.
- Use `GET /api/v1/admin/asset-categories?parent_id=` for the active top-level asset category parent dropdown.
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

### 11.12 Create and Post Fixed Asset

1. Authorized user selects an asset category and active cashbook.
2. User submits `asset_code`, name, purchase date, purchase amount, and optional asset details and documents.
3. API validates the payload, generates `reference_no` when omitted, and creates the fixed asset as `draft`.
4. User may update or delete the fixed asset while it remains draft.
5. Authorized user posts the asset through `POST /api/v1/admin/fixed-assets/{fixedAsset}/status` with `status = posted`.
6. API locks the fixed asset and cashbook rows.
7. API validates that the fixed asset is still draft, the cashbook is active, and its balance is sufficient.
8. API creates exactly one cashbook `expense` transaction linked through `reference_type = fixed_asset` and the fixed asset id.
9. API subtracts `purchase_amount` from the cashbook balance and changes the fixed asset status to `posted` in the same database transaction.
10. Any failure rolls back the cashbook transaction, balance update, and status update together.

### 11.13 Cancel Fixed Asset

1. Authorized user selects a draft fixed asset.
2. User submits `status = cancelled` through the fixed asset status endpoint.
3. API validates that the fixed asset is still draft.
4. API changes the status to `cancelled` without creating a cashbook transaction or changing the cashbook balance.

### 11.14 POS Reservation With Optional Deposit

1. POS user selects an available room and submits a reservation booking.
2. API validates room availability, guest data, booking type, charge type, expected dates, and optional deposit payload.
3. API creates a booking with `status = reserved`.
4. API changes the room status to `reserved`.
5. If a deposit is included, API validates the active cashbook and records a `deposit` booking payment.
6. API updates paid and balance amounts and returns the booking.

### 11.15 POS Walk-In Booking

1. POS user submits a booking with `booking_type = walk_in`.
2. API validates the room is available.
3. API creates the booking with `status = checked_in`.
4. API sets `checked_in_at` to the current time and records the check-in user.
5. API changes the room status to `occupied`.
6. API returns the checked-in booking.

### 11.16 POS Partial Payment

1. POS user opens a checked-in booking.
2. User submits `cashbook_id`, `payment_method`, `amount`, optional `paid_at`, and optional `note`.
3. API validates the booking is checked in and the amount does not exceed the effective balance.
4. API records a `partial` booking payment.
5. API recalculates paid and balance amounts and returns the booking.

### 11.17 POS Checkout With Payment

1. POS user opens a checked-in booking.
2. API calculates effective payable amount from room/session charge plus non-cancelled food and service orders.
3. User submits final payment data.
4. API validates the amount covers the effective balance.
5. API records a `checkout` booking payment.
6. API changes booking status to `checked_out`, sets `checked_out_at`, and records the checkout user.
7. API changes room status to `dirty`.
8. API returns the checked-out booking with calculated totals.

### 11.18 POS Food Order

1. POS user selects a checked-in booking.
2. User submits menu items and quantities.
3. API validates the booking is `checked_in` and the room is `occupied`.
4. API creates a `pending` food order and order item rows.
5. API allows status updates through the approved flow: `pending -> preparing`, `pending -> cancelled`, or `preparing -> served`.

### 11.19 POS Amenity and Laundry Service Order

1. POS user selects a checked-in booking.
2. User selects `type = amenity` or `type = laundry`.
3. API validates the booking is `checked_in`, the room is `occupied`, and every item belongs to the correct service category.
4. API creates a `pending` service order and service order item rows.
5. API allows status updates through the approved flow: `pending -> processing`, `pending -> cancelled`, or `processing -> completed`.

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
| Manage menus | Yes | No | No | Yes | No | No |
| View menus | Yes | Yes | Yes | Yes | Yes | No |
| Manage warehouses | Yes | No | No | No | No | Yes |
| View warehouses | Yes | No | No | Yes | No | Yes |
| Manage stock documents | Yes | No | No | No | No | Yes |
| View inventory ledgers | Yes | No | No | No | No | Yes |
| View stock balances | Yes | No | No | No | No | Yes |
| Manage cashbooks and transactions | Yes | No | No | No | No | No |
| Manage asset categories and fixed assets | Yes | No | No | No | No | No |
| View audit logs | Yes | No | No | No | No | No |
| View activity history through audit logs | Yes | No | No | No | No | No |
| Restore deleted records | Yes | No | No | No | No | No |
| POS room availability | Yes | Yes | Yes | Limited | Yes | No |
| POS booking dashboard | Yes | Yes | Yes | No | Yes | No |
| Create reservation booking | Yes | Yes | Yes | No | No | No |
| Create walk-in booking | Yes | Yes | Limited | No | No | No |
| Check in, partial payment, and checkout | Yes | Yes | Limited | No | No | No |
| Create food orders | Yes | Yes | No | Yes | No | No |
| Create amenity and laundry service orders | Yes | Yes | No | No | Yes | No |

Limited permissions:

- Reservation Administrator may update room status only when related to reservation operations, such as `available` to `reserved` or `reserved` to `available`.
- Portal access is checked before role-level authorization: Admin routes require `portal_access = admin` or `both`; POS routes require `portal_access = pos` or `both`.
- Current implemented admin routes are protected by Hotel Administrator role middleware. Inventory Administrator access may be opened later with route-level permission changes.

## 13. Architecture

### 13.1 Application Structure

Recommended Laravel structure:

```text
app/
  Enums/
    AdjustmentStatusEnum.php
    AdjustmentTypeEnum.php
    BookingChargeTypeEnum.php
    BookingPaymentTypeEnum.php
    BookingStatusEnum.php
    BookingTypeEnum.php
    CashbookTransactionTypeEnum.php
    FixedAssetStatusEnum.php
    FoodOrderStatusEnum.php
    PaymentMethodEnum.php
    RoomBedTypeEnum.php
    RoomStatusEnum.php
    ServiceOrderStatusEnum.php
    ServiceOrderTypeEnum.php
    StockInStatusEnum.php
    StockOutStatusEnum.php
    TransferStatusEnum.php
    UserPortalAccessEnum.php
    UserRoleEnum.php
  Http/
    Controllers/
      Api/
        V1/
          Admin/
            AssetCategoryController.php
            AuditLogController.php
            AuthController.php
            CashbookController.php
            CashbookTransactionController.php
            FixedAssetController.php
            InventoryAdjustmentController.php
            InventoryLedgerController.php
            InventoryStockBalanceController.php
            InventoryTransferController.php
            ItemCategoryController.php
            ItemController.php
            ItemUnitConversionController.php
            MenuCategoryController.php
            MenuController.php
            RoomCategoryController.php
            RoomController.php
            StockInController.php
            StockOutController.php
            UnitController.php
            UnitGroupController.php
            UserController.php
            WarehouseController.php
          Pos/
            AuthController.php
            BookingController.php
            BookingDashboardController.php
            FoodOrderController.php
            ItemController.php
            MenuController.php
            RoomController.php
            ServiceOrderController.php
    Requests/
      AssetCategories/
      Auth/
      Cashbooks/
      CashbookTransactions/
      FixedAssets/
      InventoryAdjustments/
      InventoryTransfers/
      ItemCategories/
      ItemUnitConversions/
      Items/
      MenuCategories/
      Menus/
      Pos/
        Bookings/
        FoodOrders/
        ServiceOrders/
      RoomCategories/
      Rooms/
      StockIns/
      StockOuts/
      UnitGroups/
      Units/
      Users/
      Warehouses/
    Resources/
      AssetCategories/
        AssetCategoryResource.php
      AuditLogs/
        AuditLogResource.php
      Cashbooks/
        CashbookResource.php
      CashbookTransactions/
        CashbookTransactionResource.php
      Concerns/
        FormatsDateTime.php
      FixedAssets/
        FixedAssetResource.php
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
      MenuCategories/
        MenuCategoryResource.php
      Menus/
        MenuResource.php
        MenuRecipeResource.php
      Pos/
        Bookings/
          PosBookingResource.php
        FoodOrders/
          PosFoodOrderResource.php
        Menus/
          PosMenuResource.php
        Rooms/
          PosRoomResource.php
        ServiceOrders/
          PosServiceOrderResource.php
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
    AssetCategory.php
    Booking.php
    BookingPayment.php
    Cashbook.php
    CashbookTransaction.php
    FixedAsset.php
    FoodOrder.php
    FoodOrderItem.php
    InventoryAdjustment.php
    InventoryAdjustmentItem.php
    InventoryLedger.php
    InventoryStockBalance.php
    InventoryTransfer.php
    InventoryTransferItem.php
    Item.php
    ItemCategory.php
    ItemUnitConversion.php
    Menu.php
    MenuCategory.php
    MenuRecipe.php
    RoomCategory.php
    Room.php
    RoomBed.php
    ServiceOrder.php
    ServiceOrderItem.php
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
    AssetCategories/
      AssetCategoryService.php
    AuditLogs/
      AuditLogService.php
    Auth/
      AuthService.php
    Bookings/
      BookingDashboardService.php
      BookingService.php
    Cashbooks/
      CashbookService.php
    CashbookTransactions/
      CashbookTransactionService.php
    FixedAssets/
      FixedAssetService.php
    FoodOrders/
      FoodOrderService.php
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
    MenuCategories/
      MenuCategoryService.php
    Menus/
      MenuService.php
    RoomCategories/
      RoomCategoryService.php
    Rooms/
      RoomService.php
    ServiceOrders/
      ServiceOrderService.php
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
- All first-release POS API routes should live under `/api/v1/pos`.
- POS controllers should be namespaced under `App\Http\Controllers\Api\V1\Pos`.
- Breaking changes should be introduced in a future version, for example `/api/v2`.

### 13.3 Authentication and Authorization

- Sanctum protects all internal API routes except login.
- `portal:admin` protects Admin Portal routes.
- `portal:pos` protects POS Portal routes.
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
- Validate user `portal_access` through `UserPortalAccessEnum`.
- Validate booking type, charge type, booking status, payment type, and payment method through enums.
- Validate food order status transitions so cancellation is allowed only from `pending`.
- Validate service order status transitions so cancellation is allowed only from `pending`.
- Validate service order item category matches the selected service order type.

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
  - `cashbooks.code`
  - `cashbooks.type`
  - `cashbooks.is_active`
  - `cashbook_transactions.reference_no`
  - `cashbook_transactions.cashbook_id`, `cashbook_transactions.transaction_date`
  - `cashbook_transactions.transaction_type`
  - `cashbook_transactions.reference_type`, `cashbook_transactions.reference_id`
  - `asset_categories.parent_id`
  - `asset_categories.slug`
  - `asset_categories.code`
  - `asset_categories.is_active`
  - `fixed_assets.reference_no`
  - `fixed_assets.asset_code`
  - `fixed_assets.asset_category_id`, `fixed_assets.purchase_date`
  - `fixed_assets.cashbook_id`, `fixed_assets.purchase_date`
  - `fixed_assets.serial_number`
  - `fixed_assets.status`
  - `audits.user_id`, `audits.user_type`
  - `audits.auditable_type`, `audits.auditable_id`
  - `audits.created_at`

### 13.7 Polymorphic Morph Map

Use `Relation::enforceMorphMap()` so polymorphic database values are stable aliases instead of PHP class names.

Required aliases:

- `user`
- `asset_category`
- `fixed_asset`
- `cashbook`
- `cashbook_transaction`
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
- Add `portal_access` to users.
- Add `UserPortalAccessEnum` and portal middleware.
- Add role authorization middleware or policies.
- Seed initial admin, POS, and both-portal staff accounts.

### Milestone 3: User Management

- Update user migration/model for role, active state, and audit fields.
- Add user create/update validation for `portal_access`.
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
- Seed room categories and sample rooms for POS booking flows.

### Milestone 6: Inventory Setup

- Create unit groups migration and model.
- Create units migration and model.
- Create item categories migration and model.
- Seed initial unit groups, units, and item categories.
- Seed service item categories for Amenities and Laundry.
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
- Add tests for asset category hierarchy, active lists, and idempotent seed data.
- Add tests proving a fixed asset creates exactly one cashbook transaction when posted.
- Add tests for portal access login and route protection.
- Add tests for POS room list, available rooms, booking dashboard, bookings, deposits, partial payments, and checkout payments.
- Add tests for food order and service order creation and status transitions.
- Add tests for duplicate-post protection, immutable posted assets, insufficient cashbook balance, and atomic rollback.
- Add tests for backend-generated reference numbers, batch numbers, and base quantities.
- Add tests for consistent API responses.
- Add tests for soft delete and restore behavior.
- Add tests for audit log creation.
- Add tests for date/time response format.

### Milestone 11: Documentation and Handoff

- Document environment setup.
- Document API authentication flow.
- Document Admin Portal and POS Portal route separation.
- Document API response format.
- Document date/time response format.
- Document inventory setup APIs.
- Document item and conversion APIs.
- Document warehouse and inventory transaction APIs.
- Document inventory posting behavior and ledger transaction types.
- Document cashbook, asset category, and fixed asset APIs.
- Document POS booking, payment, food order, and service order APIs.
- Document fixed asset payloads, document metadata, and atomic cashbook posting behavior.
- Document backend-generated fields.
- Document audit log filters and morph aliases.
- Document role permissions.
- Provide seed data instructions.
- Prepare dashboard integration notes.

### Milestone 12: POS Portal Operations

- Add POS route file and `/api/v1/pos` route grouping.
- Add POS auth controller using the shared user system.
- Add room list, available room list, and room detail endpoints for POS.
- Add bookings, booking payments, booking dashboard, food orders, and service orders tables.
- Add booking, booking payment, food order, and service order enums.
- Add booking creation for reservations and walk-ins.
- Add optional reservation deposit flow.
- Add partial payment and checkout payment flows.
- Add food menu list and food order status workflow.
- Add amenity and laundry item list and service order workflow.
- Include food orders, service orders, and calculated totals in booking detail response.

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
- Should fixed asset documents remain external path metadata, or should a dedicated upload and document storage API be added?
- Should fixed asset depreciation and disposal workflows be added in a later phase?
- Should posted booking payments also create cashbook transaction rows automatically, or should the booking payment table remain the operational source for this phase?
- Should service order inventory consumption be connected to stock ledgers later?
