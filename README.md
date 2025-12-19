# Statamic Loupe Search Driver

[![Latest Version on Packagist](https://img.shields.io/packagist/v/daun/statamic-loupe.svg)](https://packagist.org/packages/daun/statamic-loupe) [![Test Status](https://img.shields.io/github/actions/workflow/status/daun/statamic-loupe/ci.yml?label=tests)](https://github.com/daun/statamic-loupe/actions/workflows/ci.yml) [![License](https://img.shields.io/github/license/daun/statamic-loupe.svg)](https://github.com/daun/statamic-loupe/blob/master/LICENSE) <!-- [![Code Coverage](https://img.shields.io/codecov/c/github/daun/statamic-loupe)](https://app.codecov.io/gh/daun/statamic-loupe) -->

**This addon provides a [Loupe](https://github.com/loupe-php/loupe) search driver for Statamic sites.**

## Loupe

...is a local SQLite search engine that is easy to set up and requires no additional infrastructure.

- Only requires PHP and SQLite, nothing else
- Tolerates typos and supports stemming
- Supports `-negated` queries and `"phrase search"`
- Supports filtering and ordering on geo distance
- Sorts by relevance

## This addon

- Manages search indexes behind the scenes
- Provides highlights and snippets for contextual search results

## Requirements

- SQLite PDO 3.35 or higher

## Installation

```sh
composer require daun/statamic-loupe
```

Add the new driver to `statamic/search.php`.

```diff
'drivers' => [
    'local' => ['path' => storage_path('statamic/search')],
+   'loupe' => [],
],
```

Now set your indexes to use the new driver.

```diff
'indexes' => [
    'default' => [
-       'driver' => 'local',
+       'driver' => 'loupe',
        'searchables' => 'content',
    ],
],
```

## Configuration

While Loupe will work just fine with the default settings, there are a few knobs
you can turn to fine-tune the indexing and ranking of results.
The values below are the default values. Most of these map directly to [Loupe's
configuration items](https://github.com/loupe-php/loupe/blob/main/docs/configuration.md).

```php
'drivers' => [
    'loupe' => [
        // Storage directory of Loupe's index database
        'path' => storage_path('statamic/loupe'),

        // Number of documents to process at once during indexing
        // Helps to limit memory consumption at the cost of indexing speed
        'chunk' => 100,

        // Maximum number of words allowed in a search
        // Higher values allow more complex queries but may impact performance
        'max_query_tokens' => 10,

        // Minimum word length to allow searching by prefix
        'min_token_length_for_prefix_search' => 2,

        // Languages to consider for detecting stemming language
        // Not required for stemming, but speeds things up if they are known
        'stemming_languages' => [],

        // Whether typo tolerance is enabled
        'typo_tolerance_enabled' => true,

        // Size of the alphabet used for typo tolerance
        'typo_tolerance_alphabet_size' => 4,

        // Maximum length of terms to index for typo tolerance
        'typo_tolerance_index_length' => 14,

        // Whether typo tolerance is enabled in prefix search
        'typo_tolerance_for_prefix_search' => false,

        // Minimum ranking score of results to return (between `0.0` and `1.0`)
        'ranking_score_threshold' => 0,
    ],
],
```

## Search highlights

Enable term highlighting to wrap occurrences of search words in `<mark>` tags. You'll need to
explicitly define the attributes to apply highlighting in.

```diff
'indexes' => [
    'default' => [
        'driver' => 'loupe',
        'searchables' => 'content',
+       'highlight_attributes' => ['title', 'summary'],
    ],
],
```

You can now display the configured fields from the `search_highlights` namespace:

```antlers
{{ search:results }}
  <h2>{{ search_highlights:title }}</h2>
  <p>{{ search_highlights:summary }}</p>
{{ /search:results }}
```

You can also configure the exact tags to use for highlighting terms:

```diff
'indexes' => [
    'default' => [
        'driver' => 'loupe',
        'searchables' => 'content',
        'highlight_attributes' => ['title', 'summary'],
+       'highlight_tags' => ['<span class="highlight">', '</span>'],
    ],
],
```

## Search snippets

Snippets are condensed highlights collecting only the actual matches and the text immediately
surrounding them. This allows quick skimming of search results for relevancy and context.

> <mark>Lorem ipsum</mark> dolor sit amet, consetetur ... no sea takimata sanctus est <mark>lorem</mark> est <mark>ipsum</mark> dolor sit amet. <mark>Lorem ipsum</mark> dolor sit amet, consetetur ... dolore te feugait nulla facilisi <mark>lorem ipsum</mark> dolor sit amet, consectetuer ...

To enable snippets, define the attributes you want to generate them for, as well
as the number of words to include around each match.

```diff
'indexes' => [
    'default' => [
        'driver' => 'loupe',
        'searchables' => 'content',
+       'snippet_attributes' => ['title' => 5, 'summary' => 10],
    ],
],
```

Then use the `search_snippets` namespace to display the formatted fields:

```antlers
{{ search:results }}
  <h2>{{ search_snippets:title }}</h2>
  <p>{{ search_snippets:summary }}</p>
{{ /search:results }}
```

## License

[MIT](https://opensource.org/licenses/MIT)
