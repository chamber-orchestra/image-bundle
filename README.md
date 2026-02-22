# ChamberOrchestra ImageBundle

[![PHP Composer](https://github.com/chamber-orchestra/image-bundle/actions/workflows/php.yml/badge.svg)](https://github.com/chamber-orchestra/image-bundle/actions/workflows/php.yml)
[![PHPStan](https://img.shields.io/badge/PHPStan-max-brightgreen.svg)](https://phpstan.org/)
[![PHP-CS-Fixer](https://img.shields.io/badge/code%20style-PER--CS%20%2F%20Symfony-blue.svg)](https://cs.symfony.com/)
[![Latest Stable Version](https://img.shields.io/packagist/v/chamber-orchestra/image-bundle.svg)](https://packagist.org/packages/chamber-orchestra/image-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/chamber-orchestra/image-bundle.svg)](https://packagist.org/packages/chamber-orchestra/image-bundle)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP 8.5+](https://img.shields.io/badge/PHP-8.5%2B-777BB4.svg)](https://www.php.net/)
[![Symfony 8.0](https://img.shields.io/badge/Symfony-8.0-000000.svg)](https://symfony.com/)

**On-demand image processing, resizing, AVIF/WebP conversion, and caching for Symfony 8.**

A Symfony 8 bundle for image resizing, cropping, format conversion (AVIF, WebP, PNG, JPEG), and optimisation. Process images on demand through named **filter pipelines** — chains of processors (fit, fill, strip, interlace, optimize, output) and post-processors (avifenc, cwebp, MozJPEG, pngquant). Cache results to the local filesystem or S3-compatible storage and serve them via a controller that issues a `301` redirect to the cached URL. Nginx, Apache, and Caddy can serve cached images directly from disk — zero PHP overhead on cache hits.

Built for PHP 8.5 and Symfony 8. A modern, type-safe alternative to LiipImagineBundle.

---

## Table of contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration) — full reference with all defaults
- [Usage](#usage) — Twig filters, macros, PHP API
- [Processors](#processors) — fit, fill, optimize, strip, interlace, output
- [Post-processors](#post-processors) — avifenc, cwebp, mozjpeg, pngquant
- [Loaders](#loaders) — filesystem, stream, S3
- [Resolvers](#resolvers) — web_path, S3, PSR-6 cache decorator
- [Runtime filters](#runtime-filters) — HMAC-signed URLs, client SDKs (TypeScript, React, Next.js, Vue, Swift, Kotlin)
- [Cache invalidation](#cache-invalidation)
- [Web server configuration](#web-server-configuration) — nginx, Apache, Caddy
- [Extension points](#extension-points)
- [Testing](#testing)
- [License](#license)

---

## Features

- **On-demand image processing** — images are processed at first request and cached permanently
- **Filter pipelines** — compose processors and post-processors per named filter
- **Processors**: `fit`, `fill`, `strip`, `interlace`, `output`, `optimize` — resize, crop, strip metadata, set format
- **Post-processors**: `avifenc` (AVIF), `cwebp` (WebP), `mozjpeg` (JPEG optimisation), `pngquant` (PNG compression)
- **AVIF and WebP support** — convert images to next-gen formats via CLI post-processors or Imagick
- **Runtime filters** — pass processor options at runtime via HMAC-signed URLs
- **Runtime image URLs** — nginx-cacheable `GET /media/{pathHash}/{optionsHash}/{name}.{format}` endpoint with HMAC-validated parameters, usable from any client with the shared secret
- **Loaders**: `filesystem` (with path-traversal protection), `stream` (any PHP stream wrapper), `s3` (S3-compatible storage)
- **Resolvers**: `web_path` (filesystem + public URL), `s3` (S3/MinIO/DigitalOcean Spaces), `cache` (PSR-6 decorator)
- **Twig filters**: `image_filter`, `fit`, `fill`, `optimize`, `avif`
- **Twig macros**: responsive `<picture>` elements with AVIF/WebP/fallback srcsets and CSS background helpers
- **Retina/HiDPI** — built-in pixel density multiplier for 2x and 3x output
- **Async processing** — offload image processing to Symfony Messenger workers (requires `symfony/messenger`)
- **Concurrency control** — limit parallel processing workers via distributed locks (requires `symfony/lock`)
- **Content-addressed caching** — URL changes when the image or options change; safe for `Cache-Control: immutable`
- **Auto cache invalidation** via event subscriber (integrates with `chamber-orchestra/file-bundle`)
- **Imagick, GD, Gmagick** drivers
- **Client SDKs** — TypeScript, React, Next.js, Vue, Swift, and Kotlin signing implementations included

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | `^8.5` |
| Symfony | `8.0.*` |
| imagine/imagine | `^1.3` |
| psr/cache | `^3.0` |
| ext-exif | `*` |
| ext-imagick *(recommended)* | any |

**Optional CLI binaries:** `avifenc`, `cwebp`, `cjpeg` (MozJPEG), `pngquant`
**Optional package:** `aws/aws-sdk-php` (for S3 loader/resolver)

---

## Installation

```bash
composer require chamber-orchestra/image-bundle
```

### Register the bundle

```php
// config/bundles.php
return [
    // ...
    ChamberOrchestra\ImageBundle\ChamberOrchestraImageBundle::class => ['all' => true],
];
```

### Import routes

```yaml
# config/routes/chamber_orchestra_image.yaml
_chamber_orchestra_image:
    resource: '@ChamberOrchestraImageBundle/Resources/config/routing.php'
```

---

## Configuration

### Full reference (all defaults)

```yaml
# config/packages/chamber_orchestra_image.yaml
chamber_orchestra_image:

    # Imagine driver class
    # Allowed: Imagine\Imagick\Imagine | Imagine\Gd\Imagine | Imagine\Gmagick\Imagine
    driver: Imagine\Imagick\Imagine   # default

    # Default resolver name (must match a key under "resolvers")
    resolver: default                 # default

    # Default loader name (must match a key under "loaders")
    loader: default                   # default

    # Fallback image path when the source image cannot be loaded (null = 404)
    default_image: ~                  # default: null

    # Filesystem path where the client endpoint writes cached images
    cache_path: '%kernel.project_dir%/public/media'   # default

    # URL prefix for client endpoint cache paths
    cache_prefix: /media              # default

    # Async image processing via Symfony Messenger
    # true = always async, false = always sync, 'auto' = async when symfony/messenger is installed
    async: auto                       # default

    # Max concurrent image processing workers (0 = unlimited, requires symfony/lock)
    concurrency: 0                    # default

    # PSR-6 cache layer for resolver lookups (wraps resolvers with CacheResolver)
    cache:
        enabled: ~                    # default: null (auto: true in prod, false in debug)
        service: cache.app            # default — any PSR-6 CacheItemPoolInterface service
        lifetime: 3600                # default — cache TTL in seconds

    # Named loaders — retrieve source image binaries
    # When omitted, a "default" filesystem loader is registered automatically
    loaders:
        default:
            type: filesystem          # filesystem | stream | s3

            # --- filesystem loader options ---
            data_root:                # list of directories to search for source images
                - '%kernel.project_dir%/public'   # default
            locator: ChamberOrchestra\ImageBundle\Binary\Locator\FileSystemLocator   # default
            # Alternative: ChamberOrchestra\ImageBundle\Binary\Locator\FileSystemInsecureLocator

            # --- stream loader options ---
            # wrapper_prefix: ''      # prepended to path before file_get_contents()
            # context: ~              # optional PHP stream context service

            # --- s3 loader options (requires chamber-orchestra/file-bundle) ---
            # storage: default        # name of the file-bundle storage

    # Named resolvers — store cached images and resolve browser-accessible URLs
    # When omitted, a "default" web_path resolver is registered automatically
    resolvers:
        default:
            type: web_path            # web_path | s3 | custom

            # --- web_path resolver options ---
            web_root: '%kernel.project_dir%/public'   # default
            cache_prefix: media       # default — subdirectory under web_root

            # --- s3 resolver options (requires aws/aws-sdk-php) ---
            # bucket: my-bucket       # required
            # region: us-east-1       # default
            # endpoint: ~             # optional: https://minio.example.com
            # uri_prefix: ~           # optional: https://cdn.example.com
            # cache_prefix: media     # default — S3 key prefix

            # --- custom resolver options ---
            # service: App\MyResolver # service ID implementing ResolverInterface

    # Named filter pipelines
    filters:

        # The "default" filter is used by Twig convenience filters: fit(), fill(), optimize(), avif()
        # It must be defined if you use those filters
        default:
            output:
                quality: 80
                optimize: false
                flatten: true
            processors:
                interlace:
                    mode: partition   # best progressive rendering
                strip: {}             # remove EXIF, ICC profiles
            post_processors:
                mozjpeg:
                    quality: 80       # good JPEG compression with minimal visual loss
                pngquant:
                    quality: '65-80'  # aggressive but clean PNG quantisation
                cwebp:
                    quality: 78       # WebP sweet spot: ~30-40% smaller than JPEG at similar quality
                avifenc:
                    quality: 60       # AVIF is efficient — lower values still look great

        score_thumbnail:              # filter name (used in Twig: image_filter('score_thumbnail'))

            # Override resolver/loader for this filter (null = use global default)
            resolver: ~               # default: null (inherits from global "resolver")
            loader: ~                 # default: null (inherits from global "loader")

            # Per-filter HMAC secret (null = use kernel.secret / APP_SECRET)
            secret: ~                 # default: null

            # Expose this filter to external clients via the /media/ endpoint
            # Requires an explicit "secret" different from APP_SECRET
            exposed: false            # default

            # Override global async setting for this filter (null = use global "async")
            async: ~                  # default: null

            # Fallback image when source is missing (null = use global "default_image")
            default_image: ~          # default: null

            # Output options — control format, quality, and encoding
            output:
                quality: 75           # default — quality for all formats (1-100)
                jpeg_quality: ~       # default: null — override quality for JPEG
                png_compression_level: ~   # default: null
                png_compression_filter: ~  # default: null
                webp_quality: ~       # default: null — override quality for WebP
                avif_quality: ~       # default: null — override quality for AVIF
                format: ~             # default: null — force output format (png|jpg|webp|avif|gif|tiff|bmp)
                optimize: false       # default — enable optimisation
                flatten: true         # default — flatten alpha to background colour
                animated: false       # default — preserve animation frames

            # Processor pipeline — applied in order to the Imagine image
            processors:
                fit:                  # resize to bounding box (preserves aspect ratio, no crop)
                    width: 400
                    height: 300
                    # density: 1.0    # pixel density multiplier (2.0 = retina)
                    # filter: lanczos # resampling filter (auto "undefined" for GD)
                    # background: '#fff'
                    # alpha: 0        # background alpha (0 = opaque)

                # fill:              # resize + centre-crop to exact dimensions
                #     width: 400
                #     height: 400
                #     density: 1.0
                #     filter: lanczos
                #     background: '#fff'
                #     alpha: 0

                # optimize:          # fit without upscaling (preserves original if smaller)
                #     width: 1200
                #     height: 0
                #     density: 2

                # strip: {}          # remove EXIF, ICC profiles, metadata

                # interlace:         # progressive JPEG / interlaced PNG
                #     mode: line     # none | line | plane | partition

                # output:           # override output format/quality per-processor
                #     format: webp
                #     quality: 85

            # Post-processor pipeline — shell out to CLI binaries on encoded bytes
            post_processors:
                cwebp: {}
                # cwebp:
                #     quality: 90    # default — 0-100
                #     timeout: 30    # default — process timeout in seconds

                # avifenc:
                #     quality: 63    # default — 0-100
                #     speed: 6       # default — 0=slowest/best, 10=fastest
                #     timeout: 60    # default

                # mozjpeg:
                #     quality: 75    # default — 0-100
                #     timeout: 60    # default

                # pngquant:
                #     quality: '80-100'   # default — min-max quality range
                #     timeout: 30         # default
```

### Quick start (minimal)

Most defaults are sensible out of the box. A minimal configuration only needs a `default` filter and optionally named filters:

```yaml
# config/packages/chamber_orchestra_image.yaml
chamber_orchestra_image:
    filters:
        # Required for Twig fit(), fill(), optimize(), avif() filters.
        # Includes a balanced set of post-processors for good compression and quality.
        default:
            output:
                quality: 80
            processors:
                interlace: { mode: partition }
                strip: {}
            post_processors:
                mozjpeg: { quality: 80 }
                pngquant: { quality: '65-80' }
                cwebp: { quality: 78 }
                avifenc: { quality: 60 }

        # Exposed filter for external clients (TypeScript, Swift, Kotlin, etc.)
        # Secret must differ from APP_SECRET — share it with trusted clients out-of-band
        client:
            exposed: true
            secret: '%env(CLIENT_IMAGE_SECRET)%'
            output:
                quality: 80
            processors:
                interlace: { mode: partition }
                strip: {}
            post_processors:
                mozjpeg: { quality: 80 }
                pngquant: { quality: '65-80' }
                cwebp: { quality: 78 }
                avifenc: { quality: 60 }
```

This registers a filesystem loader from `%kernel.project_dir%/public`, a `web_path` resolver writing to `public/media`, Imagick as the driver, and auto-detects Symfony Messenger for async processing — all automatically. The `client` filter exposes the `/media/{pathHash}/{optionsHash}/{name}.{format}` endpoint for use from any HTTP client.

---

## Usage

### Twig filters

```twig
{# Apply a named filter #}
<img src="{{ '/scores/moonlight_sonata.jpg' | image_filter('score_thumbnail') }}">

{# Fit within a bounding box (aspect-ratio preserved, no crop) #}
<img src="{{ '/scores/moonlight_sonata.jpg' | fit(800, 600) }}">

{# Fill a fixed box (resize + centre-crop) #}
<img src="{{ '/recordings/violin_concerto.jpg' | fill(400, 400) }}">

{# Optimise — scales to width 1200 at 2x density, never upscales #}
<img src="{{ '/scores/symphony_no_5.jpg' | optimize }}">
<img src="{{ '/scores/symphony_no_5.jpg' | optimize(800) }}">

{# Convert to AVIF — scales to width 1200 at 2x density, outputs AVIF format #}
<img src="{{ '/scores/symphony_no_5.jpg' | avif }}">
<img src="{{ '/scores/symphony_no_5.jpg' | avif(800) }}">
<img src="{{ '/scores/symphony_no_5.jpg' | avif(800, {output: {quality: 50}}) }}">

{# Runtime filter — options merged at request time (HMAC-signed URL) #}
<img src="{{ '/scores/moonlight_sonata.jpg' | image_filter('default', {'fit': {'width': 600, 'height': 400}}) }}">
```

> `fit`, `fill`, `optimize`, and `avif` dispatch through the runtime filter mechanism — no named filter configuration needed.

### Twig macros

The bundle ships responsive `<picture>` macros that generate AVIF, WebP, and fallback `<source>` elements with 1x/2x/3x srcset variants. Import and use them in any template:

```twig
{% import '@ChamberOrchestraImage/macro/image.html.twig' as image %}

{# Responsive <picture> with fit (preserves aspect ratio) #}
{{ image.fit('/scores/symphony_no_5.jpg', 800, 600, {class: 'hero-img', alt: 'Symphony No. 5'}) }}

{# Responsive <picture> with fill (crops to exact dimensions) #}
{{ image.fill('/recordings/conductor_portrait.jpg', 200, 200, {class: 'avatar', alt: 'Conductor'}) }}

{# Pass <picture> element attributes as the 5th argument #}
{{ image.fit('/scores/moonlight_sonata.jpg', 1200, 800, {alt: 'Moonlight Sonata'}, {class: 'picture-wrapper'}) }}
```

Each macro generates three `<source>` groups (AVIF, WebP, fallback) at 1x/2x/3x densities. The `fit` macro calls look like this under the hood:

```twig
{# What the fit macro generates internally: #}
<picture>
  <source srcset="{{ src|fit(800, 600, {fit: {density: 3}, output: {format: 'avif'}}) }} 3x,
                   {{ src|fit(800, 600, {fit: {density: 2}, output: {format: 'avif'}}) }} 2x,
                   {{ src|fit(800, 600, {output: {format: 'avif'}}) }} 1x"
          type="image/avif"/>
  <source srcset="{{ src|fit(800, 600, {fit: {density: 3}, output: {format: 'webp'}}) }} 3x,
                   {{ src|fit(800, 600, {fit: {density: 2}, output: {format: 'webp'}}) }} 2x,
                   {{ src|fit(800, 600, {output: {format: 'webp'}}) }} 1x"
          type="image/webp"/>
  <source srcset="{{ src|fit(800, 600, {fit: {density: 3}}) }} 3x,
                   {{ src|fit(800, 600, {fit: {density: 2}}) }} 2x,
                   {{ src|fit(800, 600) }} 1x"/>
  <img src="{{ src|fit(800, 600) }}" width="800" height="600"
       loading="lazy" decoding="async" class="hero-img" alt="Symphony No. 5"/>
</picture>
```

The `fill` macro works identically but uses the `fill` filter (resize + centre-crop) instead of `fit`:

```twig
{# fill: same structure, different filter #}
<source srcset="{{ src|fill(200, 200, {fill: {density: 3}, output: {format: 'avif'}}) }} 3x,
                 {{ src|fill(200, 200, {fill: {density: 2}, output: {format: 'avif'}}) }} 2x,
                 {{ src|fill(200, 200, {output: {format: 'avif'}}) }} 1x"
        type="image/avif"/>
{# ...webp and fallback sources follow the same pattern #}
```

#### CSS background macros

CSS background macros output inline `style` attributes with custom properties for use with CSS `background-image`. This is useful for hero sections, banners, and other elements where CSS backgrounds are preferred:

```twig
{# Outputs style="--bg-url: url(...); --bg-url-avif: url(...); --bg-url-webp: url(...); ..." #}
<div {{ image.css_fit('/scores/la_traviata.jpg', 1200, 600) }}></div>
<div {{ image.css_fill('/scores/four_seasons.jpg', 1200, 400) }}></div>

{# Disable 2x/3x variants (1x only) #}
<div {{ image.css_fit('/scores/la_traviata.jpg', 1200, 600, false) }}></div>
```

Custom properties generated: `--bg-url`, `--bg-url-avif`, `--bg-url-webp`, `--bg-url-2x`, `--bg-url-avif-2x`, `--bg-url-webp-2x`, `--bg-url-3x`, `--bg-url-avif-3x`, `--bg-url-webp-3x`, `--bg-width`, `--bg-height`.

Pair with CSS to select the best format:

```css
.hero {
  background-image: var(--bg-url);
  background-size: var(--bg-width) var(--bg-height);
}
@supports (background-image: url("test.avif")) {
  .hero { background-image: var(--bg-url-avif); }
}
@media (min-resolution: 2dppx) {
  .hero { background-image: var(--bg-url-2x); }
  @supports (background-image: url("test.avif")) {
    .hero { background-image: var(--bg-url-avif-2x); }
  }
}
```

### PHP

```php
use ChamberOrchestra\ImageBundle\Imagine\Cache\CacheManager;
use ChamberOrchestra\ImageBundle\Service\FilterService;

// Generate a URL (no processing — image processed on first browser hit)
$url = $cacheManager->getBrowserPath('/scores/moonlight_sonata.jpg', 'default', [
    'processors' => ['fit' => ['width' => 800, 'height' => 600]],
]);

// Process, cache, and return the resolved URL
$url = $filterService->getProcessedImageUrl('/scores/moonlight_sonata.jpg', 'default', [
    'processors' => ['fit' => ['width' => 800, 'height' => 600]],
]);

// Remove all cached variants for a source image
$cacheManager->remove('/scores/moonlight_sonata.jpg');
```

## Processors

All processors are configured as key-value maps under `filters.<name>.processors`.

### `fit` — Resize to bounding box

Scales the image to fit within the given dimensions while preserving aspect ratio. Never crops.

```yaml
processors:
    fit:
        width: 800       # 0 = derive from height + aspect ratio
        height: 600      # 0 = derive from width + aspect ratio
        density: 1.0     # pixel density multiplier (2.0 = retina)
        filter: lanczos  # resampling filter
```

### `fill` — Resize and centre-crop

Scales and crops to fill the exact requested dimensions. The crop is centred.

```yaml
processors:
    fill:
        width: 400
        height: 400
        density: 1.0
```

### `optimize` — Fit without upscaling

Behaves like `fit` but never upscales the image. If the target dimensions are larger than the source, the original size is preserved.

```yaml
processors:
    optimize:
        width: 1200
        height: 0        # 0 = derive from aspect ratio
        density: 2       # retina output
```

### `strip` — Remove metadata

Strips EXIF, ICC profiles, and other embedded metadata from the image.

```yaml
processors:
    strip: {}
```

### `interlace` — Progressive encoding

Sets the interlacing mode for progressive JPEG or interlaced PNG output.

```yaml
processors:
    interlace:
        mode: line    # none | line | plane | partition
```

### `output` — Output format and quality

Controls the output format and quality.

```yaml
processors:
    output:
        format: webp    # png | jpg | jpeg | webp | gif | tiff | bmp | avif
        quality: 85     # 1-100
```

### Shared processor options

`fit`, `fill`, and `optimize` share these options from `AbstractResizeProcessor`:

| Option | Default | Description |
|---|---|---|
| `width` | `0` | Target width (px). `0` = compute from aspect ratio. |
| `height` | `0` | Target height (px). `0` = compute from aspect ratio. |
| `density` | `1.0` | Pixel density multiplier. Output = `size * density`. |
| `filter` | `lanczos` | Resampling filter (auto `undefined` for GD). |
| `background` | `#fff` | Canvas background colour for letterboxing. |
| `alpha` | `0` | Background alpha (0 = opaque). |

---

## Post-processors

Post-processors shell out to external CLI binaries and operate on the encoded image bytes. Configure them under `filters.<name>.post_processors`.

### `avifenc` — Convert to AVIF

Converts JPEG, PNG, GIF, and TIFF images to AVIF format using the `avifenc` binary.

```yaml
post_processors:
    avifenc:
        quality: 63     # 0-100 (default: 63)
        speed: 6        # 0=slowest/best, 10=fastest (default: 6)
        timeout: 60     # process timeout in seconds
```

Requires the `avifenc` binary.

### `cwebp` — Convert to WebP

Converts JPEG, PNG, GIF, and TIFF images to WebP format.

```yaml
post_processors:
    cwebp:
        quality: 90     # 0-100 (default: 90)
        timeout: 30     # process timeout in seconds
```

Requires the `cwebp` binary (e.g. `apt install webp`).

### `mozjpeg` — Optimise JPEG

Re-encodes JPEG images through MozJPEG for smaller file sizes.

```yaml
post_processors:
    mozjpeg:
        quality: 75     # 0-100 (default: 75)
        timeout: 60     # process timeout in seconds
```

Requires the `cjpeg` binary from MozJPEG.

### `pngquant` — Compress PNG

Compresses PNG images using lossy palette quantisation.

```yaml
post_processors:
    pngquant:
        quality: '80-100'   # min-max quality range
        timeout: 30
```

Requires the `pngquant` binary (e.g. `apt install pngquant`).

---

## Loaders

Loaders retrieve the source image binary. The `default` loader is always `filesystem`.

### `filesystem`

Loads images from one or more root directories on the local filesystem. Performs path traversal protection via `realpath()`.

```yaml
loaders:
    default:
        type: filesystem
        data_root:
            - '%kernel.project_dir%/public/uploads'
            - '%kernel.project_dir%/public/images'
```

Named roots allow `@name:path` placeholder syntax:

```yaml
loaders:
    default:
        type: filesystem
        data_root:
            uploads: '%kernel.project_dir%/public/uploads'
```

```twig
{{ '@uploads:conductors/karajan.jpg' | image_filter('conductor_portrait') }}
```

#### Locator security

The filesystem loader uses a **locator** to resolve and validate image paths. Two locators are available:

- **`FileSystemLocator`** (default) — resolves paths with `realpath()` and verifies the resolved path falls within a configured root directory. Symlinks that resolve outside the root are rejected. This is the recommended locator.

- **`FileSystemInsecureLocator`** — more permissive with symlinked directory structures. Still rejects `..` traversal and verifies the resolved path is under the root, but accommodates setups where the `data_root` itself or files within it are symlinks that resolve outside the configured root. Useful for monorepo setups, Vagrant/Docker shared mounts, or when bundle assets are symlinked into the web root via `assets:install --symlink`.

```yaml
loaders:
    default:
        type: filesystem
        locator: ChamberOrchestra\ImageBundle\Binary\Locator\FileSystemInsecureLocator
        data_root:
            - '%kernel.project_dir%/public'
```

### `stream`

Loads images from any PHP stream wrapper (HTTP, FTP, custom wrappers, etc.). The `wrapper_prefix` is prepended to the image path before calling `file_get_contents()`. An optional stream context can be provided for authentication or SSL options.

```yaml
loaders:
    remote:
        type: stream
        wrapper_prefix: 'https://cdn.example.com/uploads/'
```

```php
$url = $filterService->getUrlOfFilteredImage('scores/violin_concerto.jpg', 'score_thumbnail');
// internally calls file_get_contents('https://cdn.example.com/uploads/scores/violin_concerto.jpg')
```

Use it in a filter pipeline:

```yaml
filters:
    score_thumbnail:
        loader: remote
        processors:
            fit: { width: 400, height: 300 }
```

### `s3` — S3-compatible storage

Loads images from S3-compatible storage (AWS S3, MinIO, DigitalOcean Spaces). Requires `chamber-orchestra/file-bundle`.

```yaml
loaders:
    default:
        type: s3
        storage: default    # name of the file-bundle storage
```

### Custom loaders

Implement `LoaderInterface` and register a factory:

```php
use ChamberOrchestra\ImageBundle\Binary\Loader\LoaderInterface;

class MyLoader implements LoaderInterface
{
    public function find(string $path): BinaryInterface|string { /* ... */ }
    public function getName(): string { return 'my_loader'; }
}
```

```php
// In your bundle's build() method:
$extension->addLoaderFactory(new MyLoaderFactory());
```

---

## Resolvers

Resolvers store cached images and resolve them to browser-accessible URLs.

### `web_path`

Writes cached files to a directory under the web root and returns a root-relative URL.

```yaml
resolvers:
    default:
        type: web_path
        web_root: '%kernel.project_dir%/public'
        cache_prefix: media/cache
```

### `s3` — S3-compatible storage

Stores cached images in an S3-compatible bucket and resolves URLs via a CDN prefix or presigned URLs. Requires `aws/aws-sdk-php`.

```yaml
resolvers:
    default:
        type: s3
        bucket: my-bucket
        region: eu-west-1
        endpoint: ~                          # optional: https://minio.example.com
        uri_prefix: https://cdn.example.com  # optional: CDN URL prefix
        cache_prefix: media                  # S3 key prefix (default: media)
```

### `cache` (PSR-6 decorator)

Wraps any resolver with a PSR-6 cache layer to avoid filesystem `is_file()` checks on repeated requests.

```php
use ChamberOrchestra\ImageBundle\Imagine\Cache\Resolver\CacheResolver;
use Symfony\Component\Cache\Adapter\RedisAdapter;

$resolver = new CacheResolver(
    new RedisAdapter($redis),
    $innerWebPathResolver,
    ['lifetime' => 3600]
);
```

### Custom resolvers

Implement `ResolverInterface` and register a factory, or inject your resolver as a service:

```yaml
resolvers:
    my_resolver:
        type: custom
        service: App\ImageResolver\MyResolver
```

---

## Runtime filters

Runtime filters allow processor options to be passed at request time without pre-configuring a named filter. The URL is HMAC-signed to prevent parameter tampering.

```php
// Generates: /_media/cache/resolve/default/rc/<prefix>/<hash>/symphony_no_5.jpg
$url = $cacheManager->getBrowserPath('/scores/symphony_no_5.jpg', 'default', [
    'fit' => ['width' => 800, 'height' => 0],
    'output' => ['format' => 'webp'],
]);
```

The HMAC secret defaults to the `APP_SECRET` kernel parameter (standard Symfony). You can override it per filter with the `secret` option — useful when different filters are consumed by different clients that each hold their own signing key:

```yaml
filters:
    soloist_portrait:
        secret: '%env(MOBILE_IMAGE_SECRET)%'
        processors:
            fill: { width: 200, height: 200 }
            strip: {}

    programme_cover:
        secret: '%env(ADMIN_IMAGE_SECRET)%'
        processors:
            fit: { width: 1920, height: 0 }
```

When `secret` is set on a filter, all signing and verification for that filter (URL generation, controller hash check, cache path derivation, cache invalidation) uses the per-filter secret instead of `APP_SECRET`. Filters without a `secret` continue to use the global secret.

### Client image URLs (TypeScript / mobile clients)

For front-end apps, mobile clients (Swift, Kotlin), or any HTTP client, the bundle exposes an nginx-cacheable endpoint that generates processed images on demand:

```
GET /media/{pathHash}/{optionsHash}/{name}@{density}x.{format}?path=...&filter=...&type=...&width=...&height=...&quality=...
```

The filter must be marked `exposed: true` with a dedicated `secret` (must differ from `APP_SECRET`):

```yaml
filters:
    client:
        exposed: true
        secret: '%env(CLIENT_IMAGE_SECRET)%'
        processors:
            fit: { width: 1200, height: 0 }
        post_processors:
            cwebp: {}
```

Share the `secret` with trusted clients out-of-band. The URL is HMAC-validated — clients compute the hashes using the same algorithm as the PHP `Signer`.

On a cache hit the controller returns a `301` redirect to the static file; on a miss it processes the image, caches it, and redirects. Because the URL is a deterministic GET, nginx `try_files` can serve cached images directly without hitting PHP.

Supported formats: `jpg`, `jpeg`, `png`, `webp`, `avif`.

#### TypeScript signing implementation

```typescript
/**
 * HMAC-SHA256 image URL signer — mirrors the PHP Signer class.
 */
async function hash(value: string, secret: string): Promise<string> {
  const key = await crypto.subtle.importKey(
    "raw",
    new TextEncoder().encode(secret),
    { name: "HMAC", hash: "SHA-256" },
    false,
    ["sign"],
  );

  // ltrim '/' to match PHP behaviour
  const data = new TextEncoder().encode(value.replace(/^\/+/, ""));
  const signature = await crypto.subtle.sign("HMAC", key, data);

  // base64url encoding (RFC 4648 §5)
  return btoa(String.fromCharCode(...new Uint8Array(signature)))
    .replace(/\+/g, "-")
    .replace(/\//g, "_")
    .replace(/=+$/, "")
    .slice(0, 16);
}

async function sign(
  path: string,
  secret: string,
  config: Record<string, unknown>,
): Promise<string> {
  // Convert all values to strings recursively
  const stringify = (obj: Record<string, unknown>): Record<string, unknown> =>
    Object.fromEntries(
      Object.entries(obj).map(([k, v]) => [
        k,
        v && typeof v === "object" && !Array.isArray(v)
          ? stringify(v as Record<string, unknown>)
          : String(v ?? ""),
      ]),
    );

  // Sort keys recursively
  const sortKeys = (obj: Record<string, unknown>): Record<string, unknown> =>
    Object.fromEntries(
      Object.keys(obj)
        .sort()
        .map((k) => [
          k,
          obj[k] && typeof obj[k] === "object" && !Array.isArray(obj[k])
            ? sortKeys(obj[k] as Record<string, unknown>)
            : obj[k],
        ]),
    );

  const normalized = sortKeys(stringify(config));
  const pathHash = await hash(path, secret);
  const optionsHash = await hash(JSON.stringify(normalized), secret);

  return `${pathHash}/${optionsHash}`;
}
```

#### Building the URL

Configure `filter`, `secret`, and `baseUrl` once at module level:

```typescript
// lib/image-config.ts — single source of truth
const IMAGE_FILTER = "client";
const IMAGE_SECRET = process.env.NEXT_PUBLIC_IMAGE_SECRET!; // or import.meta.env.VITE_IMAGE_SECRET
const IMAGE_BASE_URL = "/media";

interface ImageUrlOptions {
  path: string; // source image path, e.g. "scores/moonlight_sonata.jpg"
  type: "fit" | "fill" | "optimize";
  width: number;
  height: number;
  density?: number; // default: 1
  quality?: number; // default: 0 (use filter default)
  format?: string; // default: source extension
}

async function buildImageUrl(options: ImageUrlOptions): Promise<string> {
  const { path, type, width, height, density = 1, quality = 0 } = options;

  const ext = path.split(".").pop() ?? "jpg";
  const format = options.format ?? ext;
  const name = path.split("/").pop()?.replace(/\.[^.]+$/, "") ?? "image";

  // Config must match the PHP controller's buildConfig() structure
  const config = {
    [type]: { width, height, density },
    output: { quality, format: format.toLowerCase() },
  };

  const signed = await sign(path, IMAGE_SECRET, config);
  const [pathHash, optionsHash] = signed.split("/");
  const params = new URLSearchParams({
    path,
    filter: IMAGE_FILTER,
    type,
    width: String(width),
    height: String(height),
    quality: String(quality),
  });

  return `${IMAGE_BASE_URL}/${pathHash}/${optionsHash}/${name}@${density}x.${format}?${params}`;
}
```

#### Usage

```typescript
const url = await buildImageUrl({
  path: "scores/moonlight_sonata.jpg",
  type: "fit",
  width: 800,
  height: 600,
  density: 2,
  quality: 85,
  format: "webp",
});
// => /media/Ab3xK9_zRt4mNp2q/Qm7pLw2dXk9Yj6Fs/moonlight_sonata@2x.webp?path=scores/moonlight_sonata.jpg&filter=client&type=fit&width=800&height=600&quality=85
```

#### Responsive `<picture>` element (React example)

This mirrors the Twig `fit` / `fill` macros — AVIF, WebP, and fallback srcsets at 1x/2x/3x densities:

```tsx
type PictureType = "fit" | "fill";

async function buildSrcSet(
  src: string, type: PictureType,
  width: number, height: number, quality: number,
  format?: string,
): Promise<string> {
  const densities = [3, 2, 1];
  const parts = await Promise.all(
    densities.map(async (density) => {
      const url = await buildImageUrl({
        path: src, type, width, height, density, quality, format,
      });
      return `${url} ${density}x`;
    }),
  );
  return parts.join(", ");
}

async function buildPictureSources(
  src: string, type: PictureType,
  width: number, height: number, quality: number,
) {
  const [avifSrcSet, webpSrcSet, fallbackSrcSet, fallbackSrc] =
    await Promise.all([
      buildSrcSet(src, type, width, height, quality, "avif"),
      buildSrcSet(src, type, width, height, quality, "webp"),
      buildSrcSet(src, type, width, height, quality),
      buildImageUrl({ path: src, type, width, height, quality }),
    ]);

  return { avifSrcSet, webpSrcSet, fallbackSrcSet, fallbackSrc };
}
```

##### React component

```tsx
// components/ResponsiveImage.tsx
import { useEffect, useState } from "react";

interface ResponsiveImageProps {
  src: string;
  type?: "fit" | "fill" | "optimize";
  width: number;
  height: number;
  quality?: number;
  alt?: string;
  className?: string;
}

interface PictureSources {
  avifSrcSet: string;
  webpSrcSet: string;
  fallbackSrcSet: string;
  fallbackSrc: string;
}

function useImageSources(
  src: string, type: "fit" | "fill" | "optimize",
  width: number, height: number, quality: number,
): PictureSources | null {
  const [sources, setSources] = useState<PictureSources | null>(null);

  useEffect(() => {
    buildPictureSources(src, type, width, height, quality).then(setSources);
  }, [src, type, width, height, quality]);

  return sources;
}

export function ResponsiveImage({
  src, type = "fit", width, height, quality = 85, alt = "", className,
}: ResponsiveImageProps) {
  const sources = useImageSources(src, type, width, height, quality);
  if (!sources) return null;

  return (
    <picture>
      <source srcSet={sources.avifSrcSet} type="image/avif" />
      <source srcSet={sources.webpSrcSet} type="image/webp" />
      <source srcSet={sources.fallbackSrcSet} />
      <img
        src={sources.fallbackSrc}
        width={width} height={height}
        loading="lazy" decoding="async"
        alt={alt} className={className}
        style={{ height: "auto" }}
      />
    </picture>
  );
}
```

```tsx
<ResponsiveImage src="scores/symphony_no_5.jpg" type="fit" width={800} height={600} alt="Symphony No. 5" />
<ResponsiveImage src="recordings/conductor_portrait.jpg" type="fill" width={200} height={200} alt="Conductor" className="rounded-full" />
```

##### Next.js server component

In Next.js server components the secret stays on the server — configure it in `lib/image-config.ts` and the component just calls `buildPictureSources` directly:

```tsx
// components/ResponsiveImage.tsx

interface ResponsiveImageProps {
  src: string;
  type?: "fit" | "fill" | "optimize";
  width: number;
  height: number;
  quality?: number;
  alt?: string;
  className?: string;
}

export async function ResponsiveImage({
  src, type = "fit", width, height, quality = 85, alt = "", className,
}: ResponsiveImageProps) {
  const { avifSrcSet, webpSrcSet, fallbackSrcSet, fallbackSrc } =
    await buildPictureSources(src, type, width, height, quality);

  return (
    <picture>
      <source srcSet={avifSrcSet} type="image/avif" />
      <source srcSet={webpSrcSet} type="image/webp" />
      <source srcSet={fallbackSrcSet} />
      <img
        src={fallbackSrc}
        width={width} height={height}
        loading="lazy" decoding="async"
        alt={alt} className={className}
        style={{ height: "auto" }}
      />
    </picture>
  );
}
```

```tsx
<ResponsiveImage src="scores/symphony_no_5.jpg" type="fit" width={800} height={600} alt="Symphony No. 5" />
<ResponsiveImage src="recordings/conductor_portrait.jpg" type="fill" width={200} height={200} alt="Conductor" className="rounded-full" />
```

##### Vue component

```vue
<!-- components/ResponsiveImage.vue -->
<script setup lang="ts">
import { ref, watchEffect } from "vue";

const props = withDefaults(defineProps<{
  src: string;
  type?: "fit" | "fill" | "optimize";
  width: number;
  height: number;
  quality?: number;
  alt?: string;
}>(), {
  type: "fit",
  quality: 85,
  alt: "",
});

const avifSrcSet = ref("");
const webpSrcSet = ref("");
const fallbackSrcSet = ref("");
const fallbackSrc = ref("");

watchEffect(async () => {
  const sources = await buildPictureSources(
    props.src, props.type, props.width, props.height, props.quality,
  );
  avifSrcSet.value = sources.avifSrcSet;
  webpSrcSet.value = sources.webpSrcSet;
  fallbackSrcSet.value = sources.fallbackSrcSet;
  fallbackSrc.value = sources.fallbackSrc;
});
</script>

<template>
  <picture v-if="fallbackSrc">
    <source :srcset="avifSrcSet" type="image/avif" />
    <source :srcset="webpSrcSet" type="image/webp" />
    <source :srcset="fallbackSrcSet" />
    <img
      :src="fallbackSrc"
      :width="width" :height="height"
      loading="lazy" decoding="async"
      :alt="alt"
      style="height: auto"
    />
  </picture>
</template>
```

```vue
<ResponsiveImage src="scores/symphony_no_5.jpg" type="fit" :width="800" :height="600" alt="Symphony No. 5" />
<ResponsiveImage src="recordings/conductor_portrait.jpg" type="fill" :width="200" :height="200" alt="Conductor" />
```

##### Rendered HTML (all frameworks)

```html
<picture>
  <source srcset="/media/.../symphony_no_5@3x.avif?... 3x, .../symphony_no_5@2x.avif?... 2x, .../symphony_no_5@1x.avif?... 1x" type="image/avif"/>
  <source srcset="/media/.../symphony_no_5@3x.webp?... 3x, .../symphony_no_5@2x.webp?... 2x, .../symphony_no_5@1x.webp?... 1x" type="image/webp"/>
  <source srcset="/media/.../symphony_no_5@3x.jpg?... 3x, .../symphony_no_5@2x.jpg?... 2x, .../symphony_no_5@1x.jpg?... 1x"/>
  <img src="/media/.../symphony_no_5@1x.jpg?..." width="800" height="600" loading="lazy" decoding="async" alt="Symphony No. 5" style="height: auto"/>
</picture>
```

#### Swift signing implementation

```swift
import CryptoKit
import Foundation

/// HMAC-SHA256 image URL signer — mirrors the PHP Signer class.
enum ImageSigner {

    static func hash(_ value: String, secret: String) -> String {
        let trimmed = value.drop(while: { $0 == "/" })
        let key = SymmetricKey(data: Data(secret.utf8))
        let signature = HMAC<SHA256>.authenticationCode(
            for: Data(String(trimmed).utf8), using: key
        )
        // base64url encoding (RFC 4648 §5)
        let base64 = Data(signature).base64EncodedString()
            .replacingOccurrences(of: "+", with: "-")
            .replacingOccurrences(of: "/", with: "_")
            .trimmingCharacters(in: CharacterSet(charactersIn: "="))
        return String(base64.prefix(16))
    }

    static func sign(path: String, secret: String, config: [String: Any]) -> String {
        let normalized = stringify(config)
        let sorted = sortKeys(normalized)
        let jsonData = try! JSONSerialization.data(
            withJSONObject: sorted, options: [.sortedKeys]
        )
        let name = String(data: jsonData, encoding: .utf8)!

        let pathHash = hash(path, secret: secret)
        let optionsHash = hash(name, secret: secret)
        return "\(pathHash)/\(optionsHash)"
    }

    // Convert all leaf values to strings recursively
    private static func stringify(_ dict: [String: Any]) -> [String: Any] {
        dict.mapValues { value in
            if let nested = value as? [String: Any] {
                return stringify(nested)
            }
            return "\(value)"
        }
    }

    // Sort dictionary keys recursively
    private static func sortKeys(_ dict: [String: Any]) -> [String: Any] {
        Dictionary(uniqueKeysWithValues: dict.sorted(by: { $0.key < $1.key }).map { key, value in
            if let nested = value as? [String: Any] {
                return (key, sortKeys(nested) as Any)
            }
            return (key, value)
        })
    }
}
```

##### Building the URL

Configure `filter`, `secret`, and `baseUrl` once at app level:

```swift
// ImageConfig.swift — single source of truth
enum ImageConfig {
    static let filter = "client"
    static let secret = Bundle.main.infoDictionary?["IMAGE_SECRET"] as! String
    static let baseUrl = "/media"
}

struct ImageUrlOptions {
    let path: String        // e.g. "scores/moonlight_sonata.jpg"
    let type: String        // "fit", "fill", or "optimize"
    let width: Int
    let height: Int
    var density: Int = 1
    var quality: Int = 0
    var format: String?     // nil = use source extension
}

func buildImageUrl(_ options: ImageUrlOptions) -> String {
    let ext = (options.path as NSString).pathExtension
    let format = options.format ?? ext
    let name = ((options.path as NSString).lastPathComponent as NSString)
        .deletingPathExtension

    let config: [String: Any] = [
        options.type: [
            "width": options.width,
            "height": options.height,
            "density": options.density,
        ],
        "output": [
            "quality": options.quality,
            "format": format.lowercased(),
        ],
    ]

    let signed = ImageSigner.sign(path: options.path, secret: ImageConfig.secret, config: config)
    let parts = signed.split(separator: "/")
    let pathHash = parts[0], optionsHash = parts[1]

    var components = URLComponents()
    components.queryItems = [
        URLQueryItem(name: "path", value: options.path),
        URLQueryItem(name: "filter", value: ImageConfig.filter),
        URLQueryItem(name: "type", value: options.type),
        URLQueryItem(name: "width", value: "\(options.width)"),
        URLQueryItem(name: "height", value: "\(options.height)"),
        URLQueryItem(name: "quality", value: "\(options.quality)"),
    ]

    return "\(ImageConfig.baseUrl)/\(pathHash)/\(optionsHash)/\(name)@\(options.density)x.\(format)\(components.string ?? "")"
}
```

##### Usage

```swift
let url = buildImageUrl(ImageUrlOptions(
    path: "scores/moonlight_sonata.jpg",
    type: "fit",
    width: 800,
    height: 600,
    density: 2,
    quality: 85,
    format: "webp"
))
```

##### Responsive image loading (SwiftUI)

Generate URLs at multiple densities for the device screen scale:

```swift
func buildResponsiveUrls(
    path: String, type: String, width: Int, height: Int,
    quality: Int = 85, format: String = "webp"
) -> [Int: String] {
    var urls: [Int: String] = [:]
    for density in 1...3 {
        var opts = ImageUrlOptions(
            path: path, type: type, width: width, height: height
        )
        opts.density = density
        opts.quality = quality
        opts.format = format
        urls[density] = buildImageUrl(opts)
    }
    return urls
}

// Usage — pick the URL matching the device scale
let urls = buildResponsiveUrls(
    path: "scores/symphony_no_5.jpg", type: "fit", width: 800, height: 600
)
let scale = Int(UIScreen.main.scale) // 1, 2, or 3
let url = urls[scale] ?? urls[1]!
```

#### Kotlin signing implementation

```kotlin
import java.net.URLEncoder
import javax.crypto.Mac
import javax.crypto.spec.SecretKeySpec
import org.json.JSONObject

/**
 * HMAC-SHA256 image URL signer — mirrors the PHP Signer class.
 */
object ImageSigner {

    fun hash(value: String, secret: String): String {
        val trimmed = value.trimStart('/')
        val mac = Mac.getInstance("HmacSHA256").apply {
            init(SecretKeySpec(secret.toByteArray(), "HmacSHA256"))
        }
        val signature = mac.doFinal(trimmed.toByteArray())
        // base64url encoding (RFC 4648 §5)
        val base64 = java.util.Base64.getEncoder().encodeToString(signature)
            .replace('+', '-')
            .replace('/', '_')
            .trimEnd('=')
        return base64.take(16)
    }

    fun sign(path: String, secret: String, config: Map<String, Any>): String {
        val normalized = stringify(config)
        val sorted = sortKeys(normalized)
        val name = JSONObject(sorted).toString()

        val pathHash = hash(path, secret)
        val optionsHash = hash(name, secret)
        return "$pathHash/$optionsHash"
    }

    // Convert all leaf values to strings recursively
    @Suppress("UNCHECKED_CAST")
    private fun stringify(map: Map<String, Any>): Map<String, Any> =
        map.mapValues { (_, value) ->
            when (value) {
                is Map<*, *> -> stringify(value as Map<String, Any>)
                else -> value.toString()
            }
        }

    // Sort keys recursively
    @Suppress("UNCHECKED_CAST")
    private fun sortKeys(map: Map<String, Any>): Map<String, Any> =
        map.toSortedMap().mapValues { (_, value) ->
            when (value) {
                is Map<*, *> -> sortKeys(value as Map<String, Any>)
                else -> value
            }
        }
}
```

##### Building the URL

Configure `filter`, `secret`, and `baseUrl` once at app level:

```kotlin
// ImageConfig.kt — single source of truth
object ImageConfig {
    val filter = "client"
    val secret = BuildConfig.IMAGE_SECRET
    val baseUrl = "/media"
}

data class ImageUrlOptions(
    val path: String,           // e.g. "scores/moonlight_sonata.jpg"
    val type: String,           // "fit", "fill", or "optimize"
    val width: Int,
    val height: Int,
    val density: Int = 1,
    val quality: Int = 0,
    val format: String? = null, // null = use source extension
)

fun buildImageUrl(options: ImageUrlOptions): String {
    val ext = options.path.substringAfterLast('.', "jpg")
    val format = options.format ?: ext
    val name = options.path.substringAfterLast('/').substringBeforeLast('.')

    val config = mapOf(
        options.type to mapOf(
            "width" to options.width,
            "height" to options.height,
            "density" to options.density,
        ),
        "output" to mapOf(
            "quality" to options.quality,
            "format" to format.lowercase(),
        ),
    )

    val signed = ImageSigner.sign(options.path, ImageConfig.secret, config)
    val (pathHash, optionsHash) = signed.split("/")

    val query = listOf(
        "path" to options.path,
        "filter" to ImageConfig.filter,
        "type" to options.type,
        "width" to options.width.toString(),
        "height" to options.height.toString(),
        "quality" to options.quality.toString(),
    ).joinToString("&") { (k, v) ->
        "$k=${URLEncoder.encode(v, "UTF-8")}"
    }

    return "${ImageConfig.baseUrl}/$pathHash/$optionsHash/$name@${options.density}x.$format?$query"
}
```

##### Usage

```kotlin
val url = buildImageUrl(ImageUrlOptions(
    path = "scores/moonlight_sonata.jpg",
    type = "fit",
    width = 800,
    height = 600,
    density = 2,
    quality = 85,
    format = "webp",
))
```

##### Responsive image loading (Compose / Android)

```kotlin
fun buildResponsiveUrls(
    path: String, type: String, width: Int, height: Int,
    quality: Int = 85, format: String = "webp",
): Map<Int, String> = (1..3).associateWith { density ->
    buildImageUrl(ImageUrlOptions(
        path = path, type = type, width = width, height = height,
        density = density, quality = quality, format = format,
    ))
}

// Usage — pick URL matching device density
val urls = buildResponsiveUrls(
    path = "scores/symphony_no_5.jpg", type = "fit", width = 800, height = 600,
)
val density = resources.displayMetrics.densityDpi / 160 // 1, 2, or 3
val url = urls[density.coerceIn(1, 3)]!!
```

---

## Cache invalidation

When using `chamber-orchestra/file-bundle`, cached variants are automatically removed when a source file is deleted via `FileRemoveSubscriber`, which listens to `PostRemoveEvent`.

To remove all cached variants for a path manually:

```php
$cacheManager->remove('/scores/moonlight_sonata.jpg');
```

---

## Web server configuration

For best performance, configure your web server to serve cached images directly from disk without hitting PHP. The bundle writes processed images to the filesystem at deterministic paths — the web server checks if the file exists and serves it immediately. Only on a cache miss does the request fall through to the Symfony controller.

### nginx

```nginx
server {
    listen 80;
    server_name example.com;
    root /var/www/public;

    # Serve cached images directly — bypass PHP entirely on cache hit
    location /media/ {
        # Try the static file first, fall through to Symfony on miss
        try_files $uri /index.php$is_args$args;

        # Immutable caching — the URL changes when the image changes (content-addressed)
        expires max;
        add_header Cache-Control "public, immutable";

        # Disable access log for static image hits (optional, reduces I/O)
        access_log off;
    }

    # Symfony front controller
    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/run/php/php-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        internal;
    }

    location / {
        try_files $uri /index.php$is_args$args;
    }
}
```

### Apache

```apache
<VirtualHost *:80>
    ServerName example.com
    DocumentRoot /var/www/public

    # Enable rewrite engine
    RewriteEngine On

    # Serve cached images directly — bypass PHP on cache hit
    # If the file exists on disk, serve it with immutable caching
    <Directory /var/www/public/media>
        <IfModule mod_headers.c>
            Header set Cache-Control "public, max-age=31536000, immutable"
        </IfModule>

        <IfModule mod_expires.c>
            ExpiresActive On
            ExpiresDefault "access plus 1 year"
        </IfModule>
    </Directory>

    # Fall through to Symfony when the cached file doesn't exist yet
    <Directory /var/www/public>
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteRule ^(.*)$ /index.php [QSA,L]
    </Directory>

    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php-fpm.sock|fcgi://localhost"
    </FilesMatch>
</VirtualHost>
```

### Caddy

```caddyfile
example.com {
    root * /var/www/public

    # Serve cached images directly with immutable caching
    @media path /media/*
    handle @media {
        header Cache-Control "public, max-age=31536000, immutable"
        try_files {path} /index.php?{query}
        file_server
    }

    # Symfony front controller
    php_fastcgi unix//run/php/php-fpm.sock {
        resolve_root_symlink
    }

    file_server
}
```

### How it works

1. **First request** (cache miss): the file doesn't exist on disk, so `try_files` falls through to `index.php`. The Symfony controller processes the image, stores it at `{cache_path}/{pathHash}/{optionsHash}/{name}@{density}x.{format}`, and returns a `301` redirect to the static URL.
2. **Subsequent requests** (cache hit): the web server finds the file on disk and serves it directly — PHP is never invoked. The `Cache-Control: public, immutable` header tells browsers and CDNs to cache the response indefinitely.
3. **Cache invalidation**: when the source image is deleted, `CacheManager::remove()` deletes the entire `{pathHash}/` directory, so the next request will be a cache miss and the image will be re-processed.

Because image URLs are content-addressed (the hash changes when the source or options change), you can safely use `immutable` caching — stale URLs are never reused.

---

## Extension points

- **Custom processors**: implement `ProcessorInterface`, auto-tagged `chamber_orchestra_image.filter.processor`
- **Custom post-processors**: implement `PostProcessorInterface`, auto-tagged `chamber_orchestra_image.filter.post_processor`
- **Custom loaders**: implement `LoaderFactoryInterface`, register in your bundle's `build()` method
- **Custom resolvers**: implement `ResolverFactoryInterface`, register in your bundle's `build()` method, or use `type: custom` with a service ID

---

## Testing

```bash
composer install
composer test                                  # PHPUnit full suite
./vendor/bin/phpunit --filter ClassName         # single class
./vendor/bin/phpunit --filter testMethod        # single method
composer analyse                               # PHPStan (level max)
composer cs-check                              # code style check (dry-run)
composer cs-fix                                # apply code style fixes
```

---

## License

MIT. See [LICENSE](LICENSE) for details.
