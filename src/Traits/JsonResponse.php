<?php

namespace Vencenty\LaravelEnhance\Traits;


use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;
use Exception;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Arr;


trait JsonResponse
{
    /**
     * 是否是错误
     *
     * @var bool
     */
    protected $isError;

    /**
     * 错误状态码
     *
     * @var int
     */
    protected $errorStatusCode = -10000;

    /**
     * 成功状态码
     *
     * @var int
     */
    protected $successStatusCode = 0;

    /**
     * 解析错误并输出
     *
     * @param $data
     * @return array
     */
    protected function resolveError($data)
    {
        // 字符串的话直接赋值返回
        if (is_string($data)) {
            $message = $data;
        } else { // 如果是数组并且不是关联数组的话,拆解
            list($error, $message) = $data;
        }

        return [
            'error' => $error ?? $this->errorStatusCode,
            'message' => $message
        ];
    }

    /**
     * 返回结果
     *
     * @param $result
     * @param $status
     * @param $headers
     * @return \Illuminate\Contracts\Routing\ResponseFactory|Response
     */
    private function response($result, $status, $headers)
    {
        $content = $this->isError
            ? $this->resolveError($result)
            : $this->createResponsiveBody($result);

        return response($content, $status, $headers);
    }

    /**
     * 创建响应
     *
     * @param $data
     * @return array
     */
    private function createResponsiveBody($data)
    {
        $body = [
            'error' => $this->successStatusCode,
        ];

        if ($data instanceof Model || $data instanceof Collection || $data instanceof AbstractPaginator) {
            $data = $data->toArray();
        }

        $body = array_merge($body, $data);

        return $body;
    }

    /**
     * 返回成功信息
     *
     * @param null $result
     * @param int $status
     * @param array $headers
     * @return \Illuminate\Contracts\Routing\ResponseFactory|Response
     */
    protected function success($result = null, $status = 200, $headers = [])
    {
        $this->isError = false;
        return $this->response($result, $status, $headers);
    }

    /**
     * 返回错误信息
     *
     * @param null $result
     * @param int $status
     * @param array $headers
     * @return \Illuminate\Contracts\Routing\ResponseFactory|Response
     */
    protected function error($result = null, $status = 200, $headers = [])
    {
        $this->isError = true;
        return $this->response($result, $status, $headers);
    }

}
