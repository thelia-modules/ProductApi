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

namespace ProductAPI\Controller\Admin;

use ProductAPI\Form\ConfigurationForm;
use ProductAPI\ProductAPI;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Exception\FormValidationException;

class ConfigurationController extends BaseAdminController
{
    #[Route('/admin/module/ProductAPI/configuration', name: 'product_api_admin_configure', methods: ['POST'])]
    public function configureAction(LoggerInterface $logger): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, ProductAPI::DOMAIN_NAME, AccessManager::UPDATE)) {
            return $response;
        }

        $form = $this->createForm(ConfigurationForm::getName());

        try {
            $data = $this->validateForm($form)->getData();

            ProductAPI::setConfigValue(ProductAPI::CONFIG_API_KEY, trim((string) $data['api_key']));
            ProductAPI::setConfigValue(ProductAPI::CONFIG_IMAGE_WIDTH, (string) $data['image_width']);
            ProductAPI::setConfigValue(ProductAPI::CONFIG_IMAGE_HEIGHT, (string) $data['image_height']);
        } catch (FormValidationException $exception) {
            $this->addFlash('danger', $this->createStandardFormValidationErrorMessage($exception));
        } catch (\Throwable $exception) {
            $logger->error('ProductAPI: {message}', ['message' => $exception->getMessage(), 'exception' => $exception]);
            $this->addFlash('danger', $this->translator->trans('An unexpected error occurred, the configuration was not saved', [], ProductAPI::DOMAIN_NAME));
        }

        return $this->generateRedirectFromRoute('admin.module.configure', [], ['module_code' => ProductAPI::getModuleCode()]);
    }
}
