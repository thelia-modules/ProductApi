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

namespace ProductAPI\Form;

use ProductAPI\ProductAPI;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

class ConfigurationForm extends BaseForm
{
    public static function getName(): string
    {
        return 'productapi_form_configuration';
    }

    protected function buildForm(): void
    {
        $translator = Translator::getInstance();

        $this->formBuilder
            ->add('api_key', TextType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                    new Length(min: ProductAPI::MINIMUM_API_KEY_LENGTH, max: 255),
                ],
                'data' => ProductAPI::getApiKey(),
                'label' => $translator->trans('API Key', [], ProductAPI::DOMAIN_NAME),
                'label_attr' => [
                    'help' => $translator->trans('At least %length% characters. The blog signs its requests with this key: change it there in the same operation.', ['%length%' => ProductAPI::MINIMUM_API_KEY_LENGTH], ProductAPI::DOMAIN_NAME),
                ],
            ])
            ->add('image_width', IntegerType::class, [
                'required' => true,
                'constraints' => [new NotBlank(), new GreaterThan(0)],
                'data' => ProductAPI::getImageSize(ProductAPI::CONFIG_IMAGE_WIDTH),
                'label' => $translator->trans('Images width', [], ProductAPI::DOMAIN_NAME),
                'label_attr' => ['help' => $translator->trans('Results images width', [], ProductAPI::DOMAIN_NAME)],
            ])
            ->add('image_height', IntegerType::class, [
                'required' => true,
                'constraints' => [new NotBlank(), new GreaterThan(0)],
                'data' => ProductAPI::getImageSize(ProductAPI::CONFIG_IMAGE_HEIGHT),
                'label' => $translator->trans('Images height', [], ProductAPI::DOMAIN_NAME),
                'label_attr' => ['help' => $translator->trans('Results images height', [], ProductAPI::DOMAIN_NAME)],
            ]);
    }
}
