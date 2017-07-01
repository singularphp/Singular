<?php

namespace Singular\Provider;

use Silex\Api\BootableProviderInterface;
use Silex\Application;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Silex\Api\ControllerProviderInterface;

/**
 * Classe PackServiceProvider, implementa a estrutura básica de um pacote.
 *
 * @author Otávio Fernandes <otavio@netonsolucoes.com.br>
 */
abstract class PackServiceProvider implements ServiceProviderInterface, BootableProviderInterface, ControllerProviderInterface
{
    /**
     * @var string
     */
    protected $pack = '';

    /**
     * @param 'Container $app
     */
    abstract public function register(Container $app);

    /**
     * @param Application $app
     */
    abstract function boot(Application $app);

    /**
     * @param Application $app
     */
    abstract public function connect(Application $app);

    /**
     * Retorna o shortname do pacote.
     *
     * @return string
     */
    public function getPackName()
    {
        return $this->pack;
    }

    /**
     * Retorna o namespace do pacote.
     *
     * @return string
     */
    public function getNameSpace()
    {
        $reflection = new \ReflectionClass(get_class($this));

        return $reflection->getNamespaceName();
    }

    /**
     * Retorna o diretório da classe do pacote.
     * 
     * @return string
     */
    public function getDirectory()
    {
        $reflection = new \ReflectionClass(get_class($this));

        return dirname($reflection->getFileName());
    }
}
