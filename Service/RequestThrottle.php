<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ProductAPI\Service;

use ProductAPI\ProductAPI;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

/**
 * Failed signatures per client address in a fixed window: a caller that signs correctly is never throttled,
 * one that guesses the signature is blocked once it has failed too often. The address is the one Symfony
 * resolves: behind a proxy it is only right when `trusted_proxies` is configured.
 */
final readonly class RequestThrottle
{
    public function __construct(
        #[Autowire(service: 'cache.app')]
        private CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * @return int seconds to wait before retrying, 0 when the client is not blocked
     */
    public function retryAfter(string $clientAddress): int
    {
        $limit = $this->limiter($clientAddress)->consume(0);

        if ($limit->getRemainingTokens() > 0) {
            return 0;
        }

        return max(1, $limit->getRetryAfter()->getTimestamp() - time());
    }

    public function recordFailure(string $clientAddress): void
    {
        $this->limiter($clientAddress)->consume();
    }

    private function limiter(string $clientAddress): LimiterInterface
    {
        return (new RateLimiterFactory(
            [
                'id' => 'product_api_failed_signature',
                'policy' => 'fixed_window',
                'limit' => ProductAPI::getFailedSignatureLimit(),
                'interval' => '1 minute',
            ],
            new CacheStorage($this->cache),
        ))->create($clientAddress);
    }
}
