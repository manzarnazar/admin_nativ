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
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Text;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class RemovedReviewsManage extends Page implements DeclaresTopbarControls, HasTable
{
    use HasPagePermission {
        canAccess as traitCanAccess;
    }
    use InteractsWithTable;

    protected static ?string $slug = 'removed-reviews';

    public static function topbarControls(): array
    {
        return ['property' => false];
    }

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.removed-reviews-manage';

    public static function shouldRegisterNavigation(): bool
    {
        return SystemMode::isMulti();
    }

    /**
     * Multi-mode only (2026-08-01)
     */
    public static function canAccess(): bool
    {
        if (SystemMode::isSingle()) {
            return false;
        }

        return static::traitCanAccess();
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.removed_reviews');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.removed_reviews');
    }

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return null;
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::GuestReviews);
    }

    public function getSubheading(): ?string
    {
        return __('admin.removed_reviews_subheading');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();

        $query = Review::query()
            ->with(['user', 'booking.propertyRoom.roomType', 'property', 'images', 'approvedBy'])
            ->where('status', ReviewStatus::Removed->value)
            ->whereHas('property', fn (Builder $q) => $q->where('country_id', $user->current_country_id));

        if ($user->current_branch_id) {
            $query->where('property_id', $user->current_branch_id);
        }

        return $table
            ->query($query)
            ->defaultSort('approved_at', 'desc')
            ->searchPlaceholder(__('admin.search_by_customer_name'))
            ->columns([
                TextColumn::make('review')
                    ->label(__('admin.review_summary'))
                    ->html()
                    ->formatStateUsing(function (Review $record): string {
                        $bookingNumber = e($record->booking?->booking_number ?? '-');
                        $propertyName = e($record->property?->name ?? '-');
                        $propertyUrl = $record->property_id
                            ? e(AllPropertiesView::getUrl(['record' => $record->property_id]))
                            : null;

                        $externalSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#3b82f6" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>';
                        $externalIcon = '<img src="data:image/svg+xml;base64,'.base64_encode($externalSvg).'" width="16" height="16" style="flex-shrink:0;display:inline-block;vertical-align:middle;" alt="" />';

                        $propertyHtml = $propertyUrl
                            ? '<a href="'.$propertyUrl.'" wire:navigate style="display:inline-flex;align-items:center;gap:4px;font-weight:500;color:#3b82f6;text-decoration:none;">'.$externalIcon.$propertyName.'</a>'
                            : '<span style="display:inline-flex;align-items:center;gap:4px;font-weight:500;color:#3b82f6;">'.$externalIcon.$propertyName.'</span>';

                        $rating = (float) $record->rating;
                        $fullStars = (int) floor($rating);
                        $emptyStars = 5 - $fullStars;
                        $starsHtml = '';
                        for ($i = 0; $i < $fullStars; $i++) {
                            $starsHtml .= '<span style="color:#f59e0b;">★</span>';
                        }
                        for ($i = 0; $i < $emptyStars; $i++) {
                            $starsHtml .= '<span style="color:#d1d5db;">★</span>';
                        }

                        $reviewFull = e($record->review);
                        $clampStyle = 'overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;';

                        $imageHtml = '';
                        if ($record->images->isNotEmpty()) {
                            $firstPath = $record->images->first()?->image_path;
                            $remaining = $record->images->count() - 1;
                            $imageHtml = '<div style="display:flex;align-items:center;gap:6px;margin-top:6px;">';
                            if ($firstPath) {
                                $imageHtml .= '<img src="'.e(asset('storage/'.$firstPath)).'" style="height:2.5rem;width:2.5rem;border-radius:0.5rem;object-fit:cover;" />';
                            }
                            if ($remaining > 0) {
                                $imageHtml .= '<span style="display:flex;height:2.5rem;min-width:2.5rem;align-items:center;justify-content:center;border-radius:0.5rem;background:#1f2937;padding:0 6px;font-size:0.75rem;font-weight:500;color:white;">+'.$remaining.'</span>';
                            }
                            $imageHtml .= '</div>';
                        }

                        return '<div style="display:flex;flex-direction:column;gap:6px;">'
                            .'<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">'
                            .'<span style="background-color:#F7F7F7;border-radius:0.5rem;padding:3px 10px;font-size:0.875rem;color:#374151;">'.__('admin.booking_id').': '.$bookingNumber.'</span>'
                            .'<span style="color:#d1d5db;font-size:1rem;">·</span>'
                            .$propertyHtml
                            .'</div>'
                            .'<div style="display:flex;align-items:center;gap:4px;">'
                            .$starsHtml
                            .'<span style="margin-left:4px;font-size:0.875rem;font-weight:600;color:#374151;">'.$rating.'</span>'
                            .'</div>'
                            .'<p style="font-size:0.875rem;color:#374151;'.$clampStyle.'" title="'.$reviewFull.'">'.$reviewFull.'</p>'
                            .$imageHtml
                            .'</div>';
                    })
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%")))
                    ->wrap(),

                TextColumn::make('user.name')
                    ->label(__('admin.customer_and_review_date'))
                    ->html()
                    ->formatStateUsing(function (Review $record): string {
                        $name = e($record->user?->name ?? '-');
                        $initial = mb_strtoupper(mb_substr($record->user?->name ?? 'U', 0, 1));
                        $date = $record->created_at->format('d M Y');
                        $avatarUrl = $record->user?->avatar
                            ? e($record->user->getFilamentAvatarUrl())
                            : null;

                        $avatarHtml = $avatarUrl
                            ? '<img src="'.$avatarUrl.'" style="height:2.5rem;width:2.5rem;flex-shrink:0;border-radius:9999px;object-fit:cover;" alt="" />'
                            : '<div style="display:flex;height:2.5rem;width:2.5rem;flex-shrink:0;align-items:center;justify-content:center;border-radius:9999px;background:#3b82f6;font-size:0.875rem;font-weight:600;color:white;">'.$initial.'</div>';

                        return '<div style="display:flex;align-items:center;gap:12px;">'
                            .$avatarHtml
                            .'<div>'
                            .'<p style="font-weight:600;color:#111827;">'.$name.'</p>'
                            .'<p style="font-size:0.75rem;color:#6b7280;">'.$date.'</p>'
                            .'</div>'
                            .'</div>';
                    })
                    ->wrap(),

                TextColumn::make('removal_reason')
                    ->label(__('admin.removed_reason'))
                    ->html()
                    ->formatStateUsing(function (Review $record): string {
                        $reason = e($record->removal_reason ?? '-');
                        $description = e($record->removal_description ?? '');

                        $warningSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#D63031" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg>';
                        $warningIcon = '<img src="data:image/svg+xml;base64,'.base64_encode($warningSvg).'" width="18" height="18" style="flex-shrink:0;" alt="" />';

                        return '<div style="display:flex;flex-direction:column;gap:6px;">'
                            .'<span style="display:inline-flex;align-items:center;gap:6px;background-color:#FBEAEA;border-radius:0.5rem;padding:5px 10px;width:fit-content;">'
                            .$warningIcon
                            .'<span style="font-size:0.875rem;font-weight:600;color:#D63031;">'.$reason.'</span>'
                            .'</span>'
                            .($description !== '' ? '<p style="font-size:0.875rem;color:#6b7280;">'.$description.'</p>' : '')
                            .'</div>';
                    })
                    ->wrap(),

                TextColumn::make('approvedBy.name')
                    ->label(__('admin.removed_by'))
                    ->html()
                    ->formatStateUsing(function (Review $record): string {
                        $name = e($record->approvedBy?->name ?? '-');
                        $date = $record->approved_at?->format('d M Y') ?? '-';

                        return '<div>'
                            .'<p style="font-weight:600;color:#111827;">'.$name.'</p>'
                            .'<p style="font-size:0.75rem;color:#6b7280;">'.$date.'</p>'
                            .'</div>';
                    })
                    ->wrap(),
            ])
            ->recordActions([
                $this->getViewAction(),
            ])
            ->filters([
                Filter::make('date_filter')
                    ->label(__('admin.period'))
                    ->form([
                        DatePicker::make('custom_date')
                            ->label(__('admin.custom_date'))
                            ->placeholder(__('admin.custom_date'))
                            ->native(false),
                        Select::make('period')
                            ->label(__('admin.period'))
                            ->placeholder(__('admin.all_time'))
                            ->options([
                                'today' => __('admin.today'),
                                'week' => __('admin.this_week'),
                                'month' => __('admin.this_month'),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['custom_date'] ?? null) {
                            return $query->whereDate('approved_at', $data['custom_date']);
                        }

                        return match ($data['period'] ?? null) {
                            'today' => $query->whereDate('approved_at', Carbon::today()),
                            'week' => $query->whereBetween('approved_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]),
                            'month' => $query->whereMonth('approved_at', Carbon::now()->month)->whereYear('approved_at', Carbon::now()->year),
                            default => $query,
                        };
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if ($data['custom_date'] ?? null) {
                            return __('admin.custom_date').': '.Carbon::parse($data['custom_date'])->format('d M Y');
                        }

                        return match ($data['period'] ?? null) {
                            'today' => __('admin.today'),
                            'week' => __('admin.this_week'),
                            'month' => __('admin.this_month'),
                            default => null,
                        };
                    }),
            ])
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('removed-reviews')
                    ->exports([
                        'user.name' => 'Customer',
                        'property.name' => 'Property',
                        'rating' => 'Rating',
                        'review' => 'Review',
                        'removal_reason' => 'Removal Reason',
                        'removal_description' => 'Removal Description',
                        'approvedBy.name' => 'Removed By',
                        'approved_at' => ['label' => 'Removed At', 'formatter' => fn (Review $record): string => $record->approved_at?->format('d M Y') ?? '-'],
                    ])
                    ->toActionGroup(),
            ])
            ->emptyStateHeading(__('admin.no_removed_reviews'))
            ->emptyStateDescription(__('admin.no_removed_reviews_description'))
            ->emptyStateIcon('heroicon-o-star');
    }

    private function getViewAction(): Action
    {
        return Action::make('view')
            ->iconButton()
            ->icon('phosphor-eye')
            ->color('gray')
            ->modalHeading(__('admin.removed_review_details'))
            ->modalDescription(function (Review $record): HtmlString {
                $bookingNumber = e($record->booking?->booking_number ?? '-');
                $propertyName = e($record->property?->name ?? '-');
                $propertyUrl = $record->property_id
                    ? e(AllPropertiesView::getUrl(['record' => $record->property_id]))
                    : null;

                $propertyHtml = $propertyUrl
                    ? '<a href="'.$propertyUrl.'" wire:navigate style="color:#3b82f6;text-decoration:none;font-weight:500;">'.$propertyName.'</a>'
                    : '<span style="color:#3b82f6;font-weight:500;">'.$propertyName.'</span>';

                return new HtmlString(
                    '<span style="display:inline-flex;align-items:center;gap:8px;font-size:0.875rem;color:#374151;">'
                        .__('admin.booking_id').': '.$bookingNumber
                        .' <span style="color:#d1d5db;">·</span> '
                        .$propertyHtml
                        .'</span>'
                );
            })
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalWidth('lg')
            ->modalFooterActionsAlignment(Alignment::End)
            ->modalSubmitAction(false)
            ->modalCancelAction(
                fn (Action $action): Action => $action
                    ->label(__('admin.close_details'))
                    ->extraAttributes(['style' => 'background:#111827;color:#fff;border-color:#111827;'])
            )
            ->schema(function (Review $record): array {
                return [
                    Text::make(new HtmlString('<div class="space-y-4">'.$this->buildReviewHtml($record).'</div>'))
                        ->extraAttributes(['class' => 'block w-full']),
                ];
            });
    }

    private function buildReviewHtml(Review $record): string
    {
        $roomTypeName = $record->booking?->propertyRoom?->roomType?->name ?? '-';

        $propertyRating = Review::query()
            ->where('property_id', $record->property_id)
            ->where('status', ReviewStatus::Published->value)
            ->avg('rating');

        $propertyImageUrl = $record->property?->images->first()?->image_path;
        $propertyImgTag = $propertyImageUrl
            ? '<img src="'.e(asset('storage/'.$propertyImageUrl)).'" style="height:3.5rem;width:3.5rem;flex-shrink:0;border-radius:0.75rem;object-fit:cover;" />'
            : '<div style="height:3.5rem;width:3.5rem;flex-shrink:0;border-radius:0.75rem;background:#e5e7eb;"></div>';

        $avatarUrl = $record->user?->avatar ? e($record->user->getFilamentAvatarUrl()) : null;
        $initial = mb_strtoupper(mb_substr($record->user?->name ?? 'U', 0, 1));
        $avatarTag = $avatarUrl
            ? '<img src="'.$avatarUrl.'" style="height:2.25rem;width:2.25rem;flex-shrink:0;border-radius:9999px;object-fit:cover;" />'
            : '<div style="display:flex;height:2.25rem;width:2.25rem;flex-shrink:0;align-items:center;justify-content:center;border-radius:9999px;background:#3b82f6;font-size:0.875rem;font-weight:600;color:white;">'.$initial.'</div>';

        $rating = (float) $record->rating;
        $fullStars = (int) floor($rating);
        $emptyStars = 5 - $fullStars;
        $starsHtml = '';
        for ($i = 0; $i < $fullStars; $i++) {
            $starsHtml .= '<span style="color:#f59e0b;font-size:1rem;">★</span>';
        }
        for ($i = 0; $i < $emptyStars; $i++) {
            $starsHtml .= '<span style="color:#d1d5db;font-size:1rem;">★</span>';
        }

        $personSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#9ca3af" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>';
        $personIcon = '<img src="data:image/svg+xml;base64,'.base64_encode($personSvg).'" width="16" height="16" />';

        $bedSvgContent = str_replace('fill="#20B364"', 'fill="#9ca3af"', (string) file_get_contents(resource_path('svg/bed.svg')));
        $bedIcon = '<img src="data:image/svg+xml;base64,'.base64_encode($bedSvgContent).'" width="16" height="16" />';

        $trashSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>';
        $trashIcon = '<img src="data:image/svg+xml;base64,'.base64_encode($trashSvg).'" width="24" height="24" />';

        $removedByName = e($record->approvedBy?->name ?? '-');
        $removeDate = $record->approved_at?->format('d M Y') ?? '-';

        $html = '';

        if ($record->removal_reason) {
            $html .= '<div style="background-color:#FBEAEA;border-radius:0.75rem;padding:16px;">'
                .'<div style="display:flex;align-items:flex-start;gap:14px;">'
                .'<div style="display:flex;height:48px;width:48px;flex-shrink:0;align-items:center;justify-content:center;border-radius:0.75rem;background:#D63031;">'
                .$trashIcon
                .'</div>'
                .'<div>'
                .'<p style="font-weight:600;color:#111827;font-size:0.9375rem;">'.__('admin.reason').': '.e($record->removal_reason).'</p>'
                .'<p style="margin-top:4px;font-size:0.875rem;color:#6b7280;">'.e($record->removal_description).'</p>'
                .'</div>'
                .'</div>'
                .'<div style="display:flex;justify-content:space-between;margin-top:12px;padding-top:12px;border-top:1px solid #F5C0C0;">'
                .'<span style="font-size:0.8125rem;color:#374151;">'.__('admin.remove_by').': '.$removedByName.'</span>'
                .'<span style="font-size:0.8125rem;color:#374151;">'.__('admin.remove_date').': '.$removeDate.'</span>'
                .'</div>'
                .'</div>';
        }

        $html .= '<p style="font-weight:600;font-size:0.9375rem;color:#111827;">'.__('admin.original_review').'</p>';

        $html .= '<div style="display:flex;align-items:center;justify-content:space-between;">'
            .'<div style="display:flex;align-items:center;gap:10px;">'
            .$avatarTag
            .'<div>'
            .'<p style="font-weight:600;color:#111827;font-size:0.875rem;">'.e($record->user?->name ?? '-').'</p>'
            .'<p style="font-size:0.75rem;color:#6b7280;">'.$record->created_at->format('d M Y').'</p>'
            .'</div>'
            .'</div>'
            .'<div style="display:flex;align-items:center;gap:4px;">'
            .$starsHtml
            .'<span style="margin-left:4px;font-weight:600;color:#111827;">'.$rating.'</span>'
            .'</div>'
            .'</div>';

        $html .= '<p style="font-size:0.875rem;color:#374151;line-height:1.6;">'.e($record->review).'</p>';

        if ($record->images->isNotEmpty()) {
            $html .= '<div style="display:flex;flex-wrap:wrap;gap:8px;">';
            foreach ($record->images as $image) {
                $url = e(asset('storage/'.$image->image_path));
                $html .= '<img src="'.$url.'" style="height:5rem;width:5rem;border-radius:0.5rem;object-fit:cover;" />';
            }
            $html .= '</div>';
        }

        $html .= '<div style="border-radius:0.75rem;background-color:#f9fafb;padding:14px;display:flex;flex-direction:column;gap:10px;">';

        $html .= '<div style="display:flex;align-items:center;gap:12px;">'
            .$propertyImgTag
            .'<div>'
            .'<p style="font-weight:600;color:#111827;">'.e($record->property?->name ?? '-').'</p>'
            .'<p style="font-size:0.75rem;color:#6b7280;">★ '.number_format((float) ($propertyRating ?? 0), 1).' – '.__('admin.overall').'</p>'
            .'</div>'
            .'</div>';

        $html .= '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">'
            .'<div style="display:flex;align-items:center;gap:10px;border-radius:0.75rem;border:1px solid #e5e7eb;background:white;padding:12px;">'
            .'<div style="flex-shrink:0;">'.$personIcon.'</div>'
            .'<div>'
            .'<p style="font-size:0.75rem;color:#6b7280;">'.__('admin.guest_name').'</p>'
            .'<p style="font-weight:600;color:#111827;font-size:0.875rem;">'.e($record->user?->name ?? '-').'</p>'
            .'</div>'
            .'</div>'
            .'<div style="display:flex;align-items:center;gap:10px;border-radius:0.75rem;border:1px solid #e5e7eb;background:white;padding:12px;">'
            .'<div style="flex-shrink:0;">'.$bedIcon.'</div>'
            .'<div>'
            .'<p style="font-size:0.75rem;color:#6b7280;">'.__('admin.room_type').'</p>'
            .'<p style="font-weight:600;color:#111827;font-size:0.875rem;">'.e($roomTypeName).'</p>'
            .'</div>'
            .'</div>'
            .'</div>';

        $html .= '</div>';

        return $html;
    }
}
