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

    protected LoupeFactory $factory;

    public function __construct(
        protected string $path
    ) {
        $this->factory = new LoupeFactory();

        File::ensureDirectoryExists($this->path, mode: 0777);
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
        File::ensureDirectoryExists($this->indexDirectory($index), mode: 0777);
        if (! File::exists($db = $this->indexPath($index))) {
            File::put($db, '', lock: true);
        }
    }

    public function dropIndex(string $index): void
    {
        File::cleanDirectory($this->indexDirectory($index));
    }
}
