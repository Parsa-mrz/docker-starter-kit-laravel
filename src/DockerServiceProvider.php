<?php

namespace ParsaMirzaie\DockerStarterKitLaravel;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;

class DockerServiceProvider extends ServiceProvider {

	public function boot() {
		$this->publishes(
			array(
				__DIR__ . '/../config/docker-config.php' => config_path( 'docker-config.php' ),
			),
			'docker-config'
		);

		$this->publishes(
			array(
				__DIR__ . '/../docker' => base_path( 'docker' ),
			),
			'docker-files'
		);

		$this->generateDockerComposeFiles();
	}

	public function register() {
		$this->mergeConfigFrom(
			__DIR__ . '/../config/docker-config.php',
			'docker-config'
		);
	}

	protected function generateDockerComposeFiles() {
		$config = config( 'docker-config' );

		foreach ( $config['environments'] as $env => $settings ) {
			$dockerComposeFile = $settings['docker_compose'];
			$services          = $settings['services'] ?? array();

			$yaml = $this->buildDockerComposeYaml( $services );

			File::ensureDirectoryExists( dirname( $dockerComposeFile ) );

			File::put( $dockerComposeFile, $yaml );
		}
	}

	protected function buildDockerComposeYaml( array $services ): string {
		$yaml = "version: '3'\nservices:\n";

		foreach ( $services as $serviceName => $config ) {
			$yaml .= "  $serviceName:\n";

			if ( isset( $config['image'] ) ) {
				$yaml .= "    image: {$config['image']}\n";
			}

			if ( isset( $config['build'] ) ) {
				$yaml .= "    build:\n";
				foreach ( $config['build'] as $key => $value ) {
					$yaml .= "      $key: $value\n";
				}
			}

			if ( isset( $config['ports'] ) ) {
				$yaml .= "    ports:\n";
				foreach ( $config['ports'] as $port ) {
					$yaml .= "      - \"$port\"\n";
				}
			}

			if ( isset( $config['volumes'] ) ) {
				$yaml .= "    volumes:\n";
				foreach ( $config['volumes'] as $volume ) {
					$yaml .= "      - \"$volume\"\n";
				}
			}

			if ( isset( $config['environment'] ) ) {
				$yaml .= "    environment:\n";
				foreach ( $config['environment'] as $key => $value ) {
					$yaml .= "      $key: \"$value\"\n";
				}
			}

			if ( isset( $config['depends_on'] ) ) {
				$yaml .= "    depends_on:\n";
				foreach ( $config['depends_on'] as $dep ) {
					$yaml .= "      - $dep\n";
				}
			}
		}

		return $yaml;
	}
}
