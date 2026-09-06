# Changelog

## [4.2.0] - 2026-09-06

- Keep index searchable while it's being updated
- Use the configured primary key consistently

## [4.1.0] - 2026-08-07

- Add config option for the matching strategy of multi-word queries

## [4.0.0] - 2026-08-06

- Upgrade loupe to 1.0
- Add config options for stop words and ranking rules
- Decompose compound words when searching German and English content
- Limit language detection to configured sites for faster indexing
- Use loupe's built-in cropping feature to generate search snippets
- Breaking change: snippet context length is now measured in characters instead of words
- Breaking change: all snippet attributes are now also highlighted

## [3.1.0] - 2026-03-24

- Add support for Laravel 13

## [3.0.0] - 2026-02-08

- Add support for Statamic 6
- Drop support for Statamic 4 and 5

## [2.0.0] - 2025-12-19

- Upgrade loupe to 0.13
- Much improved performance and concurrency handling
- Requires SQLite 3.35 or higher (possibly breaking change)

## [1.7.0] - 2025-06-22

- Upgrade loupe to 0.12

## [1.6.0] - 2025-03-06

- Add support for Laravel 12

## [1.5.0] - 2025-01-17

- Upgrade loupe to 0.9
- More efficient index handling
- Expand test coverage

## [1.4.2] - 2024-11-27

- Use correct document id when deleting
- Expand snippets to 5 surrounding words
- Add test coverage

## [1.4.1] - 2024-11-26

- Fix constructor signature
- Document path config

## [1.4.0] - 2024-11-26

- Provide condensed search snippets

## [1.3.0] - 2024-11-26

- Provide search highlights

## [1.2.0] - 2024-11-26

- Make ranking score threshold configurable
- Add documentation for config flags

## [1.1.0] - 2024-11-25

- Upgrade loupe

## [1.0.2] - 2024-11-22

- Do not pin loupe version

## [1.0.1] - 2024-11-21

- Update readme

## [1.0.0] - 2024-11-20

- Initial release 🎉

[4.1.0]: https://github.com/daun/statamic-loupe/releases/tag/4.1.0
[4.0.0]: https://github.com/daun/statamic-loupe/releases/tag/4.0.0
[3.1.0]: https://github.com/daun/statamic-loupe/releases/tag/3.1.0
[3.0.0]: https://github.com/daun/statamic-loupe/releases/tag/3.0.0
[2.0.0]: https://github.com/daun/statamic-loupe/releases/tag/2.0.0
[1.7.0]: https://github.com/daun/statamic-loupe/releases/tag/1.7.0
[1.6.0]: https://github.com/daun/statamic-loupe/releases/tag/1.6.0
[1.5.0]: https://github.com/daun/statamic-loupe/releases/tag/1.5.0
[1.4.2]: https://github.com/daun/statamic-loupe/releases/tag/1.4.2
[1.4.1]: https://github.com/daun/statamic-loupe/releases/tag/1.4.1
[1.4.0]: https://github.com/daun/statamic-loupe/releases/tag/1.4.0
[1.3.0]: https://github.com/daun/statamic-loupe/releases/tag/1.3.0
[1.2.0]: https://github.com/daun/statamic-loupe/releases/tag/1.2.0
[1.1.0]: https://github.com/daun/statamic-loupe/releases/tag/1.1.0
[1.0.2]: https://github.com/daun/statamic-loupe/releases/tag/1.0.2
[1.0.1]: https://github.com/daun/statamic-loupe/releases/tag/1.0.1
[1.0.0]: https://github.com/daun/statamic-loupe/releases/tag/1.0.0
