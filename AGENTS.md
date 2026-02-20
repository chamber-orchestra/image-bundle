# Repository Guidelines

## Project Structure & Module Organization
- Full image processing pipeline as a Symfony bundle under `ChamberOrchestra\ImageBundle`.
- Autoloading is PSR-4 from the package root (no `src/` directory).
- Key directories: `Binary/` (loaders, locators, mime), `Controller/`, `DependencyInjection/` (extension, config, factories), `EventSubscriber/`, `Exception/`, `Imagine/` (cache, data, filter), `Model/`, `Service/`, `Twig/`.
- Tests belong in `tests/` (autoloaded as `Tests\`); tools are in `bin/` (`bin/phpunit`).
- Requirements: PHP 8.5+, `imagine/imagine`, Symfony 8.0 components.

## Build, Test, and Development Commands
- Install dependencies: `composer install`.
- Run the suite: `./bin/phpunit` (uses `phpunit.xml.dist`). Add `--filter ClassNameTest` or `--filter testMethodName` to scope.
- `composer test` is an alias for `vendor/bin/phpunit`.
- Quick lint: `php -l path/to/File.php`; keep code PSR-12 even though no fixer is bundled.

## Coding Style & Naming Conventions
- Follow PSR-12: 4-space indent, one class per file, strict types (`declare(strict_types=1);`).
- Use typed properties and return types; favor `readonly` where appropriate.
- Processors implement `ProcessorInterface` with `getIndexName()` static method.
- Post-processors implement `PostProcessorInterface` and extend `AbstractPostProcessor`.
- Keep constructors light; prefer small, composable services injected via Symfony DI.

## Testing Guidelines
- Use PHPUnit (13.x). Name files `*Test.php` mirroring the class under test.
- Unit tests live in `tests/Unit/` extending `TestCase`.
- Keep tests deterministic; use data providers where appropriate.
- Cover processors, post-processors, cache manager, signer, and filter configuration.

## Commit & Pull Request Guidelines
- Commit messages: short, action-oriented, optionally bracketed scope (e.g., `[fix] handle missing source image`, `[master] bump version`).
- Keep commits focused; avoid unrelated formatting churn.
- Pull requests should include: purpose summary, key changes, test results.
