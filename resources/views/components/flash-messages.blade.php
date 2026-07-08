@if(session('success'))
    <div class="fixed top-24 left-1/2 -translate-x-1/2 z-[60] max-w-lg w-full px-4" id="flash-success">
        <div class="bg-green-500/10 border border-green-500/20 rounded-xl px-5 py-3 flex items-center gap-3 shadow-lg backdrop-blur-xl">
            <span class="material-symbols-outlined text-green-400">check_circle</span>
            <p class="text-green-400 text-sm font-medium">{{ session('success') }}</p>
            <button type="button" onclick="document.getElementById('flash-success').remove()" class="ml-auto text-green-400/60 hover:text-green-400">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </div>
    </div>
@endif

@if(session('error'))
    <div class="fixed top-24 left-1/2 -translate-x-1/2 z-[60] max-w-lg w-full px-4" id="flash-error">
        <div class="bg-red-500/10 border border-red-500/20 rounded-xl px-5 py-3 flex items-center gap-3 shadow-lg backdrop-blur-xl">
            <span class="material-symbols-outlined text-red-400">error</span>
            <p class="text-red-400 text-sm font-medium">{{ session('error') }}</p>
            <button type="button" onclick="document.getElementById('flash-error').remove()" class="ml-auto text-red-400/60 hover:text-red-400">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </div>
    </div>
@endif

@if($errors->any())
    <div class="fixed top-24 left-1/2 -translate-x-1/2 z-[60] max-w-lg w-full px-4" id="flash-validation">
        <div class="bg-amber-500/10 border border-amber-500/20 rounded-xl px-5 py-3 flex items-start gap-3 shadow-lg backdrop-blur-xl">
            <span class="material-symbols-outlined text-amber-400 mt-0.5">warning</span>
            <div class="text-amber-400 text-sm">
                <p class="font-medium mb-1">{{ __('errors.validation_failed') }}</p>
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            <button type="button" onclick="document.getElementById('flash-validation').remove()" class="ml-auto text-amber-400/60 hover:text-amber-400 shrink-0">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </div>
    </div>
@endif
