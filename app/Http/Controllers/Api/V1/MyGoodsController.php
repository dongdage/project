<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AddressData;
use App\Models\BarcodeWithoutImages;
use App\Models\Goods;
use App\Models\OrderGoods;
use App\Models\Shop;
use App\Http\Requests;
use App\Models\Images;
use App\Services\AddressService;
use App\Services\AttrService;
use App\Services\CategoryService;
use App\Services\GoodsService;
use App\Services\ImageUploadService;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Gate;
use DB;
use ExcelImport;

class MyGoodsController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->all();
        $shop = auth()->user()->shop;
        $result = GoodsService::getShopGoods($shop, $data);
        $goods = $result['goods']->with('goodsLike')->orderBy('id', 'DESC')->paginate();
        $goods->each(function ($goods) {
            $goods->setAppends([
                'cost_tips',
                'surplus_inventory',
                'pieces_list',
                'pieces_lang_list',
                'like_amount',
                'image_url',
                'pieces',
                'price'
            ])->setHidden(['goods_like', 'goodsPieces']);
        });
        return $this->success([
            'goods' => $goods->toArray(),
            'categories' => CategoryService::formatShopGoodsCate($shop)
        ]);
    }

    /**
     * 获取所有赠品
     *
     * @param \Illuminate\Http\Request $request
     * @return \WeiHeng\Responses\Apiv1Response
     */
    public function gifts(Request $request)
    {
        $shop = auth()->user()->shop;

        $gifts = $shop->goods()->active()
            ->ofNameOrCode($request->input('nameOrCode'))
            ->where('is_gift', 1)->with('goodsPieces')->select([
                'id',
                'name',
                'bar_code',
                'price_retailer',
                'pieces_retailer',
                'price_wholesaler',
                'pieces_wholesaler',
            ])->get();
        $gifts->each(function ($item) {
            $item->addHidden(['images', 'goodsPieces', 'price', 'inventory']);
            $item->setAppends(['pieces_list','cost_tips', 'surplus_inventory', 'pieces_lang_list']);
        });
        return $this->success($gifts->toArray());
    }

    /**
     * 获取商品for商品弹出窗
     *
     * @param \Illuminate\Http\Request $request
     * @return \WeiHeng\Responses\Apiv1Response
     */
    public function goods(Request $request)
    {
        $data = $request->only('condition', 'page');
        $goods = auth()->user()->shop->goods()->with([
            'goodsPieces'
        ])->ofNameOrCode($data['condition'])->active()->paginate();
        $goods->each(function ($item) {
            $item->goodsPieces->pieces_level_1_lang = cons()->valueLang('goods.pieces',
                $item->goodsPieces->pieces_level_1);
            $item->goodsPieces->pieces_level_2_lang = cons()->valueLang('goods.pieces',
                $item->goodsPieces->pieces_level_2);
            $item->goodsPieces->pieces_level_3_lang = cons()->valueLang('goods.pieces',
                $item->goodsPieces->pieces_level_3);
        });
        return $this->success([
            'goods' => $goods->toArray(),
        ]);
    }

    /**
     * store a newly created resource in storage.
     *
     * @param \App\Http\Requests\Api\v1\CreateGoodsRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function store(Requests\Api\v1\CreateGoodsRequest $request)
    {
        $user = auth()->user();

        //判断有无缴纳保证金
        /* if (!$user->deposit) {
             return $this->error('添加商品前请先缴纳保证金');
         }*/

        $result = DB::transaction(function () use ($user, $request) {

            $attributes = $request->except('images', 'pieces_level_1', 'pieces_level_2', 'pieces_level_3', 'system_1',
                'system_2', 'specification');
            $attributes['user_type'] = auth()->user()->type;

            $piecesAttributes = $request->only('pieces_level_1', 'pieces_level_2', 'pieces_level_3', 'system_1',
                'system_2', 'specification');

            $piecesAttributes = $piecesAttributes['pieces_level_3'] == '' ? array_except($piecesAttributes,
                ['pieces_level_3', 'system_2']) : $piecesAttributes;
            $piecesAttributes = $piecesAttributes['pieces_level_2'] == '' ? array_except($piecesAttributes,
                ['pieces_level_2', 'system_1']) : $piecesAttributes;

            $attributes['warning_piece'] = isset($piecesAttributes['pieces_level_2']) ? $piecesAttributes['pieces_level_2'] : $piecesAttributes['pieces_level_1'];

            $goods = $user->shop->goods()->create($attributes);

            if ($goods->exists) {
                $goods->goodsPieces()->create($piecesAttributes);

                // 更新配送地址
                $this->updateDeliveryArea($goods, $request->input('area'));

                $images = $request->hasFile('images') ? $request->file('images') : $request->input('images');

                if (!is_null($images) && empty($goods->images->toArray())) {
                    $this->_setImages($images, $goods->bar_code);
                }

                // 更新标签
                $this->updateAttrs($goods, array_get($attributes, 'attrs', []));
                //保存没有图片的条形码
                $this->saveWithoutImageOfBarCode($goods);
                $orderGoodsId = $request->input('orderGoods');
                if ($orderGoodsId) {
                    //获取异常记录
                    $orderGoods = OrderGoods::find($orderGoodsId);
                    //处理入库异常
                    (new InventoryService())->inException($orderGoods);
                }
                return true;
            }
        });

        return $result ? $this->success('添加商品成功') : $this->error('添加商品出现错误');
    }

    /**
     *  获取商品详情
     *
     * @param $goods
     * @return \WeiHeng\Responses\Apiv1Response
     */
    public function show($goods)
    {
        if (Gate::denies('validate-my-goods', $goods)) {
            return $this->forbidden('权限不足');
        }
        $goods->load(['images', 'deliveryArea', 'goodsPieces']);
        $goods->setAppends(['images_url', 'image_url', 'pieces', 'price']);
        $attrs = (new AttrService())->getAttrByGoods($goods, false);
        $goods->shop_name = $goods->shop()->pluck('name');
        $goods->attrs = $attrs;
        return $this->success(['goods' => $goods]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \App\Http\Requests\Api\v1\UpdateGoodsRequest $request
     * @param $goods
     * @return \Illuminate\Http\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function update(Requests\Api\v1\UpdateGoodsRequest $request, $goods)
    {
        if (Gate::denies('validate-my-goods', $goods)) {
            return $this->forbidden('权限不足');
        }
        $result = DB::transaction(function () use ($request, $goods) {
            $attributes = $request->except('images', 'pieces_level_1', 'pieces_level_2', 'pieces_level_3', 'system_1',
                'system_2', 'specification');
            //是否退换货补充
            $attributes['is_back'] = isset($attributes['is_back']) ? $attributes['is_back'] : 0;
            $attributes['is_change'] = isset($attributes['is_change']) ? $attributes['is_change'] : 0;
            $attributes['is_new'] = isset($attributes['is_new']) ? $attributes['is_new'] : 0;
            $attributes['is_out'] = isset($attributes['is_out']) ? $attributes['is_out'] : 0;
            $attributes['is_expire'] = isset($attributes['is_expire']) ? $attributes['is_expire'] : 0;

            if (!isset($attributes['is_promotion'])) {
                $attributes['is_promotion'] = 0;
                $attributes['promotion_info'] = '';
            }
            if ($goods->fill($attributes)->save()) {
                $goods->goodsPieces && $goods->goodsPieces->delete();
                //更新商品单位
                $piecesAttributes = $request->only('pieces_level_1', 'pieces_level_2', 'pieces_level_3', 'system_1',
                    'system_2', 'specification');

                $piecesAttributes = $piecesAttributes['pieces_level_3'] == '' ? array_except($piecesAttributes,
                    ['pieces_level_3', 'system_2']) : $piecesAttributes;
                $piecesAttributes = $piecesAttributes['pieces_level_2'] == '' ? array_except($piecesAttributes,
                    ['pieces_level_2', 'system_1']) : $piecesAttributes;
                $goods->goodsPieces()->create($piecesAttributes);
                // 更新配送地址
                $this->updateDeliveryArea($goods, $request->input('area'));

                $images = $request->hasFile('images') ? $request->file('images') : $request->input('images');
                if (!is_null($images)) {
                    $this->_setImages($images, $goods->bar_code);
                }

                // 更新标签
                $this->updateAttrs($goods, array_get($attributes, 'attrs', []));

                $this->saveWithoutImageOfBarCode($goods);
                return true;
            }
        });

        return $result ? $this->success('更新商品成功') : $this->error('更新商品时遇到问题');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param $goods
     * @return \WeiHeng\Responses\Apiv1Response
     */
    public function destroy($goods)
    {
        if (Gate::denies('validate-my-goods', $goods)) {
            return $this->forbidden('权限不足');
        }
        if ($goods->inventory->count()) {
            return $this->error('该商品已有库存信息,不能删除');
        }
        if (!$goods->status && $goods->delete()) {
            return $this->success('删除商品成功');
        }
        return $this->error('删除商品时遇到问题');
    }

    /**
     * 库存预警值设置
     *
     * @param \Illuminate\Http\Request $request
     * @param $goods
     * @return \WeiHeng\Responses\Apiv1Response
     */
    public function setWarning(Request $request, $goods)
    {
        if (Gate::denies('validate-my-goods', $goods)) {
            return $this->forbidden('没有权限');
        }
        $requestData = $request->only('warning_value', 'warning_piece');
        if ($goods->fill($requestData)->save()) {
            return $this->success('保存成功');
        }
        return $this->error('保存失败');
    }

    /**
     * 商品上下架
     *
     * @param \Illuminate\Http\Request $request
     * @return \WeiHeng\Responses\Apiv1Response
     */
    public function shelve(Request $request)
    {
        $goodsId = $request->input('id');
        $goods = Goods::find($goodsId);

        if (Gate::denies('validate-my-goods', $goods)) {
            return $this->forbidden('权限不足');
        }
        $status = intval($request->input('status'));
        $goods->status = $status;
        $statusVal = cons()->valueLang('goods.status', $status);
        if ($goods->save()) {
            if ($status) {
                return $this->success('商品' . $statusVal . '成功');
            } else {
                return $this->success(null);
            }
        }
        return $this->error('商品' . $statusVal . '失败');
    }

    /**
     * 设置为促销商品
     *
     * @param \App\Models\Goods $goods
     * @return \WeiHeng\Responses\Apiv1Response
     */
    public function promo(Goods $goods)
    {
        if (Gate::denies('validate-my-goods', $goods)) {
            return $this->forbidden('权限不足');
        }
        $shopId = auth()->user()->shop_id;

        if ($promoGoods = $goods->promoGoods()->withTrashed()->where('shop_id', $shopId)->first()) {
            return $promoGoods->fill(['deleted_at' => null])->save() ? $this->success('设置成功') : $this->error('设置失败，请重试');
        }

        $attributes = [
            'shop_id' => $shopId
        ];

        return $goods->promoGoods()->create($attributes)->exists ? $this->success('设置成功') : $this->error('设置失败，请重试');
    }

    /**
     * 设置为抵费商品
     *
     * @param \App\Models\Goods $goods
     * @return \WeiHeng\Responses\Apiv1Response
     */
    public function mortgage(Goods $goods)
    {
        if (Gate::denies('validate-my-goods', $goods)) {
            return $this->forbidden('权限不足');
        }
        $shopId = auth()->user()->shop_id;

        if ($mortgageGoods = $goods->mortgageGoods()->withTrashed()->where('shop_id', $shopId)->first()) {
            return $mortgageGoods->fill(['deleted_at' => null])->save() ? $this->success('设置成功') : $this->error('设置失败，请重试');
        }

        $attributes = [
            'goods_name' => $goods->name,
            'pieces' => $goods->pieces_id,
            'shop_id' => $shopId
        ];

        return $goods->mortgageGoods()->create($attributes)->exists ? $this->success('设置成功') : $this->error('设置失败，请重试');
    }

    /**
     * 设置为赠品
     *
     * @param \Illuminate\Http\Request $request
     * @return \WeiHeng\Responses\Apiv1Response
     */
    public function gift(Request $request)
    {
        $goodsId = $request->input('id');
        $goods = Goods::find($goodsId);

        if (Gate::denies('validate-my-goods', $goods)) {
            return $this->forbidden('权限不足');
        }
        $status = intval($request->input('status'));
        $goods->is_gift = $status;
        if ($goods->save()) {
            if ($status) {
                return $this->success('操作成功');
            } else {
                return $this->success(null);
            }
        }
        return $this->error('操作失败');
    }

    /**
     * 商品批量上下架
     *
     * @param \Illuminate\Http\Request $request
     * @return \WeiHeng\Responses\Apiv1Response
     */
    public function batchShelve(Request $request)
    {
        $goodsIds = $request->input('ids');
        $goods = Goods::where('shop_id', auth()->user()->shop()->pluck('id'))->whereIn('id', $goodsIds)->get();

        if ($goods->isEmpty()) {
            return $this->error('请选择商品');
        }
        $status = intval($request->input('status'));

        $goods->each(function ($item) use ($status) {
            $item->fill(['status' => $status])->save();
        });

        $statusVal = cons()->valueLang('goods.status', $status);
        return $this->success('商品' . $statusVal . '成功');
    }

    /**
     * 获取商品图片
     *
     * @param \Illuminate\Http\Request $request
     * @return \WeiHeng\Responses\Apiv1Response
     */
    public function getImages(Request $request)
    {
        $barCode = $request->input('bar_code');
        if (!$barCode) {
            return $this->error('暂无商品图片');
        }
        $goodsImage = Images::active()->with('image')->where('bar_code', $barCode)->paginate()->toArray();

        return $this->success(['goodsImage' => $goodsImage]);
    }

    /**
     * 商品批量导入
     *
     * @param \Illuminate\Http\Request $request
     * @return \WeiHeng\Responses\Apiv1Response
     */
    public function import(Request $request)
    {
        $file = $request->file('file');

        $postAttr = $request->only(['cate_level_1', 'cate_level_2', 'cate_level_3', 'status', 'shop_id']);
        $attrs = $request->input('attrs');
        $importResult = $this->importGoods($file, $postAttr, $attrs);

        return $importResult['type'] ? $this->success($importResult['info'],
            ['Content-Type' => 'text/html']) : $this->error($importResult['info']);
    }

    /**
     * 导入商品
     *
     * @param $file
     * @param $postAttr
     * @param $attrs
     * @return array
     */
    public function importGoods($file, $postAttr, $attrs)
    {
        $results = ExcelImport::fetchContent($file);

        if (!$results) {
            return $this->setImportResult(ExcelImport::getErrorMsg());
        }

        $shopId = isset($postAttr['shop_id']) && $postAttr['shop_id'] > 0 ? $postAttr['shop_id'] : 0;

        if ($shopId) {
            $shop = Shop::with(['deliveryArea', 'user'])->find($shopId);
        } else {
            $shop = auth()->user()->shop->load(['deliveryArea']);
        }
        if (is_null($shop)) {
            return $this->setImportResult('店铺不存在');
        }
        //dd($shop);
        DB::beginTransaction();
        global $error;
        try {
            foreach ($results as $key => $goods) {
                if (is_null($goods[0])) {
                    break;
                }
                $goodsAttr = $this->_getGoodsAttrForImport($goods, $postAttr, $shop->user_type);
                if (!is_array($goodsAttr)) {
                    $error = $goodsAttr . '在第' . ($key + 2) . '行';
                    throw new \Exception;
                }
                $goodsModel = $shop->goods()->create($goodsAttr['goods']);
                $goodsModel->goodsPieces()->create($goodsAttr['pieces']);
                if ($goodsModel->exists) {
                    $this->saveWithoutImageOfBarCode($goodsModel);
                    $this->_copyShopDeliveryAreaForImport($goodsModel, $shop);
                    !is_null($attrs) && $this->updateAttrs($goodsModel, $attrs);
                } else {
                    $error = '文件格式不正确';
                    throw new \Exception;
                }
            }
            DB::commit();
            return $this->setImportResult('上传成功', true);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->setImportResult($error);
        }
    }

    /**
     * 更新配送地址处理
     *
     * @param $model
     * @param $area
     * @return bool
     */
    private function updateDeliveryArea($model, $area)
    {
        //配送区域添加
        $areaArr = (new AddressService($area))->formatAddressPost();
        //删除原有配送区域信息
        $model->deliveryArea()->delete();
        if (!empty($areaArr)) {
            $areas = [];
            foreach ($areaArr as $data) {
                unset($data['coordinate']);
                $areasModal = new AddressData($data);
                if (!in_array($areasModal, $areas)) {
                    $areas[] = $areasModal;
                }
            }
            $model->deliveryArea()->saveMany($areas);
        }
        return true;
    }

    /**
     * 复制商店配送区域至商品配送区域
     *
     * @param $goodsModel
     * @return bool
     */
    private function _copyShopDeliveryAreaForImport($goodsModel, $shop)
    {
        if (!$goodsModel instanceof Goods) {
            return false;
        }

        $shop->deliveryArea->each(function ($area) use ($goodsModel) {
            $areaModel = $goodsModel->deliveryArea()->save(new AddressData($area->toArray()));
            unset($areaModel);
        });
        return true;
    }

    /**
     * 获取商品字段
     *
     * @param $goodsArr
     * @param $postAttr
     * @param $shopType
     * @return array|string
     */
    private function _getGoodsAttrForImport($goodsArr, $postAttr, $shopType)
    {
        $length = count($goodsArr);
        $goods = [
            'name' => $goodsArr['0'],  //商品名
            'bar_code' => $goodsArr['1'], //条形码
            'price_retailer' => $goodsArr['2'], //价格
            'price_retailer_pick_up' => $goodsArr['3'] ?? $goodsArr['2'], //自提价
            'pieces_retailer' => $goodsArr['4'] ?? 0, //单位编号
            'min_num_retailer' => $goodsArr['5'] ?? 0, //最低购买数
            'user_type' => $shopType   //用户类型

        ];

        $pieces = [
            'pieces_level_1' => $goodsArr[6],
            'pieces_level_2' => $goodsArr[7] ?? null,
            'pieces_level_3' => $goodsArr[9] ?? null,
            'system_1' => $goodsArr[8] ?? null,
            'system_2' => $goodsArr[10] ?? null,
        ];
        if ($shopType == cons('user.type.supplier')) {
            if ($length < 16) {
                return '不符合供应商批量录入商品标准请核对后再试!';
            }
            $goods['price_wholesaler'] = $goodsArr['6'] ?? $goodsArr['2'];
            $goods['price_wholesaler_pick_up'] = $goodsArr['7'] ?? $goods['price_wholesaler'];
            $goods['pieces_wholesaler'] = $goodsArr[8] ?? $goodsArr[4];
            $goods['min_num_wholesaler'] = $goodsArr[9] ?? $goodsArr[5];
            $pieces = [
                'pieces_level_1' => $goodsArr[10],
                'pieces_level_2' => $goodsArr[11] ?? null,
                'pieces_level_3' => $goodsArr[13] ?? null,
                'system_1' => $goodsArr[12] ?? null,
                'system_2' => $goodsArr[14] ?? null,
            ];

            if ($goods['pieces_wholesaler'] == $pieces['pieces_level_1']) {
                $goods['specification_wholesaler'] = end($goodsArr) . ($pieces['system_2'] > 0 ? '*' . $pieces['system_2'] : '') . '*' . $pieces['system_1'];
            } elseif ($goods['pieces_wholesaler'] == $pieces['pieces_level_2']) {
                $goods['specification_wholesaler'] = end($goodsArr) . ($pieces['system_2'] > 0 ? '*' . $pieces['system_2'] : '');
            } elseif ($goods['pieces_wholesaler'] == $pieces['pieces_level_3']) {
                $goods['specification_wholesaler'] = end($goodsArr);
            }

        } else {
            if ($length < 12) {
                return '不符合批发商批量录入商品标准请核对后再试!';
            }
        }
        if (!in_array($goods['pieces_retailer'], $pieces) || (array_get($goods,
                    'pieces_wholesaler') && !in_array($goods['pieces_wholesaler'], $pieces))
        ) {
            return '单位编号不正确请检查!';
        }
        if (!empty($pieces['pieces_level_2']) && empty($pieces['system_1'])) {
            return '二级进制没有填!';
        }
        if (!empty($pieces['pieces_level_3']) && empty($pieces['system_2'])) {
            return '三级进制没有填!';
        }

        if ($goods['pieces_retailer'] == $pieces['pieces_level_1']) {
            $goods['specification_retailer'] = end($goodsArr) . ($pieces['system_2'] > 0 ? '*' . $pieces['system_2'] : '') . '*' . $pieces['system_1'];
        } elseif ($goods['pieces_retailer'] == $pieces['pieces_level_2']) {
            $goods['specification_retailer'] = end($goodsArr) . ($pieces['system_2'] > 0 ? '*' . $pieces['system_2'] : '');
        } elseif ($goods['pieces_retailer'] == $pieces['pieces_level_3']) {
            $goods['specification_retailer'] = end($goodsArr);
        }
        $goods['warning_piece'] = isset($pieces['pieces_level_2']) ? $pieces['pieces_level_2'] : $pieces['pieces_level_1'];
        $pieces['specification'] = end($goodsArr);
        $arr['goods'] = array_merge($goods, $postAttr);
        $arr['pieces'] = $pieces;
        return $arr;
    }

    /**
     * 更新标签
     *
     * @param $model
     * @param $attrs
     */
    private function updateAttrs($model, $attrs)
    {
        //删除所有标签
        $model->attr()->detach();

        $attrArr = [];
        foreach ($attrs as $pid => $id) {
            $id && ($attrArr[$id] = ['attr_pid' => $pid]);
        }
        if (!empty($attrArr)) {
            $model->attr()->sync($attrArr);
        }
    }

    /**
     * 保存没有图片的条形码
     *
     * @param \App\Models\Goods $goods
     * @return bool
     */
    private function saveWithoutImageOfBarCode(Goods $goods)
    {
        $barCode = $goods->bar_code;
        $imagesCount = Images::where('bar_code', $barCode)->count();
        if (!$imagesCount) {
            BarcodeWithoutImages::create(['barcode' => $barCode, 'goods_name' => $goods->name]);
        }
        return true;
    }

    /**
     * 设置导入错误信息
     *
     * @param $msg
     * @param $type
     * @return array
     */
    private function setImportResult($msg, $type = false)
    {
        return [
            'info' => $msg,
            'type' => $type
        ];
    }

    /**
     * 设置图片
     *
     * @param $images
     * @param $name
     * @return bool
     */
    private function _setImages($images, $name)
    {
        $images = (new ImageUploadService($images))->formatImagePost();

        foreach ($images as $item) {
            $image = Images::create(['bar_code' => $name]);
            if ($image->exists) {
                $file = is_array($item) ? $item['path'] : $item;
                $fileName = is_array($item) ? $item['name'] : null;
                $image->associateFile($image->convertToFile($file, $fileName), 'image');
            }
        }
        return true;
    }
}