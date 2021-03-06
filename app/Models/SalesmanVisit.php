<?php

namespace App\Models;


use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\File;

class SalesmanVisit extends Model
{
    protected $table = 'salesman_visit';

    protected $fillable = [
        'salesman_customer_id',
        'x_lng',
        'y_lat',
        'shop_id',
        'photos',
        'address'
    ];

    protected $hidden = ['updated_at'];

    /**
     * 关联业务员
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function salesman()
    {
        return $this->belongsTo('App\Models\Salesman');
    }

    /**
     * 关联客户表
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function salesmanCustomer()
    {
        return $this->belongsTo('App\Models\SalesmanCustomer');
    }

    /**
     * 关联订单表
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function orders()
    {
        return $this->hasMany('App\Models\SalesmanVisitOrder');
    }

    /**
     * 关联拜访商品记录
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function goodsRecord()
    {
        return $this->hasMany('App\Models\SalesmanVisitGoodsRecord');
    }

    /**
     * 关联拜访照片
     */
    public function photos()
    {
        return $this->morphMany('App\Models\File', 'fileable');
    }

    /**
     * 按拜访时间查询
     *
     * @param $query
     * @param $beginTime
     * @param $endTime
     * @return mixed
     */
    public function scopeOfTime($query, $beginTime = null, $endTime = null)
    {
        if ($beginTime) {
            $query = $query->where('created_at', '>=', $beginTime);
        }
        if ($endTime) {
            $query = $query->where('created_at', '<=', $endTime);
        }
        return $query;
    }

    /**
     * 排序
     *
     * @param $query
     * @param string $field
     * @param string $sort
     * @return mixed
     */
    public function scopeOfSort($query, $field = 'id', $sort = 'DESC')
    {
        return $query->orderBy($field, $sort);
    }

    /**
     * 设置拍照图片
     *
     * @param $files
     * @return bool
     */
    public function setPhotosAttribute($files)
    {
        if (!empty($files)) {
            return $this->associateFiles($files, 'photos', '0', false);
        }
        return true;

    }

    /**
     * 获取订单详情
     *
     * @return array
     */
    public function getOrderDetailAttribute()
    {
        $orders = $this->orders;
        $types = cons('salesman.order.type');

        $order = $orders->where('type', $types['order'])->first();
        $returnOrder = $orders->where('type', $types['return_order'])->first();

        $orderAmount = $order ? $order->amount : 0;
        $returnOrderAmount = $returnOrder ? $returnOrder->amount : 0;

        return [
            'order_amount' => $orderAmount,
            'return_order_amount' => $returnOrderAmount
        ];
    }


    /**
     * 获取客户名
     *
     * @return string
     */
    public function getSalesmanCustomerNameAttribute()
    {
        return $this->salesmanCustomer ? $this->salesmanCustomer->name : '';
    }

    /**
     * 获取拍照图片
     * @return array
     */
    public function getPhotosUrlAttribute()
    {
        $photos = [];
        foreach ($this->photos as $value) {
            $photos[] = $value->url;
        }
        return $photos;
    }

    /**
     * 获取业务员名
     *
     * @return string
     */
    public function getSalesmanNameAttribute()
    {
        return $this->salesman ? $this->salesman->name : '';
    }


}
