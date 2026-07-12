# Getting Started

This guide will walk you through installing the library and setting up a basic logging system in your WordPress project.

## Installation Requirements

-   PHP 8.0 or higher
-   WordPress 5.0 or higher (for the Database Handler admin UI)
-   [Composer](https://getcomposer.org/) for dependency management

WP Simple Logger is designed to be installed as a Composer dependency within your plugin or theme.

```bash
composer require wptechnix/wp-simple-logger
```

After installation, ensure you include Composer's autoloader in your project's main startup file:

```php
require_once __DIR__ . '/vendor/autoload.php';
```

## Basic Setup and Usage

The core of the library is the `Log_Manager` class, which orchestrates all loggers and handlers. The following example demonstrates how to set up a logger that writes to a file.

Place this code in your main plugin file or theme's `functions.php`.

```php
<?php

use WPTechnix\WP_Simple_Logger\Log_Manager;
use WPTechnix\WP_Simple_Logger\Handlers\File_Handler;

// A central function to initialize and retrieve the logger manager.
function my_project_logger_manager(): Log_Manager {
    static $manager = null;

    if ( null === $manager ) {
        // 1. Create the Log_Manager instance.
        $manager = new Log_Manager();

        // 2. Create a Handler. Let's use the File_Handler.
        // It will write logs to wp-content/uploads/logs/my-project.log
        $log_file_path = WP_CONTENT_DIR . '/uploads/logs/my-project.log';
        $file_handler = new File_Handler( $log_file_path );

        // 3. Add the handler to the manager.
        $manager->add_handler( $file_handler );

        // 4. Initialize the manager. This registers necessary WordPress hooks.
        // This step is crucial for ensuring logs are saved correctly.
        $manager->init();
    }
    
    return $manager;
}

// Hook the initialization into WordPress.
add_action( 'plugins_loaded', 'my_project_logger_manager' );

/**
 * Example of how to use the logger elsewhere in your code.
 */
function some_function_that_does_stuff() {
    // 5. Get a logger instance for a specific "channel".
    $logger = my_project_logger_manager()->get_logger( 'user-actions' );
    
    // 6. Log a message.
    $current_user = wp_get_current_user();
    $logger->info(
        'User with ID {user_id} updated their profile.',
        [
            'user_id'   => $current_user->ID,
            'user_login' => $current_user->user_login,
        ]
    );
}

add_action( 'profile_update', 'some_function_that_does_stuff' );
```

### Explanation

1.  **`new Log_Manager()`**: We create a single instance of the `Log_Manager`, which will be the central hub for our logging system. We use a static variable to ensure it's only created once.
2.  **`new File_Handler(...)`**: We instantiate a handler. A handler is responsible for taking a log record and sending it to a destination (a file, a database, an email, etc.).
3.  **`$manager->add_handler(...)`**: We register our handler with the manager. You can add multiple handlers to send logs to different places simultaneously.
4.  **`$manager->init()`**: This critical step registers the `shutdown` hook with WordPress. The logger buffers records in memory for performance and only writes them to their destination at the very end of the request. `init()` ensures this happens.
5.  **`$manager->get_logger('user-actions')`**: We request a PSR-3 logger instance for a specific **channel**. Channels are identifiers used to categorize logs. You can create loggers for different channels like 'payments', 'api-requests', or 'debug' to keep your logs organized.
6.  **`$logger->info(...)`**: We call a standard PSR-3 logging method. The first argument is the message, and the second is an array of context data. Placeholders in the message (like `{user_id}`) are automatically replaced by values from the context array.

After a user's profile is updated, a new log entry will appear in `/wp-content/uploads/logs/my-project.log`:
```log
[2023-10-27 15:30:00] user-actions.INFO: User with ID 1 updated their profile. {"user_id":1,"user_login":"admin"}
```

Now you have a fully functional logging system! To learn about other destinations and advanced configurations, explore the guides on **[Handlers](02-Handlers.md)** and **[Formatters](03-Formatters.md)**.
