# Hosting (Drupal 11)

This is the Drupal 11 core Hosting/Hostmaster frontend module.

## Features
- Configurable queue dispatch with cron integration and serial/batch/spread scheduling.
- Context registry entity with optional `hosting/c/<name>` alias management.
- Admin UI for features, queues, and settings.
- Drush commands for dispatch, setup, import, and pause/resume.

## Submodules
- `hosting_task` provides task entities, queue worker, and the `hosting:task` Drush command.
- Other submodules (server, site, platform, package, client) add domain entities.

## Configuration
- Settings live in `hosting.settings`.
- Feature toggles live in `hosting.features`.
- Queue state is tracked in key/value storage (`hosting.queue_state`).

## Drush
- `drush hosting:dispatch`
- `drush hosting:setup`
- `drush hosting:import @example.com`
- `drush hosting:pause`
- `drush hosting:resume`

## Notes
- Backend provisioning integration expects a working Drush backend and is configured via `hosting.settings`.
