<?php

namespace Singular;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Eloquent\Model;

/**
 * Classe do store padrão do Framework.
 *
 * @author Otávio Fernandes <otavio@netonsolucoes.com.br>
 */
class SingularStore extends SingularService
{
    /**
     * Gerenciador da conexão com o banco de dados
     *
     * @var string
     */
    protected $manager = 'capsule';

    /**
     * Gerenciador do banco de dados.
     *
     * @var Manager
     */
    protected $db;

    /**
     * Classe modelo vinculada ao store.
     *
     * @var string
     */
    protected $modelClass = '';

    /**
     * Instância do modelo vinculado ao store.
     *
     * @var Model
     */
    protected $model = null;

    /**
     * Inicializa o Store.
     *
     * @param Application $app
     */
    public function __construct(Application $app, $pack)
    {
        parent::__construct($app, $pack);

        $this->db = $app[$this->manager];
        $this->model = new $this->modelClass;
    }

    /**
     * Salva um registro no model.
     *
     * @param array $data
     *
     * @return integer
     */
    public function save($data)
    {
        /**
         * @var Model
         */
        $record = new $this->modelClass($data);

        try {
            $record->save();

            return $record;
        } catch(\Exception $e) {
            return $e;
        }
    }
}
