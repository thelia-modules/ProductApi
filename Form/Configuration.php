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
    protected function trans($str, $params = [])
    {
        return Translator::getInstance()->trans($str, $params, ProductAPI::DOMAIN_NAME);
    }

    protected function buildForm()
    {
        $form = $this->formBuilder;

        $form->add(
            "api_key",
            "text",
            array(
                'data'  => ProductAPI::getConfigValue('productapi_key', ProductAPI::API_KEY),
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
                'data'  => ProductAPI::getApiUrl(),
                'label' => Translator::getInstance()->trans("API URL",[] ,ProductAPI::DOMAIN_NAME),
                'label_attr' => array(
                    'for' => "api_url"
                ),
            )
        );

        $form->add(
            'image_width',
            'text',
            array(
                'required' => true,
                'label' => $this->trans('Images width'),
                'data' => ProductAPI::getConfigValue('image_width', 500),
                'label_attr' => array(
                    'for' => 'image_width',
                    'help' => $this->trans('Results images width')
                )
            )
        );

        $form->add(
            'image_height',
            'text',
            array(
                'required' => true,
                'label' => $this->trans('Images height'),
                'data' => ProductAPI::getConfigValue('image_height', 500),
                'label_attr' => array(
                    'for' => 'image_height',
                    'help' => $this->trans('Results images height')
                )
            )
        );
    }

    public function getName(){
        return 'product_api';
    }
}