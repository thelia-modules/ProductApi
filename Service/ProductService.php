<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ProductAPI\Service;

use ProductAPI\Exception\RejectedRequestException;
use Thelia\Model\AttributeAvI18nQuery;
use Thelia\Model\AttributeCombinationQuery;
use Thelia\Model\AttributeI18nQuery;
use Thelia\Model\CountryQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductI18nQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * The payload of `/api/product`, in the 2.x format. Data comes from a fixed number of queries whatever the
 * number of sale elements, attributes and images.
 */
final readonly class ProductService
{
    public function __construct(
        private ProductPriceService $priceService,
        private ProductImageService $imageService,
        private LanguageDomain $languageDomain,
    ) {
    }

    /**
     * @return array{Product: array<string, mixed>}
     *
     * @throws RejectedRequestException
     */
    public function getProduct(?string $reference, ?int $id, string $countryCode, string $locale): array
    {
        $product = $this->findProduct($reference, $id);

        if (null === $this->languageDomain->findLanguage($locale)) {
            throw new RejectedRequestException('Language code not found.');
        }

        $country = CountryQuery::create()->findOneByIsoalpha3($countryCode);

        if (null === $country) {
            throw new RejectedRequestException(\sprintf('Country code %s not found.', $countryCode));
        }

        $data = $product->toArray();
        $data['Images'] = $this->imageService->forProduct($product, $locale);
        $data['URL'] = $this->languageDomain->rebase($product->getUrl($locale), $locale);

        $saleElements = ProductSaleElementsQuery::create()->filterByProductId($product->getId())->orderById()->find();
        $prices = $this->priceService->forSaleElements($saleElements, $product, $product->getTaxRule(), $country);
        $attributes = $this->attributesOfSaleElements($saleElements->getPrimaryKeys(false));

        foreach ($saleElements as $saleElement) {
            $saleElementId = $saleElement->getId();

            $data['ProductSaleElements'][$saleElementId] = $saleElement->toArray();
            $data['ProductSaleElements'][$saleElementId]['Prices'] = $prices[$saleElementId] ?? [];

            if (isset($attributes[$saleElementId])) {
                $data['ProductSaleElements'][$saleElementId]['i18ns'] = $attributes[$saleElementId];
            }
        }

        foreach (ProductI18nQuery::create()->filterById($product->getId())->orderByLocale()->find() as $productI18n) {
            $data['ProductI18ns'][$productI18n->getLocale()] = $productI18n->toArray();
        }

        return ['Product' => $data];
    }

    private function findProduct(?string $reference, ?int $id): Product
    {
        if (null === $reference && null === $id) {
            throw new RejectedRequestException('No product with this parameters.');
        }

        $query = ProductQuery::create();

        if (null !== $reference) {
            $query->filterByRef($reference);
        }

        if (null !== $id) {
            $query->filterById($id);
        }

        return $query->findOne() ?? throw new RejectedRequestException('No product with this parameters.');
    }

    /**
     * Attribute and value titles of each sale element, three queries in all.
     *
     * The position of a title in `Attributes` is the position of its translation among the translations of
     * the attribute, not of the attribute among the attributes of the sale element: the 2.x numbering,
     * which the blog is written against.
     *
     * @param list<int> $saleElementIds
     *
     * @return array<int, array<string, array{Attributes: array<int, array<string, string|null>>}>>
     */
    private function attributesOfSaleElements(array $saleElementIds): array
    {
        if ([] === $saleElementIds) {
            return [];
        }

        $combinations = AttributeCombinationQuery::create()
            ->filterByProductSaleElementsId($saleElementIds)
            ->orderByProductSaleElementsId()
            ->orderByAttributeId()
            ->find();

        if (0 === \count($combinations)) {
            return [];
        }

        $attributeIds = [];
        $attributeValueIds = [];
        foreach ($combinations as $combination) {
            $attributeIds[] = $combination->getAttributeId();
            $attributeValueIds[] = $combination->getAttributeAvId();
        }

        $attributeTitles = [];
        foreach (AttributeI18nQuery::create()->filterById($attributeIds)->orderById()->orderByLocale()->find() as $attributeI18n) {
            $attributeTitles[$attributeI18n->getId()][] = $attributeI18n;
        }

        $valueTitles = [];
        foreach (AttributeAvI18nQuery::create()->filterById($attributeValueIds)->orderById()->orderByLocale()->find() as $valueI18n) {
            $valueTitles[$valueI18n->getId()][] = $valueI18n;
        }

        $data = [];
        foreach ($combinations as $combination) {
            $saleElementId = $combination->getProductSaleElementsId();

            foreach ($attributeTitles[$combination->getAttributeId()] ?? [] as $position => $attributeI18n) {
                $data[$saleElementId][$attributeI18n->getLocale()]['Attributes'][$position]['Title'] = $attributeI18n->getTitle();
            }

            foreach ($valueTitles[$combination->getAttributeAvId()] ?? [] as $position => $valueI18n) {
                $data[$saleElementId][$valueI18n->getLocale()]['Attributes'][$position]['Value'] = $valueI18n->getTitle();
            }
        }

        return $data;
    }
}
