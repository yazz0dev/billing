@php
// Define SVG icons
$icons = [
    'logo' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="logo-icon"><path d="M3 3h18v18H3z"></path><path d="M3 9h18"></path><path d="M9 21V9"></path></svg>',
    'home' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="nav-icon"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955a.75.75 0 011.06 0l8.955 8.955M3 11.25V21h5.25V15H15.75v6H21v-9.75M8.25 21h7.5" /></svg>',
    'dashboard' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="nav-icon"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h12A2.25 2.25 0 0020.25 14.25V3M3.75 14.25V21M20.25 14.25V21M3 3h18M3 9h18M9 21V9" /></svg>',
    'products' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="nav-icon"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10.5 11.25h3M12 3v1.5m0 15V21m-6.75-3.75h13.5" /></svg>',
    'billing' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="nav-icon"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" /></svg>',
    'bill_history' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="nav-icon"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>',
    'sun' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="icon-sun" style="display:none;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-6.364-.386l1.591-1.591M3 12h2.25m.386-6.364L5.636 7.136M12 12a2.25 2.25 0 100-4.5 2.25 2.25 0 000 4.5z" /></svg>',
    'moon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="icon-moon"><path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" /></svg>',
    'bell' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="icon-bell"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" /></svg>',
    'user_avatar' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z" /></svg>',
    'chevron_down' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="icon-chevron"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>',
    'logout' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="dropdown-icon"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" /></svg>',
    'menu_toggle' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>',
    'profile' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="dropdown-icon"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg>'
];
@endphp
<div class="topbar">
    <div class="topbar-container">
        <div class="topbar-logo">
            <a href="{{ route('home') }}">
                {!! $icons['logo'] !!}
                <span>{{ explode(' ', config('app.name', 'BillSys'))[0] }}</span>
            </a>
        </div>

        <nav class="topbar-nav">
            <div class="topbar-nav-item">
                <a href="{{ route('home') }}" data-page="home">
                    {!! $icons['home'] !!}<span>Home</span>
                </a>
            </div>

            @auth {{-- Only show these if authenticated --}}
                @if(Auth::user()->hasRole('admin'))
                <div class="topbar-nav-item admin-only" style="display: flex;"> {{-- JS will manage actual display --}}
                    <a href="{{ route('admin.dashboard') }}" data-page="admin-dashboard">
                        {!! $icons['dashboard'] !!}<span>Dashboard</span>
                    </a>
                </div>
                <div class="topbar-nav-item admin-only" style="display: flex;">
                    <a href="{{ route('admin.products.index') }}" data-page="products">
                        {!! $icons['products'] !!}<span>Products</span>
                    </a>
                </div>
                @endif

                @if(Auth::user()->hasAnyRole(['staff', 'admin']))
                <div class="topbar-nav-item staff-only" style="display: flex;">
                    <a href="{{ route('staff.pos') }}" data-page="billing">
                        {!! $icons['billing'] !!}<span>Billing</span>
                    </a>
                </div>
                <div class="topbar-nav-item staff-only" style="display: flex;">
                    <a href="{{ route('staff.bills.view') }}" data-page="bill-history">
                        {!! $icons['bill_history'] !!}<span>Bill History</span>
                    </a>
                </div>
                @endif
            @endauth
        </nav>

        <div class="topbar-actions">
            <button class="theme-toggle" id="themeToggle" title="Toggle dark/light mode">
                {!! $icons['sun'] !!}{!! $icons['moon'] !!}
            </button>
            
            @auth
            <div class="topbar-notifications">
                <button class="notification-btn" id="notificationButton" title="Notifications">
                    {!! $icons['bell'] !!}
                    <span class="notification-badge" id="notificationBadge" style="display: none;">0</span>
                </button>
                <div class="notification-panel" id="notificationPanel">
                    <div id="notificationList"></div>
                </div>
            </div>

            <div class="topbar-user-profile">
                <button class="user-profile-btn" id="userProfileButton">
                    <div class="user-avatar">{!! $icons['user_avatar'] !!}</div>
                    <span class="user-name" id="userName">{{ Auth::user()->username }}</span>
                    {!! $icons['chevron_down'] !!}
                </button>
                <div class="user-dropdown" id="userDropdown">
                    <div class="user-dropdown-header">
                        <span class="user-role" id="userRole">{{ ucfirst(Auth::user()->role) }}</span>
                        <span class="user-email" id="userEmail">{{ Auth::user()->email }}</span>
                    </div>
                    <a href="{{ route('profile.edit') }}" class="dropdown-item">
                         {!! $icons['profile'] !!} Profile
                    </a>
                    <form method="POST" action="{{ route('logout') }}" style="margin:0;">
                        @csrf
                        <a href="{{ route('logout') }}" class="dropdown-item text-danger"
                           onclick="event.preventDefault(); this.closest('form').submit();">
                            {!! $icons['logout'] !!} Logout
                        </a>
                    </form>
                </div>
            </div>
            @endauth
            @guest
                <div class="topbar-nav-item">
                    <a href="{{ route('login') }}">Login</a>
                </div>
                {{-- <div class="topbar-nav-item"><a href="{{ route('register') }}">Register</a></div> --}}
            @endguest
            <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle menu">{!! $icons['menu_toggle'] !!}</button>
        </div>
    </div>
    <div class="mobile-menu" id="mobileMenu"></div>
</div>