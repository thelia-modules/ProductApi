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

use Symfony\Contracts\Service\ResetInterface;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;

/**
 * The API is stateless, so the URLs it builds carry the host of the request. When each language has its
 * own domain, they are moved onto the domain of the requested language: the blog displays them as they are.
 */
final class LanguageDomain implements ResetInterface
{
    /** @var array<string, Lang|null> */
    private array $languages = [];

    public function reset(): void
    {
        $this->languages = [];
    }

    public function findLanguage(string $locale): ?Lang
    {
        if (!\array_key_exists($locale, $this->languages)) {
            $this->languages[$locale] = LangQuery::create()->findOneByLocale($locale);
        }

        return $this->languages[$locale];
    }

    public function rebase(string $url, string $locale): string
    {
        if (!ConfigQuery::isMultiDomainActivated()) {
            return $url;
        }

        $domain = parse_url((string) $this->findLanguage($locale)?->getUrl());
        $parts = parse_url($url);

        if (!isset($domain['scheme'], $domain['host'], $parts['scheme'], $parts['host'])) {
            return $url;
        }

        $port = isset($domain['port']) ? ':'.$domain['port'] : '';
        $path = ($parts['path'] ?? '').(isset($parts['query']) ? '?'.$parts['query'] : '').(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');

        return $domain['scheme'].'://'.$domain['host'].$port.$path;
    }
}
