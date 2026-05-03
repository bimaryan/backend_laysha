<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function store(Request $request)
    {
        // 1. Validasi input: kita ganti 'email' menjadi 'login' yang bersifat string umum
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $loginInput = $request->input('login');

        // 2. Cek cerdas: Apakah input 'login' ini formatnya email?
        // Jika ya, kolom di DB yang dicari adalah 'email'. Jika tidak, kolomnya 'username'.
        $loginType = filter_var($loginInput, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        // 3. Siapkan array kredensial untuk Auth
        $credentials = [
            $loginType => $loginInput,
            'password' => $request->password,
        ];

        // 4. Coba autentikasi dengan kredensial dinamis tersebut
        if (Auth::attempt($credentials)) {
            $user = Auth::user();

            $user->tokens()->delete();

            $token = $user->createToken('api-token')->plainTextToken;

            return response()->json([
                'status' => 'success',
                'token' => $token,
                'user' => $user,
            ]);
        }

        // Pesan error diubah agar lebih ramah user
        return response()->json([
            'status' => 'error',
            'message' => 'Username/Email atau Password salah.',
        ], 401);
    }
}
