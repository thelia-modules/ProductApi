<?php


namespace ProductAPI\Controller\Api;

use ColissimoLabel\Exception\Exception;
use ProductAPI\ProductAPI;
use ProductAPI\Service\ProductService;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\HttpFoundation\JsonResponse;
use Thelia\Core\Translation\Translator;

/**
 * Class ProductController
 * @package ProductAPI\Controller\Api
 * @author Florian Bernard <fbernard@openstudio.fr>
 */
class ProductController extends BaseFrontController
{
    public function getMethodAction(Request $request)
    {
        // TODO: locale | lang
        $hash = $request->get('hash');
        $country = $request->get('country', 'FRA');

        try{
            if($hash && !$this->verifyHash($request)) {
                return new JsonResponse(Translator::getInstance()->trans('You are not authorized to see this.', [], ProductAPI::DOMAIN_NAME), 403);
            }

            /** @var ProductService $productService */
            $productService = $this->getContainer()->get('product_api.product.service');

            $jsonResponse = $productService->getProduct($request->query->all(), $country);

            return new JsonResponse($jsonResponse, 200);

        } catch (Exception $e){
            return new JsonResponse($e->getMessage(), 400);
        }
    }

    private function verifyHash(Request $request)
    {
        $parameters = $request->query->all();
        unset($parameters['hash']);

        $values = implode($parameters);

        return $request->query->get('hash') === sha1($values . ProductAPI::API_KEY);
    }
}