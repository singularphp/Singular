<?php

namespace Singular\Resolver;

use Pimple\Container;
use Singular\Annotation\Controller;
use Singular\Annotation\Route;
use Singular\Annotation\Before;
use Singular\Annotation\After;
use Singular\Annotation\Convert;
use Singular\Annotation\Assert;
use Singular\Annotation\Value;
use Singular\Provider\PackServiceProvider;
use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Singular\Application;

/**
 * Class ControllerResolver.
 *
 * @author Otávio Fernandes <otavio@netonsolucoes.com.br>
 */
class ControllerResolver
{
    /**
     * ControllerResolver constructor.
     *
     * @param Application $app
     */
    public function __construct(Container $app)
    {
        $this->app = $app;
        $this->reader = new AnnotationReader();
    }

    /**
     * Resolve a definição do controlador no container de dependências.
     *
     * @param Controller          $annotation
     * @param \ReflectionClass    $reflectionClass
     * @param PackServiceProvider $pack
     */
    public function resolve($annotation, $reflectionClass, $pack)
    {
        $app = $this->app;


        $snakeClass = implode('_',preg_split('/(?=[A-Z])/', $reflectionClass->getShortName(), -1, PREG_SPLIT_NO_EMPTY));
        $controllerKey = implode('.',[$pack->getPackName(),'controller', strtolower($snakeClass)]);
        $controller = $reflectionClass->getShortName();

        $app[$controllerKey] = function () use ($app, $reflectionClass, $pack) {
            $class = $reflectionClass->getName();

            return new $class($app, $pack);
        };

        $$controller = $app['controllers_factory'];

        $this->registerControllerFilters($$controller, $controllerKey,  $annotation->filters);
        $this->registerRoutes($$controller, $controllerKey, $reflectionClass);

        $routePattern = ($annotation->mount != '')  ? $pack->getPackName().'/'.$annotation->mount : $pack->getPackName().'/'.strtolower($snakeClass);

        $this->app['controllers']->mount($routePattern, $$controller);

    }

    /**
     * Registra rotas definidas no controlador.
     *
     * @param \Silex\ControllerCollection $controller
     * @param string                      $controllerService
     * @param \ReflectionClass            $reflectionClass
     */
    private function registerRoutes($controller, $controllerService, $reflectionClass)
    {
        $methods = $reflectionClass->getMethods();

        foreach ($methods as $reflectionMethod) {
            $route = $this->reader->getMethodAnnotation($reflectionMethod, 'Singular\Annotation\Route');

            $beforeFilters = $this->reader->getMethodAnnotation($reflectionMethod, 'Singular\Annotation\Before');
            $afterFilters = $this->reader->getMethodAnnotation($reflectionMethod, 'Singular\Annotation\After');

            if ($route) {
                $this->registerBasicRoute($route, $controller, $controllerService, $reflectionClass, $reflectionMethod, $beforeFilters, $afterFilters);
            }
        }
    }

    /**
     * Mapeia uma rota convencional em um controlador.
     *
     * @param Route                       $annotation
     * @param \Silex\ControllerCollection $controller
     * @param string                      $controllerService
     * @param \ReflectionClass            $reflectionClass
     * @param \ReflectionMethod           $reflectionMethod
     * @param array                       $beforeFilters
     * @param array                       $afterFilters
     */
    private function registerBasicRoute($annotation, $controller, $controllerService, $reflectionClass, $reflectionMethod, $beforeFilters, $afterFilters)
    {
        $app = $this->app;

        if ($annotation->method != null || !empty($annotation->methods)) {
            $routeMethods = $annotation->method == null ? $annotation->methods : array($annotation->method);

            if ($annotation->pattern) {
                $container = $app;
                $ctr = $container->match($annotation->pattern, $controllerService.':'.$reflectionMethod->getName())->method(implode('|', $routeMethods));
            } else {

                $ctr = $controller->match($this->getRoutePattern($reflectionMethod), $controllerService.':'.$reflectionMethod->getName())->method(implode('|', $routeMethods));
            }

            if ($annotation->name != null) {
                $ctr->bind($annotation->name);
            } else {
                $ctr->bind($controllerService.'.'.strtolower($reflectionMethod->getName()));
            }

            if (!empty($beforeFilters)) {
                foreach ($beforeFilters->methods as $method) {
                    $this->registerFilter($method, 'before', $ctr, $controllerService, $reflectionClass);
                }
            }

            if (!empty($afterFilters)) {
                foreach ($afterFilters->methods as $method) {
                    $this->registerFilter($method, 'after', $ctr, $controllerService, $reflectionClass);
                }
            }

            $this->registerVariableHandlers($ctr, $controllerService, $reflectionMethod);
        } else {
            throw Exception::routeMethodNotDefinedError(sprintf(
                "O metodo '%s' do controlador '%s' foi anotado como rota mas nao possui um metodo (post,get,etc) definido",
                $reflectionMethod->getName(), $reflectionClass->getName()
            ));
        }
    }

    /**
     * Recupera o padrão de casamento da rota.
     *
     * @param \ReflectionMethod $reflectionMethod
     *
     * @return string
     */
    private function getRoutePattern($reflectionMethod)
    {
        $pattern = $reflectionMethod->getName();
        $params = $reflectionMethod->getParameters();

        foreach ($params as $param) {
            if ($param->getClass()) {
                $className = $param->getClass()->getShortName();

                if ($className != 'Request' && $className != 'Application') {
                    $pattern .= '/{'.$param->getName().'}';
                }
            } else {
                $pattern .= '/{'.$param->getName().'}';
            }
        }

        return $pattern;
    }

    /**
     * Registra manipuladores para as variáveis da rota.
     *
     * @param \Silex\Controller $ctr
     * @param string            $controllerService
     * @param \ReflectionMethod $reflectionMethod
     */
    private function registerVariableHandlers($ctr, $controllerService, $reflectionMethod)
    {
        $this->registerVariableConverter($ctr, $controllerService, $reflectionMethod);
        $this->registerVariableAssert($ctr, $controllerService, $reflectionMethod);
        $this->registerVariableValue($ctr, $controllerService, $reflectionMethod);
    }

    /**
     * Registra os conversores de variáveis no controlador.
     *
     * @param \Silex\Controller $ctr
     * @param string            $controllerService
     * @param \ReflectionMethod $reflectionMethod
     */
    private function registerVariableConverter($ctr, $controllerService, $reflectionMethod)
    {
        $app = $this->app;
        $annotation = $this->reader->getMethodAnnotation($reflectionMethod, 'Singular\Annotation\Convert');

        if (!empty($annotation)) {
            foreach ($annotation->converters as $converter) {
                $callback = explode(':', $converter['fn']);

                if (count($callback) > 1) {
                    $service = $callback[0];
                    $fn = $callback[1];
                } else {
                    $service = $controllerService;
                    $fn = $converter['fn'];
                }

                $ctr->convert($converter['param'], function ($param) use ($app, $fn, $service) {
                    return $app[$service]->$fn($param);
                });
            }
        }
    }

    /**
     * Registra os requisitos de variáveis no controlador.
     *
     * @param \Silex\Controller $ctr
     * @param string            $controllerService
     * @param \ReflectionMethod $reflectionMethod
     */
    private function registerVariableAssert($ctr, $controllerService, $reflectionMethod)
    {
        $annotation = $this->reader->getMethodAnnotation($reflectionMethod, 'Singular\Annotation\Assert');

        if (!empty($annotation)) {
            foreach ($annotation->assertions as $assert) {
                $ctr->assert($assert['param'], $assert['exp']);
            }
        }
    }

    /**
     * Registra os valores default das variáveis.
     *
     * @param \Silex\Controller $ctr
     * @param string            $controllerService
     * @param \ReflectionMethod $reflectionMethod
     */
    private function registerVariableValue($ctr, $controllerService, $reflectionMethod)
    {
        $annotation = $this->reader->getMethodAnnotation($reflectionMethod, 'Singular\Annotation\Value');

        if (!empty($annotation)) {
            foreach ($annotation->defaults as $default) {
                $ctr->value($default['param'], $default['value']);
            }
        }
    }

    /**
     * Registra todos os filtros definidos na anotação de um controlador.
     *
     * @param \Silex\Controller $controller
     * @param string            $controllerService
     * @param array             $filters
     * @param \ReflectionClass  $reflectionClass
     */
    private function registerControllerFilters($controller, $controllerService, $filters)
    {
        foreach ($filters as $filter) {
            if ($filter instanceof Before) {
                foreach ($filter->methods as $method) {
                    $this->registerFilter($method, 'before', $controller, $controllerService);
                }
            }

            if ($filter instanceof After) {
                foreach ($filter->methods as $method) {
                    $this->registerFilter($method, 'after', $controller, $controllerService);
                }
            }
        }
    }

    /**
     * Registra um filtro específico em um controlador.
     *
     * @param string            $method
     * @param string            $type
     * @param \Silex\Controller $controller
     * @param string            $controllerService
     */
    private function registerFilter($method, $type, $controller, $controllerService)
    {
        $app = $this->app;

        if ($type == 'before') {
            $callback = function (Request $request) use ($controllerService, $app, $method) {
                $service = $this->locateService($controllerService, $method);

                return $app[$service['service_class']]->$service['service_method']($request);
            };
        } else {
            $callback = function (Request $request, Response $response) use ($controllerService, $app, $method) {
                $service = $this->locateService($controllerService, $method);

                return $app[$service['service_class']]->$service['service_method']($request, $response);
            };
        }

        $controller->$type($callback);
    }

    /**
     * Localiza o serviço e o método chamado para execução de callback.
     *
     * @param string $service
     * @param string $method
     *
     * @return array
     */
    private function locateService($service, $method)
    {
        $app = $this->app;

        if (strpos($method, ':') !== false) {
            $methodParts = explode(':', $method);

            $service = $methodParts[0];
            $method = $methodParts[1];
        }

        $refClass = new \ReflectionClass(get_class($app[$service]));

        if (!$refClass->hasMethod($method)) {
            throw Exception::controllerMethodNotDefinedError(sprintf("O metodo '%s' nao esta definido no controlador '%s'", $method, $refClass->getName()));
        }

        $refMethod = new \ReflectionMethod($refClass->getName(), $method);
        $annotations = $this->reader->getMethodAnnotations($refMethod);

        if (!empty($annotations)) {
            throw Exception::filterHasAnnotationError(sprintf("O metodo '%s' do controlador '%s' esta definido como um filtro mas possui anotacoes.", $method, $refClass->getName()));
        }

        return array(
            'service_class' => $service,
            'service_method' => $method,
        );
    }
}
