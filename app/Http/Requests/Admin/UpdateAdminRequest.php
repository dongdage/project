<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Request;

class UpdateAdminRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'user_name'=>'required|max:20|unique:admin',
            'real_name'=>'required|max:32|min:2',
            'password'=>'required|min:8|confirmed',
        ];
    }
}
