# Statamic Loupe Search Driver

**This addon provides a [Loupe](https://github.com/loupe-php/loupe) search driver for Statamic sites.**

## Loupe

Loupe is a local SQLite search engine that is easy to set up and requires no additional
infrastructure.

- Only requires PHP and SQLite, nothing else
- Tolerates typos and supports stemming
- Supports `-negated` queries and `"phrase search"`
- Supports filtering and ordering on geo distance
- Sorts by relevance

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
        'searchables' => 'all',
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
        // Fields to index
        'fields' => ['title'],

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

## License

[MIT](https://opensource.org/licenses/MIT)
