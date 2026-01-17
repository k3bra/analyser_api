<template>
    <div class="relative min-h-screen overflow-hidden bg-[#f4f7fb] text-slate-900">
        <div
            class="pointer-events-none absolute -left-32 -top-48 h-96 w-96 rounded-full bg-gradient-to-br from-sky-200 via-cyan-100 to-transparent blur-3xl opacity-80"
        ></div>
        <div
            class="pointer-events-none absolute -right-40 top-12 h-[28rem] w-[28rem] rounded-full bg-gradient-to-tr from-blue-200 via-sky-100 to-transparent blur-3xl opacity-80"
        ></div>
        <div
            class="pointer-events-none absolute left-1/3 top-2/3 h-72 w-72 rounded-full bg-gradient-to-tr from-indigo-100 via-sky-100 to-transparent blur-3xl opacity-70"
        ></div>

        <header class="relative z-10 border-b border-slate-900/10">
            <div class="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-6">
                <div>
                    <p class="text-xs uppercase tracking-[0.3em] text-slate-500">
                        DocuSnitch
                    </p>
                    <h1 class="text-2xl font-semibold tracking-tight">
                        A snitch for API docs
                    </h1>
                </div>
            </div>
        </header>

        <main class="relative z-10 mx-auto w-full max-w-6xl px-6 py-10">
            <slot />
        </main>
    </div>
</template>
