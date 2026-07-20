<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Gear Tracker API — a focused backend for organizing personal gear inventories.">
    <title>Gear Tracker API</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen overflow-x-hidden bg-[#f5f7f4] font-sans text-[#13211d] antialiased">
    <div class="relative isolate min-h-screen">
        <div class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[620px] overflow-hidden">
            <div class="absolute -right-40 -top-48 h-[560px] w-[560px] rounded-full bg-[#d5e8df] blur-3xl"></div>
            <div class="absolute -left-56 top-40 h-[440px] w-[440px] rounded-full bg-[#e9e0cc] blur-3xl"></div>
        </div>

        <header class="mx-auto flex max-w-6xl items-center justify-between px-6 py-7 lg:px-10">
            <a href="{{ url('/') }}" class="flex items-center gap-3 font-semibold tracking-tight" aria-label="Gear Tracker home">
                <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-[#13211d] text-[#d9f7e8] shadow-lg shadow-[#13211d]/15">
                    <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M8.7 4.6 6.4 7l2.2 2.2-2.2 2.2L4 9.1 6.4 6.7M15.3 19.4l2.3-2.4-2.2-2.2 2.2-2.2 2.4 2.3-2.4 2.4M13.8 4 10.2 20" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
                <span>Gear Tracker <span class="font-normal text-[#71817a]">/ API</span></span>
            </a>
            <a href="{{ route('openapi') }}" class="hidden items-center gap-2 text-sm font-medium text-[#4a6258] transition hover:text-[#13211d] sm:flex">
                API reference
                <span aria-hidden="true">↗</span>
            </a>
        </header>

        <main class="mx-auto max-w-6xl px-6 pb-20 pt-12 lg:px-10 lg:pt-24">
            <section class="grid items-center gap-14 lg:grid-cols-[1.05fr_0.95fr]">
                <div>
                    <div class="mb-7 inline-flex items-center gap-2 rounded-full border border-[#b8d3c5] bg-white/70 px-3.5 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-[#35705a] shadow-sm">
                        <span class="h-2 w-2 rounded-full bg-[#49a978] shadow-[0_0_0_4px_#49a97822]"></span>
                        Service online
                    </div>
                    <h1 class="max-w-3xl text-5xl font-semibold leading-[0.98] tracking-[-0.055em] text-[#13211d] sm:text-7xl">Keep every piece of your kit in reach.</h1>
                    <p class="mt-7 max-w-xl text-lg leading-8 text-[#5e7168]">Gear Tracker is a clean, authenticated API for organizing personal equipment, categories, images, and product imports — so the things you rely on stay easy to find.</p>
                    <div class="mt-9 flex flex-wrap items-center gap-4">
                        <a href="{{ route('openapi') }}" class="inline-flex items-center gap-3 rounded-full bg-[#13211d] px-6 py-3.5 text-sm font-semibold text-white shadow-xl shadow-[#13211d]/15 transition hover:-translate-y-0.5 hover:bg-[#29453a] focus:outline-none focus:ring-2 focus:ring-[#49a978] focus:ring-offset-2">
                            Explore the API
                            <span aria-hidden="true" class="text-lg leading-none">→</span>
                        </a>
                        <a href="{{ url('/api/v1/health') }}" class="inline-flex items-center gap-2 rounded-full border border-[#c8d6cf] bg-white/65 px-6 py-3.5 text-sm font-semibold text-[#39564a] transition hover:border-[#8eada0] hover:bg-white focus:outline-none focus:ring-2 focus:ring-[#49a978] focus:ring-offset-2">Check health <span aria-hidden="true">↗</span></a>
                    </div>
                </div>

                <div class="relative mx-auto w-full max-w-md">
                    <div class="absolute -inset-5 rounded-[2.5rem] bg-[#dcebe3]/75 blur-2xl"></div>
                    <div class="relative overflow-hidden rounded-[2rem] border border-white/80 bg-[#15251f] p-6 shadow-2xl shadow-[#13211d]/20 sm:p-8">
                        <div class="flex items-center justify-between border-b border-white/10 pb-6">
                            <div><p class="text-xs font-medium uppercase tracking-[0.2em] text-[#8faaa0]">Your inventory</p><p class="mt-1 text-xl font-semibold text-white">Field kit</p></div>
                            <span class="rounded-full bg-[#b8f0d1] px-3 py-1 text-xs font-semibold text-[#22533b]">Synced</span>
                        </div>
                        <div class="space-y-3 py-6">
                            <div class="flex items-center gap-4 rounded-2xl bg-white/[0.08] p-4"><span class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#d7eee2] text-xl">⌁</span><div class="flex-1"><p class="text-sm font-semibold text-white">Trail camera</p><p class="text-xs text-[#91aaa1]">Electronics · 2 items</p></div><span class="text-[#8faaa0]">›</span></div>
                            <div class="flex items-center gap-4 rounded-2xl bg-white/[0.08] p-4"><span class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#f2e2bd] text-xl">◒</span><div class="flex-1"><p class="text-sm font-semibold text-white">Camping stove</p><p class="text-xs text-[#91aaa1]">Cooking · 1 item</p></div><span class="text-[#8faaa0]">›</span></div>
                            <div class="flex items-center gap-4 rounded-2xl bg-white/[0.08] p-4"><span class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#e7d9e7] text-xl">⌂</span><div class="flex-1"><p class="text-sm font-semibold text-white">Shelter system</p><p class="text-xs text-[#91aaa1]">Camp · 4 items</p></div><span class="text-[#8faaa0]">›</span></div>
                        </div>
                        <div class="flex items-center justify-between border-t border-white/10 pt-5 text-xs text-[#91aaa1]"><span>8 items tracked</span><span class="font-mono text-[#b8f0d1]">/api/v1</span></div>
                    </div>
                </div>
            </section>

            <section class="mt-24 grid gap-5 border-t border-[#d9e1dc] pt-8 sm:grid-cols-3">
                <div><p class="text-sm font-semibold text-[#28493b]">Own your inventory</p><p class="mt-2 max-w-xs text-sm leading-6 text-[#6c7e75]">Categories and gear items stay scoped to the authenticated user.</p></div>
                <div><p class="text-sm font-semibold text-[#28493b]">Built for real gear</p><p class="mt-2 max-w-xs text-sm leading-6 text-[#6c7e75]">Validated image uploads and asynchronous product imports are ready to use.</p></div>
                <div><p class="text-sm font-semibold text-[#28493b]">Simple to integrate</p><p class="mt-2 max-w-xs text-sm leading-6 text-[#6c7e75]">OpenAPI 3.0.3 documentation and stable JSON error envelopes keep clients predictable.</p></div>
            </section>
        </main>

        <footer class="mx-auto flex max-w-6xl flex-col gap-3 border-t border-[#d9e1dc] px-6 py-7 text-sm text-[#71817a] sm:flex-row sm:items-center sm:justify-between lg:px-10">
            <span>Gear Tracker API · Built for the things you carry.</span>
            <a href="{{ route('openapi') }}" class="font-medium text-[#4a6258] hover:text-[#13211d]">Read the OpenAPI document ↗</a>
        </footer>
    </div>
</body>
</html>