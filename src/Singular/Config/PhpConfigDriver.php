<?php

namespace Singular\Config;

/**
 * Classe do driver de configuração json.
 *
 * @author Otávio Fernandes <otavio@netonsolucoes.com.br>
 */
class PhpConfigDriver implements ConfigDriverInterface
{
    /**
     * Implementa o carregamento do arquivo de configuração.
     *
     * @param string $filename
     *
     * @return array
     */
    public function load($filename)
    {
        $config = require $filename;
        $config = (1 === $config) ? array() : $config;

        return $config ?: array();
    }

    /**
     * Implementa a validação das extensões suportadas pelo driver.
     *
     * @param string $filename
     *
     * @return bool
     */
    public function supports($filename)
    {
        return (bool) preg_match('#\.php(\.dist)?$#', $filename);
    }
}
