<?php

namespace Singular\Provider;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Singular\Config\ChainConfigDriver;
use Singular\Config\PhpConfigDriver;
use Singular\Config\JsonConfigDriver;
use Singular\Config\YamlConfigDriver;

/**
 * Class ConfigServiceProvider.
 *
 * @author Otávio Fernandes <otavio@netonsolucoes.com.br>
 */
class ConfigServiceProvider implements ServiceProviderInterface
{
    /**
     * Nome do arquivo de configuração.
     *
     * @var string
     */
    private $filename;

    /**
     * Lista de substituições.
     *
     * @var array
     */
    private $replacements = [];

    /**
     * Driver de configuração.
     *
     * @var ChainConfigDriver
     */
    private $driver;

    /**
     * ConfigServiceProvider constructor.
     *
     * @param string            $filename
     * @param array             $replacements
     * @param ConfigDriver|null $driver
     */
    public function __construct($filename, array $replacements = [], ConfigDriver $driver = null)
    {
        $this->filename = $filename;

        if ($replacements) {
            foreach ($replacements as $key => $value) {
                $this->replacements['%'.$key.'%'] = $value;
            }
        }

        $this->driver = $driver ?: new ChainConfigDriver([
            new PhpConfigDriver(),
            new YamlConfigDriver(),
            new JsonConfigDriver(),
        ]);
    }

    /**
     * Registra o provedor de serviços de configuração.
     *
     * @param Container $pimple
     */
    public function register(Container $pimple)
    {
        $config = $this->readConfig();

        foreach ($config as $name => $value) {
            if ('%' === substr($name, 0, 1)) {
                $this->replacements[$name] = preg_replace_callback(
                    '/(%.+%)/U', function ($matches) use ($config) {
                        return $this->replacements[$matches[0]];
                    },
                    (string) $value);
            }
        }

        $this->merge($pimple, $config);
    }

    /**
     * Faz o merge das configurações no container de serviços.
     *
     * @param Container $pimple
     * @param array     $config
     */
    private function merge(Container $pimple, array $config)
    {
        foreach ($config as $name => $value) {
            if (isset($pimple[$name]) && is_array($value)) {
                $pimple[$name] = $this->mergeRecursively($pimple[$name], $value);
            } else {
                $pimple[$name] = $this->doReplacements($value);
            }
        }
    }

    /**
     * Faz o merge das configurações encadeadas recursivamente.
     *
     * @param array $currentValue
     * @param array $newValue
     *
     * @return array
     */
    private function mergeRecursively(array $currentValue, array $newValue)
    {
        foreach ($newValue as $name => $value) {
            if (is_array($value) && isset($currentValue[$name])) {
                $currentValue[$name] = $this->mergeRecursively($currentValue[$name], $value);
            } else {
                $currentValue[$name] = $this->doReplacements($value);
            }
        }

        return $currentValue;
    }

    /**
     * Efetua a substituição de valores variáveis no conteúdo das configurações.
     *
     * @param $value
     *
     * @return array|string
     */
    private function doReplacements($value)
    {
        if (!$this->replacements) {
            return $value;
        }

        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $this->doReplacements($v);
            }

            return $value;
        }

        if (is_string($value)) {
            return strtr($value, $this->replacements);
        }

        return $value;
    }

    /**
     * Efetua a leitura da configuração.
     * 
     * @return mixed
     */
    private function readConfig()
    {
        if (!$this->filename) {
            throw new \RuntimeException('A valid configuration file must be passed before reading the config.');
        }

        if (!file_exists($this->filename)) {
            throw new \InvalidArgumentException(
                sprintf("The config file '%s' does not exist.", $this->filename));
        }

        if ($this->driver->supports($this->filename)) {
            return $this->driver->load($this->filename);
        }

        throw new \InvalidArgumentException(
                sprintf("The config file '%s' appears to have an invalid format.", $this->filename));
    }
}
