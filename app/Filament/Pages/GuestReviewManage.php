<?php

namespace App\Filament\Pages;

use App\Enums\ReviewStatus;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\Review;
use App\Models\User;
use App\Support\SystemMode;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Text;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

class GuestReviewManage extends Page implements DeclaresTopbarControls, HasTable
{
    use HasPagePermission, InteractsWithTable;

    protected static ?string $slug = 'guest-reviews';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.guest-review-manage';

    public static function topbarControls(): array
    {
        if (SystemMode::isMulti()) {
            return ['property' => false];
        }

        return [];
    }

    public function getTitle(): string|Htmlable
    {
        return __(SystemMode::isMulti() ? 'admin.all_reviews' : 'admin.guest_reviews');
    }

    public static function getNavigationLabel(): string
    {
        return __(SystemMode::isMulti() ? 'admin.all_reviews' : 'admin.guest_reviews');
    }

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return null;
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::GuestReviews);
    }

    public function getHeading(): string|Htmlable
    {
        return __(SystemMode::isMulti() ? 'admin.all_reviews' : 'admin.guest_reviews');
    }

    public function getSubheading(): ?string
    {
        return __('admin.guest_reviews_subheading');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getHasReviews(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        if (SystemMode::isMulti()) {
            return Review::query()
                ->whereHas('property', fn (Builder $q) => $q->where('country_id', $user->current_country_id))
                ->exists();
        }

        return Review::query()
            ->whereHas('property', fn (Builder $q) => $q->where('country_id', $user->current_country_id))
            ->when($user->current_branch_id, fn ($q, $id) => $q->where('property_id', $id))
            ->exists();
    }

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();

        if (SystemMode::isSingle()) {
            $query = Review::query()
                ->with(['user', 'booking.propertyRoom.roomType', 'property', 'images'])
                ->whereHas('property', fn (Builder $q) => $q->where('country_id', $user->current_country_id));

            if ($user->current_branch_id) {
                $query->where('property_id', $user->current_branch_id);
            }
        } else {
            $query = Review::query()
                ->with(['user', 'booking.propertyRoom.roomType', 'property.images', 'images'])
                ->whereHas('property', fn (Builder $q) => $q->where('country_id', $user->current_country_id));
        }

        $propertyColumn = SystemMode::isSingle()
            ? TextColumn::make('property.name')
                ->label(__('admin.room_details'))
                ->description(fn (Review $record): ?string => $record->booking?->propertyRoom?->roomType?->name)
                ->searchable()
                ->wrap()
            : TextColumn::make('property.name')
                ->label(__('admin.property'))
                ->html()
                ->formatStateUsing(function (Review $record): string {
                    $property = $record->property;
                    if (! $property) {
                        return '-';
                    }

                    $imageUrl = $property->images->first()?->image_path;
                    $imgTag = $imageUrl
                        ? '<img src="'.e(asset('storage/'.$imageUrl)).'" class="h-10 w-10 shrink-0 rounded-lg object-cover" />'
                        : '<div class="h-10 w-10 shrink-0 rounded-lg bg-gray-200 dark:bg-gray-700"></div>';

                    $overallRating = Review::query()
                        ->where('property_id', $record->property_id)
                        ->where('status', ReviewStatus::Published->value)
                        ->avg('rating');

                    return '<div class="flex items-center gap-3">'
                        .$imgTag
                        .'<div>'
                        .'<p class="font-medium text-gray-900 dark:text-white">'.e($property->name).'</p>'
                        .'<p class="text-xs text-gray-700 dark:text-gray-400">★ '.number_format((float) ($overallRating ?? 0), 1).' · '.__('admin.overall').'</p>'
                        .'</div>'
                        .'</div>';
                })
                ->searchable()
                ->wrap();

        $filters = array_values(array_filter([
            SystemMode::isMulti() ? SelectFilter::make('period')
                ->label(__('admin.period'))
                ->placeholder(__('admin.all_time'))
                ->options([
                    'today' => __('admin.today'),
                    'week' => __('admin.this_week'),
                    'month' => __('admin.this_month'),
                ])
                ->query(function ($query, array $data): void {
                    match ($data['value'] ?? null) {
                        'today' => $query->whereDate('reviews.created_at', Carbon::today()),
                        'week' => $query->whereBetween('reviews.created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]),
                        'month' => $query->whereMonth('reviews.created_at', Carbon::now()->month)->whereYear('reviews.created_at', Carbon::now()->year),
                        default => null,
                    };
                }) : null,
            SelectFilter::make('status')
                ->label(__('admin.status'))
                ->options(collect(ReviewStatus::cases())->mapWithKeys(fn (ReviewStatus $s) => [$s->value => $s->label()])->toArray()),
        ]));

        return $table
            ->query($query)
            ->defaultSort('created_at', 'desc')
            ->searchPlaceholder(__('admin.search_by_property_or_customer_name'))
            ->columns([
                TextColumn::make('id')
                    ->label(__('admin.id'))
                    ->sortable()
                    ->color('primary'),

                $propertyColumn,

                TextColumn::make('user.name')
                    ->label(__('admin.room_guest_name'))
                    ->html()
                    ->extraAttributes(['style' => 'min-width: 220px;'])
                    ->formatStateUsing(function (Review $record): string {
                        $guestName = e($record->user?->name ?? '-');
                        $bookingNumber = e($record->booking?->booking_number ?? '-');
                        $date = $record->created_at->format('d M Y');
                        $roomTypeName = e($record->booking?->propertyRoom?->roomType?->name ?? '-');

                        $bookingUrl = $record->booking
                            ? e(BookingView::getUrl(['record' => $record->booking->id]))
                            : null;

                        $bookingIdHtml = $bookingUrl
                            ? '<a href="'.$bookingUrl.'" wire:navigate class="text-xs text-primary-600 hover:underline">Booking ID: '.$bookingNumber.'</a>'
                            : '<span class="text-xs text-primary-600">Booking ID: '.$bookingNumber.'</span>';

                        $bedSvgContent = str_replace('fill="#20B364"', 'fill="#374151"', (string) file_get_contents(resource_path('svg/bed.svg')));
                        $bedIcon = '<img src="data:image/svg+xml;base64,'.base64_encode($bedSvgContent).'" width="14" height="14" class="shrink-0" alt="" />';

                        return '<div class="space-y-0.5">'
                            .'<p class="font-medium text-gray-900 dark:text-white">'.$guestName.'</p>'
                            .$bookingIdHtml
                            .'<p class="text-xs text-gray-500">'.$date.'</p>'
                            .'<div class="mt-1 flex items-center gap-1.5 text-xs text-gray-500">'
                            .$bedIcon
                            .'<span>'.$roomTypeName.'</span>'
                            .'</div>'
                            .'</div>';
                    })
                    ->searchable()
                    ->wrap(),

                TextColumn::make('rating')
                    ->label(__('admin.rating_and_review'))
                    ->html()
                    ->formatStateUsing(function (Review $record): string {
                        $rating = (float) $record->rating;
                        $fullStars = (int) floor($rating);
                        $emptyStars = 5 - $fullStars;

                        $starsHtml = '';
                        for ($i = 0; $i < $fullStars; $i++) {
                            $starsHtml .= '<span class="text-amber-400">★</span>';
                        }
                        for ($i = 0; $i < $emptyStars; $i++) {
                            $starsHtml .= '<span class="text-gray-300 dark:text-gray-600">★</span>';
                        }

                        $reviewFull = e($record->review);
                        $reviewClampStyle = 'overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;';

                        $images = $record->images;
                        $imageHtml = '';
                        if ($images->isNotEmpty()) {
                            $firstPath = $images->first()?->image_path;
                            $remaining = $images->count() - 1;
                            $imageHtml = '<div class="mt-1.5 flex items-center gap-1.5">';
                            if ($firstPath) {
                                $imageHtml .= '<img src="'.e(asset('storage/'.$firstPath)).'" class="h-8 w-8 rounded object-cover" />';
                            }
                            if ($remaining > 0) {
                                $imageHtml .= '<span class="flex h-8 min-w-[2rem] items-center justify-center rounded bg-gray-800 px-1.5 text-xs font-medium text-white">+'.$remaining.'</span>';
                            }
                            $imageHtml .= '</div>';
                        }

                        return '<div>'
                            .'<div class="flex items-center gap-1">'
                            .$starsHtml
                            .'<span class="ml-1 text-sm font-medium text-gray-700 dark:text-gray-300">'.$rating.'</span>'
                            .'</div>'
                            .'<p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400" style="'.$reviewClampStyle.'" title="'.$reviewFull.'">'.$reviewFull.'</p>'
                            .$imageHtml
                            .'</div>';
                    })
                    ->wrap(),

                TextColumn::make('status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ReviewStatus::from($state)->label())
                    ->color(fn (string $state): string => ReviewStatus::from($state)->color())
                    ->description(function (Review $record): ?string {
                        if ($record->status !== ReviewStatus::Removed->value) {
                            return null;
                        }

                        $adminName = $record->approved_by
                            ? (User::find($record->approved_by)?->name ?? 'Admin')
                            : null;

                        if (! $adminName || ! $record->approved_at) {
                            return null;
                        }

                        return 'by '.$adminName.' · '.$record->approved_at->format('d M Y');
                    }),
            ])
            ->filters($filters)
            ->recordActions(array_values(array_filter([
                $this->getViewAction(),
                SystemMode::isSingle() ? $this->getEditStatusAction() : null,
                SystemMode::isSingle() ? $this->getDeleteAction() : null,
            ])))
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('guest-reviews')
                    ->exports([
                        'id' => 'ID',
                        'property.name' => 'Property',
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
            ->emptyStateIcon('heroicon-o-star')
            ->defaultPaginationPageOption(10);
    }

    private function buildImagesHtml(Review $record): string
    {
        if ($record->images->isEmpty()) {
            return '';
        }

        $html = '<div x-data="{ preview: null }">';
        $html .= '<div class="flex flex-wrap gap-2">';

        foreach ($record->images as $image) {
            $url = e(asset('storage/'.$image->image_path));
            $html .= '<img src="'.$url.'" '
                ."@click=\"preview = '".$url."'\" "
                .'class="h-24 w-24 cursor-pointer rounded-lg object-cover transition hover:opacity-80" />';
        }

        $html .= '</div>';

        $html .= '<div x-show="preview" x-transition.opacity '
            .'@click="preview = null" @keydown.escape.window="preview = null" '
            .'class="fixed inset-0 flex items-center justify-center p-4" '
            .'style="z-index: 9999; display: none; background-color: rgba(0, 0, 0, 0.8);">'
            .'<button type="button" @click="preview = null" '
            .'class="absolute right-4 top-4 text-4xl leading-none text-white hover:text-gray-300">&times;</button>'
            .'<img :src="preview" @click.stop alt="" '
            .'style="max-height: 90vh; max-width: 90vw;" class="rounded-lg object-contain" />'
            .'</div>';

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
            ->modalDescription(function (Review $record): HtmlString {
                $bookingNumber = e($record->booking?->booking_number ?? '-');
                $bookingUrl = $record->booking
                    ? e(BookingView::getUrl(['record' => $record->booking->id]))
                    : null;
                $bookingIdHtml = $bookingUrl
                    ? '<a href="'.$bookingUrl.'" wire:navigate style="font-size:0.875rem;font-weight:500;color:var(--brand-primary);text-decoration:none;">'.__('admin.booking_id').': '.$bookingNumber.'</a>'
                    : '<span style="font-size:0.875rem;font-weight:500;color:var(--brand-primary);">'.__('admin.booking_id').': '.$bookingNumber.'</span>';
                $statusLabel = ReviewStatus::from($record->status)->label();
                $badgeBg = match ($record->status) {
                    ReviewStatus::Published->value => '#dcfce7',
                    ReviewStatus::Removed->value => '#fee2e2',
                    default => '#f3f4f6',
                };
                $badgeColor = match ($record->status) {
                    ReviewStatus::Published->value => '#16a34a',
                    ReviewStatus::Removed->value => '#dc2626',
                    default => '#374151',
                };

                return new HtmlString(
                    '<span style="display:flex;align-items:center;gap:0.75rem;">'
                    .$bookingIdHtml
                    .'<span style="display:inline-flex;align-items:center;border-radius:9999px;padding:0.125rem 0.625rem;font-size:0.75rem;font-weight:500;background-color:'.$badgeBg.';color:'.$badgeColor.';">'.$statusLabel.'</span>'
                    .'</span>'
                );
            })
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalWidth('lg')
            ->modalSubmitAction(false)
            ->modalFooterActionsAlignment(Alignment::End)
            ->modalCancelAction(fn (Action $action): Action => $action
                ->label(__('admin.close_details'))
                ->extraAttributes(['style' => 'background:#111827;color:#fff;border-color:#111827;'])
            )
            ->schema(function (Review $record): array {
                $roomTypeName = $record->booking?->propertyRoom?->roomType?->name ?? '-';

                $propertyRating = Review::query()
                    ->where('property_id', $record->property_id)
                    ->where('status', ReviewStatus::Published->value)
                    ->avg('rating');

                $propertyImageUrl = $record->property?->images->first()?->image_path;
                $propertyImgTag = $propertyImageUrl
                    ? '<img src="'.e(asset('storage/'.$propertyImageUrl)).'" class="h-16 w-16 shrink-0 rounded-xl object-cover" />'
                    : '<div class="h-16 w-16 shrink-0 rounded-xl bg-gray-200 dark:bg-gray-600"></div>';

                $rating = (float) $record->rating;
                $fullStars = (int) floor($rating);
                $emptyStars = 5 - $fullStars;
                $starsHtml = '';
                for ($i = 0; $i < $fullStars; $i++) {
                    $starsHtml .= '<span style="color:#f59e0b;font-size:1.125rem;">★</span>';
                }
                for ($i = 0; $i < $emptyStars; $i++) {
                    $starsHtml .= '<span style="color:#d1d5db;font-size:1.125rem;">★</span>';
                }

                $bedSvgContent = str_replace('fill="#20B364"', 'fill="#9ca3af"', (string) file_get_contents(resource_path('svg/bed.svg')));
                $bedIcon = '<img src="data:image/svg+xml;base64,'.base64_encode($bedSvgContent).'" width="16" height="16" class="shrink-0" alt="" />';

                $personIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#9ca3af" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>';

                $html = '<div class="space-y-4">';

                $html .= '<div class="space-y-3 rounded-2xl p-4" style="background-color:#f9fafb;">';

                $html .= '<div class="flex items-center gap-3">'
                    .$propertyImgTag
                    .'<div>'
                    .'<p class="font-semibold text-gray-900">'.e($record->property?->name ?? '-').'</p>'
                    .'<p class="text-sm text-gray-500">★ '.number_format((float) ($propertyRating ?? 0), 1).' - '.__('admin.overall').'</p>'
                    .'</div>'
                    .'</div>';

                $html .= '<div class="grid grid-cols-2 gap-3">'
                    .'<div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white p-4">'
                    .'<div class="shrink-0">'.$personIcon.'</div>'
                    .'<div>'
                    .'<p class="text-xs text-gray-500">'.__('admin.guest_name').'</p>'
                    .'<p class="font-semibold text-gray-900">'.e($record->user?->name ?? '-').'</p>'
                    .'</div>'
                    .'</div>'
                    .'<div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white p-4">'
                    .'<div class="shrink-0">'.$bedIcon.'</div>'
                    .'<div>'
                    .'<p class="text-xs text-gray-500">'.__('admin.room_type').'</p>'
                    .'<p class="font-semibold text-gray-900">'.e($roomTypeName).'</p>'
                    .'</div>'
                    .'</div>'
                    .'</div>';

                $html .= '</div>';

                $html .= '<div class="flex items-center justify-between">'
                    .'<div class="flex items-center gap-0.5">'
                    .$starsHtml
                    .'<span class="ml-1 font-semibold text-gray-900 dark:text-white">'.$rating.'</span>'
                    .'</div>'
                    .'<p class="text-sm text-gray-500">'.$record->created_at->format('d M Y').'</p>'
                    .'</div>';

                $html .= '<p class="text-sm text-gray-700 dark:text-gray-300">'.e($record->review).'</p>';

                $html .= $this->buildImagesHtml($record);

                $html .= '</div>';

                return [
                    Text::make(new HtmlString($html))
                        ->extraAttributes(['class' => 'block w-full']),
                ];
            });
    }

    private function getEditStatusAction(): Action
    {
        return Action::make('editStatus')
            ->iconButton()
            ->icon('phosphor-pencil-simple-line')
            ->color('gray')
            ->disabled(static::disabledUnlessCanEdit())
            ->modalHeading(__('admin.edit_review_status'))
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalWidth('lg')
            ->modalSubmitActionLabel(__('admin.save_review'))
            ->modalFooterActionsAlignment(Alignment::End)
            ->fillForm(fn (Review $record): array => [
                'status' => $record->status,
            ])
            ->schema(function (Review $record): array {
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
                    .'<p class="text-sm">★ <span class="font-semibold">'.$record->rating.'</span></p>'
                    .'<p class="text-sm text-gray-500">'.$record->created_at->format('d M Y').'</p>'
                    .'</div>';

                $html .= '<p class="text-sm text-gray-700 dark:text-gray-300">'.e($record->review).'</p>';

                $html .= $this->buildImagesHtml($record);

                $html .= '</div>';

                return [
                    Text::make(new HtmlString($html))
                        ->extraAttributes(['class' => 'block w-full']),

                    Radio::make('status')
                        ->label(__('admin.status'))
                        ->options([
                            ReviewStatus::Published->value => ReviewStatus::Published->label(),
                            ReviewStatus::Removed->value => ReviewStatus::Removed->label(),
                        ])
                        ->required()
                        ->inline(),
                ];
            })
            ->action(function (Review $record, array $data): void {
                $record->update(['status' => $data['status']]);

                Notification::make()
                    ->title(__('admin.review_status_updated'))
                    ->success()
                    ->send();
            });
    }

    private function getDeleteAction(): Action
    {
        return Action::make('delete')
            ->extraModalWindowAttributes(['class' => 'fi-delete-modal-centered'])
            ->iconButton()
            ->icon('phosphor-trash')
            ->color('gray')
            ->before(static::enforceDeletePermission())
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-trash')
            ->modalIconColor('danger')
            ->modalHeading(__('admin.delete_review'))
            ->modalDescription(__('admin.delete_review_confirmation'))
            ->modalSubmitActionLabel(__('admin.yes_delete'))
            ->modalCancelActionLabel(__('admin.cancel'))
            ->modalSubmitAction(fn (Action $action) => $action->color('danger'))
            ->modalFooterActionsAlignment(Alignment::Center)
            ->modalCancelAction(fn (Action $action) => $action->extraAttributes(['class' => 'order-first']))
            ->action(function (Review $record): void {
                $record->delete();

                Notification::make()
                    ->title(__('admin.review_deleted'))
                    ->success()
                    ->send();
            });
    }
}
