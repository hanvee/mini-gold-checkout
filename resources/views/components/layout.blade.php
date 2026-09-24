<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Mini Gold Checkout' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-canvas text-ink font-sans antialiased">
    <div class="mx-auto max-w-md px-4 py-6">
        <header class="mb-6">
            <a href="{{ route('products.index') }}" class="text-lg font-semibold text-gold-700">
                Mini Gold Checkout
            </a>
        </header>

        {{-- Reserved above the content so a flash message never shifts card positions. --}}
        <div class="mb-4 space-y-2">
            @if (session('status'))
                <div role="status" class="rounded-md border border-green-200 bg-green-100 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div role="alert" class="rounded-md border border-red-200 bg-red-100 px-4 py-3 text-sm text-red-800">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <main>
            {{ $slot }}
        </main>
    </div>
</body>
</html>
