<?php

namespace App\Http\Controllers\Auth;

use Germey\Geetest\GeetestCaptcha;
use Illuminate\Foundation\Auth\AuthenticatesAndRegistersUsers;

class AuthController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Registration & Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users, as well as the
    | authentication of existing users. By default, this controller uses
    | a simple trait to add these behaviors. Why don't you explore it?
    |
    */

    use AuthenticatesAndRegistersUsers, GeetestCaptcha;

    /**
     * Create a new authentication controller instance.
     *
     */
    public function __construct()
    {
        $this->middleware('guest', ['except' => 'logout']);
    }

    /**
     * 登录验证
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|\Illuminate\View\View
     */
    public function login()
    {
        app('cookie')->queue('last_handle_time', 0, -2628000);
        return view('auth.login');
    }

    /**
     * 注册
     */
    public function register()
    {
        return view('auth.register-user');
    }

    /**
     *
     */
    public function setPassword()
    {
        $user = session('user');
        if (empty($user)) {
            return redirect(url('auth/register'));
        }
        return view('auth.register-password', ['user' => $user]);
    }

    /**
     * 添加商铺信息
     */
    public function addShop()
    {
        $user = session('user');
        if (empty($user)) {
            return redirect(url('auth/register'));
        }
        return view('auth.register-user-shop', ['user' => $user]);
    }

    /**
     * 注册成功
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\View\View|\Symfony\Component\HttpFoundation\Response
     */
    public function regSuccess()
    {
        if (!session('shop.name')) {
            return $this->error('请先注册', 'auth/register');
        }
        $user = session('user');
        session()->forget('user');
        return view('auth.reg-success', ['user_name' => $user['user_name'], 'user_type' => $user['type']]);
    }

    /**
     * 退出登录
     *
     * @return \WeiHeng\Responses\Apiv1Response
     */
    public function logout()
    {
        auth()->logout();
        return redirect('auth/login');
    }
}
