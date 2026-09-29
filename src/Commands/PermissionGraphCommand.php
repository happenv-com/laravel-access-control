<?php

declare(strict_types=1);

namespace Happenv\LaravelAccessControl\Commands;

use Happenv\LaravelAccessControl\Contracts\AuthControllable;
use Happenv\LaravelAccessControl\Diagram\PermissionDiagram;
use Happenv\LaravelAccessControl\Diagram\PermissionDiagrams;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Draws the permission rules of the whole catalogue, or what one principal may do and why.
 */
final class PermissionGraphCommand extends Command
{
    protected $signature = 'permission:graph
                            {principal? : The key of the principal to draw; leave it out to draw the whole catalogue}
                            {--format=tree : Output format (tree, mermaid, dot, json, or one an application registered)}
                            {--model= : The principal\'s model class; defaults to the user provider model of the guard}
                            {--guard= : The guard whose user provider model to use; defaults to the default guard}
                            {--schema-version=2 : Machine API schema version}';

    protected $description = 'Draw the permission rules of the catalogue, or what a principal may do and why';

    public function handle(PermissionDiagrams $diagrams): int
    {
        if ((string) $this->option('schema-version') !== (string) PermissionDiagram::SCHEMA_VERSION) {
            $this->error(sprintf(
                'Unsupported schema version [%s]. Supported: %d.',
                $this->option('schema-version'),
                PermissionDiagram::SCHEMA_VERSION,
            ));

            return self::FAILURE;
        }

        try {
            $key = $this->argument('principal');

            $diagram = $key === null
                ? $diagrams->catalogue()
                : $diagrams->forPrincipal($this->principal((string) $key));

            // Raw: a label is data, and the console would read `<comment>` in it as a style.
            $this->output->writeln($diagrams->render($diagram, (string) $this->option('format')), OutputInterface::OUTPUT_RAW);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @throws InvalidArgumentException when the principal cannot be found or cannot be drawn
     */
    private function principal(string $key): AuthControllable
    {
        $model = $this->option('model') ?: $this->guardModel();

        if (! is_string($model) || $model === '') {
            throw new InvalidArgumentException('Cannot tell which model the principal is: the guard has no user provider model. Pass one with --model.');
        }

        if (! is_subclass_of($model, Model::class)) {
            throw new InvalidArgumentException(sprintf('[%s] is not an Eloquent model. Pass one with --model.', $model));
        }

        $principal = $model::query()->find($key);

        if ($principal === null) {
            throw new InvalidArgumentException(sprintf('No [%s] with key [%s].', $model, $key));
        }

        if (! $principal instanceof AuthControllable) {
            throw new InvalidArgumentException(sprintf('[%s] does not implement %s.', $model, AuthControllable::class));
        }

        return $principal;
    }

    private function guardModel(): mixed
    {
        $guard = $this->option('guard') ?: config('auth.defaults.guard');
        $provider = config(sprintf('auth.guards.%s.provider', $guard));

        return is_string($provider) ? config(sprintf('auth.providers.%s.model', $provider)) : null;
    }
}
