# Hosting Module — Development TODO

## 1. Entity Completion

### 1.1 Complete Entity Field Definitions
- [ ] All 12 entity types need full `baseFieldDefinitions()` with validation constraints
- [ ] Add field formatters and widgets for entity-specific fields
- [ ] Ensure Views integration for all fields

### 1.2 Entity Operations
- [ ] Delete handlers with cascade logic (site→task cleanup)
- [ ] Access control handlers per entity type
- [ ] Bulk operations (enable/disable/delete multiple entities)

---

## 2. Manager Services

### 2.1 Complete Business Logic Layer
- [ ] **SiteManager**: domain validation, status transitions, SSL config
- [ ] **PlatformManager**: path validation, package scanning, version detection
- [ ] **ServerManager**: connectivity tests, service instance management
- [ ] **TaskManager**: task chaining, retry logic, progress tracking

### 2.2 Integration with Backend
- [ ] **BackendInvoker**: error handling improvements, timeout handling
- [ ] **ContextRegistry**: full bidirectional sync on entity CRUD
- [ ] **QueueDispatcher**: batch/spread scheduling verification

---

## 3. Views Integration

- [ ] ViewsData classes for all entity types
- [ ] Default views: site list, platform list, server list, task log
- [ ] Status formatter plugins (icon-based, color-coded)
- [ ] Interval formatter for queue timing
- [ ] Relationship handlers for entity cross-references

---

## 4. Form Improvements

- [ ] AJAX-enabled forms for dynamic field display
- [ ] Inline entity forms for nested entity creation
- [ ] Batch operation forms for multi-entity actions
- [ ] Confirmation forms for destructive operations
- [ ] Form validation delegating to manager services

---

## 5. Feature System

- [ ] Feature plugin/discovery mechanism
- [ ] Dynamic permission assignment per feature
- [ ] Feature dependency resolution
- [ ] Feature enable/disable with module lifecycle hooks

---

## 6. Task Queue Improvements

- [ ] Task chaining (install → verify → enable)
- [ ] Task progress tracking (percentage, log streaming)
- [ ] Task retry with exponential backoff
- [ ] Task cancellation
- [ ] Concurrent task limits per server
- [ ] Task priority levels

---

## 7. Testing

- [ ] PHPUnit tests for all services
- [ ] Kernel tests for entity CRUD
- [ ] Functional tests for forms and routes
- [ ] Drush command tests

---

## 8. Theming

- [ ] Verify all ViewBuilder classes render correctly
- [ ] Test shared component templates across entities
- [ ] Responsive layout testing
- [ ] Accessibility audit (ARIA, keyboard nav)
- [ ] Dark mode CSS variable set

---

## Priority

| Priority | Items |
|----------|-------|
| Critical | Entity field definitions, Manager services, Backend integration |
| High | Task queue improvements, Views integration, Testing |
| Medium | Form improvements, Feature system |
| Low | Advanced theming, Dark mode |
