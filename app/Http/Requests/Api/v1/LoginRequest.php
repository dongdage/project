<?php

namespace App\Http\Requests\Api\v1;


class LoginRequest extends Request
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'account' => 'required',
            'password' => 'required',
        ];
    }
}
