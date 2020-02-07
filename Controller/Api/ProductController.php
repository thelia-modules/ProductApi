<?php


namespace ProductAPI\Controller\Api;

use ProductAPI\ProductAPI;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Action\Image;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\HttpFoundation\JsonResponse;
use Thelia\Model\Base\AttributeAvI18nQuery;
use Thelia\Model\Base\AttributeCombinationQuery;
use Thelia\Model\Base\AttributeI18nQuery;
use Thelia\Model\Base\ConfigQuery;
use Thelia\Model\Base\ProductI18nQuery;
use Thelia\Model\Base\ProductPriceQuery;
use Thelia\Model\Base\ProductSaleElementsQuery;
use Thelia\Model\ProductI18n;
use Thelia\Model\ProductQuery;

/**
 * Class ProductController
 * @package ProductAPI\Controller\Api
 * @author Florian Bernard <fbernard@openstudio.fr>
 */
class ProductController extends BaseFrontController
{
    public function getMethodAction(Request $request)
    {
        $parameters = $request->query->all();
        $jsonResponse = [];
        $code = 404;

        if(!empty($parameters['hash'])){

            $hash = $parameters['hash'];
            unset($parameters['hash']);

            if(self::verifyHash($parameters, $hash)){

                if(!empty($parameters['lang'])){
                    $lang = $parameters['lang'];
                    unset($parameters['lang']);
                } else $lang = "FRA";

                $code = 200;
                $productService = $this->getContainer()->get('product_api.product.service');
                $jsonResponse = $productService->get($parameters, $lang);

            } else {
                $jsonResponse['message'] = "Hash incorrect";
                $code = 401;
            }

        } else {
            $jsonResponse['message'] = "Vous devez vous ajouter le hash de votre requête et de la clé d'API.";
            $code = 403;
        }

        return new JsonResponse($jsonResponse, $code);
        /*$productService = $this->getContainer()->get('product_api.product.service');
        $data = $productService->getByRef($ref, $countryIso3);

        return new JsonResponse($data);*/
    }

    private static function verifyHash($parameters, $hash)
    {
        $values = implode($parameters);

        if($hash === sha1($values . ProductAPI::API_KEY)){
            return true;
        } else {
            return false;
        }
    }
}