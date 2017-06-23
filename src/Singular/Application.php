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

        $app['singular.not_found_directory_error'] = 'O diretório  %s definido para o parâmetro %s da aplicação não foi encontrado.';
        $app['singular.not_defined_directory_error'] = 'O parâmetro %s da aplicação não foi definido.';

        $app['singular.check_directory'] = $app->protect(function ($directory) use ($app) {
            $parametro = 'singular.directory.'.$directory;

            if (!isset($app[$parametro])) {
                throw new \Exception(sprintf($app['singular.not_defined_directory_error'], $parametro));
            } elseif (!is_dir($app[$parametro])) {
                throw new \Exception(sprintf($app['singular.not_defined_directory_error'], $parametro, $app[$parametro]));
            }
        });

        $app['singular.directory.web'] = function () use ($app) {
            $app['singular.check_directory']('root');

            return $app['singular.directory.root'].'/web';
        };

        $app['singular.directory.app'] = function() use ($app) {
            $app['singular.check_directory']('root');

            return $app['singular.directory.root'].'/app';
        };

        $app['singular.directory.config'] = function() use ($app) {
            $app['singular.check_directory']('app');

            return $app['singular.directory.app'].'/config';
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

        foreach ($finder->in($app['singular.directory.app'])->files()->name('*.php') as $file) {
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
