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

use ProductAPI\ProductAPI;
use Psr\Log\LoggerInterface;
use Thelia\Action\Image;
use Thelia\Domain\Media\DTO\ImageProcessDTO;
use Thelia\Domain\Media\MediaFacade;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Model\Product;
use Thelia\Model\ProductImageI18n;
use Thelia\Model\ProductImageI18nQuery;
use Thelia\Model\ProductImageQuery;

/**
 * Images of a product, resized by the core image processor. Two queries whatever the number of images
 * (the translations, which hold the file of each language, are loaded once and handed to the models).
 * The URLs are absolute and on the domain of the requested language; the file path on the server is never given.
 */
final readonly class ProductImageService
{
    public function __construct(
        private MediaFacade $mediaFacade,
        private LanguageDomain $languageDomain,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forProduct(Product $product, string $locale): array
    {
        $images = ProductImageQuery::create()->filterByProductId($product->getId())->orderById()->find();

        if (0 === \count($images)) {
            return [];
        }

        $translationsByImage = [];
        foreach (ProductImageI18nQuery::create()->filterById($images->getPrimaryKeys(false))->orderByLocale()->find() as $translation) {
            $translationsByImage[$translation->getId()][] = $translation;
        }

        $defaultLocale = Lang::getDefaultLanguage()->getLocale();
        $data = [];
        $index = 0;

        foreach ($images as $image) {
            // setProductImageI18ns() would query the translations to schedule the missing ones for deletion.
            $image->initProductImageI18ns();
            foreach ($this->translationsOf($image->getId(), $translationsByImage[$image->getId()] ?? [], [$locale, $defaultLocale]) as $translation) {
                $image->addProductImageI18n($translation);
            }
            $image->getTranslation($locale);
            $image->getTranslation($defaultLocale);

            try {
                $file = $image->setLocale($locale)->getFile();

                $event = $this->mediaFacade->processImage(new ImageProcessDTO(
                    sourceFilepath: $this->sourcePath($file),
                    cacheSubdirectory: 'product',
                    width: ProductAPI::getImageSize(ProductAPI::CONFIG_IMAGE_WIDTH),
                    height: ProductAPI::getImageSize(ProductAPI::CONFIG_IMAGE_HEIGHT),
                    resizeMode: (string) Image::EXACT_RATIO_WITH_BORDERS,
                ));

                $data[$index] = [
                    'visible' => $image->getVisible(),
                    'position' => $image->getPosition(),
                    'image_url' => $this->absoluteUrl($event->getFileUrl(), $locale),
                    'originale_image_url' => $this->absoluteUrl($event->getOriginalFileUrl(), $locale),
                    'i18ns' => $this->translationTexts($translationsByImage[$image->getId()] ?? []),
                ];
            } catch (\Throwable $exception) {
                // An image that cannot be processed leaves a hole in the list, as in 2.x, and never fails the call.
                $this->logger->warning('ProductAPI: image {id} skipped: {message}', ['id' => $image->getId(), 'message' => $exception->getMessage()]);
            }

            ++$index;
        }

        return $data;
    }

    /**
     * The translations already read, plus an empty one for each locale the image has none in: asked for a locale
     * it does not hold, a model queries the database (and creates the missing translation).
     *
     * @param list<ProductImageI18n> $translations
     * @param list<string>           $locales
     *
     * @return list<ProductImageI18n>
     */
    private function translationsOf(int $imageId, array $translations, array $locales): array
    {
        $held = [];
        foreach ($translations as $translation) {
            $held[$translation->getLocale()] = true;
        }

        foreach ($locales as $locale) {
            if (!isset($held[$locale])) {
                $translations[] = (new ProductImageI18n())->setId($imageId)->setLocale($locale);
                $held[$locale] = true;
            }
        }

        return $translations;
    }

    /**
     * The nested `i18ns` key is the 2.x format: the blog reads `i18ns.i18ns.<locale>`.
     *
     * @param list<ProductImageI18n> $translations
     *
     * @return array<string, array<string, array<string, string|null>>>
     */
    private function translationTexts(array $translations): array
    {
        $texts = [];
        foreach ($translations as $translation) {
            $texts['i18ns'][$translation->getLocale()] = [
                'title' => $translation->getTitle(),
                'chapo' => $translation->getChapo(),
                'description' => $translation->getDescription(),
                'postscriptum' => $translation->getPostscriptum(),
            ];
        }

        return $texts;
    }

    private function sourcePath(string $file): string
    {
        $libraryPath = ConfigQuery::read('images_library_path');
        $base = null === $libraryPath ? THELIA_LOCAL_DIR.'media'.DS.'images' : THELIA_ROOT.$libraryPath;

        return \sprintf('%s/product/%s', $base, $file);
    }

    private function absoluteUrl(?string $url, string $locale): ?string
    {
        return null === $url ? null : $this->languageDomain->rebase($url, $locale);
    }
}
