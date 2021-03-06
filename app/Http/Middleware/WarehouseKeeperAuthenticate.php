<?php

namespace App\Http\Middleware;

use Closure;
use WeiHeng\WarehouseKeeper\WarehouseKeeperGuard;

class WarehouseKeeperAuthenticate
{
    /**
     * The Guard implementation.
     *
     * @var Guard
     */
    protected $auth;

    /**
     * WarehouseKeeperAuthenticate constructor.
     *
     * @param \WeiHeng\WarehouseKeeper\WarehouseKeeperGuard $auth
     */
    public function __construct(WarehouseKeeperGuard $auth)
    {

        $this->auth = $auth;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if ($this->auth->guest()) {
            return response('Unauthorized.', 401);
        } else if ($this->auth->user()->status == cons('status.off')) {
            return response('suspended', 403);
        }
        return $next($request);
    }
}
