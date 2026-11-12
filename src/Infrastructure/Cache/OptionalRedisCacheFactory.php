<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class OptionalRedisCacheFactory
{
    public function __construct(
        #[Autowire(env: 'REDIS_URL')] private string $redisUrl,
        #[Autowire('%kernel.cache_dir%/pools/optional-redis')] private string $directory,
    ) {
    }

    public function create(string $namespace, int $defaultLifetime = 600): AdapterInterface
    {
        if ('' === $this->redisUrl) {
            return new FilesystemAdapter($namespace, $defaultLifetime, $this->directory);
        }

        // Configured Redis failures surface rather than silently losing shared cache semantics.
        return new RedisAdapter(RedisAdapter::createConnection($this->redisUrl), $namespace, $defaultLifetime);
    }
}
