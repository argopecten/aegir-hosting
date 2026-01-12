# Hosting Module (7.x-3.x) — System Architecture

## Overview
The `hosting` module provides the core frontend for Aegir/Hostmaster in Drupal 7. It defines UI routes, forms, and features management; a frontend/back‑end queue bridge; drush commands; and a small context mapping table used to map Drupal nodes to backend “contexts.” The module coordinates tasks and queue execution but delegates most domain-specific node types and backend provisioning to other modules and the backend Drush/provision layer.

## Scope and Boundaries
**In scope files (core hosting module):**
- `hosting.module`
- `hosting.inc`
- `hosting.features.inc`
- `hosting.queues.inc`
- `hosting.install`
- `hosting.drush.inc`
- `dispatch.hosting.inc`
- `task.hosting.inc`
- `pause.hosting.inc`
- `resume.hosting.inc`
- `hosting.api.php`
- `includes/views/handlers/hosting_field_handler_interval.inc`
- `includes/views/handlers/hosting_field_handler_status.inc`

**Out of scope:** Any sub‑modules (e.g. `hosting_site`, `hosting_task`, `hosting_server`, etc.). The base module **calls into** those via hooks and helper functions but does not define their schemas or node types.

## Core Responsibilities

### 1) UI Routing and Access Control
Defined in `hosting.module` via `hook_menu()` and `hook_menu_alter()`.

Key routes:
- `/admin/hosting` → main Hosting admin UI (`hosting_features_form`)
- `/admin/hosting/features` → feature toggles
- `/admin/hosting/queues` → queue configuration form
- `/admin/hosting/settings` → general hosting settings
- `/hosting/queues` → queue summary/list UI
- `/hosting/js/*` → modalframe/overlay proxy callback
- `/hosting/disabled` and `/hosting/maintenance` → informational pages
- `/hostmaster` → redirect to hostmaster context node

Access control is enforced by standard Drupal permissions plus feature enablement via `hosting_menu_access()`.

### 2) Node Operations Dispatch Layer
`hosting.module` implements a generalized `hosting_nodeapi()` dispatcher:
- Node hooks (insert, update, validate, load, etc.) call `hosting_nodeapi()` which in turn invokes any `MODULE_nodeapi_TYPE_OP` implementations.
- This centralizes hosting-related node behavior while allowing submodules to attach logic.

### 3) Queue System (Frontend/Backend IPC)
`hosting.queues.inc` defines the **queue contract** and scheduling logic.

Key concepts:
- **Queue definition array** (from `hook_hosting_queues()`):
  - `type`: `serial`, `batch`, or `spread`
  - `frequency`, `items`, `enabled`, `max_threads`, `threshold`, etc.
  - `singular`/`plural` labels
- `hosting_get_queues()` merges defaults + module-provided queues + stored configuration and calculates:
  - `calc_frequency`, `calc_items`, `calc_threads`, and `running_items` based on queue type
- `hosting_run_queue()` is the drush callback for each queue; it acquires locks and runs `hosting_<queue>_queue()`.

Queues are dispatched through the **drush `hosting-dispatch`** command (`dispatch.hosting.inc`), which:
- Checks `hosting_dispatch_enabled`
- Iterates queues and launches drush subprocesses (`drush_invoke_process`) for each queue when due
- Uses locks for safety

### 4) Task Execution and Backend Integration
`task.hosting.inc` defines the frontend→backend task pipeline:

- `drush_hosting_task_validate()`:
  - Loads an existing task node or creates a new task from a context + task type
  - Marks task status as processing
  - Prepares `context_options` and `options`
- `drush_hosting_task()`:
  - If `provision_save` is required, calls `provision-save` using `context_options` assembled via `hook_hosting_TASK_OBJECT_context_options()`
  - Invokes backend command (`provision-<task_type>`) using `provision_backend_invoke`
  - Stores backend output into drush context
  - Optionally deletes backend context on `delete` task
- Rollback and post-task hooks:
  - `drush_hosting_task_rollback()` → `hook_hosting_TASK_TYPE_task_rollback`
  - `drush_hosting_post_hosting_task()` → `hook_post_hosting_TASK_TYPE_task`

### 5) Features Management
`hosting.features.inc` is the feature registry + UI:

- `hosting_get_features()` loads `hosting.feature.*.inc` definitions (even from disabled modules) and correlates them with module metadata.
- `hosting_features_form()` builds the admin UI, optionally showing dependencies and role permissions.
- `hosting_features_enable()` and `hosting_features_disable()` toggle features, enable/disable modules, and assign permissions.
- `hosting_feature_rebuild_caches()` runs schema/menu rebuilds and triggers a verify task on Hostmaster.

### 6) Context Mapping (Frontend <-> Backend)
The hosting module tracks a **context name ↔ node ID** mapping in a single table:

- `hosting_context` table (defined in `hosting.install`)
  - `nid` (PK)
  - `name` (unique, length `HOSTING_MAX_ALIAS_LENGTH`)
- API:
  - `hosting_context_register()` creates/updates mapping and path aliases
  - `hosting_context_load()` / `hosting_context_nid()` fetches node or imports if missing
  - `hosting_context_delete()` deletes mapping/alias
  - `hosting_context_name()` returns `@name` for a node

### 7) Views Integration
`hosting.module` exposes Views API in `hosting_views_api()` with handlers in:
- `includes/views/handlers/hosting_field_handler_interval.inc`
  - Formats timestamps using `hosting_format_interval()`
- `includes/views/handlers/hosting_field_handler_status.inc`
  - Configurable output modes for status fields (text/image/class)

## Data Model

### Core Table
- `hosting_context` (`hosting.install`)
  - Purpose: map frontend node IDs to backend context names
  - Keys: `nid` (PK), `name` (unique)

### In-memory Structures
- **Queue definition array** (from `hook_hosting_queues()`):
  - `type`, `frequency`, `items`, `enabled`, `total_items`, `running_items`, etc.
- **Feature definition array** (from `hook_hosting_feature()`):
  - `title`, `description`, `status`, `module`, `node`, `enable`, `disable`, `group`, dependencies
- **Task object** (Drush context):
  - `task_type`, `task_command`, `context_options`, `options`, `args`, `ref` (node)

## Configuration (Variables)

Common variable keys used:
- Queue config: `hosting_queue_<queue>_frequency`, `..._items`, `..._enabled`, `..._last_run`
- Feature status: `hosting_feature_<feature>`
- Dispatch control: `hosting_dispatch_enabled`, `hosting_dispatch_last_run`
- System settings: `hosting_default_profile`, `hosting_ignore_default_profiles`, `hosting_lock_platforms_by_default`, `hosting_platform_automatic_site_import`, `hosting_require_disable_before_delete`, `hosting_delete_force`, `hosting_cron_use_backend`
- Log display: `hosting_task_logs_types_display`

## Forms and UI

### Forms
- Feature toggle UI: `hosting_features_form()` (`/admin/hosting/features`)
- Queue configuration: `hosting_queues_configure()` with theme `hosting_queues_configure`
- General settings: `hosting_settings()`

### Form Alterations
- `hosting_form_alter()` hides core UI fields on hosting node types and adds task‑queued messages via `hosting_form_submit_messages()`.

## Drush Commands

Defined in `hosting.drush.inc`:

- `hosting-dispatch` — run all configured queues
- `hosting-setup` — configure cron + dispatch entry
- `hosting-task` — execute a task by node ID or context
- `hosting-import` — import backend context into frontend node
- `hosting-pause` / `hosting-resume` — front-end migration utilities

## Extension Hooks (Public API)

Documented in `hosting.api.php`:
- `hook_allow_domain($url, $params)`
- `hook_drush_context_import($context, &$node)`
- `hook_hosting_feature()`
- `hook_hosting_queues()`
- `hook_hosting_queues_alter()`
- `hook_hosting_processed_queues_alter()`
- `hook_hosting_TASK_OBJECT_context_options(&$task)`
- `hook_hosting_TASK_TYPE_task_rollback($task, $data)`
- `hook_post_hosting_TASK_TYPE_task($task, $data)`
- `hook_hosting_task_update_status()`
- `hook_hosting_task_guarded_nodes()` (+ alter)
- `hook_hosting_task_dangerous_tasks()` (+ alter)

## Key Execution Flows

### A) Queue Dispatch Cycle
1. Cron runs `hosting-dispatch` (from crontab configured by `hosting_setup`).
2. `dispatch.hosting.inc` checks `hosting_dispatch_enabled`.
3. For each queue, lock and check `calc_frequency`.
4. Launch queue via `drush_invoke_process("hosting-<queue>")`.
5. `hosting_run_queue()` executes `hosting_<queue>_queue()` in the relevant module.

### B) Task Execution (Frontend → Backend)
1. Task node created (usually via UI in submodules) or via `drush_hosting_task_validate()`.
2. `drush_hosting_task()` invokes provision commands.
3. `hook_hosting_TASK_OBJECT_context_options()` prepares backend context payload.
4. Backend executes task (provision), results stored in task log.
5. Rollback/Post hooks triggered if needed.

### C) Context Import
1. `drush_hosting_import()` called with alias.
2. Builds a node of the correct type, calls `hook_drush_context_import()`.
3. Saves node and records mapping in `hosting_context`.

## Notes and Constraints
- The base module defines minimal schema (`hosting_context`) and delegates most domain-specific storage (sites/platforms/tasks) to submodules.
- The queue/dispatch system assumes the backend drush/provision layer and a cron entry managed via the hosting module.
- Some UI behaviors (task queue summaries, task logs) depend on submodule-provided functions and data.
