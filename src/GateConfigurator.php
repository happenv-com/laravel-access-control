<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl;

use Happenv\LaravelAccessControl\Contracts\AuthControllable;
use Happenv\LaravelAccessControl\Contracts\PermissionDefinition;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate as FacadesGate;

final readonly class GateConfigurator
{
    public function __construct(
        private PermissionRegistry $permissionRegistry,
        private VoterRegistry $voterRegistry,
    ) {}

    public function configure(): void
    {
        foreach ($this->permissionRegistry->permissions as $permission) {
            FacadesGate::define(
                $permission,
                function (
                    ?Authenticatable $user = null,
                    ...$arguments
                ) use (
                    $permission
                ): Response {
                    if (! $user instanceof Authenticatable) {
                        return Response::deny('Unauthenticated.');
                    }

                    if ($user instanceof AuthControllable && ! $user->hasPermissionTo($permission)) {
                        return Response::deny($this->refusal($permission));
                    }

                    return $this->voterRegistry->vote(
                        $permission,
                        $user,
                        ...$arguments
                    );
                }
            );
        }
    }

    /**
     * The words a refusal comes back in.
     *
     * READ AT REFUSAL TIME, never at configure time. `configure()` runs once per worker under a
     * long-running server, so a value captured here would freeze whatever the booting process saw
     * and serve it for the life of the worker.
     *
     * The permission's VALUE, not its label: the value is what is granted, what sits in the
     * column and what a schema names in `ability:`. A label is translated, so it would put the
     * booting worker's locale into a message that may cross an organisation boundary.
     */
    private function refusal(PermissionDefinition $permission): string
    {
        return config('access-control.display_permission_in_exception') === true
            ? 'Unauthorized for ' . (string) $permission->value
            : 'Unauthorized.';
    }
}
