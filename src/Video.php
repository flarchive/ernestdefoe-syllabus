<?php

namespace Ernestdefoe\Syllabus;

/**
 * A lesson's video address, reduced to a provider and an id. Only YouTube and
 * Vimeo are accepted, and the embed address is rebuilt from the id alone, so
 * nothing a member typed ever becomes an iframe's src. YouTube plays from
 * youtube-nocookie.com and Vimeo with do-not-track.
 */
class Video
{
    /** @return array{provider: string, id: string, hash?: string}|null */
    public static function parse(?string $url): ?array
    {
        $url = self::clean($url);
        if ($url === null) {
            return null;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $host = (string) preg_replace('/^(www\.|m\.)/', '', $host);
        $path = (string) ($parts['path'] ?? '');
        parse_str((string) ($parts['query'] ?? ''), $query);

        $id = null;
        if (in_array($host, ['youtube.com', 'youtube-nocookie.com', 'music.youtube.com'], true)) {
            $id = $path === '/watch' ? ($query['v'] ?? null) : (preg_match('~^/(?:embed|shorts|live|v)/([^/?#]+)~', $path, $m) ? $m[1] : null);
        } elseif ($host === 'youtu.be') {
            $id = ltrim($path, '/');
        }

        if ($id !== null) {
            return is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/', $id) ? ['provider' => 'youtube', 'id' => $id] : null;
        }

        if (in_array($host, ['vimeo.com', 'player.vimeo.com'], true)
            && preg_match('~^/(?:video/)?(\d{1,12})(?:/([0-9a-f]{6,20}))?/?$~', $path, $m)) {
            $out = ['provider' => 'vimeo', 'id' => $m[1]];
            $hash = $m[2] ?? (is_string($query['h'] ?? null) ? $query['h'] : null);
            if ($hash && preg_match('/^[0-9a-f]{6,20}$/', $hash)) {
                $out['hash'] = $hash;
            }

            return $out;
        }

        return null;
    }

    /** @param array{provider: string, id: string, hash?: string} $video */
    public static function embedUrl(array $video): string
    {
        if ($video['provider'] === 'youtube') {
            return 'https://www.youtube-nocookie.com/embed/'.$video['id'].'?rel=0';
        }

        return 'https://player.vimeo.com/video/'.$video['id'].'?dnt=1'.(isset($video['hash']) ? '&h='.$video['hash'] : '');
    }

    /** Trimmed, control characters removed, or null when empty. */
    public static function clean(?string $url): ?string
    {
        $url = trim((string) preg_replace('/[\x00-\x20\x7F]+/u', '', (string) $url));

        return $url === '' ? null : mb_substr($url, 0, 500);
    }

    /** An https (or http) address with a host, or null: never javascript:, data: or the like. */
    public static function safeUrl(?string $url): ?string
    {
        $url = self::clean($url);
        if ($url === null) {
            return null;
        }

        $parts = parse_url($url);

        return is_array($parts) && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) && ! empty($parts['host'])
            ? $url
            : null;
    }
}
