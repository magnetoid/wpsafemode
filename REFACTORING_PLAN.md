# Code Refactoring Plan

## Overview
Comprehensive refactoring to improve code quality, maintainability, and modernize the codebase.

## Refactoring Goals

1. **Remove Global Variables** - Replace with dependency injection and singletons
2. **Add Type Hints** - PHP 7.4+ type declarations
3. **Break Down Large Files** - Split large classes into focused services
4. **Standardize Naming** - Consistent naming conventions
5. **Improve Architecture** - Better separation of concerns
6. **Reduce Duplication** - Extract common patterns

## Phase 1: Core Infrastructure ✅

### Completed
- ✅ Created `core/Config.php` - Centralized configuration management
- ✅ Created `core/Database.php` - Database connection singleton
- ✅ Created `core/Response.php` - Unified response handling
- ✅ Created `core/InputValidator.php` - Input validation utilities
- ✅ Updated `dbModel` to use Config instead of global

### Benefits
- No more global variables
- Centralized configuration
- Reusable response handling
- Consistent input validation

## Phase 2: Controller Refactoring (In Progress)

### Goals
- Extract service classes from DashboardController
- Add type hints to all methods
- Break down large methods
- Remove code duplication

### Services to Extract
1. **PluginService** - Plugin management logic
2. **ThemeService** - Theme management logic
3. **BackupService** - Backup operations
4. **ConfigService** - WordPress configuration management
5. **FileService** - File operations

## Phase 3: Model Refactoring

### Goals
- Split DashboardModel into focused models
- Add type hints
- Improve query methods
- Better error handling

### Models to Create
1. **PluginModel** - Plugin-specific operations
2. **ThemeModel** - Theme-specific operations
3. **BackupModel** - Backup operations
4. **ConfigModel** - Configuration management

## Phase 4: Helper Refactoring

### Goals
- Organize helpers by domain
- Add type hints
- Improve documentation
- Remove duplication

## Phase 5: View Refactoring

### Goals
- Extract view helpers
- Standardize view structure
- Improve template organization

## Naming Conventions

### Classes
- PascalCase: `DashboardController`, `Config`, `InputValidator`

### Methods
- camelCase: `getInput()`, `validateEmail()`, `sanitize()`

### Variables
- camelCase: `$userData`, `$config`, `$response`

### Constants
- UPPER_SNAKE_CASE: `DB_NAME`, `WPSM_API`

## Type Hints

### Return Types
```php
public function getInput(string $name): ?string
public function validateEmail(string $email): bool
public function jsonSuccess(string $message, $data = null): void
```

### Parameter Types
```php
public function sanitize($input, string $type = 'string')
public function set(string $key, $value): void
```

## Code Organization

### Directory Structure
```
/
├── core/           # Core infrastructure classes
├── controller/     # Controllers
├── model/          # Data models
├── service/        # Business logic services
├── view/           # View templates
├── helpers/        # Helper functions
├── security/       # Security classes
└── assets/         # Static assets
```

## Migration Strategy

1. **Backward Compatibility** - Maintain old code while migrating
2. **Gradual Migration** - Refactor one component at a time
3. **Testing** - Test after each refactoring phase
4. **Documentation** - Update docs as we go

## Progress Tracking

- [x] Phase 1: Core Infrastructure
- [ ] Phase 2: Controller Refactoring
- [ ] Phase 3: Model Refactoring
- [ ] Phase 4: Helper Refactoring
- [ ] Phase 5: View Refactoring

---

**Status**: Phase 1 Complete, Phase 2 In Progress
**Date**: $(date)

