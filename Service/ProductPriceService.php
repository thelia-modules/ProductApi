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

use Propel\Runtime\Collection\Collection;
use Thelia\Domain\Taxation\TaxEngine\Calculator;
use Thelia\Model\Country;
use Thelia\Model\Product;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\TaxRule;

/**
 * Prices of every sale element in one query, taxed for the requested country when the tax rule of the
 * product covers it.
 */
final readonly class ProductPriceService
{
    /**
     * @param Collection<ProductSaleElements> $saleElements
     *
     * @return array<int, array{price: float, original_price: float|null, promo: int|null}|array{}>
     */
    public function forSaleElements(Collection $saleElements, Product $product, ?TaxRule $taxRule, Country $country): array
    {
        $saleElementIds = [];
        foreach ($saleElements as $saleElement) {
            $saleElementIds[] = $saleElement->getId();
        }

        if ([] === $saleElementIds) {
            return [];
        }

        $calculator = $this->taxCalculator($product, $taxRule, $country);

        $lastPriceOfSaleElement = [];
        foreach (ProductPriceQuery::create()->filterByProductSaleElementsId($saleElementIds)->orderByCurrencyId()->find() as $productPrice) {
            $lastPriceOfSaleElement[$productPrice->getProductSaleElementsId()] = $productPrice;
        }

        $prices = [];
        foreach ($saleElements as $saleElement) {
            $productPrice = $lastPriceOfSaleElement[$saleElement->getId()] ?? null;

            if (null === $productPrice) {
                $prices[$saleElement->getId()] = [];

                continue;
            }

            $price = (float) $productPrice->getPrice();
            $promoPrice = (float) $productPrice->getPromoPrice();

            if (null !== $calculator) {
                $price = (float) $calculator->getTaxedPrice($price);
                $promoPrice = (float) $calculator->getTaxedPrice($promoPrice);
            }

            $promo = (bool) $saleElement->getPromo();

            $prices[$saleElement->getId()] = [
                'price' => $promo ? $promoPrice : $price,
                'original_price' => $promo ? $price : null,
                'promo' => $saleElement->getPromo(),
            ];
        }

        return $prices;
    }

    /**
     * The calculator answers the untaxed price for a country the tax rule does not cover.
     */
    private function taxCalculator(Product $product, ?TaxRule $taxRule, Country $country): ?Calculator
    {
        return null === $taxRule ? null : (new Calculator())->loadTaxRule($taxRule, $country, $product);
    }
}
