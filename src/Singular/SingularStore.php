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
    protected $primaryKey = 'id';

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
    protected $select = [];

    /**
     * Lista de joins.
     *
     * @var array
     */
    protected $joins = [];

    /**
     * Lista de condições padrão.
     *
     * @var array
     */
    protected $wheres = [];

    /**
     * Lista de groupBy da cláusula sql.
     *
     * @var array
     */
    protected $groupBy = [];

    /**
     * Se o store irá utilizar o mecanismo de soft delete para a tabela.
     *
     * @var bool
     */
    protected $softDelete = false;

    /**
     * Nome da coluna que armazena a informação de exclusão do registro.
     *
     * @var string
     */
    protected $softDeleteName = 'dt_exclusao';

    /**
     * Se o store irá registrar automaticamente a data de criação do registro.
     *
     * @var bool
     */
    protected $creationTouch = false;

    /**
     * Nome da coluna que armazena a informação de criação do registro.
     *
     * @var string
     */
    protected $creationTouchName = 'dt_criacao';

    /**
     * Se  store irá registrar automaticamente a data de atualização do registro.
     *
     * @var bool
     */
    protected $updateTouch = false;

    /**
     * Nome da coluna que armazena a informação de atualização do registro.
     *
     * @var string
     */
    protected $updateTouchName = 'dt_atualizacao';

    /**
     * Conexão com o banco de dados.
     *
     * @var \Doctrine\DBAL\Driver\Connection
     */
    protected $db = null;

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
     * Cria e retorna uma nova instância de QueryBuilder.
     *
     * @return QueryBuilder
     */
    public function createQueryBuilder()
    {
        return $this->db->createQueryBuilder();
    }

    /**
     * Altera o perfil de consulta do store.
     *
     * @param string $profile
     *
     * @return SingularStore $this
     */
    public function setProfile($profile)
    {
        $this->profile = $profile;

        return $this;
    }

    /**
     * Retorna todos os registros que casam com uma consulta montada num QueryBuilder.
     *
     * @param QueryBuilder $qb
     * @param array        $params
     * @param array        $pageOpts
     * @param bool         $inDeleted
     *
     * @return array
     */
    public function getAll(QueryBuilder $qb, array $params = [], array $pageOpts = [], $inDeleted = false)
    {
        if ($this->softDelete && !$inDeleted) {
            $qb->andWhere('t.'.$this->softDeleteName.' IS NULL');
        }

        if (!empty($pageOpts)) {
            $rs = $this->paginate($qb, $pageOpts, $params);
        } else {
            $rs = $this->db->fetchAll($qb->getSQL(), $params);
        }

        return $rs;
    }

    /**
     * Recupera o primeiro registro que casa com uma consulta montada no QueryBuilder.
     *
     * @param QueryBuilder $qb
     * @param array        $params
     * @param bool         $inDeleted
     *
     * @return array
     */
    public function getRow(QueryBuilder $qb, array $params = [], $inDeleted = false)
    {
        if ($this->softDelete && !$inDeleted) {
            $qb->andWhere('t.'.$this->softDeleteName.' IS NULL');
        }

        return $this->db->fetchAssoc($qb->getSQL(), $params);
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
     * @param bool    $inDeleted
     *
     * @return array
     */
    public function find($id, $inDeleted = false)
    {
        $qb = $this->db->createQueryBuilder();

        $qb->select($this->getSelect($this->profile))
            ->from($this->table, 't')
            ->where('t.'.$this->primaryKey." = :id");

        $this->addJoins($qb, $this->getJoins($this->profile));
        $params = $this->addFilters($qb, $this->getFilters($this->profile));
        $this->addGrouping($qb, $this->getGroupings($this->profile));

        $params['id'] = $id;

        return $this->getRow($qb, $params, $inDeleted);
    }

    /**
     * Localiza o primeiro registro que casa com um conjunto de filtros.
     *
     * @param array $filters
     * @param bool  $inDeleted
     *
     * @return array
     */
    public function findOneBy($filters, $inDeleted = false)
    {
        $qb = $this->db->createQueryBuilder();

        $qb->select($this->getSelect($this->profile))
            ->from($this->table, 't')
            ->where('1 = 1');

        $this->addJoins($qb, $this->getJoins($this->profile));
        $params = $this->addFilters($qb, $this->getFilters($this->profile));
        $params = array_merge($params, $this->addFilters($qb, $filters));

        return $this->getRow($qb, $params, $inDeleted);
    }

    /**
     * Localiza os registros que casam com um conjunto de filtros.
     *
     * @param array $filters
     * @param array $pageOpts
     * @param array $sort
     * @param bool  $inDeleted
     *
     * @return array
     */
    public function findBy($filters, $pageOpts = array(), $sort = array(), $inDeleted = false)
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

        return $this->getAll($qb, $params, $pageOpts, $inDeleted);
    }

    /**
     * Localiza todos os registros da tabela.
     *
     * @param array $pageOpts
     * @param array $sort
     * @param bool  $inDeleted
     *
     * @return array
     */
    public function findAll($pageOpts = array(), $sort = array(), $inDeleted = false)
    {
        $qb = $this->db->createQueryBuilder();

        $qb->select($this->getSelect($this->profile))
            ->from($this->table, 't')
            ->where('1 = 1');

        $this->addJoins($qb, $this->getJoins($this->profile));
        $params = $this->addFilters($qb, $this->getFilters($this->profile));
        $this->addGrouping($qb, $this->getGroupings($this->profile));
        $this->addSort($qb, $sort);

        return $this->getAll($qb, $params, $pageOpts, $inDeleted);
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
        if (!isset($data[$this->primaryKey])) {
            $data[$this->primaryKey] = 0;
        }

        try {
            if (0 === $data[$this->primaryKey]) {
                unset($data[$this->primaryKey]);

                $id = $this->insert($data);
            } else {
                if ($this->find($data[$this->primaryKey])) {
                    $this->update($data);
                } else {
                    $this->insert($data);
                }

                $id = $data[$this->primaryKey];
            }
        } catch(\Exception $e) {
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
            if ($this->softDelete) {
                $removed = [];
                $removed[$this->primaryKey] = $id;
                $removed[$this->softDeleteName] = date('Y-m-d H:i:s');

                $this->save($removed);
            } else {
                $this->db->delete($this->table, array(
                    $this->primaryKey => $id
                ));
            }

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
     * @return integer Identificador do registro inserido
     *
     * @throws \Exception
     */
    protected function insert($data)
    {
        try {

            if ($this->creationTouch) {
                $data[$this->creationTouchName] = date('Y-m-d H:i:s');
            }

            $this->db->insert($this->table, $this->fromArray($data));

            $id = $this->db->lastInsertId($this->sequence ? $this->sequence : $this->primaryKey);

        } catch (\Exception $e) {
            throw $e;
        }

        return $id;
    }

    /**
     * Atualiza um registro na tabela.
     *
     * @param array $data
     *
     * @return mixed
     *
     * @throws \Exception
     */
    protected function update($data)
    {
        try {

            if ($this->updateTouch) {
                $data[$this->updateTouchName] = date('Y-m-d H:i:s');
            }

            $this->db->update(
                $this->table,
                $this->fromArray($data),
                [
                    $this->primaryKey => $data[$this->primaryKey]
                ]
            );

            $id = $data[$this->primaryKey];

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
            $qb->setFirstResult(isset($pageOpts['start']) ? $pageOpts['start'] : 0)
                ->setMaxResults(isset($pageOpts['limit']) ? $pageOpts['limit'] : 200);
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
            $filterKey = str_replace('.','_',$key);

            if (is_array($filter)) {
                $key = $filter['property'];
                $filter = $filter['clause'];
            }

            if (strpos($key, '.') === false) {
                $keyAlias = "t.".$key;
            } else {
                $keyAlias = $key;
                $key = str_replace('.','_',$key);
            }

            $params = explode(':',$filter);

            if (count($params) == 1) {
                if (!in_array($filter, array('isnull','isnotnull','in','notin'))) {
                    array_unshift($params, '=');
                }

                if (in_array($filter, array('isnull','isnotnull'))) {
                    array_unshift($params, $filter);
                }
            }

            $filter = $params[1];

            switch ($params[0]) {
                case '%':
                    $list[$filterKey] = "%$filter%";

                    if ($sgbd == 'postgres'){
                        $qb->andWhere($keyAlias.' ilike :'.$filterKey);
                    } else {
                        $qb->andWhere($keyAlias.' like :'.$filterKey);
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
                    $list[$filterKey] = $filter;
                    $qb->andWhere($keyAlias.' '.$params[0].' :'.$filterKey);
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
    protected function getSelect($profile)
    {
        $compose = $this->getNamespacedProfile($profile);
        $profileNs = $compose['namespace'];
        $select = ($profileNs !== false) ? $this->getSelect($profileNs) : ['t.*'];
        $profileKey = $compose['profile'];

        if (isset($this->profiles[$profileKey]['select'])) {
            $select = $this->profiles[$profileKey]['select'];
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
    protected function getJoins($profile)
    {
        $compose = $this->getNamespacedProfile($profile);
        $profileNs = $compose['namespace'];
        $joins = ($profileNs !== false) ? $this->getJoins($profileNs) : [];
        $profileKey = $compose['profile'];

        if (isset($this->profiles[$profileKey]['joins'])) {
            $joins = $this->profiles[$profileKey]['joins'];
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
    protected function getFilters($profile)
    {
        $compose = $this->getNamespacedProfile($profile);
        $profileNs = $compose['namespace'];
        $filters = ($profileNs !== false) ? $this->getFilters($profileNs) : [];
        $profileKey = $compose['profile'];

        if (isset($this->profiles[$profileKey]['filters'])) {
            $filters = $this->profiles[$profileKey]['filters'];
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
    protected function getGroupings($profile)
    {
        $compose = $this->getNamespacedProfile($profile);
        $profileNs = $compose['namespace'];
        $groupings = ($profileNs !== false) ? $this->getGroupings($compose['namespace']) : [];
        $profileKey = $compose['profile'];

        if (isset($this->profiles[$profileKey]['groupings'])) {
            $groupings = $this->profiles[$profileKey]['groupings'];
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
     * Recupera um array com o namespace e nome do perfil.
     *
     * @param string $profile
     *
     * @return array
     */
    protected function getNamespacedProfile($profile)
    {
        $parts = explode('.', $profile);

        if (count($parts) < 2) {
            return [
                'namespace' => false,
                'profile' => $profile
            ];
        }

        return [
            'namespace' => $parts[0],
            'profile' => $parts[1]
        ];
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