<?php

use Loupe\Loupe\Loupe;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Search;

it('creates a Loupe client', function () {
    $index = Search::index('loupe_index');

    expect($index->client())->toBeInstanceOf(Loupe::class);
});

it('adds documents to the index', function () {
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

    sleep(1); // give it some time to process

    $index = Search::index('loupe_index');

    $this->assertCount(2, $index->lookup('Entry'));
});

it('updates documents in the index', function () {
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

    sleep(1); // give it some time to process

    $index = Search::index('loupe_index');

    $results = collect($index->lookup('Entry'))->pluck('title');

    $this->assertContains('Entry 1', $results);
    $this->assertContains('Entry 2', $results);

    $entry2->merge(['title' => 'Entry 2 Updated'])->save();

    sleep(1); // give it some time to process

    $results = collect($index->lookup('Entry'))->pluck('title');

    $this->assertContains('Entry 2 Updated', $results);
});
