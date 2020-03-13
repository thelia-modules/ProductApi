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
        if(empty($request->query->all())) {
            return new JsonResponse(['message' => 'Thelia Product API is working !']);
        }

        // TODO: locale | lang
        $hash = $request->get('hash');
        $country = $request->get('country', 'FRA');
        $lang = $request->get('lang', 'fr_FR');

        /** @var ApiService $apiService */
        $apiService = $this->getContainer()->get('product_api.api.service');

        /** @var ProductService $productService */
        $productService = $this->getContainer()->get('product_api.product.service');

        try{
            if($hash && !$apiService->verifyHash($request)) {
                return new JsonResponse(Translator::getInstance()->trans('You are not authorized to see this.', [], ProductAPI::DOMAIN_NAME), 403);
            }

            $jsonResponse = $productService->getProduct($request->query->all(), $country, $lang);

            return new JsonResponse($jsonResponse, 200);

        } catch (PropelException $e){
            return new JsonResponse("PROPEL ERROR : " . $e->getMessage(), 400);
        } catch (\Exception $e){
            return new JsonResponse("UNKNOW ERROR : " . $e->getMessage(), 400);
        }
    }
}