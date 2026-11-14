<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Process\Process;

/** Rootless loopback fixture; no host services/configuration or real secrets are changed. */
final class WebServerDeploymentRegressionTest extends TestCase
{
    public function testResolverPrecedenceUploadGuardsAndFpmOpcacheRestart(): void
    {
        if (!is_executable('/usr/sbin/nginx') || !is_executable('/usr/sbin/php-fpm8.4')) {
            self::markTestSkipped('Isolated deployment check requires Nginx and PHP 8.4 FPM.');
        }
        $directory = '/tmp/opencode/fieldnotes-web-test-'.bin2hex(random_bytes(8));
        $fs = new Filesystem();
        $fs->mkdir($directory.'/public/uploads/media/cache/thumbnail');
        $nginx = null;
        $fpm = null;
        try {
            $fs->dumpFile($directory.'/.env.local', 'FIXTURE_ONLY=value');
            $fs->chmod($directory.'/.env.local', 0640);
            $source = <<<'PHP'
<?php
declare(strict_types=1);
header('Content-Type: application/json');
echo json_encode([
    'version' => 'v1',
    'query' => $_SERVER['QUERY_STRING'] ?? '',
    'secretsReadable' => is_readable(dirname(__DIR__).'/.env.local'),
    'gd' => extension_loaded('gd'),
    'timestamps' => ini_get('opcache.validate_timestamps'),
]);
PHP;
            $fs->dumpFile($directory.'/public/index.php', $source);
            $fs->dumpFile($directory.'/public/uploads/media/cache/thumbnail/image.jpg', 'generated-image-fixture');
            $fs->dumpFile($directory.'/public/uploads/attack.php', '<?php echo "never-execute";');
            $fs->dumpFile($directory.'/public/.env.local', 'never-serve');
            $fs->dumpFile($directory.'/public/.secret.png', 'never-serve');
            $fs->dumpFile($directory.'/public/uploads/.secret.png', 'never-serve');
            $pool = (string) file_get_contents(dirname(__DIR__, 2).'/deploy/php-fpm/fieldnotes.conf');
            self::assertSame(1, preg_match('/^listen = (\/[^\r\n]+)$/m', $pool, $listen));
            $sampleSocket = $listen[1];
            $pool = str_replace($sampleSocket, $directory.'/fpm.sock', $pool);
            self::assertStringNotContainsString('/run/php/', $pool, 'Never bind a host service socket from the isolated fixture.');
            $fs->dumpFile($directory.'/fpm.conf', "[global]\npid = $directory/fpm.pid\nerror_log = $directory/fpm.log\ndaemonize = no\n".$pool);
            $fpmArguments = ['/usr/sbin/php-fpm8.4', '--nodaemonize', '--fpm-config', $directory.'/fpm.conf', '-d', 'opcache.file_update_protection=0'];
            (new Process(array_merge($fpmArguments, ['--test'])))->mustRun();
            // Validate the sample first; a rootless fixture cannot chown to www-data.
            $pool = str_replace(['listen.owner = www-data', 'listen.group = www-data'], ['; fixture socket owner', '; fixture socket group'], $pool);
            $fs->dumpFile($directory.'/fpm.conf', "[global]\npid = $directory/fpm.pid\nerror_log = $directory/fpm.log\ndaemonize = no\n".$pool);
            $fpm = new Process($fpmArguments);
            $fpm->start();
            $this->waitForSocket('unix://'.$directory.'/fpm.sock', $fpm);

            $port = $this->freePort();
            $server = (string) file_get_contents(dirname(__DIR__, 2).'/deploy/nginx.conf');
            $server = str_replace([
                'listen 80;', 'listen [::]:80;', '/var/www/fieldnotes/public',
                $sampleSocket, 'include fastcgi_params;',
                '/var/log/nginx/fieldnotes.access.log', '/var/log/nginx/fieldnotes.error.log',
            ], [
                'listen 127.0.0.1:'.$port.';', '', $directory.'/public',
                $directory.'/fpm.sock', 'include /etc/nginx/fastcgi_params;',
                $directory.'/access.log', $directory.'/error.log',
            ], $server);
            self::assertStringNotContainsString('/run/php/', $server);
            $fs->dumpFile($directory.'/nginx.conf', "pid $directory/nginx.pid;\nerror_log $directory/nginx-global.log;\nevents {}\nhttp {\n$server\n}\n");
            $nginxArguments = ['/usr/sbin/nginx', '-p', $directory, '-c', $directory.'/nginx.conf', '-g', 'daemon off;'];
            (new Process(array_merge($nginxArguments, ['-t'])))->mustRun();
            $nginx = new Process($nginxArguments);
            $nginx->start();
            $this->waitForSocket('tcp://127.0.0.1:'.$port, $nginx);
            $http = HttpClient::create(['base_uri' => 'http://127.0.0.1:'.$port, 'timeout' => 3, 'max_duration' => 5]);
            $resolved = $http->request('GET', '/media/cache/resolve/thumbnail/image.jpg?probe=forwarded');
            self::assertSame(200, $resolved->getStatusCode());
            $data = $resolved->toArray();
            self::assertSame('probe=forwarded', $data['query']);
            self::assertTrue($data['secretsReadable']); // Fixture owner, not a www-data identity check.
            self::assertTrue($data['gd']);
            self::assertSame('0', $data['timestamps']);
            self::assertArrayNotHasKey('expires', $resolved->getHeaders());
            $static = $http->request('GET', '/uploads/media/cache/thumbnail/image.jpg');
            self::assertSame(200, $static->getStatusCode());
            self::assertSame('generated-image-fixture', $static->getContent());
            self::assertStringContainsString('immutable', implode(' ', $static->getHeaders()['cache-control']));
            self::assertSame(404, $http->request('GET', '/missing.jpg')->getStatusCode());
            self::assertSame(403, $http->request('GET', '/uploads/attack.php')->getStatusCode());
            self::assertSame(403, $http->request('GET', '/.env.local')->getStatusCode());
            self::assertSame(403, $http->request('GET', '/.secret.png')->getStatusCode());
            self::assertSame(403, $http->request('GET', '/uploads/.secret.png')->getStatusCode());
            self::assertSame(404, $http->request('GET', '/index.php')->getStatusCode());

            $fs->dumpFile($directory.'/public/index.php', str_replace("'v1'", "'v2'", $source));
            self::assertSame('v1', $http->request('GET', '/')->toArray()['version'], 'Timestamp-disabled FPM must retain warmed bytecode before restart.');
            $fpm->stop(3, 3);
            $fpm = new Process($fpmArguments);
            $fpm->start();
            $this->waitForSocket('unix://'.$directory.'/fpm.sock', $fpm);
            self::assertSame('v2', $http->request('GET', '/')->toArray()['version'], 'FPM restart must load the updated bytecode.');
        } finally {
            $nginx?->stop(3, 3);
            $fpm?->stop(3, 3);
            $fs->remove($directory);
        }
    }

    private function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        self::assertIsResource($socket);
        $name = stream_socket_get_name($socket, false);
        self::assertIsString($name);
        fclose($socket);

        return (int) substr($name, (int) strrpos($name, ':') + 1);
    }

    private function waitForSocket(string $address, Process $process): void
    {
        for ($attempt = 0; $attempt < 100; ++$attempt) {
            if (!$process->isRunning()) {
                self::fail($process->getOutput().$process->getErrorOutput());
            }
            $socket = @stream_socket_client($address, timeout: 0.1);
            if (false !== $socket) {
                fclose($socket);

                return;
            }
            usleep(20000);
        }
        self::fail('Isolated service did not become ready: '.$process->getErrorOutput());
    }
}
