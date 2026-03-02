# Aegir Hosting Module Context (Codex)

Repository scope: `web/modules/contrib/aegir-hosting`

## What This Repo Owns

- Drupal entities, forms, controllers, and services for hosting operations.
- Task orchestration and queue-backed execution through `hosting_task`.
- Frontend-side integration with backend provision commands.

## Core Architecture Rule

Keep operations queue-backed:

`Entity/Form -> TaskManager -> QueueWorker -> BackendInvoker -> provision:*`

UI/form code must not invoke infrastructure commands directly.

## Working Rules

- Breaking changes are acceptable unless user requests compatibility constraints.
- Do not add update hooks unless explicitly requested.
- Use strict typing, dependency injection, and modern Drupal 11 patterns.
- Keep business logic in manager/service classes, not forms/controllers.

## Context Conventions

- Site contexts: canonical hostname-style names (example: `example.com`).
- Platform contexts: `platform_*`.
- Server contexts: `server_*`.
- Store canonical names without `@`; use `@` only in Drush references.

## Practical Risks To Check During Changes

- Task status/type consistency across entity fields and queue handling.
- BackendInvoker interface/usage drift during invocation changes.
- Route and permission mismatches for task forms and entity actions.
