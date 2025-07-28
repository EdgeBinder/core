# GitHub Workflow Update Required

This PR adds PHPStan to the project but cannot automatically update the GitHub workflow due to OAuth scope limitations.

## ⚠️ IMPORTANT: PHPStan Missing from CI

The current lint workflow only runs PHP CS Fixer but **does not include PHPStan**. This means static analysis is not being performed on PRs, which could allow type safety issues to slip through.

## Manual Update Required

Please manually update `.github/workflows/lint.yaml` to add the PHPStan job:

```yaml
name: Lint

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main, develop ]

jobs:
  php-cs-fixer:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v4
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.3'
        extensions: mbstring, xml, ctype, iconv, intl, pdo, pdo_mysql, dom, filter, gd, iconv, json, mbstring, pdo
        coverage: none
    
    - name: Cache Composer packages
      id: composer-cache
      uses: actions/cache@v3
      with:
        path: vendor
        key: ${{ runner.os }}-php-${{ hashFiles('**/composer.lock') }}
        restore-keys: |
          ${{ runner.os }}-php-
    
    - name: Install dependencies
      run: composer install --prefer-dist --no-progress
    
    - name: Run PHP CS Fixer
      run: composer run-script cs-check

  phpstan:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v4
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.3'
        extensions: mbstring, xml, ctype, iconv, intl, pdo, pdo_mysql, dom, filter, gd, iconv, json, mbstring, pdo
        coverage: none
    
    - name: Cache Composer packages
      id: composer-cache
      uses: actions/cache@v3
      with:
        path: vendor
        key: ${{ runner.os }}-php-${{ hashFiles('**/composer.lock') }}
        restore-keys: |
          ${{ runner.os }}-php-
    
    - name: Install dependencies
      run: composer install --prefer-dist --no-progress
    
    - name: Run PHPStan
      run: composer run-script phpstan
```

## What This Adds

- **PHPStan Job**: Runs static analysis on all PRs and pushes
- **Level 8 Analysis**: Maximum strictness for type safety
- **Composer Integration**: Uses `composer run-script phpstan` for consistency
- **Same Environment**: Uses PHP 8.3 like other jobs

## After Update

Once the workflow is updated, all PRs will automatically run:
1. **PHP CS Fixer** (code style) ✅ Currently running
2. **PHPStan** (static analysis) ❌ **MISSING - needs to be added**
3. **PHPUnit** (tests) ✅ Currently running

This ensures code quality and type safety across the entire codebase.

## Why This Matters

Without PHPStan in CI:
- Type safety issues can be merged
- Interface contract violations may go unnoticed
- Code quality standards are not enforced
- Potential runtime errors from type mismatches

**Please add PHPStan to the workflow as soon as possible to maintain code quality standards.**
