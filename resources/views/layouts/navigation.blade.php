<nav x-data="{ open: false }" class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 transition-colors duration-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">

            <!-- Left: logo + links -->
            <div class="flex items-center">
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2">
                        <div class="w-7 h-7 bg-indigo-600 rounded-md flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3 21h18M3.75 3h16.5M4.5 3v18M19.5 3v18" />
                            </svg>
                        </div>
                        <span class="font-semibold text-sm text-gray-900 dark:text-white tracking-tight">Media App</span>
                    </a>
                </div>

                <div class="hidden space-x-1 sm:ms-8 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Library') }}
                    </x-nav-link>
                    <x-nav-link :href="route('media.upload')" :active="request()->routeIs('media.upload')" wire:navigate>
                        {{ __('Upload') }}
                    </x-nav-link>
                    @can('viewHorizon')
                    <x-nav-link href="/horizon" :active="request()->is('horizon*')">
                        {{ __('Horizon') }}
                    </x-nav-link>
                    @endcan
                </div>
            </div>

            <!-- Right: theme toggle + user menu -->
            <div class="hidden sm:flex sm:items-center sm:gap-3">

                {{-- Theme toggle: auto / light / dark --}}
                <div
                    x-data="{
                        mode: localStorage.getItem('theme') || 'auto',
                        labels: { auto: 'Auto', light: 'Light', dark: 'Dark' },
                        cycle() {
                            const order = ['auto', 'light', 'dark'];
                            this.mode = order[(order.indexOf(this.mode) + 1) % 3];
                            this.apply();
                        },
                        apply() {
                            if (this.mode === 'auto') {
                                localStorage.removeItem('theme');
                                document.documentElement.classList.toggle('dark',
                                    window.matchMedia('(prefers-color-scheme: dark)').matches);
                            } else {
                                localStorage.setItem('theme', this.mode);
                                document.documentElement.classList.toggle('dark', this.mode === 'dark');
                            }
                        }
                    }"
                >
                    <button
                        @click="cycle()"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium
                               bg-gray-100 dark:bg-gray-800
                               text-gray-600 dark:text-gray-300
                               hover:bg-gray-200 dark:hover:bg-gray-700
                               border border-gray-200 dark:border-gray-700
                               transition"
                        :title="'Theme: ' + labels[mode]"
                    >
                        {{-- Sun icon (light) --}}
                        <svg x-show="mode === 'light'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                        </svg>
                        {{-- Moon icon (dark) --}}
                        <svg x-show="mode === 'dark'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                        </svg>
                        {{-- Auto icon --}}
                        <svg x-show="mode === 'auto'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0H3" />
                        </svg>
                        <span x-text="labels[mode]"></span>
                    </button>
                </div>

                {{-- User dropdown --}}
                <x-dropdown align="right" width="48" contentClasses="py-1 bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium
                                       text-gray-700 dark:text-gray-300
                                       bg-gray-100 dark:bg-gray-800
                                       hover:bg-gray-200 dark:hover:bg-gray-700
                                       border border-gray-200 dark:border-gray-700
                                       focus:outline-none transition">
                            <span>{{ Auth::user()->name }}</span>
                            <svg class="w-3.5 h-3.5 opacity-60" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')"
                                onclick="event.preventDefault(); this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open"
                    class="inline-flex items-center justify-center p-2 rounded-md
                           text-gray-400 dark:text-gray-500
                           hover:text-gray-500 dark:hover:text-gray-400
                           hover:bg-gray-100 dark:hover:bg-gray-800
                           focus:outline-none transition">
                    <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden border-t border-gray-200 dark:border-gray-800">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
                {{ __('Library') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('media.upload')" :active="request()->routeIs('media.upload')" wire:navigate>
                {{ __('Upload') }}
            </x-responsive-nav-link>
            @can('viewHorizon')
            <x-responsive-nav-link href="/horizon" :active="request()->is('horizon*')">
                {{ __('Horizon') }}
            </x-responsive-nav-link>
            @endcan
        </div>

        <div class="pt-4 pb-3 border-t border-gray-200 dark:border-gray-800">
            <div class="px-4 mb-3">
                <div class="font-medium text-sm text-gray-800 dark:text-white">{{ Auth::user()->name }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ Auth::user()->email }}</div>
            </div>

            {{-- Mobile theme toggle --}}
            <div
                x-data="{
                    mode: localStorage.getItem('theme') || 'auto',
                    labels: { auto: 'Auto', light: 'Light', dark: 'Dark' },
                    cycle() {
                        const order = ['auto', 'light', 'dark'];
                        this.mode = order[(order.indexOf(this.mode) + 1) % 3];
                        this.apply();
                    },
                    apply() {
                        if (this.mode === 'auto') {
                            localStorage.removeItem('theme');
                            document.documentElement.classList.toggle('dark',
                                window.matchMedia('(prefers-color-scheme: dark)').matches);
                        } else {
                            localStorage.setItem('theme', this.mode);
                            document.documentElement.classList.toggle('dark', this.mode === 'dark');
                        }
                    }
                }"
                class="px-4 mb-2"
            >
                <button @click="cycle()"
                    class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 dark:text-gray-300">
                    <svg x-show="mode === 'light'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                    </svg>
                    <svg x-show="mode === 'dark'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                    </svg>
                    <svg x-show="mode === 'auto'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0H3" />
                    </svg>
                    Theme: <span x-text="labels[mode]"></span>
                </button>
            </div>

            <div class="space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-responsive-nav-link :href="route('logout')"
                        onclick="event.preventDefault(); this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
