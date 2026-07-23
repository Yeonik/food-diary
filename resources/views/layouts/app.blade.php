<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1c5d5a">
    <title>@yield('title', __('nav.brand'))</title>
    <link rel="preload" as="font" type="font/woff2" crossorigin
          href="{{ asset('fonts/manrope-latin-400-normal.woff2') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <a class="skip-link" href="#content">{{ __('nav.skip_to_content') }}</a>

    @php
        $nav = [
            ['route' => 'diary.index',   'match' => 'diary.*',   'icon' => 'day',     'label' => __('nav.day')],
            ['route' => 'history.index', 'match' => 'history.*', 'icon' => 'history', 'label' => __('nav.history')],
            ['route' => 'weight.index',  'match' => 'weight.*',  'icon' => 'weight',  'label' => __('nav.weight')],
            ['route' => 'library.index', 'match' => 'library.*', 'icon' => 'library', 'label' => __('nav.library')],
            ['route' => 'goal.edit',     'match' => 'goal.*',    'icon' => 'goal',    'label' => __('nav.goal')],
        ];

        // Which screen the back arrow returns to. The five above are the roots and
        // show no arrow; everything else is reached from one of them. Mirrors the
        // build's PARENT map.
        $parents = [
            'log.photo' => 'diary.index',
            'log.manual' => 'diary.index',
            'log.confirm' => 'diary.index',
            'log.barcode' => 'diary.index',
            'log.barcode.confirm' => 'log.barcode',
            'entries.edit' => 'diary.index',
            'library.create' => 'library.index',
            'library.edit' => 'library.index',
            'library.recipe.create' => 'library.index',
            'library.recipe.edit' => 'library.index',
        ];

        $current = request()->route()?->getName();
        $backTo = $parents[$current] ?? null;

        // The three ways to log something ride along with the day, whether or not
        // it has entries yet: they are the only route to the barcode path, and an
        // empty day is exactly when a first entry gets made.
        $onDay = request()->routeIs('diary.*');

        // Signed out there is nowhere to navigate to: every section bounces back
        // to the sign-in screen, so offering them would be a rail that goes
        // nowhere. Read from the guard, not from the route name, so a screen
        // added later cannot forget to say it is a guest screen.
        $guest = ! auth()->check();
    @endphp

    <div class="app">
        {{-- Desktop: the left rail. Hidden below 900px, where the tab bar takes over. --}}
        <nav class="side" aria-label="{{ __('nav.brand') }}">
            <div class="brand">
                <div class="logo" aria-hidden="true">{{ mb_substr(__('nav.brand'), 0, 1) }}</div>
                <b>{{ __('nav.brand') }}</b>
            </div>
            @unless ($guest)
                @foreach ($nav as $item)
                    <x-nav-item :icon="$item['icon']" :label="$item['label']" :route="$item['route']" :match="$item['match']" />
                @endforeach
            @endunless
            <div class="side-spacer"></div>
        </nav>

        <div class="main">
            {{-- Topbar: back arrow (off the root screens), the screen's title, and
                 the day's three ways to log something (desktop only). --}}
            <div class="topbar">
                <div class="topbar-l">
                    @if ($backTo)
                        <a class="back" href="{{ route($backTo) }}" aria-label="{{ __('common.back') }}">‹</a>
                    @endif
                    <div class="title">@yield('title', __('nav.brand'))</div>
                    @yield('topbar_nav')
                </div>
                @if ($onDay)
                    <div class="day-actions">
                        <a class="btn btn-s" href="{{ route('log.manual') }}" data-dialog-open="manual-dialog">
                            <x-icon name="manual" /> {{ __('nav.add_manual') }}
                        </a>
                        <a class="btn btn-s" href="{{ route('log.barcode') }}">
                            <x-icon name="barcode" /> {{ __('nav.add_barcode') }}
                        </a>
                        <a class="btn btn-p" href="{{ route('log.photo') }}">
                            <x-icon name="camera" /> {{ __('nav.add_photo') }}
                        </a>
                    </div>
                @endif
            </div>

            <main class="content scr" id="content">
                <div class="wrap">
                    @if (session('status'))
                        <p class="notice">{{ session('status') }}</p>
                    @endif
                    @if ($errors->any())
                        <div class="errors">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @yield('content')
                </div>
            </main>

            {{-- Mobile: bottom tab bar --}}
            @unless ($guest)
                <nav class="tabbar" aria-label="{{ __('nav.brand') }}">
                    @foreach ($nav as $item)
                        <x-nav-item layout="tab" :icon="$item['icon']" :label="$item['label']" :route="$item['route']" :match="$item['match']" />
                    @endforeach
                </nav>
            @endunless

            {{-- Mobile: the floating action button, on the day screen only. A
                 <details>, so the menu opens with JavaScript off. --}}
            @if ($onDay)
                <details class="fab-wrap show">
                    <summary class="fab" aria-label="{{ __('nav.add') }}">
                        <x-icon name="plus" stroke-width="2.2" />
                    </summary>
                    <div class="fab-menu">
                        <a class="fab-action" href="{{ route('log.photo') }}">
                            <x-icon name="camera" /> <span>{{ __('nav.add_photo') }}</span>
                        </a>
                        <a class="fab-action" href="{{ route('log.barcode') }}">
                            <x-icon name="barcode" /> <span>{{ __('nav.add_barcode') }}</span>
                        </a>
                        <a class="fab-action" href="{{ route('log.manual') }}" data-dialog-open="manual-dialog">
                            <x-icon name="manual" /> <span>{{ __('nav.add_manual') }}</span>
                        </a>
                    </div>
                </details>
            @endif
        </div>
    </div>

    {{-- Manual entry as a dialog: opened by the "by hand" actions with JS, closed
         by Esc (native) or a click outside. Without JS the same actions are plain
         links to /log/manual, so the flow still works. It posts to a signed-in
         route, so a guest screen does not carry it. --}}
    @unless ($guest)
    <dialog id="manual-dialog" class="modal">
        <form method="post" action="{{ route('log.manual.store') }}">
            @csrf
            <div class="modal-head">
                <div class="t">{{ __('manual.title') }}</div>
                <button type="button" class="modal-close" data-dialog-close aria-label="{{ __('common.cancel') }}">×</button>
            </div>
            <div class="modal-body">
                <p class="caption">{{ __('manual.intro') }}</p>
                <div>
                    <label class="flabel" for="manual-name">{{ __('manual.name') }}</label>
                    <input type="text" class="field" id="manual-name" name="name" required>
                </div>
                <div class="actions-end" style="margin-top:0">
                    <button type="button" class="btn btn-s" data-dialog-close>{{ __('common.cancel') }}</button>
                    <button class="btn btn-p" type="submit">{{ __('manual.search') }}</button>
                </div>
            </div>
        </form>
    </dialog>
    @endunless

    <script src="{{ asset('js/app.js') }}" defer></script>
</body>
</html>
