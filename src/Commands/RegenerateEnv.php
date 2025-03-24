<?php

namespace ParsaMirzaie\DockerStarterKitLaravel\Commands;

use Illuminate\Console\Command;
use ParsaMirzaie\DockerStarterKitLaravel\DockerServiceProvider;

class RegenerateEnv extends Command {

	protected $signature   = 'docker:regen-env';
	protected $description = 'Regenerate Docker environment and compose files based on the current docker-config.php';

	public function handle() {
		$provider = new DockerServiceProvider( $this->laravel );
		$this->info( 'Regenerating Docker environment and compose files...' );

		$provider->generateDockerComposeFiles();
		$provider->generateEnvFiles();

		$this->info( 'Docker environment and compose files regenerated successfully.' );
	}
}
