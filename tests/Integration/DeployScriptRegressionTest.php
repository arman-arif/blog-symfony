<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/** Runs the real Bash entry point with recorded executables; never controls host services. */
final class DeployScriptRegressionTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = '/tmp/opencode/fieldnotes-deploy-test-'.bin2hex(random_bytes(8));
        $fs = new Filesystem();
        $fs->mkdir($this->directory);
        foreach (['php', 'composer', 'sudo', 'systemctl'] as $name) {
            $fs->dumpFile($this->directory.'/'.$name, <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
name="$(basename "$0")"
printf '%s' "$name" >> "$CALL_LOG"
printf ' %s' "$@" >> "$CALL_LOG"
printf '\n' >> "$CALL_LOG"
if [[ "$name" == sudo ]]; then
  [[ "$1" == -n ]] || exit 90
  shift
  exec "$@"
fi
if [[ "$name $*" == *"${FAIL_MATCH:-never-match}"* ]]; then exit 42; fi
if [[ "$name $1" == 'systemctl list-units' ]]; then
  echo 'fieldnotes-worker@async.service loaded active running default worker'
  echo 'fieldnotes-worker@2.service loaded active running scaled worker'
fi
if [[ "$name $1" == 'systemctl is-active' ]]; then
  shift
  # Match systemctl's real any-active semantics, including mixed service states.
  for unit in "$@"; do
    [[ "$unit" == --quiet ]] && continue
    if [[ "$unit" != "${INACTIVE_UNIT:-}" ]]; then exit 0; fi
  done
  exit 3
fi
BASH);
            $fs->chmod($this->directory.'/'.$name, 0700);
        }
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
        parent::tearDown();
    }

    public function testDefaultDeployQuiescesServicesPreservesScaledWorkersAndRestartsFpm(): void
    {
        $process = $this->deploy();
        self::assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        $calls = (string) file_get_contents($this->directory.'/calls.log');
        $stop = 'systemctl stop fieldnotes-worker@async.service fieldnotes-scheduler.service fieldnotes-worker@2.service';
        self::assertStringContainsString($stop, $calls);
        self::assertLessThan(strpos($calls, 'composer install'), strpos($calls, $stop));
        self::assertLessThan(strpos($calls, 'composer install'), strpos($calls, 'systemctl stop custom-fpm.service'));
        self::assertLessThan(strpos($calls, 'php bin/console doctrine:migrations:migrate'), strpos($calls, 'composer install'));
        self::assertLessThan(strpos($calls, 'systemctl restart custom-fpm.service'), strpos($calls, 'php bin/console cache:warmup'));
        self::assertStringContainsString('systemctl restart fieldnotes-worker@async.service fieldnotes-scheduler.service fieldnotes-worker@2.service', $calls);
        foreach (['custom-fpm.service', 'fieldnotes-worker@async.service', 'fieldnotes-scheduler.service', 'fieldnotes-worker@2.service'] as $unit) {
            self::assertStringContainsString('systemctl is-active --quiet '.$unit."\n", $calls);
        }
        self::assertStringNotContainsString('fixtures', $calls);
        self::assertStringContainsString('Deployment completed', $process->getOutput());
    }

    /** @return iterable<string, array{string}> */
    public static function failures(): iterable
    {
        yield 'worker discovery' => ['systemctl list-units'];
        yield 'missing stop privilege' => ['systemctl stop fieldnotes-worker'];
        yield 'fpm stop denied' => ['systemctl stop custom-fpm'];
        yield 'dependency failure' => ['composer install'];
        yield 'migration failure' => ['php bin/console doctrine:migrations:migrate'];
        yield 'fpm restart failure' => ['systemctl restart custom-fpm'];
        yield 'worker restart failure' => ['systemctl restart fieldnotes-worker'];
        yield 'inactive runtime' => ['systemctl is-active'];
    }

    #[DataProvider('failures')]
    public function testFailuresNeverReportSuccess(string $failure): void
    {
        $process = $this->deploy(['FAIL_MATCH' => $failure]);
        self::assertSame(42, $process->getExitCode());
        self::assertStringNotContainsString('Deployment completed', $process->getOutput());
        $calls = (string) file_get_contents($this->directory.'/calls.log');
        if (str_contains($failure, 'stop') || str_contains($failure, 'list-units')) {
            self::assertStringNotContainsString('composer install', $calls);
            self::assertStringNotContainsString('doctrine:migrations:migrate', $calls);
        }
        if (str_contains($failure, 'migration')) {
            self::assertStringNotContainsString('cache:warmup', $calls);
            self::assertStringNotContainsString('systemctl restart', $calls);
        }
    }

    /** @return iterable<string, array{string}> */
    public static function inactiveUnits(): iterable
    {
        yield 'FPM' => ['custom-fpm.service'];
        yield 'default async worker' => ['fieldnotes-worker@async.service'];
        yield 'scheduler' => ['fieldnotes-scheduler.service'];
        yield 'scaled async worker' => ['fieldnotes-worker@2.service'];
    }

    #[DataProvider('inactiveUnits')]
    public function testOneInactiveServiceCannotBeMaskedByOtherRunningServices(string $unit): void
    {
        $process = $this->deploy(['INACTIVE_UNIT' => $unit]);
        self::assertSame(3, $process->getExitCode());
        self::assertStringNotContainsString('Deployment completed', $process->getOutput());
        self::assertStringContainsString('systemctl is-active --quiet '.$unit."\n", (string) file_get_contents($this->directory.'/calls.log'));
    }

    public function testNonProductionEnvironmentFailsBeforeAnyExternalCommand(): void
    {
        $process = $this->deploy(['APP_ENV' => 'dev']);
        self::assertSame(2, $process->getExitCode());
        self::assertFileDoesNotExist($this->directory.'/calls.log');
    }

    public function testInvalidFpmUnitFailsBeforeAnyExternalCommand(): void
    {
        $process = $this->deploy(['PHP_FPM_SERVICE' => '--force']);
        self::assertSame(2, $process->getExitCode());
        self::assertFileDoesNotExist($this->directory.'/calls.log');
    }

    public function testRedisDsnInterpolationUsesTheWorkerIdentityInsteadOfTheCliFallback(): void
    {
        $unit = (string) file_get_contents(dirname(__DIR__, 2).'/deploy/systemd/fieldnotes-worker@.service');
        self::assertStringContainsString('messenger:consume async ', $unit);
        self::assertStringContainsString('Environment=MESSENGER_CONSUMER_NAME=fieldnotes-%H-%i', $unit);
        (new Filesystem())->dumpFile($this->directory.'/fixture.env', <<<'ENV'
MESSENGER_CONSUMER_NAME=fieldnotes-cli
MESSENGER_TRANSPORT_DSN="redis://127.0.0.1:6379/messages/symfony/${MESSENGER_CONSUMER_NAME}"
ENV);
        $process = new Process([
            PHP_BINARY, '-r',
            'require $argv[1]; (new Symfony\Component\Dotenv\Dotenv())->load($argv[2]); echo $_SERVER["MESSENGER_TRANSPORT_DSN"];',
            dirname(__DIR__, 2).'/vendor/autoload.php', $this->directory.'/fixture.env',
        ], env: ['MESSENGER_CONSUMER_NAME' => 'fieldnotes-test-host-2', 'MESSENGER_TRANSPORT_DSN' => false]);
        $process->mustRun();
        self::assertSame('redis://127.0.0.1:6379/messages/symfony/fieldnotes-test-host-2', $process->getOutput());
    }

    /** @param array<string, string> $environment */
    private function deploy(array $environment = []): Process
    {
        $process = new Process(['bash', dirname(__DIR__, 2).'/deploy.sh'], env: array_replace([
            'APP_DIR' => $this->directory,
            'APP_ENV' => 'prod',
            'APP_DEBUG' => '0',
            'PHP_BIN' => $this->directory.'/php',
            'COMPOSER_BIN' => $this->directory.'/composer',
            'SUDO_BIN' => $this->directory.'/sudo',
            'SYSTEMCTL_BIN' => $this->directory.'/systemctl',
            'PHP_FPM_SERVICE' => 'custom-fpm.service',
            'CALL_LOG' => $this->directory.'/calls.log',
        ], $environment));
        $process->run(); // Failures are intentionally asserted by the caller.

        return $process;
    }
}
