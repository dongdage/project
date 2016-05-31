<?php
/**
 * Created by PhpStorm.
 * User: Colin
 * Date: 2015/11/6
 * Time: 19:07
 */
namespace App\Services;

use App\Models\Order;
use App\Models\Shop;
use App\Models\SystemTradeInfo;
use Carbon\Carbon;
use DB;
use Gate;

class PayService
{

    /**
     * 支付成功添加交易信息
     *
     * @param $orders
     * @param $amount
     * @param $orderFee
     * @param $tradeNo
     * @param $payType
     * @param string $hmac
     * @param string $chargeId
     * @param int $payStatus
     * @param string $bankCardNo
     * @return bool
     */
    public function addTradeInfo(
        $orders,
        $amount,
        $orderFee,
        $tradeNo,
        $payType,
        $hmac = '',
        $chargeId = '',
        $payStatus = 1,
        $bankCardNo = ''
    ) {
        //更改订单状态
        $result = DB::transaction(function () use (
            $orders,
            $amount,
            $orderFee,
            $tradeNo,
            $payType,
            $hmac,
            $chargeId,
            $payStatus,
            $bankCardNo
        ) {
            //找出所有卖家的帐号
            $shopIds = $orders->pluck('shop_id')->all();
            $shops = Shop::whereIn('id', array_unique($shopIds))->with('user')->get();
            $accountArr = [];
            foreach ($shops as $shop) {
                $accountArr[$shop->id] = $shop->user->user_name;
            }

            $orderConf = cons('order');
            $nowTimestamp = Carbon::now();


            $tradeConf = cons('trade');

            $payType = array_get($tradeConf['pay_type'], $payType, head($tradeConf['pay_type']));

            foreach ($orders as $order) {
                $orderAttr = [
                    'pay_status' => $orderConf['pay_status']['payment_success'],
                    'paid_at' => $nowTimestamp
                ];
                if ($payType == $tradeConf['pay_type']['pos'] || $order->pay_type == cons('pay_type.cod')) {
                    $orderAttr['status'] = $orderConf['status']['finished'];
                    $orderAttr['finished_at'] = $nowTimestamp;
                }

                $order->fill($orderAttr)->save();
                $fee = ($order->price / $amount) * $orderFee;
                $fee = sprintf("%.2f", $fee);
                // 增加易宝支付log
                DB::table('yeepay_log')->insert(
                    [
                        'order_id' => $order->id,
                        'trade_no' => $tradeNo,
                        'amount' => $order->price - $fee,
                        'paid_at' => $nowTimestamp
                    ]
                );

                //增加系统交易信息
                $systemTradeInfoAttr = [
                    'type' => $tradeConf['type']['in'],
                    'pay_type' => $payType,
                    'account' => $accountArr[$order->shop_id],
                    'order_id' => $order->id,
                    'charge_id' => $chargeId,
                    'bank_card_no' => $bankCardNo,
                    'trade_no' => $tradeNo,
                    'pay_status' => $payStatus,
                    'amount' => bcsub($order->price, $fee, 2),
                    'target_fee' => $fee,
                    'trade_currency' => $tradeConf['trade_currency']['rmb'],
                    'callback_type' => 'json',
                    'hmac' => $hmac,
                ];

                // pos机支付和货到付款在线支付直接完成
                if ($payType == $tradeConf['pay_type']['pos'] || $order->pay_type == cons('pay_type.cod')) {
                    $systemTradeInfoAttr['is_finished'] = cons('trade.is_finished.yes');
                    $systemTradeInfoAttr['finished_at'] = $nowTimestamp;

                    //pos机支付成功更新用户余额
                    $shopOwner = $order->shop->user;
                    $shopOwner->balance += bcsub($order->price, $fee, 2);
                    $shopOwner->save();
                    //通知卖家
                    $redisKey = 'push:seller:' . $shopOwner->id;
                    $redisVal = '您的订单:' . $orders->first()->id . ',' . cons()->lang('push_msg.finished');

                    (new RedisService)->setRedis($redisKey, $redisVal);
                }

                SystemTradeInfo::create($systemTradeInfoAttr);
            }
            return 'success';
        });
        return $result === 'success';
    }

    /**
     * 格式化支付宝退款回调数据
     *
     * @param $resultDetails
     * @return array|bool
     */
    public function formatAlipayRefundData($resultDetails)
    {
        if (!is_string($resultDetails)) {
            return false;
        }
        $orderDatas = explode('#', $resultDetails);
        $refundDatas = [];
        foreach ($orderDatas as $order) {
            $order = explode('$', $order)[0];
            $tradeNo = explode('^', $order)[0];
            $amount = explode('^', $order)[1];
            $result = explode('^', $order)[2];
            if ($result === 'SUCCESS') {
                $refundDatas[$tradeNo] = $amount;
            } else {
                info('支付宝退款错误信息： 订单交易号：' . $tradeNo . ';退款金额：' . $amount . ';错误代码:' . $result . '; 错误详情见：https://doc.open.alipay.com/doc2/detail?treeId=66&articleId=103651&docType=1');
            }
        }
        return $refundDatas;
    }

    /**
     * 余额支付
     *
     * @param $field
     * @param $orderId
     * @return bool
     */
    public function balancepay($field, $orderId)
    {
        $orders = Order::where($field, $orderId)->get();
        if (Gate::denies('validate-online-orders', $orders)) {
            return false;
        }

        $totalFee = $orders->sum('price');
        $userBalance = (new UserService())->getUserBalance();

        if ($userBalance['availableBalance'] < $totalFee) {
            return false;
        }
        if (auth()->user()->decrement('balance', $totalFee)) {
            $result = $this->addTradeInfo($orders, $totalFee, 0, '', 'balancepay');
            if (!$result) {
                auth()->user()->increment('balance', $totalFee);
                return false;
            }
            return true;
        }
        return false;
    }

}