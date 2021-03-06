<?php
/**
 * Created by PhpStorm.
 * User: Colin
 * Date: 2015/9/18
 * Time: 11:35
 */

namespace App\Http\Controllers\Api\V1;

use App\Models\GoodsColumn;
use App\Models\OrderGoods;
use App\Models\SalesmanCustomer;
use App\Models\SalesmanCustomerDisplaySurplus;
use App\Services\AddressService;
use App\Services\AttrService;
use App\Services\BusinessService;
use Gate;
use DB;
use App\Models\Goods;
use App\Services\GoodsService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class GoodsController extends Controller
{
    /**
     * 获取热门商品列表
     *
     * @return \WeiHeng\Responses\Apiv1Response
     */
    public function getGoods()
    {
        return $this->success(['goodsColumns' => GoodsService::getNewGoodsColumn()]);
    }

    /**
     * 商品搜索
     *
     * @param \Illuminate\Http\Request $request
     * @return \WeiHeng\Responses\Apiv1Response
     */
    public function postSearch(Request $request)
    {
        $gets = $request->all();
        $addressData = (new AddressService)->getAddressData();
        $gets = array_merge($gets, array_except($addressData, 'address_name'));

        $goods = Goods::active()->shopUser()->select([
            'id',
            'name',
            'sales_volume',
            'price_retailer',
            'price_wholesaler',
            'pieces_retailer',
            'pieces_wholesaler',
            'bar_code',
            'is_new',
            'is_promotion',
            'is_out',
            'cate_level_1',
            'cate_level_2'
        ])->ofSearchType(auth()->user()->type);

        $result = GoodsService::getGoodsBySearch($gets, $goods, false);

        return $this->success([
            'goods' => $goods->paginate()->toArray(),
            'categories' => $result['categories']
        ]);
    }

    /**
     * 商品详情
     *
     * @param $goodsId
     * @return \WeiHeng\Responses\Apiv1Response
     */
    public function postDetail($goodsId)
    {
        $goods = Goods::active()->with(['images.image', 'deliveryArea'])->find($goodsId);

        if (Gate::denies('validate-goods', $goods)) {
            return $this->forbidden('权限不足');
        }
        $goods->setAppends(['images_url', 'image_url', 'pieces', 'price']);
        $attrs = (new AttrService())->getAttrByGoods($goods);
        $isLike = auth()->user()->likeGoods()->where('id', $goods->id)->pluck('id');
        $goods->shop_name = $goods->shop()->pluck('name');
        is_null($goods->specification_retailer) && ($goods->specification_retailer = '');
        is_null($goods->specification_wholesaler) && ($goods->specification_wholesaler = '');
        $goods->attrs = $attrs;
        $goods->is_like = !is_null($isLike);

        return $this->success(['goods' => $goods]);
    }

    /**
     * 常购商品
     *
     * @param \Illuminate\Http\Request $request
     * @return \WeiHeng\Responses\Apiv1Response
     */
    public function getAlwaysBuy(Request $request)
    {
        $ordersId = auth()->user()->orders()->useful()->noInvalid()->get()->pluck('id');

        $name = $request->input('name');


        if ($request->input('sort') === 'num') {
            // 按多到少
            $goodsId = OrderGoods::whereIn('order_id',
                $ordersId)->groupBy('goods_id')->select(DB::raw('sum(num) amount, goods_id'))->orderBy('amount',
                'DESC')->get()->pluck('goods_id');
        } else {
            $goodsId = OrderGoods::whereIn('order_id', $ordersId)->orderBy('id', 'DESC')->get()->pluck('goods_id');
        }
        $goodsId = $goodsId->toBase()->unique();
        if (!$goodsId->isEmpty()) {
            $referenceIdsStr = implode(',', $goodsId->all());

            $goods = Goods::active()->whereIn('id', $goodsId)
                ->ofGoodsName($name)
                ->orderByRaw("FIELD(id, $referenceIdsStr)")
                ->select([
                    'id',
                    'bar_code',
                    'name',
                    'price_retailer',
                    'price_wholesaler',
                    'pieces_retailer',
                    'pieces_wholesaler',
                    'min_num_retailer',
                    'min_num_wholesaler'
                ])->paginate();

            $goods->each(function ($item) {
                $item->setAppends(['image_url']);
            });
        } else {
            $goods = new LengthAwarePaginator(collect([]), 0, 15);
        }
        return $this->success(['goods' => $goods->toArray()]);
    }

    /**
     * 获取商品单位
     *
     * @return \WeiHeng\Responses\Apiv1Response
     */
    public function getPieces()
    {
        return $this->success(['pieces' => cons()->valueLang('goods.pieces')]);
    }

    /**
     * 获取商品单位
     *
     * @param $goodsId
     * @return \WeiHeng\Responses\Apiv1Response
     */
    public function getGoodsPieces($goodsId)
    {
        $goods = Goods::withTrashed()->with('goodsPieces')->find($goodsId);
        if (is_null($goods)) {
            return $this->error('商品不存在');
        }
        $piecesList = $goods->pieces_list;
        $piecesName = [];
        $pieces = cons()->valueLang('goods.pieces');

        foreach ($piecesList as $item) {
            $piecesName[$item] = $pieces[$item];
        }

        return $this->success(compact('piecesName'));
    }

    /**
     * 获取商品栏目id
     *
     * @param \Illuminate\Http\Request $request
     * @return \WeiHeng\Responses\Apiv1Response
     */
    public function getColumnId(Request $request)
    {
        $addressAttributes = $request->only(['province_id', 'city_id']);
        $cate = $request->input('cate_level_1');
        $columnId = GoodsColumn::OfAddress(array_filter($addressAttributes))->where('cate_level_1',
            $cate)->pluck('id_list');
        return $this->success($columnId);
    }
}