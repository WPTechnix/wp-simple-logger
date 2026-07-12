<?php

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Tests\Unit;

use WPTechnix\WP_Simple_Logger\Log_Level;
use WPTechnix\WP_Simple_Logger\Tests\TestCase;

final class Log_Level_Test extends TestCase
{
    public function test_get_level_priority_returns_correct_values(): void
    {
        $this->assertSame(100, Log_Level::get_level_priority('debug'));
        $this->assertSame(200, Log_Level::get_level_priority('info'));
        $this->assertSame(250, Log_Level::get_level_priority('notice'));
        $this->assertSame(300, Log_Level::get_level_priority('warning'));
        $this->assertSame(400, Log_Level::get_level_priority('error'));
        $this->assertSame(500, Log_Level::get_level_priority('critical'));
        $this->assertSame(550, Log_Level::get_level_priority('alert'));
        $this->assertSame(600, Log_Level::get_level_priority('emergency'));
    }

    public function test_get_level_priority_returns_zero_for_unknown_level(): void
    {
        $this->assertSame(0, Log_Level::get_level_priority('unknown'));
    }

    public function test_get_level_priority_is_case_insensitive(): void
    {
        $this->assertSame(200, Log_Level::get_level_priority('INFO'));
        $this->assertSame(200, Log_Level::get_level_priority('Info'));
    }

    public function test_get_level_from_priority_returns_correct_level(): void
    {
        $this->assertSame('debug', Log_Level::get_level_from_priority(100));
        $this->assertSame('info', Log_Level::get_level_from_priority(200));
        $this->assertSame('notice', Log_Level::get_level_from_priority(250));
        $this->assertSame('warning', Log_Level::get_level_from_priority(300));
        $this->assertSame('error', Log_Level::get_level_from_priority(400));
        $this->assertSame('critical', Log_Level::get_level_from_priority(500));
        $this->assertSame('alert', Log_Level::get_level_from_priority(550));
        $this->assertSame('emergency', Log_Level::get_level_from_priority(600));
    }

    public function test_get_level_from_priority_returns_lowest_for_below_minimum(): void
    {
        $this->assertSame('debug', Log_Level::get_level_from_priority(0));
        $this->assertSame('debug', Log_Level::get_level_from_priority(50));
    }

    public function test_get_level_from_priority_returns_highest_for_above_maximum(): void
    {
        $this->assertSame('emergency', Log_Level::get_level_from_priority(999));
    }

    public function test_get_level_from_priority_matches_threshold(): void
    {
        $this->assertSame('info', Log_Level::get_level_from_priority(220));
        $this->assertSame('warning', Log_Level::get_level_from_priority(350));
    }

    public function test_get_all_levels_returns_all_levels(): void
    {
        $levels = Log_Level::get_all_levels();

        $expected = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];
        $this->assertSame($expected, $levels);
    }

    public function test_get_level_color_returns_correct_color(): void
    {
        $this->assertSame('#000000', Log_Level::get_level_color(600));
        $this->assertSame('#821722', Log_Level::get_level_color(550));
        $this->assertSame('#DC3545', Log_Level::get_level_color(500));
        $this->assertSame('#FD7E14', Log_Level::get_level_color(400));
        $this->assertSame('#FFC107', Log_Level::get_level_color(300));
        $this->assertSame('#17A2B8', Log_Level::get_level_color(250));
        $this->assertSame('#28A745', Log_Level::get_level_color(200));
        $this->assertSame('#6c757d', Log_Level::get_level_color(100));
    }
}
