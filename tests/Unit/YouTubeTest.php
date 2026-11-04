<?php

declare(strict_types=1);

namespace MovieStar\Tests\Unit;

use MovieStar\Core\YouTube;
use MovieStar\Tests\Support\TestCase;

class YouTubeTest extends TestCase
{
    private const ID = 'dQw4w9WgXcQ';

    public function testAcceptsSupportedFormats(): void
    {
        $urls = [
            'https://www.youtube.com/watch?v=' . self::ID,
            'http://youtube.com/watch?v=' . self::ID,
            'https://m.youtube.com/watch?v=' . self::ID,
            'https://www.youtube.com/watch?feature=share&v=' . self::ID . '&t=42',
            'https://youtu.be/' . self::ID,
            'https://www.youtu.be/' . self::ID,
            'https://youtu.be/' . self::ID . '?t=10',
            'https://www.youtube.com/embed/' . self::ID,
            'HTTPS://WWW.YOUTUBE.COM/watch?v=' . self::ID,
            '  https://youtu.be/' . self::ID . "  \n",
        ];

        foreach ($urls as $url) {
            $this->assertSame(self::ID, YouTube::extractVideoId($url), $url);
        }
    }

    public function testRejectsLineBreakAfterId(): void
    {
        // O "$" de uma regex aceitaria o "\n" final; o padrão usa \z.
        $this->assertNull(YouTube::extractVideoId('https://www.youtube.com/watch?v=' . self::ID . '%0A'));
        $this->assertNull(YouTube::extractVideoId('https://www.youtube.com/watch?v=' . self::ID . '%0D%0A'));
        $this->assertNull(YouTube::extractVideoId('https://www.youtube.com/embed/' . self::ID . "\n" . 'x'));
        $this->assertNull(YouTube::extractVideoId('https://youtu.be/' . self::ID . '%0A'));
    }

    public function testRejectsOtherHostsAndSchemes(): void
    {
        $urls = [
            'https://evil.com/watch?v=' . self::ID,
            'https://youtube.com.evil.com/watch?v=' . self::ID,
            'https://evilyoutube.com/watch?v=' . self::ID,
            'javascript://www.youtube.com/watch?v=' . self::ID,
            'ftp://www.youtube.com/watch?v=' . self::ID,
            '//www.youtube.com/watch?v=' . self::ID,
            'www.youtube.com/watch?v=' . self::ID,
            'https://user:pass@www.youtube.com/watch?v=' . self::ID,
            'https://www.youtube.com:8080/watch?v=' . self::ID,
        ];

        foreach ($urls as $url) {
            $this->assertNull(YouTube::extractVideoId($url), $url);
        }
    }

    public function testRejectsInvalidIdsAndPaths(): void
    {
        $urls = [
            '',
            '   ',
            'https://www.youtube.com/watch?v=curto',
            'https://www.youtube.com/watch?v=' . self::ID . 'X',
            'https://www.youtube.com/watch?v=dQw4w9WgXc!',
            'https://www.youtube.com/watch?v[]=' . self::ID,
            'https://www.youtube.com/watch',
            'https://www.youtube.com/watchx?v=' . self::ID,
            'https://www.youtube.com/v/' . self::ID,
            'https://www.youtube.com/embed/' . self::ID . '/extra',
            'https://youtu.be/',
            'https://youtu.be/' . self::ID . '/',
            'https://www.youtube.com/watch?v=' . self::ID . '"><script>',
            'https://www.youtube.com/watch?v=' . self::ID . '&' . str_repeat('a', 300),
        ];

        foreach ($urls as $url) {
            $this->assertNull(YouTube::extractVideoId($url), $url);
        }
    }

    public function testEmbedUrl(): void
    {
        $this->assertSame('https://www.youtube.com/embed/' . self::ID, YouTube::embedUrl(self::ID));
    }
}
