<?php

namespace ParsaMirzaie\DockerStarterKitLaravel\Commands;

use Illuminate\Console\Command;
use ParsaMirzaie\DockerStarterKitLaravel\DockerServiceProvider;

class RegenerateEnv extends Command {

	protected $signature   = 'docker:regen-env';
	protected $description = 'Regenerate Docker environment files based on the current docker-config.php';

	public function handle() {
		$provider = new DockerServiceProvider( $this->laravel );
		$this->info( 'Regenerating Docker environment files...' );

		$reflection = new \ReflectionClass( $provider );
		$method     = $reflection->getMethod( 'generateEnvFiles' );
		$method->setAccessible( true );
		$method->invoke( $provider );

		$this->info( 'Docker environment files regenerated successfully.' );
	}
}
