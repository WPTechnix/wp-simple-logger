<?php

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $this->mockCommonWordPressFunctions();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    private function mockCommonWordPressFunctions(): void
    {
        Functions\when('esc_html')
            ->alias(fn (string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));

        Functions\when('esc_attr')
            ->alias(fn (string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));

        Functions\when('esc_url')
            ->alias(fn (string $url): string => $url);

        Functions\when('wp_json_encode')
            ->alias(fn (mixed $data, int $options = 0, int $depth = 512): string|false => json_encode($data, $options, $depth));

        Functions\when('get_date_from_gmt')
            ->alias(fn (string $date_string, string $format = 'Y-m-d H:i:s'): string => gmdate($format, strtotime($date_string)));

        Functions\when('get_option')
            ->alias(fn (string $option, mixed $default = false): mixed => $default);

        Functions\when('current_time')
            ->alias(fn (string $type, bool $gmt = false): string => gmdate('Y-m-d H:i:s'));

        Functions\when('sanitize_text_field')
            ->alias(fn (string $str): string => trim(stripslashes($str)));

        Functions\when('wp_unslash')
            ->alias(fn (string|array $value): string|array => is_string($value) ? stripslashes($value) : $value);

        Functions\when('wp_parse_args')
            ->alias(function (mixed $args, array $defaults = []): array {
                $parsed = is_array($args) ? $args : [];
                return array_merge($defaults, $parsed);
            });

        Functions\when('is_wp_error')
            ->alias(static fn (mixed $thing): bool => $thing instanceof \WP_Error);
    }
}
