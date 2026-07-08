@extends('layouts.app')
@section('title', __('admin.dashboard_title'))
@section('content')
<section class="pt-32 pb-16 px-6 max-w-(--spacing-container) mx-auto">
    <h1 class="font-(--font-headline) text-2xl md:text-3xl font-bold text-text-primary mb-8">{{ __('admin.dashboard') }}</h1>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        @foreach([
            ['label' => __('admin.pending_requests'), 'value' => $stats['pending_requests'] ?? 0, 'color' => 'amber'],
            ['label' => __('admin.pending_proofs'), 'value' => $stats['pending_proofs'] ?? 0, 'color' => 'blue'],
            ['label' => __('admin.approved_waiting'), 'value' => $stats['approved_waiting'] ?? 0, 'color' => 'gold'],
            ['label' => __('admin.rejected'), 'value' => $stats['rejected'] ?? 0, 'color' => 'red'],
        ] as $card)
        <div class="bg-bg-card border border-border-default rounded-2xl p-5">
            <p class="text-sm text-text-muted mb-1">{{ $card['label'] }}</p>
            <p class="text-2xl font-bold {{ $card['color'] === 'gold' ? 'text-gold-400' : ($card['color'] === 'amber' ? 'text-amber-400' : ($card['color'] === 'blue' ? 'text-blue-400' : 'text-red-400')) }}">{{ $card['value'] }}</p>
        </div>
        @endforeach
    </div>
    <div class="bg-bg-card border border-border-default rounded-2xl overflow-hidden">
        <div class="p-5 border-b border-border-default flex items-center justify-between">
            <h2 class="font-semibold text-text-primary">{{ __('admin.recent_requests') }}</h2>
            <a href="/admin/requests" class="text-sm text-gold-400 hover:underline">{{ __('admin.view_all') }}</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-bg-section text-text-muted">
                    <tr>
                        <th class="px-5 py-3 font-medium">{{ __('admin.student') }}</th>
                        <th class="px-5 py-3 font-medium">{{ __('admin.course') }}</th>
                        <th class="px-5 py-3 font-medium">{{ __('admin.status') }}</th>
                        <th class="px-5 py-3 font-medium">{{ __('admin.date') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-default">
                    @foreach($recentRequests ?? [] as $req)
                    <tr class="hover:bg-bg-section/50 transition-colors">
                        <td class="px-5 py-3 text-text-primary">{{ $req->student_name ?? '-' }}</td>
                        <td class="px-5 py-3 text-text-secondary">{{ $req->course->title ?? '-' }}</td>
                        <td class="px-5 py-3">
                            @php
                                $reqStatus = $req->status->value ?? '';
                                $reqStatusLabel = __('enum.course_request_status.' . strtolower($reqStatus));
                                $reqStatusLabelFallback = $reqStatusLabel === 'enum.course_request_status.' . strtolower($reqStatus) ? $reqStatus : $reqStatusLabel;
                            @endphp
                            <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $reqStatus === 'APPROVED' ? 'bg-green-500/10 text-green-400' : ($reqStatus === 'REJECTED' ? 'bg-red-500/10 text-red-400' : 'bg-amber-500/10 text-amber-400') }}">{{ $reqStatusLabelFallback }}</span>
                        </td>
                        <td class="px-5 py-3 text-text-muted">{{ $req->created_at?->format('Y-m-d') ?? '-' }}</td>
                    </tr>
                    @endforeach
                    @if(empty($recentRequests ?? []))
                    <tr>
                        <td colspan="4" class="px-5 py-8 text-center text-text-muted">{{ __('admin.no_data') }}</td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
