<!-- Sidebar -->
<aside x-data="{ collapsed: false }"
       :class="{ 'sidebar-collapsed': collapsed }"
       class="sidebar"
       id="sidebar">

    <!-- Logo -->
    <div class="sidebar-logo">
        <a href="{{ route('bankstatement.index') }}" class="flex items-center gap-3">
            <div class="w-10 h-10 bg-primary-500 rounded-xl flex items-center justify-center">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
            </div>
            <span class="text-xl font-bold text-white" x-show="!collapsed">{{ config('app.name', 'CRMfinity') }}</span>
        </a>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
        <!-- Main Menu -->
        <div class="sidebar-menu-title" x-show="!collapsed">Main Menu</div>

        <!-- Dashboard -->
        <a href="{{ route('dashboard') }}"
           class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <svg class="sidebar-link-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            <span x-show="!collapsed">Dashboard</span>
        </a>

        <!-- Bank Statement Analysis -->
        <a href="{{ route('bankstatement.index') }}"
           class="sidebar-link {{ request()->routeIs('bankstatement.*') && !request()->routeIs('bankstatement.lenders') && !request()->routeIs('bankstatement.lender-detail') ? 'active' : '' }}">
            <svg class="sidebar-link-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span x-show="!collapsed">Bank Statement Analysis</span>
        </a>

        <!-- MCA Applications -->
        <a href="{{ route('applications.index') }}"
           class="sidebar-link {{ request()->routeIs('applications.*') ? 'active' : '' }}">
            <svg class="sidebar-link-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span x-show="!collapsed">Applications</span>
        </a>

        <!-- Lenders & Collectors -->
        <a href="{{ route('bankstatement.lenders') }}"
           class="sidebar-link {{ request()->routeIs('bankstatement.lenders') || request()->routeIs('bankstatement.lender-detail') ? 'active' : '' }}">
            <svg class="sidebar-link-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <span x-show="!collapsed">Lenders & Collectors</span>
        </a>

        <!-- Divider -->
        <div class="my-4 border-t border-secondary-700/30"></div>

        <!-- Integrations Section -->
        <div class="sidebar-menu-title" x-show="!collapsed">Integrations</div>

        <!-- Plaid - Bank Connections -->
        <a href="{{ route('plaid.index') }}"
           class="sidebar-link {{ request()->routeIs('plaid.*') ? 'active' : '' }}">
            <svg class="sidebar-link-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
            </svg>
            <span x-show="!collapsed">Bank Connections</span>
        </a>

        <!-- Divider -->
        <div class="my-4 border-t border-secondary-700/30"></div>

        <!-- Settings Section -->
        <div class="sidebar-menu-title" x-show="!collapsed">Settings</div>

        <!-- Configuration -->
        <a href="{{ route('configuration.index') }}"
           class="sidebar-link {{ request()->routeIs('configuration.*') ? 'active' : '' }}">
            <svg class="sidebar-link-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span x-show="!collapsed">Configuration</span>
        </a>

        <!-- API Documentation -->
        <a href="/api/documentation"
           target="_blank"
           class="sidebar-link {{ request()->is('api/documentation') ? 'active' : '' }}">
            <svg class="sidebar-link-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
            </svg>
            <span x-show="!collapsed">API Documentation</span>
        </a>

        <!-- Profile -->
        <a href="{{ route('profile.edit') }}"
           class="sidebar-link {{ request()->routeIs('profile.*') ? 'active' : '' }}">
            <svg class="sidebar-link-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            <span x-show="!collapsed">Profile</span>
        </a>

        <!-- Logout -->
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="sidebar-link w-full text-left">
                <svg class="sidebar-link-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                <span x-show="!collapsed">Logout</span>
            </button>
        </form>
    </nav>
</aside>

<!-- Mobile Sidebar Overlay -->
<div x-cloak
     x-show="sidebarOpen"
     x-transition:enter="transition-opacity ease-linear duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition-opacity ease-linear duration-300"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     @click="sidebarOpen = false"
     class="fixed inset-0 bg-black/50 z-40 lg:hidden">
</div>
