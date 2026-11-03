<?php

declare(strict_types=1);

namespace MovieStar\Core;

/**
 * Validação e normalização de links do YouTube.
 *
 * Formatos aceitos:
 *  - https://www.youtube.com/watch?v=ID
 *  - https://youtu.be/ID
 *  - https://www.youtube.com/embed/ID
 */
final class YouTube
{
    private const VIDEO_ID_PATTERN = '/^[A-Za-z0-9_-]{11}$/';
    private const MAX_URL_LENGTH   = 255;
    private const YOUTUBE_HOSTS    = ['youtube.com', 'www.youtube.com', 'm.youtube.com'];
    private const SHORT_HOSTS      = ['youtu.be', 'www.youtu.be'];

    private function __construct()
    {
    }

    /**
     * Extrai o id do vídeo, ou null se a URL não for de um vídeo do YouTube.
     */
    public static function extractVideoId(string $url): ?string
    {
        $url = trim($url);

        if ($url === '' || strlen($url) > self::MAX_URL_LENGTH) {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return null;
        }

        $host = strtolower($parts['host']);
        $path = $parts['path'] ?? '';
        $id   = null;

        if (in_array($host, self::SHORT_HOSTS, true)) {
            $id = substr($path, 1);
        } elseif (in_array($host, self::YOUTUBE_HOSTS, true)) {
            if ($path === '/watch') {
                parse_str($parts['query'] ?? '', $query);
                $id = isset($query['v']) && is_string($query['v']) ? $query['v'] : null;
            } elseif (strncmp($path, '/embed/', 7) === 0) {
                $id = substr($path, 7);
            }
        }

        return is_string($id) && preg_match(self::VIDEO_ID_PATTERN, $id) === 1 ? $id : null;
    }

    public static function embedUrl(string $videoId): string
    {
        return 'https://www.youtube.com/embed/' . $videoId;
    }
}
