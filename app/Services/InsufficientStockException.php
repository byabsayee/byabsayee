<?php
namespace App\Services;

class InsufficientStockException extends \RuntimeException
{
    public int $productId; public float $have; public float $need;
    public function __construct(int $productId, float $have, float $need)
    {
        parent::__construct("Only {$have} in stock (needed {$need}).");
        $this->productId = $productId; $this->have = $have; $this->need = $need;
    }
}
