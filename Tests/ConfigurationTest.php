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

use ProductAPI\Form\ConfigurationForm;
use ProductAPI\ProductAPI;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\Admin;
use Thelia\Model\ModuleConfigQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Model\ProfileModule;

final class ConfigurationTest extends ModuleTestCase
{
    public function testTheKeyAndTheImageSizesAreSaved(): void
    {
        $session = $this->adminSession();
        $response = $this->handleAsMainRequest($this->post($session, 'a-configured-key-0123456789', '640', '480'));

        self::assertSame(302, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame([], $session->getFlashBag()->peek('danger'));
        ModuleConfigQuery::resetConfigCache();
        self::assertSame('a-configured-key-0123456789', ProductAPI::getApiKey());
        self::assertSame(640, ProductAPI::getImageSize(ProductAPI::CONFIG_IMAGE_WIDTH));
        self::assertSame(480, ProductAPI::getImageSize(ProductAPI::CONFIG_IMAGE_HEIGHT));
    }

    public function testAKeyTooShortIsRefused(): void
    {
        ProductAPI::setConfigValue(ProductAPI::CONFIG_API_KEY, 'the-previous-key-0123456789');
        $session = $this->adminSession();

        $response = $this->handleAsMainRequest($this->post($session, 'short', '500', '500'));

        self::assertSame(302, $response->getStatusCode());
        self::assertNotSame([], $session->getFlashBag()->peek('danger'));
        ModuleConfigQuery::resetConfigCache();
        self::assertSame('the-previous-key-0123456789', ProductAPI::getApiKey());
    }

    public function testAnAdministratorWithoutTheUpdateAccessCannotSave(): void
    {
        ProductAPI::setConfigValue(ProductAPI::CONFIG_API_KEY, 'the-previous-key-0123456789');
        $session = $this->adminSession($this->moduleAdmin([AccessManager::VIEW]));

        $response = $this->handleAsMainRequest($this->post($session, 'a-configured-key-0123456789', '640', '480'));

        self::assertNotSame(200, $response->getStatusCode(), 'Refusal expected.');
        self::assertStringNotContainsString('admin.module.configure', (string) $response->headers->get('Location'));
        ModuleConfigQuery::resetConfigCache();
        self::assertSame('the-previous-key-0123456789', ProductAPI::getApiKey());
    }

    public function testAnAdministratorWithTheUpdateAccessCanSave(): void
    {
        $session = $this->adminSession($this->moduleAdmin([AccessManager::VIEW, AccessManager::UPDATE]));

        $response = $this->handleAsMainRequest($this->post($session, 'a-configured-key-0123456789', '640', '480'));

        ModuleConfigQuery::resetConfigCache();
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('a-configured-key-0123456789', ProductAPI::getApiKey());
    }

    public function testASaveWithoutTheCsrfTokenIsRefused(): void
    {
        ProductAPI::setConfigValue(ProductAPI::CONFIG_API_KEY, 'the-previous-key-0123456789');
        $session = $this->adminSession();
        $request = Request::create('/admin/module/ProductAPI/configuration', 'POST');
        $request->setSession($session);
        $request->request->set(ConfigurationForm::getName(), [
            'api_key' => 'a-configured-key-0123456789',
            'image_width' => '640',
            'image_height' => '480',
        ]);

        $this->handleAsMainRequest($request);

        self::assertNotSame([], $session->getFlashBag()->peek('danger'));
        ModuleConfigQuery::resetConfigCache();
        self::assertSame('the-previous-key-0123456789', ProductAPI::getApiKey());
    }

    public function testTheConfigurationPageShowsTheEndpointAndTheKey(): void
    {
        ProductAPI::setConfigValue(ProductAPI::CONFIG_API_KEY, 'the-shown-key-0123456789');
        $request = Request::create('/admin/module/ProductAPI');
        $request->setSession($this->adminSession());

        $response = $this->handleAsMainRequest($request);
        $content = (string) $response->getContent();

        self::assertSame(200, $response->getStatusCode(), $content);
        self::assertStringContainsString('/api/product', $content);
        self::assertStringContainsString('the-shown-key-0123456789', $content);
    }

    private function post(Session $session, string $key, string $width, string $height): Request
    {
        $request = Request::create('/admin/module/ProductAPI/configuration', 'POST');
        $request->setSession($session);
        $request->request->set(ConfigurationForm::getName(), [
            'api_key' => $key,
            'image_width' => $width,
            'image_height' => $height,
            '_token' => $this->csrfToken($request, ConfigurationForm::getName()),
        ]);

        return $request;
    }

    /**
     * An administrator restricted to the given accesses on the module: the module resource and the module itself.
     *
     * @param list<string> $accesses
     */
    private function moduleAdmin(array $accesses): Admin
    {
        $admin = $this->createFixtureFactory()->restrictedAdmin([AdminResources::MODULE => $accesses]);
        $module = ModuleQuery::create()->findOneByCode('ProductAPI');
        self::assertNotNull($module, 'The module is not registered in the test database.');

        $accessManager = new AccessManager(0);
        $accessManager->build($accesses);
        (new ProfileModule())
            ->setProfileId($admin->getProfileId())
            ->setModuleId($module->getId())
            ->setAccess($accessManager->getAccessValue())
            ->save();

        return $admin;
    }

    private function adminSession(?Admin $admin = null): Session
    {
        $factory = $this->createFixtureFactory();
        $language = $factory->lang();

        $session = new Session(new MockArraySessionStorage());
        $session->setAdminUser($admin ?? $factory->admin());
        $session->set('thelia.current.admin_lang', $language);
        $session->setAdminEditionLang($language);

        return $session;
    }
}
