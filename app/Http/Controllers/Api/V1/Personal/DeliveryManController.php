<?php

namespace App\Http\Controllers\Api\V1\Personal;

use App\Http\Controllers\Api\v1\Controller;

use App\Http\Requests;
use App\Models\DeliveryMan;
use App\Models\UserBank;

class DeliveryManController extends Controller
{

    /**
     * 添加配送人员
     *
     * @param \App\Http\Requests\Api\v1\CreateDeliveryManRequest $request
     * @return \WeiHeng\Responses\Apiv1Response
     */
    public function store(Requests\Api\v1\CreateDeliveryManRequest $request)
    {
        if (auth()->user()->DeliveryMan()->create($request->all())->exists) {
            return $this->success('添加成功');
        }
        return $this->success('添加配送人员时出现问题');
    }

    /**
     * 保存
     *
     * @param \App\Http\Requests\Api\v1\CreateDeliveryManRequest $request
     * @param $userBank
     * @return \WeiHeng\Responses\Apiv1Response
     */
    public function update(Requests\Api\v1\CreateDeliveryManRequest $request, $deliveryMan)
    {
        if ($deliveryMan->fill($request->all())->save()) {
            return $this->success('保存成功');
        }
        return $this->success('保存配送人员时出现问题');
    }

    /**
     * 删除
     *
     * @param \App\Models\UserBank $bank
     * @return \WeiHeng\Responses\Apiv1Response
     * @throws \Exception
     */
    public function destroy(UserBank $bank)
    {
        if ($bank->delete()) {
            return $this->success('删除银行账号成功');
        }
        return $this->error('删除时遇到问题');
    }
}