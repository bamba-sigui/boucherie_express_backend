<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    use ApiResponse;

    public function show(Request $request)
    {
        return $this->ok(new UserResource($request->user()->load('addresses')));
    }

    public function update(UpdateProfileRequest $request)
    {
        $user = $request->user();
        $user->update($request->validated());
        return $this->ok(new UserResource($user->fresh()->load('addresses')));
    }

    public function updateFcmToken(Request $request)
    {
        $request->validate(['fcm_token' => 'required|string']);
        $request->user()->update(['fcm_token' => $request->input('fcm_token')]);
        return $this->ok(['saved' => true]);
    }

    public function uploadAvatar(Request $request)
    {
        $request->validate(['avatar' => 'required|image|mimes:jpeg,jpg,png|max:2048']);

        $user = $request->user();
        $disk = config('app.avatar_disk', 'public');

        if ($user->photo_url && str_contains($user->photo_url, '/storage/avatars/')) {
            $oldPath = str_replace(asset('storage') . '/', '', $user->photo_url);
            Storage::disk($disk)->delete($oldPath);
        }

        $path = $request->file('avatar')->store("avatars/{$user->id}", $disk);
        $url = Storage::disk($disk)->url($path);
        $user->update(['photo_url' => $url]);

        return $this->ok(['photoUrl' => $url]);
    }
}
