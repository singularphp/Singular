<?php

namespace Singular;

use Symfony\Component\HttpFoundation\Request;
use Singular\Response\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Traço da classe CRUD.
 *
 * @author Otávio Fernandes <otavio@netonsolucoes.com.br>
 */
trait Crud
{
    /**
     * Função responsável por retornar a listagem de todos os registros de uma tabela.
     *
     * @Route(method="post")
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function all(Request $request)
    {
        $app = $this->app;

        $store = $this->getStore();

        return $app->json(array(
            'success' => true,
            'results' => $store->findAll(
                [],
                $request->get('sort', $this->sort)
            )
        ));
    }

    /**
     * Função responsável por retornar a listagem de registros de uma tabela.
     *
     * @Route(method="post")
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function find(Request $request)
    {
        $app = $this->app;

        /** @var SingularStore $store */
        $store = $this->getStore();

        if (method_exists($this, 'beforeFind')) {
            $response = $this->beforeFind($request);

            if ($response instanceof Response) {
                return $response;
            }
        }

        $filters = $request->get('filter', $this->getBaseFilters());
        $paging = $request->get('paging', $this->paging);
        $sort = $request->get('sort', $this->sort);

        $data = $store->findBy($filters, $paging, $sort);
        $data['success'] = true;

        if (method_exists($this, 'afterFind')) {
            $response = $this->afterFind($request, $data);

            if ($data instanceof Response) {
                return $response;
            }
        }

        return $app->json($data);
    }

    /**
     * Função responsável por retornar um único registro.
     *
     * @Route(method="post")
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function get(Request $request)
    {
        $app = $this->app;

        /** @var SingularStore $store */
        $store = $this->getStore();

        if (method_exists($this, 'beforeGet')) {
            $response = $this->beforeGet($request);

            if ($response instanceof Response) {
                return $response;
            }
        }

        $response = [
            'success' => true,
            'result' => $store->find($request->get('id'))
        ];


        if (method_exists($this, 'afterGet')) {
            $response = $this->afterGet($request, $response);

            if ($response instanceof Response) {
                return $response;
            }
        }

        return $app->json($response);
    }

    /**
     * Função responsável por salvar o registro de uma tabela do crud.
     *
     * @Route(method="post")
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function save(Request $request)
    {
        if (method_exists($this, 'beforeSave')) {

            $response = $this->beforeSave($request);

            if ($response instanceof Response) {
                return $response;
            }
        }

        $app = $this->app;
        $store = $this->getStore();

        $app['db']->beginTransaction();

        try {
            $record = $store->save($request->request->all());

            if (!is_array($record)) {
                $response = [
                    'success' => true,
                    'record' => $record
                ];
            } else {
                $response = [
                    'success' => false,
                    'code' => $record['code'],
                    'message' => $record['message']
                ];
            }

            $app['db']->commit();
            
        } catch(\Exception $e) {
            $app['db']->rollback();

            $response = [
                'success' => false,
                'code' => $e->getCode(),
                'message' => $e->getMessage()
            ];

        }

        if (method_exists($this, 'afterSave')) {
            $response = $this->afterSave($request, $response);

            if ($response instanceof Response) {
                return $response;
            }
        }

        return $app->json($response);
    }

    /**
     * Remove um/vários registros da tabela.
     *
     * @Route(method="post")
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function remove(Request $request)
    {
        $app = $this->app;

        $store = $this->getStore();

        if (method_exists($this, 'beforeRemove')) {
            $response = $this->beforeRemove($request);

            if ($response instanceof Response) {
                return $response;
            }
        }

        $ids = $request->get('ids', array());

        if (count($ids) == 0) {
            $ids[] = $request->get('id');
        }

        $success = true;

        foreach ($ids as $idx => $id) {
            if (!$store->remove($id)) {
                $success = false;
            }
        }

        $response = [
            'success' => $success
        ];

        if (method_exists($this, 'afterRemove')) {
            $response = $this->afterRemove($request, $response);

            if ($response instanceof Response) {
                return $response;
            }
        }

        return $app->json($response);
    }
}
