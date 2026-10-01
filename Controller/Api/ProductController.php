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

namespace ProductAPI\Controller\Api;

use ProductAPI\Exception\RejectedRequestException;
use ProductAPI\Service\ProductService;
use ProductAPI\Service\RequestThrottle;
use ProductAPI\Service\SignatureVerifier;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\Routing\Attribute\Route;

/**
 * `GET /api/product?ref=<ref>|id=<id>[&country=<ISO3>][&lang=<locale>]&hash=<sha1(values . key)>`.
 *
 * Only `ref` and `id` select a product; any other parameter is ignored (it still takes part in the signature).
 * Errors carry a fixed message: the details go to the log, never to the caller.
 */
#[AsController]
#[Route('/api/product', name: 'product_api_')]
final readonly class ProductController
{
    private const CACHE_LIFETIME = 60;

    public function __construct(
        private ProductService $productService,
        private SignatureVerifier $signatureVerifier,
        private RequestThrottle $requestThrottle,
        private LoggerInterface $logger,
    ) {
    }

    #[Route('', name: 'get_method', methods: ['GET'])]
    public function getMethodAction(Request $request): JsonResponse
    {
        $query = $request->query->all();

        if ([] === $query) {
            return new JsonResponse(['message' => 'Thelia Product API is working !']);
        }

        $clientAddress = $request->getClientIp() ?? 'unknown';
        $retryAfter = $this->requestThrottle->retryAfter($clientAddress);
        if ($retryAfter > 0) {
            return new JsonResponse('Too many requests.', Response::HTTP_TOO_MANY_REQUESTS, ['Retry-After' => (string) $retryAfter]);
        }

        if (!$this->signatureVerifier->isConfigured()) {
            $this->logger->error('ProductAPI: no API key is configured, the API does not answer.');

            return new JsonResponse('Service unavailable.', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        if (!$this->signatureVerifier->isValid($query)) {
            $this->requestThrottle->recordFailure($clientAddress);
            $this->logger->warning('ProductAPI: request refused, invalid signature (client {address}).', ['address' => $request->getClientIp()]);

            return new JsonResponse('You are not authorized to see this.', Response::HTTP_FORBIDDEN);
        }

        $reference = $query['ref'] ?? null;
        $id = $query['id'] ?? null;

        if ((null !== $reference && !\is_string($reference)) || (null !== $id && !(\is_string($id) && ctype_digit($id)))) {
            return new JsonResponse('No product with this parameters.', Response::HTTP_BAD_REQUEST);
        }

        try {
            $payload = $this->productService->getProduct(
                $reference,
                null === $id ? null : (int) $id,
                \is_string($query['country'] ?? null) ? $query['country'] : 'FRA',
                \is_string($query['lang'] ?? null) ? $query['lang'] : 'fr_FR',
            );
        } catch (RejectedRequestException $exception) {
            return new JsonResponse($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $exception) {
            $this->logger->error('ProductAPI: {message}', ['message' => $exception->getMessage(), 'exception' => $exception]);

            return new JsonResponse('An error occurred.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $response = (new JsonResponse($payload))->setPublic()->setMaxAge(self::CACHE_LIFETIME);
        // The session listener would otherwise turn any response into a private one, whatever it says.
        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');

        return $response;
    }
}
