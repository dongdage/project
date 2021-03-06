<div class="modal fade" id="shopAddressMapModal" tabindex="-1" role="dialog"
     aria-labelledby="shopAddressMapModal" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header choice-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">关闭</button>
                <div class="modal-title forgot-modal-title" id="shippingAddressMapModalLabel">
                    <span>商铺地址</span>
                </div>
            </div>

            <div class="modal-body">
                <div class="shipping-address-info">
                    <div class="model-management-item"><span class="prompt addressName">营业地址</span> : <span
                                class="address-of-map"></span></div>
                    <div class="model-management-item"><span class="prompt">联系人</span> : <span
                                class="consigner-of-map"></span></div>
                    <div class="model-management-item"><span class="prompt">联系电话</span> : <span
                                class="phone-of-map"></span></div>
                </div>
                <div id="address-map" style="">
                </div>
            </div>
        </div>
    </div>
</div>
@section('css')
    @parent
    <style type="text/css">
        #address-map {
            margin-top: 20px;;
            height: 400px;
            width: 100%;
        }
    </style>
@stop
@section('js')
    @parent
    <script type="text/javascript">
        $(function () {
            var shopAddressMapModal = $('#shopAddressMapModal');
            shopAddressMapModal.on('shown.bs.modal', function (e) {
                var mapParent = $(e.relatedTarget),
                        mapXLng = mapParent.data('xLng') || 0,
                        mapYLat = mapParent.data('yLat') || 0,
                        mapAddress = mapParent.data('address') || '',
                        contact_person = mapParent.data('contact_person') || '',
                        phone = mapParent.data('phone') || '';
                $('.address-of-map').html(mapAddress);
                $('.consigner-of-map').html(contact_person);
                $('.phone-of-map').html(phone);
                getShopAddressMap(mapXLng, mapYLat);
            });
        });
    </script>
@stop



