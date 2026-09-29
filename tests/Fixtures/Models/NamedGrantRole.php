<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Tests\Fixtures\Models;

use Happenv\LaravelAccessControl\Contracts\DescribesGrantHolder;

/**
 * A role that says what it is called.
 */
class NamedGrantRole extends InMemoryGrantRole implements DescribesGrantHolder
{
    /**
     * @param  list<string>  $grants
     */
    public function __construct(
        private readonly string $name,
        array $grants = [],
    ) {
        parent::__construct($grants);
    }

    public function getGrantHolderName(): string
    {
        return $this->name;
    }
}
