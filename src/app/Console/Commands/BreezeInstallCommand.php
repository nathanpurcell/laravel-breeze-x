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
     * The request save path.
     *
     * @var string
     */
    protected $requestPath;

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
        $this->setProperties();
        $this->generateControllers();
        $this->generateRequests();
        $this->generateRoutes();

        // if ($this->guardName === 'web') {
        //     // "Dashboard" Route...
        //     $this->replaceInFile('/home', '/dashboard', resource_path('views/welcome.blade.php'));
        //     $this->replaceInFile('Home', 'Dashboard', resource_path('views/welcome.blade.php'));
        //     $this->replaceInFile('/home', '/dashboard', app_path('Providers/RouteServiceProvider.php'));
        // }

        $this->info('Breeze scaffolding installed successfully.');
        $this->comment('Please execute the "npm install && npm run dev" command to build your assets.');

        return 0;
    }

    private function setProperties()
    {
        $this->guardName = $this->argument('guard');
        $this->brokerName = $this->argument('passwords');

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
        $requests = ['LoginRequest'];

        $this->files->ensureDirectoryExists($this->requestPath);

        foreach ($requests as $request) {
            $stubPath = realpath(__DIR__.'/stubs/App/Http/Requests/Auth/'.$request.'.php');
            $savePath = $this->requestPath.$request.'.php';
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

        // $this->files->copy(__DIR__.'/stubs/routes/web.php', base_path('routes/web.php'));
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
