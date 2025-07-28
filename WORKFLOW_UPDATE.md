# GitHub Workflow Update Required

This PR adds PHPStan to the project but cannot automatically update the GitHub workflow due to OAuth scope limitations.

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
1. PHP CS Fixer (code style)
2. PHPStan (static analysis)  
3. PHPUnit (tests)

This ensures code quality and type safety across the entire codebase.
