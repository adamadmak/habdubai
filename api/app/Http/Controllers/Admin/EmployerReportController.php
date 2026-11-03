<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\EmployerReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The safety desk's reports. Moderators see who reported only when the
 * member agreed to be contacted, and even then never the phone number.
 */
class EmployerReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = EmployerReport::query()->with('member:id,display_name,telegram_username')->latest('id');

        if (($status = $request->query('status', 'open')) !== 'all') {
            $query->where('status', $status);
        }

        $reports = $query->limit(200)->get();

        // How often each employer is named, across every report.
        $names = EmployerReport::query()->get(['employer_name'])->countBy(fn ($r) => mb_strtolower(trim($r->employer_name)));

        return response()->json([
            'data' => $reports->map(fn (EmployerReport $r) => [
                'id' => $r->id,
                'employer_name' => $r->employer_name,
                'times_named' => $names[mb_strtolower(trim($r->employer_name))] ?? 1,
                'text' => $r->text,
                'contactable' => $r->contactable,
                'member' => $r->contactable && $r->member ? ['id' => $r->member->id, 'name' => $r->member->display_name, 'username' => $r->member->telegram_username] : null,
                'status' => $r->status,
                'note' => $r->note,
                'created_at' => $r->created_at?->toIso8601String(),
            ]),
            'counts' => EmployerReport::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function update(Request $request, EmployerReport $report): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'closed'])],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $report->fill($data + ['handled_by' => $request->user()->id])->save();
        AuditLog::record($request->user()->id, 'safety.report_updated', 'employer_report', $report->id, null, ['status' => $data['status']]);

        return response()->json(['ok' => true]);
    }
}
