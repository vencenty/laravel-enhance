<?php

namespace Vencenty\LaravelEnhance\Traits;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Vencenty\LaravelEnhance\Helper\Arr;

trait ResourceCURD
{
    /**
     * 获取列表
     *
     * @param array $params
     * @param array $options
     * @return array
     * @author Vencenty <yanchengtian0536@163.com>
     */
    public static function simpleList(array $params = [], $options = [])
    {
        /** @var Model $model */
        $model = new static;
        /** @var Request $request */
        $request = request();

        $options = array_merge([
            'pager' => true,    // 是否返回分页
            'page' => null,    // 页面
            'pageName' => 'page',    // 所在页面名称
            'pageSizeName' => 'per_page', // 页面大小名称
            'toArray' => false,  // callback中的对象转为Array
            'callback' => null, // 内容输出之前的回调函数
            'sort' => null, // 排序字段
            'by' => 'desc',   // 排序顺序,默认降序排列
            'attachParams' => [],
        ], $options);

        // 分页名称是否正确
        $pageName = $options['pageName'] ?? 'page';
        // 当前是第几页
        $currentPage = (int)$request->get($pageName, 1);
        // 分页大小,GET参数优先级最高,不设置的话读取模型里面设置
        $pageSize = $request->get('per_page') ?? $model->getPerPage();


        // 在这以后只要Model执行了Query，就会返回Illuminate\Database\Eloquent\Builder对象,而不是Illuminate\Database\Eloquent\Model

        /**
         * 请求构造器,如果要构造复杂SQL,使用这个参数,
         * 闭包内$this变量指向了 Illuminate\Database\Eloquent\Model 对象,
         * 但是Builder必须要有返回值,否则查询不生效
         * */
        if (isset($params['queryBuilder']) && $params['queryBuilder'] instanceof Closure) {

            $model = $params['queryBuilder']->call($model, $request);
            unset($params['queryBuilder']);
        }

        // 处理排序问题
        $sort = $request->get('sort', $options['sort']);
        $by = $request->get('by', $options['by']);

        if (!empty($sort)) {
            $model = $model->orderBy($sort, $by);
        }

        foreach ($params as $key => $param) {
            if ($param instanceof Closure) {
                $model = $model->{$key}($param);
                continue;
            }
            // 多为数组的形式转为链式调用处理
            // 这样处理以后原先 $model->where(type,'=','3')
            // 括号里面的参数全部转变为 [type, '=', 3]
            if (is_array($param) && Arr::isMultipleArray($param)) {
                foreach ($param as $condition) {
                    $model = $model->{$key}(...$condition);
                }
                continue;
            }
            // 普通数组形式调用解构赋值以后进行调用
            if(is_array($param)) {
                $model = $model->$key(...$param);
                continue;
            }

            $model = $model->$key($param);

        }

        // 计算总页数
        $total = $model->count();
        // 计算分页数据
        $data = $model->offset($pageSize * ($currentPage - 1))
            ->limit($pageSize)
            ->get();


        // 是否转为数组形式
        $data = $options['toArray'] ? $data->toArray() : $data->all();

        // 应用callback函数
        if ($options['callback'] instanceof Closure) {
            array_walk($data, $options['callback']);
        }

        // 分页数
        $pageTotal = ceil($total / $pageSize);

        return array_merge([
            'data' => $data,
            'per_page' => $pageSize, // 每页记录数
            'current_page' => $currentPage, // 当前第几页
            'total' => $total, // 一共有多少记录
            'page_total' => $pageTotal,
        ], $options['attachParams']);

    }

    /**
     * 简单编辑
     *
     * @param array $options
     * @return array
     * @author Vencenty <yanchengtian0536@163.com>
     */
    public static function simpleCreate(array $options = [])
    {
        $options['isEdit'] = false;

        return static::simpleEdit($options);
    }

    /**
     * 进行简单编辑
     *
     * @param array $options
     * @return array
     * @author Vencenty <yanchengtian0536@163.com>
     */
    public static function simpleEdit(array $options = [])
    {
        /** @var Model $model Model模型 */
        $model = new static;
        /** @var null 保存原始模型 */
        $old = null;
        /** @var array $result 输出给客户端的结果 */
        $result = [];
        /** @var string $method 当前请求的方法 */
        $method = request()->method();
        /** @var Request $request */
        $request = request();

        if (!in_array($method, ['GET', 'POST'])) {
            return $model->error("不支持的请求类型");
        }

        $options = array_merge([
            'isEdit' => true,
            'primaryKey' => null,   //更改主键
            'attributes' => null,
            'beforeSave' => null,   //保存之前
            'afterSave' => null, //保存之后
            'loadParams' => [],    //要显示的参数
            'validator' => null, //默认不使用验证
            'onload' => null, // 数据载入的时候的回调
        ], $options);

        // 主键
        $primaryKey = $options['primaryKey'] ?? $model->getKeyName();
        // 主键值
        $primaryKeyValue = (int)$request->input($primaryKey, null);


        // 编辑状态下如果没有主键值,直接抛出错误
        if ($options['isEdit'] && empty($primaryKeyValue)) {
            return $model->error('请求参数错误,hint:没有ID');
        }

        if ($options['isEdit']) {
            $model = $model->findOrFail($primaryKeyValue);
            $old = clone $model;
        }

        /**
         * POST请求的时候统一处理就可以了,在这之前,model如果是编辑状态会直接找到对应数据,
         * 如果新增状态会直接实例模型,准备塞入数据
         */
        if ($method == "POST") {

            $model = $model->fill($options['attributes'] ?? $request->all());

            if ($options['beforeSave'] instanceof Closure) {
                $result = $options['beforeSave']($model);
                if ($result) return $result;
            }


            if (!$model->save()) {
                return $model->error("保存失败");
            }

            if ($options['afterSave'] instanceof Closure) {
                $result = $options['afterSave']($model, $old);
                if ($result) return $result;
            }

            return [$primaryKey => $model->id];
        }

        // 编辑的get方法
        if ($options['onload'] instanceof Closure) {
            $options['onload']($model);
        }

        $data = $model->toArray();
        if (!empty($data)) {
            $result['data'] = $data;
        }


        $result = array_merge($result, $options['loadParams']);

        return $result;
    }

    /**
     * 批量删除接口,支持单个删除
     *
     * @param array $options
     * @return mixed
     * @author Vencenty <yanchengtian0536@163.com>
     */
    public static function simpleDelete(array $options = [])
    {
        $request = request();

        $model = new static;

        if (!$request->isMethod('POST')) {
            return $model->error("错误的请求方式");
        }
        $options = array_merge([
            'beforeDelete' => null, // 删除每一项前的回调
            'afterDelete' => null, // 删除每一项后的回调
            'primaryKey' => null, // 主键,默认id
        ], $options);


        if ($options['beforeDelete'] instanceof Closure) {
            static::deleting($options['beforeDelete']);
        }

        if ($options['afterDelete'] instanceof Closure) {
            static::deleted($options['afterDelete']);
        }

        // 主键
        $primaryKey = $options['primaryKey'] ?? $model->getKeyName();
        // 强转成Array
        $waitDeleteIdCollection = (array)$request->post($primaryKey, null);

        if (empty($waitDeleteIdCollection)) {
            return $model->error("参数错误, hint:没有{$primaryKey}");
        }

        $affectRows = static::destroy($waitDeleteIdCollection);

        return ['affectRows' => $affectRows];
    }


}
