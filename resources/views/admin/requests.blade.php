@extends('layouts.app')
@section('title', __('admin.requests_title'))
@section('content')
<section class="pt-32 pb-16 px-6 max-w-(--spacing-container) mx-auto">
    <div class="flex items-center justify-between mb-8">
        <h1 class="font-(--font-headline) text-2xl md:text-3xl font-bold text-text-primary">{{ __('admin.course_requests') }}</h1>
        <div class="flex gap-2">
            <select class="bg-bg-card border border-border-default rounded-xl px-3 py-2 text-sm text-text-primary focus:border-gold-400 focus:outline-none">
                <option>{{ __('admin.all_statuses') }}</option>
                <option>{{ __('admin.pending') }}</option>
                <option>{{ __('admin.approved') }}</option>
                <option>{{ __('admin.rejected') }}</option>
            </select>
        </div>
    </div>
    <div class="bg-bg-card border border-border-default rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-bg-section text-text-muted">
                    <tr>
                        <th class="px-5 py-3 font-medium">{{ __('admin.student') }}</th>
                        <th class="px-5 py-3 font-medium">{{ __('admin.contact') }}</th>
                        <th class="px-5 py-3 font-medium">{{ __('admin.city') }}</th>
                        <th class="px-5 py-3 font-medium">{{ __('admin.course') }}</th>
                        <th class="px-5 py-3 font-medium">{{ __('admin.telegram_channel') }}</th>
                        <th class="px-5 py-3 font-medium">{{ __('admin.payment') }}</th>
                        <th class="px-5 py-3 font-medium">{{ __('admin.status') }}</th>
                        <th class="px-5 py-3 font-medium">{{ __('admin.amount') }}</th>
                        <th class="px-5 py-3 font-medium">{{ __('admin.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-default">
                    @foreach($requests ?? [] as $request)
                    @php
                        $status = $request->status->value;
                        $channel = $request->course?->telegramChannel;
                        $hasUsableChannel = $channel && $channel->is_active && ! empty($channel->telegram_url);
                    @endphp
                    <tr class="hover:bg-bg-section/50 transition-colors">
                        <td class="px-5 py-3">
                            <div class="text-text-primary font-medium">{{ $request->student_name }}</div>
                            <div class="text-text-muted text-xs">{{ $request->student_email }}</div>
                        </td>
                        <td class="px-5 py-3 text-text-secondary text-xs">
                            {{ $request->student_phone ?? '-' }}
                        </td>
                        <td class="px-5 py-3 text-text-secondary text-xs">
                            {{ $request->student_city ?? '-' }}
                        </td>
                        <td class="px-5 py-3 text-text-secondary">{{ $request->course->title ?? '-' }}</td>
                        <td class="px-5 py-3 text-xs">
                            @if($hasUsableChannel)
                                <span class="px-2 py-0.5 rounded font-semibold bg-green-500/10 text-green-400">{{ __('admin.telegram_configured') }}</span>
                            @else
                                <span class="px-2 py-0.5 rounded font-semibold bg-amber-500/10 text-amber-400">{{ __('admin.telegram_not_configured') }}</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-xs">
                            @php
                                $methodKey = match (strtoupper((string) $request->payment_method)) {
                                    'FIB' => 'request.method_fib',
                                    'FASTPAY' => 'request.method_fastpay',
                                    'CARD' => 'request.method_card',
                                    default => null,
                                };
                            @endphp
                            <div class="text-text-secondary">
                                {{ $methodKey ? __($methodKey) : ($request->payment_method ?? '-') }}
                            </div>
                            @if($request->latestPaymentProof)
                                @php $proofStatus = $request->latestPaymentProof->status->value; @endphp
                                <span class="mt-1 inline-block px-2 py-0.5 rounded font-semibold {{ $proofStatus === 'APPROVED' ? 'bg-green-500/10 text-green-400' : ($proofStatus === 'REJECTED' ? 'bg-red-500/10 text-red-400' : 'bg-amber-500/10 text-amber-400') }}">
                                    {{ __('admin.proof_status_'.strtolower($proofStatus)) }}
                                </span>
                                <a href="{{ route('admin.payment-proofs.download', $request->latestPaymentProof->id) }}" target="_blank" rel="noopener noreferrer" class="block mt-1 text-gold-400 hover:text-gold-300 inline-flex items-center gap-1">
                                    <span class="material-symbols-outlined text-xs">visibility</span>
                                    {{ __('admin.view_proof') }}
                                </a>
                            @else
                                <span class="text-amber-400">{{ __('admin.no_proof_yet') }}</span>
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            @php
                                $statusLabel = __('enum.course_request_status.' . strtolower($status));
                                $statusLabelFallback = $statusLabel === 'enum.course_request_status.' . strtolower($status) ? $status : $statusLabel;
                            @endphp
                            <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $status === 'APPROVED' ? 'bg-green-500/10 text-green-400' : ($status === 'REJECTED' ? 'bg-red-500/10 text-red-400' : 'bg-amber-500/10 text-amber-400') }}">{{ $statusLabelFallback }}</span>
                        </td>
                        <td class="px-5 py-3 text-text-muted">
                            {{ $request->latestPaymentProof ? number_format($request->latestPaymentProof->amount_iqd) . ' IQD' : '-' }}
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex flex-wrap gap-2 items-start">
                                @if($status === 'PENDING_REVIEW' && $request->latestPaymentProof)
                                <form method="POST" action="{{ route('admin.requests.approve', $request->id) }}" class="inline" onsubmit="return confirm(@json(__('admin.confirm_approve')))">
                                    @csrf
                                    <input type="hidden" name="payment_proof_id" value="{{ $request->latestPaymentProof->id }}">
                                    <button type="submit" class="text-xs btn-primary px-3 py-1.5" data-loading-text="{{ __('messages.processing') }}">
                                        <span class="btn-text">{{ __('admin.approve') }}</span>
                                    </button>
                                </form>
                                @endif

                                @if(in_array($status, ['PENDING_REVIEW', 'PENDING_PAYMENT']))
                                <form method="POST" action="{{ route('admin.requests.reject', $request->id) }}" class="inline" onsubmit="return confirm(@json(__('admin.confirm_reject')))">
                                    @csrf
                                    <div class="flex flex-col gap-2">
                                        <input type="text" name="rejection_reason" required placeholder="{{ __('admin.enter_rejection_reason') }}" class="text-xs bg-bg-input border border-border-default rounded-lg px-2 py-1 text-text-primary placeholder-text-muted focus:border-gold-400 focus:outline-none w-48">
                                        <button type="submit" class="text-xs bg-red-500/10 text-red-400 px-3 py-1.5 rounded hover:bg-red-500/20 transition-colors text-left" data-loading-text="{{ __('messages.processing') }}">
                                            <span class="btn-text">{{ __('admin.reject') }}</span>
                                        </button>
                                    </div>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                    @if(empty($requests ?? []))
                    <tr>
                        <td colspan="9" class="px-5 py-8 text-center text-text-muted">{{ __('admin.no_requests') }}</td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
