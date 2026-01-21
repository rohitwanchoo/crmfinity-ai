<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Connected - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="min-h-screen flex flex-col items-center justify-center p-4">
        <div class="w-full max-w-md">
            <div class="bg-white rounded-2xl shadow-xl p-8 text-center">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Bank Already Connected</h1>
                <p class="text-gray-600 mb-6">Your bank account has already been connected to this application.</p>

                <div class="bg-gray-50 rounded-lg p-4 text-left mb-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="font-medium text-gray-900">{{ $linkRequest->institution_name }}</div>
                            <div class="text-sm text-gray-500">Connected on {{ $linkRequest->completed_at->format('M d, Y') }}</div>
                        </div>
                        <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    @if($linkRequest->accounts_connected)
                    <div class="mt-3 pt-3 border-t border-gray-200">
                        <div class="text-sm text-gray-500">{{ count($linkRequest->accounts_connected) }} account(s) linked</div>
                    </div>
                    @endif
                </div>

                <p class="text-sm text-gray-400">No further action is needed. We'll be in touch about your application.</p>
            </div>

            <div class="text-center mt-8 text-sm text-gray-400">
                <p>{{ config('app.name') }}</p>
            </div>
        </div>
    </div>
</body>
</html>
