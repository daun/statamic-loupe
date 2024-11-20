<?php

namespace Daun\StatamicLoupe;

use Daun\StatamicLoupe\Loupe\Manager;
use Illuminate\Foundation\Application;
use Loupe\Loupe\LoupeFactory;
use Statamic\Facades\Search;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    public function bootAddon(): void
    {
        Search::extend('loupe', function (Application $app, array $config, string $name, ?string $locale = null) {
            $path = $config['path'] ?? storage_path('statamic/loupe');
            $manager = new Manager(new LoupeFactory(), $path);

            return $app->makeWith(Loupe\Index::class, [
                'manager' => $manager,
                'config' => $config,
                'name' => $name,
                'locale' => $locale,
            ]);
        });
    }
}
