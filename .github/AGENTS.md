# AI Agent Quick Reference - aegir-hosting

**Repository**: Frontend Component (Drupal Hosting Modules)  
**Current Location**: `/var/aegir/aegir-2601/web/modules/contrib/aegir-hosting/`  
**GitHub**: https://github.com/argopecten/aegir-hosting  
**Part of**: Aegir Hostmaster (multi-repository project)

## You Are Here

This is the **Frontend Component** - a standalone Git repository that is also a submodule of the main aegir-hostmaster project.

**This component handles**:
- ✅ Drupal entities (HostingSite, HostingPlatform, HostingServer)
- ✅ Forms and validation
- ✅ Task queue system
- ✅ Services (ContextRegistry, BackendInvoker, QueueDispatcher)
- ❌ Infrastructure automation (that's aegir-provision)
- ❌ UI presentation (that's aegir-eldir)

## Development Environment

**Target Platform**: Ubuntu 24.04 LTS or later versions  
**LAMP Stack**:
- Apache 2.4+ with PHP-FPM
- PHP 8.3+
- MySQL 8.0+
- Drupal 11.x

## Development Guidelines

**Critical Rules**:
1. ⚠️ **Breaking changes allowed** - We ignore backward compatibility
2. ⚠️ **No update hooks** - Do NOT create update hooks unless explicitly requested
3. 📝 **Documentation on request only** - Update docs only when specifically asked
4. 🎯 **Modern PHP only** - Use PHP 8.3+ features freely (typed properties, attributes, etc.)
5. 🔄 **Clean slate approach** - Prioritize clean implementation over migration paths

## Essential Documentation

**Start here for this component**:
- **[AI-INSTRUCTIONS.md](AI-INSTRUCTIONS.md)** - Complete technical guide for this component (2000+ lines)
- **[doc/Home.md](../doc/Home.md)** - User-facing documentation

**For cross-component work**:
- **[Parent Repo AI Guide](../../../../.github/AI-AGENT-GUIDE.md)** - Navigation across all 4 repositories
- **[Parent Repo Architecture](../../../../.github/ARCHITECTURE.md)** - Integration architecture

## Related Components

**Backend** (when you need to understand Drush commands):
- Path: `../../drush/Commands/contrib/aegir-provision/`
- AI Docs: [../../../drush/Commands/contrib/aegir-provision/.github/AI-INSTRUCTIONS.md](../../../drush/Commands/contrib/aegir-provision/.github/AI-INSTRUCTIONS.md)

**Theme** (when you need to understand UI rendering):
- Path: `../../themes/contrib/aegir-eldir/`
- AI Docs: [../../../themes/contrib/aegir-eldir/.github/AI-INSTRUCTIONS.md](../../../themes/contrib/aegir-eldir/.github/AI-INSTRUCTIONS.md)

## Quick Navigation

```bash
# Check context
pwd                    # Should show: .../aegir-hosting
git remote -v          # Should show: argopecten/aegir-hosting

# Work in this component
git status             # Shows changes in hosting only
git checkout -b feat   # Creates branch in hosting repo

# Commit workflow
git add . && git commit -m "message"  # Commit in hosting
cd ../../../../                        # Go to parent repo
git add web/modules/contrib/aegir-hosting  # Stage submodule update
git commit -m "Update hosting"         # Commit in parent
```

## Component Boundaries

**You should modify files here when**:
- Adding/changing entity definitions
- Updating forms or validation
- Modifying task queue logic
- Changing ContextRegistry or BackendInvoker
- Adding hosting-specific services

**You should NOT modify files here when**:
- Implementing Drush commands → Use aegir-provision
- Generating Apache configs → Use aegir-provision
- Changing templates/CSS → Use aegir-eldir
- Modifying installation scripts → Use parent repo

## Integration Points

**This component writes**:
- Drush alias YAML: `~/.drush/sites/*.site.yml` (via ContextRegistry)

**This component calls**:
- Backend commands: `drush provision-install @site` (via BackendInvoker)

**This component provides**:
- Render arrays for theme (via entity view builders)

## Key Files

- `src/Service/ContextRegistry.php` - Entity ↔ Context synchronization
- `src/Service/BackendInvoker.php` - Shell execution of Drush commands
- `hosting_site/src/Entity/HostingSite.php` - Site entity
- `hosting_platform/src/Entity/HostingPlatform.php` - Platform entity
- `hosting_task/src/Plugin/QueueWorker/HostingTaskQueueWorker.php` - Task execution

---

**Need more context?** Read [AI-INSTRUCTIONS.md](AI-INSTRUCTIONS.md) for complete technical details.
