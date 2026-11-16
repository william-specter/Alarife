<script setup lang="ts">
import type { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, BookOpen, Download, Layers, Sparkles } from 'lucide-vue-next';
import { route } from 'ziggy-js';

const page = usePage<SharedData>();
const currentUser = page.props.auth.user;

defineProps<{
    encyclopedias: {
        title: string;
        subtitle: string | null;
        description: string | null;
        slug: string;
        cover_url: string | null;
        topics_count: number;
        articles_count: number;
        theme_key: string;
    }[];
    canLogin: boolean;
    canRegister: boolean;
}>();
</script>

<template>
    <Head title="Alarife — reference works, published everywhere">
        <meta
            name="description"
            content="Alarife is a publishing platform for independent encyclopedias: structured authoring, distinct themes, versioned releases and complete offline websites."
        />
    </Head>

    <div class="min-h-screen bg-[#faf7f1] text-[#1b1712]">
        <header class="border-b border-[#e6dfd2]">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-5">
                <Link :href="route('home')" class="flex items-center gap-2.5">
                    <span class="flex size-9 items-center justify-center rounded-md bg-[#1b1712] text-[#faf7f1]"
                        ><span class="font-display text-xl leading-none">A</span></span
                    >
                    <span class="flex flex-col leading-tight">
                        <span class="font-display text-lg">Alarife</span>
                        <span class="text-[9px] font-semibold uppercase tracking-[0.22em] text-[#7c7365]">Reference publishing</span>
                    </span>
                </Link>
                <nav class="flex items-center gap-2 text-xs font-medium">
                    <template v-if="currentUser">
                        <Link :href="route('dashboard')" class="inline-flex h-9 items-center rounded-md bg-[#1b1712] px-4 text-[#faf7f1]"
                            >Open dashboard</Link
                        >
                    </template>
                    <template v-else>
                        <Link v-if="canLogin" :href="route('login')" class="px-3 py-2 text-[#5c5346] hover:text-[#1b1712]">Sign in</Link>
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="inline-flex h-9 items-center rounded-md bg-[#1b1712] px-4 text-[#faf7f1]"
                            >Create account</Link
                        >
                    </template>
                </nav>
            </div>
        </header>

        <main>
            <!-- Hero -->
            <section class="mx-auto grid max-w-6xl gap-10 px-5 py-16 lg:grid-cols-[1.2fr_0.8fr] lg:items-end lg:py-24">
                <div>
                    <p class="mb-4 text-[10px] font-semibold uppercase tracking-[0.22em] text-[#b4512e]">A publisher's platform</p>
                    <h1 class="font-display text-[3.2rem] leading-[0.97] tracking-[-0.045em] sm:text-[4.6rem]">
                        Build reference works.<br />Publish them <em class="text-[#b4512e]">everywhere</em>.
                    </h1>
                    <p class="mt-5 max-w-xl text-base leading-relaxed text-[#5c5346]">
                        Author structured encyclopedias, shape each one with its own visual identity, publish versioned editions — and distribute a
                        complete website that works offline.
                    </p>
                    <div class="mt-7 flex flex-wrap items-center gap-3 text-sm">
                        <Link
                            :href="currentUser ? route('dashboard') : route('login')"
                            class="inline-flex h-11 items-center gap-2 rounded-md bg-[#1b1712] px-5 font-medium text-[#faf7f1]"
                        >
                            Start publishing <ArrowRight class="size-4" />
                        </Link>
                        <a href="#encyclopedias" class="inline-flex h-11 items-center rounded-md border border-[#d8cfc0] px-5 font-medium"
                            >Browse public works</a
                        >
                    </div>
                    <div class="mt-10 grid max-w-lg grid-cols-3 gap-4 border-t border-[#e6dfd2] pt-5 text-xs text-[#7c7365]">
                        <div><strong class="font-display block text-2xl text-[#1b1712]">1×</strong>Author once</div>
                        <div><strong class="font-display block text-2xl text-[#1b1712]">5</strong>Visual themes</div>
                        <div><strong class="font-display block text-2xl text-[#1b1712]">0</strong>Servers needed offline</div>
                    </div>
                </div>

                <div class="relative">
                    <div
                        class="absolute -left-3 top-8 hidden h-[78%] w-[72%] rotate-[-4deg] rounded-sm border border-[#d8cfc0] bg-[#efe7da] sm:block"
                    />
                    <div class="relative rounded-sm border border-[#d8cfc0] bg-white p-4 shadow-xl shadow-black/5">
                        <div class="mb-3 flex items-center justify-between text-[9px] font-semibold uppercase tracking-[0.16em] text-[#8a8070]">
                            <span>The editor's desk</span><span>No. 001</span>
                        </div>
                        <div class="aspect-[4/3] overflow-hidden bg-[#eee5d7]">
                            <div class="flex h-full flex-col justify-between p-5">
                                <div class="h-2 w-24 bg-[#b4512e]" />
                                <div>
                                    <p class="font-display text-[2rem] leading-[0.95] tracking-tight">The shape of a reference work.</p>
                                    <p class="mt-3 max-w-[240px] text-[10px] leading-relaxed text-[#6f6455]">
                                        Structured content, an independent design layer and an archive you can carry on a USB drive.
                                    </p>
                                </div>
                                <div class="flex gap-1.5">
                                    <span class="h-1.5 w-8 bg-[#1b1712]" /><span class="h-1.5 w-4 bg-[#c9a227]" /><span
                                        class="h-1.5 w-6 bg-[#d8cfc0]"
                                    />
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 flex items-center justify-between text-[10px] text-[#8a8070]">
                            <span>Composed in Alarife</span><span>Offline edition included</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Public works -->
            <section id="encyclopedias" class="border-y border-[#e6dfd2] bg-[#f4efe5]">
                <div class="mx-auto max-w-6xl px-5 py-14">
                    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-[#b4512e]">Public shelf</p>
                            <h2 class="font-display mt-1 text-3xl tracking-tight">Independent reference works</h2>
                        </div>
                        <Link :href="route('dashboard')" class="text-xs font-semibold text-[#b4512e]">Publish your own →</Link>
                    </div>

                    <div v-if="encyclopedias.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <a
                            v-for="item in encyclopedias"
                            :key="item.slug"
                            :href="`/${item.slug}`"
                            class="group overflow-hidden rounded-sm border border-[#d8cfc0] bg-white transition-all hover:-translate-y-0.5 hover:shadow-lg"
                        >
                            <div class="aspect-[16/10] overflow-hidden bg-[#eee5d7]">
                                <img
                                    v-if="item.cover_url"
                                    :src="item.cover_url"
                                    :alt="item.title"
                                    class="size-full object-cover transition-transform duration-500 group-hover:scale-[1.03]"
                                />
                                <div v-else class="font-display flex size-full items-center justify-center text-4xl text-[#c4b8a5]">
                                    {{ item.title.slice(0, 1) }}
                                </div>
                            </div>
                            <div class="p-4">
                                <p class="font-display text-xl leading-tight">{{ item.title }}</p>
                                <p v-if="item.subtitle" class="mt-0.5 text-[11px] text-[#7c7365]">{{ item.subtitle }}</p>
                                <p class="mt-2 line-clamp-2 text-xs leading-relaxed text-[#7c7365]">{{ item.description }}</p>
                                <p class="mt-3 border-t border-[#eee7db] pt-2 text-[10px] uppercase tracking-[0.1em] text-[#9a8f7e]">
                                    {{ item.topics_count }} topics · {{ item.articles_count }} articles · {{ item.theme_key }}
                                </p>
                            </div>
                        </a>
                    </div>
                    <div v-else class="rounded-sm border border-dashed border-[#cfc4b3] bg-white/50 px-6 py-12 text-center">
                        <BookOpen class="mx-auto size-6 text-[#9a8f7e]" />
                        <p class="font-display mt-3 text-xl">The public shelf is waiting for its first work.</p>
                        <p class="mt-1 text-xs text-[#7c7365]">Sign in, create an encyclopedia and publish a release.</p>
                    </div>
                </div>
            </section>

            <!-- Principles -->
            <section class="mx-auto max-w-6xl px-5 py-16">
                <div class="grid gap-8 md:grid-cols-3">
                    <div>
                        <span class="mb-3 flex size-9 items-center justify-center rounded-full border border-[#d8cfc0]"
                            ><Layers class="size-4"
                        /></span>
                        <h3 class="font-display text-xl">Content independent of design</h3>
                        <p class="mt-2 text-sm leading-relaxed text-[#6f6455]">
                            Articles are structured documents. Change the theme, cover layout or typography without touching a word.
                        </p>
                    </div>
                    <div>
                        <span class="mb-3 flex size-9 items-center justify-center rounded-full border border-[#d8cfc0]"
                            ><Sparkles class="size-4"
                        /></span>
                        <h3 class="font-display text-xl">Versioned, reproducible releases</h3>
                        <p class="mt-2 text-sm leading-relaxed text-[#6f6455]">
                            Every publication is an immutable snapshot with its own theme version, article set and archive checksum.
                        </p>
                    </div>
                    <div>
                        <span class="mb-3 flex size-9 items-center justify-center rounded-full border border-[#d8cfc0]"
                            ><Download class="size-4"
                        /></span>
                        <h3 class="font-display text-xl">A website you can carry</h3>
                        <p class="mt-2 text-sm leading-relaxed text-[#6f6455]">
                            Export HTML, CSS, JavaScript and media. Open it from a folder, host it anywhere, archive it forever.
                        </p>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-[#e6dfd2]">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-5 py-6 text-[11px] text-[#8a8070]">
                <span>© {{ new Date().getFullYear() }} Alarife — a publisher's platform.</span>
                <span>Online editions · Offline archives · One content model</span>
            </div>
        </footer>
    </div>
</template>
