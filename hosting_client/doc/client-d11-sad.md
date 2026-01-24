# Client (Drupal 11) - SAD

## 1. Purpose
Provide multi-client support: client records, user association, platform access, and access control to hosting objects.

## 2. Drupal 7 Behavior Summary
- Node type `client` with machine name (`uname`) and optional auto-user creation.
- `hosting_client_user` table maps users to clients.
- Node grants allow clients to view/edit their sites/platforms/servers.
- User profile form altered to assign clients; autocomplete endpoint.
- Platform access restrictions stored in `hosting_platform_client_access`.
- Client prefix and registration settings in variables.

## 3. Drupal 11 Architecture Alignment
- Content entity `hosting_client` replaces node type.
- Relation entity `hosting_client_user` (user-client membership).
- Relation entity `hosting_platform_access` (client-platform mapping).
- Access control handler for client entity and policy service for related entities.
- Config API for client prefix and auto-registration settings.

## 4. Components
### 4.1 Entities and Fields
- `hosting_client`
  - `name`, `uname`, `owner`, `status`
- `hosting_client_user`
  - `user`, `client`, `contact_type`
- `hosting_platform_access`
  - `client`, `platform`

### 4.2 Services
- `ClientManager`
  - create, validate unique `uname`, sanitize names
- `ClientAccessManager`
  - provides access checks and entity query conditions

### 4.3 Routes/Forms/UI
- Client entity form with machine name widget.
- User profile form alter to manage client memberships.
- Client platform access form to allow/deny platforms.
- Config form for client prefix and registration options.
- Autocomplete route for client lookup.

### 4.4 Views/Reporting
- Default view `hosting_client_list`.
- Optional views for client-owned sites.

## 5. Configuration
- Client prefix, auto-registration toggle, default client in config.

## 6. Permissions
- `administer clients`, `create client`, `view client`, `edit own client`, `delete own client`, `edit client users`, `edit client uname`.

## 7. Integration Points
- Site and platform modules apply client access constraints.
- Task module should enforce client ownership on task creation.

## 8. Migration Notes
- Migrate `hosting_client`, `hosting_client_user`, `hosting_platform_client_access` into new entities.

## 9. Testing
- Access tests for client ownership.
- Form tests for user-client assignment.
