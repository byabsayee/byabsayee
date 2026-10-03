<?php
namespace App\Services\Integration;

/** Rejects an inbound event with a stable code. $retry tells the sender the same event may succeed later. */
class Reject extends \RuntimeException
{
    public string $errCode; public bool $retry;
    public function __construct(string $code, string $message, bool $retry = false)
    {
        parent::__construct($message);
        $this->errCode = $code; $this->retry = $retry;
    }
}
