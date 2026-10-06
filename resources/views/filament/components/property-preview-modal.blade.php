<div class="space-y-4">
    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3 font-medium">{{ __('admin.name') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('admin.property_type') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('admin.city') }}</th>
                    <th class="px-4 py-3 font-medium text-right">{{ __('admin.rating') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                @forelse($properties as $property)
                <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900/50">
                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                        {{ $property->name }}
                    </td>
                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400">
                        {{ $property->propertyType?->name ?? '-' }}
                    </td>
                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400">
                        {{ $property->city?->name ?? '-' }}
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="inline-flex items-center gap-1">
                            <svg class="h-4 w-4 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" clip-rule="evenodd" />
                            </svg>
                            <span class="font-medium text-gray-900 dark:text-white">{{ number_format($property->rating ?? 0, 1) }}</span>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                        {{ __('admin.no_properties_found') }}
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>


    @if($totalCount > $properties->count())
    <div class="text-center text-sm text-gray-500 dark:text-gray-400">
        {{ __('admin.showing_first') }} {{ $properties->count() }} {{ __('admin.out_of') }} {{ $totalCount }} {{ __('admin.properties') }}.
    </div>
    @endif
</div>