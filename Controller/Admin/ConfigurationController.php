<?php


namespace ProductAPI\Controller\Admin;


use ElasticSearchProduct\ElasticSearchProduct;
use ProductAPI\ProductAPI;
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
     * @return JsonResponse The api key
     */
    public function getApiKeyAction()
    {
        return JsonResponse::create(ProductAPI::API_KEY, 200);
    }
}
