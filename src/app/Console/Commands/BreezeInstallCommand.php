<?php

namespace Purcell\BreezeX\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class BreezeInstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'breeze-x:install {guard=web} {passwords=users}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install the controllers and routes for a named guard/password broker. ';

    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * The name of the guard.
     *
     * @var string
     */
    protected $guardName;

    /**
     * The name of the password broker.
     *
     * @var string
     */
    protected $brokerName;

    /**
     * The controller save path.
     *
     * @var string
     */
    protected $controllerPath;

    /**
     * The controller namespace.
     *
     * @var string
     */
    protected $controllerNamespace;

    /**
     * The fully-qualified namespace of the model for registration.
     *
     * @var string
     */
    protected $modelNamespace;

    /**
     * The short name of the model for registration.
     *
     * @var string
     */
    protected $modelName;

    /**
     * The request save path.
     *
     * @var string
     */
    protected $requestPath;

    /**
     * The request namespace.
     *
     * @var string
     */
    protected $requestNamespace;

    /**
     * The views save path.
     *
     * @var string
     */
    protected $viewsPath;

    /**
     * The views blade prefix.
     *
     * @var string
     */
    protected $viewsPrefix;

    /**
     * The routes file name.
     *
     * @var string
     */
    protected $routesFilename;

    /**
     * The routes prefix.
     *
     * @var string
     */
    protected $routePrefix;

    /**
     * The routes name prefix.
     *
     * @var string
     */
    protected $routeNamePrefix;

    /**
     * The guard name to pass to the "guest" middleware.
     *
     * @var string
     */
    protected $routeGuestGuardName;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if (!$this->setProperties()){
            return 1;
        }

        $this->generateControllers();
        $this->generateRequests();
        $this->generateMiddlewares();
        $this->generateRoutes();

        // if ($this->guardName === 'web') {
        //     // "Dashboard" Route...
        //     $this->replaceInFile('/home', '/dashboard', resource_path('views/welcome.blade.php'));
        //     $this->replaceInFile('Home', 'Dashboard', resource_path('views/welcome.blade.php'));
        //     $this->replaceInFile('/home', '/dashboard', app_path('Providers/RouteServiceProvider.php'));
        // }

        $this->info('Breeze scaffolding installed successfully.');

        return 0;
    }

    private function setProperties()
    {
        $this->guardName = $this->argument('guard');
        $this->brokerName = $this->argument('passwords');

        // Do not proceed if the guard/password broker is not defined.
        if (
            is_null(config('auth.guards.'.$this->guardName)) ||
            is_null(config('auth.passwords.'.$this->brokerName))
        ){
            $this->warn(sprintf(
                'We could not find the config for guard "%s" or the password broker "%s"',
                $this->guardName,
                $this->brokerName
            ));

            $this->line('Please update your "auth.php" file before continuing. ');

            return false;
        }

        $guardProvider = config(sprintf('auth.guards.%s.provider', $this->guardName));
        $model = config(sprintf('auth.providers.%s.model', $guardProvider));

        if (is_null($model)){
            $this->warn('Guard config does not have a valud model. ');
            $this->line('Breeze-X currently only supports Eloquent');
            return false;
        }

        $this->modelNamespace = $model;
        $parts = explode('\\', $model);
        $this->modelName = array_pop($parts);

        $this->controllerNamespace = ($this->guardName === 'web')
            ? 'App\Http\Controllers\Auth'
            : sprintf('App\Http\Controllers\Auth\%s', Str::title($this->guardName));

        $this->controllerPath = ($this->guardName === 'web')
            ? app_path('Http/Controllers/Auth/')
            : sprintf(app_path('Http/Controllers/Auth/%s/'), Str::title($this->guardName));

        $this->requestPath = ($this->guardName === 'web')
            ? app_path('Http/Requests/Auth/')
            : sprintf(app_path('Http/Requests/Auth/%s/'), Str::title($this->guardName));

        $this->viewsPath = ($this->guardName === 'web')
            ? resource_path('views')
            : sprintf(resource_path('views/%s'), Str::lower($this->guardName));

        $this->viewsPrefix = ($this->guardName === 'web')
            ? 'auth.'
            : sprintf('auth.%s.', Str::lower($this->guardName));

        $this->routesFilename = ($this->guardName === 'web')
            ? 'auth.php'
            : sprintf('%s-auth.php', Str::lower($this->guardName));

        $this->routePrefix = ($this->guardName === 'web')
            ? '/'
            : sprintf('/%s', Str::plural(Str::lower($this->guardName)));

        $this->routeNamePrefix = ($this->guardName === 'web')
            ? ''
            : sprintf('%s.', Str::plural(Str::lower($this->guardName)));

        $this->routeGuestGuardName = ($this->guardName === 'web')
            ? ''
            : sprintf(':%s', $this->guardName);

        $this->requestNamespace = ($this->guardName === 'web')
            ? 'App\Http\Requests\Auth'
            : sprintf('App\Http\Requests\Auth\%s', Str::title($this->guardName));

        return true;
    }

    /**
     * Generate the authentication library controllers for the given config.
     *
     * @return void
     */
    protected function generateControllers()
    {
        $controllers = [
            'AuthenticatedSessionController',
            'ConfirmablePasswordController',
            'EmailVerificationNotificationController',
            'EmailVerificationPromptController',
            'NewPasswordController',
            'PasswordResetLinkController',
            'RegisteredUserController',
            'VerifyEmailController',
        ];

        $this->files->ensureDirectoryExists($this->controllerPath);

        foreach ($controllers as $controller) {
            $stubPath = realpath(__DIR__.'/stubs/App/Http/Controllers/Auth/'.$controller.'.php');
            $savePath = $this->controllerPath.$controller.'.php';
            $this->files->put($savePath, $this->buildClass($stubPath));
        }
    }

    /**
     * Generate the request.
     *
     * @return void
     */
    protected function generateRequests()
    {
        $requests = [
            'LoginRequest',
            'EmailVerificationRequest',
        ];

        $this->files->ensureDirectoryExists($this->requestPath);

        foreach ($requests as $request) {
            $stubPath = realpath(__DIR__.'/stubs/App/Http/Requests/Auth/'.$request.'.php');
            $savePath = $this->requestPath.$request.'.php';
            $this->files->put($savePath, $this->buildClass($stubPath));
        }
    }
    /**
     * Generate middlewares.
     *
     * @return void
     */
    protected function generateMiddlewares()
    {
        $middlewares = [
            'EnsureEmailIsVerified',
        ];

        $middlewarePath = app_path('Http/Middleware/');

        $this->files->ensureDirectoryExists($middlewarePath);

        foreach ($middlewares as $middleware) {
            $stubPath = realpath(__DIR__.'/stubs/App/Http/Middleware/'.$middleware.'.php');
            $savePath = $middlewarePath.$middleware.'.php';
            $this->files->put($savePath, $this->buildClass($stubPath));
        }
    }

    /**
     * Generate the routes, prefixing and grouping as required.
     *
     * @return void
     */
    protected function generateRoutes()
    {
        $stubPath = realpath(__DIR__.'/stubs/routes/auth.php');
        $savePath = base_path('routes/'.$this->routesFilename);
        $this->files->put($savePath, $this->buildClass($stubPath));

        $this->files->put(
            base_path('routes/web.php'),
            $this->buildClass(realpath(__DIR__.'/stubs/routes/web.php'))
        );
    }

    /**
     * Build the class files by substituting placeholders.
     *
     * @param  string  $filePath
     * @return string
     */
    protected function buildClass(string $filePath)
    {
        return $this->substitute($this->files->get($filePath));
    }

    /**
     * Read the $contents and replace placeholders with real values.
     *
     * @param  string  $contents
     * @return string
     */
    protected function substitute(string $contents)
    {
        return str_replace([
            'DummyGuardName',
            'DummyBrokerName',
            'DummyControllerNamespace',
            'DummyRoutesFilename',
            'DummyRoutePrefix',
            'DummyRouteNamePrefix',
            'DummyRouteGuestMiddlewareGuard',
            'DummyViewPathPrefix',
            'DummyViewPrefix',
            'DummyModelNamespace',
            'DummyModelTable',
            'DummyModel',
            'DummyRequestNamespace',
        ], [
            $this->guardName,
            $this->brokerName,
            $this->controllerNamespace,
            $this->routesFilename,
            $this->routePrefix,
            $this->routeNamePrefix,
            $this->routeGuestGuardName,
            $this->viewsPath,
            $this->viewsPrefix,
            $this->modelNamespace,
            Str::snake(Str::pluralStudly($this->modelName)),
            $this->modelName,
            $this->requestNamespace,
        ], $contents);
    }

    /**
     * Replace a given string within a given file.
     *
     * @param  string  $search
     * @param  string  $replace
     * @param  string  $path
     * @return void
     */
    protected function replaceInFile($search, $replace, $path)
    {
        file_put_contents($path, str_replace($search, $replace, file_get_contents($path)));
    }
}
