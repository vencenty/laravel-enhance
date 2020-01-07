<?php

namespace Vencenty\LaravelEnhance\Traits;

use Closure;


trait JsonResponse
{

    /**
     * 输出错误
     * @param $target
     * @return array
     */
    protected function resolveError($target)
    {
        list($error, $message) = $target;

        return [
            'error' => $error,
            'message' => $message
        ];
    }

    /**
     * 判断是否产生了错误
     * @param null $message
     * @param int $error
     * @return \Illuminate\Http\JsonResponse
     */
    private function response($message = null, $error = 0)
    {
        $data = $this->isError($message)
            ? $this->resolveError($message)
            : $this->createResponsiveBody($message, $error);

        return response($data);
    }


    /**
     * 创建响应结构体
     * @param $message
     * @param $error
     * @return array
     */
    private function createResponsiveBody($message, $error)
    {
        // 转为collection
        $output = collect([
            'error' => $error,
        ]);

        // 如果是字符串的话
        if (is_string($message)) {
            $output = $output->merge(['message' => $message]);
        }
        // 全部merge到output中
        if (is_array($message) || is_object($message)) {
            $output = $output->merge($message);
        }

        return $output;
    }

    /**
     * 请求成功返回的响应码
     * @param null $message
     * @param int $error
     * @return \Illuminate\Http\JsonResponse
     */
    protected function success($message = null, $error = 0)
    {
        return $this->response($message, $error);
    }

    /**
     * 数组的第一个元素为小于0的数字,那么认为是错误码
     * @param $data
     * @return bool
     */
    private function isError($data)
    {
        $supposeErrorCode = is_array($data) ? current($data) : false;
        return $supposeErrorCode && is_numeric($supposeErrorCode) && ($supposeErrorCode < 0);
    }

    /**
     * 返回错误信息
     * @param null $message
     * @param int $error
     * @return \Illuminate\Http\JsonResponse
     */
    protected function error($message = null, $error = -1)
    {
        return $this->response($message, $error);
    }
}
