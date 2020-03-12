<?php
/*************************************************************************************/
/*      This file is part of the GoogleTagManager package.                           */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace ProductAPI\Form;


use ProductAPI\ProductAPI;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

/**
 * Class Configuration
 * @package ProductAPI\Form
 * @author Florian Bernard <fbernard@openstudio.fr>
 */
class Configuration extends BaseForm
{
    protected function buildForm()
    {
        $form = $this->formBuilder;

        $apiKey = ProductAPI::getConfigValue('productapi_key', ProductAPI::API_KEY);
        $apiUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/api/product';

        $form->add(
            "api_key",
            "text",
            array(
                'data'  => $apiKey,
                'label' => Translator::getInstance()->trans("API Key",[] ,ProductAPI::DOMAIN_NAME),
                'label_attr' => array(
                    'for' => "api_key"
                ),
            )
        );

        $form->add(
            "api_url",
            "text",
            array(
                'data'  => $apiUrl,
                'label' => Translator::getInstance()->trans("API URL",[] ,ProductAPI::DOMAIN_NAME),
                'label_attr' => array(
                    'for' => "api_url"
                ),
            )
        );
    }

    public function getName(){
        return 'product_api';
    }
}