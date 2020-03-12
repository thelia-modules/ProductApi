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

        $apiKey = ProductAPI::API_KEY;

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
    }

    public function getName(){
        return 'product_api';
    }
}