<?php

use Daun\StatamicLoupe\Loupe\Index;
use Daun\StatamicLoupe\ServiceProvider;
use Loupe\Loupe\Loupe;
use Statamic\Facades\Search;

test('boots without issues', function () {
    $provider = new ServiceProvider($this->app);
    $provider->bootAddon();
})->throwsNoExceptions();

test('register a search driver', function () {
    $index = Search::index('loupe_index');

    expect($index)->toBeInstanceOf(Index::class);
    expect($index->client())->toBeInstanceOf(Loupe::class);
});
