# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2026-07-17

### Added

- `Contracts\Normalizer_Interface` — injectable contract for context normalization, implemented by `Normalizers\Data_Normalizer`
- `Utils\Json_Encoder` — centralized JSON encoding with Monolog-compatible default flags (`JSON_UNESCAPED_SLASHES`, `JSON_UNESCAPED_UNICODE`, `JSON_PRESERVE_ZERO_FRACTION`, `JSON_INVALID_UTF8_SUBSTITUTE`, `JSON_PARTIAL_OUTPUT_ON_ERROR`)
- `Utils\Debug_Logger` — internal error reporting helper, guarded by `WP_DEBUG`, replacing inline `error_log()` calls across handlers
- `Database\Table_Name_Validator` — shared validation for log table names, used by both `Log_Repository` and `Database_Installer`
- `Database_Handler` constructor now accepts optional `Log_Repository` and `Database_Installer` instances for dependency injection in tests
- `Log_Manager` constructor now accepts an optional `Normalizer_Interface` for custom normalization strategies
- `#[Override]` attribute on all handler and formatter methods for PHP 8.3+ compatibility

### Changed

- **Breaking:** Context normalization rewritten to match Monolog's `NormalizerFormatter` exactly:
  - Objects (including `stdClass`, `JsonSerializable`, and `__toString`-able instances) now normalize to a class-keyed array, e.g. `{"App\Foo": {"bar": 1}}`, instead of opaque `"[object(App\Foo)]"` strings
  - Exception traces now record `file:line` per stack frame instead of the full `getTraceAsString()` text, reducing payload size and avoiding leaked function arguments
  - `DateTime` (mutable) values are now normalized alongside `DateTimeImmutable` via the broader `DateTimeInterface`
  - Default date format changed to `Y-m-d\TH:i:sP` (Monolog's `SIMPLE_DATE`), omitting microsecond precision
  - `Closure` values now normalize as objects (`{"Closure": {}}`) instead of a bespoke `"[Closure]"` string
  - Depth-limit and array-truncation messages reworded to Monolog's exact phrasing (e.g. `"Over 9 levels deep, aborting normalization"`)
- **Breaking:** JSON encoding across all formatters and handlers now uses `Utils\Json_Encoder`, applying Monolog's default flag set consistently
- `Database\Log_Repository` and `Database\Database_Installer` relocated from `src/Handlers/Database/` to `src/Database/`
- `Data_Normalizer` relocated from `src/Utils/` to `src/Normalizers/` and now implements `Normalizer_Interface`
- `Log_List_Table` and `Log_Viewer` use `Json_Encoder` for consistent JSON output in the admin context viewer
- PHPStan analysis level increased from **8 to 10** for stricter static analysis
- Test suite restructured into organized subdirectories: `Handlers/`, `Formatters/`, `Database/`, `Utils/`, `Normalizers/`

### Removed

- `Abstract_Formatter` — formatters now implement `Formatter_Interface` directly
- `Utils\Data_Normalizer` — replaced by `Normalizers\Data_Normalizer`
- `@package` PHPDoc tags throughout the codebase

### Dev

- Updated `wptechnix/wp-coding-standards` dev dependency from `^1.0` to `^1.1.0`
- CI matrix now includes PHP 8.5
- Migrated dependency updates from Dependabot to Renovate with grouped automerge rules

## [1.0.0] - 2025-07-12

### Added

- PSR-3 compliant logging with 8 severity levels (debug through emergency)
- Channel-based log isolation for organizing logs by subsystem
- Log Manager as a central hub with singleton-safe channel retrieval
- File Handler for buffered, append-only log writing with automatic directory creation
- Database Handler with auto-installing schema, log expiry, and a full admin log viewer
- Email Handler for buffered HTML notifications with customizable content
- Slack Handler posting color-coded attachment alerts via Incoming Webhooks
- Webhook Handler for shipping structured JSON to any HTTP endpoint
- Null Handler for discarding logs in tests or specific environments
- Line Formatter with customizable template and automatic empty-context suppression
- JSON Formatter with whitelist-based field selection
- HTML Formatter with severity-colored headers and formatted context display
- Admin log viewer with search, channel/level filtering, sortable columns, bulk delete, and per-channel or full clear actions
- Context data normalization handling exceptions, closures, DateTimeImmutable, JsonSerializable, and nested arrays safely
- Automatic log flushing on shutdown, before redirects, and before wp_die
- Handler-level channel scoping, minimum level thresholds, and configurable buffer sizes
- Fluent configuration API on all handlers (set_channels, set_formatter, set_buffer_limit, etc.)
- Handler and Formatter interfaces for custom extensions
- PHP 8.0+ with typed properties, named arguments, and match expressions
- Comprehensive test suite with PHPUnit, Brain Monkey, and a Spy_Handler for integration testing
- PHPStan and PHPCS tooling with Docker-based development environment
