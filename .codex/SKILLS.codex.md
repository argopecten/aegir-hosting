# Aegir Hosting Module Skills (Codex)

Scope: `web/modules/contrib/aegir-hosting` only.

## coding-standards

When: any implementation or review in this repository.

- Use strict typing and modern PHP 8.3+ patterns.
- Prefer `final` by default and fully typed method signatures.
- Use Drupal 11 attribute-style hooks (`#[Hook]`) instead of procedural hooks.
- Use DI in classes; avoid service locators in business logic.

## create-entity

When: adding or extending hosting entities.

- Follow `hosting_*` entity naming and routing conventions.
- Keep entity logic in entity/manager classes, not forms.
- Add list/view builders and permissions where required.

## task-queue

When: implementing background operations.

- Follow pipeline: `Form/Action -> TaskManager -> QueueWorker -> BackendInvoker`.
- Do not call backend provision operations directly from UI forms.
- Add availability guards and task log visibility for operability.

## debug

When: troubleshooting task, context, queue, or backend failures.

- Verify context/alias integrity first.
- Inspect queue status and per-task logs.
- Validate backend command behavior separately before code changes.

## yaml-context

When: editing or repairing context alias YAML for server/platform/site objects.

- Validate canonical naming conventions and expected keys.
- Confirm alias resolution via Drush commands before proceeding.
