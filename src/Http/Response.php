<?php

declare(strict_types=1);

namespace SuccessTree\Http;

use SuccessTree\Support\Json;

/**
 * HTTP response value object: JSON or static file.
 */
class Response
{
    /** @var int */
    private $status;

    /** @var array<string, string> */
    private $headers;

    /** @var string */
    private $body;

    /** @var array|null decoded payload for JSON responses */
    private $data;

    public function __construct(int $status = 200, array $headers = [], string $body = '', ?array $data = null)
    {
        $this->status = $status;
        $this->headers = $headers;
        $this->body = $body;
        $this->data = $data;
    }

    public static function json(array $data, int $status = 200): self
    {
        return new self($status, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], Json::encodeOutput($data), $data);
    }

    /**
     * Static file with Content-Type, ETag and Cache-Control. Answers 304 when If-None-Match matches.
     */
    public static function file(string $path, string $contentType, ?string $ifNoneMatch = null, int $maxAge = 86400): self
    {
        $content = @file_get_contents($path);
        if ($content === false) {
            return self::json(['ok' => false, 'error' => 'asset not found'], 404);
        }
        $etag = '"' . substr(sha1($content), 0, 20) . '"';
        $headers = [
            'Content-Type' => $contentType,
            'ETag' => $etag,
            'Cache-Control' => 'public, max-age=' . $maxAge,
            'X-Content-Type-Options' => 'nosniff',
        ];
        $mtime = @filemtime($path);
        if ($mtime) {
            $headers['Last-Modified'] = gmdate('D, d M Y H:i:s', $mtime) . ' GMT';
        }
        if ($ifNoneMatch !== null) {
            foreach (explode(',', $ifNoneMatch) as $tag) {
                $tag = trim($tag);
                if ($tag === $etag || $tag === 'W/' . $etag || $tag === '*') {
                    return new self(304, $headers, '');
                }
            }
        }
        $headers['Content-Length'] = (string)strlen($content);
        return new self(200, $headers, $content);
    }

    public function status(): int
    {
        return $this->status;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function header(string $name): ?string
    {
        foreach ($this->headers as $k => $v) {
            if (strcasecmp($k, $name) === 0) {
                return $v;
            }
        }
        return null;
    }

    public function body(): string
    {
        return $this->body;
    }

    /** Decoded payload for JSON responses (null for files). */
    public function data(): ?array
    {
        return $this->data;
    }

    /** Emits status, headers and body. */
    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $k => $v) {
                header($k . ': ' . str_replace(["\r", "\n"], '', $v));
            }
        }
        if ($this->status !== 304) {
            echo $this->body;
        }
    }
}
