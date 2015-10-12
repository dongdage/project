<?php
/**
 * Created by PhpStorm.
 * User: Colin
 * Date: 2015/9/10
 * Time: 16:46
 */
namespace App\Http\Controllers\Api\V1;

use DB;
use Illuminate\Http\Request;

class AddressController extends Controller
{

    /**
     * 根据区id 获取街道
     *
     * @param \Illuminate\Http\Request $request
     * @return \WeiHeng\Responses\Apiv1Response
     */
    public function street(Request $request)
    {
        $addressList = DB::table('address')->where('pid', $request->input('pid'))->lists('name', 'id');
        return $this->success($addressList);
    }
}