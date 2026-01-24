# Package (Drupal 11) - SAD

## 1. Purpose
Track Drupal packages (profiles/modules/themes) and their instances on platforms.

## 2. Drupal 7 Behavior Summary
- Package node type (hidden from UI).
- Instances stored in `hosting_package_instance` with version, schema_version, status.
- Language availability stored in `hosting_package_languages`.
- Sync API builds instances for platforms/sites/profiles, marking missing as removed.
- Version parsing to numeric code and comparison between platforms.
- Profile filtering for site creation (blocked profiles, default profile).

## 3. Drupal 11 Architecture Alignment
- Content entities: `hosting_package`, `hosting_package_instance`.
- Multi-value field for languages on instance.
- Services: discovery, sync, version compare.
- Config: blocked profiles list, default profile, ignore default profiles.

## 4. Components
### 4.1 Entities and Fields
- `hosting_package`
  - `package_type` (profile/module/theme), `short_name`, `old_short_name`, `description`
- `hosting_package_instance`
  - `platform` (entity ref), `package` (entity ref), `filename`, `version`, `schema_version`, `version_code`, `status`, `platform_ref`
  - `languages` (multi-value string)

### 4.2 Services
- `PackageDiscovery`
  - read platform filesystem and module/theme/profile info
- `PackageInstanceSync`
  - apply discovered packages to instances, remove missing
- `PackageVersionParser`
  - compute `version_code` from version string
- `PackageComparison`
  - compare platform package sets (upgrade/downgrade/missing)

### 4.3 Routes/Forms/UI
- Package entities are not manually created; exposed via views.
- Site form uses package instances for profile/language options.

### 4.4 Views/Reporting
- Default view `hosting_package_list`.
- Custom fields: status, site count, type filters.

## 5. Configuration
- Blocked profiles, default profile, ignore default profiles in config.

## 6. Permissions
- Optional `view package` for read access.

## 7. Integration Points
- Platform verify triggers package sync.
- Site creation uses profile selection from instances.

## 8. Migration Notes
- Migrate `hosting_package`, `hosting_package_instance`, `hosting_package_languages`.
- Map old short names when available.

## 9. Testing
- Kernel tests for discovery and sync.
- Functional tests for profile selection on site form.
