# Axytos Payment Plugin for JTL Shop

Axytos "Pay Later" payment method integration for JTL Shop 5+. Implements the full payment workflow (precheck → confirmation → invoice → shipping) and provides admin monitoring tools.

## Requirements
- PHP >= 7.4
- Composer
- (Optional) DDEV for local development

## Install
```
composer install
```

## Testing
The project uses PHPUnit 9.6 via Composer.

- Local:
```
vendor/bin/phpunit -c phpunit.xml
```

- Inside container (path may vary):
```
ddev exec -d /var/www/html/shop/plugins/axytos_payment vendor/bin/phpunit -c phpunit.xml
```

### Test Structure
```
tests/
├── bootstrap.php              # Test environment setup (loads Composer autoloader)
├── BaseTestCase.php           # Shared base class for tests (extends PHPUnit TestCase)
├── Unit/                      # Unit tests (no external dependencies)
│   └── ExampleTest.php        # Example PHPUnit tests
└── Integration/               # Integration tests (database, APIs)
    └── (future integration tests)
```

### Writing Tests
- Extend `Tests\\BaseTestCase`
- Use PHPUnit assertions and mocks (e.g., `$this->assertSame()`, `$this->createMock()`)
- Keep integration tests isolated; rollback any DB changes

## Autoloader Notes (PHPUnit)
PHPUnit autoloading is configured via Composer. If autoloading ever breaks, regenerate with:
```
composer dump-autoload -o
```

## Code Quality
- Lint/format (PSR-12):
```
vendor/bin/phpcs --standard=phpcs.xml
```

## Internationalization
Compile translations after updating `.po` files:
```
msgfmt locale/{locale}/base.po -o locale/{locale}/base.mo
# Example
msgfmt locale/en-EN/base.po -o locale/en-EN/base.mo
```

## More Docs
- Developer guidance: `AGENTS.md`
- Data flow overview: `docs/data-flow.md`
