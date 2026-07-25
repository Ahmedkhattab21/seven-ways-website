<?php

namespace App\Core\Exceptions;

use RuntimeException;

class BusinessRuleException extends RuntimeException
{
    public function __construct(
        string $message,
        private array $errors = [],
        private int $status = 422,
        private array $meta = []
    ) {
        parent::__construct($message);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function meta(): array
    {
        return $this->meta;
    }
}
