<?php

namespace App\Domains\RAG\Attributes;

/**
 * Base for attributes that register a class as a named driver.
 *
 * Read by DriverDiscovery so managers and factories need no hardcoded driver map.
 */
abstract class DriverAttribute
{
    public function __construct(
        public readonly string $name
    ) {}
}
