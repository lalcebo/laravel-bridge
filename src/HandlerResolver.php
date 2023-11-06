<?php

namespace Bref\LaravelBridge;

use RuntimeException;

use Bref\Runtime\FileHandlerLocator;
use Psr\Container\ContainerInterface;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Console\Kernel;

use Bref\LaravelBridge\Http\OctaneHandler;

/**
 * This class resolves Lambda handlers.
 *
 * It extends the default Bref behavior (that resolves handlers from files)
 * to also resolve class handlers from the Laravel container.
 */
class HandlerResolver implements ContainerInterface
{
    private ?Application $laravel;
    private FileHandlerLocator $fileLocator;

    public function __construct()
    {
        // Bref's default handler resolver
        $this->fileLocator = new FileHandlerLocator;
        $this->laravel = null;
    }

    public function get(string $id)
    {
        // By default, we check if the handler is a file name (classic Bref behavior)
        if ($this->fileLocator->has($id)) {
            return $this->fileLocator->get($id);
        }

        // The Octane handler is special: it is not created by Laravel's container
        if ($id === OctaneHandler::class) {
            return new OctaneHandler;
        }

        // If not, we try to get the handler from the Laravel container
        return $this->laravel()->get($id);
    }

    public function has(string $id): bool
    {
        // By default, we check if the handler is a file name (classic Bref behavior)
        if ($this->fileLocator->has($id)) {
            return true;
        }

        // The Octane handler is special: it is not created by Laravel's container
        if ($id === OctaneHandler::class) {
            return true;
        }

        // If not, we try to get the handler from the Laravel container
        return $this->laravel()->has($id);
    }

    /**
     * Create and return the Laravel application.
     */
    private function laravel(): Application
    {
        // Only create it once
        if ($this->laravel) {
            return $this->laravel;
        }

        $bootstrapFile = $this->resolveBootstrapLocation();

        $this->laravel = require $bootstrapFile;

        if (! $this->laravel instanceof Application) {
            throw new RuntimeException(sprintf(
                "Expected the `%s` file to return a %s object, instead it returned `%s`",
                $bootstrapFile,
                Application::class,
                is_object($this->laravel) ? get_class($this->laravel) : gettype($this->laravel),
            ));
        }

        $kernel = $this->laravel->make(Kernel::class);
        $kernel->bootstrap();

        return $this->laravel;
    }

    private function resolveBootstrapLocation(): string
    {
        $bootstrapFile = getcwd() . '/bootstrap/app.php';

        if (file_exists($bootstrapFile)) {
            return $bootstrapFile;
        }

        // Going up 4 directories will get us from `vendor/brefphp/laravel-bridge/src` 
        // to the Laravel root folder so we can navigate to `bootstrap/app.php` 
        if (file_exists(__DIR__ . '/../../../../bootstrap/app.php')) {
            return realpath(__DIR__ . '/../../../../bootstrap/app.php');
        }

        throw new RuntimeException(
            "Unable to locate `{$bootstrapFile}`: Bref tried to load that file to retrieve the Laravel app"
        );
    }
}
