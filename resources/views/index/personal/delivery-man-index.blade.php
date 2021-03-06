@extends('index.manage-master')
@section('subtitle', '个人中心-配送人员')
@include('includes.delivery-man')
@include('includes.renew')

@section('container')
    @include('includes.menu')
    <div class="page-content-wrapper">
        <div class="page-content">
            <div class="row">
                <div class="col-sm-12 path-title">
                    <a href="{{ url('personal/info') }}">个人中心</a> >
                    <span class="second-level"> 配送人员</span>
                </div>
            </div>
            <form action="#" method="post">
                <div class="row coupon">
                    <div class="col-sm-12 table-responsive">
                        <div class="add-coupon">
                            <a class="add btn btn-blue-lighter update-modal" href="javascript:" data-toggle="modal"
                               data-target="#deliveryModal" data-url="{{ url('api/v1/personal/delivery-man') }}">
                                <label>
                                    <span class="fa fa-plus"></span>
                                </label>添加配送人员
                            </a>
                        </div>
                        <table class="table table-bordered table-center public-table">
                            <thead>
                            <tr>
                                <th>姓名</th>
                                <th>联系方式</th>
                                <th>POS机登录名</th>
                                <th>POS机编号</th>
                                {{--<th>过期时间</th>--}}
                                <th>操作</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($deliveryMen as $man)
                                <tr>
                                    <td>
                                        {{ $man->name }}
                                    </td>
                                    <td>
                                        {{ $man->phone }}
                                    </td>
                                    <td>
                                        {{ $man->user_name }}
                                    </td>
                                    <td>
                                        {{ $man->pos_sign }}
                                    </td>
                                    <td>
                                        <div role="group" class="btn-group btn-group-xs">
                                            <a data-toggle="modal"
                                               data-target="#deliveryModal" data-id="{{ $man->id }}"
                                               data-name="{{ $man->name }}" data-phone="{{ $man->phone }}"
                                               data-user-name="{{ $man->user_name }}"
                                               data-pos-sign="{{ $man->pos_sign }}"
                                               data-url="{{ url('api/v1/personal/delivery-man/' . $man->id) }}"
                                               class="edit update-modal">
                                                <i class="iconfont icon-xiugai"></i> 编辑
                                            </a>
                                            {{--@if($man->expire_at)--}}
                                            {{--<a data-target="#expireModal" data-toggle="modal" data-type="delivery"--}}
                                            {{--data-id="{{ $man->id }}">--}}
                                            {{--<i class="iconfont icon-chaopiao"></i>续费</a>--}}
                                            {{--@endif--}}

                                            <a href="javascript:" data-method="put"
                                               data-url="{{ url('api/v1/personal/delivery-man/'. $man->id.'/status')}}"
                                               data-status="{{ $man->status }}"
                                               data-on='<i class="iconfont icon-qiyong"></i> 启用'
                                               data-off='<i class="iconfont icon-jinyong"></i> 禁用'
                                               class="ajax-no-form color-blue">
                                                {!!  $man->status ? '<i class="iconfont icon-jinyong"></i> 禁用' : '<i class="iconfont icon-qiyong"></i> 启用' !!}
                                            </a>

                                            {{--<a data-url="{{ url('api/v1/personal/delivery-man/'. $man->id) }}"
                                               data-method="delete" class="red delete-no-form ajax" href="javascript:"
                                               type="button">
                                                <i class="iconfont icon-shanchu"></i> 删除
                                            </a>--}}
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </form>
        </div>
    </div>
    @parent
@stop
@section('js')
    @parent
    <script type="text/javascript">
        ajaxNoForm();
    </script>
@stop
