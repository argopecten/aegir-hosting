# Contributing to Aegir Hosting

Thank you for your interest in contributing to Aegir Hosting! This document provides guidelines and best practices for contributing to the project.

## Getting Started

1. Fork the repository on GitHub
2. Clone your fork locally
3. Create a feature branch from `main`
4. Make your changes following the guidelines below
5. Test your changes thoroughly
6. Submit a pull request

## Architecture Principles

### Entity-First Architecture

**Key Principle**: All data operations go through Drupal entities. Forms and controllers NEVER directly manipulate Provision contexts.

```php
// ❌ WRONG: Direct context manipulation
$contextRepo->save($context->set('domain', $value));

// ✅ CORRECT: Use entity API
$entity->set('domain', $value);
$entity->save();  // Automatically triggers context sync
```

### Service Layer Pattern

Business logic belongs in manager services, not in forms or controllers.

**Manager Services:**
- `SiteManager`: Site entity business logic
- `PlatformManager`: Platform entity operations
- `ServerManager`: Server entity management
- `TaskManager`: Task lifecycle management

**Example:**
```php
// ❌ WRONG: Business logic in form
public function validateForm(array &$form, FormStateInterface $form_state) {
  $domain = $form_state->getValue('domain');
  if (!filter_var($domain, FILTER_VALIDATE_DOMAIN)) {
    $form_state->setErrorByName('domain', 'Invalid domain');
  }
}

// ✅ CORRECT: Delegate to manager service
public function validateForm(array &$form, FormStateInterface $form_state) {
  $domain = $form_state->getValue('domain');
  if (!$this->siteManager->isDomainValid($domain)) {
    $form_state->setErrorByName('domain', $this->t('Invalid domain'));
  }
}
```

### Context Naming Convention

- Context names are stored **without** the `@` prefix in entities
- The `@` prefix is only added when invoking Drush commands
- Example: Store `example.com`, invoke as `@example.com`

```php
// ❌ WRONG: Store with @ prefix
$entity->set('context_name', '@example.com');

// ✅ CORRECT: Store without @ prefix
$entity->set('context_name', 'example.com');

// ✅ CORRECT: Add @ when invoking backend
$this->backendInvoker->invoke('verify', ['@' . $context_name]);
```

## Code Standards

### Drupal Coding Standards

Follow [Drupal coding standards](https://www.drupal.org/docs/develop/standards) for all PHP, JavaScript, and CSS code.

**Required:**
- Use 2 spaces for indentation
- Follow PSR-4 autoloading
- Use PHP 8+ type declarations
- Document all public methods and classes
- Use dependency injection

### Entity Development

#### 1. Define All Fields
Always use `BaseFieldDefinition` for entity fields:

```php
public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
  $fields = parent::baseFieldDefinitions($entity_type);
  
  $fields['domain'] = BaseFieldDefinition::create('string')
    ->setLabel(t('Domain'))
    ->setDescription(t('The site domain name'))
    ->setRequired(TRUE)
    ->setSettings(['max_length' => 255])
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
  
  return $fields;
}
```

#### 2. Sync Entities to Contexts

Use `postSave()` hook to sync entities with contexts:

```php
public function postSave(EntityStorageInterface $storage, $update = TRUE) {
  parent::postSave($storage, $update);
  
  // Sync to context
  $context_name = $this->get('domain')->value;
  $registry = \Drupal::service('hosting.context_registry');
  $registry->register($context_name, $this->getEntityTypeId(), $this->id());
}
```

#### 3. Queue Tasks for Backend Operations

Never call `BackendInvoker` directly from forms:

```php
// ❌ WRONG: Direct backend invocation
$this->backendInvoker->invoke('install', ['example.com']);

// ✅ CORRECT: Queue through TaskManager
$this->taskManager->createTask($entity, 'install');
```

### Service Development

#### 1. Use Dependency Injection

```php
class SiteManager {
  
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ContextRegistry $contextRegistry,
    protected LoggerChannelInterface $logger,
  ) {}
  
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('hosting.context_registry'),
      $container->get('logger.channel.hosting'),
    );
  }
}
```

#### 2. Return Typed Values

Use PHP 8+ return type declarations:

```php
public function isDomainValid(string $domain): bool {
  return filter_var($domain, FILTER_VALIDATE_DOMAIN) !== FALSE;
}

public function getSiteByDomain(string $domain): ?HostingSite {
  $sites = $this->entityTypeManager
    ->getStorage('hosting_site')
    ->loadByProperties(['domain' => $domain]);
  
  return $sites ? reset($sites) : NULL;
}
```

#### 3. Handle Errors Gracefully

Log errors and throw meaningful exceptions:

```php
public function createSite(array $data): HostingSite {
  try {
    $site = $this->entityTypeManager
      ->getStorage('hosting_site')
      ->create($data);
    $site->save();
    
    $this->logger->info('Created site: @domain', [
      '@domain' => $site->label(),
    ]);
    
    return $site;
  }
  catch (\Exception $e) {
    $this->logger->error('Failed to create site: @message', [
      '@message' => $e->getMessage(),
    ]);
    throw new SiteCreationException('Site creation failed', 0, $e);
  }
}
```

### Form Development

#### 1. Keep Forms Thin

Forms should handle:
- Building form structure
- Validating user input (using manager services)
- Saving entities
- Displaying messages

Forms should NOT contain:
- Business logic
- Direct database queries
- Backend invocations
- Complex calculations

#### 2. Use Form States

Implement dynamic forms with `#states`:

```php
$form['ssl_enabled'] = [
  '#type' => 'checkbox',
  '#title' => $this->t('Enable SSL'),
  '#default_value' => $entity->get('ssl_enabled')->value,
];

$form['ssl_redirect'] = [
  '#type' => 'checkbox',
  '#title' => $this->t('Redirect HTTP to HTTPS'),
  '#default_value' => $entity->get('ssl_redirect')->value,
  '#states' => [
    'visible' => [
      ':input[name="ssl_enabled"]' => ['checked' => TRUE],
    ],
  ],
];
```

## Testing

### Unit Tests

Write unit tests for all manager services:

```php
namespace Drupal\Tests\hosting_site\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\hosting_site\Service\SiteManager;

class SiteManagerTest extends UnitTestCase {
  
  public function testIsDomainValid() {
    $manager = $this->createSiteManager();
    
    $this->assertTrue($manager->isDomainValid('example.com'));
    $this->assertFalse($manager->isDomainValid('invalid domain'));
  }
}
```

### Functional Tests

Write functional tests for entity CRUD operations:

```php
namespace Drupal\Tests\hosting_site\Functional;

use Drupal\Tests\BrowserTestBase;

class HostingSiteTest extends BrowserTestBase {
  
  protected static $modules = ['hosting', 'hosting_site'];
  
  public function testSiteCreation() {
    $site = $this->createSite(['domain' => 'test.example.com']);
    
    $this->assertNotNull($site->id());
    $this->assertEquals('test.example.com', $site->label());
  }
}
```

## Documentation

### Code Comments

- Document all public methods with PHPDoc
- Explain complex logic with inline comments
- Use `@see` to reference related code
- Use `@todo` for planned improvements

```php
/**
 * Validates a domain name.
 *
 * Checks that the domain follows DNS naming conventions and
 * is not already in use by another site.
 *
 * @param string $domain
 *   The domain name to validate.
 *
 * @return bool
 *   TRUE if the domain is valid, FALSE otherwise.
 *
 * @see https://tools.ietf.org/html/rfc1035
 */
public function isDomainValid(string $domain): bool {
  // Implementation
}
```

### Update Documentation

When adding new features, update the relevant documentation:

- [README.md](README.md): Overview and quick reference
- [doc/Home.md](doc/Home.md): Architecture overview
- [doc/hosting-d11.md](doc/hosting-d11.md): System architecture details
- Submodule READMEs for feature-specific documentation

## Pull Request Process

1. **Create a descriptive PR title**: Use conventional commits format
   - `feat: Add site cloning functionality`
   - `fix: Resolve context sync race condition`
   - `docs: Update entity architecture documentation`

2. **Write a clear description**: Explain what, why, and how
   - What problem does this solve?
   - How does it work?
   - Are there breaking changes?
   - Does it require configuration changes?

3. **Link related issues**: Reference GitHub issues with `#123`

4. **Request review**: Tag relevant maintainers

5. **Address feedback**: Respond to all review comments

6. **Update documentation**: Include doc updates in your PR

## Common Pitfalls

### 1. Not Defining Entity Fields

```php
// ❌ WRONG: Adding properties without field definitions
class HostingSite extends ContentEntityBase {
  public $domain;  // This won't work!
}

// ✅ CORRECT: Define via baseFieldDefinitions()
$fields['domain'] = BaseFieldDefinition::create('string')
  ->setLabel(t('Domain'))
  ->setRequired(TRUE);
```

### 2. Skipping Context Sync

```php
// ❌ WRONG: Creating entity without context sync
$site = HostingSite::create(['domain' => 'example.com']);
$site->save();  // Context not created!

// ✅ CORRECT: Implement postSave() hook
public function postSave(EntityStorageInterface $storage, $update = TRUE) {
  parent::postSave($storage, $update);
  $registry = \Drupal::service('hosting.context_registry');
  $registry->register($this->get('domain')->value, 'hosting_site', $this->id());
}
```

### 3. Synchronous Backend Operations

```php
// ❌ WRONG: Executing long-running operations in request
public function submitForm(array &$form, FormStateInterface $form_state) {
  $this->backendInvoker->invoke('install', ['example.com']);
  // This blocks the request for minutes!
}

// ✅ CORRECT: Queue the operation
public function submitForm(array &$form, FormStateInterface $form_state) {
  $this->taskManager->createTask($entity, 'install');
  $this->messenger()->addStatus($this->t('Installation queued.'));
}
```

## Questions?

- **Issues**: [GitHub Issues](https://github.com/argopecten/aegir-hosting/issues)
- **Wiki**: [GitHub Wiki](https://github.com/argopecten/aegir-hosting/wiki)
- **Homepage**: [aegir.hu](https://aegir.hu/)

## License

By contributing to Aegir Hosting, you agree that your contributions will be licensed under the same license as the project (GPL-2.0-or-later).
