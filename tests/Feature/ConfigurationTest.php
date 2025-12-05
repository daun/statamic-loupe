<?php

use Daun\StatamicLoupe\Loupe\Factory;
use Daun\StatamicLoupe\Loupe\Index;
use Loupe\Loupe\Configuration;
use Loupe\Loupe\LoupeFactory;

it('returns a configuration object', function () {
    $index = $this->app->makeWith(Index::class, ['name' => 'default']);
    $configuration = $index->configuration();

    expect($configuration)->toBeInstanceOf(Configuration::class);
});

it('provides defaults', function () {
    /** @var Index */
    $index = $this->app->makeWith(Index::class, ['name' => 'default']);
    $configuration = $index->configuration();

    expect($configuration->getPrimaryKey())->toEqual('id');
    expect($configuration->getSearchableAttributes())->toEqual(['title']);
    expect($configuration->getMaxQueryTokens())->toEqual(10);
    expect($configuration->getMinTokenLengthForPrefixSearch())->toEqual(2);
    expect($configuration->getLanguages())->toEqual([]);
    expect($configuration->getTypoTolerance()->isDisabled())->toEqual(false);
    expect($configuration->getTypoTolerance()->getAlphabetSize())->toEqual(4);
    expect($configuration->getTypoTolerance()->getIndexLength())->toEqual(14);
});

it('overrides defaults', function () {
    $config = [
        'fields' => ['id', 'summary', 'url'], // id will be ignored
        'max_query_tokens' => 11,
        'min_token_length_for_prefix_search' => 3,
        'stemming_languages' => ['de', 'fr'],
        'typo_tolerance_alphabet_size' => 5,
        'typo_tolerance_index_length' => 15,
    ];

    /** @var Index */
    $index = $this->app->makeWith(Index::class, ['name' => 'default', 'config' => $config]);
    $configuration = $index->configuration();

    expect($configuration->getSearchableAttributes())->toEqual(['summary', 'url']);
    expect($configuration->getMaxQueryTokens())->toEqual(11);
    expect($configuration->getMinTokenLengthForPrefixSearch())->toEqual(3);
    expect($configuration->getLanguages())->toEqual(['de', 'fr']);
    expect($configuration->getTypoTolerance()->isDisabled())->toEqual(false);
    expect($configuration->getTypoTolerance()->getAlphabetSize())->toEqual(5);
    expect($configuration->getTypoTolerance()->getIndexLength())->toEqual(15);

    // Need to test typo tolerance separately
    $config = [
        'typo_tolerance_enabled' => false,
    ];

    /** @var Index */
    $index = $this->app->makeWith(Index::class, ['name' => 'default', 'config' => $config]);
    $configuration = $index->configuration();

    expect($configuration->getTypoTolerance()->isDisabled())->toEqual(true);
});

it('passes the configuration into the factory', function () {
    $spy = Mockery::spy(Factory::class);
    $spy->shouldReceive('create')
        ->andReturn((new Factory(new LoupeFactory))->create(fixtures_path('indexes', 'default'), Configuration::create()));

    $this->app->instance(Factory::class, $spy);

    $index = $this->app->makeWith(Index::class, ['name' => 'default']);
    $configuration = $index->configuration();

    $client = $index->client();

    $spy->shouldHaveReceived('create')
        ->once()
        ->with($index->dir(), $configuration);

});
