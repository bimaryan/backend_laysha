<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    public function index(Request $request)
    {
        $userId = Auth::guard('sanctum')->id();
        $sessionId = $request->header('X-Session-ID');

        $room = ChatRoom::where(function ($q) use ($userId, $sessionId) {
            if ($userId) {
                $q->where('user_id', $userId);
            } else {
                $q->where('session_id', $sessionId);
            }
        })->with(['messages.replyTo'])->first();

        if (! $room) {
            return response()->json(['status' => 'success', 'data' => [], 'is_locked' => false]);
        }

        $formattedChats = $room->messages->map(function ($msg) {
            return [
                'id' => $msg->id,
                'role' => $msg->sender_type, // 'user', 'ai', 'admin'
                'text' => $msg->message,
                'time' => $msg->created_at->format('H:i'),
                'instruction' => $msg->instruction,
                'replyTo' => $msg->replyTo ? [
                    'id' => $msg->replyTo->id,
                    'text' => $msg->replyTo->message,
                    'role' => $msg->replyTo->sender_type,
                ] : null,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $formattedChats,
            'is_locked' => $room->is_locked,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(['message' => 'required|string']);
        $sessionId = $request->header('X-Session-ID') ?? Str::uuid()->toString();
        $userId = Auth::guard('sanctum')->id();

        // 1. Cari atau Buat Ruangan Chat
        $room = ChatRoom::firstOrCreate(
            ['user_id' => $userId, 'session_id' => $userId ? null : $sessionId],
            ['case_id' => 'UMUM-'.date('Ymd').'-'.strtoupper(Str::random(4))]
        );

        // 2. Simpan Pesan Warga
        $userMsg = ChatMessage::create([
            'chat_room_id' => $room->id,
            'sender_type' => 'user',
            'message' => $request->message,
            'reply_to_id' => $request->reply_to_id ?? null,
        ]);

        if ($room->is_locked) {
            return response()->json(['status' => 'success', 'is_locked' => true, 'session_id' => $sessionId]);
        }

        // 3. Panggil AI (FastAPI)
        try {
            // Tambahkan timeout(30) agar request tidak menggantung jika API LLM merespons lama
            $response = Http::withoutVerifying()->timeout(30)->post('http://127.0.0.1:8222/api/chat', [
                'pesan' => $request->message,
            ]);

            if ($response->successful()) {
                $aiData = $response->json();

                $kategori = $aiData['kategori_prediksi'] ?? 'NON_KDRT';

                // Sesuaikan status darurat (Di FastAPI K5 adalah Darurat/Nyawa Terancam)
                $isEmergency = in_array($kategori, ['K5']);

                // Jika bahaya meningkat, perbarui ID Kasus (cth: UMUM-xxx jadi K5-xxx)
                if ($kategori !== 'NON_KDRT' && $kategori !== 'SAPAAN' && $room->latest_category !== $kategori) {
                    $newCaseId = $kategori.'-'.date('Ymd').'-'.strtoupper(Str::random(4));
                    $room->update(['latest_category' => $kategori, 'case_id' => $newCaseId]);
                }

                if ($isEmergency) {
                    $room->update(['is_locked' => true]);
                }

                // 4. Simpan Pesan AI (AI membalas pesan warga tadi)
                ChatMessage::create([
                    'chat_room_id' => $room->id,
                    'sender_type' => 'ai',
                    'message' => $aiData['balasan_bot'] ?? 'Pesan Anda telah kami terima.',
                    'reply_to_id' => $userMsg->id,
                    'instruction' => $this->getInstruction($kategori),
                ]);

                return response()->json(['status' => 'success', 'session_id' => $sessionId, 'is_locked' => $isEmergency]);
            } else {
                return response()->json(['error' => 'API FastAPI merespon error: '.$response->status()], 500);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Service Down: '.$e->getMessage()], 500);
        }
    }

    // --- TAMBAHAN: Fungsi destroy untuk rute DELETE /chat/history ---
    public function destroy(Request $request)
    {
        $userId = Auth::guard('sanctum')->id();
        $sessionId = $request->header('X-Session-ID');

        $room = ChatRoom::where(function ($q) use ($userId, $sessionId) {
            if ($userId) {
                $q->where('user_id', $userId);
            } else {
                $q->where('session_id', $sessionId);
            }
        })->first();

        if ($room) {
            // Hapus semua pesan di dalam ruangan ini
            $room->messages()->delete();

            // Reset status ruang chat agar seperti baru
            $room->update([
                'is_locked' => false,
                'latest_category' => null,
                'case_id' => 'UMUM-'.date('Ymd').'-'.strtoupper(Str::random(4)),
            ]);

            return response()->json(['status' => 'success', 'message' => 'Riwayat obrolan berhasil dihapus.']);
        }

        return response()->json(['status' => 'error', 'message' => 'Ruangan chat tidak ditemukan.'], 404);
    }
    // ----------------------------------------------------------------

    private function getInstruction($kategori)
    {
        $map = [
            'SAPAAN' => '👋 Bot Menyapa',
            'NON_KDRT' => 'Bukan KDRT (Perasaan sedih, stres, depresi tanpa unsur kekerasan)',
            'K1' => '💡 ARAHAN: Keluhan Ringan (Terkait relasi rumah tangga, belum jelas ada kekerasan)',
            'K2' => '⚠️ PERINGATAN: Kekerasan Verbal / Emosional (Dibentak, dihina, direndahkan, dimaki)',
            'K3' => '⚠️ PERINGATAN: Tekanan Psikologis / Kontrol (Intimidasi, ancaman, pengurungan, larangan)',
            'K4' => '🚨 BAHAYA: Kekerasan Fisik (Dipukul, ditampar, ditendang, didorong, dijambak)',
            'K5' => '🚨 DARURAT NYAWA: Kekerasan Berat / Darurat (Dicekik, diancam dibunuh, pakai senjata, luka parah)',
        ];

        return $map[$kategori] ?? null;
    }
}
