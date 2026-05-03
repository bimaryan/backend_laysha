<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function getUser(Request $request)
    {
        $user = $request->user();

        if ($user) {
            return response()->json([
                'status' => 'success',
                'nama_lengkap' => $user->nama_lengkap,
                'username' => $user->username,
                'role' => $user->role
            ], 200);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Unauthorized'
        ], 401);
    }

    public function logout(Request $request)
    {
        // Hapus token yang sedang digunakan untuk login saat ini
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Berhasil logout dan token dihapus'
        ], 200);
    }
}
