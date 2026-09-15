# Hotel Management System Project Specification

## 1. Project Overview

The Hotel Management System is a backend API application for managing hotel staff users, room categories, and rooms. The system is designed for an internal admin web dashboard and will expose versioned REST APIs secured with Laravel Sanctum authentication.

The first release focuses on core hotel administration data: internal staff accounts, room category setup, room inventory, room status tracking, audit logs, soft deletes, and activity history.

## 2. Goals

- Provide a secure Laravel backend API for an internal hotel administration dashboard.
- Manage internal staff users and role-based access.
- Manage room categories with activation control.
- Manage rooms and their operational statuses.
- Keep API responses consistent across success, validation, authentication, authorization, and error states.
- Preserve important operational history with audit logs, soft deletes, and activity history.
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
- Soft delete and restore support where appropriate.
- Audit logging for create, update, delete, restore, login, and logout events.
- Activity history for user, room category, and room records.
- MySQL database support.
- Backend-only Laravel project with no frontend build tooling.

### Out of Scope for Initial Release

- Public guest/customer registration.
- Public booking website.
- Reservation/booking workflows.
- Payment processing.
- Restaurant ordering.
- Inventory stock management.
- Housekeeping task assignment.
- Multi-branch hotel support.
- Mobile app implementation.

These excluded areas may be added in later API versions or feature phases.

## 4. Technology Stack

- Framework: Laravel
- Authentication: Laravel Sanctum
- Database: MySQL
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

- Hotel Administrator: Full administrative access across users, room categories, rooms, audit logs, and system settings.
- Front Office Administrator: Manage room availability and day-to-day room status changes.
- Reservation Administrator: View and update room availability/status related to reservation operations.
- Restaurant Administrator: Internal role reserved for future restaurant workflows.
- Customer Service Administrator: View room and category information to support guest service workflows.
- Inventory Administrator: Internal role reserved for future inventory workflows.

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
- Users should have activity history.

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
- Status changes must be captured in activity history.

### 6.5 Audit Logs

The system must record audit logs for important events.

Required audited actions:

- User login
- User logout
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

Recommended audit log fields:

- `id`
- `actor_id`
- `actor_role`
- `action`
- `auditable_type`
- `auditable_id`
- `old_values`
- `new_values`
- `ip_address`
- `user_agent`
- `created_at`

### 6.6 Activity History

Activity history should allow administrators to inspect the timeline of changes for an entity.

Supported entities:

- Users
- Room categories
- Rooms

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

### 8.5 Audit Logs Table

| Column | Type | Notes |
| --- | --- | --- |
| `id` | unsigned big integer | Primary key |
| `actor_id` | unsigned big integer nullable | Acting user |
| `actor_role` | string nullable | Actor role at time of action |
| `action` | string | Event/action name |
| `auditable_type` | string nullable | Polymorphic model type |
| `auditable_id` | unsigned big integer nullable | Polymorphic model id |
| `old_values` | json nullable | Previous values |
| `new_values` | json nullable | New values |
| `ip_address` | string nullable | Request IP |
| `user_agent` | text nullable | Request user agent |
| `created_at` | timestamp | Event time |

### 8.6 Relationships

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
- Audit log belongs to actor user.
- Audit log morphs to auditable entity.

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
| `POST` | `/api/v1/admin/room-categories` | Create room category |
| `GET` | `/api/v1/admin/room-categories/{roomCategory}` | Show room category |
| `PUT/PATCH` | `/api/v1/admin/room-categories/{roomCategory}` | Update room category |
| `DELETE` | `/api/v1/admin/room-categories/{roomCategory}` | Soft delete room category |
| `POST` | `/api/v1/admin/room-categories/{roomCategory}/toggle-active` | Update room category active state |
| `POST` | `/api/v1/admin/room-categories/{roomCategory}/restore` | Restore room category |

### 9.4 Room Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/api/v1/admin/rooms` | List rooms |
| `POST` | `/api/v1/admin/rooms` | Create room |
| `GET` | `/api/v1/admin/rooms/{room}` | Show room |
| `POST` | `/api/v1/admin/rooms/{room}/status` | Update room status |
| `DELETE` | `/api/v1/admin/rooms/{room}` | Soft delete room |
| `POST` | `/api/v1/admin/rooms/{room}/restore` | Restore room |

### 9.5 Audit and Activity Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/api/v1/admin/audit-logs` | List audit logs |
| `GET` | `/api/v1/admin/users/{user}/activities` | User activity history |
| `GET` | `/api/v1/admin/room-categories/{roomCategory}/activities` | Room category activity history |
| `GET` | `/api/v1/admin/rooms/{room}/activities` | Room activity history |

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
- Audit log screen
- Entity activity timeline screen

Expected dashboard behaviors:

- Use bearer-token authentication with Sanctum.
- Show server validation errors next to relevant fields.
- Use pagination for user, room category, room, and audit log lists.
- Provide filters for active/inactive categories and room status.
- Confirm destructive actions before delete.
- Show restore action only to roles with restore permission.

## 11. Workflows

### 11.1 Staff Login

1. Staff user submits email and password.
2. API validates credentials.
3. API checks that the user is active.
4. API issues a Sanctum token.
5. API records `last_login_at`.
6. API writes a login audit log.
7. Dashboard stores the token securely and uses it for future requests.

### 11.2 Create Room Category

1. Authorized user submits category name, optional description, and active state.
2. API trims the name and normalizes repeated spaces.
3. API validates uniqueness among non-deleted room categories.
4. API creates the room category.
5. API records audit log and activity history.
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
10. API records audit log and activity history.
11. API returns the created room using the standard response format.

### 11.4 Update Room Status

1. Authorized user selects a new room status.
2. API validates the status against `RoomStatusEnum`.
3. API updates the room status.
4. API records old status and new status.
5. API writes audit log and activity history.
6. API returns the updated room.

### 11.5 Soft Delete and Restore

1. Authorized user requests delete.
2. API verifies permission.
3. API stores `deleted_by`.
4. API soft deletes the record.
5. API records audit log and activity history.
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
| View audit logs | Yes | No | No | No | No | No |
| View activity history | Yes | Yes | Yes | Limited | Yes | Limited |
| Restore deleted records | Yes | No | No | No | No | No |

Limited permissions:

- Reservation Administrator may update room status only when related to reservation operations, such as `available` to `reserved` or `reserved` to `available`.
- Restaurant Administrator and Inventory Administrator activity visibility is reserved for future modules and should be restricted in the initial release.

## 13. Architecture

### 13.1 Application Structure

Recommended Laravel structure:

```text
app/
  Enums/
    RoomBedTypeEnum.php
    RoomStatusEnum.php
  Http/
    Controllers/
      Api/
        V1/
          Admin/
            AuthController.php
            UserController.php
            RoomCategoryController.php
            RoomController.php
            AuditLogController.php
    Requests/
      Auth/
      Users/
      RoomCategories/
      Rooms/
    Resources/
      Users/
        UserResource.php
      RoomCategories/
        RoomCategoryResource.php
      Rooms/
        RoomResource.php
        RoomBedResource.php
      AuditLogs/
        AuditLogResource.php
  Models/
    User.php
    RoomCategory.php
    Room.php
    RoomBed.php
    AuditLog.php
  Policies/
    UserPolicy.php
    RoomCategoryPolicy.php
    RoomPolicy.php
    AuditLogPolicy.php
  Services/
    Auth/
      AuthService.php
    Users/
      UserService.php
    AuditLogs/
      AuditLogService.php
    Activities/
      ActivityService.php
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
- Use unique rules scoped to ignore soft-deleted records.
- Use enum validation for room bed type and room status.
- Validate room bed quantity as an integer greater than or equal to `1`.

### 13.5 API Response Layer

- Use a shared API response helper or response macro.
- Use Laravel API Resources for entity serialization.
- Handle exceptions consistently through Laravel exception rendering.

### 13.6 Database

- Use MySQL in production and local development unless a local SQLite setup is intentionally used for tests.
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
  - `audit_logs.actor_id`
  - `audit_logs.auditable_type`
  - `audit_logs.auditable_id`
  - `audit_logs.created_at`

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
- Add user activity history.

### Milestone 4: Room Categories

- Create room categories migration and model.
- Add room category requests, resource, controller, and policy.
- Implement trim and whitespace normalization.
- Implement CRUD, soft delete, and restore.
- Add audit logs and activity history.

### Milestone 5: Rooms

- Create `RoomBedTypeEnum` and `RoomStatusEnum`.
- Create rooms migration and model.
- Create room beds migration and model.
- Add room requests, resource, controller, and policy.
- Implement room CRUD.
- Implement room status update endpoint.
- Add audit logs and activity history for room changes.

### Milestone 6: Audit Logs and Activity History

- Create audit logs migration and model.
- Implement `AuditLogService`.
- Add audit log list endpoint.
- Add activity timeline endpoints for supported entities.
- Add filters by actor, entity type, action, and date range.

### Milestone 7: Testing and Quality

- Add feature tests for authentication.
- Add feature tests for user permissions.
- Add feature tests for room category CRUD.
- Add feature tests for room CRUD and status changes.
- Add tests for consistent API responses.
- Add tests for soft delete and restore behavior.
- Add tests for audit log creation.

### Milestone 8: Documentation and Handoff

- Document environment setup.
- Document API authentication flow.
- Document API response format.
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
