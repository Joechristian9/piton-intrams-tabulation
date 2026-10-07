<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $page['props']['theme'] ?? 'gold' }}" @if (! empty($page['props']['themeVars'])) style="{{ \App\Support\AppTheme::inlineStyle($page['props']['themeVars']) }}" @endif>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Browser tab / home-screen icons (PITON logo) -->
        <link rel="icon" href="/favicon.ico" sizes="16x16 32x32 48x48">
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <meta name="theme-color" content="#000000">

        {{-- Fonts are bundled by Vite (@fontsource in app.jsx), not loaded from the
             internet, so pages don't stall when the event network is offline. --}}

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
        {{-- Shown while the app's script loads on a full page load, shaped like the page
             being opened; app.css hides it once React renders into #app (pure CSS, so it
             costs nothing after that). Must stay right after #app. Sign-in pages (login,
             landing, account) get a plain dark screen instead. --}}
        @php
            $component = $page['component'] ?? '';
            $signedIn = ! preg_match('#^(Auth/|Welcome$|Profile/)#', $component);
            $variant = str_starts_with($component, 'Judge/Score') ? 'cards'
                : (str_starts_with($component, 'Admin/Results/') ? 'table' : 'default');
        @endphp
        <div class="boot-skeleton fixed inset-0 z-50 flex flex-col bg-neutral-900 md:flex-row" aria-hidden="true">
            @if ($signedIn)
                <div class="hidden w-[60px] shrink-0 flex-col gap-4 bg-neutral-800 p-4 md:flex">
                    <div class="skeleton h-8 w-8 rounded-full"></div>
                    @for ($i = 0; $i < 6; $i++)
                        <div class="skeleton mt-2 h-5 w-5 rounded"></div>
                    @endfor
                </div>
                <div class="flex h-14 shrink-0 items-center justify-between border-b border-neutral-700 bg-neutral-800 px-4 md:hidden">
                    <div class="skeleton h-7 w-40 rounded-md"></div>
                    <div class="skeleton h-8 w-8 rounded-md"></div>
                </div>
                <div class="min-w-0 flex-1 overflow-hidden p-4 md:p-8">
                    @if ($variant === 'cards')
                        <div class="skeleton mx-auto h-8 w-56 rounded-md"></div>
                        <div class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                            @for ($i = 0; $i < 5; $i++)
                                <div class="{{ $i > 1 ? 'hidden sm:flex' : 'flex' }} flex-col items-center gap-3 rounded-xl border border-neutral-800 p-4">
                                    <div class="skeleton h-72 w-full rounded-md"></div>
                                    <div class="skeleton h-5 w-3/4 rounded-md"></div>
                                    <div class="skeleton h-10 w-28 rounded-full"></div>
                                </div>
                            @endfor
                        </div>
                    @elseif ($variant === 'table')
                        <div class="skeleton h-8 w-64 rounded-md"></div>
                        <div class="mt-6 space-y-3 rounded-xl border border-neutral-800 p-3">
                            @for ($i = 0; $i < 8; $i++)
                                <div class="flex items-center gap-4">
                                    <div class="skeleton h-8 w-8 rounded-full"></div>
                                    <div class="skeleton h-4 w-40 rounded-md"></div>
                                    <div class="skeleton ml-auto h-4 w-14 rounded-md"></div>
                                </div>
                            @endfor
                        </div>
                    @else
                        <div class="skeleton h-8 w-48 rounded-md"></div>
                        <div class="mt-6 grid gap-4 lg:grid-cols-2">
                            @for ($i = 0; $i < 4; $i++)
                                <div class="space-y-3 rounded-xl border border-neutral-800 p-5">
                                    <div class="skeleton h-6 w-2/3 rounded-md"></div>
                                    <div class="skeleton h-4 w-1/2 rounded-md"></div>
                                </div>
                            @endfor
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </body>
</html>
