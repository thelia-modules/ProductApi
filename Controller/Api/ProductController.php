<?php


namespace ProductAPI\Controller\Api;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\EventDispatcher\EventDispatcher;
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
    public function getByRefAction($ref, $countryIso3)
    {
        $productService = $this->getContainer()->get('product_api.product.service');
        $data = $productService->getByRef($ref, $countryIso3);

        return new JsonResponse($data);
    }
}