# Formatters Guide

Formatters convert a `Log_Entry` object into a string representation. They are used by the text-based handlers (`File_Handler` and `Email_Handler`), each of which has a sensible default formatter that you can override. Handlers that emit structured data (such as `Database_Handler` and the webhook handlers) build their own output and do not use a formatter.

## How to Use a Formatter

You can set a formatter in two ways:

1.  **Via the Handler's Constructor**:
    ```php
    use WPTechnix\WP_Simple_Logger\Handlers\File_Handler;
    use WPTechnix\WP_Simple_Logger\Formatters\Json_Formatter;
    
    $formatter = new Json_Formatter();
    $handler = new File_Handler( $path, 'debug', 0, $formatter );
    ```

2.  **Via the `set_formatter()` Method**:
    ```php
    use WPTechnix\WP_Simple_Logger\Handlers\File_Handler;
    use WPTechnix\WP_Simple_Logger\Formatters\Json_Formatter;

    $handler = new File_Handler( $path );
    $handler->set_formatter( new Json_Formatter() );
    ```

---

## Line Formatter

Formats a log record into a single, customizable line of text. This is the default formatter for the `File_Handler`.

### Constructor

`new Line_Formatter(?string $format = null, bool $ignore_empty_context = true)`

-   **`$format`** (`?string`): A format string with placeholders. If `null`, it defaults to: `[%timestamp%] %channel%.%level_name%: %message% %context%`
-   **`$ignore_empty_context`** (`bool`): If `true`, the `%context%` placeholder (and any preceding space) is removed if the context array is empty.

### Available Placeholders

-   `%timestamp%`: The formatted date and time of the log (e.g., `2023-10-27 15:30:00`).
-   `%channel%`: The channel name (e.g., `payments`).
-   `%level_name%`: The uppercase log level name (e.g., `INFO`, `ERROR`).
-   `%message%`: The log message itself, with any `{placeholder}` tokens already interpolated from the context.
-   `%context%`: The context data, rendered as a JSON string.

### Usage Example

```php
use WPTechnix\WP_Simple_Logger\Formatters\Line_Formatter;

// Create a custom TSV (Tab-Separated Values) format
$format = "%timestamp%\t%level_name%\t%channel%\t%message%\t%context%\n";
$tsv_formatter = new Line_Formatter($format);

$file_handler->set_formatter($tsv_formatter);
```

#### Example Output (Default Format)

`[2023-10-27 15:30:00] payments.INFO: Transaction successful. {"transaction_id":"txn_123"}`

---

## JSON Formatter

Serializes the entire log record into a JSON string, with each log on a new line. This is ideal for sending logs to external services like Elasticsearch or Datadog.

### Constructor

`new Json_Formatter(?array $keys_to_include = null)`

-   **`$keys_to_include`** (`?array`): An array of keys to include in the final JSON output. If `null`, it defaults to `['datetime', 'channel', 'level', 'levelName', 'message', 'context']`.

### Available Keys

-   `id`: The database ID (only available when reading from DB).
-   `datetime`: The full UTC datetime string (`Y-m-d H:i:s`).
-   `timestamp`: The Unix timestamp.
-   `channel`: The channel name.
-   `level`: The integer priority of the log level.
-   `levelName`: The string name of the log level (e.g., `info`).
-   `message`: The log message.
-   `context`: The context data object/array.

### Usage Example

```php
use WPTechnix\WP_Simple_Logger\Formatters\Json_Formatter;

// Create a formatter that only includes a specific set of keys
$json_formatter = new Json_Formatter(
    keys_to_include: ['timestamp', 'levelName', 'message', 'context']
);

$file_handler->set_formatter($json_formatter);
```

#### Example Output
```json
{"timestamp":1698409800,"levelName":"info","message":"Transaction successful.","context":{"transaction_id":"txn_123"}}
```

---

## HTML Formatter

Formats a log record into a styled HTML table row. This is designed for human readability and is the default formatter for the `Email_Handler`.

### Constructor

`new Html_Formatter()`

This formatter has no constructor arguments.

### Usage Example

This formatter is primarily used internally by the `Email_Handler`, but you could use it with a `File_Handler` to create an HTML log file.

```php
use WPTechnix\WP_Simple_Logger\Formatters\Html_Formatter;

$html_formatter = new Html_Formatter();
$file_handler->set_formatter($html_formatter);
// The resulting file would be a full HTML document of log entries.
```

### Example Output

The `Html_Formatter` produces a block of HTML that is styled for readability in an email client. See the screenshot in the **[Handlers Guide](02-Handlers.md)** for a visual example.
