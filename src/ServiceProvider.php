<?php

namespace Daun\StatamicLoupe;

use Illuminate\Foundation\Application;
use Statamic\Facades\Search;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    public function bootAddon(): void
    {
        Search::extend('loupe', function (Application $app, array $config, string $name, ?string $locale = null) {
            return $app->makeWith(Loupe\Index::class, [
                'name' => $name,
                'config' => $config,
                'locale' => $locale,
            ]);
        });
    }
}
