# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

A Symfony bundle for on-demand image processing, caching, and serving. Modelled after LiipImagineBundle. Processes images through named filter pipelines (chains of processors and post-processors), caches results to the filesystem, and serves them via a controller that redirects to the cached URL.

**Package:** `chamber-orchestra/image-bundle`
**Requirements:** PHP ^8.5, `imagine/imagine`, Symfony 8.0 components, `ext-exif`
**Namespace:** `ChamberOrchestra\ImageBundle` (PSR-4 from `src/`)
**Bundle class:** `ChamberOrchestraImageBundle`

## Commands

```bash
# Install dependencies
composer install

# Run all tests
composer test                           # or: vendor/bin/phpunit

# Run specific test file
./vendor/bin/phpunit tests/Unit/Filter/FilterManagerTest.php

# Run tests in specific directory
./vendor/bin/phpunit tests/Unit/Filter/

# Run single test method
./vendor/bin/phpunit --filter testMethodName

# Check code style (dry-run)
composer run-script cs-check

# Auto-fix code style
./vendor/bin/php-cs-fixer fix
```

## Architecture

### Request Flow

1. Request hits `ImageController` (routes defined in `src/Resources/config/routing.yaml`)
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

- PHPUnit 13.x with `failOnRisky` and `failOnWarning`; tests autoloaded as `Tests\`
- Unit tests only, in `tests/Unit/` — use PHPUnit 13 attributes (`#[Test]`)
- CI: GitHub Actions on `master` / `8.0` branches, PHP 8.5

## Code Style

- PHP 8.5+ with strict types (`declare(strict_types=1);`)
- PSR-4 autoloading: `ChamberOrchestra\ImageBundle\` → `src/`
- `@PER-CS` + `@Symfony` PHP-CS-Fixer rulesets
- Native function invocations must be backslash-prefixed (e.g., `\array_merge()`, `\sprintf()`, `\count()`)
- No global namespace imports — never use `use function` or `use const`
- Ordered imports (alpha), no unused imports, single quotes, trailing commas in multiline
- 4-space indent
- Typed properties and return types; favor `readonly`
- Constructor injection only; autowiring and autoconfiguration
- Commit style: short, action-oriented with optional bracketed scope — `[fix] ...`, `[8.0] ...`

## Dependencies

- Requires PHP 8.5, `ext-exif`, `imagine/imagine`, Symfony 8.0 components (`config`, `dependency-injection`, `filesystem`, `http-foundation`, `http-kernel`, `mime`, `options-resolver`, `process`, `routing`), `psr/cache`
- Dev: PHPUnit 13, `php-cs-fixer`, `psr/log`, `twig/twig`
- Main branch is `main`

## Testing Conventions

- Use music thematics for test fixtures and naming (e.g., entity names like `Composition`, `Instrument`, `Rehearsal`, `Score`; file names like `symphony_no_5.pdf`, `violin_concerto.mp3`, `moonlight_sonata.jpg`; prefixes like `scores`, `recordings`)

## Workflow Orchestration

### 1. Plan Mode Default
- Enter plan mode for ANY non-trivial task (3+ steps or architectural decisions)
- If something goes sideways, STOP and re-plan immediately — don't keep pushing
- Use plan mode for verification steps, not just building
- Write detailed specs upfront to reduce ambiguity

### 2. Subagent Strategy
- Use subagents liberally to keep main context window clean
- Offload research, exploration, and parallel analysis to subagents
- For complex problems, throw more compute at it via subagents
- One task per subagent for focused execution

### 3. Self-Improvement Loop
- After ANY correction from the user: update `tasks/lessons.md` with the pattern
- Write rules for yourself that prevent the same mistake
- Ruthlessly iterate on these lessons until mistake rate drops
- Review lessons at session start for relevant project

### 4. Verification Before Done
- Never mark a task complete without proving it works
- Diff behavior between main and your changes when relevant
- Ask yourself: "Would a staff engineer approve this?"
- Run tests, check logs, demonstrate correctness

### 5. Demand Elegance (Balanced)
- For non-trivial changes: pause and ask "is there a more elegant way?"
- If a fix feels hacky: "Knowing everything I know now, implement the elegant solution"
- Skip this for simple, obvious fixes — don't over-engineer
- Challenge your own work before presenting it

### 6. Autonomous Bug Fixing
- When given a bug report: just fix it. Don't ask for hand-holding
- Point at logs, errors, failing tests — then resolve them
- Zero context switching required from the user
- Go fix failing CI tests without being told how

## Task Management

1. **Plan First:** Write plan to `tasks/todo.md` with checkable items
2. **Verify Plan:** Check in before starting implementation
3. **Track Progress:** Mark items complete as you go
4. **Explain Changes:** High-level summary at each step
5. **Document Results:** Add review section to `tasks/todo.md`
6. **Capture Lessons:** Update `tasks/lessons.md` after corrections

## Core Principles

- **Simplicity First:** Make every change as simple as possible. Impact minimal code.
- **No Laziness:** Find root causes. No temporary fixes. Senior developer standards.
- **Minimal Impact:** Changes should only touch what's necessary. Avoid introducing bugs.
