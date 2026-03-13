<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * GET /api/reports/summary — ringkasan angka bulan ini
     */
    public function summary(Request $request): JsonResponse
    {
        $month = $request->get('month', now()->month);
        $year  = $request->get('year',  now()->year);

        return response()->json([
            'total_tickets'    => Ticket::whereMonth('created_at', $month)->whereYear('created_at', $year)->count(),
            'resolved'         => Ticket::whereIn('status', ['Resolved','Closed'])->whereMonth('resolved_at', $month)->whereYear('resolved_at', $year)->count(),
            'avg_resolution_h' => round(
                Ticket::whereIn('status', ['Resolved','Closed'])
                    ->whereMonth('resolved_at', $month)
                    ->whereYear('resolved_at', $year)
                    ->avg('resolution_time_minutes') / 60, 1
            ),
            'sla_score'        => $this->calcOverallSla(),
            'open_tickets'     => Ticket::open()->count(),
            'overdue_tickets'  => Ticket::overdue()->count(),
        ]);
    }

    /**
     * GET /api/reports/tickets — tiket dengan filter
     */
    public function tickets(Request $request): JsonResponse
    {
        $query = Ticket::with(['requester:id,name','assignee:id,name'])
            ->latest();

        if ($request->filled('from'))   $query->whereDate('created_at', '>=', $request->from);
        if ($request->filled('to'))     $query->whereDate('created_at', '<=', $request->to);
        if ($request->filled('status')) $query->where('status', $request->status);

        return response()->json($query->paginate(50));
    }

    /**
     * GET /api/reports/sla — SLA breakdown per priority
     */
    public function sla(): JsonResponse
    {
        $result = [];
        foreach (['Critical','High','Medium','Low'] as $p) {
            $total   = Ticket::where('priority', $p)->whereIn('status', ['Resolved','Closed'])->count();
            $onTime  = Ticket::where('priority', $p)->whereIn('status', ['Resolved','Closed'])->where('sla_breached', false)->count();
            $breached = $total - $onTime;
            $result[$p] = [
                'total'        => $total,
                'on_time'      => $onTime,
                'breached'     => $breached,
                'score'        => $total > 0 ? round(($onTime / $total) * 100) : 100,
            ];
        }
        return response()->json($result);
    }

    /**
     * GET /api/reports/technicians — performa teknisi
     */
    public function technicians(): JsonResponse
    {
        $techs = User::technicians()
            ->withCount([
                'assignedTickets as total_assigned',
                'assignedTickets as total_resolved' => fn($q) => $q->whereIn('status', ['Resolved','Closed']),
                'assignedTickets as sla_met'        => fn($q) => $q->whereIn('status', ['Resolved','Closed'])->where('sla_breached', false),
            ])
            ->addSelect(DB::raw("
                (SELECT ROUND(AVG(resolution_time_minutes) / 60, 1)
                 FROM tickets WHERE assigned_to = users.id
                 AND status IN ('Resolved','Closed')
                ) as avg_hours
            "))
            ->get()
            ->map(fn($u) => [
                'id'            => $u->id,
                'name'          => $u->name,
                'initials'      => $u->initials,
                'color'         => $u->color,
                'role'          => $u->role_display,
                'total_assigned'=> $u->total_assigned,
                'total_resolved'=> $u->total_resolved,
                'sla_met'       => $u->sla_met,
                'sla_score'     => $u->total_resolved > 0 ? round(($u->sla_met / $u->total_resolved) * 100) : 100,
                'avg_hours'     => $u->avg_hours ?? 0,
            ]);

        return response()->json($techs);
    }

    /**
     * GET /api/reports/assets — ringkasan aset
     */
    public function assets(): JsonResponse
    {
        return response()->json([
            'total'              => Asset::count(),
            'active'             => Asset::where('status', 'Active')->count(),
            'maintenance'        => Asset::where('status', 'Maintenance')->count(),
            'inactive'           => Asset::where('status', 'Inactive')->count(),
            'warranty_expired'   => Asset::whereNotNull('warranty_expiry')->whereDate('warranty_expiry', '<', now())->count(),
            'warranty_expiring'  => Asset::whereNotNull('warranty_expiry')->whereDate('warranty_expiry', '>=', now())->whereDate('warranty_expiry', '<=', now()->addDays(30))->count(),
            'by_category'        => Asset::select('category', DB::raw('count(*) as count'))->groupBy('category')->get(),
        ]);
    }

    /**
     * GET /api/reports/export?type=pdf|excel&report=tickets
     * Placeholder — implement dengan laravel-excel atau dompdf
     */
    public function export(Request $request): JsonResponse
    {
        $request->validate([
            'type'   => 'required|in:pdf,excel',
            'report' => 'required|in:tickets,sla,technicians,assets',
        ]);

        // TODO: Implement dengan maatwebsite/excel atau barryvdh/laravel-dompdf
        return response()->json([
            'message'      => 'Export sedang diproses.',
            'download_url' => url("/api/reports/download/{$request->report}.{$request->type}"),
        ]);
    }

    // ── Private ──────────────────────────────────────────────────────────────
    private function calcOverallSla(): int
    {
        $total   = Ticket::whereIn('status', ['Resolved','Closed'])->count();
        $onTime  = Ticket::whereIn('status', ['Resolved','Closed'])->where('sla_breached', false)->count();
        return $total > 0 ? round(($onTime / $total) * 100) : 100;
    }
}
