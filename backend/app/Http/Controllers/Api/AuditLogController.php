<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::query()
            ->with('user')
            ->when($request->filled('action'), fn ($query) => $query->where('action', (string) $request->string('action')))
            ->latest();

        if ($request->query('format') === 'csv') {
            return response()->streamDownload(function () use ($query): void {
                $output = fopen('php://output', 'w');
                fputcsv($output, ['id', 'action', 'actor_email', 'auditable_type', 'auditable_id', 'ip_address', 'created_at']);
                $query->limit(500)->get()->each(function (AuditLog $log) use ($output): void {
                    fputcsv($output, [
                        $log->id,
                        $log->action,
                        $log->user?->email,
                        $log->auditable_type,
                        $log->auditable_id,
                        $log->ip_address,
                        $log->created_at?->toISOString(),
                    ]);
                });
                fclose($output);
            }, 'tutormatch-audit-log.csv', [
                'Content-Type' => 'text/csv',
            ]);
        }

        $logs = $query->paginate((int) $request->integer('per_page', 20));

        return AuditLogResource::collection($logs);
    }
}
