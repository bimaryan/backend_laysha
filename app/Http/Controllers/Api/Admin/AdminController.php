<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exports\ReportsExport;
use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class AdminController extends Controller
{
    public function getDashboardStats()
    {
        try {
            // 1. Hitung Total User Terdaftar (Warga)
            $totalUsers = User::where('role', '!=', 'admin')->count();

            // 2. Hitung Total Interaksi Chat (Room)
            $totalChats = ChatRoom::count();

            // 3. Ambil Distribusi Kategori (K1-K6, dll)
            // Menghitung berapa banyak room untuk setiap kategori yang ada
            $categoryDistribution = ChatRoom::select('latest_category', DB::raw('count(*) as total'))
                ->whereNotNull('latest_category')
                ->groupBy('latest_category')
                ->pluck('total', 'latest_category')
                ->toArray();

            // 4. Ambil 5 Riwayat Interaksi Terbaru
            $recentReports = ChatRoom::with(['user:id,nama_lengkap', 'messages' => function ($q) {
                $q->latest()->limit(1);
            }])
                ->orderBy('updated_at', 'desc')
                ->limit(5) // <--- INI ADALAH BATASNYA (Limit dari database langsung)
                ->get()
                ->map(function ($room) {
                    $lastMsg = $room->messages->first();

                    // Membersihkan pesan dari tag --REPLY-- jika ada (opsional)
                    $cleanMessage = $lastMsg ? $lastMsg->message : 'Tidak ada pesan';
                    if (str_contains($cleanMessage, '|--REPLY--|')) {
                        $cleanMessage = explode('|--REPLY--|', $cleanMessage)[1] ?? $cleanMessage;
                    }

                    return [
                        'id' => $room->id,
                        'user_id' => $room->user_id,
                        'user' => $room->user,
                        'message' => $cleanMessage,
                        'category' => $room->latest_category ?? 'Umum',
                        'created_at' => $room->updated_at,
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'total_users' => $totalUsers,
                    'total_chats' => $totalChats,
                    'category_distribution' => $categoryDistribution,
                    'recent_reports' => $recentReports,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getAllReports(Request $request)
    {
        $search = $request->query('search');

        $reports = ChatRoom::with(['user:id,nama_lengkap', 'messages' => function ($q) {
            $q->latest()->limit(1);
        }])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    // Gunakan LIKE (Case Insensitive tergantung collation DB)
                    // Jika di Postgres tetap gunakan ILIKE
                    $q->where('case_id', 'LIKE', '%'.$search.'%')
                        ->orWhereHas('user', function ($u) use ($search) {
                            $u->where('nama_lengkap', 'LIKE', '%'.$search.'%');
                        });
                });
            })
            ->orderBy('updated_at', 'desc')
            ->paginate(15)
            ->withQueryString(); // Memastikan param search ikut dalam link paginasi API

        return response()->json(['status' => 'success', 'data' => $reports]);
    }

    public function getReportDetail($id)
    {
        $room = ChatRoom::with(['user', 'messages.replyTo'])->findOrFail($id);

        $formattedThread = $room->messages->map(function ($msg) {
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
            'data' => [
                'room' => $room,
                'thread' => $formattedThread,
                'is_locked' => $room->is_locked,
            ],
        ]);
    }

    public function replyToUser(Request $request, $id)
    {
        $room = ChatRoom::findOrFail($id);

        ChatMessage::create([
            'chat_room_id' => $room->id,
            'sender_type' => 'admin',
            'message' => $request->message,
            'reply_to_id' => $request->reply_to_id,
        ]);

        $room->touch(); // Update jam room

        return response()->json(['status' => 'success']);
    }

    public function closeCase($id)
    {
        ChatRoom::findOrFail($id)->update(['is_locked' => false]);

        return response()->json(['status' => 'success']);
    }

    public function exportReports(Request $request)
    {
        $search = $request->query('search');

        $reports = ChatRoom::with(['user:id,nama_lengkap', 'messages' => function ($q) {
            $q->latest()->limit(1);
        }])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('case_id', 'LIKE', '%'.$search.'%')
                        ->orWhereHas('user', function ($u) use ($search) {
                            $u->where('nama_lengkap', 'LIKE', '%'.$search.'%');
                        });
                });
            })
            ->orderBy('updated_at', 'desc')
            ->get(); // Ambil semua data (tanpa paginate) untuk export

        return Excel::download(new ReportsExport($reports), 'laporan-safetalk-'.now()->format('Y-m-d').'.xlsx');
    }
}
