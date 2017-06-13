<?php

namespace Singular\Config;

/**
 * Classe do driver de configuração para arquivos json.
 *
 * @author Otávio Fernandes <otavio@netonsolucoes.com.br>
 */
class JsonConfigDriver implements ConfigDriverInterface
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
        $config = $this->parseJson($filename);

        if (JSON_ERROR_NONE !== json_last_error()) {
            $jsonError = $this->getJsonError(json_last_error());
            throw new \RuntimeException(
                sprintf('Invalid JSON provided "%s" in "%s"', $jsonError, $filename));
        }

        return $config ?: array();
    }

    /**
     * Implementa a validação das extensões de arquivo permitidas.
     *
     * @param string $filename
     *
     * @return bool
     */
    public function supports($filename)
    {
        return (bool) preg_match('#\.json(\.dist)?$#', $filename);
    }

    /**
     * Faz o parseamento do conteúdo do arquivo JSON.
     *
     * @param string $filename
     *
     * @return mixed
     */
    private function parseJson($filename)
    {
        $json = file_get_contents($filename);

        return json_decode($json, true);
    }

    /**
     * Retorna a mensagem de erro do parseamento do conteúdo JSON.
     *
     * @param int $code
     *
     * @return mixed|string
     */
    private function getJsonError($code)
    {
        $errorMessages = array(
            JSON_ERROR_DEPTH => 'The maximum stack depth has been exceeded',
            JSON_ERROR_STATE_MISMATCH => 'Invalid or malformed JSON',
            JSON_ERROR_CTRL_CHAR => 'Control character error, possibly incorrectly encoded',
            JSON_ERROR_SYNTAX => 'Syntax error',
            JSON_ERROR_UTF8 => 'Malformed UTF-8 characters, possibly incorrectly encoded',
        );

        return isset($errorMessages[$code]) ? $errorMessages[$code] : 'Unknown';
    }
}
