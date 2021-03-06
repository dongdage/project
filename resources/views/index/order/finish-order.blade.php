@extends('index.index-master')

@section('subtitle' , '提交成功')

@section('container')
    <div class="container dealer-index index shopping-cart">
        <div class="row audit-step-outs">
            <div class="col-sm-3 step ">
                1.查看购物车
                <span></span>
                <span class="triangle-right first"></span>
                <span class="triangle-right last"></span>
            </div>
            <div class="col-sm-3 step">
                2.确认订单消息
                <span class="triangle-right first"></span>
                <span class="triangle-right last"></span>
            </div>
            <div class="col-sm-3 step step-active">
                3.成功提交订单
                <span class="triangle-right first"></span>
                <span class="triangle-right last"></span>
            </div>
            <div class="col-sm-3 step">
                4.等待确认
            </div>
        </div>
        <div class="row table-list-row">
            <div class="col-sm-4 col-sm-offset-4 shopping-finish text-center">
                <p class="order-ok-title">
                    <i class="fa fa-check-circle-o order-ok-icon"></i>
                    订单已提交，请于24小时内完成支付
                </p>

                {{--<div class="operating  pay-way text-left">--}}

                    {{--<p class="text-left title">--}}
                        {{--当前账户余额：<span class="red">¥{{ $userBalance }} &nbsp;</span>--}}
                        {{--@if($userBalance < $orderSumPrice)--}}
                            {{--<span class="red">(余额不足)</span>--}}
                        {{--@endif--}}
                    {{--</p>--}}
                    {{--<label>--}}
                        {{--<input type="radio" {{ $userBalance < $orderSumPrice ? 'disabled' : '' }} name="pay_way"--}}
                               {{--value="balancepay" >--}}
                        {{--<img class="pay-img" src="{{ asset('images/balance.png') }}">--}}
                    {{--</label>--}}
                    {{--<br/> <br/>--}}
                    {{--<p class="text-left title">请选择支付方式：</p>--}}
                    {{--@foreach(cons()->lang('pay_way.online') as $key=> $way)--}}
                        {{--<label>--}}
                            {{--<input type="radio" {{ $key == 'yeepay' ? 'checked' : '' }} name="pay_way"--}}
                                   {{--value="{{ $key }}"/>--}}
                            {{--<img src="{{ asset('images/' . $key  .'.png') }}"/> &nbsp;&nbsp;&nbsp;--}}
                        {{--</label>--}}
                    {{--@endforeach--}}
                {{--</div>--}}

                <p class="finish-operating">
                    {{--<a href="{{ url('yeepay/' . $orderId . ($type == 'all' ? '?type=all' : '')) }}"--}}
                       {{--class="btn btn-danger pay" onclick="showPaySuccess()" target="_blank">前往支付</a>--}}
                    <a href="{{ url('order-buy') }}" class="check-order">查看订单</a>
                </p>
            </div>
        </div>
    </div>
@stop

