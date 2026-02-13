# ChamberOrchestra ImageBundle

A Symfony 8 bundle for **on-demand image processing, resizing, format conversion, and filesystem caching**. Modelled after LiipImagineBundle but written for PHP 8.5 and Symfony 8.

Images are processed through named **filter pipelines** — chains of processors (resize, strip, interlace, output format) and post-processors (cwebp, MozJPEG, pngquant) — cached to the filesystem, and served via a controller that issues a `301` redirect to the cached URL.

---

## Features

- **On-demand processing** — images are processed at first request and cached permanently
- **Filter pipelines** — compose processors and post-processors per named filter
- **Processors**: `fit`, `fill`, `strip`, `interlace`, `output`, `optimize`
- **Post-processors**: `cwebp` (WebP), `mozjpeg` (JPEG optimisation), `pngquant` (PNG compression)
- **Runtime filters** — pass processor options at runtime via HMAC-signed URLs
- **Loaders**: `filesystem` (with root-path security), `stream` (any PHP stream wrapper)
- **Resolvers**: `web_path` (filesystem + public URL), `cache` (PSR-6 decorator)
- **Twig filters**: `image_filter`, `fit`, `fill`, `optimize`
- **Auto cache invalidation** via event subscriber (integrates with `chamber-orchestra/file-bundle`)
- **Imagick, GD, Gmagick** drivers — AVIF and WebP require Imagick

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
    resource: '@ChamberOrchestraImageBundle/Resources/config/routing.yaml'
```

---

## Configuration

```yaml
# config/packages/chamber_orchestra_image.yaml
chamber_orchestra_image:

    # Image driver: Imagine\Imagick\Imagine | Imagine\Gd\Imagine | Imagine\Gmagick\Imagine
    driver: Imagine\Imagick\Imagine

    # Default fallback image (optional)
    default_image: ~

    # Named loaders (filesystem loader is registered automatically as 'default')
    loaders:
        default:
            type: filesystem
            data_root:
                - '%kernel.project_dir%/public'

    # Named resolvers (web_path resolver is registered automatically as 'default')
    resolvers:
        default:
            type: web_path
            web_root: '%kernel.project_dir%/public'
            cache_prefix: media/cache

    # Named filter pipelines
    filters:
        thumbnail:
            output:
                quality: 85
                format: webp         # force output format (requires Imagick)
            processors:
                fit:
                    width: 400
                    height: 300
            post_processors:
                cwebp: {}

        avatar:
            output:
                quality: 90
            processors:
                fill:
                    width: 200
                    height: 200
                strip: {}
            post_processors:
                pngquant:
                    quality: '80-100'

        hero:
            output:
                quality: 80
            processors:
                fit:
                    width: 1920
                    height: 0        # 0 = derive automatically from aspect ratio
                interlace:
                    mode: line       # none | line | plane | partition
                strip: {}
            post_processors:
                mozjpeg:
                    quality: 75
```

---

## Usage

### Twig

```twig
{# Apply a named filter #}
<img src="{{ '/uploads/photo.jpg' | image_filter('thumbnail') }}">

{# Fit within a bounding box (aspect-ratio preserved, no crop) #}
<img src="{{ '/uploads/photo.jpg' | fit(800, 600) }}">

{# Fill a fixed box (resize + centre-crop) #}
<img src="{{ '/uploads/photo.jpg' | fill(400, 400) }}">

{# Optimise — scales to width 1200 at 2× density by default #}
<img src="{{ '/uploads/photo.jpg' | optimize }}">
<img src="{{ '/uploads/photo.jpg' | optimize(800) }}">

{# Runtime filter — options merged in at request time (HMAC-signed URL) #}
<img src="{{ '/uploads/photo.jpg' | image_filter('default', {'fit': {'width': 600, 'height': 400}}) }}">
```

> `fit` and `fill` dispatch through the runtime filter mechanism — no named filter configuration needed.

### PHP

```php
use ChamberOrchestra\ImageBundle\Imagine\Cache\CacheManager;
use ChamberOrchestra\ImageBundle\Service\FilterService;

// Get the browser-ready URL (processes and caches on first call)
$url = $filterService->getUrlOfFilteredImage('/uploads/photo.jpg', 'thumbnail');

// Generate a URL only (no processing — useful for lazy loading)
$url = $cacheManager->getBrowserPath('/uploads/photo.jpg', 'thumbnail');

// Runtime filter (processor options at call time)
$url = $cacheManager->getBrowserPath('/uploads/photo.jpg', 'default', [
    'fit' => ['width' => 800, 'height' => 600],
]);

// Remove all cached variants for a source image
$cacheManager->remove('/uploads/photo.jpg');
```

---

## Processors

All processors are configured as key-value maps under `filters.<name>.processors`.

### `fit` — Resize to bounding box

Scales the image to fit within the given dimensions while preserving aspect ratio. Never crops. If the image is already smaller, it is returned unchanged (or scaled by density).

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

### `output` — Output options

Controls the output format and quality. Requires Imagick for format conversion.

```yaml
processors:
    output:
        format: webp    # png | jpg | jpeg | webp | gif | tiff | bmp | avif
        quality: 85     # 1–100
        path: ~         # override the cache storage path
```

---

## Post-processors

Post-processors shell out to external CLI binaries and operate on the encoded image bytes. Configure them under `filters.<name>.post_processors`.

### `cwebp` — Convert to WebP

Converts JPEG, PNG, GIF, and TIFF images to WebP format.

```yaml
post_processors:
    cwebp:
        quality: 90     # 0–100 (default: 90)
        timeout: 30     # process timeout in seconds
```

Requires the `cwebp` binary (e.g. `apt install webp`).

### `mozjpeg` — Optimise JPEG

Re-encodes JPEG images through MozJPEG for smaller file sizes.

```yaml
post_processors:
    mozjpeg:
        quality: 75     # 0–100 (default: 75)
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
{{ '@uploads:avatars/user-42.jpg' | image_filter('avatar') }}
```

### `stream`

Loads images from any PHP stream wrapper (HTTP, S3, FTP, etc.).

```yaml
loaders:
    s3:
        type: stream
        wrapper: 's3://'
```

```php
$url = $filterService->getUrlOfFilteredImage('my-bucket/photo.jpg', 'thumbnail');
// internally calls file_get_contents('s3://my-bucket/photo.jpg')
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

Implement `ResolverInterface` and register a factory extending `CustomResolverFactory`, or inject your resolver as a service:

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
// Generates: /_media/cache/resolve/default/rc/<prefix>/<hash>/photo.jpg
$url = $cacheManager->getBrowserPath('/uploads/photo.jpg', 'default', [
    'fit' => ['width' => 800, 'height' => 0],
    'output' => ['format' => 'webp'],
]);
```

The HMAC secret is configured via the `APP_SECRET` kernel parameter (standard Symfony).

---

## Cache invalidation

When using `chamber-orchestra/file-bundle`, cached variants are automatically removed when a source file is deleted via `FileRemoveSubscriber`, which listens to `PostRemoveEvent`.

To remove all cached variants for a path manually:

```php
$cacheManager->remove('/uploads/photo.jpg');
```

---

## Testing

```bash
composer install
composer test              # PHPUnit full suite
./bin/phpunit --filter ClassName   # single class
./bin/phpunit --filter testMethod  # single method
composer cs-check          # code style check (dry-run)
composer cs-fix            # apply code style fixes
```

---

## License

Apache-2.0. See [LICENSE](LICENSE) for details.
