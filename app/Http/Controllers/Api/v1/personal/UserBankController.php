<?php

namespace App\Http\Controllers\Api\v1\personal;

use App\Http\Controllers\Api\v1\Controller;

use App\Http\Requests;
use App\Models\UserBank;

class UserBankController extends Controller
{
    protected $userId = 1;

    /**
     * 设置默认银行
     *
     * @param $bankInfo
     * @return \WeiHeng\Responses\Apiv1Response
     */
    public function bankDefault($bankInfo)
    {
        $userId = $bankInfo['user_id'];
        if ($userId != $this->userId) {
            return $this->error('要修改的账号不存在');
        }
        if ($bankInfo['is_default'] == 1) {
            return $this->success('设置成功');
        }
        // 设置些用户其它银行账号默认

        $defaultBank = UserBank::where(['user_id' => $this->userId, 'is_default' => 1])->first();
        if ($defaultBank->id) {
            $defaultBank->fill(['is_default' => 0])->save();
        }

        if ($bankInfo->fill(['is_default' => 1])->save()) {
            return $this->success('设置成功');
        }
        return $this->error('设置失败，请重试');
    }

    /**
     * 添加提现账号
     *
     * @param \App\Http\Requests\Index\CreateUserBankRequest $request
     * @return \WeiHeng\Responses\Apiv1Response
     */
    public function store(Requests\Index\CreateUserBankRequest $request)
    {
        // TODO: userId 登录后添加
        if (UserBank::create($request->all())->exists) {
            return $this->success('添加账号成功');
        }
        return $this->success('添加账号时出现问题');
    }

    /**
     * 保存
     *
     * @param \App\Http\Requests\Index\UpdateUserBankRequest $request
     * @param $userBank
     * @return \WeiHeng\Responses\Apiv1Response
     */
    public function update(Requests\Index\UpdateUserBankRequest $request, $userBank)
    {
        if ($userBank->fill($request->all())->save()) {
            return $this->success('保存成功');
        }
        return $this->success('保存账号时出现问题');
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
