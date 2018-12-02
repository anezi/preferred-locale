<?php declare(strict_types=1);

namespace Anezi\PreferredLocale\EventSubscriber;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContextAwareInterface;

class LocaleListener
{
    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    /**
     * @var array
     */
    private $locales;

    /**
     * @var string
     */
    private $defaultRoute;

    /**
     * @var string
     */
    private $defaultLocale;

    /**
     * @var bool
     */
    private $ignoreDefaultLocale;

    /**
     * @var RequestContextAwareInterface
     */
    private $router;

    public function __construct(UrlGeneratorInterface $urlGenerator, RequestContextAwareInterface $router, string $defaultRoute, string $locales, ?string $defaultLocale, bool $ignoreDefaultLocale = null)
    {
        $this->urlGenerator = $urlGenerator;
        $this->router = $router;
        $this->defaultRoute = $defaultRoute;
        $this->ignoreDefaultLocale = $ignoreDefaultLocale ?? true;

        $this->locales = $this->cleanLocales($locales);

        if (empty($this->locales)) {
            throw new \UnexpectedValueException('The list of supported locales must not be empty.');
        }

        $defaultLocale = $this->cleanLocale($defaultLocale);

        if ($defaultLocale) {
            if (!\in_array($defaultLocale, $this->locales, true)) {
                throw new \UnexpectedValueException(sprintf('The default locale ("%s") must be one of "%s".', $defaultLocale, $locales));
            }

            \array_unshift($this->locales, $defaultLocale);
            $this->locales = array_unique($this->locales);

            $this->defaultLocale = $defaultLocale;

            return;
        }

        $this->defaultLocale = $this->locales[0];
    }

    public function onKernelRequest(GetResponseEvent $event): void
    {
        if (!$this->supports($event)) {
            return;
        }

        $request = $event->getRequest();
        $session = $request->getSession();

        $preferredLocale = null;

        if ($session) {
            $preferredLocale = $session->get('_locale');
        }

        if (!$preferredLocale) {
            $preferredLocale = $request->getPreferredLanguage($this->locales);

            if ($session) {
                $session->set('_locale', $preferredLocale);
            }
        }

        if ($this->ignoreDefaultLocale && $preferredLocale === $this->defaultLocale) {
            return;
        }

        $event->setResponse(
            new RedirectResponse(
                $this->urlGenerator->generate(
                    $this->defaultRoute,
                    ['_locale' => \strtolower(\str_replace('_', '-', $preferredLocale))]
                )
            )
        );
    }

    private function supports(GetResponseEvent $event): bool
    {
        $request = $event->getRequest();

        if (!$event->isMasterRequest()) {
            return false;
        }

        if ($request->getPathInfo() !== '/') {
            $this->fixLocale($event->getRequest());

            return false;
        }

        if (!$request->headers->get('referer')) {
            return true;
        }

        if (stripos($request->headers->get('referer'), $request->getSchemeAndHttpHost()) !== 0) {
            return true;
        }

        return false;
    }

    /**
     * @param string $locales
     *
     * @return array
     */
    protected function cleanLocales(string $locales): array
    {
        $cleanedLocales = \explode('|', $locales);
        $cleanedLocales = \array_map('trim', $cleanedLocales);

        $cleanedLocales = \array_filter($cleanedLocales, function (string $locale) {
            return !empty($locale);
        });

        return $cleanedLocales;
    }

    protected function cleanLocale(?string $defaultLocale): ?string
    {
        if ($defaultLocale) {
            $defaultLocale = \trim($defaultLocale);
        }

        if ($defaultLocale === '') {
            return null;
        }

        return $defaultLocale;
    }

    private function fixLocale(Request $request): void
    {
        if (strpos($request->getLocale(), '-') !== false) {
            [$p1, $p2] = explode('-', $request->getLocale());

            $locale = $p1 . '_' . strtoupper($p2);

            $request->attributes->set('_locale', $locale);
            $request->setLocale($locale);

            $routeParams = $request->attributes->get('_route_params');

            $routeParams['_locale'] = $locale;

            $request->attributes->set('_route_params', $routeParams);

            $this->router->getContext()->setParameter('_locale', $locale);
        }
    }
}
