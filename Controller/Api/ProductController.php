<?php


namespace ProductAPI\Controller;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\HttpFoundation\JsonResponse;
use Thelia\Model\Base\AttributeAvI18nQuery;
use Thelia\Model\Base\AttributeCombinationQuery;
use Thelia\Model\Base\AttributeI18nQuery;
use Thelia\Model\Base\ProductI18nQuery;
use Thelia\Model\Base\ProductPriceQuery;
use Thelia\Model\Base\ProductSaleElementsQuery;
use Thelia\Model\ProductQuery;

/**
 * Class ProductController
 * @package ProductAPI\Controller\Api
 * @author Florian Bernard <fbernard@openstudio.fr>
 */
class ProductController extends BaseFrontController
{
    public function getByRefAction($ref)
    {
        try {
            $product = ProductQuery::create()->filterByRef($ref, Criteria::LIKE)->findOne(); // Get the product with the ref
            $productI18ns = ProductI18nQuery::create()->filterByProduct($product)->find(); // Get the product translation with the product
            $productSaleElements = ProductSaleElementsQuery::create()->filterByProduct($product)->find(); // Get the product sale elements

            $data['Product'] = $product->toArray(); // Jsonify the product
            $data['ProductSaleElements'] = $productSaleElements->toArray(); // Jsonify the product sale elements

            foreach ($productI18ns as $i18n){
                $data['ProductI18ns'][$i18n->getLocale()] = $i18n->toArray();
            }

            $index = 0;
            foreach ($productSaleElements as $pse) {
                $productPrice = ProductPriceQuery::create()->filterByProductSaleElements($pse)->findOne();

                $data['ProductSaleElements'][$index]['Prices']['Regular'] = $productPrice->getPrice();
                $data['ProductSaleElements'][$index]['Prices']['Promo'] = $productPrice->getPromoPrice();

                $attributeCombinations = AttributeCombinationQuery::create()->filterByProductSaleElements($pse)->find();

                foreach ($attributeCombinations as $ac) {
                    $attributeI18n = AttributeI18nQuery::create()->filterByAttribute($ac->getAttribute())->findOne();
                    $attributeAVI18n = AttributeAvI18nQuery::create()->filterByAttributeAv($ac->getAttributeAv())->findOne();

                    $data['ProductSaleElements'][$index]['Attributes'][] = $attributeAVI18n->getTitle();
                }

                $index++;
            }

        } catch(PropelException $e){
            return new JsonResponse(['error' => "PROPEL ERROR, PLEASE VERIFY METHOD 'getByRefAction' in ProductController !"]);
        }

        return new JsonResponse($data);
    }
}