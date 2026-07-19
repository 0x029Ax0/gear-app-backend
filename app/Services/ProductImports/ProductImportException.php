<?php

namespace App\Services\ProductImports;

use RuntimeException;

class ProductImportException extends RuntimeException
{
    public function __construct(public readonly string $failureCode, string $message)
    {
        parent::__construct($message);
    }
}
