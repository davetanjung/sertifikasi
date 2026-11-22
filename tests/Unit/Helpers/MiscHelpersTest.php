<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use Exception;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class MiscHelpersTest extends TestCase
{
    public function test_check_permissions_is_off_by_default_and_follows_the_config(): void
    {
        $this->assertFalse(_check_permissions());

        config(['app.check_permissions' => true]);

        $this->assertTrue(_check_permissions());
    }

    public function test_exception_message_hides_duplicate_key_errors_when_debug_is_off(): void
    {
        config(['app.debug' => false]);

        $this->assertSame('Code has been used / duplicate.', _get_exception_message(new Exception('raw sql', 23000)));
        $this->assertSame('Code have duplicate.', _get_exception_message(new Exception('raw sql', 1062)));
    }

    public function test_exception_message_shows_the_real_message_when_debug_is_on(): void
    {
        config(['app.debug' => true]);
        Log::shouldReceive('debug')->once();

        $this->assertSame('raw sql', _get_exception_message(new Exception('raw sql', 23000), false));
    }

    public function test_exception_message_appends_a_six_character_reference_code(): void
    {
        Log::shouldReceive('debug')->once();

        $this->assertMatchesRegularExpression('/^Something broke \[[A-Za-z0-9]{6}\]$/', _get_exception_message(new Exception('Something broke')));
    }

    public function test_exception_message_can_leave_out_the_reference_code_and_logs_the_error(): void
    {
        Log::shouldReceive('debug')->once()->withArgs(
            fn (string $message): bool => str_contains($message, 'NO_USER') && str_contains($message, 'Something broke'),
        );

        $this->assertSame('Something broke', _get_exception_message(new Exception('Something broke'), false));
    }

    public function test_markdown_returns_an_empty_string_for_blank_input(): void
    {
        $this->assertSame('', _markdown(null));
        $this->assertSame('', _markdown(''));
        $this->assertSame('', _markdown('   '));
    }

    public function test_markdown_renders_basic_formatting(): void
    {
        $this->assertStringContainsString('<strong>bold</strong>', _markdown('**bold**'));
    }

    public function test_markdown_strips_embedded_html_and_unsafe_links(): void
    {
        $html = _markdown("<script>alert(1)</script>\n\n[click](javascript:alert(1))");

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_number_to_words_spells_a_number_out(): void
    {
        if (! class_exists(\NumberFormatter::class)) {
            $this->markTestSkipped('The intl extension is not installed.');
        }

        $this->assertSame('twenty-one', _number_to_words(21));
        $this->assertSame('one', _number_to_words('1'));
    }
}
