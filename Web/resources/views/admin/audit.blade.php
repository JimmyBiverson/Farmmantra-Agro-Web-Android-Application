@extends('layouts.app')
@section('title', 'Audit Logs')
@section('page-title', 'Audit Logs')

@section('content')
<div class="card-full">
    <div class="card-header">
        <h3 class="text-sm font-semibold" style="color:var(--text-primary)">Activity Logs ({{ $logs->total() }})</h3>
    </div>
    <div class="card-body p-0">
        <div class="overflow-x-auto">
            <table class="w-full table-dark">
                <thead>
                    <tr class="border-b" style="border-color:var(--border-color)">
                        <th class="px-4 py-3 text-left">Time</th>
                        <th class="px-4 py-3 text-left">User</th>
                        <th class="px-4 py-3 text-left">Action</th>
                        <th class="px-4 py-3 text-left">Description</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    <tr class="border-b" style="border-color:var(--border-color)">
                        <td class="px-4 py-3 text-sm whitespace-nowrap" style="color:var(--text-muted)">{{ $log->created_at->format('M d, H:i') }}</td>
                        <td class="px-4 py-3 text-sm font-medium" style="color:var(--text-primary)">{{ $log->user?->name ?? 'System' }}</td>
                        <td class="px-4 py-3"><span class="badge badge-primary">{{ $log->action }}</span></td>
                        <td class="px-4 py-3 text-sm" style="color:var(--text-secondary)">{{ $log->description }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-4 py-6 text-center text-sm" style="color:var(--text-muted)">No activity logs found</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="px-4 py-3" style="border-top:1px solid var(--border-color)">{{ $logs->links() }}</div>
</div>
@endsection
