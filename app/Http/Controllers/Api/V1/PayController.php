<?php
/**
 * Created by PhpStorm.
 * User: Colin
 * Date: 2015/9/28
 * Time: 10:05
 */
namespace App\Http\Controllers\Api\V1;

use App\Models\Order;
use App\Models\Shop;
use App\Models\SystemTradeInfo;
use App\Services\RedisService;
use Carbon\Carbon;
use DB;
use Gate;
use Illuminate\Http\Request;
use Pingpp\Charge;
use Pingpp\Pingpp;
use WeiHeng\Yeepay\YeepayClient;

class PayController extends Controller
{
    public function __construct()
    {
        Pingpp::setApiKey('sk_test_ynfvfL4084KSz9eHOSaXf9OC');
    }

    /**
     * 支付
     *
     * @param \Illuminate\Http\Request $request
     * @param $orderId
     * @return \WeiHeng\Responses\Apiv1Response
     */
    public function charge(Request $request, $orderId)
    {
        $type = $request->input('type');
        $field = $type == 'all' ? 'pid' : 'id';
        $orders = Order::where($field, $orderId)->get();
        if ($orders->isEmpty()) {
            return $this->error('获取失败，请重试');
        }

        if (Gate::denies('validate-online-orders', $orders)) {
            return $this->error('获取失败，请重试');
        }
        $orderIds = $orders->pluck('shop_id')->all();

        $orderNames = Shop::whereIn('id', $orderIds)->get()->implode('name', ',');

        //配置extra

        //Pingpp::setApiKey('sk_live_8izjnHmf9mPG4aTOWL0yvbv9');

        $extra = array(
            'product_category' => '1',
            'identity_id' => auth()->id() . '',
            'identity_type' => 2,
            'terminal_type' => 3,
            'terminal_id' => auth()->id() . '',
            'user_ua' => $request->server('HTTP_USER_AGENT'),
            'result_url' => url('api/v1/pay/success-url')
        );
        $charge = Charge::create(
            array(
                'subject' => '成都订百达科技有限公司',
                'body' => $orderNames,
                'amount' => ($orders->pluck('price')->sum()) * 100,   //单位为分
                'order_no' => $orderId,
                'currency' => 'cny',
                'extra' => $extra,
                'channel' => 'yeepay_wap',
                'client_ip' => $request->server('REMOTE_ADDR'),
                'description' => $type,
                'app' => array('id' => 'app_1mH8m59WrrDCHSqb')
            )
        )->__toArray(true);

        return $this->success($charge);
    }

    public function refund($orderId)
    {
        $order = Order::where('user_id', auth()->id())->find($orderId);

        if (Gate::denies('validate-refund-order', $order)) {
            return $this->error('订单不存在或不能退款');
        }
        $tradeInfo = SystemTradeInfo::where('order_id', $orderId)->select([
            'order_id',
            'pay_type',
            'amount',
            'charge_id',
            'trade_no'
        ])->first();

        if (!$tradeInfo) {
            return $this->error('订单不存在或不能退款');
        }

        if ($tradeInfo->pay_type == cons('trade.pay_type.yeepay')) {
            if ($this->_refundByYeepay($tradeInfo)) {
                // 更新订单状态
                $order->fill([
                    'pay_status' => cons('order.pay_status.refund_success'),
                    'refund_at' => Carbon::now()
                ])->save();
                //通知卖家已退款
                $shop = Shop::find($order->shop_id);
                $redisKey = 'push:seller:' . $shop->user->id;
                $redisVal = '您的订单号' . $order->id . ',' . cons()->lang('push_msg.refund');
                RedisService::setRedis($redisKey, $redisVal);
                return $this->success('退款成功');
            } else {
                return $this->error('退款时遇到错误');
            }
        } else {
            $result = $this->_refundByPingxx($tradeInfo);

            if($result) {
                //通知卖家已退款
                $shop = Shop::find($order->shop_id);
                $redisKey = 'push:seller:' . $shop->user->id;
                $redisVal = '您的订单号' . $order->id . ',' . cons()->lang('push_msg.refund');
                RedisService::setRedis($redisKey, $redisVal);
                $this->success('退款成功');
            }else {
                $this->error('退款时遇到错误');
            }
        }

    }

    /**
     * 移动端页面跳转
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function successUrl()
    {
        return redirect('dbdfmcg://pingppwappay?result=success');
    }

    /**
     * Pingxx退款
     *
     * @param $tradeInfo
     * @return bool
     */
    private function _refundByPingxx($tradeInfo)
    {
        $ch = Charge::retrieve($tradeInfo->charge_id);
        $ch->refunds->create(
            array(
                'amount' => $tradeInfo->amount * 100,
                'description' => 'Refund Description',
                'metadata' => ['order_no' => $tradeInfo->order_id]
            )
        );
        return true;
    }

    /**
     * @param $tradeInfo
     * @return bool
     */
    private function _refundByYeepay($tradeInfo)
    {

        $yeepayConf = config('yeepay');

        $params = array(
            'p0_Cmd' => "RefundOrd",
            'p1_MerId' => $yeepayConf['p1_mer_id'],
            'pb_TrxId' => $tradeInfo->trade_no,
            'p3_Amt' => $tradeInfo->amount,
            'p4_Cur' => "CNY",
            'p5_Desc' => 'Refund Description',
        );
        //RefundOrd715201233826542I18.00CNYRefund Description
        $hmac = $this->_getYeepayHamc($params);
        $params['hmac'] = $hmac;

        $yeepayClient = new YeepayClient($yeepayConf['ref_url_online']);
        $pageContents = $yeepayClient->quickPost($yeepayConf['ref_url_online'], $params);

        //$pageContents = "r0_Cmd=RefundOrd\nr1_Code=1\nr2_TrxId=715201233826542I\nr4_Order=yeepay_86263891\nr3_Amt=0.01\nrf_fee=0.0\nr4_Cur=RMB\nhmac=c9b63e32132df69d56b2bdd59b2e1948\n";
        $result = explode("\n", $pageContents);

        return $pageContents && $this->_validateYeepayRefundResult($result);

    }


    /**
     *  获取hamc
     *
     * @param $params
     * @return string
     */
    private function _getYeepayHamc($params)
    {
        //	加入订单查询请求，固定值"QueryOrdDetail"
        $sbOld = $params['p0_Cmd'];
        //	加入商户编号
        $sbOld = $sbOld . $params['p1_MerId'];
        //	加入易宝支付交易流水号
        $sbOld = $sbOld . $params['pb_TrxId'];
        //	加入退款金额
        $sbOld = $sbOld . $params['p3_Amt'];
        //	加入交易币种
        $sbOld = $sbOld . $params['p4_Cur'];
        //	加入退款说明
        $sbOld = $sbOld . $params['p5_Desc'];

        $hmac = HmacMd5($sbOld, config('yeepay.merchant_key'));

        return $hmac;
    }

    private function _validateYeepayRefundResult($result)
    {
        $params = [];
        $hmac = '';
        foreach ($result as $val) {//数组循环
            $val = trim($val);
            if (strlen($val) == 0) {
                continue;
            }
            $aryReturn = explode("=", $val);
            $sKey = $aryReturn[0];
            //$sValue = urldecode($aryReturn[1]);
            $sValue = $aryReturn[1];

            if ($sKey == "r0_Cmd") {                                            //业务类型
                $params['r0_Cmd'] = $sValue;
            } elseif ($sKey == "r1_Code") {                                //退款申请结果
                if ($sValue != 1) {
                    return false;
                }
                $params['r1_Code'] = $sValue;
            } elseif ($sKey == "r2_TrxId") {                    //易宝支付交易流水号
                $params['r2_TrxId'] = $sValue;
            } elseif ($sKey == "r3_Amt") {                      //退款金额
                $params['r3_Amt'] = $sValue;
            } elseif ($sKey == "r4_Cur") {                      //交易币种
                $params['r4_Cur'] = $sValue;
            } elseif ($sKey == "hmac") {                                    //取得签名数据
                $hmac = $sValue;
            }
        }

        return $this->_validateRefundHamc($params, $hmac);
    }

    /**
     * 验证退款hamc
     */
    private function _validateRefundHamc($params, $hmac)
    {
        //进行校验码检查 取得加密前的字符串
        $sbOld = "";
        //加入业务类型
        $sbOld = $sbOld . $params['r0_Cmd'];
        //加入退款申请是否成功
        $sbOld = $sbOld . $params['r1_Code'];
        //加入易宝支付交易流水号
        $sbOld = $sbOld . $params['r2_TrxId'];
        //加入退款金额
        $sbOld = $sbOld . $params['r3_Amt'];
        //加入交易币种
        $sbOld = $sbOld . $params['r4_Cur'];

        $sNewString = HmacMd5($sbOld, config('yeepay.merchant_key'));

        return $sNewString == $hmac;
    }
}