# Changelog

All notable changes to this project are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-09-29

Initial release.

### Added

- Private, per-user journal JSON API for posts and tags, plus shared categories.
- Other users' posts and tags return `404`, not `403`.
- Category management behind the `manage-journal-categories` gate, denied by default.
- Search (with literal `%` and `_`), sorting and pagination on every list endpoint; posts also filter by `category` and `tag`.
- Automatic, per-user unique post slugs; tags created on the fly from a post's `tags` array.
- `posts_count` on tags and categories; category counts include only the viewer's own posts.
- Configurable route prefix, middleware, name prefix, user model (int, UUID or ULID keys) and page sizes.
- `run_migrations` option for apps that publish the migrations.
- Support for Laravel 12 and 13 on PHP 8.2+.

[Unreleased]: https://github.com/jakub-gawlik/laravel-journal/compare/v1.0.0...main
[1.0.0]: https://github.com/jakub-gawlik/laravel-journal/releases/tag/v1.0.0
