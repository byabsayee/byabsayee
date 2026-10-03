<?php
namespace App\Services\Integration;

/** A failure with a stable machine-readable code; the API layer turns it into {"ok":false,"error":{code,message}}. */
class ApiError extends \RuntimeException
{
    public string $errCode;
    public int $httpStatus;
    public array $extra;

    public function __construct(string $code, string $message, int $http = 400, array $extra = [])
    {
        parent::__construct($message);
        $this->errCode = $code; $this->httpStatus = $http; $this->extra = $extra;
    }
}
