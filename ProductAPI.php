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

namespace ProductAPI;

use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Thelia\Module\BaseModule;

class ProductAPI extends BaseModule
{
    public const DOMAIN_NAME = 'productapi';
    public const CONFIG_API_KEY = 'productapi_key';
    public const CONFIG_IMAGE_WIDTH = 'image_width';
    public const CONFIG_IMAGE_HEIGHT = 'image_height';
    public const CONFIG_FAILED_SIGNATURE_LIMIT = 'failed_signatures_per_minute';

    public const DEFAULT_IMAGE_SIZE = 500;
    public const DEFAULT_FAILED_SIGNATURE_LIMIT = 30;
    public const MINIMUM_API_KEY_LENGTH = 16;

    /**
     * The key is never given a default: without a configured key the API refuses to answer.
     */
    public static function getApiKey(): string
    {
        return trim((string) self::getConfigValue(self::CONFIG_API_KEY, ''));
    }

    public static function getImageSize(string $configName): int
    {
        $size = (int) self::getConfigValue($configName, (string) self::DEFAULT_IMAGE_SIZE);

        return $size > 0 ? $size : self::DEFAULT_IMAGE_SIZE;
    }

    public static function getFailedSignatureLimit(): int
    {
        $limit = (int) self::getConfigValue(self::CONFIG_FAILED_SIGNATURE_LIMIT, (string) self::DEFAULT_FAILED_SIGNATURE_LIMIT);

        return $limit > 0 ? $limit : self::DEFAULT_FAILED_SIGNATURE_LIMIT;
    }

    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([
                __DIR__.'/I18n/*',
                __DIR__.'/Tests/*',
                __DIR__.'/templates/*',
                __DIR__.'/Exception/*',
            ])
            ->autowire()
            ->autoconfigure();
    }
}
