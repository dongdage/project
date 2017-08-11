<?php

namespace App\Models;

use App\Services\GoodsService;
use App\Services\InventoryService;
use Carbon\Carbon;

class Inventory extends Model
{
    protected $table = 'inventory';
    protected $fillable = [
        'inventory_number',
        'user_id',
        'goods_id',
        'shop_id',
        'order_number',
        'inventory_type',
        'action_type',
        'production_date',
        'pieces',
        'cost',
        'quantity',
        'surplus',
        'remark',
        'in_id'
    ];

    protected $appends = [];

    protected $hidden = [];

    /**
     * 商品关联
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     *
     */
    public function goods()
    {
        return $this->belongsTo('App\Models\Goods');
    }

    /**
     * 订单关联
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     *
     */
    public function order()
    {
        return $this->belongsTo('App\Models\Order', 'order_number');
    }

    /**
     * 用户关联
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     *
     */
    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }

    /**
     * 验证店铺
     *
     * @param $query
     * @return mixed
     */
    public function scopeVerifyShop($query)
    {
        return $query->where('shop_id', auth()->user()->shop->id);
    }

    /**
     * 筛选入库
     *
     * @param $query
     * @return mixed
     */
    public function scopeOfIn($query)
    {
        return $query->where('action_type', cons('inventory.action_type.in'));
    }

    /**
     * 筛选出库
     *
     * @param $query
     * @return mixed
     */
    public function scopeOfOut($query)
    {
        return $query->where('action_type', cons('inventory.action_type.out'));
    }

    /**
     * 以编号搜索
     *
     * @param $query
     * @return mixed
     */
    public function scopeOfNumber($query, $number)
    {
        return $query->where(function ($query) use ($number) {
            $query->where('inventory_number', 'LIKE', '%' . $number . '%')
                ->orWhere('order_number', 'LIKE', '%' . $number . '%');
        });
    }

    /**
     * 以当月检索
     *
     * @param $query
     * @return mixed
     */
    public function scopeOfNowMonth($query)
    {
        return $query->where('created_at', '>=', (new Carbon)->startOfMonth()->toDateTimeString())
            ->where('created_at', '<=', (new Carbon)->endOfDay()->toDateTimeString());
    }

    /**
     * 获得转换后的出入库数量
     *
     * @return int|string
     */
    public function getTransformationQuantityAttribute()
    {
        return InventoryService::calculateQuantity($this->goods, $this->quantity);

    }

    /**
     * 获得入库成本
     *
     * @return mixed
     */
    public function getInCostAttribute()
    {
        $where = [
            'id' => $this->in_id,
        ];
        $inv = $this->where($where)->OfIn()->first(['cost']);
        return $inv->cost ?? 0;
    }


    /**
     * 获得入库实际成本
     *
     * @return mixed
     */
    private function getInActualCost()
    {
        $inv = $this->where('id', $this->in_id)->OfIn()->first(['cost', 'pieces', 'goods_id']);
        //进制
        if ($inv) {
            $system = GoodsService::getPiecesSystem($inv->goods, $inv->pieces);
            return ($inv->cost / $system) ?? 0;
        }


    }


    /**
     * 获得入库单位
     *
     * @return mixed
     */
    public function getInPiecesAttribute()
    {
        $where = [
            'id' => $this->in_id,
        ];
        $cost = $this->where($where)->OfIn()->first(['pieces']);
        return $cost->pieces ?? 0;
    }

    /*
     * 获取商品名
     */
    public function getGoodsNameAttribute()
    {
        return $this->goods->name;
    }

    /*
     * 获取商品条形码
     */
    public function getGoodsBarcodeAttribute()
    {
        return $this->goods->bar_code;
    }

    /**
     * 获得盈利额
     *
     * @return string
     */
    public function getProfitAttribute()
    {
        $system = GoodsService::getPiecesSystem($this->goods, $this->pieces);
        return sprintf("%.2f",
            substr(sprintf("%.4f", $this->quantity * (($this->cost / $system) - $this->getInActualCost())), 0, -2));
    }

    /**
     * 获取买家名
     *
     * @return mixed
     */
    public function getBuyerNameAttribute()
    {
        if ($this->order_number > 0) {
            return $this->order->user_shop_name;
        }
    }

    /**
     * 获取卖家名
     *
     * @return mixed
     */
    public function getSellerNameAttribute()
    {
        if ($this->order_number > 0) {
            return $this->order->shop_name;
        }
    }

    /**
     * 获取配送人员名
     *
     * @return mixed
     */
    public function getDeliveryNameAttribute()
    {
        if ($this->order_number > 0) {
            $deliveryMans = $this->order->deliveryMan;
            $name = [];
            foreach ($deliveryMans as $deliveryMan) {
                $name[] = $deliveryMan->name;
            }
            return implode(',', $name);
        }
    }
}