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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Thelia\Model\ModuleConfigQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * Runs on a disposable database (DATABASE_NAME ending in `_test`), never on the shop's.
 * The requests go through the kernel itself: symfony/browser-kit is not required by the module.
 */
abstract class ModuleTestCase extends IntegrationTestCase
{
    private mixed $errorHandler = null;
    private mixed $exceptionHandler = null;

    protected function setUp(): void
    {
        $databaseName = $_SERVER['DATABASE_NAME'] ?? getenv('DATABASE_NAME');
        if (!\is_string($databaseName) || !str_ends_with($databaseName, '_test')) {
            self::fail(\sprintf('Refusing to run on the database "%s": use a *_test database.', (string) $databaseName));
        }

        $this->errorHandler = $this->currentErrorHandler();
        $this->exceptionHandler = $this->currentExceptionHandler();

        parent::setUp();
        ModuleConfigQuery::resetConfigCache();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Booting the kernel in debug mode installs error and exception handlers PHPUnit reports as leaked.
        $this->restoreHandlers();
        ModuleConfigQuery::resetConfigCache();
        \Thelia\Core\HttpFoundation\Request::$isAdminEnv = false;
    }

    private function restoreHandlers(): void
    {
        for ($attempt = 0; $attempt < 10 && $this->currentErrorHandler() !== $this->errorHandler; ++$attempt) {
            restore_error_handler();
        }

        for ($attempt = 0; $attempt < 10 && $this->currentExceptionHandler() !== $this->exceptionHandler; ++$attempt) {
            restore_exception_handler();
        }
    }

    private function currentErrorHandler(): mixed
    {
        $handler = set_error_handler(static fn (): bool => false);
        restore_error_handler();

        return $handler;
    }

    private function currentExceptionHandler(): mixed
    {
        $handler = set_exception_handler(static fn () => null);
        restore_exception_handler();

        return $handler;
    }

    /**
     * IntegrationTestCase pushes a synthetic request: it is taken off the stack while the
     * kernel handles this one, so that this request is the main request the controller reads.
     */
    protected function handleAsMainRequest(Request $request): Response
    {
        $requestStack = $this->requestStack();
        $pushedRequests = [];
        while (null !== $pushedRequest = $requestStack->pop()) {
            $pushedRequests[] = $pushedRequest;
        }

        try {
            return self::$kernel->handle($request);
        } finally {
            while (null !== $requestStack->pop()) {
            }
            foreach (array_reverse($pushedRequests) as $pushedRequest) {
                $requestStack->push($pushedRequest);
            }
        }
    }

    protected function requestStack(): RequestStack
    {
        $requestStack = static::getContainer()->get('request_stack');
        self::assertInstanceOf(RequestStack::class, $requestStack);

        return $requestStack;
    }

    protected function csrfToken(Request $request, string $tokenId): string
    {
        $requestStack = $this->requestStack();
        $tokenManager = static::getContainer()->get('security.csrf.token_manager');
        self::assertInstanceOf(CsrfTokenManagerInterface::class, $tokenManager);

        $requestStack->push($request);
        try {
            return $tokenManager->getToken($tokenId)->getValue();
        } finally {
            $requestStack->pop();
        }
    }
}
