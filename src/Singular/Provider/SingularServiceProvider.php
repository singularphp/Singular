<?php

namespace Singular\Provider;

use Silex\Api\EventListenerProviderInterface;
use Silex\Application;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Singular\ControllerLocator;
use Singular\Resolver\ControllerResolver;
use Singular\Resolver\ServiceResolver;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\EventListener\RouterListener;
use Singular\ConfigLoader;
use Singular\ServiceLocator;
use Singular\Resolver;

/**
 * Classe SingularServiceProvide.
 *
 * Define os parâmetros e serviços necessários ao framework.
 *
 * @author Otávio Fernandes <otavio@netonsolucoes.com.br>
 */
class SingularServiceProvider implements ServiceProviderInterface, EventListenerProviderInterface
{
    /**
     * Registra o provedor de serviços do singular.
     *
     * @param Container $app
     */
    public function register(Container $app)
    {
        $app['singular.packs'] = function () use ($app) {
            return new Container();
        };

        $app['singular.service_map'] = function () use ($app) {
            return new Container();
        };

        $app['singular.config_loader'] = function () use ($app) {
            return new ConfigLoader($app);
        };

        $app['singular.service_locator'] = function () use ($app) {
            return new ServiceLocator($app['singular.packs'], $app['singular.service_map'], $app['singular.resolver']);
        };

        $app['singular.service_resolver'] = function() use ($app) {
            return new ServiceResolver($app);
        };

        $app['singular.resolver'] = function () use ($app) {
            return new Resolver($app['singular.controller_resolver'], $app['singular.service_resolver']);
        };

        $app['singular.controller_locator'] = function () use ($app) {
            return new ControllerLocator($app['singular.resolver'], $app['singular.packs'], $app['singular.service_map']);
        };

        $app['singular.controller_resolver'] = function() use ($app) {
            return new ControllerResolver($app);
        };
    }

    /**
     * Registra o listener para interceptar a requisição e localizar o respectivo controlador.
     *
     * @param Container $app
     * @param EventDispatcherInterface $dispatcher
     */
    public function subscribe(Container $app, EventDispatcherInterface $dispatcher)
    {
        $dispatcher->addListener(KernelEvents::REQUEST, function (GetResponseEvent $event) use ($app) {
            $request = $event->getRequest();

            if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
                $app['singular.controller_locator']->locateController($request->getPathInfo());
                $app->flush();
            }

        },APPLICATION::EARLY_EVENT);
    }
}
