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
        $sourceDockerPath = __DIR__ . '/../docker';
        $destinationDockerPath = base_path('docker');

        if (file_exists($sourceDockerPath) && !file_exists($destinationDockerPath)) {
            File::copyDirectory($sourceDockerPath, $destinationDockerPath);
        }

        $this->publishes([
            __DIR__ . '/../config/docker-config.php' => config_path('docker-config.php'),
        ], 'docker-config');

        $this->publishes([
            __DIR__ . '/../docker' => base_path('docker'),
        ], 'docker-files');

        if ($this->app->runningInConsole()) {
            $this->commands([
                RegenerateEnv::class,
            ]);
        }

        $this->generateDockerComposeFiles();
        $this->generateEnvFiles();
    }

    public function register()
    {
        $configPath = __DIR__ . '/../config/docker-config.php';
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

            // Define volumes and networks only if relevant services are active
            $volumes = [];
            if (isset($activeServices['app'])) {
                $volumes["vendor_$env"] = new \stdClass();
                $volumes['laravel_storage'] = new \stdClass();
            }
            if (isset($activeServices['postgresql'])) {
                $volumes["pgdata_$env"] = new \stdClass();
            }
            if (isset($activeServices['redis'])) {
                $volumes["redisdata_$env"] = new \stdClass();
            }

            $dockerCompose = [
                'version' => '3.8',
                'volumes' => $volumes,
                'networks' => [
                    'app-network' => [
                        'driver' => 'bridge',
                    ],
                ],
                'services' => [],
            ];

            // Apply custom configurations only to active services
            foreach ($activeServices as $serviceName => $serviceConfig) {
                switch ($serviceName) {
                    case 'app':
                        $serviceConfig['image'] = "personal_website_$env";
                        $serviceConfig['container_name'] = 'laravel_app';
                        $serviceConfig['env_file'] = ["envs/$env/.env"];
                        $serviceConfig['volumes'] = [
                            '.:/var/www/html',
                            "vendor_$env:/var/www/html/vendor",
                            'laravel_storage:/var/www/html/storage',
                        ];
                        $serviceConfig['ports'] = ['8000:8000', '5173:5173'];
                        $serviceConfig['networks'] = ['app-network'];
                        $serviceConfig['healthcheck'] = [
                            'test' => ['CMD', 'nc', '-z', 'localhost', '8000'],
                            'interval' => '10s',
                            'timeout' => '5s',
                            'retries' => 5,
                        ];
                        break;
                    case 'postgresql':
                        $serviceConfig['container_name'] = 'postgres';
                        $serviceConfig['env_file'] = ["envs/$env/.env"];
                        $serviceConfig['environment'] = [
                            'POSTGRES_PASSWORD' => '5@K2!pS@x^hEt39JB@3Wsx&h2wMPB',
                            'POSTGRES_USER' => 'Strongbox3978',
                            'POSTGRES_DB' => 'laravel_app',
                        ];
                        $serviceConfig['volumes'] = ["pgdata_$env:/var/lib/postgresql/data"];
                        $serviceConfig['networks'] = ['app-network'];
                        break;
                    case 'redis':
                        $serviceConfig['container_name'] = 'redis';
                        $serviceConfig['env_file'] = ["envs/$env/.env"];
                        $serviceConfig['volumes'] = ["redisdata_$env:/data"];
                        $serviceConfig['networks'] = ['app-network'];
                        break;
                }
                $dockerCompose['services'][$serviceName] = $serviceConfig;
            }

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
            $envFilePath = base_path("envs/$env/.env");
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
                    continue; // Only one database service can be active
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
        $envLines[] = 'APP_KEY='; // Generate with `php artisan key:generate` later
        $envLines[] = 'APP_LOCALE=en';
        $envLines[] = 'APP_FALLBACK_LOCALE=en';
        $envLines[] = 'APP_FAKER_LOCALE=en_US';
        $envLines[] = '';
        $envLines[] = 'LOG_CHANNEL=stack';
        $envLines[] = 'LOG_LEVEL=debug';

        $activeDatabase = $this->getActiveDatabase($services);
        if ($activeDatabase === 'mysql') {
            $envLines[] = 'DB_CONNECTION=mysql';
            $envLines[] = 'DB_HOST=mysql';
            $envLines[] = 'DB_PORT=3306';
            $envLines[] = 'DB_DATABASE=laravel_app';
            $envLines[] = 'DB_USERNAME=Strongbox3978';
            $envLines[] = 'DB_PASSWORD=5@K2!pS@x^hEt39JB@3Wsx&h2wMPB';
        } elseif ($activeDatabase === 'postgresql') {
            $envLines[] = 'DB_CONNECTION=pgsql';
            $envLines[] = 'DB_HOST=postgres';
            $envLines[] = 'DB_PORT=5432';
            $envLines[] = 'DB_DATABASE=laravel_app';
            $envLines[] = 'DB_USERNAME=Strongbox3978';
            $envLines[] = 'DB_PASSWORD=5@K2!pS@x^hEt39JB@3Wsx&h2wMPB';
        } else {
            $envLines[] = '# No active database service defined';
        }

        if (isset($services['redis']) && ($services['redis']['active'] ?? false)) {
            $envLines[] = 'REDIS_HOST=redis';
            $envLines[] = 'REDIS_PORT=6379';
            $envLines[] = 'REDIS_PASSWORD=null';
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
                    'env_file' => base_path('envs/develop/.env'),
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
                    'env_file' => base_path('envs/production/.env'),
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