<?php

use Illuminate\Support\Facades\File;
use Loupe\Loupe\Exception\InvalidSearchParametersException;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Search;

beforeEach(function () {
    $this->basePath = fixtures_path('indexes', random_int(11, 99999999));
    config(['statamic.search.drivers.loupe.path' => $this->basePath]);

    Collection::make()->handle('pages')->title('Pages')->save();
});

afterEach(function () {
    File::deleteDirectory($this->basePath);
});

function useIndexOptions(array $options): void
{
    config(['statamic.search.indexes.default' => [
        'driver' => 'loupe',
        'searchables' => ['content'],
        'fields' => ['title'],
        ...$options,
    ]]);
}

function indexEntry(string $id, array $data): void
{
    Entry::make()->id($id)->collection('pages')->data($data)->save();
}

function titlesFor(string $query): array
{
    return Search::index()->lookup($query)->pluck('title')->all();
}

function scoresFor(string $query): array
{
    return Search::index()->lookup($query)->mapWithKeys(fn ($hit) => [$hit['title'] => $hit['search_score']])->all();
}

it('ignores stop words in queries', function () {
    useIndexOptions(['stop_words' => ['the']]);

    indexEntry('a', ['title' => 'The Umbrella']);
    indexEntry('b', ['title' => 'Rain Season']);

    expect(titlesFor('the rain'))->toEqual(['Rain Season']);
});

it('matches stop words if none are configured', function () {
    useIndexOptions([]);

    indexEntry('a', ['title' => 'The Umbrella']);
    indexEntry('b', ['title' => 'Rain Season']);

    expect(titlesFor('the rain'))->toContain('The Umbrella', 'Rain Season');
});

it('only searches configured fields', function () {
    useIndexOptions(['fields' => ['title']]);

    indexEntry('a', ['title' => 'Umbrella', 'summary' => 'Xylophone']);

    expect(titlesFor('umbrella'))->toEqual(['Umbrella']);
    expect(titlesFor('xylophone'))->toBeEmpty();
});

it('searches additional fields if configured', function () {
    useIndexOptions(['fields' => ['title', 'summary']]);

    indexEntry('a', ['title' => 'Umbrella', 'summary' => 'Xylophone']);

    expect(titlesFor('xylophone'))->toEqual(['Umbrella']);
});

it('tolerates typos by default', function () {
    useIndexOptions([]);

    indexEntry('a', ['title' => 'Umbrella']);

    expect(titlesFor('umbrela'))->toEqual(['Umbrella']);
});

it('does not tolerate typos if disabled', function () {
    useIndexOptions(['typo_tolerance_enabled' => false]);

    indexEntry('a', ['title' => 'Umbrella']);

    expect(titlesFor('umbrela'))->toBeEmpty();
    expect(titlesFor('umbrella'))->toEqual(['Umbrella']);
});

it('matches short prefixes by default', function () {
    useIndexOptions([]);

    indexEntry('a', ['title' => 'Umbrella']);

    expect(titlesFor('umb'))->toEqual(['Umbrella']);
});

it('requires longer prefixes if configured', function () {
    useIndexOptions(['min_token_length_for_prefix_search' => 6]);

    indexEntry('a', ['title' => 'Umbrella']);

    expect(titlesFor('umb'))->toBeEmpty();
    expect(titlesFor('umbrel'))->toEqual(['Umbrella']);
});

it('limits the number of results', function () {
    useIndexOptions(['hits_per_page' => 2]);

    foreach (range(1, 5) as $i) {
        indexEntry("e{$i}", ['title' => "Umbrella {$i}"]);
    }

    expect(titlesFor('umbrella'))->toHaveCount(2);
});

it('limits the total number of hits', function () {
    useIndexOptions(['max_total_hits' => 3]);

    foreach (range(1, 5) as $i) {
        indexEntry("e{$i}", ['title' => "Umbrella {$i}"]);
    }

    expect(titlesFor('umbrella'))->toHaveCount(3);
});

it('discards results below the ranking score threshold', function () {
    useIndexOptions(['ranking_score_threshold' => 0.9]);

    indexEntry('a', ['title' => 'Umbrella Season']);
    indexEntry('b', ['title' => 'Season Rain Wind Cloud Storm Snow Hail Fog']);

    expect(titlesFor('umbrella season'))->toEqual(['Umbrella Season']);
});

it('keeps low scoring results without a threshold', function () {
    useIndexOptions(['ranking_score_threshold' => 0]);

    indexEntry('a', ['title' => 'Umbrella Season']);
    indexEntry('b', ['title' => 'Season Rain Wind Cloud Storm Snow Hail Fog']);

    expect(titlesFor('umbrella season'))->toHaveCount(2);
});

it('matches any query word by default', function () {
    useIndexOptions([]);

    indexEntry('a', ['title' => 'Umbrella Season']);
    indexEntry('b', ['title' => 'Umbrella']);

    expect(titlesFor('umbrella season'))->toContain('Umbrella Season', 'Umbrella');
});

it('requires all query words with the all matching strategy', function () {
    useIndexOptions(['matching_strategy' => 'all']);

    indexEntry('a', ['title' => 'Umbrella Season']);
    indexEntry('b', ['title' => 'Umbrella']);

    expect(titlesFor('umbrella season'))->toEqual(['Umbrella Season']);
});

it('rejects an unknown matching strategy', function () {
    useIndexOptions(['matching_strategy' => 'some']);

    indexEntry('a', ['title' => 'Umbrella']);

    titlesFor('umbrella');
})->throws(InvalidSearchParametersException::class);

it('limits the number of query tokens', function () {
    useIndexOptions(['max_query_tokens' => 1]);

    indexEntry('a', ['title' => 'Umbrella']);
    indexEntry('b', ['title' => 'Xylophone']);

    expect(titlesFor('umbrella xylophone'))->toEqual(['Umbrella']);
});

it('uses all query tokens by default', function () {
    useIndexOptions([]);

    indexEntry('a', ['title' => 'Umbrella']);
    indexEntry('b', ['title' => 'Xylophone']);

    expect(titlesFor('umbrella xylophone'))->toContain('Umbrella', 'Xylophone');
});

it('tolerates typos in prefixes if enabled', function () {
    useIndexOptions(['typo_tolerance_for_prefix_search' => true]);

    indexEntry('a', ['title' => 'Umbrella']);

    expect(titlesFor('umbrol'))->toEqual(['Umbrella']);
});

it('does not tolerate typos in prefixes by default', function () {
    useIndexOptions(['typo_tolerance_for_prefix_search' => false]);

    indexEntry('a', ['title' => 'Umbrella']);

    expect(titlesFor('umbrol'))->toBeEmpty();
    expect(titlesFor('umbrel'))->toEqual(['Umbrella']);
});

it('applies ranking rules', function () {
    useIndexOptions(['fields' => ['title', 'summary'], 'ranking_rules' => ['attribute']]);

    indexEntry('a', ['title' => 'Rain', 'summary' => 'Umbrella']);
    indexEntry('b', ['title' => 'Umbrella', 'summary' => 'Rain']);

    // The 'attribute' rule ranks matches in earlier fields higher
    expect(titlesFor('umbrella'))->toEqual(['Umbrella', 'Rain']);
    expect(scoresFor('umbrella')['Umbrella'])->toBeGreaterThan(scoresFor('umbrella')['Rain']);
});

it('ignores the field order without the attribute ranking rule', function () {
    useIndexOptions(['fields' => ['title', 'summary'], 'ranking_rules' => ['words']]);

    indexEntry('a', ['title' => 'Rain', 'summary' => 'Umbrella']);
    indexEntry('b', ['title' => 'Umbrella', 'summary' => 'Rain']);

    $scores = scoresFor('umbrella');

    expect($scores['Umbrella'])->toEqual($scores['Rain']);
});
