<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Food diary')</title>
    <style>
        /* Deliberately neutral. No red/green, no warning colours: the numbers
           are information, not judgement. */
        :root {
            --ink: #222; --muted: #6b6b6b; --line: #ddd; --bg: #fafafa;
            --panel: #fff; --accent: #33506b; --estimate: #8a6d3b;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; color: var(--ink); background: var(--bg);
            font: 15px/1.5 system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
        }
        header { border-bottom: 1px solid var(--line); background: var(--panel); }
        nav { max-width: 780px; margin: 0 auto; padding: 12px 16px; display: flex; gap: 16px; flex-wrap: wrap; }
        nav a { color: var(--accent); text-decoration: none; }
        nav a:hover { text-decoration: underline; }
        main { max-width: 780px; margin: 0 auto; padding: 20px 16px 60px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        h2 { font-size: 16px; margin: 24px 0 8px; }
        a { color: var(--accent); }
        .muted { color: var(--muted); }
        .status { background: #eef3f7; border: 1px solid #d5e0ea; padding: 8px 12px; border-radius: 4px; }
        .errors { background: #f6f0e8; border: 1px solid #e4d6c0; padding: 8px 12px 8px 28px; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid var(--line); vertical-align: top; }
        th { color: var(--muted); font-weight: 600; font-size: 13px; }
        .panel { background: var(--panel); border: 1px solid var(--line); border-radius: 6px; padding: 14px 16px; margin-bottom: 14px; }
        .row { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
        label { display: block; font-size: 13px; color: var(--muted); margin-bottom: 3px; }
        input, select, button { font: inherit; padding: 7px 9px; border: 1px solid var(--line); border-radius: 4px; background: #fff; }
        input[type=number] { width: 110px; }
        button, .btn {
            background: var(--accent); color: #fff; border: 0; border-radius: 4px;
            padding: 8px 14px; cursor: pointer; text-decoration: none; display: inline-block;
        }
        button.link, .btn.link { background: none; color: var(--accent); padding: 4px 6px; }
        .badge { font-size: 12px; color: var(--muted); border: 1px solid var(--line); border-radius: 10px; padding: 1px 8px; }
        /* An estimate looks different and never reads as verified. */
        .estimate { border-left: 3px dashed var(--estimate); }
        .estimate-tag { color: var(--estimate); font-size: 12px; }
        .totals { display: flex; gap: 20px; flex-wrap: wrap; font-variant-numeric: tabular-nums; }
        .totals .big { font-size: 22px; }
        .inline-form { display: inline; }
    </style>
</head>
<body>
    <header>
        <nav>
            <a href="{{ route('diary.index') }}">Diary</a>
            <a href="{{ route('log.photo') }}">Log photo</a>
            <a href="{{ route('log.manual') }}">Log manually</a>
            <a href="{{ route('library.index') }}">Library</a>
            <a href="{{ route('weight.index') }}">Weight</a>
            <a href="{{ route('goal.edit') }}">Goal</a>
        </nav>
    </header>
    <main>
        @if (session('status'))
            <p class="status">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <ul class="errors">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif
        @yield('content')
    </main>
</body>
</html>
