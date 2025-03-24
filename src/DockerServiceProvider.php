<?php

namespace ParsaMirzaie\DockerStarterKitLaravel;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use ParsaMirzaie\DockerStarterKitLaravel\Commands\RegenerateEnv;
use Symfony\Component\Yaml\Yaml;

class DockerServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Define source and destination paths
        $sourceDockerPath = __DIR__ . '/../docker'; // One level up from src/
        $destinationDockerPath = base_path('docker');

        // Copy the entire docker/ directory on package install
        if (file_exists($sourceDockerPath) && !file_exists($destinationDockerPath)) {
            File::copyDirectory($sourceDockerPath, $destinationDockerPath);
        }

        // Publish the config file
        $this->publishes([
            __DIR__ . '/../config/docker-config.php' => config_path('docker-config.php'),
        ], 'docker-config');

        // Publish the docker/ directory
        $this->publishes([
            __DIR__ . '/../docker' => base_path('docker'),
        ], 'docker-files');

        if ($this->app->runningInConsole()) {
            $this->commands([
                RegenerateEnv::class,
            ]);
        }

        // Generate .env and docker-compose.yml files immediately after install
        $this->generateDockerComposeFiles();
        $this->generateEnvFiles();
    }

    public function register()
    {
        $configPath = __DIR__ . '/../config/docker-config.php'; // One level up from src/
        if (file_exists($configPath)) {
            $this->mergeConfigFrom($configPath, 'docker-config');
        } else {
            $this->app['config']->set('docker-config', $this->getDefaultConfig());
        }
    }

    public function generateDockerComposeFiles()
    {
        $config = config('docker-config.environments');

        foreach ($config as $env => $settings) {
            $dockerComposePath = $settings['docker_compose'];
            $allServices = $settings['services'] ?? [];

            $activeServices = $this->filterActiveServices($allServices);

            $dockerCompose = [
                'version' => '3',
                'services' => $activeServices,
            ];

            $directory = dirname($dockerComposePath);
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            file_put_contents($dockerComposePath, Yaml::dump($dockerCompose, 4, 2));
        }
    }

    public function generateEnvFiles()
    {
        $config = config('docker-config.environments');

        foreach ($config as $env => $settings) {
            $envFilePath = $settings['env_file'];
            $services = $settings['services'] ?? [];

            $envContent = $this->buildEnvContent($env, $services);

            $directory = dirname($envFilePath);
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            file_put_contents($envFilePath, $envContent);
        }
    }

    protected function filterActiveServices(array $services): array
    {
        $activeServices = [];
        $databaseServices = ['mysql', 'postgresql'];
        $activeDatabase = null;

        foreach ($databaseServices as $dbService) {
            if (isset($services[$dbService]) && ($services[$dbService]['active'] ?? false)) {
                if ($activeDatabase) {
                    continue;
                }
                $activeDatabase = $dbService;
                $serviceConfig = $services[$dbService];
                unset($serviceConfig['active']);
                $activeServices[$dbService] = $serviceConfig;
            }
        }

        foreach ($services as $name => $config) {
            if (in_array($name, $databaseServices)) {
                continue;
            }
            if ($config['active'] ?? false) {
                $serviceConfig = $config;
                unset($serviceConfig['active']);
                $activeServices[$name] = $serviceConfig;

                if (isset($activeServices[$name]['depends_on'])) {
                    $activeServices[$name]['depends_on'] = array_filter(
                        $activeServices[$name]['depends_on'],
                        fn($dep) => isset($activeServices[$dep])
                    );
                }
            }
        }

        return $activeServices;
    }

    protected function buildEnvContent(string $env, array $services): string
    {
        $envLines = [];

        if ($env === 'develop') {
            $envLines[] = 'APP_ENV=development';
            $envLines[] = 'APP_DEBUG=true';
            $envLines[] = 'APP_URL=http://localhost';
        } elseif ($env === 'production') {
            $envLines[] = 'APP_ENV=production';
            $envLines[] = 'APP_DEBUG=false';
            $envLines[] = 'APP_URL=https://yourdomain.com';
        }

        $envLines[] = '';
        $envLines[] = 'APP_LOCALE=en';
        $envLines[] = 'APP_FALLBACK_LOCALE=en';
        $envLines[] = 'APP_FAKER_LOCALE=en_US';
        $envLines[] = '';
        $envLines[] = 'APP_MAINTENANCE_DRIVER=file';
        $envLines[] = '# APP_MAINTENANCE_STORE=database';
        $envLines[] = '';
        $envLines[] = 'PHP_CLI_SERVER_WORKERS=4';
        $envLines[] = '';
        $envLines[] = 'BCRYPT_ROUNDS=12';
        $envLines[] = '';
        $envLines[] = 'LOG_CHANNEL=stack';
        $envLines[] = 'LOG_STACK=single';
        $envLines[] = 'LOG_DEPRECATIONS_CHANNEL=null';
        $envLines[] = 'LOG_LEVEL=debug';
        $envLines[] = '';

        $activeDatabase = $this->getActiveDatabase($services);
        if ($activeDatabase === 'mysql') {
            $envLines[] = 'DB_CONNECTION=mysql';
            $envLines[] = 'DB_HOST=mysql';
            $envLines[] = 'DB_PORT=3306';
            $envLines[] = 'DB_DATABASE=laravel';
            $envLines[] = 'DB_USERNAME=laravel';
            $envLines[] = 'DB_PASSWORD=secret';
        } elseif ($activeDatabase === 'postgresql') {
            $envLines[] = 'DB_CONNECTION=pgsql';
            $envLines[] = 'DB_HOST=postgresql';
            $envLines[] = 'DB_PORT=5432';
            $envLines[] = 'DB_DATABASE=laravel';
            $envLines[] = 'DB_USERNAME=laravel';
            $envLines[] = 'DB_PASSWORD=secret';
        } else {
            $envLines[] = '# No active database service defined';
        }
        $envLines[] = '';

        $envLines[] = 'SESSION_DRIVER=database';
        $envLines[] = 'SESSION_LIFETIME=120';
        $envLines[] = 'SESSION_ENCRYPT=false';
        $envLines[] = 'SESSION_PATH=/';
        $envLines[] = 'SESSION_DOMAIN=null';
        $envLines[] = '';
        $envLines[] = 'BROADCAST_CONNECTION=log';
        $envLines[] = 'FILESYSTEM_DISK=local';
        $envLines[] = 'QUEUE_CONNECTION=database';
        $envLines[] = '';
        $envLines[] = 'CACHE_STORE=database';
        $envLines[] = '# CACHE_PREFIX=';
        $envLines[] = '';
        $envLines[] = 'MEMCACHED_HOST=127.0.0.1';
        $envLines[] = '';

        if (isset($services['redis']) && ($services['redis']['active'] ?? false)) {
            $envLines[] = 'REDIS_CLIENT=phpredis';
            $envLines[] = 'REDIS_PASSWORD=null';
            $envLines[] = 'REDIS_HOST=redis';
            $envLines[] = 'REDIS_PORT=6379';
        } else {
            $envLines[] = 'REDIS_CLIENT=phpredis';
            $envLines[] = 'REDIS_PASSWORD=null';
            $envLines[] = 'REDIS_HOST=127.0.0.1';
            $envLines[] = 'REDIS_PORT=6379';
        }
        $envLines[] = '';

        $envLines[] = 'MAIL_MAILER=log';
        $envLines[] = 'MAIL_SCHEME=null';
        $envLines[] = 'MAIL_HOST=127.0.0.1';
        $envLines[] = 'MAIL_PORT=2525';
        $envLines[] = 'MAIL_USERNAME=null';
        $envLines[] = 'MAIL_PASSWORD=null';
        $envLines[] = 'MAIL_FROM_ADDRESS="hello@example.com"';
        $envLines[] = 'MAIL_FROM_NAME="${APP_NAME}"';
        $envLines[] = '';
        $envLines[] = 'AWS_ACCESS_KEY_ID=';
        $envLines[] = 'AWS_SECRET_ACCESS_KEY=';
        $envLines[] = 'AWS_DEFAULT_REGION=us-east-1';
        $envLines[] = 'AWS_BUCKET=';
        $envLines[] = 'AWS_USE_PATH_STYLE_ENDPOINT=false';
        $envLines[] = '';
        $envLines[] = 'VITE_APP_NAME="${APP_NAME}"';
        $envLines[] = '';

        if (isset($services['rabbitmq']) && ($services['rabbitmq']['active'] ?? false)) {
            $envLines[] = 'RABBITMQ_HOST=rabbitmq';
            $envLines[] = 'RABBITMQ_PORT=5672';
        } else {
            $envLines[] = 'RABBITMQ_HOST=127.0.0.1';
            $envLines[] = 'RABBITMQ_PORT=5672';
        }

        return implode("\n", $envLines) . "\n";
    }

    protected function getActiveDatabase(array $services): ?string
    {
        $databaseServices = ['mysql', 'postgresql'];
        foreach ($databaseServices as $dbService) {
            if (isset($services[$dbService]) && ($services[$dbService]['active'] ?? false)) {
                return $dbService;
            }
        }
        return null;
    }

    protected function getDefaultConfig(): array
    {
        return [
            'environments' => [
                'develop' => [
                    'path' => base_path('docker/develop'),
                    'docker_compose' => base_path('docker/develop/docker-compose.yml'),
                    'env_file' => base_path('docker/develop/.env.develop'),
                    'services' => [
                        'app' => [
                            'active' => true,
                            'build' => [
                                'context' => base_path(),
                                'dockerfile' => 'docker/develop/Dockerfile',
                            ],
                            'ports' => ['8000:80'],
                            'volumes' => ['.:/var/www'],
                            'depends_on' => ['postgresql'],
                        ],
                        'mysql' => [
                            'active' => false,
                            'image' => 'mysql:8.0',
                            'ports' => ['3306:3306'],
                            'environment' => [
                                'MYSQL_DATABASE' => 'laravel',
                                'MYSQL_USER' => 'laravel',
                                'MYSQL_PASSWORD' => 'secret',
                                'MYSQL_ROOT_PASSWORD' => 'secret',
                            ],
                        ],
                        'redis' => [
                            'active' => false,
                            'image' => 'redis:7.0',
                            'ports' => ['6379:6379'],
                        ],
                        'rabbitmq' => [
                            'active' => false,
                            'image' => 'rabbitmq:3.12-management',
                            'ports' => ['5672:5672', '15672:15672'],
                            'environment' => [
                                'RABBITMQ_DEFAULT_USER' => 'guest',
                                'RABBITMQ_DEFAULT_PASS' => 'guest',
                            ],
                        ],
                        'postgresql' => [
                            'active' => true,
                            'image' => 'postgres:15',
                            'ports' => ['5432:5432'],
                            'environment' => [
                                'POSTGRES_DB' => 'laravel',
                                'POSTGRES_USER' => 'laravel',
                                'POSTGRES_PASSWORD' => 'secret',
                            ],
                        ],
                    ],
                ],
                'production' => [
                    'path' => base_path('docker/production'),
                    'docker_compose' => base_path('docker/production/docker-compose.yml'),
                    'env_file' => base_path('docker/production/.env.production'),
                    'services' => [
                        'app' => [
                            'active' => true,
                            'build' => [
                                'context' => base_path(),
                                'dockerfile' => 'docker/production/Dockerfile',
                            ],
                            'ports' => ['80:80'],
                            'volumes' => ['.:/var/www'],
                            'depends_on' => ['postgresql'],
                        ],
                        'mysql' => [
                            'active' => false,
                            'image' => 'mysql:8.0',
                            'ports' => ['3306:3306'],
                            'environment' => [
                                'MYSQL_DATABASE' => 'laravel',
                                'MYSQL_USER' => 'laravel',
                                'MYSQL_PASSWORD' => 'secret',
                                'MYSQL_ROOT_PASSWORD' => 'secret',
                            ],
                        ],
                        'redis' => [
                            'active' => false,
                            'image' => 'redis:7.0',
                            'ports' => ['6379:6379'],
                        ],
                        'rabbitmq' => [
                            'active' => false,
                            'image' => 'rabbitmq:3.12-management',
                            'ports' => ['5672:5672', '15672:15672'],
                            'environment' => [
                                'RABBITMQ_DEFAULT_USER' => 'guest',
                                'RABBITMQ_DEFAULT_PASS' => 'guest',
                            ],
                        ],
                        'postgresql' => [
                            'active' => true,
                            'image' => 'postgres:15',
                            'ports' => ['5432:5432'],
                            'environment' => [
                                'POSTGRES_DB' => 'laravel',
                                'POSTGRES_USER' => 'laravel',
                                'POSTGRES_PASSWORD' => 'secret',
                            ],
                        ],
                    ],
                ],
            ],
            'default_environment' => env('DOCKER_ENV', 'develop'),
            'commands' => [
                'up' => 'docker-compose -f %s up -d',
                'down' => 'docker-compose -f %s down',
                'build' => 'docker-compose -f %s build',
            ],
        ];
    }
}