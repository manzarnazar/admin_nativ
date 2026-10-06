<?php

namespace App\Filament\Pages;

use App\Enums\ReviewStatus;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Concerns\HasReportPageConventions;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Contracts\IsReportPage;
use App\Models\Review;
use App\Models\User;
use App\Support\SystemMode;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

/**
 * Row = one Review. Shows every status (Published/Removed/Pending), not just Published — unlike
 * PropertyReport's average-rating calculation (which deliberately only counts Published reviews
 * toward a property's public score), this is a feedback LOG, and a removed/pending review is
 * still real guest feedback that was submitted, just not currently public.
 *
 * No dedicated single-review page/route exists anywhere in this codebase (GuestReviewManage.php,
 * the main admin review list, opens a modal instead) — Review ID links to the review's Booking
 * detail page instead, the closest real destination available.
 *
 * Rating uses the single-star + number style already established in PropertyReport.php/
 * AllPropertiesView.php (★ + bold number), not GuestReviewManage.php's different 5-star-row
 * convention — matches this report's own Figma design, which shows the simpler style.
 *
 * Deliberately does NOT reuse HasReportPageConventions::propertyColumn()/partnerColumn() —
 * those closures are typed `fn (Booking $record)`, and passing a Review into them throws a real
 * TypeError (verified directly, not assumed) since Review reaches `property` as its own direct
 * relation, not through a Booking. Custom Review-typed columns below do the same lookup instead.
 */
class FeedbackRatingReport extends Page implements DeclaresTopbarControls, HasTable, IsReportPage
{
    use HasPagePermission {
        canAccess as traitCanAccess;
    }
    use HasReportPageConventions;
    use InteractsWithTable;

    protected static ?string $slug = 'reports/feedback-rating';

    protected static string $permissionSlug = 'reports';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.report-table-page';

    public static function canAccess(): bool
    {
        return SystemMode::isMulti() && static::traitCanAccess();
    }

    public static function topbarControls(): array
    {
        return ['country' => true, 'property' => false];
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.report_feedback_rating_title');
    }

    public function getSubheading(): ?string
    {
        return __('admin.report_feedback_rating_desc');
    }

    private function resolveRatingColorClass(float $rating): string
    {
        return match (true) {
            $rating >= 4.0 => 'text-green-600 dark:text-green-400',
            $rating < 3.0 => 'text-red-600 dark:text-red-400',
            default => 'text-gray-950 dark:text-white',
        };
    }

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();
        $countryId = $user->current_country_id;

        $query = Review::query()
            ->with([
                'user' => fn ($q) => $q->withTrashed(),
                'property.partner.user',
                'booking',
            ])
            ->whereHas('property', fn (Builder $q) => $q->where('country_id', $countryId));

        return $table
            ->query($query)
            ->defaultSort('created_at', 'desc')
            ->searchPlaceholder(__('admin.search_by_customer_name'))
            ->columns([
                TextColumn::make('id')
                    ->label(__('admin.review_id'))
                    ->html()
                    ->state(function (Review $record): Htmlable {
                        if (! $record->booking_id) {
                            return new HtmlString('#'.str_pad((string) $record->id, 3, '0', STR_PAD_LEFT));
                        }

                        return static::linkedNameWithIcon(
                            BookingView::getUrl(['record' => $record->booking_id]),
                            '#'.str_pad((string) $record->id, 3, '0', STR_PAD_LEFT),
                        );
                    }),

                TextColumn::make('property.name')
                    ->label(__('admin.property'))
                    ->state(fn (Review $record): string => $record->property?->name ?? '-'),

                TextColumn::make('property.partner.user.name')
                    ->label(__('admin.partner'))
                    ->state(fn (Review $record): string => $record->property?->partner?->user?->name ?? '-')
                    ->toggleable(),

                TextColumn::make('user.name')
                    ->label(__('admin.customer'))
                    ->searchable()
                    ->state(fn (Review $record): string => $record->user?->name ?? '-'),

                TextColumn::make('rating')
                    ->label(__('admin.rating'))
                    ->html()
                    ->state(function (Review $record): string {
                        $rating = number_format((float) $record->rating, 1);
                        $colorClass = $this->resolveRatingColorClass((float) $record->rating);

                        return '<div class="flex items-center gap-1"><span class="text-yellow-400">★</span>'
                            .'<span class="font-semibold '.$colorClass.'">'.$rating.'</span></div>';
                    }),

                TextColumn::make('review')
                    ->label(__('admin.review_text'))
                    ->limit(60)
                    ->wrap()
                    ->tooltip(fn (Review $record): ?string => $record->review)
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label(__('admin.date'))
                    ->date('d M, Y')
                    ->toggleable(),
            ])
            ->filters($filters = [
                static::dateRangeFilter('created_at'),

                static::propertyTypeFilter($countryId),

                static::cityFilter($countryId),

                static::partnerFilter($countryId),

                SelectFilter::make('status')
                    ->label(__('admin.status'))
                    ->options(collect(ReviewStatus::cases())->mapWithKeys(fn (ReviewStatus $s) => [$s->value => $s->label()])->toArray())
                    ->native(false),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(count($filters))
            ->deferFilters(false)
            ->columnManager(true)
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('feedback-rating-report')
                    ->exports([
                        'id' => ['label' => 'Review ID', 'formatter' => fn (Review $r): string => '#'.str_pad((string) $r->id, 3, '0', STR_PAD_LEFT)],
                        'property.name' => 'Property',
                        'property.partner.user.name' => 'Partner',
                        'user.name' => 'Customer',
                        'rating' => ['label' => 'Rating', 'formatter' => fn (Review $r): string => number_format((float) $r->rating, 1)],
                        'review' => 'Review Text',
                        'created_at' => ['label' => 'Date', 'formatter' => fn (Review $r): string => $r->created_at->format('d M, Y')],
                    ])
                    ->toActionGroup(),
            ])
            ->emptyStateHeading(__('admin.no_reviews_yet'))
            ->emptyStateIcon('heroicon-o-star')
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions(static::reportPaginationOptions());
    }
}
