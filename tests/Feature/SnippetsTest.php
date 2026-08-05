<?php

use Daun\StatamicLoupe\Loupe\Index;
use Illuminate\Support\Facades\File;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Search;

const SUMMARY = 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est lorem ipsum dolor sit amet.';

beforeEach(function () {
    $this->basePath = fixtures_path('indexes', random_int(11, 99999999));
    config(['statamic.search.drivers.loupe.path' => $this->basePath]);

    Collection::make()->handle('pages')->title('Pages')->save();
});

afterEach(function () {
    File::deleteDirectory($this->basePath);
});

function configureIndex(array $config): void
{
    config(['statamic.search.indexes.default' => [
        'driver' => 'loupe',
        'searchables' => ['content'],
        'fields' => ['title', 'summary'],
        ...$config,
    ]]);
}

function makeEntry(string $id, string $title, string $summary): void
{
    Entry::make()->id($id)->collection('pages')->data([
        'title' => $title,
        'summary' => $summary,
    ])->save();
}

function snippetsFor(string $query): array
{
    $result = Search::index()->search($query)->get()->first();

    return $result->toAugmentedArray()['search_snippets'];
}

/** Bypasses the IndexManager cache so a test can vary the index config. */
function snippetsWithLength(int $length, string $query = 'lorem ipsum'): array
{
    $index = app()->makeWith(Index::class, ['name' => 'default', 'config' => [
        ...config('statamic.search.indexes.default'),
        'path' => config('statamic.search.drivers.loupe.path'),
        'snippet_attributes' => ['summary' => $length],
    ]]);

    return $index->search($query)->get()->first()->toAugmentedArray()['search_snippets'];
}

function highlightsFor(string $query): array
{
    $result = Search::index()->search($query)->get()->first();

    return $result->toAugmentedArray()['search_highlights'];
}

it('returns no snippets unless configured', function () {
    configureIndex([]);
    makeEntry('test-1', 'Lorem ipsum', SUMMARY);

    expect(snippetsFor('lorem'))->toEqual([]);
});

it('crops and highlights configured attributes', function () {
    configureIndex(['snippet_attributes' => ['summary' => 60]]);
    makeEntry('test-1', 'Lorem ipsum', SUMMARY);

    $snippets = snippetsFor('lorem ipsum');

    expect($snippets)->toHaveKey('summary');
    expect($snippets)->not->toHaveKey('title');
    expect($snippets['summary'])->toContain('<mark>Lorem ipsum</mark>');
    expect($snippets['summary'])->toContain('…');
    expect($snippets['summary'])->not->toEqual(SUMMARY);
    expect(mb_strlen($snippets['summary']))->toBeLessThan(mb_strlen(SUMMARY));
});

it('treats snippet lengths as characters, not words', function () {
    configureIndex(['snippet_attributes' => ['summary' => 30]]);
    makeEntry('test-1', 'Lorem ipsum', SUMMARY);

    // Each fragment is bounded by the crop length in characters, plus
    // whatever the boundary snapping adds to avoid cutting mid-word
    $short = strip_tags(snippetsFor('lorem ipsum')['summary']);
    $long = strip_tags(snippetsWithLength(120)['summary']);

    expect(mb_strlen($short))->toBeLessThan(mb_strlen($long));
    expect(mb_strlen($long))->toBeLessThan(mb_strlen(SUMMARY));
});

it('uses the default snippet length for attributes without one', function () {
    configureIndex(['snippet_attributes' => ['summary'], 'snippet_length' => 40]);
    makeEntry('test-1', 'Lorem ipsum', SUMMARY);

    $snippets = snippetsFor('lorem ipsum');

    expect($snippets)->toHaveKey('summary');
    expect($snippets['summary'])->toContain('<mark>');
    expect(mb_strlen(strip_tags($snippets['summary'])))->toBeLessThan(mb_strlen(SUMMARY));
});

it('limits the number of fragments', function () {
    configureIndex([
        'snippet_attributes' => ['summary' => 30],
        'snippet_max_fragments' => 1,
        'snippet_marker' => '###',
    ]);
    makeEntry('test-1', 'Lorem ipsum', SUMMARY);

    $snippet = snippetsFor('lorem ipsum dolor')['summary'];

    expect(substr_count($snippet, '###'))->toBeLessThanOrEqual(2);
});

it('uses a custom snippet marker', function () {
    configureIndex([
        'snippet_attributes' => ['summary' => 60],
        'snippet_marker' => ' [...] ',
    ]);
    makeEntry('test-1', 'Lorem ipsum', SUMMARY);

    $snippet = snippetsFor('lorem ipsum')['summary'];

    expect($snippet)->toContain('[...]');
    expect($snippet)->not->toContain('…');
});

it('uses custom highlight tags in snippets', function () {
    configureIndex([
        'snippet_attributes' => ['summary' => 60],
        'highlight_tags' => ['<b>', '</b>'],
    ]);
    makeEntry('test-1', 'Lorem ipsum', SUMMARY);

    $snippet = snippetsFor('lorem ipsum')['summary'];

    expect($snippet)->toContain('<b>Lorem ipsum</b>');
    expect($snippet)->not->toContain('<mark>');
});

it('returns the full attribute if shorter than the snippet length', function () {
    configureIndex(['snippet_attributes' => ['title' => 200]]);
    makeEntry('test-1', 'Lorem ipsum', SUMMARY);

    expect(snippetsFor('lorem ipsum')['title'])->toEqual('<mark>Lorem ipsum</mark>');
});

it('keeps highlights and snippets independent for disjoint attributes', function () {
    configureIndex([
        'highlight_attributes' => ['title'],
        'snippet_attributes' => ['summary' => 60],
    ]);
    makeEntry('test-1', 'Lorem ipsum', SUMMARY);

    expect(highlightsFor('lorem ipsum'))->toEqual(['title' => '<mark>Lorem ipsum</mark>']);
    expect(snippetsFor('lorem ipsum')['summary'])->toContain('…');
});

it('crops attributes listed as both highlight and snippet', function () {
    configureIndex([
        'highlight_attributes' => ['summary'],
        'snippet_attributes' => ['summary' => 60],
    ]);
    makeEntry('test-1', 'Lorem ipsum', SUMMARY);

    $result = Search::index()->search('lorem ipsum')->get()->first()->toAugmentedArray();

    // Loupe returns a single formatted value per attribute, so cropping wins
    expect($result['search_highlights']['summary'])->toEqual($result['search_snippets']['summary']);
    expect($result['search_highlights']['summary'])->toContain('…');
});
