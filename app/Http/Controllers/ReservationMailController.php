<?php

namespace App\Http\Controllers;

use App\Mail\ReservationGuestMail;
use App\Services\Reservations\ReservationMailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReservationMailController extends Controller
{
    public function index(Request $request, ReservationMailService $mail)
    {
        abort_unless($request->user()?->access_role === 'admin', 403);
        $counts = DB::table('reservation_mail_outbox')->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $recent = DB::table('reservation_mail_outbox')->orderByRaw("CASE WHEN status = 'failed' THEN 0 ELSE 1 END")->orderByDesc('created_at')->orderByDesc('id')->limit(20)->get()->map(function ($row) {
            $payload = json_decode($row->payload, true);

            return ['id' => $row->id, 'reference' => $payload['reference'], 'recipient' => $payload['email'],
                'subject' => ReservationGuestMail::heading($row->type), 'status' => $row->status, 'attempts' => $row->attempts,
                'sent_at' => $row->sent_at, 'created_at' => $row->created_at, 'last_error' => $row->last_error];
        });

        return response()->json(['enabled' => (bool) config('reservation_mail.enabled'), 'configured' => $mail->configured(), 'counts' => $counts, 'recent' => $recent]);
    }

    public function retry(Request $request, string $id)
    {
        abort_unless($request->user()?->access_role === 'admin', 403);
        $changed = DB::table('reservation_mail_outbox')->where('id', $id)->where('status', 'failed')->update([
            'status' => 'pending', 'attempts' => 0, 'available_at' => now(), 'last_error' => null, 'updated_at' => now(),
        ]);
        abort_unless($changed, 409, 'Nur endgültig fehlgeschlagene Mails können erneut freigegeben werden. Bitte neu laden.');

        return response()->json(['message' => 'Mail erneut für den Versand vorgemerkt.']);
    }
}
