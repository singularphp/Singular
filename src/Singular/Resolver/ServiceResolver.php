<?php

namespace Singular\Resolver;

use Doctrine\Common\Annotations\AnnotationReader;
use Pimple\Container;
use Singular\Annotation\Service;
use Singular\Provider\PackServiceProvider;
use Singular\Application;

/**
 * Class ServiceRegister.
 *
 *
 * @author Otávio Fernandes <otavio@netonsolucoes.com.br>
 */
class ServiceResolver
{
    /**
     * Registra o container de dependência.
     *
     * @param Application $app
     */
    public function __construct(Container $app)
    {
        $this->app = $app;
        $this->reader = new AnnotationReader();
    }

    /**
     * Registra os serviços do pacote.
     *
     * @param Service             $annotation
     * @param \ReflectionClass    $reflectionClass
     * @param PackServiceProvider $pack
     */
    public function resolve($annotation, $reflectionClass, $pack)
    {
        $app = $this->app;

        $snakeClass = implode('_',preg_split('/(?=[A-Z])/', $reflectionClass->getShortName(), -1, PREG_SPLIT_NO_EMPTY));
        $relativeNamespace = preg_replace('/'.$pack->getNameSpace().'/', '', $reflectionClass->getName(), 1);
        list($empty, $serviceDir, $serviceClass) = explode('\\', $relativeNamespace);
        $serviceKey = implode('.',[$pack->getPackName(),strtolower($serviceDir), strtolower($snakeClass)]);

        $class = $reflectionClass->getName();
        switch ($annotation->type) {
            case 'factory':
                $app[$serviceKey] = $app->factory(function () use ($class, $app, $pack) {
                    return new $class($app, $pack);
                });
            break;
            case 'shared':
                $app[$serviceKey] = function () use ($class, $app, $pack) {
                    return new $class($app, $pack);
                };
            break;
            case 'protected':
                $app[$serviceKey] = $app->protect(function () use ($class, $app, $pack) {
                    return new $class($app, $pack);
                });
            break;
        }

        $this->registerParameters($reflectionClass, $serviceKey);
    }

    /**
     * Registra parâmetros definidos dentro dos serviços.
     *
     * @param \ReflectionClass $reflectionClass
     * @param string           $serviceKey
     */
    public function registerParameters($reflectionClass, $serviceKey)
    {
        $app = $this->app;
        $properties = $reflectionClass->getProperties();
        $class = $reflectionClass->getName();

        foreach ($properties as $property) {
            $annotation = $this->reader->getPropertyAnnotation($property, 'Singular\Annotation\Parameter');

            if (!empty($annotation)) {
                $parameterKey = $serviceKey.'.'.$property->getName();

                $app[$parameterKey] = function () use ($class, $property) {

                    $prop = $property->getName();

                    return $class::$$prop;
                };
            }
        }
    }
}
