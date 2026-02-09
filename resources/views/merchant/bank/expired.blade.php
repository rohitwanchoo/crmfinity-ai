<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link Expired - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="min-h-screen flex flex-col items-center justify-center p-4">
        <div class="w-full max-w-md">
            <div class="bg-white rounded-2xl shadow-xl p-8 text-center">
                <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Link Expired</h1>
                <p class="text-gray-600 mb-6">This bank connection link has expired. Please contact your representative to request a new link.</p>

                <div class="bg-gray-50 rounded-lg p-4 text-left mb-6">
                    <div class="text-sm text-gray-500">Application for</div>
                    <div class="font-medium text-gray-900">{{ $linkRequest->business_name }}</div>
                    <div class="text-sm text-gray-600 mt-2">Expired on {{ $linkRequest->expires_at->format('M d, Y') }}</div>
                </div>

                <p class="text-sm text-gray-400">If you need assistance, please contact support.</p>
            </div>

            <div class="text-center mt-8 text-sm text-gray-400">
                <p>{{ config('app.name') }}</p>
            </div>
        </div>
    </div>
</body>
</html>
