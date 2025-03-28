<?php

return array(
	'environments'        => array(
		'develop'    => array(
			'path'           => base_path( 'docker/develop' ),
			'docker_compose' => base_path( 'docker/develop/docker-compose.yml' ),
			'env_file'       => base_path( 'docker/develop/.env.develop' ),
			'services'       => array(
				'app'        => array(
					'active'  => true,
					'build'   => array(
						'context'    => base_path(),
						'dockerfile' => 'docker/develop/Dockerfile',
					),
					'ports'   => array( '8000:80' ),
					'volumes' => array( '.:/var/www' ),
				),
				'mysql'      => array(
					'active'      => false,
					'image'       => 'mysql:8.0',
					'ports'       => array( '3306:3306' ),
					'environment' => array(
						'MYSQL_DATABASE'      => 'laravel',
						'MYSQL_USER'          => 'laravel',
						'MYSQL_PASSWORD'      => 'secret',
						'MYSQL_ROOT_PASSWORD' => 'secret',
					),
				),
				'redis'      => array(
					'active' => false,
					'image'  => 'redis:7.0',
					'ports'  => array( '6379:6379' ),
				),
				'rabbitmq'   => array(
					'active'      => false,
					'image'       => 'rabbitmq:3.12-management',
					'ports'       => array( '5672:5672', '15672:15672' ),
					'environment' => array(
						'RABBITMQ_DEFAULT_USER' => 'guest',
						'RABBITMQ_DEFAULT_PASS' => 'guest',
					),
				),
				'postgresql' => array(
					'active'      => true,
					'image'       => 'postgres:15',
					'ports'       => array( '5432:5432' ),
					'environment' => array(
						'POSTGRES_DB'       => 'laravel',
						'POSTGRES_USER'     => 'laravel',
						'POSTGRES_PASSWORD' => 'secret',
					),
				),
			),
		),
		'production' => array(
			'path'           => base_path( 'docker/production' ),
			'docker_compose' => base_path( 'docker/production/docker-compose.yml' ),
			'env_file'       => base_path( 'docker/production/.env.production' ),
			'services'       => array(
				'app'        => array(
					'active'  => true,
					'build'   => array(
						'context'    => base_path(),
						'dockerfile' => 'docker/production/Dockerfile',
					),
					'ports'   => array( '80:80' ),
					'volumes' => array( '.:/var/www' ),
				),
				'mysql'      => array(
					'active'      => false,
					'image'       => 'mysql:8.0',
					'ports'       => array( '3306:3306' ),
					'environment' => array(
						'MYSQL_DATABASE'      => 'laravel',
						'MYSQL_USER'          => 'laravel',
						'MYSQL_PASSWORD'      => 'secret',
						'MYSQL_ROOT_PASSWORD' => 'secret',
					),
				),
				'redis'      => array(
					'active' => false,
					'image'  => 'redis:7.0',
					'ports'  => array( '6379:6379' ),
				),
				'rabbitmq'   => array(
					'active'      => false,
					'image'       => 'rabbitmq:3.12-management',
					'ports'       => array( '5672:5672', '15672:15672' ),
					'environment' => array(
						'RABBITMQ_DEFAULT_USER' => 'guest',
						'RABBITMQ_DEFAULT_PASS' => 'guest',
					),
				),
				'postgresql' => array(
					'active'      => true,
					'image'       => 'postgres:15',
					'ports'       => array( '5432:5432' ),
					'environment' => array(
						'POSTGRES_DB'       => 'laravel',
						'POSTGRES_USER'     => 'laravel',
						'POSTGRES_PASSWORD' => 'secret',
					),
				),
			),
		),
	),
	'default_environment' => env( 'DOCKER_ENV', 'develop' ),
	'commands'            => array(
		'up'    => 'docker-compose -f %s up -d',
		'down'  => 'docker-compose -f %s down',
		'build' => 'docker-compose -f %s build',
	),
);
