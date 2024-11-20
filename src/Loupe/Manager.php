<?php

namespace Daun\StatamicLoupe\Loupe;

use Illuminate\Support\Facades\File;
use Loupe\Loupe\Configuration;
use Loupe\Loupe\Loupe;
use Loupe\Loupe\LoupeFactory;
use Statamic\Facades\Path;

class Manager
{
    /**
     * @var Loupe[]
     */
    protected array $clients = [];

    public function __construct(
        protected readonly LoupeFactory $factory,
        protected string $path,
    ) {
        if (! File::isDirectory($this->path)) {
            File::makeDirectory($this->path, recursive: true);
        }
    }

    public function get(string $index, Configuration $configuration): Loupe
    {
        return ($this->clients[$index] ??= $this->make($index, $configuration));
    }

    public function make(string $index, Configuration $configuration): Loupe
    {
        $this->createIndex($index);

        return $this->factory->create($this->indexDirectory($index), $configuration);
    }

    public function indexDirectory(string $index): string
    {
        return Path::resolve("{$this->path}/{$index}");
    }

    public function indexPath(string $index): string
    {
        return Path::resolve("{$this->path}/{$index}/loupe.db");
    }

    public function indexExists(string $index): bool
    {
        return File::exists($this->indexPath($index));
    }

    public function createIndex(string $index): void
    {
        if (! File::isDirectory($dir = $this->indexDirectory($index))) {
            File::makeDirectory($dir, recursive: true);
        }
        if (! File::exists($db = $this->indexPath($index))) {
            File::put($db, '');
        }
    }

    public function dropIndex(string $index): void
    {
        File::deleteDirectory($this->indexDirectory($index));
    }
}
