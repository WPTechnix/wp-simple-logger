<?php

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Tests\Unit;

use Brain\Monkey\Functions;
use Psr\Log\LogLevel;
use WPTechnix\WP_Simple_Logger\Handlers\Email_Handler;
use WPTechnix\WP_Simple_Logger\Log_Entry;
use WPTechnix\WP_Simple_Logger\Tests\TestCase;

final class Email_Handler_Test extends TestCase
{
    public function test_sends_html_email_on_flush(): void
    {
        $captured = [];
        Functions\expect('wp_mail')
            ->once()
            ->andReturnUsing(function ($to, $subject, $body, $headers) use (&$captured): bool {
                $captured = compact('to', 'subject', 'body', 'headers');
                return true;
            });

        $handler = new Email_Handler('to@example.com', 'Critical Alert', LogLevel::ERROR);
        $handler->handle(new Log_Entry('payments', 500, 'gateway down', '2024-01-15 10:30:00', ['gateway' => 'stripe']));
        $handler->flush();

        $this->assertSame(['to@example.com'], $captured['to']);
        $this->assertSame('Critical Alert', $captured['subject']);
        $this->assertStringContainsString('gateway down', $captured['body']);
        $this->assertStringContainsString('CRITICAL', $captured['body']);
        $this->assertStringContainsString('stripe', $captured['body']);
        $this->assertContains('Content-Type: text/html; charset=UTF-8', $captured['headers']);
    }

    public function test_accepts_array_of_recipients(): void
    {
        $recipients = [];
        Functions\expect('wp_mail')
            ->once()
            ->andReturnUsing(function ($to) use (&$recipients): bool {
                $recipients = $to;
                return true;
            });

        $handler = new Email_Handler(['a@example.com', 'b@example.com'], 'Subject');
        $handler->handle(new Log_Entry('ch', 400, 'msg', '2024-01-15 10:30:00'));
        $handler->flush();

        $this->assertSame(['a@example.com', 'b@example.com'], $recipients);
    }

    public function test_custom_title_intro_and_footer_appear_in_body(): void
    {
        $body = '';
        Functions\expect('wp_mail')
            ->once()
            ->andReturnUsing(function ($to, $subject, $emailBody) use (&$body): bool {
                $body = $emailBody;
                return true;
            });

        $handler = new Email_Handler('a@example.com', 'Subject');
        $handler->set_email_title('My Title')
            ->set_email_intro('Intro paragraph')
            ->set_email_footer('Footer note');
        $handler->handle(new Log_Entry('ch', 500, 'msg', '2024-01-15 10:30:00'));
        $handler->flush();

        $this->assertStringContainsString('My Title', $body);
        $this->assertStringContainsString('Intro paragraph', $body);
        $this->assertStringContainsString('Footer note', $body);
    }

    public function test_no_email_sent_when_buffer_is_empty(): void
    {
        Functions\expect('wp_mail')->never();

        $handler = new Email_Handler('a@example.com', 'Subject');
        $handler->flush();

        $this->expectNotToPerformAssertions();
    }
}
