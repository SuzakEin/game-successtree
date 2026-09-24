<?php

declare(strict_types=1);

namespace SuccessTree\Http;

/**
 * Normalised request: method, query params, JSON body, a few headers.
 */
class Request
{
    const MAX_BODY = 4194304; // 4 MB

    /** @var string */
    private $method;

    /** @var array */
    private $params;

    /** @var mixed */
    private $body;

    /** @var array<string, string> lower-cased header names */
    private $headers;

    /** @var bool */
    private $invalidBody;

    /**
     * @param mixed $body decoded JSON body (array) or null
     */
    public function __construct(string $method = 'GET', array $params = [], $body = null, array $headers = [], bool $invalidBody = false)
    {
        $this->method = strtoupper($method);
        $this->params = $params;
        $this->body = $body;
        $this->headers = [];
        foreach ($headers as $k => $v) {
            $this->headers[strtolower((string)$k)] = (string)$v;
        }
        $this->invalidBody = $invalidBody;
    }

    public static function fromGlobals(int $maxBody = self::MAX_BODY): self
    {
        $method = isset($_SERVER['REQUEST_METHOD']) ? (string)$_SERVER['REQUEST_METHOD'] : 'GET';
        $params = is_array($_GET) ? $_GET : [];
        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (is_string($k) && strncmp($k, 'HTTP_', 5) === 0 && is_scalar($v)) {
                $headers[str_replace('_', '-', strtolower(substr($k, 5)))] = (string)$v;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string)$_SERVER['CONTENT_TYPE'];
        }
        $body = null;
        $invalid = false;
        if (strtoupper($method) !== 'GET' && strtoupper($method) !== 'HEAD') {
            $raw = @file_get_contents('php://input', false, null, 0, $maxBody + 1);
            if (is_string($raw) && strlen($raw) > $maxBody) {
                $invalid = true;
            } elseif (is_string($raw) && trim($raw) !== '') {
                $decoded = json_decode($raw, true, 128);
                if (is_array($decoded)) {
                    $body = $decoded;
                } else {
                    $invalid = true;
                }
            } elseif (!empty($_POST) && is_array($_POST)) {
                $body = $_POST;
            }
            if (!isset($params['action']) && isset($_POST['action']) && is_string($_POST['action'])) {
                $params['action'] = $_POST['action'];
            }
        }
        return new self($method, $params, $body, $headers, $invalid);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function action(): string
    {
        $a = $this->param('action', '');
        return is_string($a) ? $a : '';
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    public function param(string $name, $default = null)
    {
        return array_key_exists($name, $this->params) ? $this->params[$name] : $default;
    }

    public function params(): array
    {
        return $this->params;
    }

    /** @return mixed */
    public function body()
    {
        return $this->body;
    }

    public function hasInvalidBody(): bool
    {
        return $this->invalidBody;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $k = strtolower($name);
        return isset($this->headers[$k]) ? $this->headers[$k] : $default;
    }
}
