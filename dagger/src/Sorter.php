<?php

declare(strict_types=1);

namespace DaggerModule;

use Dagger\Attribute\DaggerFunction;
use Dagger\Attribute\DaggerObject;
use Dagger\Attribute\DefaultPath;
use Dagger\Attribute\Doc;
use Dagger\Container;
use Dagger\Directory;

use function Dagger\dag;

#[DaggerObject]
#[Doc('Sorter dagger functions')]
class Sorter
{
    private const PHP_VERSIONS = ['8.1', '8.2', '8.3', '8.4'];

    #[DaggerFunction]
    #[Doc('Build test environnment')]
    public function build(
        #[DefaultPath('.')] Directory $source,
        string $phpVersion = '8.3',
        string $dependencyVersion = 'highest',
    ): Container
    {
        $composerCache = dag()->cacheVolume('composer');

        $composer = dag()
            ->container()
            ->from('composer:2');

        $installPhpExtensions = dag()
            ->http('https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions');

        $container = dag()
            ->container()
            ->from('php:' . $phpVersion)
            ->withFile('/usr/local/bin/install-php-extensions', $installPhpExtensions, 0755)
            ->withExec(['install-php-extensions', 'zip', 'opcache', 'pcov'])
            ->withFile('/usr/bin/composer', $composer->file('/usr/bin/composer'))
            ->withWorkdir('/app')
            ->withFile('composer.json', $source->file('composer.json'))
            ->withMountedCache('/root/.composer/cache', $composerCache);

        if ('lowest' === $dependencyVersion) {
            $container = $container
                ->withExec(['composer', 'update', '--prefer-lowest']);
        }

        return $container
            ->withExec(['composer', 'install'])
            ->withDirectory('examples', $source->directory('examples'))
            ->withDirectory('src', $source->directory('src'))
            ->withDirectory('tests', $source->directory('tests'))
            ->withFile('.php-cs-fixer.dist.php', $source->file('.php-cs-fixer.dist.php'))
            ->withFile('infection.json5', $source->file('infection.json5'))
            ->withFile('phpunit.xml.dist', $source->file('phpunit.xml.dist'))
            ->withFile('psalm.xml', $source->file('psalm.xml'));
    }

    #[DaggerFunction]
    #[Doc('Run test suite')]
    public function test(
        #[DefaultPath('.')] Directory $source,
        string $phpVersion = '8.3',
        string $dependencyVersion = 'highest',
        ?string $coverallsRepoToken = null,
        ?string $ciName = null,
        ?string $ciJobId = null,
        ?string $ciBranch = null,
    ): string
    {
        $container = $this
            ->build($source, $phpVersion, $dependencyVersion)
            ->withFile('/tmp/coveralls-linux.tar.gz', dag()->http('https://coveralls.io/coveralls-linux.tar.gz'))
            ->withExec(['tar', '-xvzf', '/tmp/coveralls-linux.tar.gz', '-C', '/usr/local/bin']);


        $exec = ['php', './vendor/bin/phpunit', '--coverage-text'];
        if ($coverallsRepoToken) {
            $exec[] = '--coverage-clover=build/logs/clover.xml';

            $container = $container->withEnvVariable('COVERALLS_REPO_TOKEN', $coverallsRepoToken);
        }

        if ($ciName) {
            $container = $container->withEnvVariable('CI_NAME', $ciName);
        }

        if ($ciJobId) {
            $container = $container->withEnvVariable('CI_JOB_ID', $ciJobId);
        }

        if ($ciBranch) {
            $container = $container->withEnvVariable('CI_BRANCH', $ciBranch);
        }

        $container = $container->withExec($exec);

        if ($coverallsRepoToken) {
            $container = $container->withExec(['coveralls', 'report']);
        }

        return $container
            ->stdout();
    }

    #[DaggerFunction]
    #[Doc('Run test suite for all supported PHP versions')]
    public function testAll(#[DefaultPath('.')] Directory $source): string
    {
        $output = '';
        foreach (self::PHP_VERSIONS as $phpVersion) {
            $output .= $this->test($source, $phpVersion);
            $output .= $this->test($source, $phpVersion, 'lowest');
        }

        return $output;
    }

    #[DaggerFunction]
    #[Doc('Psalm static analysis')]
    public function psalm(#[DefaultPath('.')] Directory $source): string
    {
        return $this
            ->build($source, '8.1')
            ->withExec(['./vendor/bin/psalm', '--show-info=true', '--no-diff'])
            ->stdout();
    }

    #[DaggerFunction]
    #[Doc('Infection mutation testing')]
    public function mutation(
        #[DefaultPath('.')] Directory $source,
        ?string $strykerDashboardApiKey = null,
        ?string $githubActions = null,
        ?string $githubRepository = null,
        ?string $githubRef = null,
    ): string
    {
        $container = $this
            ->build($source, '8.1')
            ->withExec(['apt-get', 'update'])
            ->withExec(['apt-get', 'install', '-y', 'git'])
            ->withDirectory('.git', $source->directory('.git'));

        $exec = ['./vendor/bin/infection', '--threads=1', '--min-msi=90'];

        if ($strykerDashboardApiKey && $githubActions) {
            $exec[] = '--logger-github=true';

            $container = $container
                ->withEnvVariable('GITHUB_ACTIONS', $githubActions)
                ->withEnvVariable('GITHUB_REPOSITORY', $githubRepository)
                ->withEnvVariable('GITHUB_REF', $githubRef)
                ->withEnvVariable('STRYKER_DASHBOARD_API_KEY', $strykerDashboardApiKey);
        }

        return $container
            ->withExec($exec)
            ->stdout();
    }

    #[DaggerFunction]
    #[Doc('PHP-CS-Fixer static analysis')]
    public function phpCsFixer(#[DefaultPath('.')] Directory $source): string
    {
        return $this
            ->build($source, '8.1')
            ->withExec(['./vendor/bin/php-cs-fixer', 'fix', '--dry-run', '--diff', '--ansi'])
            ->stdout();
    }

    #[DaggerFunction]
    #[Doc('Opens a PHP Aware shell')]
    public function cli(#[DefaultPath('.')] Directory $source, string $phpVersion = '8.4'): Container
    {
        return $this
            ->build($source, $phpVersion)
            ->withMountedDirectory('/app', $source)
            ->terminal();
    }
}
