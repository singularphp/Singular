<?php

namespace Singular;

use Singular\Annotation\Controller;
use Singular\Annotation\Service;
use Singular\Provider\PackServiceProvider;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\AnnotationReader;

/**
 * Classe Resolver.
 *
 * Resolve e define os serviços e controllers no container da aplicação
 *
 * @author Otávio Fernandes <otavio@netonsolucoes.com.br>
 */
class Resolver
{
    /**
     * Resolver constructor.
     * 
     * @param ControllerResolver $controlerResolver
     * @param $serviceResolver
     */
    public function __construct($controlerResolver, $serviceResolver)
    {
        AnnotationRegistry::registerAutoloadNamespace("Singular\Annotation", __DIR__.'/../');

        $this->controllerResolver = $controlerResolver;
        $this->serviceResolver = $serviceResolver;
    }

    /**
     * Resolve a definição de um serviço.
     *
     * @param string              $serviceClassName
     * @param PackServiceProvider $pack
     */
    public function resolveService($serviceClassName, $pack)
    {
        $reader = new AnnotationReader();

        if (class_exists($serviceClassName)) {
            $reflectionClass = new \ReflectionClass($serviceClassName);
            $classAnnotations = $reader->getClassAnnotations($reflectionClass);

            foreach ($classAnnotations as $annotation) {
                if ($annotation instanceof Service) {
                    $this->serviceResolver->resolve($annotation, $reflectionClass, $pack);
                }
            }
        }
    }

    /**
     * Resolve a definição do serviço de um controlador.
     *
     * @param string              $controllerClassName
     * @param PackServiceProvider $pack
     */
    public function resolveController($controllerClassName, $pack)
    {
        $reader = new AnnotationReader();

        $reflectionClass = new \ReflectionClass($controllerClassName);
        $classAnnotations = $reader->getClassAnnotations($reflectionClass);

        foreach ($classAnnotations as $annotation) {
            if ($annotation instanceof Controller) {
                $this->controllerResolver->resolve($annotation, $reflectionClass, $pack);
            }
        }
    }

    /**
     * Extrái as anotações da classe e registra controladores ou serviços.
     *
     * @param string              $class
     * @param PackServiceProvider $pack
     */
    public function readAnnotations($class, $pack)
    {
        $reader = new AnnotationReader();

        $reflectionClass = new \ReflectionClass($class);
        $classAnnotations = $reader->getClassAnnotations($reflectionClass);

        foreach ($classAnnotations as $annotation) {
            if ($annotation instanceof Controller) {
                $this->ctrlRegister->register($annotation, $reflectionClass, $pack);
            }
        }
    }
}
