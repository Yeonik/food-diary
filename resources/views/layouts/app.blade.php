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
    @endphp

    <div class="app">
        {{-- Desktop: left sidebar --}}
        <nav class="sidebar" aria-label="{{ __('nav.brand') }}">
            <span class="sidebar__brand">{{ __('nav.brand') }}</span>
            @foreach ($nav as $item)
                <a class="sidebar__item" href="{{ route($item['route']) }}"
                   @if (request()->routeIs($item['match'])) aria-current="page" @endif>
                    <x-icon :name="$item['icon']" />
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>

        <main class="app__content" id="content">
            {{-- Desktop quick-add: two compact buttons, top-right. On mobile the
                 round FAB below does this job, so this bar is desktop-only. --}}
            <div class="quickadd">
                <a class="btn btn--ghost btn--sm" href="{{ route('log.manual') }}" data-dialog-open="manual-dialog">
                    <x-icon name="plus" /> {{ __('nav.add_manual') }}
                </a>
                <a class="btn btn--ghost btn--sm" href="{{ route('log.barcode') }}">
                    <x-icon name="barcode" /> {{ __('nav.add_barcode') }}
                </a>
                <a class="btn btn--sm" href="{{ route('log.photo') }}">
                    <x-icon name="camera" /> {{ __('nav.add_photo') }}
                </a>
            </div>

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
        </main>
    </div>

    {{-- Mobile: bottom tab bar --}}
    <nav class="tabbar" aria-label="{{ __('nav.brand') }}">
        @foreach ($nav as $item)
            <a class="tabbar__item" href="{{ route($item['route']) }}"
               @if (request()->routeIs($item['match'])) aria-current="page" @endif>
                <x-icon :name="$item['icon']" />
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>

    {{-- Mobile: floating action button with two entries (pure HTML, no JS) --}}
    <details class="fab">
        <summary class="fab__toggle" aria-label="{{ __('nav.add') }}">
            <x-icon name="plus" />
        </summary>
        <div class="fab__menu">
            <a class="fab__action" href="{{ route('log.photo') }}">
                <x-icon name="camera" /> <span>{{ __('nav.add_photo') }}</span>
            </a>
            <a class="fab__action" href="{{ route('log.barcode') }}">
                <x-icon name="barcode" /> <span>{{ __('nav.add_barcode') }}</span>
            </a>
            <a class="fab__action" href="{{ route('log.manual') }}" data-dialog-open="manual-dialog">
                <x-icon name="manual" /> <span>{{ __('nav.add_manual') }}</span>
            </a>
        </div>
    </details>

    {{-- Manual entry as a dialog: opened by the "by hand" actions with JS, closed
         by Esc (native) or a click outside. Without JS the same actions are plain
         links to /log/manual, so the flow still works. --}}
    <dialog id="manual-dialog" class="dialog">
        <form method="post" action="{{ route('log.manual.store') }}" class="dialog__panel">
            @csrf
            <div class="dialog__head">
                <h2>{{ __('manual.title') }}</h2>
                <button type="button" class="dialog__close" data-dialog-close aria-label="{{ __('common.cancel') }}">×</button>
            </div>
            <p class="muted">{{ __('manual.intro') }}</p>
            <div class="field">
                <label for="manual-name">{{ __('manual.name') }}</label>
                <input type="text" id="manual-name" name="name" required>
            </div>
            <button class="btn btn--block" type="submit">{{ __('manual.search') }}</button>
        </form>
    </dialog>

    <script src="{{ asset('js/app.js') }}" defer></script>
</body>
</html>
