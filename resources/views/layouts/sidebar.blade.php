<!-- Sidebar -->
<div x-show="sidebarOpen || window.innerWidth >= 1024"
     style="position: fixed; left: 0; top: 0; bottom: 0; width: 260px; background-color: #1f2937; z-index: 99998; overflow-y: auto;"
     id="sidebar"
     @click.away="if (window.innerWidth < 1024) sidebarOpen = false">

    <!-- Logo -->
    <div style="padding: 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.1);">
        <a href="{{ route('bankstatement.index') }}" style="display: flex; align-items: center; gap: 0.75rem; text-decoration: none;">
            <div style="width: 2.5rem; height: 2.5rem; background: linear-gradient(135deg, rgb(34, 197, 94), rgb(22, 163, 74)); border-radius: 0.75rem; display: flex; align-items: center; justify-content: center;">
                <svg style="width: 1.5rem; height: 1.5rem; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
            </div>
            <span style="font-size: 1.25rem; font-weight: bold; color: white;">{{ config('app.name', 'CRMfinity') }}</span>
        </a>
    </div>

    <!-- Navigation -->
    <nav style="padding: 1rem 0;">
        <!-- Main Menu -->
        <div style="padding: 0.5rem 1.5rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: rgba(255,255,255,0.6); letter-spacing: 0.05em; margin-top: 0.5rem;">Main Menu</div>

        @php
            $linkStyle = "display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1.5rem; color: rgba(255,255,255,0.9); text-decoration: none; font-size: 0.875rem; font-weight: 500;";
            $activeStyle = "background: rgba(34, 197, 94, 0.2); border-right: 4px solid rgb(34, 197, 94);";
            $iconStyle = "width: 1.25rem; height: 1.25rem; flex-shrink: 0;";
        @endphp

        <!-- Dashboard -->
        <a href="{{ route('dashboard') }}"
           style="{{ $linkStyle }} {{ request()->routeIs('dashboard') ? $activeStyle : '' }}"
           onmouseover="if (!this.classList.contains('active')) this.style.background='rgba(255,255,255,0.1)'"
           onmouseout="if (!this.classList.contains('active')) this.style.background='transparent'">
            <svg style="{{ $iconStyle }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            <span>Dashboard</span>
        </a>

        <!-- Bank Statement Analysis -->
        <a href="{{ route('bankstatement.index') }}"
           style="{{ $linkStyle }} {{ request()->routeIs('bankstatement.*') && !request()->routeIs('bankstatement.lenders') && !request()->routeIs('bankstatement.lender-detail') ? $activeStyle : '' }}"
           onmouseover="if (!this.classList.contains('active')) this.style.background='rgba(255,255,255,0.1)'"
           onmouseout="if (!this.classList.contains('active')) this.style.background='transparent'">
            <svg style="{{ $iconStyle }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span>Bank Statement Analysis</span>
        </a>

        <!-- Applications -->
        <a href="{{ route('applications.index') }}"
           style="{{ $linkStyle }} {{ request()->routeIs('applications.*') ? $activeStyle : '' }}"
           onmouseover="if (!this.classList.contains('active')) this.style.background='rgba(255,255,255,0.1)'"
           onmouseout="if (!this.classList.contains('active')) this.style.background='transparent'">
            <svg style="{{ $iconStyle }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span>Applications</span>
        </a>

        <!-- Lenders & Collectors -->
        <a href="{{ route('bankstatement.lenders') }}"
           style="{{ $linkStyle }} {{ request()->routeIs('bankstatement.lenders') || request()->routeIs('bankstatement.lender-detail') ? $activeStyle : '' }}"
           onmouseover="if (!this.classList.contains('active')) this.style.background='rgba(255,255,255,0.1)'"
           onmouseout="if (!this.classList.contains('active')) this.style.background='transparent'">
            <svg style="{{ $iconStyle }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <span>Lenders & Collectors</span>
        </a>

        <!-- Divider -->
        <div style="margin: 1rem 0; border-top: 1px solid rgba(255,255,255,0.1);"></div>

        <!-- Integrations Section -->
        <div style="padding: 0.5rem 1.5rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: rgba(255,255,255,0.6); letter-spacing: 0.05em;">Integrations</div>

        <!-- Bank Connections -->
        <a href="{{ route('plaid.index') }}"
           style="{{ $linkStyle }} {{ request()->routeIs('plaid.*') ? $activeStyle : '' }}"
           onmouseover="if (!this.classList.contains('active')) this.style.background='rgba(255,255,255,0.1)'"
           onmouseout="if (!this.classList.contains('active')) this.style.background='transparent'">
            <svg style="{{ $iconStyle }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
            </svg>
            <span>Bank Connections</span>
        </a>

        <!-- Divider -->
        <div style="margin: 1rem 0; border-top: 1px solid rgba(255,255,255,0.1);"></div>

        <!-- Settings Section -->
        <div style="padding: 0.5rem 1.5rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: rgba(255,255,255,0.6); letter-spacing: 0.05em;">Settings</div>

        <!-- Configuration -->
        <a href="{{ route('configuration.index') }}"
           style="{{ $linkStyle }} {{ request()->routeIs('configuration.*') ? $activeStyle : '' }}"
           onmouseover="if (!this.classList.contains('active')) this.style.background='rgba(255,255,255,0.1)'"
           onmouseout="if (!this.classList.contains('active')) this.style.background='transparent'">
            <svg style="{{ $iconStyle }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span>Configuration</span>
        </a>

        <!-- API Usage -->
        <a href="{{ route('api-usage.index') }}"
           style="{{ $linkStyle }} {{ request()->routeIs('api-usage.*') ? $activeStyle : '' }}"
           onmouseover="if (!this.classList.contains('active')) this.style.background='rgba(255,255,255,0.1)'"
           onmouseout="if (!this.classList.contains('active')) this.style.background='transparent'">
            <svg style="{{ $iconStyle }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
            <span>API Usage</span>
        </a>

        <!-- API Documentation -->
        <a href="/api/documentation"
           target="_blank"
           style="{{ $linkStyle }} {{ request()->is('api/documentation') ? $activeStyle : '' }}"
           onmouseover="if (!this.classList.contains('active')) this.style.background='rgba(255,255,255,0.1)'"
           onmouseout="if (!this.classList.contains('active')) this.style.background='transparent'">
            <svg style="{{ $iconStyle }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
            </svg>
            <span>API Documentation</span>
        </a>

        <!-- Profile -->
        <a href="{{ route('profile.edit') }}"
           style="{{ $linkStyle }} {{ request()->routeIs('profile.*') ? $activeStyle : '' }}"
           onmouseover="if (!this.classList.contains('active')) this.style.background='rgba(255,255,255,0.1)'"
           onmouseout="if (!this.classList.contains('active')) this.style.background='transparent'">
            <svg style="{{ $iconStyle }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            <span>Profile</span>
        </a>

        <!-- Logout -->
        <form method="POST" action="{{ route('logout') }}" style="margin: 0;">
            @csrf
            <button type="submit"
                    style="{{ $linkStyle }} width: 100%; text-align: left; background: transparent; border: none; cursor: pointer;"
                    onmouseover="this.style.background='rgba(255,255,255,0.1)'"
                    onmouseout="this.style.background='transparent'">
                <svg style="{{ $iconStyle }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                <span>Logout</span>
            </button>
        </form>
    </nav>
</div>

<!-- Mobile Sidebar Overlay -->
<div x-show="sidebarOpen"
     @click="sidebarOpen = false"
     style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 9998;"
     class="lg:hidden"
     x-transition:enter="transition-opacity ease-linear duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition-opacity ease-linear duration-300"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">
</div>
