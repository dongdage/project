<?php

namespace App\Models;


class Order extends Model
{
    protected $table = 'order';
    protected $fillable = [
        'order_id',
        'price',
        'pay_type',
        'pay_id',
        'remark',
        'pay_status',
        'status',
        'shipping_address_id',
        'delivery_man_id',
        'user_id',
        'shop_id',
        'paid_at',
        'send_at',
        'confirmed_at',
        'finished_at',
        'is_cancel',
        'cancel_by',
        'cancel_at'
    ];

    protected $appends = ['status_name', 'payment_type', 'step_num',];


    /**
     * 该订单下所有商品
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function orderGoods()
    {
        return $this->hasMany('App\Models\OrderGoods');
    }


    /**
     * 收货人信息
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function shippingAddress()
    {
        return $this->belongsTo('App\Models\ShippingAddress');
    }

    /**
     * 用户信息
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo('App\Models\user');
    }

    /**
     * 店铺信息
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function shop()
    {
        return $this->belongsTo('App\Models\shop');
    }


    /**
     * 关联商品
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function goods()
    {
        return $this->belongsToMany('App\Models\Goods', 'order_goods', 'order_id', 'goods_id')->withPivot('price',
            'num', 'total_price');
    }

    /**
     * 订单配送人员信息
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function deliveryMan()
    {
        return $this->belongsTo('App\Models\DeliveryMan');
    }

    /**
     * 支付形式
     *
     * @param $type
     * @return mixed
     */
    public function getPaymentTypeAttribute()
    {
        $type = $this->attributes['pay_type'];

        return cons()->valueLang('pay_type')[$type];
    }

    /**
     * 订单状态显示
     *
     * @return mixed
     */
    public function getStatusNameAttribute()
    {
        $status = $this->attributes['status'];
        $payStatus = $this->attributes['pay_status'];
        $payType = $this->attributes['pay_type'];
        $isCancel = $this->attributes['is_cancel'];
        if ($isCancel) {
            return cons()->lang('order.is_cancel.on');
        }
        if ($payType == cons('pay_type.online')) {//在线支付
            if (!$status) {//显示未确认
                return cons()->valueLang('order.status')[$status];
            }
            if (!$payStatus) {//显示未支付
                return cons()->valueLang('order.pay_status')[$payStatus];
            }
        }
        //货到付款，当客户已付款时候显示订单状态为已付款
        if ($payType == cons('pay_type.cod') && $payStatus == cons('order.pay_status.payment_success')
            && $status == cons('order.status.send')
        ) {
            return cons()->lang('order.pay_status.payment_success');
        }


        return cons()->valueLang('order.status')[$status];
    }

    /**
     * 进度条显示
     *
     * @return mixed
     */
    public function getStepNumAttribute()
    {
        $payType = $this->attributes['pay_type'];//支付方式
        $payStatus = $this->attributes['pay_status'];//支付状态
        $status = $this->attributes['status'];//订单状态
        if ($payType == cons('pay_type.online')) {//在线支付
            if ($payStatus) {
                return $status + 2;
            }

            return $status + 1;
        } else {//货到付款
            if ($payStatus) {
                return $status + 2;
            }
            if ($status <= cons('order.status.send')) {
                return $status + 1;
            }
        }
    }

    /**
     * 根据买家名字查询订单及买家信息--getSearch()
     *
     * @param $query
     * @param $search
     * @return mixed
     */
    public function scopeOfUserType($query, $search)
    {
        return $query->wherehas('user', function ($query) use ($search) {

            $query->where('user_name', $search);
        });
    }

    /**
     * 根据卖家名字查询订单及卖家信息--getSearch()
     *
     * @param $query
     * @param $search
     * @return mixed
     */
    public function scopeOfSellerType($query, $search)
    {
        return $query->wherehas('shop.user', function ($query) use ($search) {

            $query->where('user_name', $search['search_content']);


        });
    }

    /**
     * 购买订单条件
     *
     * @param $query
     * @param $userId
     * @return mixed
     */
    public function scopeOfBuy($query, $userId)
    {
        return $query->where('user_id', $userId)->with('shop.user', 'goods')->orderBy('id', 'desc');
    }

    /**
     * 销售订单条件
     *
     * @param $query
     * @param $userId
     * @return mixed
     */
    public function scopeOfSell($query, $userId)
    {
        return $query->wherehas('shop.user', function ($query) use ($userId) {
            $query->where('id', $userId);
        })->with('user', 'goods')->orderBy('id', 'desc');
    }

    /**
     * 通过shop_id查询卖家
     *
     * @param $query
     * @param $userId
     * @return mixed
     */
    public function scopeOfSellByShopId($query, $userId)
    {
        return $query->wherehas('shop.user', function ($query) use ($userId) {
            $query->where('id', $userId);
        });

    }

    /**
     * 未确认
     *
     * @param $query
     * @return mixed
     */
    public function scopeNonSure($query)
    {
        return $query->where('status', cons('order.status.non_sure'));
    }

    /**
     * 未取消条件
     *
     * @param $query
     * @return mixed
     */
    public function scopeNonCancel($query)
    {
        return $query->where('is_cancel', cons('order.is_cancel.off'));
    }

    /**
     * 未付款
     *
     * @param $query
     * @return mixed
     */
    public function scopeNonPayment($query)
    {
        return $query->where('pay_status', cons('order.pay_status.non_payment'))->whereNotIn('status',
            [cons('order.status.non_sure'), cons('order.status.finished')]);
    }

    /**
     * 待收款
     *
     * @param $query
     * @return mixed
     */
    public function scopeGetPayment($query)
    {
        return $query->where('pay_status', cons('order.pay_status.non_payment'))->where('status',
            cons('order.status.send'));
    }

    /**
     * 未发货
     *
     * @param $query
     * @return mixed
     */
    public function scopeNonSend($query)
    {
        return $query->where('status', cons('order.status.non_send'));
    }


    /**
     * 待收货,分在线支付和货到付款来讨论
     *
     * @param $query
     * @return mixed
     */
    public function scopeNonArrived($query)
    {
        return $query->where(function ($query) {
            $query->where('pay_type', cons('pay_type.online'))->where('pay_status',
                cons('order.pay_status.payment_success'))->whereIn('status',
                [cons('order.status.non_send'), cons('order.status.send')]);
        })->OrWhere(function ($query) {
            $query->where('pay_type', cons('pay_type.cod'))->whereIn('status',
                [cons('order.status.non_send'), cons('order.status.send')]);
        });
    }

    /**
     * 根据select发送过来的实际参数生成查询语句
     *
     * @param $query
     * @param $search
     * @return mixed
     */
    public function scopeOfSelectOptions($query, $search)
    {
        return $query->where(function ($query) use ($search) {
            if ($search['pay_type']) {
                $query->where('pay_type', $search['pay_type']);
            }
            if ($search['status']) {
                if ($search['status'] == key(cons('order.pay_status'))) {//查询未付款
                    $query->where('pay_status', cons('order.pay_status')[$search['status']])->whereNotIn('status', '<>',
                        cons('order.status.non_sure'));
                } else {
                    $query->where('status', cons('order.status')[$search['status']]);
                }
            }
            if ($search['start_at'] && $search['end_at']) {
                $query->whereBetween('created_at', [$search['start_at'], $search['end_at']]);
            }
        });
    }
}
