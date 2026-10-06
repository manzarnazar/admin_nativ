@extends('installer::install')

@section('step')
    @if($errors->any())
        @foreach($errors->all() as $error)
            <div class="bg-red-100 border-l-4 border-red-500 p-4 mb-3">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm leading-5 text-red-700">
                            {!! $error !!}
                        </p>
                    </div>
                </div>
            </div>
        @endforeach
    @else
        <div class="bg-green-100 border-l-4 border-green-500 p-4 mb-3">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm leading-5 text-green-700">
                        Database connection successful
                    </p>
                </div>
            </div>
        </div>
    @endif
    <p class="pb-3 text-gray-800">The installation of the database and the loading of all the basic data of the application will be carried out.</p>
    <p class="pb-3 text-gray-800">This may take a while, please wait and don't close the page.</p>
    {{-- Processing overlay --}}
    <div id="migration-overlay" style="display:none; position:fixed; inset:0; background:rgba(255,255,255,0.95); z-index:9999; flex-direction:column; align-items:center; justify-content:center; gap:0;">
        <div style="background:#fff; border-radius:16px; box-shadow:0 4px 24px rgba(0,0,0,0.10); padding:2.5rem 3rem; display:flex; flex-direction:column; align-items:center; min-width:320px; max-width:420px; text-align:center;">
            <svg style="width:48px; height:48px; animation:spin 1s linear infinite; color:#6366f1; margin-bottom:1.25rem;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle style="opacity:0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path style="opacity:0.85;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <p style="font-size:1.125rem; font-weight:600; color:#1e293b; margin:0 0 0.5rem;">Running migrations&hellip;</p>
            <p style="font-size:0.875rem; color:#64748b; margin:0 0 0.75rem;">Setting up database tables. Please wait.</p>
            <p style="font-size:0.75rem; color:#94a3b8; margin:0; padding:0.5rem 1rem; background:#f8fafc; border-radius:8px;">⚠️ Do not close or refresh this page</p>
        </div>
        <style>@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }</style>
    </div>

    <form method="post" action="{{ route('install.migrations') }}" id="migration-form">
        @csrf
        <div class="flex justify-end">
            @if($errors->any())
                <x-installer::button type="submit" color="red">
                    Try again
                    <svg class="fill-current w-5 h-5 ml-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                    </svg>
                </x-installer::button>
            @else
                <x-installer::button type="submit" id="migration-submit-btn">
                    Run Migrations
                    <svg class="fill-current w-5 h-5 ml-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                    </svg>
                </x-installer::button>
            @endif
        </div>
    </form>

    <script>
        document.getElementById('migration-form').addEventListener('submit', function () {
            var overlay = document.getElementById('migration-overlay');
            overlay.style.display = 'flex';
            document.getElementById('migration-submit-btn') && (document.getElementById('migration-submit-btn').disabled = true);
        });
    </script>
@endsection
