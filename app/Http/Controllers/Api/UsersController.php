<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\UserRequest;
use App\Models\User;
use Cache;
use App\Transformers\UserTransformer;
use App\Models\Image;

class UsersController extends Controller
{
    public function store(UserRequest $request){
        $verifyData = Cache::get($request->verification_key);
        if(!$verifyData){
            return $this->response->error('验证码失效', 422);
        }

        if($request->verification_code !== $verifyData['code']){
            return $this->response->errorUnauthorize('验证码错误');
        }

        $user = User::create([
            'name' => $request->name,
            'phone' => $verifyData['phone'],
            'password' => bcrypt($request->password),
        ]);

        Cache::forget($request->notification_key);

        return $this->response->item($user, new UserTransformer())
            ->setMeta([
                'access_token' => \Auth::guard('api')->fromUser($user),
                'token_type' => 'Bearer',
                'expires_in' => \Auth::guard('api')->factory()->getTTL() * 60
            ])
            ->setStatusCode(201);
    }

    public function me(){
        return $this->response->item($this->user(), new UserTransformer());
    }

    public function update(UserRequest $request)
    {
        $user = $this->user();

        $attributes = $request->only(['name', 'email', 'introduction']);

        if ($request->avatar_image_id) {
            $image = Image::find($request->avatar_image_id);

            $attributes['avatar'] = $image->path;
        }
        $user->update($attributes);

        return $this->response->item($user, new UserTransformer());
    }
}
