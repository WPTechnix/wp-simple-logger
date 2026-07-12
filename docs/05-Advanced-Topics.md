# Advanced Topics

This section covers more complex usage patterns, customization, and best practices for production environments.

## Multi-Channel, Multi-Handler Logging

A powerful feature of the library is the ability to route different logs to different handlers. You can combine multiple handlers, each configured for specific channels or severity levels, to create a sophisticated logging strategy.

### Example Scenario

Imagine you have a plugin that processes payments and interacts with an external API. Your logging requirements are:
-   Log **all** events to a file for general debugging.
-   Log all **payment and API** events to the database for auditing.
-   Receive an **email notification** only for **critical errors** related to payments.

```php
<?php
global $wpdb;
use Psr\Log\LogLevel;
use WPTechnix\WP_Simple_Logger\Log_Manager;
use WPTechnix\WP_Simple_Logger\Handlers\File_Handler;
use WPTechnix\WP_Simple_Logger\Handlers\Database_Handler;
use WPTechnix\WP_Simple_Logger\Handlers\Email_Handler;

$manager = new Log_Manager();

// Handler 1: Log everything to a file.
$file_handler = new File_Handler( WP_CONTENT_DIR . '/logs/full_app.log' );
$manager->add_handler( $file_handler ); // No channel or level restriction.

// Handler 2: Log 'payments' and 'api' channels to the database.
$db_handler = new Database_Handler( $wpdb->prefix . 'app_logs' );
$db_handler->set_channels( ['payments', 'api'] ); // Restrict to specific channels.
$manager->add_handler( $db_handler );

// Handler 3: Email only critical errors from the 'payments' channel.
$email_handler = new Email_Handler(
    'admin@example.com', 
    'Critical Payment Error', 
    LogLevel::CRITICAL // Only handles CRITICAL level and higher.
);
$email_handler->set_channels( ['payments'] ); // Only handles the 'payments' channel.
$manager->add_handler($email_handler);

// Initialize the manager to register all hooks.
$manager->init();

// --- Usage Example ---
$payment_logger = $manager->get_logger('payments');
$api_logger = $manager->get_logger('api');
$general_logger = $manager->get_logger('general');

// This goes to File and Database.
$payment_logger->info('Payment initiated.', ['order_id' => 123]);

// This goes to File only.
$general_logger->debug('User session started.');

// This goes to File, Database, AND Email.
$payment_logger->critical('Payment gateway timeout!', ['gateway' => 'Stripe']);
```

## Customization Guide

The library is designed to be extended. You can easily create your own handlers and formatters by implementing the provided interfaces.

### Creating a Custom Handler

WP Simple Logger already ships with handlers for files, the database, email, Slack, generic webhooks, and a null sink (see the **[Handlers Guide](02-Handlers.md)**). If you need a different destination, you can add your own handler in a few lines.

-   Extend `Abstract_Handler` to get level checking, channel filtering, and formatter support for free, then implement `handle(Log_Entry $entry)` and `flush()`.
-   If your destination is an HTTP endpoint, extend `Abstract_Webhook_Handler` instead and implement a single `build_payload()` method. Buffering and the `wp_remote_request()` transport are handled for you.

#### Example: A Discord Handler

Discord incoming webhooks accept a JSON body, so extending `Abstract_Webhook_Handler` is all that is needed:

```php
<?php
use WPTechnix\WP_Simple_Logger\Handlers\Abstract_Webhook_Handler;
use WPTechnix\WP_Simple_Logger\Log_Entry;

class Discord_Handler extends Abstract_Webhook_Handler {

    /**
     * @param array<int, Log_Entry> $entries
     * @return array<string, mixed>
     */
    protected function build_payload( array $entries ): array {
        $lines = array_map(
            static fn ( Log_Entry $entry ): string => sprintf(
                '**%s** [%s] %s',
                strtoupper( $entry->get_level_name() ),
                $entry->get_channel(),
                $entry->get_message()
            ),
            $entries
        );

        return [ 'content' => implode( "\n", $lines ) ];
    }
}

// Usage:
// $manager->add_handler( new Discord_Handler( 'https://discord.com/api/webhooks/...' ) );
```

### Creating a Custom Formatter

To create a custom formatter, implement the `Formatter_Interface`.

1.  Create a class that implements `\WPTechnix\WP_Simple_Logger\Contracts\Formatter_Interface`.
2.  Implement the `format(Log_Entry $entry): string` method.

#### Example: A Simple Message-Only Formatter
```php
<?php
use WPTechnix\WP_Simple_Logger\Contracts\Formatter_Interface;
use WPTechnix\WP_Simple_Logger\Log_Entry;

class Simple_Message_Formatter implements Formatter_Interface {
    public function format(Log_Entry $entry): string {
        return strtoupper($entry->get_level_name()) . ': ' . $entry->get_message() . "\n";
    }
}
```

## Best Practices

-   **Centralize Initialization**: Configure your `Log_Manager` and handlers in one central place (like a service container or a dedicated bootstrap function) to ensure consistency across your application.
-   **Use Channels Effectively**: Adopt a clear and consistent naming convention for your channels. This makes filtering and debugging much easier.
-   **Be Mindful of Sensitive Data**: Avoid logging passwords, API keys, personal user information, or other sensitive data. If you must log potentially sensitive data, consider writing a custom formatter to sanitize or redact it before it's stored.
-   **Use Appropriate Log Levels**: Use `DEBUG` for verbose development information, `INFO` for routine events, `WARNING` for non-critical issues, and `ERROR`/`CRITICAL` for problems that require immediate attention. This makes level-based filtering effective.
-   **Leverage Buffering**: For high-traffic sites, handlers like `Database_Handler` and `File_Handler` benefit from a non-zero buffer limit. This reduces the number of I/O operations by batching writes.

## Edge Cases & Limitations

-   **Fatal Errors**: If a PHP fatal error occurs that prevents WordPress's `shutdown` hook from running, buffered logs may be lost. This is a fundamental limitation of PHP's execution model.
-   **`wp_die()`**: The library automatically hooks into `wp_die` to flush logs before execution terminates, so logs from AJAX handlers or form submissions that end in `wp_die()` should be saved correctly.
