<?php

declare(strict_types=1);

namespace SuccessTree\Http;

/**
 * Error with an HTTP status, rendered as {"ok":false,"error":"…"}.
 */
class ApiException extends \RuntimeException
{
    /** @var int */
    private $status;

    public function __construct(string $message, int $status = 400)
    {
        parent::__construct($message);
        $this->status = $status;
    }

    public function status(): int
    {
        return $this->status;
    }
}
