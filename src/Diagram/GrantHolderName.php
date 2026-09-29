<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Diagram;

use Happenv\LaravelAccessControl\Contracts\DescribesGrantHolder;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;

/**
 * How a principal or role is labelled in a diagram: what it says of itself
 * ({@see DescribesGrantHolder}), else an Eloquent `name`, else its class — with its key for a model.
 */
final class GrantHolderName
{
    public static function of(object $holder): string
    {
        if ($holder instanceof DescribesGrantHolder && $holder->getGrantHolderName() !== '') {
            return $holder->getGrantHolderName();
        }

        $class = self::shortClass($holder);

        if (! $holder instanceof Model) {
            return $class;
        }

        // Asked only when the model has one — a column, a cast or an accessor: under
        // Model::preventAccessingMissingAttributes() asking for one it lacks throws.
        if ($holder->hasAttribute('name')) {
            $name = $holder->getAttribute('name');

            if (is_string($name) && $name !== '') {
                return $name;
            }
        }

        $key = $holder->getKey();

        return is_int($key) || (is_string($key) && $key !== '') ? $class . '#' . $key : $class;
    }

    /**
     * An anonymous class has no name of its own; it is named after what it extends.
     */
    private static function shortClass(object $holder): string
    {
        $reflection = new ReflectionClass($holder);

        if (! $reflection->isAnonymous()) {
            return $reflection->getShortName();
        }

        $parent = $reflection->getParentClass();

        return $parent === false ? 'anonymous' : $parent->getShortName();
    }
}
