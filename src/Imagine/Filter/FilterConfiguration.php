<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Imagine\Filter;

use ChamberOrchestra\ImageBundle\Exception\NonExistingFilterException;

/**
 * @phpstan-type FilterConfig = array{
 *     resolver: string,
 *     loader: string,
 *     output?: array<string, mixed>,
 *     default_image?: ?string,
 *     secret: string,
 *     async: bool,
 *     exposed: bool,
 *     processors: array<string, array<string, mixed>>,
 *     post_processors: array<string, array<string, mixed>>,
 * }
 */
class FilterConfiguration
{
    /**
     * @param array<string, FilterConfig> $filters
     */
    public function __construct(private array $filters = [])
    {
    }

    /**
     * @return FilterConfig
     */
    public function get(string $filter): array
    {
        if (!\array_key_exists($filter, $this->filters)) {
            throw new NonExistingFilterException(\sprintf('Could not find configuration for a filter: %s', $filter));
        }

        return $this->filters[$filter];
    }

    /**
     * @param FilterConfig $config
     */
    public function set(string $filter, array $config): void
    {
        $this->filters[$filter] = $config;
    }

    /**
     * @return array<string, FilterConfig>
     */
    public function all(): array
    {
        return $this->filters;
    }
}
