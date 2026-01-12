# Package (hosting_package) — Architecture

## Purpose
Tracks Drupal packages (profiles/modules/themes) and instances per platform.

## Key Responsibilities
- Package node lifecycle.
- Package instance CRUD and synchronization.
- Profile selection for site provisioning.

## Data Model
- `hosting_package`: package metadata (`package_type`, `short_name`, `description`, etc.).
- `hosting_package_instance`: package installed on a platform with versions.
- `hosting_package_languages`: language availability per instance.

## Core Functions / APIs
- Package types: `_hosting_package_types()`.
- Profile utilities:
  - `hosting_get_profiles()`
  - `hosting_get_profile_platforms()`
  - `hosting_get_profile_languages()`
- Package/instance loaders:
  - `_hosting_package_load()`
  - `hosting_package_instance_load()`
  - `hosting_package_instances_load()`

## Forms / UI
- No standalone forms; package node is hidden from UI by main hosting module.
- Used in site/platform forms as select options.

## Views
- Views data + handlers in `package/includes/views`.
- Default view: `hosting_package_list`.

## Drush
- `drush_hosting_package_pre_hosting_task()` hook in `hosting_package.drush.inc`.
