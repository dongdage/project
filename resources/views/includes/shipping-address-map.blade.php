@section('right')
    <div class="modal fade" id="shippingAddressMapModal" tabindex="-1" role="dialog"
         aria-labelledby="shippingAddressMapModalLabel"
         aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                                aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="shippingAddressMapModalLabel">收货人地址<span class="extra-text"></span>
                    </h4>
                </div>

                <div class="modal-body">
                    <div class="shipping-address-info">
                        <span class="prompt">收货地址</span> : <span class="address-of-map"></span>
                        <span class="prompt">联系人</span> : <span class="consigner-of-map"></span>
                        <span class="prompt">联系电话</span> : <span class="phone-of-map"></span>
                    </div>
                    <div id="address-map"
                         style="margin-top:20px;;height: 400px;width:100%;">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">关闭</button>
                </div>
            </div>
        </div>
    </div>
    @parent
@stop
@section('js')
    @parent
    <script type="text/javascript">
        $(function () {
            var shippingAddressMapModal = $('#shippingAddressMapModal');
            shippingAddressMapModal.on('shown.bs.modal', function (e) {
                var mapParent = $(e.relatedTarget),
                        mapXLng = mapParent.data('xLng') || 0,
                        mapYLat = mapParent.data('yLat') || 0,
                        mapAddress = mapParent.data('address') || '',
                        consigner = mapParent.data('consigner') || '',
                        phone = mapParent.data('phone') || '';
                $('.address-of-map').html(mapAddress);
                $('.consigner-of-map').html(consigner);
                $('.phone-of-map').html(phone);
                getShopAddressMap(mapXLng, mapYLat);
            });
        })
    </script>
@stop