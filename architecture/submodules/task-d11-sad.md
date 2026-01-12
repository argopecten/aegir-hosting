# Task (Drupal 11) - SAD

## 1. Purpose
Provide task lifecycle management, queue processing, and task logging.

## 2. Drupal 7 Behavior Summary
- Task node type with status constants (queued, processing, success, warning, error).
- Task queue processing and dispatch via cron and drush.
- Task log entries stored in `hosting_task_log` with type/message/error.
- AJAX endpoints for live log tail and queue refresh.
- Task cancel with CSRF token.
- Hooks for dangerous tasks and guarded nodes.

## 3. Drupal 11 Architecture Alignment
- Content entity `hosting_task`.
- Content entity `hosting_task_log`.
- Queue worker `hosting_task` and scheduler service.
- Controller endpoints for log polling and task list rendering (optional JSON).
- Drush commands for dispatch and task creation.

## 4. Components
### 4.1 Entities and Fields
- `hosting_task`
  - `task_type`, `context_name`, `status`, `command`, `args`, `options`
  - `started`, `completed`, `duration`
- `hosting_task_log`
  - `task` (entity ref), `type`, `message`, `error`, `timestamp`

### 4.2 Services
- `TaskManager`
  - create tasks
  - enqueue queue items
  - execute tasks and update status
- `QueueDispatcher`
  - schedule queue runs (cron)
- `TaskLogManager`
  - append log entries and parse final status
- `TaskPolicy`
  - dangerous task checks
  - guarded node checks

### 4.3 Routes/Forms/UI
- Task listing view (`hosting_task_list`).
- Task detail view with log table.
- AJAX route for log tail (polling) and queue refresh.
- Cancel action with CSRF protection.

### 4.4 Views/Reporting
- Default view `hosting_task_list`.
- Handlers for status, type, and log filters.

### 4.5 Drush
- `hosting:dispatch` to run queues.
- `hosting:task` to create tasks by context and type.

## 5. Configuration
- Queue configuration in `hosting.settings`.
- Log display types configuration (optional).

## 6. Permissions
- `administer tasks`, `view task logs`, `cancel own tasks`, `create task`.

## 7. Integration Points
- Server/Platform/Site modules declare tasks and receive post-task hooks.
- Backend invocation via `BackendInvoker` service.
- Guarded nodes (hostmaster) and dangerous tasks list enforced in TaskManager.

## 8. Migration Notes
- Migrate `hosting_task`, `hosting_task_arguments`, `hosting_task_log`.
- Map D7 status integers to D11 enum values.

## 9. Testing
- Kernel tests for queue execution and status transitions.
- Functional tests for log tail endpoint.
