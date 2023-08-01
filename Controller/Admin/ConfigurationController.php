<?php


namespace ProductAPI\Controller\Admin;

use Exception;
use ProductAPI\Form\Configuration;
use ProductAPI\ProductAPI;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\JsonResponse;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Translation\Translator;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/module/ProductAPI', name: 'product_api_admin_')]
class ConfigurationController extends BaseAdminController
{
    #[Route('', name: 'view', methods: 'GET')]
    public function viewAction()
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), array('ProductAPI'), AccessManager::VIEW)) {
            return $response;
        }

        return $this->render('productapi/configuration');
    }

    #[Route('', name: 'configure', methods: 'POST')]
    public function configureAction(Translator $translator)
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), array('ProductAPI'), AccessManager::VIEW)) {
            return $response;
        }

        $form = $this->createForm(Configuration::getName());

        try {
            $data = $this->validateForm($form, 'POST')->getData();

            ProductAPI::setConfigValue('image_width', $data['image_width']);
            ProductAPI::setConfigValue('image_height', $data['image_height']);

        } catch (Exception $e) {
            $this->setupFormErrorContext(
                $translator->trans("ProductAPI configuration", [], ProductAPI::DOMAIN_NAME),
                $e->getMessage(),
                $form,
                $e
            );
        }

        return $this->generateSuccessRedirect($form);
    }

    /**
     * @return JsonResponse The api key
     */
    public function getApiKeyAction(): JsonResponse
    {
        return new JsonResponse(ProductAPI::API_KEY, 200);
    }

    #[Route('/update-api-key', name: 'update_api_key', methods: 'POST')]
    public function updateApiKey(Request $request)
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), array('productapi'), AccessManager::UPDATE)) {
            return $response;
        }

        try {
            ProductAPI::setConfigValue('productapi_key',$request->get('newKey'));
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }

        return new JsonResponse([], 200);
    }
}
