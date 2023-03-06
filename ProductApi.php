<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace ProductApi;

use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Thelia\Module\BaseModule;

class ProductApi extends BaseModule
{
    /** @var string */
    const DOMAIN_NAME = 'productapi';
    const API_KEY = 'ExRtVQjUCCBApuN4s4fPEQ6i5yggYvm2';
    const CONFIG_NAME_SERVER_HOST = 'server_host';

    public static function getApiUrl()
    {
        return 'https://' . $_SERVER['HTTP_HOST'] . '/api/product';
    }

    public static function getServerHost()
    {
        return rtrim(self::getConfigValue(self::CONFIG_NAME_SERVER_HOST, null), '/');
    }

    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([THELIA_MODULE_DIR . ucfirst(self::getModuleCode()). "/I18n/*"])
            ->autowire(true)
            ->autoconfigure(true);
    }
}
