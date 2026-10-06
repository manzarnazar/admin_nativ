<x-filament-panels::page>
    @php
    $property = $this->getProperty();
    $tabs = $this->getTabs();
    $activeTab = $this->activeTab;
    $reviewsAvgRating = $this->getReviewsAvgRating();
    $reviewsCount = $this->getReviewsCount();
    @endphp

    @include('filament.schemas.components.property-detail.header-card', [
        'property' => $property,
        'backUrl' => $backUrl,
        'showPartner' => false,
        'reviewsAvgRating' => $reviewsAvgRating,
        'reviewsCount' => $reviewsCount,
        'tabs' => $tabs,
        'activeTab' => $activeTab,
    ])

    <div class="section-bg-gray -mx-8 -mb-8 px-8 pt-6 pb-8">
        @if ($activeTab === 'overview')
            @include('filament.schemas.components.property-detail.tab-overview', [
                'property' => $property,
                'otherRegistrationValues' => $this->getOtherRegistrationValues(),
                'showPartnerSidebar' => false,
            ])
        @elseif ($activeTab === 'rooms_pricing')
            {{ $this->table }}
            @include('filament.schemas.components.property-detail.room-stats', ['stats' => $this->getRoomStats()])
        @elseif ($activeTab === 'facilities')
            @include('filament.schemas.components.property-detail.tab-facilities', [
                'groupedFacilities' => $this->getGroupedFacilities(),
            ])
        @elseif ($activeTab === 'property_rules')
            @include('filament.schemas.components.property-detail.tab-property-rules', [
                'property' => $property,
                'cancellationPolicy' => $this->getCancellationPolicy(),
                'ruleGroups' => $this->getPropertyRuleAnswers(),
            ])
        @elseif ($activeTab === 'location_nearby')
            @include('filament.schemas.components.property-detail.tab-location-nearby', [
                'property' => $property,
                'groupedNearbyPlaces' => $this->getGroupedNearbyPlaces(),
            ])
        @elseif ($activeTab === 'media')
            @include('filament.schemas.components.property-detail.tab-media', [
                'primaryImages' => $this->getPrimaryImages(),
                'galleryGroups' => $this->getGalleryGroups(),
                'mediaCounts' => $this->getMediaCounts(),
            ])
        @elseif ($activeTab === 'documentations')
            @include('filament.schemas.components.property-detail.tab-documents', [
                'property' => $property,
                'documentCount' => $this->getDocumentCount(),
            ])
        @elseif ($activeTab === 'wallet')
            @include('filament.schemas.components.property-detail.tab-wallet', [
                'propertyId' => $property->id,
                'walletStats' => $this->getWalletStats(),
                'walletSubTab' => $this->walletSubTab,
                'walletDatePreset' => $this->walletDatePreset,
                'walletCustomDate' => $this->walletCustomDate,
            ])
        @elseif ($activeTab === 'analytics')
            @include('filament.schemas.components.property-detail.tab-analytics', [
                'analyticsStats' => $this->getAnalyticsStats(),
                'revenueChartData' => $this->revenueChartData,
                'bookingStatusData' => $this->bookingStatusData,
                'commissionData' => null,
                'payoutData' => $this->getPayoutData(),
                'showPlatformEarnings' => false,
                'showCommissionCard' => false,
                'analyticsDatePreset' => $this->analyticsDatePreset,
                'analyticsCustomDate' => $this->analyticsCustomDate,
                'revenueChartPreset' => $this->revenueChartPreset,
                'bookingStatusPreset' => $this->bookingStatusPreset,
            ])
        @elseif ($activeTab === 'logs')
            @include('filament.schemas.components.property-detail.tab-logs', [
                'property' => $property,
                'auditLogs' => $this->getAuditLogs(),
                'auditLogDate' => $this->auditLogDate,
            ])
        @else
            <div class="flex flex-col items-center justify-center gap-4 rounded-2xl border border-[#EDEDED] bg-white px-6 py-16 text-center dark:border-gray-700 dark:bg-gray-900">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-primary-50 dark:bg-primary-950">
                    <x-heroicon-o-wrench-screwdriver class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                </div>
                <h4 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('admin.coming_soon') }}</h4>
                <p class="max-w-md text-sm text-gray-500 dark:text-gray-400">{{ __('admin.coming_soon_description') }}</p>
            </div>
        @endif
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
