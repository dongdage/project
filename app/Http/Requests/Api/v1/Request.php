<?php

namespace App\Http\Requests\Api\v1;

use App\Http\Requests\Request as BaseRequest;
use Illuminate\Http\JsonResponse;
use DB;
use Cache;

abstract class Request extends BaseRequest
{

    /**
     * Get the proper failed validation response for the request.
     *
     * @param  array $errors
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function response(array $errors)
    {
        request_info('接口验证错误', $errors);
        return new JsonResponse(['id' => 'invalid_params', 'message' => '参数无效', 'errors' => $errors], 422);
    }

    /**
     * Get the response for a forbidden operation.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function forbiddenResponse()
    {
        request_info('接口权限不足');
        return new JsonResponse(['id' => 'forbidden', 'message' => '没有权限'], 403);
    }

    /**
     * 默认validator
     *
     * @param \Illuminate\Contracts\Validation\Factory $factory
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function defaultValidator($factory)
    {
        $rules = $this->container->call([$this, 'rules']);
        return $factory->make($this->all(), $rules, $this->messages(), $this->attributes());
    }

    /**
     * 查询下级地址
     */
    public function lowerLevelAddress($pid)
    {
        $cacheConf = cons('address.districts.cache');
        $streetId = $pid;
        $cacheKey = $cacheConf['pre_name'] . $streetId;
        if (Cache::has($cacheKey)) {
            $addressList = Cache::get($cacheKey);
        } else {
            $addressList = DB::table('address')->where('pid', $streetId)->lists('name', 'id');
            Cache::forever($cacheKey, $addressList);
        }

        return $addressList;

    }

}