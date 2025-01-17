<?php

namespace Daun\StatamicLoupe\Loupe;

use Loupe\Loupe\Configuration;
use Loupe\Loupe\LoupeFactory;

/**
 * Wrapper around LoupeFactory to allow mocking the create() method
 */
class Factory
{
    public function __construct(protected LoupeFactory $factory) {}

    public function create(string $path, Configuration $config)
    {
        return $this->factory->create($path, $config);
    }
}
