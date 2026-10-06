<?php

namespace App\Filament\Partner\Pages;

use App\Enums\ReviewRemovalStatus;
use App\Enums\ReviewStatus;
use App\Filament\Concerns\RequiresApprovedPartner;
use App\Filament\Partner\Enums\NavigationGroup;
use App\Models\Partner;
use App\Models\Review;
use App\Models\User;
use App\Support\PartnerContext;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\Text;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class PartnerRemoveReviewsManage extends Page implements HasTable
{
    use InteractsWithTable;
    use RequiresApprovedPartner;

    protected static ?string $slug = 'remove-reviews';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.partner.pages.partner-remove-reviews-manage';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::ReviewMonitoring;
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.remove_requested');
    }

    public static function getNavigationIcon(): string|Htmlable|null
    {
        return null;
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.remove_requested');
    }

    public function getSubheading(): ?string
    {
        return __('admin.remove_requested_subheading');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    private function getPartner(): ?Partner
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->partner;
    }

    private function getCurrentPropertyId(): ?int
    {
        $partner = $this->getPartner();
        $countryId = $partner ? PartnerContext::currentCountryId($partner) : null;

        return ($partner && $countryId) ? PartnerContext::currentPropertyId($partner, $countryId) : null;
    }

    public function hasRemovalRequests(): bool
    {
        $partner = $this->getPartner();
        $countryId = $partner ? PartnerContext::currentCountryId($partner) : null;
        $propertyId = $this->getCurrentPropertyId();

        $query = Review::query()
            ->where('removal_requested', true)
            ->whereHas('property', fn (Builder $q) => $q->where('partner_id', $partner?->id)->where('country_id', $countryId));

        if ($propertyId) {
            $query->where('property_id', $propertyId);
        }

        return $query->exists();
    }

    public function table(Table $table): Table
    {
        $partner = $this->getPartner();
        $countryId = $partner ? PartnerContext::currentCountryId($partner) : null;
        $propertyId = $this->getCurrentPropertyId();

        $query = Review::query()
            ->with(['user', 'booking.propertyRoom.roomType', 'property', 'images'])
            ->where('removal_requested', true)
            ->whereHas('property', fn (Builder $q) => $q->where('partner_id', $partner?->id)->where('country_id', $countryId));

        if ($propertyId) {
            $query->where('property_id', $propertyId);
        }

        return $table
            ->query($query)
            ->defaultSort('created_at', 'desc')
            ->searchPlaceholder(__('admin.search_by_property_or_customer_name'))
            ->columns([
                TextColumn::make('id')
                    ->label(__('admin.id'))
                    ->sortable()
                    ->color('primary'),

                TextColumn::make('booking.propertyRoom.roomType.name')
                    ->label(__('admin.room_details'))
                    ->formatStateUsing(fn (Review $record): string => $record->booking?->propertyRoom?->roomType?->name ?? '-')
                    ->description(function (Review $record): string {
                        $avg = Review::query()
                            ->where('property_id', $record->property_id)
                            ->where('status', ReviewStatus::Published->value)
                            ->avg('rating');

                        return '★ '.number_format((float) ($avg ?? 0), 1).' - '.__('admin.overall');
                    })
                    ->limit(25)
                    ->wrap(),

                TextColumn::make('user.name')
                    ->label(__('admin.guest_details'))
                    ->description(fn (Review $record): string => 'Booking ID: '.($record->booking?->booking_number ?? '-'))
                    ->searchable()
                    ->limit(20)
                    ->wrap(),

                TextColumn::make('rating')
                    ->label(__('admin.rating_and_review'))
                    ->formatStateUsing(fn (Review $record): string => '★ '.$record->rating)
                    ->description(fn (Review $record): string => Str::limit($record->review, 60))
                    ->wrap(),

                TextColumn::make('removal_status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->formatStateUsing(fn (?ReviewRemovalStatus $state): string => $state?->label() ?? ReviewRemovalStatus::Requested->label())
                    ->color(fn (?ReviewRemovalStatus $state): string => $state?->color() ?? 'info'),
            ])
            ->filters([
                SelectFilter::make('removal_status')
                    ->label(__('admin.status'))
                    ->options(collect(ReviewRemovalStatus::cases())
                        ->mapWithKeys(fn (ReviewRemovalStatus $s) => [$s->value => $s->label()])->toArray()),
            ])
            ->recordActions([
                $this->getViewAction(),
            ])
            ->emptyStateHeading(__('admin.no_removal_requests'))
            ->emptyStateDescription(__('admin.no_removal_requests_description'))
            ->emptyStateIcon('heroicon-o-arrow-path');
    }

    private function getViewAction(): Action
    {
        return Action::make('view')
            ->iconButton()
            ->icon('phosphor-eye')
            ->color('gray')
            ->modalHeading(__('admin.review_details'))
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalWidth('lg')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('admin.close_details'))
            ->schema(function (Review $record): array {
                $roomTypeName = $record->booking?->propertyRoom?->roomType?->name ?? '-';
                $propertyRating = Review::query()
                    ->where('property_id', $record->property_id)
                    ->where('status', ReviewStatus::Published->value)
                    ->avg('rating');

                $removalStatus = $record->removal_status;
                $statusLabel = $removalStatus?->label() ?? ReviewRemovalStatus::Requested->label();
                $statusColor = match ($removalStatus) {
                    ReviewRemovalStatus::Approved => '#dc2626',
                    ReviewRemovalStatus::Rejected => '#d97706',
                    default => '#0284c7',
                };

                $html = '<div class="space-y-4">'
                    .'<div class="flex items-center gap-3">'
                    .'<span class="text-sm text-gray-500">ID: '.$record->id.'</span>'
                    .'<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium" style="background-color: '.$statusColor.'20; color: '.$statusColor.';">'.$statusLabel.'</span>'
                    .'</div>';

                // Room card
                $html .= '<div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">'
                    .'<p class="font-semibold text-gray-900 dark:text-white">'.e($roomTypeName).'</p>'
                    .'<p class="text-xs text-gray-500 dark:text-gray-400">★ '.number_format((float) ($propertyRating ?? 0), 1).' - '.__('admin.overall').'</p>'
                    .'</div>';

                // Guest info
                $html .= '<div class="grid grid-cols-2 gap-4 rounded-lg border border-gray-200 p-4 dark:border-gray-700">'
                    .'<div><p class="text-xs text-gray-500">'.__('admin.guest_name').'</p><p class="font-medium text-gray-900 dark:text-white">'.e($record->user?->name ?? '-').'</p></div>'
                    .'<div><p class="text-xs text-gray-500">'.__('admin.booking_id').'</p><p class="font-medium text-primary-600">'.e($record->booking?->booking_number ?? '-').'</p></div>'
                    .'</div>';

                // Rating + review
                $html .= '<div class="flex items-center justify-between">'
                    .'<p class="text-sm font-semibold">★ '.$record->rating.'</p>'
                    .'<p class="text-sm text-gray-500">'.$record->created_at->format('d M Y').'</p>'
                    .'</div>'
                    .'<p class="text-sm text-gray-700 dark:text-gray-300">'.e($record->review).'</p>';

                // Images
                if ($record->images->isNotEmpty()) {
                    $html .= '<div class="flex flex-wrap gap-2">';
                    foreach ($record->images as $image) {
                        $url = e(asset('storage/'.$image->image_path));
                        $html .= '<img src="'.$url.'" class="h-20 w-20 rounded-lg object-cover" />';
                    }
                    $html .= '</div>';
                }

                // Removal reason
                if ($record->removal_reason) {
                    $html .= '<div class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/20">'
                        .'<p class="text-xs font-medium text-amber-700 dark:text-amber-400">'.__('admin.reason').': '.e($record->removal_reason).'</p>'
                        .'<p class="mt-1 text-sm text-amber-600 dark:text-amber-300">'.e($record->removal_description).'</p>'
                        .'</div>';
                }

                $html .= '</div>';

                return [
                    Text::make(new HtmlString($html))
                        ->extraAttributes(['class' => 'block w-full']),
                ];
            });
    }
}
