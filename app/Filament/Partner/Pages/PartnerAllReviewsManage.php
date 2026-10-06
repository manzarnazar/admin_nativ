<?php

namespace App\Filament\Partner\Pages;

use App\Enums\ReviewRemovalStatus;
use App\Enums\ReviewStatus;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\RequiresApprovedPartner;
use App\Filament\Partner\Enums\NavigationGroup;
use App\Models\Partner;
use App\Models\Review;
use App\Models\User;
use App\Support\PartnerContext;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
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

class PartnerAllReviewsManage extends Page implements HasTable
{
    use InteractsWithTable;
    use RequiresApprovedPartner;

    protected static ?string $slug = 'reviews';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.partner.pages.partner-reviews-manage';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::ReviewMonitoring;
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.all_reviews');
    }

    public static function getNavigationIcon(): string|Htmlable|null
    {
        return null;
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.guest_reviews');
    }

    public function getSubheading(): ?string
    {
        return __('admin.guest_reviews_subheading');
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

    public function hasReviews(): bool
    {
        $partner = $this->getPartner();
        $countryId = $partner ? PartnerContext::currentCountryId($partner) : null;
        $propertyId = $this->getCurrentPropertyId();

        $query = Review::query()
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
            ->whereHas('property', fn (Builder $q) => $q->where('partner_id', $partner?->id)->where('country_id', $countryId))
            ->whereNotNull('user_id');

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
                    ->formatStateUsing(function (Review $record): string {
                        return $record->booking?->propertyRoom?->roomType?->name ?? '-';
                    })
                    ->description(function (Review $record): string {
                        $avg = Review::query()
                            ->where('property_id', $record->property_id)
                            ->where('status', ReviewStatus::Published->value)
                            ->avg('rating');

                        return '★ '.number_format((float) ($avg ?? 0), 1).' - '.__('admin.overall');
                    })
                    ->searchable(query: fn (Builder $q, string $s) => $q->whereHas('property', fn ($p) => $p->where('name', 'like', "%{$s}%")))
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

                TextColumn::make('status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ReviewStatus::from($state)->label())
                    ->color(fn (string $state): string => ReviewStatus::from($state)->color()),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.status'))
                    ->options(collect([ReviewStatus::Published, ReviewStatus::Removed])
                        ->mapWithKeys(fn (ReviewStatus $s) => [$s->value => $s->label()])->toArray()),
            ])
            ->recordActions([
                $this->getViewAction(),
                $this->getRequestRemovalAction(),
            ])
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('partner-reviews')
                    ->exports([
                        'id' => 'ID',
                        'user.name' => 'Guest Name',
                        'rating' => 'Rating',
                        'review' => 'Review',
                        'status' => ['label' => 'Status', 'formatter' => fn (Review $record): string => ReviewStatus::from($record->status)->label()],
                        'created_at' => ['label' => 'Date', 'formatter' => fn (Review $record): string => $record->created_at->format('M d, Y')],
                    ])
                    ->toActionGroup(),
            ])
            ->emptyStateHeading(__('admin.no_reviews_available'))
            ->emptyStateDescription(__('admin.no_reviews_description'))
            ->emptyStateIcon('heroicon-o-star');
    }

    private function buildReviewCardHtml(Review $record): string
    {
        $roomTypeName = $record->booking?->propertyRoom?->roomType?->name ?? '-';
        $propertyRating = Review::query()
            ->where('property_id', $record->property_id)
            ->where('status', ReviewStatus::Published->value)
            ->avg('rating');

        $html = '<div class="space-y-4">';

        $html .= '<div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">'
            .'<p class="font-semibold text-gray-900 dark:text-white">'.e($roomTypeName).'</p>'
            .'<p class="text-xs text-gray-500 dark:text-gray-400">★ '.number_format((float) ($propertyRating ?? 0), 1).' - '.__('admin.overall').'</p>'
            .'</div>';

        $html .= '<div class="grid grid-cols-2 gap-4 rounded-lg border border-gray-200 p-4 dark:border-gray-700">'
            .'<div><p class="text-xs text-gray-500 dark:text-gray-400">'.__('admin.guest_name').'</p><p class="font-medium text-gray-900 dark:text-white">'.e($record->user?->name ?? '-').'</p></div>'
            .'<div><p class="text-xs text-gray-500 dark:text-gray-400">'.__('admin.booking_id').'</p><p class="font-medium text-primary-600">'.e($record->booking?->booking_number ?? '-').'</p></div>'
            .'</div>';

        $html .= '<div class="flex items-center justify-between">'
            .'<p class="text-sm font-semibold">★ '.$record->rating.'</p>'
            .'<p class="text-sm text-gray-500">'.$record->created_at->format('d M Y').'</p>'
            .'</div>';

        $html .= '<p class="text-sm text-gray-700 dark:text-gray-300">'.e($record->review).'</p>';

        if ($record->images->isNotEmpty()) {
            $html .= '<div class="flex flex-wrap gap-2">';
            foreach ($record->images as $image) {
                $url = e(asset('storage/'.$image->image_path));
                $html .= '<img src="'.$url.'" class="h-20 w-20 rounded-lg object-cover" />';
            }
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
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
                $statusLabel = ReviewStatus::from($record->status)->label();
                $statusColor = $record->status === ReviewStatus::Published->value ? '#16a34a' : '#dc2626';

                $html = '<div class="space-y-4">'
                    .'<div class="flex items-center gap-3">'
                    .'<span class="text-sm text-gray-500">ID: '.$record->id.'</span>'
                    .'<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium" style="background-color: '.$statusColor.'20; color: '.$statusColor.';">'.$statusLabel.'</span>'
                    .'</div>'
                    .$this->buildReviewCardHtml($record)
                    .'</div>';

                return [
                    Text::make(new HtmlString($html))
                        ->extraAttributes(['class' => 'block w-full']),
                ];
            });
    }

    private function getRequestRemovalAction(): Action
    {
        return Action::make('requestRemoval')
            ->iconButton()
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->tooltip(__('admin.request_removal'))
            ->visible(fn (Review $record): bool => ! $record->removal_requested)
            ->modalHeading(__('admin.review_remove_request'))
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalWidth('lg')
            ->modalSubmitActionLabel(__('admin.send_request'))
            ->schema(function (Review $record): array {
                return [
                    Text::make(new HtmlString('<div class="mb-2">'.$this->buildReviewCardHtml($record).'</div>'))
                        ->extraAttributes(['class' => 'block w-full']),

                    TextInput::make('removal_reason')
                        ->label(__('admin.reason'))
                        ->placeholder('e.g. Abusive Language')
                        ->required()
                        ->maxLength(255),

                    Textarea::make('removal_description')
                        ->label(__('admin.description'))
                        ->placeholder(__('admin.brief_description_of_removal_reason'))
                        ->required()
                        ->rows(4),
                ];
            })
            ->action(function (Review $record, array $data): void {
                $record->update([
                    'removal_requested' => true,
                    'removal_status' => ReviewRemovalStatus::Requested->value,
                    'removal_reason' => $data['removal_reason'],
                    'removal_description' => $data['removal_description'],
                ]);

                Notification::make()
                    ->title(__('admin.removal_request_sent'))
                    ->success()
                    ->send();
            });
    }
}
