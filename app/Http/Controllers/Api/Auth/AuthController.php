<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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
                'email' => $user->email,
                'tanggal_lahir' => $user->tanggal_lahir,
                'role' => $user->role,
                'created_at' => $user->created_at
            ], 200);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Unauthorized'
        ], 401);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        // Validasi input
        $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'tanggal_lahir' => 'nullable|date',
            'password' => 'nullable|string|min:6',
        ]);

        // Update data user
        $user->nama_lengkap = $request->nama_lengkap;
        $user->email = $request->email;
        $user->tanggal_lahir = $request->tanggal_lahir;

        // Jika user mengisi form password, maka update password-nya
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Profil berhasil diperbarui',
            'data' => [
                'nama_lengkap' => $user->nama_lengkap,
                'username' => $user->username,
                'email' => $user->email,
                'tanggal_lahir' => $user->tanggal_lahir,
                'role' => $user->role,
                'created_at' => $user->created_at
            ]
        ], 200);
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
