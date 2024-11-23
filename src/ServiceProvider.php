<?php

namespace Daun\StatamicLoupe;

use Daun\StatamicLoupe\Loupe\Manager;
use Illuminate\Foundation\Application;
use Statamic\Facades\Search;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    public function bootAddon(): void
    {
        Search::extend('loupe', function (Application $app, array $config, string $name, ?string $locale = null) {
            return $app->makeWith(Loupe\Index::class, [
                'manager' => new Manager($config['path'] ?? storage_path('statamic/loupe')),
                'config' => $config,
                'name' => $name,
                'locale' => $locale,
            ]);
        });
    }
}
