<?php

declare(strict_types=1);

use Happenv\LaravelAccessControl\PermissionRegistry;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\User;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\ProductPermission;
use Happenv\LaravelAccessControl\Tests\Fixtures\Permissions\Rules\GalleryPermission;
use Illuminate\Foundation\Auth\User as PlainUser;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    resolve(PermissionRegistry::class)->register([ProductPermission::class, GalleryPermission::class]);

    config(['auth.providers.users.model' => User::class]);

    $this->user = User::create([
        'name' => 'Jan',
        'email' => 'jan@example.com',
        'password' => 'password',
        'permissions' => ['product.update'],
    ]);
});

/**
 * Run the command and read its whole output back. The diagram is written in one piece, so
 * expectsOutputToContain(), which consumes the write it matches, could check only one fragment.
 *
 * @param  array<string, mixed>  $arguments
 * @return array{int, string}
 */
function permissionGraph(array $arguments = []): array
{
    $exitCode = Artisan::call('permission:graph', $arguments);

    return [$exitCode, Artisan::output()];
}

describe('permission:graph', function (): void {
    it('draws the catalogue as a tree by default', function (): void {
        [$exitCode, $output] = permissionGraph();

        expect($exitCode)->toBe(0)
            ->and($output)->toContain('Products', 'View Gallery (gallery.view)', 'implied by Update Products');
    });

    it('draws a principal found by its key, in the format asked for', function (): void {
        [$exitCode, $output] = permissionGraph(['principal' => $this->user->id, '--format' => 'mermaid']);

        expect($exitCode)->toBe(0)
            ->and($output)->toStartWith('flowchart LR')
            ->toContain('n0(["Jan"])', 'n1["direct grants"]');
    });

    it('hands a principal over as json', function (): void {
        [$exitCode, $output] = permissionGraph(['principal' => $this->user->id, '--format' => 'json']);

        $diagram = json_decode($output, true);

        expect($exitCode)->toBe(0)
            ->and($diagram['kind'])->toBe('principal')
            ->and(array_column($diagram['nodes'], 'state', 'id'))->toMatchArray([
                'permission:product.update' => 'allowed',
                'permission:gallery.manage' => 'implied',
            ]);
    });

    it('loads the principal from the model it is given', function (): void {
        config(['auth.providers.users.model' => null]);

        [$exitCode, $output] = permissionGraph(['principal' => $this->user->id, '--model' => User::class]);

        expect($exitCode)->toBe(0)
            ->and($output)->toStartWith('Jan');
    });

    it('refuses what it cannot draw', function (array $arguments, string $message): void {
        [$exitCode, $output] = permissionGraph($arguments);

        expect($exitCode)->toBe(1)
            ->and($output)->toContain($message);
    })->with([
        'an unknown format' => [['--format' => 'svg'], 'No diagram renderer for format [svg]. Available: tree, mermaid, dot, json.'],
        'an unsupported schema version' => [['--schema-version' => '2'], 'Unsupported schema version [2]. Supported: 1.'],
        'a key nobody has' => [['principal' => '999'], 'No [' . User::class . '] with key [999].'],
        'a class that is not a model' => [['principal' => '1', '--model' => stdClass::class], '[stdClass] is not an Eloquent model.'],
        'a model that is not a principal' => [['principal' => '1', '--model' => PlainUser::class], 'does not implement'],
        'a guard without a provider' => [['principal' => '1', '--guard' => 'nope'], 'Cannot tell which model the principal is'],
    ]);
});
