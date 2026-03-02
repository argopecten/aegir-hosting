# Work with ContextRegistry

Inspect, troubleshoot, or extend the ContextRegistry — the bridge between Drupal entities and Drush YAML context aliases.

## How ContextRegistry Works

```
Entity Save → ContextRegistry::syncToContext($entity)
                → Build YAML data from entity fields
                → Write to drush/sites/aegir/{name}.site.yml
                → (Optionally) Run provision:save

Entity Load ← ContextRegistry::syncFromContext($alias)
                ← Read YAML from drush/sites/aegir/
                ← Map YAML fields to entity properties
                ← Create/update entity
```

## YAML ↔ Entity Field Mapping

| YAML Key | Entity Field | Entity Type |
|----------|-------------|-------------|
| `provision.type: server` | — | `HostingServer` |
| `provision.ip` | `ip_addresses` | `HostingServer` |
| `provision.http_service` | ServiceInstance (`http`) | `HostingServer` |
| `provision.type: platform` | — | `HostingPlatform` |
| `provision.root` | `publish_path` | `HostingPlatform` |
| `provision.server` | `web_server` (entity ref) | `HostingPlatform` |
| `provision.type: site` | — | `HostingSite` |
| `provision.uri` | `title` | `HostingSite` |
| `provision.platform` | `platform` (entity ref) | `HostingSite` |
| `provision.db_server` | `db_server` (entity ref) | `HostingSite` |

## Syncing After Entity Changes

```php
// In Hook class (triggered automatically on entity save)
$contextRegistry = \Drupal::service('hosting.context_registry');
$contextRegistry->syncToContext($entity);

// Importing from an existing context
$entity = $contextRegistry->syncFromContext('@server_web2');
```

## YAML File Format

### Server Context (`drush/sites/aegir/server_master.site.yml`)

```yaml
server_master:
  provision:
    context_type: server
    aegir_root: /home/aegir
    remote_host: hostname
    script_user: aegir
    http_service_type: apache
    http_port: 80
    db_service_type: mysql
    db_port: 3306
    master_db_user: aegir_user
    master_db_passwd: password
  host: hostname
  user: aegir
```

### Platform Context (`drush/sites/aegir/platform_d11.site.yml`)

```yaml
platform_d11:
  provision:
    context_type: platform
    aegir_root: /home/aegir
    server: "@server_master"
    web_server: "@server_master"
    root: /var/aegir/platforms/drupal11
  root: /var/aegir/platforms/drupal11
  host: hostname
  user: aegir
```

### Site Context (`drush/sites/aegir/example.com.site.yml`)

```yaml
example.com:
  provision:
    context_type: site
    aegir_root: /home/aegir
    platform: "@platform_d11"
    db_server: "@server_master"
    uri: example.com
    profile: standard
    language: en
  uri: http://example.com
  root: /var/aegir/platforms/drupal11
  host: hostname
  user: aegir
```

## Context Naming Rules

| Context Type | Pattern | Example |
|-------------|---------|---------|
| Server | `server_{sanitized_hostname}` | `server_master` |
| Platform | `platform_{name}` | `platform_d11` |
| Site | domain directly (no prefix) | `example.com` |

**No `@` prefix in database storage.** The `@` is only used when referencing contexts in YAML values and Drush commands.

## Inspection Commands

```bash
# Via Drush (preferred — includes merged data)
drush site:alias @server_master
drush site:alias @example.com
drush site:alias --format=yaml

# Raw YAML file
cat drush/sites/aegir/server_master.site.yml

# All contexts
ls drush/sites/aegir/

# Check via database
drush sql:query "SELECT * FROM hosting_context"
```

## Creating/Updating Contexts via provision:save

```bash
# Server context
drush provision:save @server_master --type=server \
  --data='{"http_service_type":"apache","db_service_type":"mysql"}'

# Platform context
drush provision:save @platform_d11 --type=platform \
  --data='{"root":"/var/aegir/platforms/drupal11","server":"@server_master"}'

# Site context
drush provision:save @example.com --type=site \
  --data='{"platform":"@platform_d11","db_server":"@server_master","uri":"example.com"}'
```

## Context Hierarchy

```
Site (@example.com)
  └── platform: @platform_d11
        └── server: @server_master (HTTP)
  └── db_server: @server_master (DB)
```

References always use `@` prefix in YAML values. Database/PHP code uses canonical name without `@`.

## Troubleshooting

| Problem | Check |
|---------|-------|
| No YAML file after entity save | ContextRegistry hooks registered? `drush cr` needed? |
| Wrong YAML structure | Check field mapping in `ContextRegistry::syncToContext()` |
| "Context not found" in provision | Run `provision:save @context_name` to create it |
| Entity ref not resolved | Check entity is saved before context sync |

Inspect or troubleshoot ContextRegistry as described by the user. Always verify with `drush site:alias` after changes.
