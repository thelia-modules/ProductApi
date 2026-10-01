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

namespace ProductAPI\Tests;

use ProductAPI\ProductAPI;
use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Propel;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Model\AttributeI18n;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\ModuleConfigQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductImage;
use Thelia\Model\TaxRuleCountry;

/**
 * The expected payloads are the 2.0.3 ones: same routes, parameters, keys and values.
 */
final class ProductApiTest extends ModuleTestCase
{
    private const KEY = 'test-only-key-0123456789abcdef';

    private Product $product;
    private Country $taxedCountry;
    private string $clientAddress;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clientAddress = $this->randomAddress();
        ProductAPI::setConfigValue(ProductAPI::CONFIG_API_KEY, self::KEY);

        $factory = $this->createFixtureFactory();
        $this->taxedCountry = $this->country('FRA');
        $taxRule = $factory->taxRule(['isDefault' => false]);
        $taxRuleCountry = new TaxRuleCountry();
        $taxRuleCountry
            ->setTaxRuleId($taxRule->getId())
            ->setCountryId($this->taxedCountry->getId())
            ->setTaxId($factory->tax()->getId())
            ->setPosition(1)
            ->save();

        $this->product = $factory->product($factory->category(), $taxRule, $factory->currency(), [
            'ref' => 'PAPI-1',
            'title' => 'Helmet',
            'basePrice' => 100.0,
        ]);
    }

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testTheHealthMessageIsGivenWithoutParameters(): void
    {
        $response = $this->call([], sign: false);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['message' => 'Thelia Product API is working !'], $this->decode($response));
    }

    public function testAProductIsServedByReferenceInThe2xFormat(): void
    {
        $response = $this->call(['ref' => 'PAPI-1', 'lang' => 'en_US', 'country' => 'FRA']);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $payload = $this->decode($response);
        self::assertSame(['Product'], array_keys($payload));

        $product = $payload['Product'];
        foreach (['Id', 'TaxRuleId', 'Ref', 'Visible', 'Position', 'TemplateId', 'BrandId', 'Virtual', 'CreatedAt', 'UpdatedAt', 'Version', 'VersionCreatedAt', 'VersionCreatedBy', 'Images', 'URL', 'ProductSaleElements', 'ProductI18ns'] as $key) {
            self::assertArrayHasKey($key, $product);
        }
        // Keys Thelia 3 adds to the 2.x payload (columns of the models).
        self::assertArrayHasKey('GuestCheckoutForbidden', $product);
        self::assertSame('PAPI-1', $product['Ref']);
        self::assertSame($this->product->getId(), $product['Id']);
        self::assertSame([], $product['Images']);
        self::assertStringStartsWith('http', $product['URL']);

        self::assertEqualsCanonicalizing(['Id', 'Locale', 'Title', 'Chapo', 'Description', 'Postscriptum', 'MetaTitle', 'MetaDescription', 'MetaKeywords'], array_keys($product['ProductI18ns']['en_US']));
        self::assertSame('Helmet', $product['ProductI18ns']['en_US']['Title']);

        self::assertCount(1, $product['ProductSaleElements']);
        $saleElement = array_values($product['ProductSaleElements'])[0];
        foreach (['Id', 'ProductId', 'Ref', 'Quantity', 'Promo', 'Newness', 'Weight', 'IsDefault', 'EanCode', 'Prices'] as $key) {
            self::assertArrayHasKey($key, $saleElement);
        }
        // JSON has no float zero fraction: 120.0 is read back as 120.
        self::assertEquals(['price' => 120, 'original_price' => null, 'promo' => 0], $saleElement['Prices']);
        self::assertArrayHasKey('Position', $saleElement);
        self::assertIsBool($saleElement['Visible']);
    }

    public function testAProductIsServedByIdAndByBothParameters(): void
    {
        $byId = $this->decode($this->call(['id' => (string) $this->product->getId()]));
        $both = $this->decode($this->call(['ref' => 'PAPI-1', 'id' => (string) $this->product->getId()]));
        $mismatch = $this->call(['ref' => 'PAPI-1', 'id' => (string) ($this->product->getId() + 1000)]);

        self::assertSame('PAPI-1', $byId['Product']['Ref']);
        self::assertSame($byId, $both);
        self::assertSame(400, $mismatch->getStatusCode());
    }

    public function testAnOfflineProductIsServedWithItsVisibleFlag(): void
    {
        $this->product->setVisible(0)->save();

        $payload = $this->decode($this->call(['ref' => 'PAPI-1']));

        self::assertSame(0, $payload['Product']['Visible']);
    }

    public function testAnUnknownParameterIsIgnoredAndOnlyRefAndIdSelectTheProduct(): void
    {
        $reference = $this->decode($this->call(['ref' => 'PAPI-1']));

        $withUnknown = $this->call(['ref' => 'PAPI-1', 'foo' => 'bar', 'position' => '99999', 'visible' => '0', 'tax_rule_id' => '99999']);

        self::assertSame(200, $withUnknown->getStatusCode(), (string) $withUnknown->getContent());
        self::assertSame($reference, $this->decode($withUnknown));
    }

    public function testASignatureIsRequiredAndConstantTimeComparedWithTheKey(): void
    {
        $unsigned = $this->call(['ref' => 'PAPI-1'], sign: false);
        $wrong = $this->call(['ref' => 'PAPI-1', 'hash' => sha1('PAPI-1wrong')], sign: false);
        $uppercase = $this->call(['ref' => 'PAPI-1', 'hash' => strtoupper(sha1('PAPI-1'.self::KEY))], sign: false);
        $withoutKeyInHash = $this->call(['ref' => 'PAPI-1', 'hash' => sha1('PAPI-1')], sign: false);

        foreach ([$unsigned, $wrong, $uppercase, $withoutKeyInHash] as $response) {
            self::assertSame(403, $response->getStatusCode());
            self::assertSame('You are not authorized to see this.', $this->decode($response));
        }
    }

    public function testTheSignatureCoversTheValuesInTheOrderReceived(): void
    {
        $hash = sha1('PAPI-1en_USFRA'.self::KEY);
        $response = $this->raw('ref=PAPI-1&lang=en_US&country=FRA&hash='.$hash);
        $reordered = $this->raw('lang=en_US&ref=PAPI-1&country=FRA&hash='.$hash);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(403, $reordered->getStatusCode());
    }

    public function testAnArrayParameterIsRefused(): void
    {
        $response = $this->raw('ref[]=PAPI-1&hash='.sha1('Array'.self::KEY));

        self::assertSame(403, $response->getStatusCode());
    }

    public function testWithoutAConfiguredKeyTheApiDoesNotAnswerWhateverTheSignature(): void
    {
        ProductAPI::setConfigValue(ProductAPI::CONFIG_API_KEY, '');
        $knownDefaultKey = 'ExRtVQjUCCBApuN4s4fPEQ6i5yggYvm2';

        $response = $this->call(['ref' => 'PAPI-1', 'hash' => sha1('PAPI-1'.$knownDefaultKey)], sign: false);
        $emptyKey = $this->call(['ref' => 'PAPI-1', 'hash' => sha1('PAPI-1')], sign: false);

        self::assertSame(503, $response->getStatusCode());
        self::assertSame(503, $emptyKey->getStatusCode());
        self::assertStringNotContainsString('PAPI-1', (string) $response->getContent());
    }

    public function testWithoutAnyKeyRecordedTheApiDoesNotFallBackOnAKeyOfTheCode(): void
    {
        ModuleConfigQuery::create()->filterByName(ProductAPI::CONFIG_API_KEY)->delete();
        ModuleConfigQuery::resetConfigCache();
        $knownDefaultKey = 'ExRtVQjUCCBApuN4s4fPEQ6i5yggYvm2';

        $response = $this->call(['ref' => 'PAPI-1', 'hash' => sha1('PAPI-1'.$knownDefaultKey)], sign: false);

        self::assertSame(503, $response->getStatusCode());
    }

    public function testErrorsAreGenericAndCarryNoInternalDetail(): void
    {
        $unknownProduct = $this->call(['ref' => 'NOPE']);
        $unknownCountry = $this->call(['ref' => 'PAPI-1', 'country' => 'ZZZ']);
        $unknownLanguage = $this->call(['ref' => 'PAPI-1', 'lang' => 'xx_XX']);
        $noSelector = $this->call(['lang' => 'fr_FR']);
        $injection = $this->call(['id' => '1 OR 1=1']);

        foreach ([$unknownProduct, $unknownCountry, $unknownLanguage, $noSelector, $injection] as $response) {
            self::assertSame(400, $response->getStatusCode());
            self::assertIsString($this->decode($response));
            self::assertDoesNotMatchRegularExpression('#PROPEL|UNKNOW|SQLSTATE|Exception|/var/|\.php#i', (string) $response->getContent());
        }
        self::assertSame('No product with this parameters.', $this->decode($unknownProduct));
        self::assertSame('Country code ZZZ not found.', $this->decode($unknownCountry));
    }

    public function testThePriceIsTaxedOnlyForACountryTheTaxRuleCovers(): void
    {
        $this->country('DEU');

        $taxed = $this->decode($this->call(['ref' => 'PAPI-1', 'country' => 'FRA']));
        $untaxed = $this->decode($this->call(['ref' => 'PAPI-1', 'country' => 'DEU']));

        self::assertEquals(120, array_values($taxed['Product']['ProductSaleElements'])[0]['Prices']['price']);
        self::assertEquals(100, array_values($untaxed['Product']['ProductSaleElements'])[0]['Prices']['price']);
    }

    public function testAPriceIsTaxedAtTheRateOfTheRequestedCountry(): void
    {
        $factory = $this->createFixtureFactory();
        $germany = $this->country('DEU');
        (new TaxRuleCountry())
            ->setTaxRuleId($this->product->getTaxRuleId())
            ->setCountryId($germany->getId())
            ->setTaxId($factory->tax(['requirements' => ['percent' => '19'], 'title' => 'German VAT'])->getId())
            ->setPosition(1)
            ->save();

        $france = $this->decode($this->call(['ref' => 'PAPI-1', 'country' => 'FRA']));
        $germany = $this->decode($this->call(['ref' => 'PAPI-1', 'country' => 'DEU']));

        self::assertEquals(120, array_values($france['Product']['ProductSaleElements'])[0]['Prices']['price']);
        self::assertEquals(119, array_values($germany['Product']['ProductSaleElements'])[0]['Prices']['price']);
    }

    public function testAPromotionGivesThePromoPriceAndTheOriginalPrice(): void
    {
        $saleElement = $this->product->getProductSaleElementss()->getFirst();
        $saleElement->setPromo(1)->save();
        $price = $saleElement->getProductPrices()->getFirst();
        $price->setPromoPrice('80.000000')->save();

        $payload = $this->decode($this->call(['ref' => 'PAPI-1', 'country' => 'FRA']));

        self::assertEquals(
            ['price' => 96, 'original_price' => 120, 'promo' => 1],
            array_values($payload['Product']['ProductSaleElements'])[0]['Prices'],
        );
    }

    public function testAttributesKeepThe2xNumbering(): void
    {
        $factory = $this->createFixtureFactory();
        $attribute = $factory->attribute(['title' => 'Size']);
        $french = new AttributeI18n();
        $french->setId($attribute->getId())->setLocale('fr_FR')->setTitle('Taille')->save();
        $value = $factory->attributeAv($attribute, ['title' => 'Large']);
        $saleElement = $this->product->getProductSaleElementss()->getFirst();
        $factory->attributeCombination($saleElement, $value);

        $payload = $this->decode($this->call(['ref' => 'PAPI-1']));

        // In 2.x the index counts the translations of the attribute, not the attributes of the sale element.
        self::assertSame(
            [
                'en_US' => ['Attributes' => [0 => ['Title' => 'Size', 'Value' => 'Large']]],
                'fr_FR' => ['Attributes' => [1 => ['Title' => 'Taille']]],
            ],
            array_values($payload['Product']['ProductSaleElements'])[0]['i18ns'],
        );
    }

    public function testImagesAreAbsoluteWithoutTheServerPathAndInAFixedNumberOfQueries(): void
    {
        $this->useFixtureMedia();
        $this->addImage(1);
        $this->addImage(2);

        $this->call(['ref' => 'PAPI-1', 'lang' => 'en_US']);
        $queriesWithTwoImages = $this->countQueries(fn (): Response => $this->call(['ref' => 'PAPI-1', 'lang' => 'en_US']));
        $this->addImage(3);
        $this->addImage(4);
        $response = null;
        $queriesWithFourImages = $this->countQueries(function () use (&$response): Response {
            return $response = $this->call(['ref' => 'PAPI-1', 'lang' => 'en_US']);
        });

        self::assertSame($queriesWithTwoImages, $queriesWithFourImages, 'The number of queries grows with the number of images.');

        $images = $this->decode($response)['Product']['Images'];
        self::assertCount(4, $images);
        self::assertSame(['visible', 'position', 'image_url', 'originale_image_url', 'i18ns'], array_keys($images[0]));
        self::assertMatchesRegularExpression('#^https?://[^/]+/.+papi.*\.png$#', $images[0]['image_url']);
        self::assertMatchesRegularExpression('#^https?://[^/]+/.+papi.*\.png$#', $images[0]['originale_image_url']);
        self::assertSame(['i18ns' => ['en_US' => ['title' => 'Image 1', 'chapo' => null, 'description' => null, 'postscriptum' => null]]], $images[0]['i18ns']);
        self::assertStringNotContainsString('image_path', (string) $response->getContent());
        self::assertStringNotContainsString(THELIA_ROOT, (string) $response->getContent());
    }

    public function testAnImageWithoutFileLeavesAHoleAndDoesNotFailTheCall(): void
    {
        $this->useFixtureMedia();
        $broken = new ProductImage();
        $broken->setProductId($this->product->getId())->setVisible(1)->setPosition(1)->setLocale('en_US')->setFile('')->save();
        $this->addImage(2);

        $response = $this->call(['ref' => 'PAPI-1', 'lang' => 'en_US']);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([1], array_keys($this->decode($response)['Product']['Images']));
    }

    public function testUrlsMoveToTheDomainOfTheRequestedLanguageWhenEachLanguageHasOne(): void
    {
        $this->useFixtureMedia();
        $this->addImage(1);
        $german = $this->language('de_DE');
        $german->setUrl('https://motorrad.example.test')->save();
        ConfigQuery::write('one_domain_foreach_lang', '1');
        ConfigQuery::resetCache();

        $product = $this->decode($this->call(['ref' => 'PAPI-1', 'lang' => 'de_DE']))['Product'];

        self::assertStringStartsWith('https://motorrad.example.test/', $product['URL']);
        self::assertStringStartsWith('https://motorrad.example.test/', $product['Images'][0]['image_url']);
        self::assertStringStartsWith('https://motorrad.example.test/', $product['Images'][0]['originale_image_url']);

        ConfigQuery::write('one_domain_foreach_lang', '0');
        ConfigQuery::resetCache();
    }

    public function testTheResponseIsCachedBrieflyOnlyWhenItIsAProduct(): void
    {
        $ok = $this->call(['ref' => 'PAPI-1']);
        $missing = $this->call(['ref' => 'NOPE']);

        self::assertStringContainsString('max-age=60', (string) $ok->headers->get('Cache-Control'));
        self::assertStringContainsString('public', (string) $ok->headers->get('Cache-Control'));
        // The core never starts a session on /api/ routes (SessionManager::sessionIsStartable()); the mock
        // session storage of the test kernel is the only cookie source left.
        self::assertSame([], array_values(array_diff(array_map(static fn ($cookie): string => $cookie->getName(), $ok->headers->getCookies()), ['MOCKSESSID'])));
        self::assertStringNotContainsString('max-age=60', (string) $missing->headers->get('Cache-Control'));
    }

    public function testAClientThatFailsTheSignatureTooOftenIsBlockedAndASignedClientNever(): void
    {
        ProductAPI::setConfigValue(ProductAPI::CONFIG_FAILED_SIGNATURE_LIMIT, '2');

        $statuses = [];
        for ($attempt = 0; $attempt < 3; ++$attempt) {
            $statuses[] = $this->call(['ref' => 'PAPI-1', 'hash' => 'wrong'], sign: false)->getStatusCode();
        }
        $blocked = $this->call(['ref' => 'PAPI-1']);
        $otherClient = $this->randomAddress();
        $signedStatuses = [];
        for ($attempt = 0; $attempt < 5; ++$attempt) {
            $signedStatuses[] = $this->call(['ref' => 'PAPI-1'], address: $otherClient)->getStatusCode();
        }

        self::assertSame([403, 403, 429], $statuses);
        self::assertSame(429, $blocked->getStatusCode());
        self::assertGreaterThan(0, (int) $blocked->headers->get('Retry-After'));
        self::assertSame([200, 200, 200, 200, 200], $signedStatuses);
    }

    public function testTheKeyAndTheSignatureNeverReachTheLog(): void
    {
        $logFile = $this->logFile();
        $before = is_file($logFile) ? (string) file_get_contents($logFile) : '';

        $this->call(['ref' => 'PAPI-1', 'hash' => 'wrong-signature-value'], sign: false);
        $this->call(['ref' => 'NOPE']);

        $added = substr((string) file_get_contents($logFile), \strlen($before));
        self::assertStringNotContainsString(self::KEY, $added);
        self::assertStringNotContainsString('wrong-signature-value', $added);
    }

    /**
     * @param array<string, string> $query
     */
    private function call(array $query, bool $sign = true, ?string $address = null): Response
    {
        if ($sign && [] !== $query) {
            $query['hash'] = sha1(implode($query).self::KEY);
        }

        return $this->raw(http_build_query($query), $address);
    }

    private function raw(string $queryString, ?string $address = null): Response
    {
        $request = Request::create('http://localhost/api/product?'.$queryString, 'GET', server: ['REMOTE_ADDR' => $address ?? $this->clientAddress]);

        return $this->handleAsMainRequest($request);
    }

    private function randomAddress(): string
    {
        return \sprintf('10.%d.%d.%d', random_int(1, 254), random_int(1, 254), random_int(1, 254));
    }

    private function decode(Response $response): mixed
    {
        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    private function country(string $isoAlpha3): Country
    {
        return CountryQuery::create()->findOneByIsoalpha3($isoAlpha3)
            ?? $this->createFixtureFactory()->country(['isoalpha3' => $isoAlpha3, 'isoalpha2' => substr($isoAlpha3, 0, 2), 'isocode' => (string) random_int(100, 999)]);
    }

    private function language(string $locale): Lang
    {
        $language = LangQuery::create()->findOneByLocale($locale);
        self::assertInstanceOf(Lang::class, $language, \sprintf('The test database has no %s language.', $locale));

        return $language;
    }

    private function useFixtureMedia(): void
    {
        ConfigQuery::write('images_library_path', Path::makeRelative(__DIR__.'/Fixtures/media/images', rtrim(THELIA_ROOT, '/')));
        ConfigQuery::resetCache();
    }

    private function addImage(int $position): void
    {
        $image = new ProductImage();
        $image->setProductId($this->product->getId())->setVisible(1)->setPosition($position);
        $image->setLocale('en_US')->setFile('papi.png')->setTitle('Image '.$position);
        $image->save();
    }

    /**
     * @param callable(): Response $call
     */
    private function countQueries(callable $call): int
    {
        $connection = Propel::getConnection('TheliaMain');
        self::assertInstanceOf(ConnectionWrapper::class, $connection);
        $connection->useDebug(true);
        $before = $connection->getQueryCount();

        try {
            self::assertSame(200, $call()->getStatusCode());

            return $connection->getQueryCount() - $before;
        } finally {
            $connection->useDebug(false);
        }
    }

    private function logFile(): string
    {
        return Path::join(static::getContainer()->getParameter('kernel.logs_dir'), 'test.log');
    }
}
