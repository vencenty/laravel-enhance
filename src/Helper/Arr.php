<?php


namespace Vencenty\LaravelEnhance\Helper;

class Arr
{
    /**
     * 判断是否是多维数组
     *
     * @param array $array
     * @return bool
     * @author Vencenty <yanchengtian0536@163.com>
     */
    public static function isMultipleArray(array $array)
    {
        return count($array) != count($array, COUNT_RECURSIVE);
    }

    /**
     * 无限极分类
     *
     * @param $data
     * @param $pid
     * @param string $field
     * @param string $childNode
     * @return array
     * @author Vencenty <yanchengtian0536@163.com>
     */
    public static function unlimitedSort($data, $pid, $field = 'parent_id', $childNode = 'child')
    {
        $tree = [];
        foreach ($data as $item) {
            if ($item[$field] == $pid) {
                $item[$childNode] = self::unlimitedSort($data, $item['id'], $field);
                // 卸载掉空的数组元素
                if ($item[$childNode] == null) {
                    unset($item[$childNode]);
                }
                $tree[] = $item;
            }
        }
        return $tree;
    }

}