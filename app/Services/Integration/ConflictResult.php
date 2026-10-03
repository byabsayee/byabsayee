<?php
namespace App\Services\Integration;

/** Sends an inbound event to the conflict queue instead of applying it (nothing is written). */
class ConflictResult extends \RuntimeException
{
    public string $kind; public $local; public $remote;
    public function __construct(string $kind, string $note, $local = null, $remote = null)
    {
        parent::__construct($note);
        $this->kind = $kind; $this->local = $local; $this->remote = $remote;
    }
}
