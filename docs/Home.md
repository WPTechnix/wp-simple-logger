# WP Simple Logger: Overview

WP Simple Logger is a powerful, flexible, and developer-friendly PSR-3 compliant logging library for WordPress. It provides a robust framework for capturing, managing, and storing application events, errors, and debugging information in a structured and efficient way.

Designed to be both simple for basic use and highly extensible for complex applications, this library is the ideal choice for plugin and theme developers who need a reliable logging solution that goes beyond `error_log()`.

## Key Features

-   **PSR-3 Compliant**: Implements the standard PHP logger interface, ensuring interoperability and a familiar API for developers (`$logger->info(...)`, `$logger->error(...)`, etc.).
-   **Channel-Based Logging**: Organize logs into different "channels" (e.g., 'payments', 'api', 'debug') to easily separate and filter logs from different parts of your application.
-   **Multiple Handlers**: Ship logs to various destinations simultaneously. Built-in handlers include:
    -   **File Handler**: Writes logs to a server file.
    -   **Database Handler**: Stores logs in a custom, optimized database table.
    -   **Email Handler**: Sends high-priority logs via email notifications.
    -   **Slack Handler**: Posts color-coded alerts to a Slack channel.
    -   **Webhook Handler**: Ships structured JSON logs to any HTTP endpoint.
    -   **Null Handler**: Discards logs, handy for tests or disabling output.
-   **Customizable Formatters**: Control the exact output format of your log records. Built-in formatters include:
    -   **Line Formatter**: A single, customizable line of text.
    -   **JSON Formatter**: Structured JSON, perfect for log aggregation services.
    -   **HTML Formatter**: Richly formatted HTML, used by the Email Handler.
-   **Built-in Log Viewer**: The Database Handler includes a beautiful, fully-featured admin interface for viewing, searching, and filtering logs directly within the WordPress dashboard.
-   **Performance-Oriented**: Logs are buffered in memory and written in batches during the `shutdown` hook, minimizing performance impact on page loads.
-   **Extensible Architecture**: Easily create your own custom handlers (e.g., for Discord, Sentry, or other APIs) and formatters to meet your project's specific needs.

## When to Use It

-   Debugging complex issues in development or production environments.
-   Auditing critical events like user actions, payment transactions, or API calls.
-   Monitoring application health and capturing unexpected errors.
-   Providing detailed diagnostic information for support teams.
-   Building robust, professional WordPress plugins and themes.

## Next Steps

Ready to get started? Head over to the **[Getting Started](01-Getting-Started.md)** guide to install the library and log your first message.
