<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * GET /api/dashboard
     * Stat cards + SLA + recent tickets + technician performance
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // ── Stat cards ────────────────────────────────────────────────────────
        $baseQuery = Ticket::forUser($user);

        $stats = [
            'total_tickets'    => (clone $baseQuery)->count(),
            'open_tickets'     => (clone $baseQuery)->open()->count(),
            'resolved_tickets' => (clone $baseQuery)->whereIn('status', ['Resolved','Closed'])->count(),
            'overdue_tickets'  => (clone $baseQuery)->overdue()->count(),
        ];

        // ── SLA per priority ──────────────────────────────────────────────────
        // Hitung berdasarkan apakah tiket selesai sebelum sla_deadline
        $sla = [];
        foreach (['Critical','High','Medium','Low'] as $priority) {
            $total  = Ticket::where('priority', $priority)
                ->whereIn('status', ['Resolved','Closed'])
                ->count();
            $onTime = Ticket::where('priority', $priority)
                ->whereIn('status', ['Resolved','Closed'])
                ->where('sla_breached', false)
                ->count();
            // Jika belum ada tiket resolved → default 100% (belum ada pelanggaran)
            $sla[$priority] = $total > 0 ? round(($onTime / $total) * 100) : 100;
        }

        // ── Recent tickets ────────────────────────────────────────────────────
        $recentTickets = Ticket::with(['requester:id,name,initials,color', 'assignee:id,name,initials'])
            ->forUser($user)
            ->latest()
            ->limit(8)
            ->get();

        // ── Technician performance ────────────────────────────────────────────
        // Tampilkan semua teknisi (bukan hanya jika user adalah teknisi)
        // super_admin & manager_it bisa melihat semua
        $techPerf = User::technicians()
            ->withCount([
                'assignedTickets as resolved_count' => fn($q) =>
                    $q->whereIn('status', ['Resolved', 'Closed']),
                // Hitung tiket yang selesai SEBELUM deadline SLA (tidak breach)
                'assignedTickets as sla_met_count' => fn($q) =>
                    $q->whereIn('status', ['Resolved', 'Closed'])
                      ->where('sla_breached', false),
            ])
            ->addSelect(DB::raw("
                (SELECT ROUND(AVG(resolution_time_minutes) / 60, 1)
                 FROM tickets
                 WHERE assigned_to = users.id
                 AND status IN ('Resolved','Closed')
                 AND resolution_time_minutes IS NOT NULL
                ) as avg_resolution_hours
            "))
            ->get()
            ->map(fn($t) => [
                'id'                   => $t->id,
                'name'                 => $t->name,
                'initials'             => $t->initials,
                'color'                => $t->color,
                'role'                 => $t->role,
                'resolved_count'       => $t->resolved_count    ?? 0,
                'sla_met_count'        => $t->sla_met_count     ?? 0,
                'avg_resolution_hours' => $t->avg_resolution_hours
                    ? number_format((float)$t->avg_resolution_hours, 1) . 'h'
                    : '—',
                // SLA score: persentase tiket selesai sebelum deadline
                'sla_score'            => $t->resolved_count > 0
                    ? round(($t->sla_met_count / $t->resolved_count) * 100)
                    : null, // null = belum ada tiket resolved (tampil "—" di frontend)
            ]);

        return response()->json([
            'stats'            => $stats,
            'sla'              => $sla,
            'overall_sla'      => round(array_sum($sla) / count($sla)),
            'recent_tickets'   => $recentTickets,
            'tech_performance' => $techPerf,
        ]);
    }

    /**
     * GET /api/dashboard/chart
     * Monthly ticket data + category distribution
     */
    public function chart(Request $request): JsonResponse
    {
        // Monthly data (last 7 months)
        $monthly = collect();
        for ($i = 6; $i >= 0; $i--) {
            $month    = now()->subMonths($i);
            $open     = Ticket::whereYear('created_at',  $month->year)
                ->whereMonth('created_at',  $month->month)
                ->count();
            $resolved = Ticket::whereYear('resolved_at', $month->year)
                ->whereMonth('resolved_at', $month->month)
                ->whereIn('status', ['Resolved', 'Closed'])
                ->count();
            $monthly->push([
                'm' => $month->locale('id')->isoFormat('MMM'),
                'o' => $open,
                'r' => $resolved,
            ]);
        }

        // Category distribution
        $categories = Ticket::select('category', DB::raw('count(*) as count'))
            ->groupBy('category')
            ->orderByDesc('count')
            ->get();

        $colors  = ['#3B8BFF','#8B5CF6','#06B6D4','#10B981','#F59E0B','#EF4444','#F97316','#64748B'];
        $catDist = $categories->values()->map(fn($c, $i) => [
            'label' => $c->category,
            'count' => $c->count,
            'color' => $colors[$i % count($colors)],
        ]);

        return response()->json([
            'monthly'              => $monthly,
            'category_distribution'=> $catDist,
        ]);
    }
}