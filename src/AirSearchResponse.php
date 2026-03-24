<?php

namespace Uiskz\Air;

use Psr\Log\LoggerInterface;

class AirSearchResponse
{
    public string $currency;

    /**
     * List of errors encountered during the search process.
     *
     * @var string[]
     */
    public array $errors = [];

    /**
     * List of available options for the search request.
     * @var AirOption[]
     */
    public array $options = [];

    protected LoggerInterface|null $logger;

    public function __construct(LoggerInterface|null $logger = null)
    {
        $this->logger = $logger;
    }

    public function addOption(AirOption $option): void
    {
        $this->options[] = $option;
    }
}