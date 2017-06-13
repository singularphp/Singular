<?php

namespace Singular;

use Pimple\Container;

/**
 * Class ServiceLocator.
 *
 * Responsável por localizar um serviço singular pela chave de namespace.
 */
class ServiceLocator
{
    /**
     * Pacotes registrados na aplicação.
     *
     * @var Container
     */
    private $packs;

    /**
     * Mapa de chaves de serviço.
     *
     * @var Container
     */
    private $map;

    /**
     * @var \Singular\Resolver
     */
    private $resolver;

    /**
     * ServiceLocator constructor.
     *
     * @param Container       $packs
     * @param Container       $map
     * @param ServiceRegister $register
     */
    public function __construct(Container $packs, Container $map, Resolver $resolver)
    {
        $this->packs = $packs;
        $this->map = $map;
        $this->resolver = $resolver;
    }

    /**
     * Tenta localizar um serviço pelo seu id.
     *
     * @param string $id
     */
    public function locate($id)
    {
        $parts = explode('.', $id);
        $pack = $parts[0];
        $service = end($parts);

        if (!isset($this->packs[$pack])) {
            return false;
        }

        if (isset($this->map[$service])) {
            $service = $this->map[$service];
        }

        $service = $this->keyToName($service);
        $location = $this->getPathLocation($parts);
        $serviceClassName = $this->packs[$pack]->getNameSpace().'\\'.$location.$service;

        $this->resolver->resolveService($serviceClassName, $this->packs[$pack]);
    }

    /**
     * Recupera o path de localização do serviço.
     *
     * @param array $parts
     *
     * @return string
     */
    private function getPathLocation($parts)
    {
        $path = '';

        for ($i = 1; $i < count($parts) - 1; ++$i) {
            $part = $parts[$i];
            $path .= $this->keyToName($part).'\\';
        }

        return $path;
    }

    /**
     * Transforma uma chave em um nome capitalizado.
     *
     * @param string $key
     *
     * @return string
     */
    private function keyToName($key)
    {
        $parts = explode('_', $key);
        $name = '';

        foreach ($parts as $part) {
            $name .= ucfirst($part);
        }

        return $name;
    }
}
