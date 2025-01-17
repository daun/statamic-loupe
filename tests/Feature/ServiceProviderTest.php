<?php

use Daun\StatamicLoupe\Loupe\Index;
use Daun\StatamicLoupe\ServiceProvider;
use Statamic\Facades\Search;

it('boots without issues', function () {
    $provider = new ServiceProvider($this->app);
    $provider->bootAddon();
})->throwsNoExceptions();

it('registers a search driver', function () {
    $index = Search::index('pages');

    expect($index)->toBeInstanceOf(Index::class);
});
