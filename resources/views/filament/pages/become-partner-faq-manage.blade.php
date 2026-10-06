<x-filament-panels::page>
    @if ($this->getHasTopics())
        <div class="space-y-4" x-data="{ openTopic: null }">
            @foreach ($this->getTopics() as $topic)
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    {{-- Topic Header --}}
                    <div
                        class="flex cursor-pointer items-center gap-4 px-5 py-4"
                        x-on:click="openTopic = openTopic === {{ $topic->id }} ? null : {{ $topic->id }}"
                    >
                        {{-- Sort Number --}}
                        <span class="flex items-center justify-center rounded-lg bg-gray-100 text-xs font-bold text-gray-600 dark:bg-gray-800 dark:text-gray-300" style="width: 36px; height: 36px; min-width: 36px;">
                            {{ str_pad($topic->sort_order, 2, '0', STR_PAD_LEFT) }}
                        </span>

                        {{-- Title + Description --}}
                        <div class="min-w-0 flex-1 overflow-hidden">
                            <h4 class="truncate text-sm font-semibold text-gray-950 dark:text-white">
                                {{ $topic->title }}
                            </h4>
                            @if ($topic->description)
                                <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">
                                    {{ $topic->description }}
                                </p>
                            @endif
                        </div>

                        {{-- Topic Actions --}}
                        <div class="flex shrink-0 items-center gap-1" x-on:click.stop>
                            {{ ($this->editTopicAction)(['topic' => $topic->id]) }}
                            {{ ($this->deleteTopicAction)(['topic' => $topic->id]) }}
                        </div>

                        {{-- FAQ Count Badge --}}
                        <span class="shrink-0 rounded-lg border border-gray-200 py-1 text-center text-xs font-medium text-gray-600 dark:border-gray-600 dark:text-gray-400" style="width: 70px;">
                            {{ $topic->faqs_count }} {{ $topic->faqs_count > 1 ? __('admin.faqs') : __('admin.faq') }}
                        </span>

                        {{-- Chevron --}}
                        <x-heroicon-o-chevron-up
                            class="h-4 w-4 shrink-0 text-gray-400 transition-transform duration-200"
                            x-bind:class="openTopic === {{ $topic->id }} ? '' : 'rotate-180'"
                        />
                    </div>

                    {{-- FAQs List (Collapsible) --}}
                    <div
                        x-show="openTopic === {{ $topic->id }}"
                        x-collapse
                    >
                        <div class="border-t border-gray-200 dark:border-gray-700">
                            @if ($topic->faqs->isNotEmpty())
                                @foreach ($topic->faqs as $faq)
                                    <div class="faq-item group flex items-start justify-between border-b border-gray-100 px-5 py-3 last:border-b-0 dark:border-gray-800">
                                        <div class="min-w-0 flex-1 pr-4">
                                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                {{ $faq->question }}
                                            </p>
                                            <p class="mt-1 text-xs leading-relaxed text-gray-500 dark:text-gray-400" style="display: -webkit-box; -webkit-line-clamp: 10; line-clamp: 10; -webkit-box-orient: vertical; overflow: hidden;">
                                                {{ $faq->answer }}
                                            </p>
                                        </div>

                                        {{-- FAQ Actions --}}
                                        <div class="faq-item-actions flex shrink-0 items-center gap-1">
                                            {{ ($this->editFaqAction)(['faq' => $faq->id]) }}
                                            {{ ($this->deleteFaqAction)(['faq' => $faq->id]) }}
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <div class="px-5 py-6 text-center">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ __('admin.no_faqs_added_to_this_topic_yet') }}
                                    </p>
                                </div>
                            @endif

                            {{-- Add FAQ Link --}}
                            <div class="border-t border-gray-200 px-5 py-3 dark:border-gray-700">
                                <button
                                    type="button"
                                    wire:click="mountAction('addFaq', {{ json_encode(['topic' => $topic->id]) }})"
                                    class="inline-flex items-center gap-1 text-sm font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300"
                                >
                                    <x-heroicon-o-plus class="h-4 w-4" />
                                    {{ __('admin.add_faq_to', ['topic' => $topic->title]) }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="fi-ta-ctn rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex w-full flex-col items-center justify-center gap-4 px-6 py-12 text-center">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                    <x-heroicon-o-question-mark-circle class="h-6 w-6 text-gray-400 dark:text-gray-500" />
                </div>

                <h4 class="text-base font-semibold text-gray-950 dark:text-white">
                    {{ __('admin.no_faq_topics_added') }}
                </h4>
                <p class="max-w-md text-sm text-gray-500 dark:text-gray-400">
                    {{ __('admin.no_faq_topics_added_description') }}
                </p>
            </div>
        </div>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
