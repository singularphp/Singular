<?php

namespace Singular;

use Singular\Provider\PackServiceProvider;
use Silex\Application as SilexApplication;
use Pimple\ServiceProviderInterface;
use Singular\Provider\SingularServiceProvider;
use Symfony\Component\Finder\Finder;
use Silex\Provider\ServiceControllerServiceProvider;
use Singular\Response\JsonResponse;

/**
 * Classe principal do Framework.
 *
 * @author Otávio Fernandes <otavio@netonsolucoes.com.br>
 */
class Application extends SilexApplication
{
    /**
     * Instantiate a new Application.
     *
     * Objects and parameters can be passed as argument to the constructor.
     *
     * @param array $values The parameters or objects.
     */
    public function __construct(array $values = array())
    {
        parent::__construct($values);

        $app = $this;
        $app['monitor.start_time'] = microtime(true);

        $app['web_dir'] = function () use ($app) {
            if (!isset($app['base_dir'])) {
                throw new \Exception('O diretorio raiz da aplicacao "base_dir" nao foi definido!');
            } elseif (!is_dir($app['base_dir'])) {
                throw new \Exception('O diretorio raiz da aplicacao "base_dir" nao foi encontrado!');
            }

            return $app['base_dir'].'/web';
        };

        $app->register(new ServiceControllerServiceProvider());
        $app->register(new SingularServiceProvider());
    }

    /**
     * Efetua o carregamento automático dos scripts php dentro do diretório app.
     */
    private function autoload()
    {
        $app = $this;
        $finder = new Finder();

        $app['app_dir'] = $app['base_dir'].'/app';

        if (!is_dir($app['app_dir'])) {
            throw Exception::directoryNotFound('O diretório '.$app['app_dir'].' nao foi encontrado');
        }

        foreach ($finder->in($app['base_dir'].'/app')->files()->name('*.php') as $file) {
            include_once $file->getRealpath();
        }
    }

    /**
     * Gets a parameter or an object.
     *
     * @param string $id The unique identifier for the parameter or object
     *
     * @return mixed The value of the parameter or an object
     *
     * @throws InvalidArgumentException if the identifier is not defined
     */
    public function offsetGet($id)
    {
        if (!isset($this[$id])) {
            $this['singular.service_locator']->locate($id);
        }

        return parent::offsetGet($id);
    }

    /**
     * Convert some data into a JSON response.
     *
     * @param mixed $data    The response data
     * @param int   $status  The response status code
     * @param array $headers An array of response headers
     *
     * @return JsonResponse
     */
    public function json($data = array(), $status = 200, array $headers = array())
    {
        return new JsonResponse($data, $status, $headers);
    }

    /**
     * {@inheritdoc}
     */
    public function register(ServiceProviderInterface $provider, array $values = array())
    {
        if ($provider instanceof PackServiceProvider) {
            $this['singular.packs'][$provider->getPackName()] = $provider;
        }

        parent::register($provider, $values);

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * Carrega as configurações e inclui os scripts da aplicação automaticamente.
     */
    public function boot()
    {
        $this['singular.config_loader']->loadConfigs();
        $this->autoload();

        if (!$this->booted) {
            $packs = $this['singular.packs']->keys();
            foreach ($packs as $packName) {
                $pack = $this['singular.packs'][$packName];
                $pack->boot($this);
                $pack->connect($this);
            }

            parent::boot();
        }
    }
}
