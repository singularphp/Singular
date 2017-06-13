<?php

namespace Singular;

use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RequestContext;

class ControllerLocator
{
    /**
     * @var Resolver
     */
    protected $resolver;

    /**
     * @var array
     */
    protected $packs;

    /**
     * @param RouteCollection $routes
     * @param RequestContext  $context
     * @param Register        $register
     * @param array           $packs
     */
    public function __construct(Resolver $resolver, $packs, $map)
    {
        $this->resolver = $resolver;
        $this->packs = $packs;
        $this->map = $map;
    }

    /**
     * Tenta localizar o controlador vinculado à chamada.
     *
     * @param $pathinfo
     */
    public function locateController($pathinfo)
    {
        $this->loadAnnotationRoute($pathinfo);
    }

    /**
     * Tenta carregar uma rota anotada em um controlador de acordo com o pathinfo.
     *
     * @param $pathinfo
     */
    protected function loadAnnotationRoute($pathinfo)
    {
        @list($empty, $pack, $controller, $method) = explode('/', $pathinfo);

        if (!isset($this->packs[$pack])) {
            return;
        }

        if (isset($this->map[$controller])) {
            $controller = $this->map[$controller];
        }

        $controller = $this->underscoreToCamelCase($controller, true);

        $fullClassName = $this->packs[$pack]->getNameSpace().'\\Controller\\'.$controller;

        if (class_exists($fullClassName)) {
            $this->resolver->resolveController($fullClassName, $this->packs[$pack]);
        }
    }

    /**
     * Convert strings with underscores into CamelCase.
     *
     * @param $string
     * @param bool $firstCharCaps
     *
     * @return mixed
     */
    private function underscoreToCamelCase($string, $firstCharCaps = false)
    {
        if ($firstCharCaps == true) {
            $string[0] = strtoupper($string[0]);
        }

        $func = create_function('$c', 'return strtoupper($c[1]);');

        return preg_replace_callback('/_([a-z])/', $func, $string);
    }
}
