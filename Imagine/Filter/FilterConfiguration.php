<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\Imagine\Filter;

use ChamberOrchestra\ImageBundle\Exception\NonExistingFilterException;

class FilterConfiguration
{
    public function __construct(private array $filters = [])
    {
    }

    public function get(string $filter): array
    {
        if (!\array_key_exists($filter, $this->filters)) {
            throw new NonExistingFilterException(\sprintf('Could not find configuration for a filter: %s', $filter));
        }

        return $this->filters[$filter];
    }

    public function set(string $filter, array $config): void
    {
        $this->filters[$filter] = $config;
    }

    public function all(): array
    {
        return $this->filters;
    }
}
