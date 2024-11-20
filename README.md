# Statamic Loupe Search Driver

**This addon provides a [Loupe](https://github.com/loupe-php/loupe) search driver for Statamic sites.**

## Loupe

Loupe is a local SQLite search engine that is easy to set up and requires no additional
infrastructure.

- Only requires PHP and SQLite, nothing else
- Tolerates typos and supports stemming
- Supports phrase search using `"quotation marks"`
- Supports filtering and ordering on geo distance
- Sorts by relevance

## Installation

```sh
composer require daun/statamic-loupe
```

Add the new driver to the `statamic/search.php` config file.

```php
'drivers' => [

    // other drivers

    'loupe' => [
        'path' => storage_path('statamic/loupe'),
    ],
],
```

## License

[MIT](https://opensource.org/licenses/MIT)
