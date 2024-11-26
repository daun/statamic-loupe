use Daun\StatamicLoupe\Search\Snippets;

<?php

use Daun\StatamicLoupe\Search\Snippets;

it('returns empty string for empty input', function () {
    $snippets = new Snippets();
    expect($snippets->generate(''))->toEqual('');
});

it('returns empty string when no start tag is found', function () {
    $snippets = new Snippets();
    expect($snippets->generate('Lorem ipsum dolor sit amet.'))->toEqual('');
});

it('returns snippet with single match unchanged', function () {
    $snippets = new Snippets();
    $text = 'Lorem ipsum dolor sit amet, <mark>consectetur</mark> adipiscing elit.';
    $expected = 'Lorem ipsum dolor sit amet, <mark>consectetur</mark> adipiscing elit.';
    expect($snippets->generate($text))->toEqual($expected);
});

it('returns snippet with multiple matches unchanged', function () {
    $snippets = new Snippets();
    $text = 'Lorem ipsum <mark>dolor</mark> sit amet, <mark>consectetur</mark> adipiscing elit.';
    $expected = 'Lorem ipsum <mark>dolor</mark> sit amet, <mark>consectetur</mark> adipiscing elit.';
    expect($snippets->generate($text))->toEqual($expected);
});

it('returns snippet with surrounding words', function () {
    $snippets = new Snippets();
    $text = 'This is a <mark>test</mark> string with some surrounding words.';
    $expected = 'This is a <mark>test</mark> string with some surrounding words.';
    expect($snippets->generate($text))->toEqual($expected);
});

it('truncates space between distant matches', function () {
    $snippets = new Snippets();
    $text = 'Lorem ipsum <mark>dolor</mark> sit amet consectetur adipiscing dolor sit elit lorem amet ipsum dolor sit amet <mark>consectetur</mark> adipiscing elit.';
    $expected = 'Lorem ipsum <mark>dolor</mark> sit amet consectetur adipiscing dolor ... amet ipsum dolor sit amet <mark>consectetur</mark> adipiscing elit.';
    expect($snippets->generate($text))->toEqual($expected);
});

it('truncates space before first match', function () {
    $snippets = new Snippets();
    $text = 'Lorem ipsum dolor sit amet consectetur adipiscing <mark>dolor</mark> sit';
    $expected = '... dolor sit amet consectetur adipiscing <mark>dolor</mark> sit';
    expect($snippets->generate($text))->toEqual($expected);
});

it('truncates space after first match', function () {
    $snippets = new Snippets();
    $text = 'Lorem ipsum dolor <mark>sit</mark> amet consectetur adipiscing dolor sit amet consectetur adipiscing';
    $expected = 'Lorem ipsum dolor <mark>sit</mark> amet consectetur adipiscing dolor sit ...';
    expect($snippets->generate($text))->toEqual($expected);
});

it('allow customizing the number of words', function () {
    $snippets = new Snippets(surroundingWords: 3);
    $text = 'Lorem ipsum <mark>dolor</mark> sit amet consectetur adipiscing dolor sit elit lorem amet ipsum dolor sit amet <mark>consectetur</mark> adipiscing elit.';
    $expected = 'Lorem ipsum <mark>dolor</mark> sit amet consectetur ... dolor sit amet <mark>consectetur</mark> adipiscing elit.';
    expect($snippets->generate($text))->toEqual($expected);
});

it('handles custom tag', function () {
    $snippets = new Snippets('[start]', '[end]');
    $text = 'Lorem ipsum dolor sit amet consectetur adipiscing [start]dolor[end] sit';
    $expected = '... dolor sit amet consectetur adipiscing [start]dolor[end] sit';
    expect($snippets->generate($text))->toEqual($expected);
});

it('handles custom separator', function () {
    $snippets = new Snippets(separator: '???');
    $text = 'Lorem ipsum dolor sit amet consectetur adipiscing <mark>dolor</mark> sit';
    $expected = '??? dolor sit amet consectetur adipiscing <mark>dolor</mark> sit';
    expect($snippets->generate($text))->toEqual($expected);
});
