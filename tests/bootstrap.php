<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

if (! defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

if (! class_exists('wpdb')) {
    /**
     * Minimal stand-in for the WordPress `$wpdb` class so the database layer can be
     * mocked with Mockery in tests without loading WordPress.
     */
    class wpdb
    {
        public string $prefix = 'wp_';

        public function prepare($query, ...$args)
        {
            return $query;
        }

        public function query($query)
        {
            return 0;
        }

        public function get_results($query, $output = null)
        {
            return [];
        }

        public function get_var($query)
        {
            return null;
        }

        public function get_col($query)
        {
            return [];
        }

        public function esc_like($text)
        {
            return $text;
        }

        public function get_charset_collate()
        {
            return '';
        }
    }
}
