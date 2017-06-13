<?php

namespace Singular\Config;

/**
 * Classe do driver de configuração para encadeamento de outros drivers.
 *
 * @author Otávio Fernandes <otavio@netonsolucoes.com.br>
 */
class ChainConfigDriver implements ConfigDriverInterface
{
    /**
     * Lista de drivers para encadeamento de configurações.
     *
     * @var array
     */
    private $drivers;

    /**
     * ChainConfigDriver constructor.
     *
     * @param array $drivers
     */
    public function __construct(array $drivers)
    {
        $this->drivers = $drivers;
    }

    /**
     * Implementação do carregamento da configuração do driver.
     *
     * @param string $filename
     *
     * @return mixed
     */
    public function load($filename)
    {
        $driver = $this->getDriver($filename);

        return $driver->load($filename);
    }

    /**
     * Implementação da validação das extensões permitidas.
     *
     * @param string $filename
     *
     * @return bool
     */
    public function supports($filename)
    {
        return (bool) $this->getDriver($filename);
    }

    /**
     * Verifica se algum dos drivers encadeados suporta o tipo de arquivo.
     *
     * @param string $filename
     *
     * @return mixed|null
     */
    private function getDriver($filename)
    {
        foreach ($this->drivers as $driver) {
            if ($driver->supports($filename)) {
                return $driver;
            }
        }

        return;
    }
}
