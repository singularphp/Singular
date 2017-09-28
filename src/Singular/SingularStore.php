<?php

namespace Singular;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Singular\Command\Service\StoreService;

/**
 * Store da aplicação.
 *
 * @author Otávio Fernandes <otavio@netonsolucoes.com.br>
 *
 * @package Singular
 */
class SingularStore extends SingularService
{
    /**
     * Nome da conexão default.
     *
     * @var string
     */
    protected $conn = 'default';

    /**
     * Conexão com o banco de dados.
     *
     * @var \Doctrine\DBAL\Driver\Connection
     */
    protected $db;

    /**
     * Tabela origem do store.
     *
     * @var string
     */
    protected $table;

    /**
     * Campo utilizado como chave da tabela.
     *
     * @var string
     */
    protected $id = 'id';

    /**
     * Campo utilizado para definição do nome da sequence da tabela.
     *
     * @var string
     */
    protected $sequence = null;

    /**
     * Perfis de consulta.
     *
     * @var array
     */
    protected $profiles = [
        /**
         * Definição do perfil default de consulta.
         */
        'default' => [
            'select' => ['t.*'],
            'joins' => [],
            'filters' => [],
            'groupings' => []
        ]
    ];

    /**
     * Perfil de consulta padrão utilizado pelo store.
     *
     * @var string
     */
    protected $profile = 'default';

    /**
     * @var array
     */
    protected $select = array();

    /**
     * Lista de joins.
     *
     * @var array
     */
    protected $joins = array();

    /**
     * Lista de condições padrão.
     *
     * @var array
     */
    protected $wheres = array();

    /**
     * Lista de groupBy da cláusula sql.
     *
     * @var array
     */
    protected $groupBy = array();

    /**
     * Inicializa o Store.
     *
     * @param Application $app
     */
    public function __construct(Application $app, $pack)
    {
        parent::__construct($app, $pack);

        $this->db = $this->getConnection();
    }

    /**
     * Altera o perfil de consulta do store.
     *
     * @param string $profile
     *
     * @return StoreService $this
     */
    public function setProfile($profile)
    {
        $this->profile = $profile;

        return $this;
    }

    /**
     * Adiciona condições nas cláusulas where.
     *
     * @param array $list
     */
    public function addClauses($list)
    {
        $this->wheres = array_merge($this->wheres, $list);
    }

    /**
     * Localiza um registro unico pelo seu id.
     *
     * @param integer $id
     *
     * @return array
     */
    public function find($id)
    {
        $qb = $this->db->createQueryBuilder();

        $qb->select($this->getSelect($this->profile))
            ->from($this->table, 't')
            ->where('t.'.$this->id." = :id");

        $this->addJoins($qb, $this->getJoins($this->profile));
        $params = $this->addFilters($qb, $this->getFilters($this->profile));
        $this->addGrouping($qb, $this->getGroupings($this->profile));

        $params['id'] = $id;

        return $this->db->fetchAssoc($qb->getSQL(), $params);
    }

    /**
     * Localiza o primeiro registro que casa com um conjunto de filtros.
     *
     * @param array $filters
     *
     * @return array
     */
    public function findOneBy($filters)
    {
        $qb = $this->db->createQueryBuilder();

        $qb->select($this->getSelect($this->profile))
            ->from($this->table, 't')
            ->where('1 = 1');

        $this->addJoins($qb, $this->getJoins($this->profile));
        $params = $this->addFilters($qb, $this->getFilters($this->profile));
        $params = array_merge($params, $this->addFilters($qb, $filters));

        return $this->db->fetchAssoc($qb->getSQL(), $params);
    }

    /**
     * Localiza os registros que casam com um conjunto de filtros.
     *
     * @param array $filters
     * @param array $pageOpts
     * @param array $sort
     *
     * @return array
     */
    public function findBy($filters, $pageOpts = array(), $sort = array())
    {
        $qb = $this->db->createQueryBuilder();

        $qb->select($this->getSelect($this->profile))
            ->from($this->table, 't')
            ->where('1 = 1');

        $this->addJoins($qb, $this->getJoins($this->profile));
        $params = $this->addFilters($qb, $this->getFilters($this->profile));
        $params = array_merge($params, $this->addFilters($qb, $filters));
        $this->addGrouping($qb, $this->getGroupings($this->profile));
        $this->addSort($qb, $sort);

        if (!empty($pageOpts)) {
            $rs = $this->paginate($qb, $pageOpts, $params);
        } else {
            $rs = $this->db->fetchAll($qb->getSQL(), $params);
        }

        return $rs;
    }

    /**
     * Localiza todos os registros da tabela.
     *
     * @param array $pageOpts
     * @param array $sort
     *
     * @return array
     */
    public function findAll($pageOpts = array(), $sort = array())
    {
        $qb = $this->db->createQueryBuilder();

        $qb->select($this->getSelect($this->profile))
            ->from($this->table, 't')
            ->where('1 = 1');

        $this->addJoins($qb, $this->getJoins($this->profile));
        $params = $this->addFilters($qb, $this->getFilters($this->profile));
        $this->addGrouping($qb, $this->getGroupings($this->profile));
        $this->addSort($qb, $sort);

        if (!empty($pageOpts)) {
            $rs = $this->paginate($qb, $pageOpts, $params);
        } else {
            $rs = $this->db->fetchAll($qb->getSQL(), $params);
        }

        return $rs;
    }

    /**
     * Salva um registro no banco de dados.
     *
     * @param array $data
     *
     * @return Mixed
     */
    public function save($data)
    {
        if (!isset($data[$this->id])) {
            $data[$this->id] = 0;
        }

        try {
            if (0 === $data[$this->id]) {
                unset($data[$this->id]);

                $id = $this->insert($data);
            } else {
                if ($this->find($data[$this->id])) {
                    $this->update($data);
                } else {
                    $this->insert($data);
                }

                $id = $data[$this->id];
            }
        } catch(\Exception $e) {
            print_r($e->getCode());
            return [
                'code' => $e->getCode(),
                'message' => $e->getMessage()
            ];
        }

        return $id;
    }

    /**
     * Exclui um registro da tabela.
     *
     * @param Integer $id
     *
     * @return Boolean
     */
    public function remove($id)
    {
        try {
            $this->db->delete($this->table, array(
                $this->id => $id
            ));

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Exclui um registro da tabela por uma condição composta.
     *
     * @param array $filter
     *
     * @return bool
     */
    public function removeBy(array $filter)
    {
        try {
            $this->db->delete($this->table, $filter);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Insere um novo registro na tabela.
     *
     * @param array $data
     *
     * @return Mixed
     */
    protected function insert($data)
    {
        try {

            $this->db->insert($this->table, $this->fromArray($data));

            if ($this->sequence){
                $id = $this->db->lastInsertId($this->sequence);
            } else {
                $id = $this->db->lastInsertId($this->id);
            }

        } catch (\Exception $e) {
            throw $e;
        }

        return $id;
    }

    /**
     * Atualiza um registro na tabela.
     *
     * @param array $data
     * @return mixed
     * @throws \Exception
     */
    protected function update($data)
    {
        try {
            $this->db->update($this->table, $this->fromArray($data), array(
                $this->id => $data[$this->id]
            ));

            $id = $data[$this->id];

        } catch (\Exception $e) {
            throw $e;
        }

        return $id;
    }

    /**
     * Adiciona parâmetros de ordenação ao QueryBuilder.
     *
     * @param QueryBuilder $qb
     * @param array        $sort
     */
    protected function addSort($qb, $sort)
    {
        foreach ($sort as $property => $direction) {
            $qb->addOrderBy($property, $direction);
        }
    }

    /**
     * Pagina o resultado de uma consulta.
     *
     * @param QueryBuilder $qb
     * @param array        $pageOpts
     * @param array        $filters
     *
     * @return array
     */
    protected function paginate($qb, $pageOpts, $filters = array())
    {
        $db = $this->db;

        $total = count($db->fetchAll($qb->getSQL(), $filters));

        if (isset($pageOpts['start'])) {
            $qb->setFirstResult(isset($pageOpts['start']) ? $pageOpts['start'] : 0)->
            setMaxResults(isset($pageOpts['limit']) ? $pageOpts['limit'] : 200);
        }

        $result = $db->fetchAll($qb->getSQL(), $filters);

        return array(
            'total' => $total,
            'results' => $result
        );
    }

    /**
     * Adiciona os filtros ao query builder.
     *
     * @param QueryBuilder $qb
     * @param array $filters
     *
     * @return array
     */
    protected function addFilters($qb, $filters)
    {
        $list = [];

        $sgbd = isset($this->app['dbms']) ? $this->app['dbms'] : 'mysql';

        foreach ($filters as $key => $filter) {
            if (strpos($key, '.') === false){
                $keyAlias = "t.".$key;
            } else {
                $keyAlias = $key;
                $key = str_replace('.','_',$key);
            }

            $params = explode(':',$filter);

            if (count($params) == 1) {
                if (!in_array($filter, array('isnull','isnotnull','in','notin'))){
                    array_unshift($params, '=');
                }

                if (in_array($filter, array('isnull','isnotnull'))){
                    array_unshift($params, $filter);
                }
            }

            $filter = $params[1];

            switch ($params[0]) {
                case '%':
                    $list[$key] = "%$filter%";

                    if ($sgbd == 'postgres'){
                        $qb->andWhere($keyAlias.' ilike :'.$key);
                    } else {
                        $qb->andWhere($keyAlias.' like :'.$key);
                    }

                    break;
                case 'in':
                    $qb->andWhere($keyAlias.' IN ('.$filter.')');
                    break;
                case 'notin':
                    $qb->andWhere($keyAlias.' NOT IN ('.$filter.')');
                    break;
                case 'isnull':
                    $qb->andWhere($keyAlias.'  IS NULL');
                    break;
                case 'isnotnull':
                    $qb->andWhere($keyAlias.'  IS NOT NULL');
                    break;
                default:
                    $list[$key] = $filter;
                    $qb->andWhere($keyAlias.' '.$params[0].' :'.$key);
                    break;
            }


        }

        return $list;
    }

    /**
     * Adiciona joins à consulta.
     *
     * @param QueryBuilder $qb
     * @param array        $joins
     */
    protected function addJoins($qb, $joins)
    {
        foreach ($joins as $join) {

            if (count($join) == 3) {
                $join[] = 'join';
            }

            if ($join[3] == 'left') {
                $qb->leftJoin('t',$join[0], $join[1], $join[2]);
            } else {
                $qb->join('t',$join[0], $join[1], $join[2]);
            }
        }
    }

    /**
     * Adiciona groupby à consulta.
     *
     * @param QueryBuilder $qb
     * @param array        $groupings
     */
    protected function addGrouping($qb, $groupings)
    {
        foreach ($groupings as $grouping) {
            $qb->addGroupBy($grouping);
        }
    }

    /**
     * Adiciona cláusulas where padrão nas consultas.
     *
     * @param QueryBuilder $qb
     */
    protected function addWhere($qb)
    {
        $this->addFilters($qb, $this->wheres);
    }

    /**
     * Mapeia os dados recebidos de um array nos campos existentes na tabela.
     *
     * @param array $source
     *
     * @return array
     */
    protected function fromArray($source)
    {
        $data = array();
        $columnNames = $this->getColumnNames();

        foreach ($source as $key => $value) {

            if (in_array($key, $columnNames)) {

                if (is_string($value)) {
                    $value = ($value);
                }

                $data[$key] = $value;
            }
        }

        return $data;
    }

    /**
     * Recupera a relação de campos para serem utilizados na consulta.
     *
     * @param string $profile
     *
     * @return string
     */
    private function getSelect($profile)
    {
        $select = ['t.*'];

        if (isset($this->profiles[$profile]['select'])) {
            $select = $this->profiles[$profile]['select'];
        }

        return implode(",", $select);
    }

    /**
     * Recupera a relação de joins a serem aplicados na consulta.
     *
     * @param string $profile
     *
     * @return array
     */
    private function getJoins($profile)
    {
        $joins = [];

        if (isset($this->profiles[$profile]['joins'])) {
            $joins = $this->profiles[$profile]['joins'];
        }

        return $joins;
    }

    /**
     * Recupera a relação de filtros a serem aplicados na consulta.
     *
     * @param string $profile
     *
     * @return array
     */
    private function getFilters($profile)
    {
        $filters = [];

        if (isset($this->profiles[$profile]['filters'])){
            $filters = $this->profiles[$profile]['filters'];
        }

        return $filters;
    }

    /**
     * Recupera a relação de agrupamentos a serem aplicados na consulta.
     *
     * @param string $profile
     *
     * @return array
     */
    private function getGroupings($profile)
    {
        $groupings = [];

        if (isset($this->profiles[$profile]['groupings'])) {
            $groupings = $this->profiles[$profile]['groupings'];
        }

        return $groupings;
    }

    /**
     * Retorna os nomes das colunas da tabela.
     *
     * @return array
     *
     * @return array
     */
    protected function getColumnNames()
    {
        $names = array();
        $columns = $this->db->getSchemaManager()->listTableColumns($this->table);

        if (count($columns) == 0) {
            $schema = $this->schema ?: $this->app['db.schema'];
            $fullTable = $schema.".".$this->table;
            $columns = $this->db->getSchemaManager()->listTableColumns($fullTable);
        }

        foreach ($columns as $column) {
            $names[] = $column->getName();
        }

        return $names;
    }

    /**
     * Recupera a conexão utilizada pelo store.
     *
     * @return Connection
     */
    protected function getConnection()
    {
        if ('default' == $this->conn) {
            return $this->app['db'];
        } else {
            return $this->app['dbs'][$this->conn];
        }
    }
}