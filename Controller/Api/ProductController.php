<?php


namespace ProductAPI\Controller\Api;

use ProductAPI\ProductAPI;
use ProductAPI\Service\ApiService;
use ProductAPI\Service\ProductService;
use Propel\Runtime\Exception\PropelException;
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

        /** @var ApiService $apiService */
        $apiService = $this->getContainer()->get('product_api.api.service');

        /** @var ProductService $productService */
        $productService = $this->getContainer()->get('product_api.product.service');

        try{
            if($hash && !$apiService->verifyHash($request)) {
                return new JsonResponse(Translator::getInstance()->trans('You are not authorized to see this.', [], ProductAPI::DOMAIN_NAME), 403);
            }

            $jsonResponse = $productService->getProduct($request->query->all(), $country);

            return new JsonResponse($jsonResponse, 200);

        } catch (PropelException $e){
            return new JsonResponse("PROPEL ERROR : " . $e->getMessage(), 400);
        } catch (\Exception $e){
            return new JsonResponse("UNKNOW ERROR : " . $e->getMessage(), 400);
        }
    }
}