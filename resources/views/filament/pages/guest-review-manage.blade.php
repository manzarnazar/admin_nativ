<x-filament-panels::page>
    @if ($this->getHasReviews())
        {{ $this->table }}
    @else
        <div class="fi-ta-ctn rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 48px 24px; text-align: center; width: 100%;">
                <div style="background-color: #f3f4f6; border-radius: 50%; padding: 12px; margin-bottom: 16px;">
                    <x-heroicon-o-star style="width: 24px; height: 24px; color: #9ca3af;" />
                </div>
                <p style="font-size: 14px; font-weight: 600; color: #111827; margin: 0;">{{ __('admin.no_reviews_available') }}</p>
                <p style="font-size: 13px; color: #6b7280; margin: 8px 0 0; max-width: 350px; line-height: 1.5;">{{ __('admin.no_reviews_description') }}</p>
            </div>
        </div>
    @endif
</x-filament-panels::page>
