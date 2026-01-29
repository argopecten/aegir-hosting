# Hosting Core (Drupal 11) - System Architecture Document

## 1. Purpose
Provide the Hostmaster frontend core for Drupal 11: feature toggles, queue scheduling, admin UI, context registry, and Drush 13 integration for dispatching backend tasks to aegir-provision.

## 2. Drupal 7 Behavior Summary
- `hosting.module` defines admin routes, permissions, blocks, and form alters.
- Node hooks are routed through `hosting_nodeapi()` to per-type handlers.
- Features subsystem discovers `hosting.feature.*.inc` definitions and can enable/disable modules and assign permissions.
- Queue subsystem supports serial/batch/spread scheduling with calculated frequency and items.
- Dispatch command (`hosting-dispatch`) triggers queue runs; cron is installed via `hosting-setup`.
- Context registry table `hosting_context` maps node IDs to backend context names and creates `hosting/c/<name>` path aliases.
- Settings form covers default profile, platform lock behavior, auto import, delete policies, cron method, log type visibility.
- Task queue summary and queue list blocks shown in the UI.
- Views handlers for interval/status formatting.

## 3. Drupal 11 Architecture Alignment
- Config API replaces variables for all settings and feature toggles.
- Content entity `hosting_context` replaces the table for context mapping.
- Queue scheduling implemented as a service (`hosting.queue_dispatcher`) plus cron hook.
- Drush 13 commands with PHP 8 attributes for dispatch/setup/import/pause/resume.
- Feature discovery via module config and feature manager service.
- Views integration via `ViewsData` and formatter plugins.
- Service injection using Drupal's dependency injection container.

## 4. Components
### 4.1 Entities and Storage
- `hosting_context` (content entity)
  - Base table: `hosting_context`
  - Fields:
    - `id` (primary key)
    - `uuid` (UUID)
    - `context_name` (string, required, entity label)
    - `entity_type` (string, required)
    - `entity_id` (integer, required)
  - Admin permission: `administer hosting`

### 4.2 Services (hosting.services.yml)
- `logger.channel.hosting`
  - Dedicated logging channel for hosting operations
  
- `hosting.feature_manager`
  - Class: `Drupal\hosting\Service\FeatureManager`
  - Dependencies: module_handler, config.factory, module_installer, entity_type.manager
  - Discover features and dependencies
  - Enable/disable features and apply role permissions
  
- `hosting.backend_invoker`
  - Class: `Drupal\hosting\Service\BackendInvoker`
  - Dependencies: config.factory, logger.channel.hosting
  - Execute backend Drush commands with alias and options
  
- `hosting.context_registry`
  - Class: `Drupal\hosting\Service\ContextRegistry`
  - Dependencies: entity_type.manager, path.validator, logger.channel.hosting
  - CRUD for context entity and alias creation
  
- `hosting.queue_runner`
  - Class: `Drupal\hosting\Service\QueueRunner`
  - Dependencies: queue, plugin.manager.queue_worker, logger.channel.hosting
  - Run specific queue workers
  
- `hosting.queue_dispatcher`
  - Class: `Drupal\hosting\Service\QueueDispatcher`
  - Dependencies: config.factory, keyvalue, hosting.queue_runner, datetime.time, logger.channel.hosting, database, module_handler
  - Calculate next run times based on config
  - Call QueueWorkers with computed items

### 4.3 Configuration-Based Features
- Features defined in `hosting.features.yml` config
  - `enabled`: array of enabled feature machine names
  
- Queue definitions in `hosting.settings.yml`
  - Queue properties:
    - `label`: Human-readable name
    - `description`: Queue description
    - `type`: serial, batch, or spread
    - `frequency`: seconds between runs
    - `items`: number of items per run
    - `enabled`: boolean
    - `queue_id`: Drupal queue ID
    - `max_threads`: maximum concurrent threads
    - `min_threads`: minimum threads
    - `threshold`: item threshold

### 4.4 Routes/Forms/UI
- Admin routes (hosting.routing.yml):
  - `/hosting` → `HostingFeaturesForm` (Features overview)
  - `/hosting/features` → `HostingFeaturesForm` (Feature toggles)
  - `/hosting/queues` → `HostingQueuesForm` (Queue schedule config)
  - `/hosting/settings` → `HostingSettingsForm` (General settings)
  
- Forms implemented:
  - `Drupal\hosting\Form\HostingFeaturesForm`
  - `Drupal\hosting\Form\HostingQueuesForm`
  - `Drupal\hosting\Form\HostingSettingsForm`
  
- Theme templates:
  - `hosting-queues-table.html.twig`

### 4.5 Views/Reporting
- Views data + formatters:
  - interval formatting (`hosting_format_interval` equivalent)
  - status formatting (text/image/class modes)
- Default views provided by submodules (site/platform/task/etc.)

### 4.6 Drush/CLI (Drush 13 with PHP 8 Attributes)
- **Class**: `Drupal\hosting\Commands\HostingCommands`
- **Commands**:
  - `hosting:dispatch` (alias: `hosting-dispatch`)
    - Dispatch queues based on configured schedules
    - Invokes `QueueDispatcher::dispatch()`
  - `hosting:setup` (alias: `hosting-setup`)
    - Enable dispatch in config
    - Display recommended cron setup
  - `hosting:import` (alias: `hosting-import`)
    - Import backend context into registry
    - Argument: context name (e.g., @example.com)
  - `hosting:pause` (alias: `hosting-pause`)
    - Set `dispatch_enabled` to FALSE
  - `hosting:resume` (alias: `hosting-resume`)
    - Set `dispatch_enabled` to TRUE

## 5. Configuration
### 5.1 hosting.settings.yml
```yaml
dispatch_enabled: true
backend:
  drush_path: /usr/bin/drush
  alias: '@hostmaster'
queues:
  tasks:
    label: 'Tasks'
    description: 'Execute provisioning tasks.'
    type: 'serial'
    frequency: 300
    items: 5
    enabled: true
    queue_id: 'hosting_task'
    max_threads: 6
    min_threads: 1
    threshold: 100
settings:
  default_profile: ''
  default_db_server: null
  ignore_default_profiles: false
  blocked_profiles: []
  require_disable_before_delete: true
  delete_force: false
  lock_platforms_by_default: false
  platform_automatic_site_import: true
  cron_method: 'drush'
  task_logs_types_display:
    - error
    - warning
    - info
    - message
    - notice
    - ok
    - status
    - success
```

### 5.2 hosting.features.yml
```yaml
enabled: []
```

## 6. Permissions (hosting.permissions.yml)
- **administer hosting**
  - Title: 'Administer hosting'
  - Description: 'Configure and manage the hosting system.'
  
- **administer hosting features**
  - Title: 'Administer hosting features'
  - Description: 'Enable or disable Hosting features.'
  
- **administer hosting queues**
  - Title: 'Administer hosting queues'
  - Description: 'Configure queue scheduling and execution.'
  
- **administer hosting settings**
  - Title: 'Administer hosting settings'
  - Description: 'Configure general Hosting settings.'
  
- **access disabled sites**
  - Title: 'Access disabled sites'
  - Description: 'View site disabled pages.'
  
- **access hosting queues**
  - Title: 'Access hosting queues'
  - Description: 'View queue status and listings.'

## 7. Integration Points
- **hosting_task**: Task entities, queue worker, and `hosting:task` Drush command
- **hosting_server**: Server entity and web server services
- **hosting_platform**: Platform entity and deployment management
- **hosting_site**: Site entity and domain management
- **hosting_client**: Client entity and access control
- **hosting_package**: Package registry and profile management
- **hosting_db_server**: Database server configuration
- **hosting_web_server**: Web server configuration

## 8. Hooks and Events
- `hosting_cron()`: Invokes `hosting.queue_dispatcher->dispatch()` on Drupal cron
- `hosting_theme()`: Registers `hosting_queues_table` template

## 9. Entity Architecture Details

### 9.1 Entity ↔ Context Synchronization
The ContextRegistry service handles bidirectional mapping:

**Registration Flow:**
1. Entity created/updated in Drupal
2. `postSave()` hook calls `ContextRegistry::register()`
3. HostingContext entity created/updated
4. Drush alias YAML generated in backend
5. Path alias created at `/hosting/c/{context_name}`

**Naming Convention:**
- Entities store context names **without** `@` prefix
- Backend invocation adds `@` prefix
- Example: Store `example.com`, invoke as `@example.com`

### 9.2 Manager Services Pattern
Business logic is separated into manager services:

**SiteManager** (`hosting_site/src/Service/SiteManager.php`):
- Domain validation and uniqueness checking
- Site status management (enabled/disabled/deleted)
- SSL configuration validation
- Database credential generation

**PlatformManager** (`hosting_platform/src/Service/PlatformManager.php`):
- Platform path validation
- Package discovery and scanning
- Drupal version detection
- Platform status transitions

**ServerManager** (`hosting_server/src/Service/ServerManager.php`):
- Server connectivity testing
- Service instance management
- SSH configuration validation
- Server health monitoring

**TaskManager** (`hosting_task/src/Service/TaskManager.php`):
- Task creation and queuing
- Task lifecycle management
- Task execution coordination
- Error handling and retry logic

### 9.3 Form Architecture
Forms delegate to manager services:

```php
class HostingSiteForm extends ContentEntityForm {
  public function __construct(
    EntityTypeManager $entity_type_manager,
    SiteManager $site_manager  // Injected manager
  ) { }
  
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $domain = $form_state->getValue('domain');
    
    // Delegate validation to manager
    if (!$this->siteManager->isDomainValid($domain)) {
      $form_state->setErrorByName('domain', 'Invalid domain');
    }
  }
}
```

## 10. Best Practices and Anti-Patterns

### ✅ DO:
- Use entity API for all data operations
- Delegate business logic to manager services
- Keep forms thin (validation and rendering only)
- Use dependency injection for services
- Store context names without `@` prefix
- Queue backend operations through TaskManager
- Use ContextRegistry for entity-context sync

### ❌ DON'T:
- Bypass entity layer to manipulate contexts directly
- Put business logic in forms or controllers
- Call BackendInvoker directly from forms
- Store context names with `@` prefix in entities
- Skip field definitions (use BaseFieldDefinition)
- Execute long-running operations synchronously

## 11. Migration from Drupal 7
- Migrate `hosting_context` table to `hosting_context` content entity
- Migrate variable settings to `hosting.settings` config
- Convert feature registry from include files to config-based system
- Update node-based entities to content entities
- Replace legacy nodeapi hooks with entity hooks
- Convert Drush commands from Drush 8 to Drush 13 with PHP 8 attributes

## 11. Migration from Drupal 7
- Migrate `hosting_context` table to `hosting_context` content entity
- Migrate variable settings to `hosting.settings` config
- Convert feature registry from include files to config-based system
- Update node-based entities to content entities
- Replace legacy nodeapi hooks with entity hooks
- Convert Drush commands from Drush 8 to Drush 13 with PHP 8 attributes

## 12. Current Implementation Status
✅ **Completed:**
- Core service architecture (ContextRegistry, BackendInvoker, QueueDispatcher, etc.)
- HostingContext entity with field definitions
- Configuration schema for settings and features
- Drush 13 commands with PHP 8 attributes
- Admin routes and forms
- Permission system
- Cron integration
- Logger channel
- Theme template system

⚠️ **In Progress:**
- Manager service implementations (SiteManager, PlatformManager, etc.)
- Feature plugin/registry system
- Views integration and data handlers
- Queue worker plugins
- Task execution flow
- Entity field validation
- Advanced form features (AJAX, batch operations)

🔮 **Planned:**
- Complete entity field definitions for all entities
- SSH-based remote server execution
- Package entity and tracking system
- Client entity and permissions
- Site cloning and migration workflows
- Platform deployment automation
- Advanced task features (chaining, progress tracking)

## 13. Theming Architecture

### 13.1 Entity View Theming Pattern

All entity views (platform, server, site, task, client) follow a consistent modern pattern:

**ViewBuilder → Preprocess → Template → CSS**

#### ViewBuilder Classes

**Implemented ViewBuilders:**
- `hosting_platform`: `HostingPlatformViewBuilder`
- `hosting_server`: `HostingServerViewBuilder`
- `hosting_site`: `HostingSiteViewBuilder`
- `hosting_task`: `HostingTaskViewBuilder`
- `hosting_client`: `HostingClientViewBuilder`

Each ViewBuilder:
- Sets `#theme` to use custom template
- Adds entity reference (`#hosting_platform`, `#hosting_server`, etc.)
- Sets `#view_mode` for template suggestions
- Attaches entity-specific CSS library

```php
class HostingPlatformViewBuilder extends EntityViewBuilder {
  public function view(EntityInterface $entity, $view_mode = 'full', $langcode = NULL): array {
    $build = [
      '#theme' => 'hosting_platform',
      '#hosting_platform' => $entity,
      '#view_mode' => $view_mode,
    ];
    
    $build['#attached']['library'][] = 'hosting_platform/hosting_platform.entity_view';
    
    return $build;
  }
}
```

Entity annotations specify the ViewBuilder:
```php
 *   handlers = {
 *     "view_builder" = "Drupal\hosting_platform\Entity\HostingPlatformViewBuilder",
 *     "list_builder" = "Drupal\hosting_platform\Entity\HostingPlatformListBuilder",
 *   },
```

#### ListBuilder Classes

**Implemented ListBuilders:**
- `hosting_platform`: `HostingPlatformListBuilder`
- `hosting_task`: `HostingTaskListBuilder`
- `hosting_client`: `HostingClientListBuilder`

ListBuilders provide custom columns and formatting for entity collection pages:
```php
class HostingClientListBuilder extends EntityListBuilder {
  public function buildHeader(): array {
    return [
      'name' => $this->t('Name'),
      'uname' => $this->t('Internal Name'),
      'owner' => $this->t('Owner'),
      'users' => $this->t('Users'),
      'sites' => $this->t('Sites'),
      'status' => $this->t('Status'),
    ] + parent::buildHeader();
  }
}
```

#### Preprocess Functions
Each entity has a `template_preprocess_hosting_*()` function that:
- Extracts entity and view mode from elements
- Builds `$variables['content']` from render element children
- Sets `title_prefix`, `title_suffix`, and `title_attributes`
- Separates sidebar from main content

```php
function template_preprocess_hosting_platform(array &$variables): void {
  $variables['entity'] = $variables['elements']['#hosting_platform'];
  $variables['view_mode'] = $variables['elements']['#view_mode'];
  
  foreach (\Drupal\Core\Render\Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
  
  $variables['title_prefix'] = $variables['elements']['#title_prefix'] ?? [];
  $variables['title_suffix'] = $variables['elements']['#title_suffix'] ?? [];
  $variables['label'] = $variables['entity']->label();
  
  $variables['sidebar'] = $variables['content']['hosting_sidebar'] ?? NULL;
  unset($variables['content']['hosting_sidebar']);
}
```

#### Theme Hook Registration
All entity theme hooks use `'render element' => 'elements'` pattern:

```php
function hosting_platform_theme($existing, $type, $theme, $path): array {
  return [
    'hosting_platform' => [
      'render element' => 'elements',
      'template' => 'hosting-platform',
    ],
    // Component templates use 'variables' pattern
    'hosting_platform_sidebar' => [
      'variables' => ['task_queue' => NULL, 'navigation' => NULL, ...],
      'template' => 'hosting-platform-sidebar',
    ],
  ];
}
```

### 13.2 Template Structure

#### Entity Templates (Semantic HTML5)
```twig
<article{{ attributes.addClass('hosting-platform') }}>
  {{ title_prefix }}
  {% if label %}
    <h2{{ title_attributes }}>{{ label }}</h2>
  {% endif %}
  {{ title_suffix }}
  <div class="hosting-platform-content">
    {{ content }}
  </div>
  {% if sidebar %}
    <aside class="hosting-platform-sidebar">
      {{ sidebar }}
    </aside>
  {% endif %}
</article>
```

#### Component Templates
- **Task Queues**: `<section>` elements with `.hosting-panel` class
- **Navigation**: `<nav>` elements with `.hosting-navigation-section` class
- **Sidebars**: `<div>` containers with `.hosting-sidebar` class
- **Lists**: Semantic sections with conditional title rendering

### 13.3 CSS Architecture

#### Library Files
Each entity has a dedicated `.libraries.yml` file:

```yaml
hosting_platform.entity_view:
  css:
    theme:
      css/hosting-platform.css: {}
```

#### CSS Grid Layout Pattern
All entity views use responsive CSS Grid:

```css
/* Base: Mobile-first single column */
.hosting-platform-view {
  display: grid;
  grid-template-columns: 1fr;
  gap: 2rem;
}

/* Desktop: 2-column layout (content + sidebar) */
@media (min-width: 768px) {
  .hosting-platform-view {
    grid-template-columns: 2fr 1fr;
  }
  
  .hosting-platform-view .hosting-sidebar {
    order: 2;
  }
}
```

#### CSS Variables (Standardized Color Scheme)

All CSS variables are now centralized in `hosting/css/hosting-common.css` and shared across all entity submodules:

```css
:root {
  /* Primary colors */
  --hosting-primary: #0074bd;
  --hosting-primary-hover: #005a9c;
  --hosting-secondary: #5c5c5c;
  
  /* Status colors */
  --hosting-success-bg: #d4edda;
  --hosting-success-text: #155724;
  --hosting-error-bg: #f8d7da;
  --hosting-error-text: #721c24;
  --hosting-warning-bg: #fff3cd;
  --hosting-warning-text: #856404;
  --hosting-info-bg: #cce5ff;
  --hosting-info-text: #004085;
  
  /* Neutral colors */
  --hosting-border: #ddd;
  --hosting-bg-light: #f9f9f9;
  --hosting-panel-bg: #f5f5f5;
  --hosting-text-dark: #333;
  --hosting-text-muted: #666;
  --hosting-shadow: rgba(0, 0, 0, 0.1);
  
  /* Task status colors */
  --hosting-task-queued: #ffc107;
  --hosting-task-processing: #2196f3;
  --hosting-task-success: #4caf50;
  --hosting-task-error: #f44336;
  --hosting-task-warning: #ff9800;
  
  /* Client status colors */
  --hosting-client-active: #4caf50;
  --hosting-client-inactive: #9e9e9e;
}
```

All submodule libraries declare dependency on `hosting/common`:
```yaml
# hosting_platform/hosting_platform.libraries.yml
hosting_platform.entity_view:
  css:
    theme:
      css/hosting-platform.css: {}
  dependencies:
    - hosting/common
```

### 13.4 Component Architecture

#### Shared Components (hosting module)

The hosting module provides shared templates used across all entity submodules:
- `hosting-task-queue.html.twig` - Task queue section component
- `hosting-navigation.html.twig` - Navigation section component
- `hosting-sidebar.html.twig` - Sidebar wrapper component
- `hosting-queues-table.html.twig` - Queue status table

These shared templates are referenced by submodules via the `path` parameter:
```php
'hosting_platform_task_queue' => [
  'variables' => ['title' => NULL, 'list' => NULL, 'attributes' => NULL],
  'template' => 'hosting-task-queue',
  'path' => \Drupal::service('extension.list.module')->getPath('hosting') . '/templates',
],
```

#### Entity-Specific Components

**hosting_platform:**
- `hosting-platform.html.twig` (main entity)
- `hosting-platform-sites-list.html.twig` (platform-specific)
- Uses shared: task-queue, navigation, sidebar

**hosting_server:**
- `hosting-server.html.twig` (main entity)
- `hosting-server-queue-summary.html.twig` (server-specific)
- `hosting-service-status-cell.html.twig` (service status display)
- Uses shared: task-queue, navigation, sidebar

**hosting_site:**
- `hosting-site.html.twig` (main entity)
- Uses shared: task-queue, navigation, sidebar

**hosting_task:**
- `hosting-task.html.twig` (main entity)
- Standalone display with status-based styling

**hosting_client:**
- `hosting-client.html.twig` (main entity)
- Displays associated users and sites

### 13.5 Theme Suggestions

Each entity implements `hook_theme_suggestions_HOOK()` for customization:

```php
function hosting_platform_theme_suggestions_hosting_platform(array $variables) {
  $suggestions = [];
  if (!empty($variables['entity'])) {
    $entity = $variables['entity'];
    $suggestions[] = 'hosting_platform__' . $entity->id();
    if (isset($variables['view_mode'])) {
      $suggestions[] = 'hosting_platform__' . $variables['view_mode'];
    }
  }
  return $suggestions;
}
```

This allows theme overrides like:
- `hosting-platform--full.html.twig` (view mode)
- `hosting-platform--123.html.twig` (specific entity)

### 13.6 Theming Best Practices

✅ **DO:**
- Use `'render element' => 'elements'` for entity templates
- Implement preprocess functions for variable extraction
- Use semantic HTML5 elements (article, section, nav, aside)
- Implement ViewBuilder classes for custom entity rendering
- Implement ListBuilder classes for custom collection displays
- Use CSS Grid for responsive layouts
- Follow mobile-first approach
- Attach libraries in ViewBuilder
- Document all template variables in docblocks
- Leverage shared templates and CSS from hosting/common
- Use standardized CSS variables for colors and spacing

❌ **DON'T:**
- Use old `'variables'` pattern for entity templates
- Put business logic in templates or preprocess
- Skip title_prefix/title_suffix support
- Use inline styles or deprecated HTML
- Bypass ViewBuilder for entity rendering
- Duplicate CSS variables or templates across modules
- Hardcode colors or spacing values

### 13.7 Template Consolidation

The hosting module provides shared components to reduce duplication:

**Shared Templates (`hosting/templates/`):**
- `hosting-task-queue.html.twig` - Reusable task queue component
- `hosting-navigation.html.twig` - Reusable navigation component
- `hosting-sidebar.html.twig` - Reusable sidebar wrapper
- `hosting-queues-table.html.twig` - Queue status table

**Shared CSS (`hosting/css/`):**
- `hosting-common.css` - CSS variables, utility classes, common patterns

**Benefits:**
- Single source of truth for styling
- Easier maintenance and customization
- Consistent UX across all entities
- Reduced code duplication (~900 lines saved)
- Better performance (shared CSS loaded once)

## 14. Dependencies
- **PHP**: >=8.3
- **Drupal Core**: ^11.2
- **Drush**: 13.x
- **Backend**: argopecten/aegir-provision 11.x-dev
- **Theme**: argopecten/aegir-eldir 11.x-dev
