<?php

namespace App\Http\Controllers\Index;

use App\Models\Attr;
use App\Models\Category;
use App\Models\Goods;
use App\Services\GoodsService;
use DB;
use Gate;


use App\Http\Requests;
use App\Services\AttrService;
use Illuminate\Http\Request;

class MyGoodsController extends Controller
{
    protected $sort = [
        'name',
        'price',
        'new'
    ];

    /**
     * Display a listing of the resource.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index(Request $request)
    {
        $gets = $request->all();
        $data = array_filter($this->_formatGet($gets));


        $goods = auth()->user()->shop->goods()->with('images')->select([
            'id',
            'name',
            'sales_volume',
            'price_retailer',
            'price_wholesaler',
            'min_num_retailer',
            'min_num_wholesaler',
            'user_type',
            'is_new',
            'is_promotion',
            'cate_level_1',
            'cate_level_2'
        ]);

        $result = GoodsService::getGoodsBySearch($data, $goods);

        return view('index.my-goods.index', [
            'goods' => $result['goods']->paginate(),
            'categories' => $result['categories'],
            'attrs' => $result['attrs'],
            'searched' => $result['searched'],
            'moreAttr' => $result['moreAttr'],
            'get' => $gets,
            'data' => $data
        ]);

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        //默认加入店铺配送地址
        $shop = auth()->user()->shop()->with(['deliveryArea.coordinate'])->first();
        $shopDelivery = $shop->deliveryArea->each(function ($area) {
            $area->id = '';
            $area->coordinate;
        });
        $goods = new Goods;
        $goods->deliveryArea = $shopDelivery;
        return view('index.my-goods.goods', [
            'goods' => $goods,
            'attrs' => [],
            'coordinates' => $shopDelivery
        ]);
    }


    /**
     * Display the specified resource.
     *
     * @param $goods
     * @return \Illuminate\View\View
     */
    public function show($goods)
    {
        if (Gate::denies('validate-my-goods', $goods)) {
            abort(403);
        }

        $attrs = (new AttrService())->getAttrByGoods($goods, true);
        $coordinate = $goods->deliveryArea->each(function ($area) {
            $area->coordinate;
        });

        return view('index.my-goods.detail',
            ['goods' => $goods, 'attrs' => $attrs, 'coordinates' => $coordinate->toJson()]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $goods
     * @return Response
     */
    public function edit($goods)
    {
        if (Gate::denies('validate-my-goods', $goods)) {
            abort(403);
        }
        $goodsAttr = $goods->attr;
        //获取所有标签
        $attrGoods = [];
        foreach ($goodsAttr as $attr) {
            $attrGoods[$attr->pid] = $attr->pivot->toArray();
        }

        $attrIds = array_pluck($attrGoods, 'attr_pid');
        $attrResults = Attr::select(['attr_id', 'pid', 'name'])->where('category_id',
            $goods->category_id)->where(function ($query) use ($attrIds) {
            $query->whereIn('attr_id', $attrIds)->orWhere(function ($query) use ($attrIds) {
                $query->whereIn('pid', $attrIds);
            });
        })->get()->toArray();
        $attrResults = (new AttrService($attrResults))->format();
        $coordinates = $goods->deliveryArea->each(function ($area) {
            $area->coordinate;
        });
        return view('index.my-goods.goods', [
            'goods' => $goods,
            'attrs' => $attrResults,
            'attrGoods' => $attrGoods,
            'coordinates' => $coordinates
        ]);
    }

    /**
     * 格式化查询每件
     *
     * @param $get
     * @return array
     */
    private function _formatGet($get)
    {
        $data = [];
        foreach ($get as $key => $val) {
            if (starts_with($key, 'attr_')) {
                $pid = explode('_', $key)[1];
                $data['attr'][$pid] = $val;
            } else {
                $data[$key] = $val;
            }
        }

        return $data;
    }

}
