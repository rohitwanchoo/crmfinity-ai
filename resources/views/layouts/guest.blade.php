<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>CRMFinity AI - Smart Underwriting Platform</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen flex">
            <!-- Left Side - Branding -->
            <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-primary-500 to-primary-700 relative overflow-hidden">
                <div class="absolute inset-0 bg-[url('data:image/svg+xml,%3Csvg width=\"60\" height=\"60\" viewBox=\"0 0 60 60\" xmlns=\"http://www.w3.org/2000/svg\"%3E%3Cg fill=\"none\" fill-rule=\"evenodd\"%3E%3Cg fill=\"%23ffffff\" fill-opacity=\"0.05\"%3E%3Cpath d=\"M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\"/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')] opacity-20"></div>
                <div class="relative z-10 flex flex-col justify-center items-center w-full px-12 text-white">
                    <div class="w-20 h-20 bg-white/20 rounded-2xl flex items-center justify-center mb-8 backdrop-blur-sm">
                        <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <h1 class="text-4xl font-bold mb-2 text-center">CRMFinity AI</h1>
                    <p class="text-sm font-medium text-white/60 uppercase tracking-widest mb-4 text-center">Smart Underwriting Platform</p>
                    <p class="text-lg text-white/80 text-center max-w-md">Powerful AI-driven underwriting platform for smarter financial decisions.</p>
                    <div class="mt-12 grid grid-cols-3 gap-8 text-center">
                        <div>
                            <div class="text-3xl font-bold">99%</div>
                            <div class="text-sm text-white/70 mt-1">Accuracy</div>
                        </div>
                        <div>
                            <div class="text-3xl font-bold">50K+</div>
                            <div class="text-sm text-white/70 mt-1">Analyses</div>
                        </div>
                        <div>
                            <div class="text-3xl font-bold">24/7</div>
                            <div class="text-sm text-white/70 mt-1">Available</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side - Form -->
            <div class="flex-1 flex flex-col justify-center items-center px-6 py-12 bg-dashboard-bg">
                <div class="w-full max-w-md">
                    <!-- Mobile Logo -->
                    <div class="lg:hidden flex justify-center mb-8">
                        <div class="w-16 h-16 bg-primary-500 rounded-xl flex items-center justify-center">
                            <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                    </div>

                    <!-- Form Card -->
                    <div class="card">
                        <div class="card-body">
                            {{ $slot }}
                        </div>
                    </div>

                    <!-- Footer -->
                    <p class="mt-8 text-center text-sm text-secondary-500">
                        &copy; {{ date('Y') }} {{ config('app.name', 'CRMfinity') }}. All rights reserved.
                    </p>
                </div>
            </div>
        </div>
    </body>
</html>
