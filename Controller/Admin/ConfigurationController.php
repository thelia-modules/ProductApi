<?php


namespace ProductAPI\Controller\Admin;


use ColissimoLabel\Exception\Exception;
use ElasticSearchProduct\ElasticSearchProduct;
use ProductAPI\ProductAPI;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\JsonResponse;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;

class ConfigurationController extends BaseAdminController
{
    /**
     * @return mixed|\Thelia\Core\HttpFoundation\Response
     */
    public function viewAction()
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), array('ProductAPI'), AccessManager::VIEW)) {
            return $response;
        }

        return $this->render('productapi/configuration');
    }

    /**
     * @return mixed|\Thelia\Core\HttpFoundation\Response
     */
    public function configureAction()
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), array('ProductAPI'), AccessManager::VIEW)) {
            return $response;
        }

        $form = $this->createForm('product_api.configuration.form');

        try {
            $data = $this->validateForm($form, 'POST')->getData();

            ProductAPI::setConfigValue('image_width', $data['image_width']);
            ProductAPI::setConfigValue('image_height', $data['image_height']);

        } catch (\Exception $e) {
            $this->setupFormErrorContext(
                $this->getTranslator()->trans("ProductAPI configuration", [], ProductAPI::DOMAIN_NAME),
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
    public function getApiKeyAction()
    {
        return JsonResponse::create(ProductAPI::API_KEY, 200);
    }

    /**
     * @return JsonResponse The result
     */
    public function updateApiKey(Request $request)
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), array('productapi'), AccessManager::UPDATE)) {
            return $response;
        }

        try {
            ProductAPI::setConfigValue('productapi_key',$request->get('newKey'));
        } catch (\Exception $e) {
            return JsonResponse::create(['error' => $e->getMessage()], 500);
        }

        return JsonResponse::create([], 200);
    }
}
