<?php

use Daun\StatamicLoupe\Loupe\Factory;
use Daun\StatamicLoupe\Loupe\Index;
use Loupe\Loupe\Configuration;
use Loupe\Loupe\LoupeFactory;
use Loupe\Loupe\SearchParameters;

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
    expect($configuration->getLanguages())->toEqual(['en']); // derived from the default site
    expect($configuration->getMinTokenLengthForPrefixSearch())->toEqual(2); // lowered on purpose
    expect($configuration->getTypoTolerance()->isDisabled())->toEqual(false);
});

it('defers to the defaults of loupe', function () {
    /** @var Index */
    $index = $this->app->makeWith(Index::class, ['name' => 'default']);
    $configuration = $index->configuration();
    $loupe = Configuration::create();

    expect($configuration->getMaxQueryTokens())->toEqual($loupe->getMaxQueryTokens());
    expect($configuration->getMaxTotalHits())->toEqual($loupe->getMaxTotalHits());
    expect($configuration->getStopWords())->toEqual($loupe->getStopWords());
    expect($configuration->getRankingRules())->toEqual($loupe->getRankingRules());
    expect($configuration->getTypoTolerance()->getAlphabetSize())->toEqual($loupe->getTypoTolerance()->getAlphabetSize());
    expect($configuration->getTypoTolerance()->getIndexLength())->toEqual($loupe->getTypoTolerance()->getIndexLength());
    expect($configuration->getTypoTolerance()->isEnabledForPrefixSearch())->toEqual($loupe->getTypoTolerance()->isEnabledForPrefixSearch());
});

it('overrides defaults', function () {
    $config = [
        'fields' => ['id', 'summary', 'url'], // id will be ignored
        'max_query_tokens' => 11,
        'min_token_length_for_prefix_search' => 3,
        'languages' => ['de', 'fr'],
        'max_total_hits' => 500,
        'stop_words' => ['der', 'die', 'das'],
        'ranking_rules' => ['words', 'exactness'],
        'typo_tolerance_alphabet_size' => 5,
        'typo_tolerance_index_length' => 15,
        'typo_tolerance_for_prefix_search' => true,
    ];

    /** @var Index */
    $index = $this->app->makeWith(Index::class, ['name' => 'default', 'config' => $config]);
    $configuration = $index->configuration();

    expect($configuration->getSearchableAttributes())->toEqual(['summary', 'url']);
    expect($configuration->getMaxQueryTokens())->toEqual(11);
    expect($configuration->getMinTokenLengthForPrefixSearch())->toEqual(3);
    expect($configuration->getLanguages())->toEqual(['de', 'fr']);
    expect($configuration->getMaxTotalHits())->toEqual(500);
    expect($configuration->getStopWords())->toEqual(['das', 'der', 'die']); // sorted by Loupe
    expect($configuration->getRankingRules())->toEqual(['words', 'exactness']);
    expect($configuration->getTypoTolerance()->isDisabled())->toEqual(false);
    expect($configuration->getTypoTolerance()->getAlphabetSize())->toEqual(5);
    expect($configuration->getTypoTolerance()->getIndexLength())->toEqual(15);
    expect($configuration->getTypoTolerance()->isEnabledForPrefixSearch())->toEqual(true);

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
    if (! file_exists(fixtures_path('indexes', 'default'))) {
        mkdir(fixtures_path('indexes', 'default'), recursive: true);
    }

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

it('defaults to the matching strategy of loupe', function () {
    /** @var Index */
    $index = $this->app->makeWith(Index::class, ['name' => 'default']);

    expect($index->matchingStrategy())->toEqual(SearchParameters::create()->getMatchingStrategy());
});

it('uses a configured matching strategy', function () {
    $config = ['matching_strategy' => 'all'];

    /** @var Index */
    $index = $this->app->makeWith(Index::class, ['name' => 'default', 'config' => $config]);

    expect($index->matchingStrategy())->toEqual('all');
});

it('fetches as many results as loupe allows', function () {
    /** @var Index */
    $index = $this->app->makeWith(Index::class, ['name' => 'default']);

    expect($index->hitsPerPage())->toEqual(SearchParameters::MAX_LIMIT);
});

it('never fetches more results than the maximum total hits', function () {
    $config = ['max_total_hits' => 50];

    /** @var Index */
    $index = $this->app->makeWith(Index::class, ['name' => 'default', 'config' => $config]);

    expect($index->hitsPerPage())->toEqual(50);
});

it('never fetches more results than loupe accepts', function () {
    $config = ['hits_per_page' => 5000, 'max_total_hits' => 5000];

    /** @var Index */
    $index = $this->app->makeWith(Index::class, ['name' => 'default', 'config' => $config]);

    expect($index->hitsPerPage())->toEqual(SearchParameters::MAX_LIMIT);
});

it('fetches fewer results if configured', function () {
    $config = ['hits_per_page' => 10];

    /** @var Index */
    $index = $this->app->makeWith(Index::class, ['name' => 'default', 'config' => $config]);

    expect($index->hitsPerPage())->toEqual(10);
});
