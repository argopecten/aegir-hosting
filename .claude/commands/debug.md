# Debug Aegir Hosting Operations

Diagnose and debug operations in aegir-hosting. Investigate the issue described and work through these diagnostic steps systematically.

## Entity and Task Inspection

```bash
# Check task status
drush sql:query "SELECT id, task_type, task_status FROM hosting_task ORDER BY id DESC LIMIT 10"

# View task logs for a specific task
drush sql:query "SELECT * FROM hosting_task_log WHERE task__target_id=123 ORDER BY id"

# Check ContextRegistry sync — verify YAML exists
cat drush/sites/aegir/example.com.site.yml
ls drush/sites/aegir/

# Check entity↔context mapping
drush sql:query "SELECT * FROM hosting_context LIMIT 20"
```

## Task Queue Debugging

```bash
# List all queues
drush queue:list

# Manually run the hosting task queue
drush queue:run hosting_task_queue

# Trigger a task directly via PHP eval
drush php:eval "\Drupal::service('hosting.task_manager')->createTask(\Drupal::entityTypeManager()->getStorage('hosting_site')->load(1), 'verify');"

# Enable/test dispatch
drush hosting:dispatch-enable
drush hosting:dispatch
```

## Backend (Provision) Testing

```bash
# List all available provision commands
drush list --filter=provision

# Test a provision command directly (bypasses queue)
drush provision:verify @example.com

# Full debug output
drush provision:verify @server_master --debug -vvv 2>&1 | tee /tmp/provision-debug.log

# Check Apache config syntax after verify
sudo apache2ctl -t

# List generated vhosts
ls -la /var/aegir/config/apache/vhost.d/
```

## Frontend Inspection

```bash
# Rebuild cache (after code changes)
drush cr

# List enabled hosting modules
drush pm:list --type=module --status=enabled | grep hosting

# Check for pending entity schema changes
drush php:eval "print_r(\Drupal::entityDefinitionUpdateManager()->getChangeSummary());"

# Reinstall a module (no update hooks — clean slate approach)
drush pm:uninstall {module} -y && drush pm:enable {module} -y
```

## PHP Debugging in Code

```php
// Check task status programmatically
$task = \Drupal::entityTypeManager()
    ->getStorage('hosting_task')
    ->load($task_id);
$status = $task->get('task_status')->value;

// Check task logs programmatically
$logs = \Drupal::entityTypeManager()
    ->getStorage('hosting_task_log')
    ->loadByProperties(['task__target_id' => $task_id]);

// Force context sync
$registry = \Drupal::service('hosting.context_registry');
$registry->syncToContext($entity);
```

## Common Failure Points

| Symptom | Cause | Fix |
|---------|-------|-----|
| Task stays `queued` | Cron not running | `drush cron` or check crontab |
| Task goes to `error` | Backend command failed | Check task logs for stderr |
| No YAML file | ContextRegistry didn't sync | Call `syncToContext($entity)` |
| "Context not found" | Missing YAML alias | Run `provision:save` first |
| "Service not registered" | Missing service key | Add `http_service`, `db_service` to YAML |
| Apache won't reload | Syntax error in vhost | `sudo apache2ctl configtest` |
| DB connection refused | Wrong credentials | Check `db_host`, `db_port`, `db_user` keys |
| Permission denied | File ownership | Check `aegir` user owns paths, `www-data` group |
| Route not found | Missing `drush cr` | Always `drush cr` after routing changes |
| Form class not found | Wrong namespace or path | Verify PSR-4 path matches namespace |
| Entity schema error | Schema not updated | Reinstall module (`pm:uninstall` + `pm:enable`) |

## Known Bugs

- **`LockManager.php`** instantiates abstract `ProvisionEvent` directly — PHP Fatal Error. Fix: create concrete `LockEvent`/`UnlockEvent` classes extending `ProvisionEvent`.
- **`BackendInvokerInterface`** only declares `invoke()` but concrete class also has `invokeStreaming()` — interface needs updating.

Diagnose the issue described by the user using the commands above. Run diagnostics, interpret output, and identify the root cause before suggesting fixes.
