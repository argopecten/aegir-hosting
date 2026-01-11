# Hosting Module Forms Development

This document describes the forms that have been developed for the Aegir Hosting modules.

## Overview

Eight new configuration forms have been created for various hosting submodules that previously lacked administrative interfaces. All forms follow Drupal 9/10 best practices and extend `ConfigFormBase` for configuration management.

## Created Forms

### 1. Hosting Cron Settings Form
**Location:** `cron/src/Form/HostingCronSettingsForm.php`

**Purpose:** Configure cron task execution settings

**Features:**
- Default cron interval selection (hourly to weekly)
- Backend cron execution toggle
- Queue processing configuration
- Queue frequency and items per run settings

**Configuration Key:** `hosting_cron.settings`

---

### 2. Hosting Subdirectories Settings Form
**Location:** `subdirs/src/Form/HostingSubdirsSettingsForm.php`

**Purpose:** Manage subdirectory support for sites

**Features:**
- Enable/disable subdirectory support
- Maximum subdirectory depth configuration
- Allowed characters pattern (regex)
- Uniqueness validation toggle
- Reserved subdirectory names list

**Configuration Key:** `hosting_subdirs.settings`

**Validation:** Includes regex pattern validation

---

### 3. Hosting Web Server Settings Form
**Location:** `web_server/src/Form/HostingWebServerSettingsForm.php`

**Purpose:** Configure web server defaults and behavior

**Features:**
- Default web server type selection (Apache, Nginx)
- HTTP and HTTPS port configuration
- Restart command customization
- Configuration file path settings
- Script user configuration
- Configuration backup settings with retention

**Configuration Key:** `hosting_web_server.settings`

---

### 4. Hosting Web Cluster Settings Form
**Location:** `web_cluster/src/Form/HostingWebClusterSettingsForm.php`

**Purpose:** Configure web clustering for distributed sites

**Features:**
- Enable/disable web clustering
- Load balancing method selection
  - Round Robin
  - Least Connections
  - IP Hash
  - Weighted
- Session affinity configuration
- Health check interval and timeout
- File synchronization method (Rsync, NFS, GlusterFS, CephFS)
- Automatic failover toggle

**Configuration Key:** `hosting_web_cluster.settings`

---

### 5. Hosting Web Pack Settings Form
**Location:** `web_pack/src/Form/HostingWebPackSettingsForm.php`

**Purpose:** Manage web server configuration packs

**Features:**
- Enable/disable web packs
- Default pack configuration
- Custom pack creation toggle
- Pack inheritance support
- **Cache Settings:**
  - Enable/disable caching
  - Cache TTL configuration
- **Security Settings:**
  - Security headers toggle
  - Rate limiting toggle

**Configuration Key:** `hosting_web_pack.settings`

---

### 6. Hosting Database Server Settings Form
**Location:** `db_server/src/Form/HostingDbServerSettingsForm.php`

**Purpose:** Configure database server settings

**Features:**
- Default database type (MySQL, MariaDB, PostgreSQL)
- Default port configuration
- Connection timeout settings
- Maximum connections limit
- **Backup Settings:**
  - Enable automatic backups
  - Backup schedule (hourly to monthly)
  - Retention period
  - Compression toggle
- **Security Settings:**
  - SSL requirement toggle
  - Password complexity enforcement
- **Monitoring Settings:**
  - Performance monitoring toggle
  - Slow query threshold configuration

**Configuration Key:** `hosting_db_server.settings`

---

### 7. Hosting Clone Settings Form
**Location:** `clone/src/Form/HostingCloneSettingsForm.php`

**Purpose:** Configure site cloning behavior

**Features:**
- URI validation toggle
- Copy database by default
- Copy files (public and private) by default
- Client ownership preservation
- Platform preservation
- Naming pattern with tokens (%original, %date)
- Clone operation timeout
- Concurrent clone limit
- Pre-clone backup creation
- Email notification toggle

**Configuration Key:** `hosting_clone.settings`

---

### 8. Hosting Task Garbage Collection Settings Form
**Location:** `task_gc/src/Form/HostingTaskGcSettingsForm.php`

**Purpose:** Manage automatic cleanup of old task records

**Features:**
- Enable/disable task garbage collection
- Collection frequency (hourly to weekly)
- **Retention Settings:**
  - Successful tasks retention
  - Failed tasks retention
  - Cancelled tasks retention
- **Task Type Settings:**
  - Clean verify tasks toggle
  - Clean backup tasks toggle
  - Clean migrate tasks toggle
- Batch processing size
- Preserve latest tasks per site
- Deletion logging toggle

**Configuration Key:** `hosting_task_gc.settings`

---

## Implementation Notes

### Form Structure

All forms follow this structure:
```php
<?php

namespace Drupal\{module_name}\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class {FormName} extends ConfigFormBase {
  public function getFormId() { }
  protected function getEditableConfigNames() { }
  public function buildForm(array $form, FormStateInterface $form_state) { }
  public function submitForm(array &$form, FormStateInterface $form_state) { }
}
```

### Form States

Many forms use Drupal's `#states` API for conditional field visibility based on checkbox values.

Example:
```php
'#states' => [
  'visible' => [
    ':input[name="some_checkbox"]' => ['checked' => TRUE],
  ],
],
```

### Validation

Forms include appropriate validation where needed:
- Subdirs form validates regex patterns
- Port numbers are validated with min/max values
- Numeric fields include appropriate constraints

### Configuration Storage

All forms use Drupal's configuration API for persistent storage:
```php
$this->config('{module}.settings')
  ->set('key', $value)
  ->save();
```

## Next Steps

### 1. Register Routes

Each form needs a route definition in the module's `.routing.yml` file:

```yaml
{module}.settings:
  path: '/admin/hosting/{module}/settings'
  defaults:
    _form: '\Drupal\{module}\Form\{FormClass}'
    _title: '{Module} Settings'
  requirements:
    _permission: 'administer hosting'
```

### 2. Add Menu Links

Create menu links in `{module}.links.menu.yml`:

```yaml
{module}.settings:
  title: '{Module} Settings'
  route_name: {module}.settings
  parent: system.admin_config_hosting
  weight: 10
```

### 3. Create Configuration Schema

Define configuration schema in `config/schema/{module}.schema.yml`:

```yaml
{module}.settings:
  type: config_object
  label: '{Module} settings'
  mapping:
    enabled:
      type: boolean
      label: 'Enabled'
    # ... other fields
```

### 4. Set Default Configuration

Create default config in `config/install/{module}.settings.yml`:

```yaml
enabled: false
# ... other default values
```

### 5. Add Permissions

Ensure proper permissions exist in the module's `.permissions.yml` file or use existing hosting permissions.

### 6. Testing

Test each form:
1. Access the form URL
2. Verify all fields display correctly
3. Test form submission
4. Verify configuration is saved
5. Test form states (conditional fields)
6. Test validation rules

## Form Features Summary

| Module | Form Fields | Fieldsets | Validation | States API |
|--------|-------------|-----------|------------|------------|
| cron | 5 | 0 | No | Yes |
| subdirs | 5 | 0 | Yes | Yes |
| web_server | 8 | 0 | No | Yes |
| web_cluster | 7 | 0 | No | Yes |
| web_pack | 8 | 2 | No | Yes |
| db_server | 12 | 3 | No | Yes |
| clone | 11 | 0 | No | Yes |
| task_gc | 11 | 2 | No | Yes |

## Benefits

1. **Consistent UI:** All forms follow Drupal standards
2. **Configuration Management:** Uses Drupal's config API
3. **User-Friendly:** Conditional fields reduce complexity
4. **Validation:** Proper input validation where needed
5. **Extensibility:** Easy to add new fields or options
6. **Documentation:** Well-documented code with descriptions

## Maintenance

When updating forms:
1. Update the configuration schema
2. Provide update hooks if structure changes
3. Update default configuration
4. Test existing configuration migration
5. Update documentation

---

**Created:** January 11, 2026
**Version:** 1.0
**Status:** Implementation Complete
