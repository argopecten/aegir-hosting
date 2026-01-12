# Task (hosting_task) — Architecture

## Purpose
Defines task node type, queue processing, logging, UI, and Ajax updates.

## Key Responsibilities
- Task creation/queueing/execution.
- Task log storage and display.
- Ajax polling for task status/log updates.
- Task access control and cancellation.

## Data Model
- `hosting_task`: task metadata (`task_type`, `rid`, `task_status`, `executed`, `delta`).
- `hosting_task_arguments`: key/value args per task.
- `hosting_task_log`: task log entries with type, message, error, timestamp.

## Core Functions / APIs
- Queue definition: `hosting_task_hosting_queues()`.
- Task creation: `hosting_add_task()`.
- Queue worker: `hosting_tasks_queue()`.
- Execution: `hosting_task_execute()`.
- Logging: `hosting_task_log()`, `_hosting_task_log_table()`.
- Status helpers: `hosting_task_status_name()`, `hosting_task_status_class()`.

## Forms / UI
- Task confirm, restore, retry, status update forms.
- Ajax list/log endpoints:
  - `hosting_task_ajax_list()`
  - `hosting_task_log_ajax()`
  - `hosting_task_ajax_queue()`

## Views
- Views data + handlers in `task/includes/views`.
- Default view: `hosting_task_list`.

## Drush / Hooks
- Drush integration for status updates: `hosting_task.drush.inc`.
- API hooks in `hosting_task.api.php`:
  - `hook_hosting_tasks()`, `hook_hosting_tasks_alter()`, etc.
