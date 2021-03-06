@include('includes.timepicker')
@section('body')
    <div class="modal fade" id="couponModal" tabindex="-1" role="dialog" aria-labelledby="couponModalLabel"
         aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">

                <div class="modal-header choice-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">关闭</button>
                    <div class="modal-title forgot-modal-title" id="cropperModalLabel">
                        添加优惠券<span class="extra-text"></span>
                    </div>
                </div>
                <form class="form-horizontal ajax-form"
                      action=""
                      data-help-class="col-sm-push-2 col-sm-10" data-no-loading="true" method="post" autocomplete="off">
                    <div class="modal-body ">
                        <div class="form-group row">
                            <label class="col-sm-2 control-label" for="full">满:</label>

                            <div class="col-sm-10 col-md-6">
                                <input class="form-control" id="full" name="full" placeholder="请输入最低订单金额"
                                       type="text">
                            </div>
                        </div>

                        <div class="form-group row">
                            <label class="col-sm-2 control-label" for="discount">减:</label>

                            <div class="col-sm-10 col-md-6">
                                <input class="form-control" id="discount" name="discount" placeholder="请输入优惠金额"
                                       type="text">
                            </div>
                        </div>

                        <div class="form-group row">
                            <label class="col-sm-2 control-label" for="stock">总量:</label>

                            <div class="col-sm-10 col-md-6">
                                <input class="form-control" id="stock" name="stock"
                                       placeholder="请输入生成总量" type="text">
                            </div>
                        </div>

                        {{--<div class="form-group row">--}}
                        {{--<label class="col-sm-2 control-label" for="start_at">开始日期:</label>--}}

                        {{--<div class="col-sm-10 col-md-6">--}}
                        {{--<input class="form-control datetimepicker" data-format="YYYY-MM-DD" id="start_at" name="start_at"--}}
                        {{--placeholder="请输入开始时间"--}}
                        {{--type="text">--}}
                        {{--</div>--}}
                        {{--</div>--}}
                        <div class="form-group row">
                            <label class="col-sm-2 control-label" for="end_at">结束日期:</label>

                            <div class="col-sm-10 col-md-6">
                                <input class="form-control datetimepicker" id="end_at" data-format="YYYY-MM-DD"
                                       data-min-date="true" name="end_at" placeholder="请输入结束时间"
                                       type="text">
                            </div>
                        </div>


                    </div>
                    <div class="modal-footer middle-footer">
                        <button class="btn btn-success" data-method="post" type="submit">提交
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @parent
@stop

@section('js')
    @parent
    <script>
        $(function () {
            var couponModel = $('#couponModal'),
                form = couponModel.find('form'),
                targetParent = null,
                url = '',
                couponId = 0,
                full = $('input[name="full"]'),
                discount = $('input[name="discount"]'),
                stock = $('input[name="stock"]'),
                //start_at = $('input[name="start_at"]'),
                end_at = $('input[name="end_at"]'),
                submitBtn = $('button[type="submit"]');

            couponModel.on('show.bs.modal', function (e) {
                targetParent = $(e.relatedTarget),
                    url = targetParent.data('url'),
                    couponId = targetParent.data('id');
                if (couponId) {
                    $.get(site.api('personal/coupon/' + couponId), '', function (data) {
                        var coupon = data.coupon;
                        full.val(coupon.full).prop('disabled', true);
                        discount.val(coupon.discount).prop('disabled', true);
                        stock.val(coupon.stock);
                        //start_at.val(coupon.start_at);
                        end_at.val(coupon.end_at);
                        submitBtn.data('method', 'put').data('url', url);
                    }, 'json')
                } else {
                    submitBtn.data('method', 'post').data('url', url);
                }
            }).on('hidden.bs.modal', function () {
                full.val('').prop('disabled', false);
                discount.val('').prop('disabled', false);
                stock.val('');
                end_at.val('');
                submitBtn.data('method', null).data('url', null);
                form.formValidate('reset');
            });

        })
    </script>
@stop