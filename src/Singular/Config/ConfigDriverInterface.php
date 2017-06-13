<?php

namespace Singular\Config;

/**
 * Interface para drivers de configuração.
 *
 * @author Otávio Fernandes <otavio@netonsolucoes.com.br>
 */
interface ConfigDriverInterface
{
    /**
     * Carrega o conteúdo do arquivo de configuração.
     *
     * @param string $filename
     *
     * @return mixed
     */
    public function load($filename);

    /**
     * Retorna as extensões de arquivo suportadas para o driver.
     *
     * @param string $filename
     *
     * @return mixed
     */
    public function supports($filename);
}
