<?php

namespace Daun\StatamicLoupe\Loupe;

use Exception;
use Illuminate\Filesystem\Filesystem;
use Loupe\Loupe\Configuration;
use Loupe\Loupe\Loupe;
use Loupe\Loupe\LoupeFactory;

use function Illuminate\Filesystem\join_paths;

class Manager
{
    /**
     * @var Loupe[]
     */
    protected array $clients = [];

    protected LoupeFactory $factory;

    protected Filesystem $filesystem;

    public function __construct(
        protected string $path
    ) {
        $this->factory = new LoupeFactory();
        $this->filesystem = new Filesystem();

        $this->init();
    }

    protected function init(): void
    {
        $this->filesystem->ensureDirectoryExists($this->path);

        if (! $this->filesystem->isDirectory($this->path)) {
            throw new Exception(sprintf('The Loupe path "%s" does not exist and cannot be created.', $this->path));
        }

        if (! $this->filesystem->isWritable($this->path)) {
            throw new Exception(sprintf('The Loupe path "%s" is not writable.', $this->path));
        }
    }

    public function get(string $index, Configuration $configuration): Loupe
    {
        return $this->clients[$index] ??= $this->make($index, $configuration);
    }

    public function make(string $index, Configuration $configuration): Loupe
    {
        $this->createIndex($index);

        return $this->factory->create($this->indexDirectory($index), $configuration);
    }

    public function indexExists(string $index): bool
    {
        return $this->filesystem->exists($this->indexPath($index));
    }

    public function createIndex(string $index): void
    {
        $dir = $this->indexDirectory($index);
        $db = $this->indexPath($index);

        if (! $this->filesystem->exists($db)) {
            $this->filesystem->ensureDirectoryExists($dir);
            $this->filesystem->put($db, '');
        }

        if (! $this->filesystem->isFile($db)) {
            throw new Exception(sprintf('The Loupe index "%s" does not exist and cannot be created.', $db));
        }

        if (! $this->filesystem->isWritable($db)) {
            throw new Exception(sprintf('The Loupe index "%s" is not writable.', $db));
        }
    }

    public function deleteIndex(string $index): void
    {
        $this->filesystem->cleanDirectory($this->indexDirectory($index));
    }

    public function truncateIndex(string $index): void
    {
        $this->get($index, Configuration::create())->deleteAllDocuments();
    }

    public function indexDirectory(string $index): string
    {
        return join_paths($this->path, $index);
    }

    public function indexPath(string $index): string
    {
        return join_paths($this->path, $index, 'loupe.db');
    }
}
