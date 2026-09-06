<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunicationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = Ticket::forUser($request->user()->id)->withCount(['messages as unread_count' => fn ($q) => $q->where('is_admin', true)->whereNull('read_at')])->with('messages')->latest('updated_at')->paginate(20);
        return response()->json(['data' => collect($items->items())->map(fn ($t) => $this->ticket($t)), 'links' => ['first' => $items->url(1), 'last' => $items->url($items->lastPage()), 'prev' => $items->previousPageUrl(), 'next' => $items->nextPageUrl()], 'meta' => ['current_page' => $items->currentPage(), 'last_page' => $items->lastPage(), 'per_page' => $items->perPage(), 'total' => $items->total(), 'unread_total' => Ticket::forUser($request->user()->id)->whereHas('messages', fn ($q) => $q->where('is_admin', true)->whereNull('read_at'))->withCount(['messages as unread' => fn ($q) => $q->where('is_admin', true)->whereNull('read_at')])->get()->sum('unread')]]);
    }
    public function store(Request $request): JsonResponse { $data = $request->validate(['subject' => ['required', 'string', 'max:255'], 'message' => ['required', 'string', 'max:5000']]); $ticket = Ticket::create(['subject' => $data['subject'], 'user_id' => $request->user()->id, 'status' => 'new']); $ticket->messages()->create(['message' => $data['message'], 'is_admin' => false]); return response()->json(['data' => $this->ticket($ticket->load('messages'))], 201); }
    public function show(Request $request, Ticket $ticket): JsonResponse { $this->authorizeTicket($request, $ticket); $ticket->messages()->where('is_admin', true)->whereNull('read_at')->update(['read_at' => now()]); return response()->json(['data' => $ticket->messages()->oldest()->get()->map(fn ($m) => $this->message($m))]); }
    public function reply(Request $request, Ticket $ticket): JsonResponse { $this->authorizeTicket($request, $ticket); abort_if(($ticket->status?->value ?? $ticket->status) === 'closed', 422, 'Cette conversation est fermée.'); $data = $request->validate(['message' => ['required', 'string', 'max:5000']]); $message = $ticket->messages()->create(['message' => $data['message'], 'is_admin' => false]); $ticket->touch(); return response()->json(['data' => $this->message($message)], 201); }
    public function unread(Request $request): JsonResponse { return response()->json(['data' => ['count' => \App\Models\Message::whereHas('ticket', fn ($q) => $q->where('user_id', $request->user()->id))->where('is_admin', true)->whereNull('read_at')->count()]]); }
    private function authorizeTicket(Request $request, Ticket $ticket): void { abort_unless($ticket->user_id === $request->user()->id, 403); }
    private function ticket(Ticket $t): array { $last = $t->messages->sortByDesc('created_at')->first(); return ['id' => $t->id, 'number' => $t->ticket_number, 'subject' => $t->subject, 'status' => $t->status?->value ?? $t->status, 'unread_count' => (int) ($t->unread_count ?? 0), 'last_message' => $last ? $this->message($last) : null, 'updated_at' => $t->updated_at?->toIso8601String()]; }
    private function message($m): array { return ['id' => $m->id, 'message' => $m->message, 'sender' => $m->is_admin ? 'school' : 'user', 'read_at' => $m->read_at?->toIso8601String(), 'created_at' => $m->created_at?->toIso8601String()]; }
}
