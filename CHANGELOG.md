# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
