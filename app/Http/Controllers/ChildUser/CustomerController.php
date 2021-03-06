<?php
/**
 * Created by PhpStorm.
 * User: Colin
 * Date: 2015/12/4
 * Time: 15:31
 */
namespace App\Http\Controllers\ChildUser;

use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request, $userType)
    {
        $type = array_get(cons('user.type'), $userType, head(cons('user.type')));
        $customerUserIds = Order::where('shop_id', child_auth()->user()->shop_id)->lists('user_id')->unique();
        $users = User::whereIn('id', $customerUserIds)->where('type', $type)->with('shop.shopAddress');
        $address = $request->except('name');
        $name = $request->input('name');

        if (!empty(array_filter($address)) || $name) {
            $users->whereHas('shop', function ($query) use ($address, $name) {
                $name && $query->where('name', 'like', '%' . $name . '%');
                $address && $query->ofDeliveryArea(array_filter($address), 'shopAddress');
            });
        }

        return view('child-user.customer.index', [
            'users' => $users->paginate(),
            'data' => $request->all(),
            'type' => $userType,
        ]);
    }
}
