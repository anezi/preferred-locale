<?php declare(strict_types=1);

namespace Anezi\PreferredLocale\Tests\EventSubscriber;

use Anezi\PreferredLocale\EventSubscriber\LocaleListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class LocalListenerTest extends TestCase
{
    /**
     * @expectedException \UnexpectedValueException
     */
    public function testEmptyLocalesShouldThrowException(): void
    {
        $this->createListener('', 'fr');
    }

    /**
     * @expectedException \UnexpectedValueException
     */
    public function testDefaultLocaleShouldBeInLocales(): void
    {
        $this->createListener('fr', 'fr_BE');
    }

    public function testEmptyDefaultLocaleShouldUseTheFirstLocaleInLocales(): void
    {
        $listener = $this->createListener('fr|fr_BE', '', false);
        $event = $this->createEvent('*', HttpKernelInterface::MASTER_REQUEST, '/');

        $this->assertPath('/fr', $listener, $event);
    }

    public function testIgnoreDefaultLocale(): void
    {
        $listener = $this->createListener('fr|fr_BE', 'fr_BE', true);
        $event = $this->createEvent('*', HttpKernelInterface::MASTER_REQUEST, '/');

        $this->assertNullResponse($listener, $event);
    }

    public function testDisableIgnoreDefaultLocale(): void
    {
        $listener = $this->createListener('fr|fr_BE', 'fr_BE', false);
        $event = $this->createEvent('*', HttpKernelInterface::MASTER_REQUEST, '/');

        $this->assertPath('/fr-be', $listener, $event);
    }

    public function testIgnoreSubRequests(): void
    {
        $listener = $this->createListener('fr|fr_BE', 'fr_BE', false);
        $event = $this->createEvent('*', HttpKernelInterface::SUB_REQUEST, '/');

        $this->assertNullResponse($listener, $event);
    }

    public function testIgnoreNonRootPaths(): void
    {
        $listener = $this->createListener('fr|fr_BE', 'fr_BE', false);
        $event = $this->createEvent('*', HttpKernelInterface::MASTER_REQUEST, '/page');

        $this->assertNullResponse($listener, $event);
    }

    public function testIgnoreInternalUrls(): void
    {
        $listener = $this->createListener('fr|fr_BE', 'fr_BE', false);
        $event = $this->createEvent('*', HttpKernelInterface::MASTER_REQUEST, '/', 'https://example.com/page');

        $this->assertNullResponse($listener, $event);
    }

    public function testDoNotIgnoreExternalUrls(): void
    {
        $listener = $this->createListener('fr|fr_BE', 'fr_BE', false);
        $event = $this->createEvent('*', HttpKernelInterface::MASTER_REQUEST, '/', 'https://another-example.com/page');

        $this->assertPath('/fr-be', $listener, $event);
    }

    public function testSession(): void
    {
        $listener = $this->createListener('fr|fr_BE', 'fr_BE', false);
        $event = $this->createEvent('*', HttpKernelInterface::MASTER_REQUEST, '/', 'https://another-example.com/page');

        $event->getRequest()->setSession($this->mockSession(null));

        $this->assertPath('/fr-be', $listener, $event);

        $event->getRequest()->setSession($this->mockSession('fr'));

        $this->assertPath('/fr', $listener, $event);
    }

    private function createListener(string $locales, ?string $defaultLocale, ?bool $ignoreDefaultLocale = null): LocaleListener
    {
        $routes = new RouteCollection();

        $routes->add('homepage', new Route('/{_locale}'));

        $requestContext = new RequestContext();

        $urlMatcher = new UrlMatcher($routes, $requestContext);

        return new LocaleListener(new UrlGenerator($routes, $requestContext), $urlMatcher, 'homepage', $locales, $defaultLocale, $ignoreDefaultLocale);
    }

    private function createEvent(string $acceptlanguage, int $requestType, string $requestUri, ?string $referer = null): GetResponseEvent
    {
        $request = new Request(
            [],
            [],
            [],
            [],
            [],
            [
                'HTTP_ACCEPT_LANGUAGE' => $acceptlanguage,
                'REQUEST_URI' => $requestUri,
                'HTTP_HOST' => 'example.com',
                'HTTPS' => 'on',
                'HTTP_REFERER' => $referer,
            ]
        );

        return new GetResponseEvent(new HttpKernel(new EventDispatcher(), new ControllerResolver()), $request, $requestType);
    }

    protected function assertNullResponse(LocaleListener $listener, GetResponseEvent $event): void
    {
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    protected function assertPath(string $expected, LocaleListener $listener, GetResponseEvent $event): void
    {
        $listener->onKernelRequest($event);

        /** @var RedirectResponse $response */
        $response = $event->getResponse();

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame($expected, $response->getTargetUrl());
    }

    private function mockSession(?string $expected): SessionInterface
    {
        $mock = $this->getMockBuilder(SessionInterface::class)
            ->getMock();

        $mock
            ->method('get')
            ->willReturn($expected);

        return $mock;
    }
}
