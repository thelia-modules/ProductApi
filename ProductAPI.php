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

namespace ProductAPI;

use Thelia\Module\BaseModule;

class ProductAPI extends BaseModule
{
    /** @var string */
    const DOMAIN_NAME = 'productapi';

    const API_KEY = 'ExRtVQjUCCBApuN4s4fPEQ6i5yggYvm2';

    const CONFIG_NAME_SERVER_HOST = 'server_host';

    /*
     * You may now override BaseModuleInterface methods, such as:
     * install, destroy, preActivation, postActivation, preDeactivation, postDeactivation
     *
     * Have fun !
     */

    public static function getServerHost()
    {
        return rtrim(self::getConfigValue(self::CONFIG_NAME_SERVER_HOST, null), '/');
    }
}
