<?php

use Daun\StatamicLoupe\Loupe\Index;
use Illuminate\Support\Facades\File;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Search;
use Statamic\Facades\Site;

beforeEach(function () {
    $this->basePath = fixtures_path('indexes', random_int(11, 99999999));
    config(['statamic.search.drivers.loupe.path' => $this->basePath]);
});

afterEach(function () {
    File::deleteDirectory($this->basePath);
});

function makeIndex(array $config = [], ?string $locale = null): Index
{
    return app()->makeWith(Index::class, [
        'name' => 'default',
        'config' => $config,
        'locale' => $locale,
    ]);
}

function useSites(array $sites): void
{
    Site::setSites($sites);
}

it('derives the language from the only site', function () {
    useSites(['default' => ['url' => '/', 'locale' => 'de_DE']]);

    expect(makeIndex()->languages())->toEqual(['de']);
});

it('prefers an explicit lang over the locale', function () {
    useSites(['default' => ['url' => '/', 'locale' => 'de_DE', 'lang' => 'en']]);

    expect(makeIndex()->languages())->toEqual(['en']);
});

it('derives all site languages for a shared index', function () {
    useSites([
        'english' => ['url' => '/', 'locale' => 'en_US'],
        'german' => ['url' => '/de/', 'locale' => 'de_DE'],
        'french' => ['url' => '/fr/', 'locale' => 'fr_FR'],
    ]);

    expect(makeIndex()->languages())->toEqual(['en', 'de', 'fr']);
});

it('deduplicates languages of sites sharing a language', function () {
    useSites([
        'usa' => ['url' => '/', 'locale' => 'en_US'],
        'uk' => ['url' => '/uk/', 'locale' => 'en_GB'],
    ]);

    expect(makeIndex()->languages())->toEqual(['en']);
});

it('derives the language of a localized index only', function () {
    useSites([
        'english' => ['url' => '/', 'locale' => 'en_US'],
        'german' => ['url' => '/de/', 'locale' => 'de_DE'],
    ]);

    expect(makeIndex(locale: 'german')->languages())->toEqual(['de']);
    expect(makeIndex(locale: 'english')->languages())->toEqual(['en']);
});

it('uses explicitly configured languages', function () {
    useSites(['default' => ['url' => '/', 'locale' => 'de_DE']]);

    expect(makeIndex(['languages' => ['en', 'fr']])->languages())->toEqual(['en', 'fr']);
});

it('opts out of language preselection with an empty array', function () {
    useSites(['default' => ['url' => '/', 'locale' => 'de_DE']]);

    expect(makeIndex(['languages' => []])->languages())->toEqual([]);
});

it('passes the derived languages into the loupe configuration', function () {
    useSites(['default' => ['url' => '/', 'locale' => 'de_DE']]);

    expect(makeIndex()->configuration()->getLanguages())->toEqual(['de']);
});

it('decomposes compound words of the derived language', function () {
    useSites(['default' => ['url' => '/', 'locale' => 'de_DE']]);

    Collection::make()->handle('pages')->title('Pages')->save();
    Entry::make()->id('test-1')->collection('pages')->data(['title' => 'Zeitungspapier'])->save();

    expect(collect(Search::index()->lookup('Papier'))->pluck('title'))->toContain('Zeitungspapier');
});

it('does not decompose compound words of another language', function () {
    useSites(['default' => ['url' => '/', 'locale' => 'de_DE']]);
    config(['statamic.search.indexes.default.languages' => ['en']]);

    Collection::make()->handle('pages')->title('Pages')->save();
    Entry::make()->id('test-1')->collection('pages')->data(['title' => 'Zeitungspapier'])->save();

    expect(collect(Search::index()->lookup('Papier')))->toBeEmpty();
});

it('does not decompose compound words when another language is configured', function () {
    useSites(['default' => ['url' => '/', 'locale' => 'de_DE']]);
    config(['statamic.search.indexes.default.languages' => ['fr']]);

    Collection::make()->handle('pages')->title('Pages')->save();
    Entry::make()->id('test-1')->collection('pages')->data(['title' => 'Zeitungspapier'])->save();

    expect(collect(Search::index()->lookup('Papier')))->toBeEmpty();
});

it('decomposes compound words when the language is added back', function () {
    useSites(['default' => ['url' => '/', 'locale' => 'de_DE']]);
    config(['statamic.search.indexes.default.languages' => ['fr', 'de']]);

    Collection::make()->handle('pages')->title('Pages')->save();
    Entry::make()->id('test-1')->collection('pages')->data(['title' => 'Zeitungspapier'])->save();

    expect(collect(Search::index()->lookup('Papier'))->pluck('title'))->toContain('Zeitungspapier');
});

it('still decomposes compound words when opting out of language preselection', function () {
    useSites(['default' => ['url' => '/', 'locale' => 'de_DE']]);
    config(['statamic.search.indexes.default.languages' => []]);

    Collection::make()->handle('pages')->title('Pages')->save();
    Entry::make()->id('test-1')->collection('pages')->data(['title' => 'Zeitungspapier'])->save();

    // Loupe detects the language per document instead of using the configured candidates
    expect(collect(Search::index()->lookup('Papier'))->pluck('title'))->toContain('Zeitungspapier');
});
