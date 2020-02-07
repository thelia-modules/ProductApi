<?php


namespace ProductAPI\Service;


use Comment\Model\CommentQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Thelia\Action\Image;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\JsonResponse;
use Thelia\Model\Base\AttributeAvI18nQuery;
use Thelia\Model\Base\AttributeCombinationQuery;
use Thelia\Model\Base\AttributeI18nQuery;
use Thelia\Model\Base\CountryQuery;
use Thelia\Model\Base\ProductI18nQuery;
use Thelia\Model\Base\ProductPriceQuery;
use Thelia\Model\Country;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\Base\ProductSaleElementsQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ProductI18n;
use Thelia\Model\ProductQuery;
use Thelia\Model\TaxRule;
use Thelia\Model\TaxRule as ChildTaxRule;
use Thelia\TaxEngine\Calculator;

class ProductService
{
    protected $eventDispatcher;

    public function __construct(EventDispatcher $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param array $parameters
     * @param string $lang
     * @return mixed
     */
    public function get(array $parameters, $lang = "FRA")
    {
        $data = [];

        try{
            foreach ($parameters as $parameter => $value){
                $function = "filterBy".$parameter;
                $productQuery = ProductQuery::create()->$function($value);
            }

            $product = $productQuery->findOne();

            if(null === $product){
                return $data['message'] = "Aucun produit trouvé avec les informations donnnées.";
            }
            
            $productI18ns = $product->getProductI18ns(); // Get product's translations
            $productSaleElements = $product->getProductSaleElementss(); // Get product's pses
            $productTaxRule = $product->getTaxRule(); // Get product's tax rule

            $country = CountryQuery::create()->filterByIsoalpha3($lang)->findOne(); // Get country from 3 alpha iso code

            if(null === $country){
                return $data['message'] = "La pays recherché ($lang), n'existe pas.";
            }

            $taxed = $this->checkIfCountryIsTaxed($productTaxRule, $country);

            $data['Product'] = $product->toArray(); // Jsonify the product
            $data['Product']['Images'] = $this->getImageData($product->getProductImages(), 'product');
            $data['Product']['URL'] = $product->getUrl('fr_FR');

            $indexPSE = 0;
            foreach ($productSaleElements as $productSaleElement){
                $priceData =  $this->getPricesData($productSaleElement, $product, $productTaxRule, $taxed, $country); // Get Prices for each currency

                $data['Product']['ProductSaleElements'][$indexPSE] = $productSaleElement->toArray(); // Jsonify the product sale element
                $data['Product']['ProductSaleElements'][$indexPSE]['Prices'] = $priceData; // Add prices to pse

                $attributeCombinations = $productSaleElement->getAttributeCombinations();

                foreach($attributeCombinations as $attributeCombination){

                    $attributeTitleI18ns = $attributeCombination->getAttribute()->getAttributeI18ns();
                    $attributeValueI18ns = $attributeCombination->getAttributeAv()->getAttributeAvI18ns();

                    $index = 0;
                    foreach ($attributeTitleI18ns as $attributeI18n){
                        $data['Product']['ProductSaleElements'][$indexPSE]['i18ns'][$attributeI18n->getLocale()]['Attributes'][$index]['Title'] = $attributeI18n->getTitle();
                        $index++;
                    }

                    $index = 0;
                    foreach ($attributeValueI18ns as $attributeValueI18n){
                        $data['Product']['ProductSaleElements'][$indexPSE]['i18ns'][$attributeValueI18n->getLocale()]['Attributes'][$index]['Value'] = $attributeValueI18n->getTitle();
                        $index++;
                    }
                }
                $indexPSE++;
            }

            foreach ($productI18ns as $productI18n){
                $data['Product']['ProductI18ns'][$productI18n->getLocale()] = $productI18n->toArray(); // Jsonify the product translation
            }

        } catch(PropelException $e){

        }

        return $data;
    }

    public function checkIfCountryIsTaxed($productTaxRule, $country)
    {
        /** @var ChildTaxRule $productTaxRule */
        $productTaxeRuleCountries = $productTaxRule->getTaxRuleCountries(); // Get product taxed countries to check if product is taxed

        $taxedCountriesID = [];

        foreach ($productTaxeRuleCountries as $taxedCountry){
            $taxedCountriesID[] = $taxedCountry->getCountry()->getId(); // Creating taxed countries id array to check if country wanted has taxes
        }

        // Check if country wanted is in taxed countries list
        if(in_array($country->getId(), $taxedCountriesID)){
            return true;
        } else {
            return false;
        }
    }

    protected function getPricesData(ProductSaleElements $productSaleElement, Product $product, TaxRule $taxRule, $taxed, Country $country)
    {
        $productPrices = $productSaleElement->getProductPrices();
        $prices = [];

        foreach ($productPrices as $productPrice) {
            if($taxed) {
                $taxCalculator = $this->getTaxCalculator($taxRule, $product, $country);
                $price = $taxCalculator->getTaxedPrice($productPrice->getPrice());
                $promoPrice = $taxCalculator->getTaxedPrice($productPrice->getPromoPrice());
            } else {
                $price = $productPrice->getPrice();
                $promoPrice = $productPrice->getPromoPrice();
            }

            $prices = [
                'price' => $productSaleElement->getPromo() ? doubleval($promoPrice) : doubleval($price),
                'original_price' => $productSaleElement->getPromo() ? doubleval($price) : null,
                'promo' => $productSaleElement->getPromo(),
            ];
        }

        return $prices;
    }

    protected function getLangData($i18ns, $withUrl = false, $model = null,  $viewName = '')
    {
        $data = [];
        foreach ($i18ns as $i18n) {
            /**
             * @var ProductI18n $i18n
             */
            $data['i18ns'][$i18n->getLocale()] = [
                'title' => $i18n->getTitle(),
                'chapo' => $i18n->getChapo(),
                'description' => $i18n->getDescription(),
                'postscriptum' => $i18n->getPostscriptum()
            ];

            if ($withUrl) {
                $url = $model->getRewrittenUrl($i18n->getLocale());

                $data['urls'][$i18n->getLocale()] = $url ? $url :
                    sprintf(
                        "/?view=".$viewName."&lang=%s&".$viewName."_id=%d",
                        $i18n->getLocale(),
                        $model->getId()
                    );
            }
        }

        return $data;
    }

    protected function getImageData($images, $type)
    {
        $data = [];

        $index = 0;
        foreach ($images as $image) {
            if (null !== $image) {
                try {
                    $imageEvent = self::createImageEvent($image->getFile(), $type);
                    $this->eventDispatcher->dispatch(TheliaEvents::IMAGE_PROCESS, $imageEvent);

                    $i18nMethod = "get".ucfirst($type).'ImageI18ns';
                    $imageI18ns = $image->$i18nMethod();
                    $langData = $this->getLangData($imageI18ns, false);

                    $data[$index] = [
                        'visible' => $image->getVisible(),
                        'position' => $image->getPosition(),
                        'image_url' => $imageEvent->getFileUrl(),
                        'originale_image_url' => $imageEvent->getOriginalFileUrl(),
                        'image_path' => $imageEvent->getCacheFilepath(),
                        'i18ns' => $langData
                    ];

                } catch (\Exception $e) {
                    $error = $e->getMessage();
                }
                $index++;
            }
        }

        return $data;
    }

    protected function getTaxCalculator($taxRule, $product, $taxedCountry)
    {
        $taxCalculator = new Calculator();

        $country = null;

        //Fix for thelia <= 2.4.0
        if (isset($taxedCountries[0])) {
            $country = CountryQuery::create()->findOneById($taxedCountry->getId());
        }

        if (null === $country) {
            $country = Country ::getDefaultCountry();
        }

        $taxCalculator->loadTaxRule($taxRule, $country, $product);

        return $taxCalculator;
    }

    protected function createImageEvent($imageFile, $type)
    {
        $imageEvent = new ImageEvent();
        $baseSourceFilePath = ConfigQuery::read('images_library_path');
        if ($baseSourceFilePath === null) {
            $baseSourceFilePath = THELIA_LOCAL_DIR . 'media' . DS . 'images';
        } else {
            $baseSourceFilePath = THELIA_ROOT . $baseSourceFilePath;
        }
        // Put source image file path
        $sourceFilePath = sprintf(
            '%s/%s/%s',
            $baseSourceFilePath,
            $type,
            $imageFile
        );
        $imageEvent->setSourceFilepath($sourceFilePath);
        $imageEvent->setCacheSubdirectory($type);
        $imageEvent->setWidth(200)
            ->setHeight(200)
            ->setResizeMode(Image::EXACT_RATIO_WITH_BORDERS);
        return $imageEvent;
    }
}