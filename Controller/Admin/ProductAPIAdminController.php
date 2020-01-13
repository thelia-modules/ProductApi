<?php


namespace ProductAPI\Controller\Admin;


use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Controller\Api\BaseApiController;
use Thelia\Model\Base\AttributeAvI18nQuery;
use Thelia\Model\Base\AttributeCombinationQuery;
use Thelia\Model\Base\AttributeI18nQuery;
use Thelia\Model\Base\ProductImageQuery;
use Thelia\Model\Base\ProductPriceQuery;
use Thelia\Model\Base\ProductSaleElementsQuery;
use Thelia\Model\ProductI18nQuery;
use Thelia\Model\ProductQuery;

class ProductAPIAdminController extends BaseAdminController
{
    public function searchAction(){
        $searched_ref = "%" . $this->getRequest()->query->get('q') . "%";

        $product = ProductQuery::create()->filterByRef($searched_ref, Criteria::LIKE)->findOne();

        if(null === $product)
            return $this->jsonResponse(json_encode(null));

        $productI18n = ProductI18nQuery::create()->filterByProduct($product)->findOne();
        $productImage = ProductImageQuery::create()->filterByProduct($product)->where('position', 1)->findOne();

        $result['product']['title'] = $productI18n->getTitle();
        $result['product']['description'] = $productI18n->getDescription();

        if(null !== $productImage)
            $result['product']['image'] = $productImage->getFile();
        else
            $result['product']['image'] = null;

        $productSaleElements = ProductSaleElementsQuery::create()->filterByProduct($product)->find();

        foreach ($productSaleElements as $pse) {
            $attributeCombinations = AttributeCombinationQuery::create()->filterByProductSaleElements($pse)->find();

            foreach ($attributeCombinations as $attributeCombination) {
                $attributeI18n = AttributeI18nQuery::create()->filterByAttribute($attributeCombination->getAttribute())->findOne();
                $attributeAVI18n = AttributeAvI18nQuery::create()->filterByAttributeAv($attributeCombination->getAttributeAv())->findOne();

                $result['product']['declinaisons'][$pse->getId()]['title'] = $attributeI18n->getTitle();
                $result['product']['declinaisons'][$pse->getId()]['attribute'] = $attributeAVI18n->getTitle();
            }

            $productPrice = ProductPriceQuery::create()->filterByProductSaleElements($pse)->findOne();

            $result['product']['declinaisons'][$pse->getId()]['price'] = $productPrice->getPrice();
            $result['product']['declinaisons'][$pse->getId()]['promo_price'] = $productPrice->getPromoPrice();
        }

        return $this->jsonResponse(json_encode($result));
    }
}