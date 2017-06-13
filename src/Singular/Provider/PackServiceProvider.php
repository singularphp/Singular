<?php

namespace Singular\Provider;

use Silex\Application;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Silex\Api\ControllerProviderInterface;

/**
 * Classe PackServiceProvider, implementa a estrutura básica de um pacote.
 *
 * @author Otávio Fernandes <otavio@netonsolucoes.com.br>
 */
class PackServiceProvider implements ServiceProviderInterface, ControllerProviderInterface
{
    /**
     * @var string
     */
    protected $pack = '';

    /**
     * @param Application $app
     */
    public function register(Container $app)
    {
    }

    /**
     * @param Application $app
     */
    public function boot(Application $app)
    {
    }

    /**
     * @param Application $app
     */
    public function connect(Application $app)
    {
    }

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
}
