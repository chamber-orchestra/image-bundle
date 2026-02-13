# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

A Symfony bundle for on-demand image processing, caching, and serving. Modelled after LiipImagineBundle. Processes images through named filter pipelines (chains of processors and post-processors), caches results to the filesystem, and serves them via a controller that redirects to the cached URL.

**Requirements:** PHP ^8.5, `imagine/imagine`, Symfony 8.0 components, `ext-exif`

**Namespace:** `ChamberOrchestra\ImageBundle` (PSR-4 from package root — no `src/` directory)

## Commands

```bash
composer install                        # Install dependencies
./bin/phpunit                           # Run all tests
./bin/phpunit --filter ClassName        # Run a specific test class
./bin/phpunit --filter testMethodName   # Run a specific test method
composer test                           # Alias for vendor/bin/phpunit
```

## Architecture

### Request Flow

1. Request hits `ImageController` (routes defined in `Resources/config/routing.yaml`)
   - `/_media/cache/resolve/{filter}/{path}` — standard filter
   - `/_media/cache/resolve/{filter}/rc/{hash}/{path}` — runtime filter with HMAC signature
2. `FilterService` checks `CacheManager` for existing cached version
3. If not cached: `DataManager` loads source binary via `LoaderInterface`
4. `FilterManager` applies the filter pipeline:
   - `ProcessorInterface` chain (fit, fill, interlace, strip, optimize, output) — each mutates an `ImageInterface`
   - `PostProcessorInterface` chain (pngquant, mozjpeg, cwebp) — shell out to external binaries
5. `CacheManager` stores result via `ResolverInterface`
6. Controller returns a 301 redirect to the cached URL

### Key Layers

- **Binary/Loading:** `LoaderInterface` (`FileSystemLoader`, `StreamLoader`) → `LocatorInterface` (`FileSystemLocator`, `FileSystemInsecureLocator`) → `BinaryInterface` / `FileBinaryInterface`
- **Filter/Processing:** `FilterConfiguration` holds config; `FilterManager` orchestrates processors and post-processors
- **Cache/Resolver:** `CacheManager` (central), `ResolverInterface` (`WebPathResolver`, `CacheResolver` decorator), `Signer` for runtime URL HMAC
- **Twig:** `ImageExtension` registers filters `image_filter`, `fit`, `fill`, `optimize` via lazy `ImageRuntime`
- **Events:** `FileRemoveSubscriber` cleans cached images when source files are deleted (requires `dev/file-bundle`)

### Extension Points

- Custom resolvers via factory pattern: implement `ResolverFactoryInterface`, register in bundle's `build()`
- Custom loaders via factory pattern: implement `LoaderFactoryInterface`
- Custom processors: implement `ProcessorInterface`, auto-tagged `chamber_orchestra_image.filter.processor`
- Custom post-processors: implement `PostProcessorInterface`, auto-tagged `chamber_orchestra_image.filter.post_processor`

### Configuration

Config tree under `chamber_orchestra_image`:
- `driver`: Gd, Imagick, or Gmagick
- `filters`: named filter definitions with `output`, `processors`, `post_processors`
- `resolvers`: named resolver configs
- `loaders`: named loader configs
- `default_image`: fallback image path

## Testing

- PHPUnit 13.x; tests in `tests/` autoloaded as `Tests\`
- Unit tests in `tests/Unit/` extend `TestCase`

## Code Conventions

- PSR-12, `declare(strict_types=1)`, 4-space indent
- Typed properties and return types; favor `readonly`
- Constructor injection only; autowiring and autoconfiguration
- Commit style: short, action-oriented with optional bracketed scope — `[fix] ...`, `[master] ...`
