@php
    // Check for completed analyses that haven't been notified yet
    try {
        $unnotifiedAnalyses = \App\Models\AnalysisSession::where('user_id', Auth::id())
            ->whereNull('notified_at')
            ->whereNotNull('created_at')
            ->where('created_at', '>', now()->subMinutes(30)) // Only last 30 minutes
            ->orderBy('created_at', 'desc')
            ->limit(10) // Limit to prevent too many
            ->get();
    } catch (\Exception $e) {
        \Log::error('Failed to load unnotified analyses: ' . $e->getMessage());
        $unnotifiedAnalyses = collect(); // Empty collection
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Alpine.js cloak style -->
        <style>
            [x-cloak] { display: none !important; }
        </style>
    </head>
    <body class="font-sans antialiased"
          x-data="{
              sidebarOpen: false,
              showToast: false,
              toastMessage: '',
              toastType: 'success',
              sessionSuccess: '{{ session("success") ? addslashes(session("success")) : "" }}',
              sessionInfo: '{{ session("info") ? addslashes(session("info")) : "" }}'
          }"
          x-init="
              // Show toast for session messages
              setTimeout(() => {
                  if (sessionSuccess) {
                      toastMessage = sessionSuccess;
                      toastType = 'success';
                      showToast = true;
                      const audio = document.getElementById('notification-sound');
                      if (audio) audio.play().catch(e => console.log('Audio failed:', e));
                      setTimeout(() => { showToast = false; }, 5000);
                  } else if (sessionInfo) {
                      toastMessage = sessionInfo;
                      toastType = 'info';
                      showToast = true;
                      const audio = document.getElementById('notification-sound');
                      if (audio) audio.play().catch(e => console.log('Audio failed:', e));
                      setTimeout(() => { showToast = false; }, 5000);
                  }
              }, 300);
          ">

        <!-- Toast Notification -->
        <div x-show="showToast"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 translate-y-2"
             style="position: fixed; top: 80px; right: 20px; z-index: 99999; max-width: 400px; width: calc(100% - 40px);"
             class="lg:w-96">
            <div :class="toastType === 'success' ? 'bg-green-50 border-green-500' : 'bg-blue-50 border-blue-500'"
                 style="border-left: 4px solid; border-radius: 8px; padding: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
                <div style="display: flex; align-items: start; gap: 12px;">
                    <div :class="toastType === 'success' ? 'bg-green-100' : 'bg-blue-100'"
                         style="flex-shrink: 0; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <svg x-show="toastType === 'success'" style="width: 24px; height: 24px; color: rgb(34, 197, 94);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <svg x-show="toastType === 'info'" style="width: 24px; height: 24px; color: rgb(59, 130, 246);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div style="flex: 1;">
                        <h4 :class="toastType === 'success' ? 'text-green-800' : 'text-blue-800'"
                            style="font-weight: 600; font-size: 14px; margin-bottom: 4px;">
                            <span x-show="toastType === 'success'">Processing Started!</span>
                            <span x-show="toastType === 'info'">Notification</span>
                        </h4>
                        <p :class="toastType === 'success' ? 'text-green-700' : 'text-blue-700'"
                           style="font-size: 13px; line-height: 1.5;"
                           x-text="toastMessage"></p>
                    </div>
                    <button @click="showToast = false"
                            :class="toastType === 'success' ? 'text-green-400 hover:text-green-600' : 'text-blue-400 hover:text-blue-600'"
                            style="flex-shrink: 0; background: none; border: none; cursor: pointer;">
                        <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Audio for notification -->
        <audio id="notification-sound" preload="auto">
            <source src="data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBCx+zPDTgjMGHGm98OScTgwPVKzn77BiFwtOqOPxul8fBDGO1vDKdSYEKnXD79l9OAgTWrHq7KdQEgpOouDyu20jBS1/y/DVgzcHHGe58OOaTQsNUqvk7rFhGAU3jtfuy3goBCpzwu/YfDkJFl6z6uqlVRMHQJre8bltJAUsfc3w14I0BxxqvPDjmE0JD1Gp5O+yYRkGNo7U78t3KgUrc8Ps2Hk4ChVZsvLqo1ISCkuc3vO4bSIEK3vL8NaCMwYcabrv45lNCQ1Pp+TvsWEaBTaO1+/LdCkFLXfC7Nh4NwsTWKzy6qJPEQlKmtzzuGsgBCt8yvDVgDEGHWm78OGaSwkOUKfk77FhGgY2j9fuy3YpBSx1wu/YdzcJE1ew8OqiUBEJTJvd8rZqHgQrfcrw04A0Bh1ouPDhmUwJDk6m5O+xYRsGN5DZ7sp1KAUqcsLu1nk2CRRWr+3qolINDk2b3PKzaBsFKnvL8NSAMwYcZ7Tv4ZdOCQ5Rp+XvsWEbBjiQ2O/KcycEKnHD79Z4NQkUVq/t56FRDQtJmdzys2YhBSp7zPLWgjYHHGe07+GWT8ErfMrvz3YnBCxzvunXejUIE1es7OihUBEISJjb8LNmHwYsecvx1oQzBh1nuO/fllBTKHnK7896JQUrcsru13k2CRJWrvHnolIAVq3r56JRDQtKmtzyssLCsrJSUHyc39y0VBMJSpre8bJnHgUsesy03HnVlpKEajEf89S9XwAAa3vI8NeCM0YbZ7nr45lNCQ1Vp+TvsWIbBS6O2O/JdCYFLHTD7th4OAoUWLDx6aFSD01Sm97ysWgiB" type="audio/wav">
        </audio>

        <!-- Sidebar -->
        @include('layouts.sidebar')

        <!-- Top Header -->
        @include('layouts.header')

        <!-- Main Content -->
        <main class="main-content">
            <!-- Page Heading -->
            @isset($header)
                <div class="mb-6">
                    {{ $header }}
                </div>
            @endisset

            <!-- Flash Messages - Hidden, using toast instead -->
            @if (session('error'))
                <div class="alert alert-danger mb-6">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            <!-- Page Content -->
            {{ $slot }}
        </main>

        @stack('scripts')

        <!-- Toast Notification Script -->
        <script>
            // Function to show toast (for completion notifications)
            function showToast(message, type = 'success') {
                if (!document.body.__x) {
                    setTimeout(() => showToast(message, type), 100);
                    return;
                }

                document.body.__x.$data.toastMessage = message;
                document.body.__x.$data.toastType = type;
                document.body.__x.$data.showToast = true;

                const audio = document.getElementById('notification-sound');
                if (audio) audio.play().catch(e => console.log('Audio failed:', e));

                setTimeout(() => {
                    if (document.body.__x && document.body.__x.$data) {
                        document.body.__x.$data.showToast = false;
                    }
                }, 5000);
            }

            // Check for completed analyses on page load
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(() => {

                // Check for completed analyses
                @if($unnotifiedAnalyses->count() > 0)
                    const analyses = @json($unnotifiedAnalyses);
                    const sessionIds = [];

                    analyses.forEach((analysis, index) => {
                        sessionIds.push(analysis.id);

                        setTimeout(() => {
                            const message = analyses.length === 1
                                ? `Analysis complete for "${analysis.filename}"! Click to view results.`
                                : `Analysis ${index + 1} of ${analyses.length} complete: "${analysis.filename}"`;

                            showToast(message, 'success');

                            // Add click handler to redirect to results
                            const toastEl = document.querySelector('[x-show="showToast"]');
                            if (toastEl) {
                                toastEl.style.cursor = 'pointer';
                                toastEl.onclick = function() {
                                    window.location.href = `/bankstatement/results/${analysis.session_id}`;
                                };
                            }
                        }, 500 + (index * 6000)); // Delay between notifications
                    });

                    // Mark analyses as notified
                    fetch('/bankstatement/mark-notified', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ session_ids: sessionIds })
                    }).catch(e => console.error('Failed to mark as notified:', e));
                @endif
            });

            // Make showToast globally available
            window.showToast = showToast;
        </script>
    </body>
</html>
