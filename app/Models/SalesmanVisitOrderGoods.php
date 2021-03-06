<?php

namespace App\Models;

class SalesmanVisitOrderGoods extends Model
{
    protected $table = 'salesman_visit_order_goods';

    protected $fillable = [
        'goods_id',
        'price',
        'pieces',
        'num',
        'amount',
        'type',
        'salesman_visit_order_id',
        'salesman_visit_id'
    ];

    protected $appends = ['pieces_name', 'goods_name', 'goods_image'];


    /**
     * 商品表
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function goods()
    {
        return $this->belongsTo('App\Models\Goods')->withTrashed();
    }

    /**
     * 关联订单表
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function salesmanVisitOrder()
    {
        return $this->belongsTo('App\Models\SalesmanVisitOrder');
    }

    /**
     * 关联商品记录表
     *
     * @return mixed
     */
    public function goodsRecord()
    {
        return $this->belongsTo('App\Models\SalesmanVisitGoodsRecord', 'salesman_visit_id',
            'salesman_visit_id')->where('goods_id', $this->goods_id);
    }

    /**
     * 获取商品名
     *
     * @return string
     */
    public function getGoodsNameAttribute()
    {
        return $this->goods ? $this->goods->name : '';
    }

    /**
     * 获取商品图片
     *
     * @return string
     */
    public function getGoodsImageAttribute()
    {
        return (string) $this->goods ? $this->goods->image_url : asset('images/goods_default.png');
    }

    /**
     * 获取单位名
     *
     * @return string
     */
    public function getPiecesNameAttribute()
    {
        return cons()->valueLang('goods.pieces', $this->pieces);
    }
}
