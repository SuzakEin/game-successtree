<?php

declare(strict_types=1);

namespace SuccessTree\Service;

/**
 * Raised when an editor payload (or a request body) is invalid. Carries every error found.
 */
class ValidationException extends \InvalidArgumentException
{
    /** @var string[] */
    private $errors;

    /** @param string[] $errors */
    public function __construct(array $errors)
    {
        $this->errors = array_values($errors);
        $shown = array_slice($this->errors, 0, 5);
        $msg = 'Invalid payload: ' . implode('; ', $shown);
        if (count($this->errors) > 5) {
            $msg .= ' (+' . (count($this->errors) - 5) . ' more)';
        }
        parent::__construct($msg);
    }

    /** @return string[] */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
