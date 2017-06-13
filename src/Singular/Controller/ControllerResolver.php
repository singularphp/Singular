<?php

namespace Singular\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;

class ControllerResolver implements ControllerResolverInterface
{
    protected $controllerResolver;
    protected $callbackResolver;

    /**
     * Constructor.
     *
     * @param ControllerResolverInterface $controllerResolver A ControllerResolverInterface instance to delegate to
     * @param CallbackResolver            $callbackResolver   A service resolver instance
     */
    public function __construct(ControllerResolverInterface $controllerResolver, CallbackResolver $callbackResolver)
    {
        $this->controllerResolver = $controllerResolver;
        $this->callbackResolver = $callbackResolver;
    }

    /**
     * {@inheritdoc}
     */
    public function getController(Request $request)
    {
        $controller = $request->attributes->get('_controller', null);

        if (!$this->callbackResolver->isValid($controller)) {
            return $this->controllerResolver->getController($request);
        }

        return $this->callbackResolver->convertCallback($controller);
    }

    /**
     * {@inheritdoc}
     */
    public function getArguments(Request $request, $controller)
    {
        return $this->controllerResolver->getArguments($request, $controller);
    }
}
