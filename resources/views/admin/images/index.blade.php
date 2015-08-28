@extends('admin.master')
@include('includes.treetable')
@section('subtitle' , '图片管理')

@section('right-container')
    <form class="form-horizontal" action="{{ url('admin/images') }}" method="get"
          data-help-class="col-sm-push-2 col-sm-10" data-done-url="{{ url('admin/images') }}">
        <div id="container">
            <div class="form-group">
                <div class="row col-lg-12">
                    <div class="col-sm-2">
                        <select name="cate_level_1" class="address-province form-control">

                        </select>
                    </div>
                    <div class="col-sm-2" id="level2">
                        <select name="cate_level_2" class="address-city form-control">

                        </select>
                    </div>
                    <div class="col-sm-2">
                        <select name="cate_level_3" class="address-district form-control">
                            <option selected="selected" value="0">请选择</option>
                        </select>
                    </div>
                    <div class="col-sm-2">
                    <span class="input-group-btn">
                        <button class="btn btn-primary" type="submit">搜索</button></span>
                    </div>
                </div>
            </div>

            <div class="form-group attr">

            </div>
            <div class="form-group">
                <div class="col-sm-10">
                    @foreach($goodsImage as $id => $image)
                        <div class="row shop-pictures">
                            <div class="col-xs-6 col-sm-4 col-md-3">
                                <div class="thumbnail">
                                    <a aria-label="Close" class="close btn ajax" type="button"  data-url="{{ url('admin/images',[$image->id]) }}"
                                            data-method="delete">
                                        <span aria-hidden="true" type="button">×</span>
                                    </a>
                                    <img alt="" src="{{ $image->image_url }}">
                                    <label class="form-control">{{ $image->image['name'] }}</label>
                                </div>
                            </div>
                            @endforeach
                        </div>
                </div>
            </div>
    </form>
@stop

@section('js')
    @parent
    <script>
        $(function () {
            $('#attr').treetable({expandable: true});
            getCategory(site.api('categories'));
            getAllCategory(site.api('categories'), '{{ isset($search['cate_level_1']) ? $search['cate_level_1'] :0 }}', '{{ isset($search['cate_level_2']) ? $search['cate_level_2'] :0 }}', '{{ isset($search['cate_level_3']) ? $search['cate_level_3'] :0 }}');
            $('select[name="cate_level_1"]').change(function () {
                $('div.attr').html('');
            });

            $('select[name="cate_level_2"] , select[name="cate_level_3"]').change(function () {
                var categoryId = $(this).val() || $('select[name="cate_level_2"]').val();

                $.get(site.api('categories/' + categoryId + '/attrs'), {format: true}, function (data) {
                    var html = '';
                    for (var index in data) {
                        var options = '';
                        html += '<label class="control-label col-sm-1">' + data[index]['name'] + '</label>';
                        html += '<div class="col-sm-2">';
                        html += ' <select name="attrs[' + data[index]['id'] + ']" class="form-control">';
                        for (var i in data[index]['child']) {
                            options += ' <option value="' + data[index]['child'][i]['id'] + '">' + data[index]['child'][i]['name'] + '</option>'
                        }
                        html += options;
                        html += '</select>'
                        html += '</div>'
                    }
                    $('div.attr').html(html);
                }, 'json')
            })
        });
    </script>
@stop