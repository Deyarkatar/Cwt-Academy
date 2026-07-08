@extends('layouts.app')
@section('title', __('admin.telegram_access_title'))
@section('content')
<section class="pt-32 pb-16 px-6 max-w-(--spacing-container) mx-auto">
    <div class="flex items-center justify-between mb-8">
        <h1 class="font-(--font-headline) text-2xl md:text-3xl font-bold text-text-primary">{{ __('admin.manual_telegram_access') }}</h1>
        <div class="flex gap-2">
            <select class="bg-bg-card border border-border-default rounded-xl px-3 py-2 text-sm text-text-primary focus:border-gold-400 focus:outline-none">
                <option>{{ __('admin.all_statuses') }}</option>
                <option>{{ __('admin.pending_add') }}</option>
                <option>{{ __('admin.added') }}</option>
                <option>{{ __('admin.revoked') }}</option>
            </select>
        </div>
    </div>
    <div class="bg-bg-card border border-border-default rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-bg-section text-text-muted">
                    <tr>
                        <th class="px-5 py-3 font-medium">{{ __('admin.student') }}</th>
                        <th class="px-5 py-3 font-medium">{{ __('admin.course') }}</th>
                        <th class="px-5 py-3 font-medium">{{ __('admin.status') }}</th>
                        <th class="px-5 py-3 font-medium">{{ __('admin.admin_note') }}</th>
                        <th class="px-5 py-3 font-medium">{{ __('admin.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-default">
                    @foreach($grants ?? [] as $grant)
                    <tr class="hover:bg-bg-section/50 transition-colors">
                        <td class="px-5 py-3">
                            <div class="text-text-primary font-medium">{{ $grant->student_name ?? '-' }}</div>
                            <div class="text-text-muted text-xs">{{ $grant->student_email ?? '' }}</div>
                        </td>
                        <td class="px-5 py-3 text-text-secondary">{{ $grant->course->title ?? '-' }}</td>
                        <td class="px-5 py-3">
                            @php
                                $grantStatus = $grant->status->value ?? '';
                                $grantStatusLabel = __('enum.telegram_access_status.' . strtolower($grantStatus));
                                $grantStatusLabelFallback = $grantStatusLabel === 'enum.telegram_access_status.' . strtolower($grantStatus) ? $grantStatus : $grantStatusLabel;
                            @endphp
                            <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $grantStatus === 'MANUALLY_ADDED' ? 'bg-green-500/10 text-green-400' : ($grantStatus === 'REVOKED' ? 'bg-red-500/10 text-red-400' : 'bg-amber-500/10 text-amber-400') }}">{{ $grantStatusLabelFallback }}</span>
                        </td>
                        <td class="px-5 py-3 text-text-muted max-w-xs truncate">{{ $grant->admin_note ?? '-' }}</td>
                        <td class="px-5 py-3">
                            <div class="flex flex-wrap gap-2 items-start">
                                @if($grantStatus === 'PENDING_MANUAL_ADD')
                                <form method="POST" action="{{ route('admin.telegram.mark_added', $grant->id) }}" class="inline" onsubmit="return confirm('{{ __('admin.confirm_mark_added') }}')">
                                    @csrf
                                    <div class="flex flex-col gap-2">
                                        <input type="text" name="manual_access_reference" placeholder="{{ __('admin.manual_access_reference') }}" class="text-xs bg-bg-input border border-border-default rounded-lg px-2 py-1 text-text-primary placeholder-text-muted focus:border-gold-400 focus:outline-none w-48">
                                        <input type="text" name="admin_note" placeholder="{{ __('admin.enter_admin_note') }}" class="text-xs bg-bg-input border border-border-default rounded-lg px-2 py-1 text-text-primary placeholder-text-muted focus:border-gold-400 focus:outline-none w-48">
                                        <button type="submit" class="text-xs btn-primary px-3 py-1.5 text-left" data-loading-text="{{ __('messages.processing') }}">
                                            <span class="btn-text">{{ __('admin.mark_added') }}</span>
                                        </button>
                                    </div>
                                </form>
                                @endif
                                @if($grantStatus !== 'REVOKED')
                                <form method="POST" action="{{ route('admin.telegram.revoke', $grant->id) }}" class="inline" onsubmit="return confirm('{{ __('admin.confirm_revoke') }}')">
                                    @csrf
                                    <div class="flex flex-col gap-2">
                                        <input type="text" name="revoked_reason" required placeholder="{{ __('admin.enter_revoke_reason') }}" class="text-xs bg-bg-input border border-border-default rounded-lg px-2 py-1 text-text-primary placeholder-text-muted focus:border-gold-400 focus:outline-none w-48">
                                        <button type="submit" class="text-xs bg-red-500/10 text-red-400 px-3 py-1.5 rounded hover:bg-red-500/20 transition-colors text-left" data-loading-text="{{ __('messages.processing') }}">
                                            <span class="btn-text">{{ __('admin.revoke') }}</span>
                                        </button>
                                    </div>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                    @if(empty($grants ?? []))
                    <tr>
                        <td colspan="5" class="px-5 py-8 text-center text-text-muted">{{ __('admin.no_grants') }}</td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
