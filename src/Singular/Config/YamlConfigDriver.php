<?php

namespace Singular\Config;

use Symfony\Component\Yaml\Yaml;

/**
 * Classe do driver de configuração do formato YAML.
 *
 * @author Otávio Fernandes <otavio@netonsolucoes.com.br>
 */
class YamlDriver implements ConfigDriverInterface
{
    /**
     * Implementa o carregamento do conteúdo do arquivo de configuração.
     *
     * @param string $filename
     *
     * @return array
     */
    public function load($filename)
    {
        if (!class_exists('Symfony\\Component\\Yaml\\Yaml')) {
            throw new \RuntimeException('Unable to read yaml as the Symfony Yaml Component is not installed.');
        }
        $config = Yaml::parse(file_get_contents($filename));

        return $config ?: [];
    }

    /**
     * Implementa a validação da extensão suportada pelo driver.
     *
     * @param string $filename
     * 
     * @return bool
     */
    public function supports($filename)
    {
        return (bool) preg_match('#\.ya?ml(\.dist)?$#', $filename);
    }
}
