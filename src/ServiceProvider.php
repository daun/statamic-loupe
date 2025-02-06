<?php

namespace Daun\StatamicLoupe;

use Illuminate\Foundation\Application;
use Loupe\Loupe\LoupeFactory;
use Loupe\Loupe\LoupeFactoryInterface;
use Statamic\Facades\Search;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    public function register()
    {
        $this->app->bind(LoupeFactoryInterface::class, fn () => new LoupeFactory());
    }

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
