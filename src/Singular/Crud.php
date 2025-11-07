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
            $data = $this->afterFind($request, $data);

            if ($data instanceof Response) {
                return $data;
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

        $app['db']->beginTransaction();

        try {
            $success = true;

            foreach ($ids as $idx => $id) {
                if (!$store->remove($id)) {
                    $success = false;
                }
            }

            $response = [
                'success' => $success
            ];

            $app['db']->commit();

        } catch (\Exception $e) {
            $app['db']->rollback();

            // Tratamento genérico de exceções (similar ao método save)
            // Se a exceção tem código HTTP >= 400, retornar resposta estruturada
            // Isso permite que módulos externos usem códigos HTTP para indicar erros específicos
            if ($e->getCode() >= 400 && $e->getCode() < 600) {
                $response = [
                    'success' => false,
                    'code' => $e->getCode(),
                    'message' => $e->getMessage()
                ];
                
                // Se a exceção implementa métodos para dados adicionais, incluí-los na resposta
                // (permite extensibilidade sem acoplamento)
                if (method_exists($e, 'toArray')) {
                    $response = array_merge($response, $e->toArray());
                }
            } else {
                // Para outras exceções, retornar resposta genérica
                $response = [
                    'success' => false,
                    'code' => $e->getCode(),
                    'message' => $e->getMessage()
                ];
            }
        }

        if (method_exists($this, 'afterRemove')) {
            $response = $this->afterRemove($request, $response);

            if ($response instanceof Response) {
                return $response;
            }
        }

        return $app->json($response);
    }
}
