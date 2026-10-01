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

/**
 * The signature is sha1 of the query values in the order received, hash excluded, followed by the API key.
 */
final readonly class SignatureVerifier
{
    public function isConfigured(): bool
    {
        return '' !== ProductAPI::getApiKey();
    }

    /**
     * @param array<string, mixed> $query
     */
    public function isValid(array $query): bool
    {
        $apiKey = ProductAPI::getApiKey();
        $signature = $query['hash'] ?? null;

        if ('' === $apiKey || !\is_string($signature)) {
            return false;
        }

        unset($query['hash']);

        foreach ($query as $value) {
            if (!\is_scalar($value) && null !== $value) {
                return false;
            }
        }

        return hash_equals(sha1(implode($query).$apiKey), $signature);
    }
}
