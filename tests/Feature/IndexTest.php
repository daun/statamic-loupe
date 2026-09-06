<?php

use Daun\StatamicLoupe\Loupe\Index;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Loupe\Loupe\Loupe;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Search;
use Statamic\Search\Documents;
use Statamic\Search\InsertMultipleJob;

beforeEach(function () {
    $this->basePath = fixtures_path('indexes', random_int(11, 99999999));
    config(['statamic.search.drivers.loupe.path' => $this->basePath]);
});

afterEach(function () {
    File::deleteDirectory($this->basePath);
});

it('builds the correct paths and directories', function () {
    $index = Search::index();

    expect($index->base())->toEqual($this->basePath.'/');
    expect($index->dir())->toEqual($this->basePath.'/default');
    expect($index->path())->toEqual($this->basePath.'/default/loupe.db');
});

it('uses custom index name for paths and directories', function () {
    $index = Search::index('pages');

    expect($index->base())->toEqual($this->basePath.'/');
    expect($index->dir())->toEqual($this->basePath.'/pages');
    expect($index->path())->toEqual($this->basePath.'/pages/loupe.db');
});

it('creates a Loupe client', function () {
    $index = Search::index();

    expect($index->client())->toBeInstanceOf(Loupe::class);
});

it('only creates an index if required', function () {
    $index = Search::index();
    expect($index->exists())->toBeFalse();

    $client = $index->client();

    expect($index->exists())->toBeTrue();
});

it('adds documents to the index', function () {
    $index = Search::index();

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

it('keeps existing documents searchable while an update is queued', function () {
    $index = Search::index();

    Collection::make()->handle('pages')->title('Pages')->save();

    Entry::make()
        ->id('test-1')
        ->collection('pages')
        ->data(['title' => 'Entry 1'])
        ->save();

    expect($index->client()->countDocuments())->toBe(1);

    Queue::fake();

    $index->update();

    Queue::assertPushed(InsertMultipleJob::class);
    expect($index->client()->countDocuments())->toBe(1);
});

it('prunes stale documents across all browse result pages', function () {
    $index = Search::index();
    $documents = collect(range(1, 1001))->mapWithKeys(
        fn (int $id) => ["stale::$id" => ['title' => "Stale $id"]]
    );

    $index->insertDocuments(new Documents($documents));

    expect($index->client()->countDocuments())->toBe(1001);

    Queue::fake();

    $index->update();

    expect($index->client()->countDocuments())->toBe(0);
});

it('deletes the index contents from its directory', function () {
    $index = Search::index();
    $index->client();

    expect($index->exists())->toBeTrue();

    (fn () => $this->deleteIndex())->call($index);

    expect($index->exists())->toBeFalse();
});

it('updates documents in the index', function () {
    $index = Search::index();

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
    $index = Search::index();

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

it('discards a stale index when the configuration changed', function () {
    Collection::make()->handle('pages')->title('Pages')->save();

    Entry::make()->id('test-1')->collection('pages')->data(['title' => 'Entry 1'])->save();

    expect(collect(Search::index()->lookup('Entry'))->pluck('title'))->toContain('Entry 1');

    /** @var Index */
    $reconfigured = app()->makeWith(Index::class, ['name' => 'default', 'config' => [
        ...config('statamic.search.indexes.default'),
        'path' => config('statamic.search.drivers.loupe.path'),
        'stop_words' => ['the'],
    ]]);

    expect($reconfigured->client()->needsReindex())->toBeTrue();

    $reconfigured->insertDocuments(new Documents(['test-2' => ['title' => 'Entry 2']]));

    // Documents indexed under the previous configuration are dropped
    $results = collect($reconfigured->lookup('Entry'))->pluck('title');
    expect($results)->toContain('Entry 2');
    expect($results)->not->toContain('Entry 1');

    // Following inserts keep appending again
    $reconfigured->insertDocuments(new Documents(['test-3' => ['title' => 'Entry 3']]));

    expect(collect($reconfigured->lookup('Entry'))->pluck('title'))->toContain('Entry 2', 'Entry 3');
});
