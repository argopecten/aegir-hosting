# Aegir Hosting - AI Coding Agent Instructions

## Repository Overview

**Aegir Hosting** is the Drupal 11 frontend for Aegir Hostmaster, providing the entity layer, forms, and task management system for the hosting platform. It defines the canonical data model where all hosting operations originate through Drupal entities that sync to backend Provision contexts.

**Technology Stack**: Drupal 11 (PHP 8.3+), Drush 13, MySQL 8.0+, Ubuntu 24.04 LTS

**Architecture**: Entity-driven with queue-based backend dispatch. Entities (Sites, Platforms, Servers) map to Provision contexts via the ContextRegistry.

## Directory Structure

```
aegir-hosting/
├── src/                          # Core hosting module
│   ├── Entity/
│   │   └── HostingContext.php   # Entity ↔ Context registry
│   ├── Service/
│   │   ├── ContextRegistry.php  # Context synchronization
│   │   ├── BackendInvoker.php   # Drush command execution
│   │   └── QueueDispatcher.php  # Task queue orchestration
│   ├── Commands/
│   │   └── HostingCommands.php  # Drush commands (hosting:*)
│   └── Form/
│       └── HostingSettingsForm.php  # Admin settings
├── hosting_site/                # Site entity module
│   ├── src/
│   │   ├── Entity/
│   │   │   └── HostingSite.php  # Site entity definition
│   │   ├── Form/
│   │   │   └── HostingSiteForm.php  # Site CRUD forms
│   │   ├── Service/
│   │   │   └── SiteManager.php  # Site business logic
│   │   └── Controller/
│   │       └── HostingSiteController.php  # Site pages
│   └── hosting_site.info.yml
├── hosting_platform/            # Platform entity module
│   ├── src/
│   │   ├── Entity/
│   │   │   └── HostingPlatform.php
│   │   ├── Form/
│   │   │   └── HostingPlatformForm.php
│   │   └── Service/
│   │       └── PlatformManager.php
│   └── hosting_platform.info.yml
├── hosting_server/              # Server entity module
│   ├── src/
│   │   ├── Entity/
│   │   │   ├── HostingServer.php
│   │   │   └── HostingServiceInstance.php
│   │   ├── Form/
│   │   │   └── HostingServerForm.php
│   │   └── Service/
│   │       └── ServerManager.php
│   └── hosting_server.info.yml
├── hosting_task/                # Task queue module
│   ├── src/
│   │   ├── Entity/
│   │   │   ├── HostingTask.php
│   │   │   └── HostingTaskLog.php
│   │   ├── Service/
│   │   │   └── TaskManager.php
│   │   ├── Plugin/
│   │   │   └── QueueWorker/
│   │   │       └── HostingTaskQueueWorker.php
│   │   └── Form/
│   │       └── HostingQueueForm.php
│   └── hosting_task.info.yml
├── hosting_client/              # Client entity module
├── hosting_package/             # Package tracking module
├── hosting_db_server/           # DB server specifics
├── hosting_web_server/          # Web server specifics
└── hosting.info.yml             # Main module definition

**Key Principle**: All data operations go through Drupal entities. Forms NEVER directly manipulate Provision contexts.
```

## Core Architecture

### Entity ↔ Context Mapping

The hosting system maintains a bidirectional mapping between Drupal entities and Provision contexts:

```
HostingSite (entity) ↔ @example.com (context)
HostingPlatform (entity) ↔ @platform_d11 (context)
HostingServer (entity) ↔ @server_master (context)
```

**Registry Entity**: [src/Entity/HostingContext.php](src/Entity/HostingContext.php)

```php
/**
 * Defines the HostingContext entity.
 *
 * Links Drupal entities to Provision context names.
 *
 * @ContentEntityType(
 *   id = "hosting_context",
 *   label = @Translation("Hosting Context"),
 *   base_table = "hosting_context",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   },
 * )
 */
class HostingContext extends ContentEntityBase {
  // Fields:
  // - context_name: string (e.g., 'example.com', NO @ prefix)
  // - entity_type: string (e.g., 'hosting_site')
  // - entity_id: integer (Drupal entity ID)
  
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields['context_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Context Name'))
      ->setDescription(t('The name of the context (without @ prefix)'))
      ->setRequired(TRUE);
    
    $fields['entity_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Entity Type'))
      ->setDescription(t('The entity type ID'))
      ->setRequired(TRUE);
    
    $fields['entity_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Entity ID'))
      ->setDescription(t('The entity ID'))
      ->setRequired(TRUE);
    
    return $fields;
  }
}
```

**Current State**:
- ✅ HostingContext entity defined
- ✅ Links entities to context names
- ✅ Creates path aliases (/hosting/c/{context_name})
- ⚠️ No validation of context_name format
- ⚠️ No orphaned context cleanup
- ❌ No context versioning or history

**Future Goals**:
- Add context_name validation (no spaces, special chars)
- Implement orphaned context cleanup (entity deleted but context remains)
- Add context change history tracking
- Support context renaming with migration

### Context Registry Service

**File**: [src/Service/ContextRegistry.php](src/Service/ContextRegistry.php)

Central service for entity ↔ context synchronization:

```php
class ContextRegistry {
  
  /**
   * Register entity with context name.
   *
   * Creates HostingContext record and generates Drush alias YAML.
   *
   * @param string $context_name
   *   Context name WITHOUT @ prefix (e.g., 'example.com')
   * @param string $entity_type
   *   Entity type ID (e.g., 'hosting_site')
   * @param int $entity_id
   *   Entity ID
   */
  public function register(string $context_name, string $entity_type, int $entity_id): void {
    // 1. Load entity to get all properties
    $entity = $this->entityTypeManager
      ->getStorage($entity_type)
      ->load($entity_id);
    
    // 2. Convert entity fields to context properties
    $properties = $this->entityToContextProperties($entity);
    
    // 3. Create/update HostingContext record
    $hosting_context = $this->getOrCreateHostingContext($context_name);
    $hosting_context->set('entity_type', $entity_type);
    $hosting_context->set('entity_id', $entity_id);
    $hosting_context->save();
    
    // 4. Write Drush alias YAML
    $this->writeContextAlias($context_name, $properties);
    
    // 5. Create path alias: /hosting/c/{context_name} → /hosting/{type}/{id}
    $this->createPathAlias($context_name, $entity_type, $entity_id);
  }
  
  /**
   * Unregister context (entity deleted).
   */
  public function unregister(string $context_name): void {
    // Delete HostingContext record
    // Delete Drush alias YAML
    // Delete path alias
  }
  
  /**
   * Get entity from context name.
   */
  public function getEntity(string $context_name): ?ContentEntityInterface {
    $hosting_context = $this->loadByContextName($context_name);
    
    if (!$hosting_context) {
      return NULL;
    }
    
    return $this->entityTypeManager
      ->getStorage($hosting_context->get('entity_type')->value)
      ->load($hosting_context->get('entity_id')->value);
  }
  
  /**
   * Convert entity fields to context properties.
   *
   * Maps entity field names to Provision context property names.
   */
  private function entityToContextProperties(ContentEntityInterface $entity): array {
    $type = $entity->getEntityTypeId();
    
    return match ($type) {
      'hosting_site' => $this->siteEntityToContext($entity),
      'hosting_platform' => $this->platformEntityToContext($entity),
      'hosting_server' => $this->serverEntityToContext($entity),
      default => [],
    };
  }
  
  private function siteEntityToContext(HostingSite $site): array {
    return [
      'type' => 'site',
      'uri' => $site->get('domain')->value,  // domain → uri
      'platform' => $site->get('platform')->entity->get('context_name')->value,
      'db_server' => $site->get('db_server')->entity->get('context_name')->value,
      'db_name' => $site->get('db_name')->value,
      'db_user' => $site->get('db_user')->value,
      'db_passwd' => $site->get('db_passwd')->value,
      'db_host' => $site->get('db_host')->value,
      'db_port' => $site->get('db_port')->value,
      'ssl_enabled' => $site->get('ssl_enabled')->value,
      'ssl_redirect' => $site->get('ssl_redirect')->value,
      'root' => $site->get('platform')->entity->get('publish_path')->value . '/web',
    ];
  }
  
  // Similar methods for platform and server...
}
```

**Current State**:
- ✅ Basic register/unregister functionality
- ✅ Entity to context property mapping
- ✅ Drush alias YAML generation
- ⚠️ Limited error handling
- ⚠️ No transaction support
- ❌ No context diff/merge for updates
- ❌ No batch synchronization for many entities

**Future Goals**:
- Add transactional context sync (rollback on failure)
- Implement context diff to only update changed properties
- Add batch sync command for all entities
- Support bidirectional sync (context changes update entities)
- Add context validation before writing
- Implement context locks for concurrent operations

### Backend Invoker Service

**File**: [src/Service/BackendInvoker.php](src/Service/BackendInvoker.php)

Executes Drush provision commands from PHP:

```php
class BackendInvoker {
  
  /**
   * Execute provision command.
   *
   * @param string $command
   *   Command name without 'provision-' prefix (e.g., 'install', 'verify')
   * @param array $args
   *   Positional arguments (e.g., ['example.com'])
   * @param array $options
   *   Command options (e.g., ['profile' => 'standard'])
   * @param string|null $alias
   *   Optional server alias (e.g., '@server_master')
   *
   * @return array
   *   ['output' => string, 'error' => string, 'exit_code' => int]
   */
  public function invoke(
    string $command,
    array $args = [],
    array $options = [],
    ?string $alias = NULL
  ): array {
    
    // Get configuration
    $drush_path = $this->config->get('hosting.settings.backend.drush_path') ?: 'drush';
    $default_alias = $this->config->get('hosting.settings.backend.alias');
    
    // Build command
    $cmd = [$drush_path];
    
    if ($alias ?? $default_alias) {
      $cmd[] = $alias ?? $default_alias;
    }
    
    $cmd[] = 'provision-' . $command;
    $cmd = array_merge($cmd, $args);
    
    // Add options
    foreach ($options as $key => $value) {
      if ($value === TRUE) {
        $cmd[] = '--' . $key;
      } elseif ($value !== FALSE && $value !== NULL) {
        $cmd[] = '--' . $key . '=' . escapeshellarg($value);
      }
    }
    
    // Execute via Process API
    $process = new Process($cmd);
    $process->setTimeout(3600);  // 1 hour max
    $process->run();
    
    return [
      'output' => $process->getOutput(),
      'error' => $process->getErrorOutput(),
      'exit_code' => $process->getExitCode(),
    ];
  }
  
  /**
   * Execute and throw exception on failure.
   */
  public function invokeOrFail(
    string $command,
    array $args = [],
    array $options = [],
    ?string $alias = NULL
  ): string {
    
    $result = $this->invoke($command, $args, $options, $alias);
    
    if ($result['exit_code'] !== 0) {
      throw new BackendException(
        "Backend command failed: provision-{$command}\n" .
        "Error: {$result['error']}\n" .
        "Output: {$result['output']}"
      );
    }
    
    return $result['output'];
  }
}
```

**Configuration** (in [src/Form/HostingSettingsForm.php](src/Form/HostingSettingsForm.php)):
```php
$form['backend'] = [
  '#type' => 'details',
  '#title' => $this->t('Backend Settings'),
  '#open' => TRUE,
];

$form['backend']['drush_path'] = [
  '#type' => 'textfield',
  '#title' => $this->t('Drush Path'),
  '#default_value' => $config->get('backend.drush_path') ?: 'drush',
  '#description' => $this->t('Path to Drush binary (default: drush)'),
];

$form['backend']['alias'] = [
  '#type' => 'textfield',
  '#title' => $this->t('Default Server Alias'),
  '#default_value' => $config->get('backend.alias'),
  '#description' => $this->t('Optional default server alias (e.g., @server_master)'),
];
```

**Current State**:
- ✅ Basic Drush command execution
- ✅ Configurable Drush path and alias
- ✅ Timeout handling
- ⚠️ No streaming output (waits for completion)
- ⚠️ No progress indicators
- ❌ No SSH support for remote servers
- ❌ No command queuing
- ❌ No retry logic

**Future Goals**:
- Add streaming output for long-running commands
- Implement SSH-based remote execution
- Add command queuing system
- Implement retry logic with exponential backoff
- Add command logging and audit trail
- Support dry-run mode
- Implement command cancellation

## Entity Architecture

### HostingSite Entity

**File**: [hosting_site/src/Entity/HostingSite.php](hosting_site/src/Entity/HostingSite.php)

```php
/**
 * Defines the HostingSite entity.
 *
 * @ContentEntityType(
 *   id = "hosting_site",
 *   label = @Translation("Site"),
 *   handlers = {
 *     "view_builder" = "Drupal\hosting_site\HostingSiteViewBuilder",
 *     "form" = {
 *       "default" = "Drupal\hosting_site\Form\HostingSiteForm",
 *       "add" = "Drupal\hosting_site\Form\HostingSiteForm",
 *       "edit" = "Drupal\hosting_site\Form\HostingSiteForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "hosting_site",
 *   admin_permission = "administer hosting sites",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "domain",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "canonical" = "/hosting/sites/{hosting_site}",
 *     "add-form" = "/hosting/sites/add",
 *     "edit-form" = "/hosting/sites/{hosting_site}/edit",
 *     "delete-form" = "/hosting/sites/{hosting_site}/delete",
 *     "collection" = "/hosting/sites",
 *   },
 * )
 */
class HostingSite extends ContentEntityBase implements HostingSiteInterface {
  
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    
    $fields['domain'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Domain'))
      ->setDescription(t('The site domain name (maps to Provision uri)'))
      ->setRequired(TRUE)
      ->setSettings([
        'max_length' => 255,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    
    $fields['platform'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Platform'))
      ->setDescription(t('The platform this site runs on'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'hosting_platform')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    
    $fields['db_server'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Database Server'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'hosting_server')
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 5])
      ->setDisplayOptions('form', ['type' => 'entity_reference_autocomplete', 'weight' => 5])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    
    $fields['profile'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Install Profile'))
      ->setDescription(t('Drupal installation profile'))
      ->setSetting('target_type', 'hosting_package')
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 10])
      ->setDisplayOptions('form', ['type' => 'entity_reference_autocomplete', 'weight' => 10])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    
    $fields['db_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Database Name'))
      ->setComputed(TRUE)  // Auto-generated from domain
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 15]);
    
    $fields['db_user'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Database User'))
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 16]);
    
    $fields['db_passwd'] = BaseFieldDefinition::create('password')
      ->setLabel(t('Database Password'))
      ->setDescription(t('Generated automatically if not provided'));
    
    $fields['db_host'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Database Host'))
      ->setDefaultValue('localhost')
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 17]);
    
    $fields['db_port'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Database Port'))
      ->setDefaultValue(3306)
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 18]);
    
    $fields['ssl_enabled'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('SSL Enabled'))
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('view', ['type' => 'boolean', 'weight' => 20])
      ->setDisplayOptions('form', ['type' => 'boolean_checkbox', 'weight' => 20])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    
    $fields['ssl_redirect'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Force HTTPS'))
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', ['type' => 'boolean_checkbox', 'weight' => 21])
      ->setDisplayConfigurable('form', TRUE);
    
    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Status'))
      ->setDefaultValue('enabled')
      ->setSetting('allowed_values', [
        'enabled' => 'Enabled',
        'disabled' => 'Disabled',
        'deleted' => 'Deleted',
      ])
      ->setDisplayOptions('view', ['type' => 'list_default', 'weight' => 25])
      ->setDisplayConfigurable('view', TRUE);
    
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 30]);
    
    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 31]);
    
    return $fields;
  }
  
  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);
    
    // Auto-generate database name from domain
    if ($this->get('db_name')->isEmpty()) {
      $db_name = preg_replace('/[^a-z0-9_]/', '_', strtolower($this->get('domain')->value));
      $this->set('db_name', $db_name);
    }
    
    // Auto-generate database user
    if ($this->get('db_user')->isEmpty()) {
      $this->set('db_user', $this->get('db_name')->value . '_user');
    }
    
    // Auto-generate password if not set
    if ($this->get('db_passwd')->isEmpty()) {
      $this->set('db_passwd', user_password(32));
    }
  }
  
  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);
    
    // Sync to context
    $registry = \Drupal::service('hosting.context_registry');
    $registry->register($this->get('domain')->value, 'hosting_site', $this->id());
  }
}
```

**Current State**:
- ✅ Complete field definitions
- ✅ Auto-generation of db_name, db_user, db_passwd
- ✅ Context sync in postSave
- ⚠️ No validation for domain format
- ⚠️ No uniqueness constraint on domain
- ❌ No support for domain aliases
- ❌ No client association
- ❌ No quota tracking

**Future Goals**:
- Add domain validation (valid DNS format)
- Implement domain uniqueness constraint
- Add support for domain aliases (www, etc.)
- Link sites to client entities
- Add disk/database quota fields
- Implement site status workflow (pending → enabled → disabled)
- Add site statistics (visits, size, etc.)

### HostingSite Form

**File**: [hosting_site/src/Form/HostingSiteForm.php](hosting_site/src/Form/HostingSiteForm.php)

```php
class HostingSiteForm extends ContentEntityForm {
  
  protected SiteManager $siteManager;
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->siteManager = $container->get('hosting_site.manager');
    return $instance;
  }
  
  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    
    /** @var \Drupal\hosting_site\Entity\HostingSite $site */
    $site = $this->entity;
    
    // Domain field with validation description
    $form['domain']['widget'][0]['value']['#description'] = $this->t(
      'Enter a valid domain name (e.g., example.com). Do not include http:// or trailing slash.'
    );
    
    // Filter platforms to only show enabled ones
    $form['platform']['widget'][0]['target_id']['#selection_settings']['filter']['status'] = 'enabled';
    
    // Only show DB servers
    $form['db_server']['widget'][0]['target_id']['#selection_settings']['filter']['service_type'] = 'db';
    
    // Hide auto-generated fields in add form
    if ($site->isNew()) {
      $form['db_name']['#access'] = FALSE;
      $form['db_user']['#access'] = FALSE;
      $form['db_passwd']['#access'] = FALSE;
    }
    
    return $form;
  }
  
  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    
    /** @var \Drupal\hosting_site\Entity\HostingSite $site */
    $site = $this->entity;
    
    // Validate domain format
    $domain = $form_state->getValue('domain')[0]['value'];
    $domain = $this->siteManager->normalizeDomain($domain);
    
    if (!$this->siteManager->isDomainValid($domain)) {
      $form_state->setErrorByName('domain', $this->t('Invalid domain format.'));
      return;
    }
    
    // Check domain uniqueness
    if ($this->siteManager->domainExists($domain, $site->id())) {
      $form_state->setErrorByName('domain', $this->t('Domain already exists.'));
      return;
    }
    
    // Update normalized domain
    $form_state->setValue('domain', [['value' => $domain]]);
    
    // Validate platform is enabled
    $platform_id = $form_state->getValue('platform')[0]['target_id'];
    $platform = $this->entityTypeManager->getStorage('hosting_platform')->load($platform_id);
    
    if ($platform && $platform->get('status')->value !== 'enabled') {
      $form_state->setErrorByName('platform', $this->t('Selected platform is not enabled.'));
    }
  }
  
  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $result = parent::save($form, $form_state);
    
    /** @var \Drupal\hosting_site\Entity\HostingSite $site */
    $site = $this->entity;
    
    $message_args = ['%label' => $site->label()];
    $message = $result === SAVED_NEW
      ? $this->t('Created site %label.', $message_args)
      : $this->t('Updated site %label.', $message_args);
    
    $this->messenger()->addStatus($message);
    
    // Queue installation task for new sites
    if ($result === SAVED_NEW) {
      $task_manager = \Drupal::service('hosting_task.manager');
      $task_manager->createTask($site, 'install', [
        'profile' => $site->get('profile')->entity->getName(),
        'client_email' => \Drupal::currentUser()->getEmail(),
      ]);
      
      $this->messenger()->addStatus($this->t('Site installation task queued.'));
    }
    
    $form_state->setRedirectUrl($site->toUrl('collection'));
    
    return $result;
  }
}
```

**Manager Service** ([hosting_site/src/Service/SiteManager.php](hosting_site/src/Service/SiteManager.php)):

```php
class SiteManager {
  
  /**
   * Normalize domain name.
   *
   * Removes protocol, www, trailing slashes, converts to lowercase.
   */
  public function normalizeDomain(string $domain): string {
    // Remove protocol
    $domain = preg_replace('#^https?://#', '', $domain);
    
    // Remove www
    $domain = preg_replace('#^www\.#', '', $domain);
    
    // Remove trailing slash
    $domain = rtrim($domain, '/');
    
    // Lowercase
    $domain = strtolower($domain);
    
    return $domain;
  }
  
  /**
   * Validate domain format.
   */
  public function isDomainValid(string $domain): bool {
    // Basic DNS name validation
    $pattern = '/^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i';
    return (bool) preg_match($pattern, $domain);
  }
  
  /**
   * Check if domain already exists.
   */
  public function domainExists(string $domain, ?int $exclude_id = NULL): bool {
    $query = $this->entityTypeManager
      ->getStorage('hosting_site')
      ->getQuery()
      ->condition('domain', $domain)
      ->accessCheck(FALSE);
    
    if ($exclude_id) {
      $query->condition('id', $exclude_id, '!=');
    }
    
    return (bool) $query->count()->execute();
  }
}
```

**Current State**:
- ✅ Form extends ContentEntityForm properly
- ✅ Manager service for business logic
- ✅ Domain validation and normalization
- ✅ Auto-queue install task on create
- ⚠️ Limited field filtering
- ❌ No AJAX for platform selection (show Drupal version)
- ❌ No client selection
- ❌ No advanced settings (cron key, aliases, etc.)

**Future Goals**:
- Add AJAX callbacks for dynamic field updates
- Implement advanced settings fieldset
- Add client selection with auto-complete
- Support domain alias management
- Add site cloning functionality
- Implement batch site creation
- Add import/export functionality

### HostingPlatform Entity

**File**: [hosting_platform/src/Entity/HostingPlatform.php](hosting_platform/src/Entity/HostingPlatform.php)

```php
/**
 * Defines the HostingPlatform entity.
 *
 * @ContentEntityType(
 *   id = "hosting_platform",
 *   label = @Translation("Platform"),
 *   ...
 * )
 */
class HostingPlatform extends ContentEntityBase implements HostingPlatformInterface {
  
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    
    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setRequired(TRUE)
      ->setSettings(['max_length' => 255])
      ->setDisplayOptions('view', ['label' => 'hidden', 'weight' => -5])
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => -5])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    
    $fields['publish_path'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Publish Path'))
      ->setDescription(t('Absolute path to Drupal root (maps to Provision root)'))
      ->setRequired(TRUE)
      ->setSettings(['max_length' => 1024])
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 0])
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => 0])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    
    $fields['server'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Server'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'hosting_server')
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 5])
      ->setDisplayOptions('form', ['type' => 'entity_reference_autocomplete', 'weight' => 5])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    
    $fields['drupal_version'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Drupal Version'))
      ->setComputed(TRUE)  // Detected by verify task
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 10]);
    
    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Status'))
      ->setDefaultValue('enabled')
      ->setSetting('allowed_values', [
        'enabled' => 'Enabled',
        'disabled' => 'Disabled',
        'locked' => 'Locked',
        'deleted' => 'Deleted',
      ])
      ->setDisplayOptions('view', ['type' => 'list_default', 'weight' => 15])
      ->setDisplayConfigurable('view', TRUE);
    
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'));
    
    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'));
    
    return $fields;
  }
  
  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);
    
    // Sync to context
    $context_name = 'platform_' . $this->id();
    $registry = \Drupal::service('hosting.context_registry');
    $registry->register($context_name, 'hosting_platform', $this->id());
    
    // Queue verify task to scan packages
    if (!$update) {
      $task_manager = \Drupal::service('hosting_task.manager');
      $task_manager->createTask($this, 'verify');
    }
  }
}
```

**Current State**:
- ✅ Basic platform entity
- ✅ Context sync
- ✅ Auto-verify on creation
- ⚠️ No package tracking
- ⚠️ No makefile/composer.json parsing
- ❌ No Git integration
- ❌ No platform deployment workflow
- ❌ No platform migration support

**Future Goals**:
- Implement package entity relationships
- Add Git repository integration
- Support automated platform deployment
- Add platform update workflow
- Implement platform migration (moving sites)
- Add platform validation (Drupal requirements)
- Support composer.lock tracking

### HostingServer Entity

**File**: [hosting_server/src/Entity/HostingServer.php](hosting_server/src/Entity/HostingServer.php)

```php
/**
 * Defines the HostingServer entity.
 *
 * @ContentEntityType(
 *   id = "hosting_server",
 *   label = @Translation("Server"),
 *   ...
 * )
 */
class HostingServer extends ContentEntityBase implements HostingServerInterface {
  
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    
    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setRequired(TRUE);
    
    $fields['hostname'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Hostname'))
      ->setDescription(t('Server hostname or IP (maps to Provision remote_host)'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 0])
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => 0])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    
    $fields['aegir_root'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Aegir Root'))
      ->setDescription(t('Base directory for Aegir (e.g., /var/aegir)'))
      ->setDefaultValue('/var/aegir')
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 5])
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => 5])
      ->setDisplayConfigurable('form', TRUE);
    
    $fields['script_user'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Script User'))
      ->setDescription(t('System user for Aegir operations'))
      ->setDefaultValue('aegir')
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => 10]);
    
    $fields['web_group'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Web Group'))
      ->setDescription(t('Web server group (e.g., www-data)'))
      ->setDefaultValue('www-data')
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => 11]);
    
    // Services handled via HostingServiceInstance entity (one-to-many)
    
    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Status'))
      ->setDefaultValue('enabled')
      ->setSetting('allowed_values', [
        'enabled' => 'Enabled',
        'disabled' => 'Disabled',
        'maintenance' => 'Maintenance',
      ])
      ->setDisplayOptions('view', ['type' => 'list_default', 'weight' => 15]);
    
    return $fields;
  }
}
```

**Service Instance Entity** ([hosting_server/src/Entity/HostingServiceInstance.php](hosting_server/src/Entity/HostingServiceInstance.php)):

```php
/**
 * Defines the HostingServiceInstance entity.
 *
 * Represents a service (HTTP, DB) running on a server.
 *
 * @ContentEntityType(
 *   id = "hosting_service_instance",
 *   label = @Translation("Service Instance"),
 *   ...
 * )
 */
class HostingServiceInstance extends ContentEntityBase {
  
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    
    $fields['server'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Server'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'hosting_server');
    
    $fields['service_type'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Service Type'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values', [
        'http' => 'Web Server',
        'db' => 'Database Server',
        'dns' => 'DNS Server',
      ]);
    
    $fields['implementation'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Implementation'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values_function', 'hosting_get_service_implementations');
      // Returns: ['apache' => 'Apache', 'nginx' => 'Nginx'] for http
      //          ['mysql' => 'MySQL', 'pgsql' => 'PostgreSQL'] for db
    
    $fields['enabled'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Enabled'))
      ->setDefaultValue(TRUE);
    
    return $fields;
  }
}
```

**Current State**:
- ✅ Basic server entity
- ✅ Service instance architecture
- ⚠️ No remote server support (SSH)
- ⚠️ No service status checking
- ❌ No service configuration storage
- ❌ No load balancing support
- ❌ No failover configuration

**Future Goals**:
- Implement SSH connection testing
- Add service health monitoring
- Store service-specific config (Apache mods, MySQL settings)
- Support server clustering
- Add load balancer configuration
- Implement failover rules
- Add resource monitoring (CPU, RAM, disk)

## Task Queue System

### HostingTask Entity

**File**: [hosting_task/src/Entity/HostingTask.php](hosting_task/src/Entity/HostingTask.php)

```php
/**
 * Defines the HostingTask entity.
 *
 * @ContentEntityType(
 *   id = "hosting_task",
 *   label = @Translation("Task"),
 *   ...
 * )
 */
class HostingTask extends ContentEntityBase implements HostingTaskInterface {
  
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    
    $fields['context_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Context Name'))
      ->setDescription(t('Context name WITHOUT @ prefix'))
      ->setRequired(TRUE);
    
    $fields['command'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Command'))
      ->setDescription(t('Provision command (e.g., provision-install)'))
      ->setRequired(TRUE);
    
    $fields['task_type'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Task Type'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values', [
        'install' => 'Install',
        'verify' => 'Verify',
        'migrate' => 'Migrate',
        'backup' => 'Backup',
        'restore' => 'Restore',
        'clone' => 'Clone',
        'delete' => 'Delete',
        'enable' => 'Enable',
        'disable' => 'Disable',
      ]);
    
    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Status'))
      ->setDefaultValue('queued')
      ->setSetting('allowed_values', [
        'queued' => 'Queued',
        'processing' => 'Processing',
        'success' => 'Success',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
      ])
      ->setDisplayOptions('view', ['type' => 'list_default', 'weight' => 0]);
    
    $fields['args'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Arguments'))
      ->setDescription(t('Command arguments as key-value pairs'));
    
    $fields['options'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Options'))
      ->setDescription(t('Command options as key-value pairs'));
    
    $fields['started'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Started'))
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 10]);
    
    $fields['completed'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Completed'))
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 11]);
    
    $fields['duration'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Duration'))
      ->setDescription(t('Task duration in seconds'))
      ->setComputed(TRUE);
    
    $fields['exit_code'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Exit Code'));
    
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'));
    
    return $fields;
  }
  
  /**
   * Get duration in seconds.
   */
  public function getDuration(): ?int {
    if ($this->get('started')->isEmpty() || $this->get('completed')->isEmpty()) {
      return NULL;
    }
    
    return $this->get('completed')->value - $this->get('started')->value;
  }
}
```

### Task Manager Service

**File**: [hosting_task/src/Service/TaskManager.php](hosting_task/src/Service/TaskManager.php)

```php
class TaskManager {
  
  /**
   * Create a new task.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface|string $context
   *   Entity object or context name
   * @param string $task_type
   *   Task type (install, verify, migrate, etc.)
   * @param array $args
   *   Optional arguments
   * @param array $options
   *   Optional command options
   *
   * @return \Drupal\hosting_task\Entity\HostingTask
   */
  public function createTask(
    ContentEntityInterface|string $context,
    string $task_type,
    array $args = [],
    array $options = []
  ): HostingTask {
    
    // Get context name
    if ($context instanceof ContentEntityInterface) {
      $context_name = $this->getContextName($context);
    } else {
      $context_name = $context;
    }
    
    // Create task entity
    $task = HostingTask::create([
      'context_name' => $context_name,
      'task_type' => $task_type,
      'command' => 'provision-' . $task_type,
      'args' => $args,
      'options' => $options,
      'status' => 'queued',
    ]);
    $task->save();
    
    // Add to queue
    $queue = $this->queueFactory->get('hosting_task');
    $queue->createItem(['task_id' => $task->id()]);
    
    $this->logger->info('Task @id created: @type for @context', [
      '@id' => $task->id(),
      '@type' => $task_type,
      '@context' => $context_name,
    ]);
    
    return $task;
  }
  
  /**
   * Run a task by ID.
   */
  public function runTaskId(int $task_id): void {
    $task = $this->entityTypeManager->getStorage('hosting_task')->load($task_id);
    
    if (!$task) {
      throw new \RuntimeException("Task {$task_id} not found");
    }
    
    $this->runTask($task);
  }
  
  /**
   * Execute a task.
   */
  public function runTask(HostingTask $task): void {
    // Update status
    $task->set('status', 'processing');
    $task->set('started', time());
    $task->save();
    
    try {
      // Invoke backend command
      $result = $this->backendInvoker->invoke(
        $task->get('task_type')->value,
        [$task->get('context_name')->value],
        $task->get('options')->getValue()[0] ?? []
      );
      
      // Log output
      $this->logTaskOutput($task, $result['output'], $result['error']);
      
      // Update task status
      $task->set('status', $result['exit_code'] === 0 ? 'success' : 'failed');
      $task->set('exit_code', $result['exit_code']);
      $task->set('completed', time());
      $task->save();
      
      $this->logger->info('Task @id completed: @status', [
        '@id' => $task->id(),
        '@status' => $task->get('status')->value,
      ]);
      
    } catch (\Exception $e) {
      // Handle failure
      $task->set('status', 'failed');
      $task->set('completed', time());
      $task->save();
      
      $this->logTaskOutput($task, '', $e->getMessage());
      
      $this->logger->error('Task @id failed: @error', [
        '@id' => $task->id(),
        '@error' => $e->getMessage(),
      ]);
    }
  }
  
  /**
   * Log task output.
   */
  private function logTaskOutput(HostingTask $task, string $output, string $error): void {
    if ($output) {
      HostingTaskLog::create([
        'task_id' => $task->id(),
        'type' => 'output',
        'message' => $output,
      ])->save();
    }
    
    if ($error) {
      HostingTaskLog::create([
        'task_id' => $task->id(),
        'type' => 'error',
        'message' => $error,
      ])->save();
    }
  }
  
  /**
   * Get context name from entity.
   */
  private function getContextName(ContentEntityInterface $entity): string {
    $type = $entity->getEntityTypeId();
    
    return match ($type) {
      'hosting_site' => $entity->get('domain')->value,
      'hosting_platform' => 'platform_' . $entity->id(),
      'hosting_server' => 'server_' . $entity->id(),
      default => throw new \RuntimeException("Unsupported entity type: {$type}"),
    };
  }
}
```

### Queue Worker

**File**: [hosting_task/src/Plugin/QueueWorker/HostingTaskQueueWorker.php](hosting_task/src/Plugin/QueueWorker/HostingTaskQueueWorker.php)

```php
/**
 * Processes hosting tasks.
 *
 * @QueueWorker(
 *   id = "hosting_task",
 *   title = @Translation("Hosting Task Queue"),
 *   cron = {"time" = 60}
 * )
 */
class HostingTaskQueueWorker extends QueueWorkerBase {
  
  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $task_id = $data['task_id'];
    
    $task_manager = \Drupal::service('hosting_task.manager');
    $task_manager->runTaskId($task_id);
  }
}
```

### Queue Dispatcher

**File**: [src/Service/QueueDispatcher.php](src/Service/QueueDispatcher.php)

```php
class QueueDispatcher {
  
  /**
   * Dispatch queues (called by cron).
   */
  public function dispatch(): void {
    if (!$this->config->get('hosting.settings.dispatch_enabled')) {
      return;
    }
    
    $queue_manager = \Drupal::service('plugin.manager.queue_worker');
    $queue_factory = \Drupal::service('queue');
    
    // Get hosting_task queue
    $queue = $queue_factory->get('hosting_task');
    $worker = $queue_manager->createInstance('hosting_task');
    
    // Process items
    $max_items = $this->config->get('hosting.settings.queue.hosting_task.items_per_run') ?? 10;
    $processed = 0;
    
    while ($processed < $max_items && ($item = $queue->claimItem())) {
      try {
        $worker->processItem($item->data);
        $queue->deleteItem($item);
        $processed++;
      } catch (\Exception $e) {
        $this->logger->error('Queue item failed: @error', ['@error' => $e->getMessage()]);
        $queue->releaseItem($item);
      }
    }
    
    $this->logger->info('Processed @count task(s)', ['@count' => $processed]);
  }
}
```

**Current State**:
- ✅ Complete task entity and queue system
- ✅ Queue worker plugin
- ✅ Task manager service
- ✅ Task logging
- ⚠️ No task priority
- ⚠️ No task cancellation
- ⚠️ No task dependencies
- ❌ No distributed queue workers
- ❌ No task retry logic
- ❌ No task scheduling (run at specific time)

**Future Goals**:
- Add task priority field and sorting
- Implement task cancellation
- Support task dependencies (task B waits for task A)
- Add distributed queue workers (multiple servers)
- Implement retry logic with exponential backoff
- Add task scheduling (cron-like)
- Implement task chaining (multiple tasks in sequence)
- Add task progress tracking (0-100%)

## Drush Commands

**File**: [src/Commands/HostingCommands.php](src/Commands/HostingCommands.php)

```php
class HostingCommands extends DrushCommands {
  
  /**
   * Setup Aegir hosting system.
   *
   * @command hosting:setup
   * @aliases hsetup
   */
  public function setup(): void {
    // Enable dispatch
    $config = \Drupal::configFactory()->getEditable('hosting.settings');
    $config->set('dispatch_enabled', TRUE);
    $config->save();
    
    $this->io()->success('Hosting dispatch enabled.');
    $this->io()->note('Add to crontab: * * * * * cd ' . DRUPAL_ROOT . ' && drush hosting:dispatch');
  }
  
  /**
   * Dispatch hosting task queue.
   *
   * @command hosting:dispatch
   * @aliases hdispatch
   */
  public function dispatch(): void {
    $dispatcher = \Drupal::service('hosting.queue_dispatcher');
    $dispatcher->dispatch();
  }
  
  /**
   * Sync all entities to contexts.
   *
   * @command hosting:sync-contexts
   * @aliases hsync
   */
  public function syncContexts(): void {
    $registry = \Drupal::service('hosting.context_registry');
    
    // Sync sites
    $sites = \Drupal::entityTypeManager()->getStorage('hosting_site')->loadMultiple();
    foreach ($sites as $site) {
      $registry->register($site->get('domain')->value, 'hosting_site', $site->id());
      $this->io()->writeln("Synced site: {$site->label()}");
    }
    
    // Sync platforms
    $platforms = \Drupal::entityTypeManager()->getStorage('hosting_platform')->loadMultiple();
    foreach ($platforms as $platform) {
      $context_name = 'platform_' . $platform->id();
      $registry->register($context_name, 'hosting_platform', $platform->id());
      $this->io()->writeln("Synced platform: {$platform->label()}");
    }
    
    // Sync servers
    $servers = \Drupal::entityTypeManager()->getStorage('hosting_server')->loadMultiple();
    foreach ($servers as $server) {
      $context_name = 'server_' . $server->id();
      $registry->register($context_name, 'hosting_server', $server->id());
      $this->io()->writeln("Synced server: {$server->label()}");
    }
    
    $this->io()->success('All contexts synced.');
  }
}
```

**Future Commands**:
- `hosting:install-timer` - Generate systemd timer files
- `hosting:install-cron` - Add cron entry
- `hosting:status` - Show system health
- `hosting:queue-status` - Show queue statistics
- `hosting:task-run <id>` - Manually run a task
- `hosting:task-cancel <id>` - Cancel a task
- `hosting:cleanup` - Clean up orphaned contexts

## Anti-Patterns

### ❌ Don't bypass the entity layer
```php
// WRONG: Direct context manipulation
$contextRepo->save($context->set('domain', $value));

// CORRECT: Use entity API
$entity->set('domain', $value);
$entity->save();  // Triggers context sync
```

### ❌ Don't create forms without manager services
```php
// WRONG: Business logic in form
public function validateForm() {
  if (!filter_var($domain, FILTER_VALIDATE_DOMAIN)) { ... }
}

// CORRECT: Delegate to manager service
public function validateForm() {
  if (!$this->siteManager->isDomainValid($domain)) { ... }
}
```

### ❌ Don't reference contexts with @ in entity storage
```php
// WRONG: Store alias format
$entity->set('context_name', '@example.com');

// CORRECT: Store canonical name
$entity->set('context_name', 'example.com');  // No @ prefix
```

### ❌ Don't skip field definitions
```php
// WRONG: Add properties without BaseFieldDefinition
class HostingSite extends ContentEntityBase {
  public $domain;  // No field definition
}

// CORRECT: Define via baseFieldDefinitions()
$fields['domain'] = BaseFieldDefinition::create('string')
  ->setLabel(t('Domain'))
  ->setRequired(TRUE);
```

### ❌ Don't call BackendInvoker directly from forms
```php
// WRONG: Execute backend commands from form
public function submitForm(array &$form, FormStateInterface $form_state) {
  $this->backendInvoker->invoke('install', ['example.com']);
}

// CORRECT: Create task through TaskManager
public function submitForm(array &$form, FormStateInterface $form_state) {
  $this->taskManager->createTask($entity, 'install');
}
```

## System Requirements

**Drupal**: 11.x

**PHP**: 8.3+ with extensions:
- `php8.3-mysql`
- `php8.3-gd`
- `php8.3-curl`
- `php8.3-xml`
- `php8.3-mbstring`

**Database**: MySQL 8.0+ or MariaDB 10.6+

**Operating System**: Ubuntu 24.04 LTS

**Drush**: 13.x

## Future Development Goals

### High Priority

1. **Complete Entity Field Definitions**
   - Add missing fields to all entities
   - Implement computed fields
   - Add field validation

2. **Manager Services**
   - Complete SiteManager, PlatformManager, ServerManager
   - Move business logic out of forms
   - Add domain validation, path validation, etc.

3. **Task System Enhancements**
   - Add task priority
   - Implement task cancellation
   - Add task dependencies
   - Implement progress tracking

4. **Form Improvements**
   - Add AJAX callbacks
   - Implement advanced settings
   - Add batch operations
   - Improve validation messages

5. **Theme Integration**
   - Use `item_info_listing` theme hook consistently
   - Create proper view builders
   - Add entity view modes (full, teaser)
   - Implement template suggestions

### Medium Priority

6. **Client Entity**
   - Implement client entity
   - Link sites to clients
   - Add client permissions
   - Implement client quotas

7. **Package Tracking**
   - Implement package entity
   - Track Drupal core/contrib versions
   - Scan platforms for packages
   - Add update notifications

8. **Remote Servers**
   - Add SSH connection testing
   - Implement remote task execution
   - Add server health monitoring
   - Support distributed architecture

9. **Advanced Features**
   - Site cloning workflow
   - Platform migration workflow
   - Bulk operations (enable/disable/migrate)
   - Import/export functionality

10. **Security & Permissions**
    - Implement granular permissions
    - Add client isolation
    - Implement resource quotas
    - Add audit logging

### Low Priority

11. **API & Integration**
    - REST API for external integrations
    - Webhook support
    - Billing system integration
    - Monitoring system integration

12. **Reporting & Analytics**
    - Site usage statistics
    - Resource usage tracking
    - Task execution metrics
    - Performance dashboards

13. **Automation**
    - Automated backups
    - Automated updates
    - Automated scaling
    - Self-healing infrastructure

## Key Files Reference

- [src/Entity/HostingContext.php](src/Entity/HostingContext.php) - Entity ↔ Context registry
- [src/Service/ContextRegistry.php](src/Service/ContextRegistry.php) - Context synchronization
- [src/Service/BackendInvoker.php](src/Service/BackendInvoker.php) - Backend command execution
- [src/Service/QueueDispatcher.php](src/Service/QueueDispatcher.php) - Task queue orchestration
- [src/Commands/HostingCommands.php](src/Commands/HostingCommands.php) - Drush commands
- [hosting_site/src/Entity/HostingSite.php](hosting_site/src/Entity/HostingSite.php) - Site entity
- [hosting_site/src/Form/HostingSiteForm.php](hosting_site/src/Form/HostingSiteForm.php) - Site form
- [hosting_site/src/Service/SiteManager.php](hosting_site/src/Service/SiteManager.php) - Site business logic
- [hosting_task/src/Entity/HostingTask.php](hosting_task/src/Entity/HostingTask.php) - Task entity
- [hosting_task/src/Service/TaskManager.php](hosting_task/src/Service/TaskManager.php) - Task management
- [hosting_task/src/Plugin/QueueWorker/HostingTaskQueueWorker.php](hosting_task/src/Plugin/QueueWorker/HostingTaskQueueWorker.php) - Queue worker
