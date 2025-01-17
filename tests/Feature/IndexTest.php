<?php

use Loupe\Loupe\Loupe;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Search;

beforeEach(function () {
    $this->basePath = fixtures_path('indexes/'.random_int(11, 99999999));

    config(['statamic.search.drivers.loupe.path' => $this->basePath]);
});

it('builds the correct paths and directories', function () {
    $index = Search::index('loupe_index');

    expect($index->base())->toEqual($this->basePath . '/');
    expect($index->dir())->toEqual($this->basePath . '/loupe_index');
    expect($index->path())->toEqual($this->basePath . '/loupe_index/loupe.db');
});

it('only creates an index if required', function () {
    $index = Search::index('loupe_index');
    expect($index->exists())->toBeFalse();

    $client = $index->client();

    expect($index->exists())->toBeTrue();
});

it('creates a Loupe client', function () {
    $index = Search::index('loupe_index');

    expect($index->client())->toBeInstanceOf(Loupe::class);
});

it('adds documents to the index', function () {
    $index = Search::index('loupe_index');

    $this->assertCount(0, $index->lookup('Entry'));

    $collection = Collection::make()
        ->handle('pages')
        ->title('Pages')
        ->save();

    $entry1 = Entry::make()
        ->id('test-1')
        ->collection('pages')
        ->data(['title' => 'Entry 1']);
    $entry1->save();

    $entry2 = Entry::make()
        ->id('test-2')
        ->collection('pages')
        ->data(['title' => 'Entry 2']);
    $entry2->save();

    $this->assertCount(2, $index->lookup('Entry'));
});

it('updates documents in the index', function () {
    $index = Search::index('loupe_index');

    $collection = Collection::make()
        ->handle('pages')
        ->title('Pages')
        ->save();

    $entry1 = Entry::make()
        ->id('test-1')
        ->collection('pages')
        ->data(['title' => 'Entry 1']);
    $entry1->save();

    $entry2 = Entry::make()
        ->id('test-2')
        ->collection('pages')
        ->data(['title' => 'Entry 2']);
    $entry2->save();

    $results = collect($index->lookup('Entry'))->pluck('title');

    $this->assertContains('Entry 1', $results);
    $this->assertContains('Entry 2', $results);

    $entry2->merge(['title' => 'Entry 2 Updated'])->save();

    $results = collect($index->lookup('Entry'))->pluck('title');

    $this->assertContains('Entry 2 Updated', $results);
});

it('removes documents from the index', function () {
    $index = Search::index('loupe_index');

    $collection = Collection::make()
        ->handle('pages')
        ->title('Pages')
        ->save();

    $entry1 = Entry::make()
        ->id('test-1')
        ->collection('pages')
        ->data(['title' => 'Entry 1']);
    $entry1->save();

    $entry2 = Entry::make()
        ->id('test-2')
        ->collection('pages')
        ->data(['title' => 'Entry 2']);
    $entry2->save();

    $results = collect($index->lookup('Entry'))->pluck('title');

    $this->assertContains('Entry 1', $results);
    $this->assertContains('Entry 2', $results);

    $entry2->delete();

    $results = collect($index->lookup('Entry'))->pluck('title');

    $this->assertContains('Entry 1', $results);
    $this->assertNotContains('Entry 2', $results);
});
