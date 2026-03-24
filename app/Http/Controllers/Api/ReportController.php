<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Project;
use App\Models\Task;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ReportController extends Controller
{
    // ── SLA target ────────────────────────────────────────────────────────────
    private const SLA_TARGET = [
        'Critical' => '4 Jam',
        'High'     => '8 Jam',
        'Medium'   => '24 Jam',
        'Low'      => '72 Jam',
    ];

    // ── Helper: apply common filters (from, to, user_id, status) ke query Ticket ──
    private function applyTicketFilters($query, Request $request)
    {
        if ($request->filled('from'))    $query->whereDate('created_at', '>=', $request->from);
        if ($request->filled('to'))      $query->whereDate('created_at', '<=', $request->to);
        if ($request->filled('status'))  $query->where('status', $request->status);

        if ($request->filled('user_id')) {
            $uid = $request->user_id;
            $query->where(function ($q) use ($uid) {
                $q->where('requester_id', $uid)
                  ->orWhere('assigned_to', $uid);
            });
        }

        return $query;
    }

    /**
     * GET /api/reports/summary
     */
    public function summary(Request $request): JsonResponse
    {
        $month = $request->get('month', now()->month);
        $year  = $request->get('year',  now()->year);

        $base = Ticket::whereMonth('created_at', $month)->whereYear('created_at', $year);
        $base = $this->applyTicketFilters($base, $request);

        $resolvedThisMonth = (clone $base)->whereIn('status', ['Resolved', 'Closed'])->count();

        $avgMinutes = (clone $base)
            ->whereIn('status', ['Resolved', 'Closed'])
            ->whereNotNull('resolution_time_minutes')
            ->avg('resolution_time_minutes') ?? 0;

        $slaQuery  = $this->applyTicketFilters(Ticket::whereIn('status', ['Resolved', 'Closed']), $request);
        $slaTotal  = (clone $slaQuery)->count();
        $slaOnTime = (clone $slaQuery)->where('sla_breached', false)->count();
        $slaScore  = $slaTotal > 0 ? round(($slaOnTime / $slaTotal) * 100) : 100;

        $openQuery = $this->applyTicketFilters(
            Ticket::whereNotIn('status', ['Resolved', 'Closed']),
            (clone $request)->replace(array_merge($request->all(), ['status' => '']))
        );

        return response()->json([
            'total_tickets'   => (clone $base)->count(),
            'resolved'        => $resolvedThisMonth,
            'open'            => (clone $base)->where('status', 'Open')->count(),
            'in_progress'     => (clone $base)->whereIn('status', ['Assigned', 'In Progress', 'Waiting User'])->count(),
            'avg_resolution'  => round($avgMinutes / 60, 1),
            'sla_score'       => $slaScore,
            'open_tickets'    => $openQuery->count(),
            'overdue_tickets' => $this->applyTicketFilters(Ticket::overdue(), $request)->count(),
        ]);
    }

    /**
     * GET /api/reports/tickets?format=json|pdf|excel
     */
    public function tickets(Request $request)
    {
        $query = Ticket::with(['requester:id,name,department', 'assignee:id,name'])->latest();
        $query = $this->applyTicketFilters($query, $request);

        $format = strtolower($request->get('format', 'json'));

        if ($format === 'excel') return $this->exportTicketsExcel($query->get());
        if ($format === 'pdf')   return $this->exportTicketsPdf($query->limit(200)->get());

        $tickets = $query->paginate(20);
        $tickets->getCollection()->transform(fn($t) => [
            'id'            => $t->id,
            'ticket_number' => $t->ticket_number ?? "#{$t->id}",
            'title'         => $t->title,
            'category'      => $t->category,
            'priority'      => $t->priority,
            'status'        => $t->status,
            'requester'     => $t->requester ? ['name' => $t->requester->name, 'department' => $t->requester->department] : null,
            'assignee'      => $t->assignee  ? ['name' => $t->assignee->name] : null,
            'created_at'    => $t->created_at?->toISOString(),
            'resolved_at'   => $t->resolved_at?->toISOString(),
            'sla_deadline'  => $t->sla_deadline?->toISOString(),
            'sla_breached'  => (bool) $t->sla_breached,
        ]);

        return response()->json($tickets);
    }

    /**
     * GET /api/reports/sla?format=json|pdf|excel
     */
    public function sla(Request $request)
    {
        $rows = [];
        foreach (['Critical', 'High', 'Medium', 'Low'] as $p) {
            $base = Ticket::where('priority', $p)->whereIn('status', ['Resolved', 'Closed']);

            if ($request->filled('from'))    $base->whereDate('created_at', '>=', $request->from);
            if ($request->filled('to'))      $base->whereDate('created_at', '<=', $request->to);
            if ($request->filled('user_id')) {
                $uid = $request->user_id;
                $base->where(function ($q) use ($uid) {
                    $q->where('requester_id', $uid)->orWhere('assigned_to', $uid);
                });
            }

            $total    = (clone $base)->count();
            $onTime   = (clone $base)->where('sla_breached', false)->count();
            $breached = $total - $onTime;

            $rows[] = [
                'priority' => $p,
                'target'   => self::SLA_TARGET[$p],
                'total'    => $total,
                'on_time'  => $onTime,
                'breached' => $breached,
                'achieved' => $total > 0 ? round(($onTime / $total) * 100) : 100,
            ];
        }

        $format = strtolower($request->get('format', 'json'));
        if ($format === 'excel') return $this->exportSlaExcel($rows);
        if ($format === 'pdf')   return $this->exportSlaPdf($rows);

        return response()->json($rows);
    }

    /**
     * GET /api/reports/technicians?format=json|pdf|excel
     */
    public function technicians(Request $request)
    {
        $techQuery = User::technicians();

        if ($request->filled('user_id')) {
            $techQuery->where('id', $request->user_id);
        }

        $techs = $techQuery->get()->map(function ($u) use ($request) {
            $base = Ticket::where('assigned_to', $u->id);

            if ($request->filled('from')) $base->whereDate('created_at', '>=', $request->from);
            if ($request->filled('to'))   $base->whereDate('created_at', '<=', $request->to);

            $totalAssigned = (clone $base)->count();
            $totalResolved = (clone $base)->whereIn('status', ['Resolved', 'Closed'])->count();
            $slaMet        = (clone $base)->whereIn('status', ['Resolved', 'Closed'])->where('sla_breached', false)->count();

            $avgMinutes = (clone $base)
                ->whereIn('status', ['Resolved', 'Closed'])
                ->whereNotNull('resolution_time_minutes')
                ->avg('resolution_time_minutes') ?? 0;

            return [
                'name'           => $u->name,
                'role'           => $u->role_display ?? 'IT Support',
                'total_assigned' => $totalAssigned,
                'resolved_count' => $totalResolved,
                'sla_met'        => $slaMet,
                'sla_score'      => $totalResolved > 0 ? round(($slaMet / $totalResolved) * 100) : 100,
                'avg_hours'      => round($avgMinutes / 60, 1),
            ];
        });

        $format = strtolower($request->get('format', 'json'));
        if ($format === 'excel') return $this->exportTechExcel($techs->toArray());
        if ($format === 'pdf')   return $this->exportTechPdf($techs->toArray());

        return response()->json($techs);
    }

    /**
     * GET /api/reports/assets?format=json|pdf|excel
     */
    public function assets(Request $request)
    {
        $query = Asset::orderBy('category')->orderBy('name');

        if ($request->filled('from'))   $query->whereDate('purchase_date', '>=', $request->from);
        if ($request->filled('to'))     $query->whereDate('purchase_date', '<=', $request->to);
        if ($request->filled('status')) $query->where('status', $request->status);

        $format = strtolower($request->get('format', 'json'));
        $assets = $query->get();

        if ($format === 'excel') return $this->exportAssetsExcel($assets);
        if ($format === 'pdf')   return $this->exportAssetsPdf($assets);

        $data = $assets->map(fn($a) => [
            'asset_number'    => $a->asset_number,
            'name'            => $a->name,
            'category'        => $a->category,
            'brand'           => $a->brand,
            'model'           => $a->model,
            'serial_number'   => $a->serial_number,
            'status'          => $a->status,
            'location'        => $a->location,
            'user'            => $a->user,
            'warranty_expiry' => $a->warranty_expiry?->format('d/m/Y'),
            'purchase_date'   => $a->purchase_date?->format('d/m/Y'),
        ])->values();

        return response()->json([
            'total'            => $assets->count(),
            'active'           => $assets->where('status', 'Active')->count(),
            'maintenance'      => $assets->where('status', 'Maintenance')->count(),
            'inactive'         => $assets->where('status', 'Inactive')->count(),
            'warranty_expired' => $assets->filter(fn($a) => $a->warranty_expiry && now()->gt($a->warranty_expiry))->count(),
            'by_category'      => $assets->groupBy('category')->map->count(),
            'data'             => $data,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // EXCEL EXPORTS
    // ══════════════════════════════════════════════════════════════════════════

    private function buildSpreadsheet(array $headers, array $rows, string $title): Spreadsheet
    {
        $ss    = new Spreadsheet();
        $sheet = $ss->getActiveSheet()->setTitle($title);

        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers)) . '1');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 13, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);

        $sheet->setCellValue('A2', 'Digenerate: ' . now()->format('d M Y H:i'));
        $sheet->mergeCells('A2:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers)) . '2');
        $sheet->getStyle('A2')->applyFromArray([
            'font'      => ['italic' => true, 'size' => 9, 'color' => ['argb' => 'FF888888']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        foreach ($headers as $colIdx => $label) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1);
            $sheet->setCellValue("{$colLetter}4", $label);
            $sheet->getStyle("{$colLetter}4")->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2563EB']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCCE0FF']]],
            ]);
            $sheet->getColumnDimensionByColumn($colIdx + 1)->setAutoSize(true);
        }

        foreach ($rows as $rIdx => $row) {
            $rowNum = $rIdx + 5;
            $bg     = $rIdx % 2 === 0 ? 'FFF8FAFF' : 'FFFFFFFF';
            foreach (array_values($row) as $cIdx => $val) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx + 1);
                $sheet->setCellValue("{$colLetter}{$rowNum}", $val ?? '');
                $sheet->getStyle("{$colLetter}{$rowNum}")->applyFromArray([
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bg]],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFDDDDDD']]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);
            }
        }

        return $ss;
    }

    private function streamExcel(Spreadsheet $ss, string $filename): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->streamDownload(function () use ($ss) {
            $writer = new Xlsx($ss);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    private function exportTicketsExcel($tickets)
    {
        $headers = ['No.', 'No. Tiket', 'Judul', 'Kategori', 'Prioritas', 'Status', 'Reporter', 'Departemen', 'Assigned To', 'SLA Deadline', 'Dibuat', 'Diselesaikan', 'SLA Breached'];
        $rows = $tickets->values()->map(fn($t, $i) => [
            $i + 1,
            $t->ticket_number ?? "#$t->id",
            $t->title,
            $t->category,
            $t->priority,
            $t->status,
            $t->requester?->name ?? '—',
            $t->requester?->department ?? '—',
            $t->assignee?->name ?? 'Unassigned',
            $t->sla_deadline?->format('d/m/Y H:i') ?? '—',
            $t->created_at->format('d/m/Y H:i'),
            $t->resolved_at?->format('d/m/Y H:i') ?? '—',
            $t->sla_breached ? 'Ya' : 'Tidak',
        ])->toArray();

        $ss = $this->buildSpreadsheet($headers, $rows, 'Laporan Tiket');
        return $this->streamExcel($ss, 'tickets-report.xlsx');
    }

    private function exportSlaExcel(array $rows)
    {
        $headers = ['Prioritas', 'Target SLA', 'Total Tiket', 'Tepat Waktu', 'Terlambat', 'Pencapaian (%)'];
        $data = array_map(fn($r) => [
            $r['priority'], $r['target'], $r['total'], $r['on_time'], $r['breached'], $r['achieved'] . '%',
        ], $rows);

        $ss = $this->buildSpreadsheet($headers, $data, 'SLA Performance Report');
        return $this->streamExcel($ss, 'sla-report.xlsx');
    }

    private function exportTechExcel(array $rows)
    {
        $headers = ['Nama Teknisi', 'Role', 'Total Ditugaskan', 'Diselesaikan', 'SLA Terpenuhi', 'SLA Score (%)', 'Avg Waktu (jam)'];
        $data = array_map(fn($r) => [
            $r['name'], $r['role'], $r['total_assigned'], $r['resolved_count'],
            $r['sla_met'], $r['sla_score'] . '%', $r['avg_hours'],
        ], $rows);

        $ss = $this->buildSpreadsheet($headers, $data, 'Laporan Kinerja Teknisi');
        return $this->streamExcel($ss, 'technicians-report.xlsx');
    }

    private function exportAssetsExcel($assets)
    {
        $headers = ['No.', 'No. Aset', 'Nama', 'Kategori', 'Brand / Model', 'Serial Number', 'Status', 'Lokasi', 'Pengguna', 'Tgl Beli', 'Garansi s/d', 'Harga Beli'];
        $rows = $assets->values()->map(fn($a, $i) => [
            $i + 1,
            $a->asset_number,
            $a->name,
            $a->category,
            trim("{$a->brand} {$a->model}"),
            $a->serial_number,
            $a->status,
            $a->location,
            $a->user ?? '—',
            $a->purchase_date?->format('d/m/Y') ?? '—',
            $a->warranty_expiry?->format('d/m/Y') ?? '—',
            $a->purchase_price ? 'Rp ' . number_format($a->purchase_price, 0, ',', '.') : '—',
        ])->toArray();

        $ss = $this->buildSpreadsheet($headers, $rows, 'Inventaris Aset IT');
        return $this->streamExcel($ss, 'assets-report.xlsx');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PDF EXPORTS
    // ══════════════════════════════════════════════════════════════════════════

    private function exportTicketsPdf($tickets)
    {
        $html = $this->pdfWrapper('Laporan Tiket', now()->format('d M Y H:i'), '
            <table>
                <thead>
                    <tr>
                        <th>No. Tiket</th><th>Judul</th><th>Kategori</th>
                        <th>Prioritas</th><th>Status</th><th>Reporter</th>
                        <th>Assigned</th><th>Dibuat</th><th>SLA</th>
                    </tr>
                </thead>
                <tbody>' .
            $tickets->map(fn($t) => "
                    <tr>
                        <td style=\"font-family:monospace\">{$t->ticket_number}</td>
                        <td>{$t->title}</td>
                        <td>{$t->category}</td>
                        <td class=\"pri-{$t->priority}\">{$t->priority}</td>
                        <td>{$t->status}</td>
                        <td>{$t->requester?->name}</td>
                        <td>{$t->assignee?->name}</td>
                        <td>{$t->created_at->format('d/m/Y')}</td>
                        <td>" . ($t->sla_breached ? '<span class="breached">✗</span>' : '<span class="ok">✓</span>') . "</td>
                    </tr>"
            )->implode('') . '
                </tbody>
            </table>
        ');

        return Pdf::loadHTML($html)->setPaper('a4', 'landscape')->download('tickets-report.pdf');
    }

    private function exportSlaPdf(array $rows)
    {
        $html = $this->pdfWrapper('SLA Performance Report', now()->format('d M Y H:i'), '
            <table>
                <thead>
                    <tr>
                        <th>Prioritas</th><th>Target SLA</th><th>Total</th>
                        <th>Tepat Waktu</th><th>Terlambat</th><th>Pencapaian</th>
                    </tr>
                </thead>
                <tbody>' .
            implode('', array_map(fn($r) => "
                    <tr>
                        <td class=\"pri-{$r['priority']}\">{$r['priority']}</td>
                        <td>{$r['target']}</td>
                        <td>{$r['total']}</td>
                        <td style=\"color:#10b981\">{$r['on_time']}</td>
                        <td style=\"color:#ef4444\">{$r['breached']}</td>
                        <td><strong>{$r['achieved']}%</strong></td>
                    </tr>", $rows)) . '
                </tbody>
            </table>
        ');

        return Pdf::loadHTML($html)->setPaper('a4')->download('sla-report.pdf');
    }

    private function exportTechPdf(array $rows)
    {
        $html = $this->pdfWrapper('Laporan Kinerja Teknisi', now()->format('d M Y H:i'), '
            <table>
                <thead>
                    <tr>
                        <th>Nama Teknisi</th><th>Role</th><th>Ditugaskan</th>
                        <th>Diselesaikan</th><th>SLA Terpenuhi</th><th>SLA Score</th><th>Avg Waktu</th>
                    </tr>
                </thead>
                <tbody>' .
            implode('', array_map(fn($r) => "
                    <tr>
                        <td><strong>{$r['name']}</strong></td>
                        <td>{$r['role']}</td>
                        <td>{$r['total_assigned']}</td>
                        <td style=\"color:#10b981\">{$r['resolved_count']}</td>
                        <td>{$r['sla_met']}</td>
                        <td><strong>{$r['sla_score']}%</strong></td>
                        <td>{$r['avg_hours']} jam</td>
                    </tr>", $rows)) . '
                </tbody>
            </table>
        ');

        return Pdf::loadHTML($html)->setPaper('a4')->download('technicians-report.pdf');
    }

    private function exportAssetsPdf($assets)
    {
        $html = $this->pdfWrapper('Inventaris Aset IT', now()->format('d M Y H:i'), '
            <table>
                <thead>
                    <tr>
                        <th>No. Aset</th><th>Nama</th><th>Kategori</th><th>Brand/Model</th>
                        <th>Serial</th><th>Status</th><th>Lokasi</th><th>Garansi s/d</th>
                    </tr>
                </thead>
                <tbody>' .
            $assets->map(fn($a) => "
                    <tr>
                        <td style=\"font-family:monospace\">{$a->asset_number}</td>
                        <td>{$a->name}</td>
                        <td>{$a->category}</td>
                        <td>{$a->brand} {$a->model}</td>
                        <td style=\"font-family:monospace\">{$a->serial_number}</td>
                        <td>{$a->status}</td>
                        <td>{$a->location}</td>
                        <td>" . ($a->warranty_expiry?->format('d/m/Y') ?? '—') . "</td>
                    </tr>"
            )->implode('') . '
                </tbody>
            </table>
        ');

        return Pdf::loadHTML($html)->setPaper('a4', 'landscape')->download('assets-report.pdf');
    }

    private function pdfWrapper(string $title, string $generated, string $body): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                * { margin:0; padding:0; box-sizing:border-box; }
                body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size:9px; color:#1e293b; }
                .header { background:#1e3a5f; color:#fff; padding:16px 20px; margin-bottom:16px; }
                .header h1 { font-size:16px; font-weight:700; letter-spacing:0.5px; }
                .header .sub { font-size:8px; color:#93c5fd; margin-top:3px; }
                table { width:100%; border-collapse:collapse; margin:0 20px; width:calc(100% - 40px); }
                thead tr { background:#2563eb; color:#fff; }
                th { padding:7px 8px; text-align:left; font-size:8px; font-weight:600; text-transform:uppercase; letter-spacing:0.4px; border:1px solid #1d4ed8; }
                td { padding:6px 8px; border:1px solid #e2e8f0; vertical-align:middle; }
                tbody tr:nth-child(even) { background:#f8faff; }
                .footer { position:fixed; bottom:10px; left:20px; right:20px; font-size:7px; color:#94a3b8; border-top:1px solid #e2e8f0; padding-top:5px; display:flex; justify-content:space-between; }
                .pri-Critical { color:#dc2626; font-weight:700; }
                .pri-High     { color:#ea580c; font-weight:700; }
                .pri-Medium   { color:#d97706; }
                .pri-Low      { color:#16a34a; }
                .ok      { color:#10b981; font-weight:700; }
                .breached{ color:#ef4444; font-weight:700; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>$title</h1>
                <div class="sub">IT Support Management System &nbsp;·&nbsp; Digenerate: $generated</div>
            </div>
            $body
            <div class="footer">
                <span>IT Support Management System</span>
                <span>$title — $generated</span>
                <span>Halaman <span class="pagenum"></span></span>
            </div>
        </body>
        </html>
        HTML;
    }

    private function calcOverallSla(): int
    {
        $total  = Ticket::whereIn('status', ['Resolved', 'Closed'])->count();
        $onTime = Ticket::whereIn('status', ['Resolved', 'Closed'])->where('sla_breached', false)->count();
        return $total > 0 ? round(($onTime / $total) * 100) : 100;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PROJECT REPORTS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * GET /api/project-reports/summary
     */
    public function summaryproject(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = $this->getProjectsForUser($user);

        if ($request->filled('from'))     $query->whereDate('created_at', '>=', $request->input('from'));
        if ($request->filled('to'))       $query->where('created_at', '<=', $request->input('to'));
        if ($request->filled('status'))   $query->where('status', $request->input('status'));
        if ($request->filled('priority')) $query->where('priority', $request->input('priority'));

        $projects   = $query->get();
        $projectIds = $projects->pluck('id');

        // ✅ FIX: hitung task via pivot task_assignees, bukan assigned_to
        $totalTasks = Task::whereIn('project_id', $projectIds)->count();

        $completedTasks = Task::whereIn('project_id', $projectIds)
            ->whereHas('column', fn($q) => $q->where('name', 'Prod'))
            ->count();

        $avgProgress = $projects->count() > 0
            ? round($projects->avg('task_stats.progress') ?? 0)
            : 0;

        return response()->json([
            'total_projects'  => $projects->count(),
            'active_projects' => $projects->where('status', 'active')->count(),
            'total_tasks'     => $totalTasks,
            'completed_tasks' => $completedTasks,
            'avg_progress'    => $avgProgress,
        ]);
    }

    /**
     * GET /api/project-reports/projects
     */
    public function projects(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = $this->getProjectsForUser($user)
            ->with([
                'creator:id,name',
                'members:id,name',
            ])
            ->withCount('tasks');

        if ($request->filled('from'))     $query->where('created_at', '>=', $request->input('from'));
        if ($request->filled('to'))       $query->where('created_at', '<=', $request->input('to'));
        if ($request->filled('status'))   $query->where('status', $request->input('status'));
        if ($request->filled('priority')) $query->where('priority', $request->input('priority'));

        $projects = $query->latest()->get()->map(function ($project) {
            $columns      = $project->columns()->withCount('tasks')->orderBy('position')->get();
            $totalColumns = $columns->count();
            $totalTasks   = 0;
            $weightedScore = 0.0;

            foreach ($columns as $index => $column) {
                $count = $column->tasks_count ?? 0;
                if ($count === 0) continue;

                $totalTasks    += $count;
                $weight         = $totalColumns > 1 ? ($index / ($totalColumns - 1)) * 100 : 100.0;
                $weightedScore += $count * $weight;
            }

            $lastColumn = $columns->last();
            $completed  = $lastColumn ? ($lastColumn->tasks_count ?? 0) : 0;
            $progress   = $totalTasks > 0 ? (int) round($weightedScore / $totalTasks) : 0;

            return [
                'name'            => $project->name,
                'category'        => $project->category ?? '—',
                'status'          => $project->status,
                'priority'        => $project->priority ?? 'medium',
                'progress'        => min(100, max(0, $progress)),
                'total_tasks'     => $totalTasks,
                'completed_tasks' => $completed,
                'creator_name'    => $project->creator->name ?? '—',
                'members'         => $project->members->pluck('name')->all(),
                // ✅ FIX: start_date selalu null, pakai created_at sebagai tanggal mulai
                'start_date'      => $project->created_at?->locale('id')->isoFormat('D MMM YYYY'),
                'due_date'        => $project->due_date ? \Carbon\Carbon::parse($project->due_date)->locale('id')->isoFormat('D MMM YYYY') : null,
            ];
        });

        if ($request->input('format') === 'excel') {
            return $this->exportExcel($projects, 'project-report');
        }

        return response()->json(['data' => $projects]);
    }

    /**
     * GET /api/project-reports/tasks
     * ✅ FIX: load assignees via pivot task_assignees (many-to-many)
     */
    public function tasks(Request $request): JsonResponse
    {
        $user       = $request->user();
        $projectIds = $this->getProjectsForUser($user)->pluck('id');

        $query = Task::whereIn('project_id', $projectIds)
            ->with([
                'project:id,name',
                'column:id,name',
                // ✅ Gunakan relasi assignees (pivot), bukan assignee (belongs-to)
                'assignees:id,name',
            ]);

        if ($request->filled('from'))     $query->whereDate('created_at', '>=', $request->input('from'));
        if ($request->filled('to'))       $query->whereDate('created_at', '<=', $request->input('to'));
        if ($request->filled('priority')) $query->where('priority', $request->input('priority'));

        // ✅ FIX: filter by user_id via pivot task_assignees
        if ($request->filled('user_id')) {
            $query->whereHas('assignees', fn($q) => $q->where('users.id', $request->input('user_id')));
        }

        $tasks = $query->latest()->get()->map(fn($task) => [
            'project_name'  => $task->project->name ?? '—',
            'task_title'    => $task->title,
            'column_name'   => $task->column->name ?? '—',
            'priority'      => $task->priority,
            // ✅ Tampilkan semua assignee sebagai string gabungan
            'assigned_name' => $task->assignees->isNotEmpty()
                                    ? $task->assignees->pluck('name')->implode(', ')
                                    : 'Unassigned',
            'due_date'      => $task->due_date ? \Carbon\Carbon::parse($task->due_date)->locale('id')->isoFormat('D MMM YYYY') : null,
            'created_at'    => $task->created_at ? \Carbon\Carbon::parse($task->created_at)->locale('id')->isoFormat('D MMM YYYY') : null,
        ]);

        if ($request->input('format') === 'excel') {
            return $this->exportExcel($tasks, 'task-report');
        }

        return response()->json(['data' => $tasks]);
    }

    /**
     * GET /api/project-reports/team-performance
     * ✅ FIX: semua query task pakai pivot task_assignees, bukan assigned_to
     */
    public function teamPerformance(Request $request): JsonResponse
    {
        $user       = $request->user();
        $projectIds = $this->getProjectsForUser($user)->pluck('id');

        // ✅ FIX: cari user yang ada di pivot task_assignees, bukan assigned_to
        $query = User::where(function ($q) use ($projectIds) {
            $q->whereHas('projects', fn($pq) => $pq->whereIn('project_id', $projectIds))
              ->orWhereHas('assignedTasks', fn($tq) => $tq->whereIn('project_id', $projectIds));
        });

        // Filter by user_id jika ada
        if ($request->filled('user_id')) {
            $query->where('id', $request->input('user_id'));
        }

        $users = $query->get()->map(function ($u) use ($projectIds, $request) {
            // ✅ FIX: hitung task via pivot task_assignees
            $baseTask = Task::whereIn('project_id', $projectIds)
                ->whereHas('assignees', fn($q) => $q->where('users.id', $u->id));

            // Terapkan filter tanggal jika ada
            if ($request->filled('from')) $baseTask->whereDate('created_at', '>=', $request->input('from'));
            if ($request->filled('to'))   $baseTask->whereDate('created_at', '<=', $request->input('to'));

            $assignedTasks = (clone $baseTask)->count();

            $completedTasks = (clone $baseTask)
                ->whereHas('column', fn($q) => $q->where('name', 'Prod'))
                ->count();

            $inProgress = (clone $baseTask)
                ->whereHas('column', fn($q) => $q->whereNotIn('name', ['Prod', 'Mulai Project']))
                ->count();

            $completionRate = $assignedTasks > 0
                ? round(($completedTasks / $assignedTasks) * 100)
                : 0;

            $projectsCount = $u->projects()
                ->whereIn('project_id', $projectIds)
                ->distinct('project_id')
                ->count();

            return [
                'name'            => $u->name,
                'role'            => $u->role ?? '—',
                'total_assigned'  => $assignedTasks,
                'completed_tasks' => $completedTasks,
                'in_progress'     => $inProgress,
                'completion_rate' => $completionRate,
                'projects_count'  => $projectsCount,
            ];
        })->filter(fn($u) => $u['total_assigned'] > 0)->values();

        if ($request->input('format') === 'excel') {
            return $this->exportExcel($users, 'team-performance');
        }

        return response()->json(['data' => $users]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * ✅ FIX: Super Admin bisa lihat semua project
     */
    private function getProjectsForUser($user)
    {
        // Super Admin: lihat semua project
        if (in_array($user->role, ['super_admin', 'admin'])) {
            return Project::query();
        }

        // User biasa: hanya project yang dibuat atau yang dia jadi member
        return Project::where(function ($q) use ($user) {
            $q->where('created_by', $user->id)
              ->orWhereHas('members', fn($m) => $m->where('user_id', $user->id));
        });
    }

    private function exportExcel($data, $filename)
    {
        // TODO: Implement Excel export for project reports
        return response()->json($data);
    }
}