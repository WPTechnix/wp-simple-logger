<?php

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Tests\Unit\Utils;

use WPTechnix\WP_Simple_Logger\Utils\Color;
use WPTechnix\WP_Simple_Logger\Tests\TestCase;

final class Color_Test extends TestCase
{
    public function test_is_color_dark_returns_true_for_dark_colors(): void
    {
        $this->assertTrue(Color::is_color_dark('#000000'));
        $this->assertTrue(Color::is_color_dark('#DC3545'));
        $this->assertTrue(Color::is_color_dark('#821722'));
        $this->assertTrue(Color::is_color_dark('#FD7E14'));
        $this->assertTrue(Color::is_color_dark('#17A2B8'));
    }

    public function test_is_color_dark_returns_false_for_light_colors(): void
    {
        $this->assertFalse(Color::is_color_dark('#FFFFFF'));
        $this->assertFalse(Color::is_color_dark('#FFC107'));
        $this->assertFalse(Color::is_color_dark('#FFF8E7'));
        $this->assertFalse(Color::is_color_dark('#E0F7FA'));
    }

    public function test_is_color_dark_handles_short_hex(): void
    {
        $this->assertTrue(Color::is_color_dark('#000'));
        $this->assertFalse(Color::is_color_dark('#FFF'));
    }

    public function test_is_color_dark_handles_hashless_values(): void
    {
        $this->assertTrue(Color::is_color_dark('000000'));
        $this->assertFalse(Color::is_color_dark('FFFFFF'));
    }
}
