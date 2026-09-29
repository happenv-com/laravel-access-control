<?php

declare(strict_types=1);

use Happenv\LaravelAccessControl\Diagram\GrantHolderName;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\InMemoryRole;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\NamedGrantRole;
use Happenv\LaravelAccessControl\Tests\Fixtures\Models\User;
use Illuminate\Database\Eloquent\Model;

describe('GrantHolderName', function (): void {
    it('uses the name a holder gives itself', function (): void {
        expect(GrantHolderName::of(new NamedGrantRole('Editor')))->toBe('Editor');
    });

    it('uses the name attribute of an Eloquent model', function (): void {
        expect(GrantHolderName::of(new User(['name' => 'Jan'])))->toBe('Jan');
    });

    it('falls back to the class and key of a model without a name', function (): void {
        $model = new class extends Model
        {
            protected $table = 'roles';
        };

        $model->setAttribute('id', 7);

        // An anonymous class is named after what it extends.
        expect(GrantHolderName::of($model))->toBe('Model#7')
            ->and(GrantHolderName::of(new User))->toBe('User');
    });

    it('falls back to the short class name of anything else', function (): void {
        expect(GrantHolderName::of(new InMemoryRole))->toBe('InMemoryRole')
            ->and(GrantHolderName::of(new class {}))->toBe('anonymous');
    });
});
