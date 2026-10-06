<?php

namespace App\Filament\Pages;

use App\Enums\ReviewRemovalStatus;
use App\Enums\ReviewStatus;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\Review;
use App\Models\User;
use App\Support\SystemMode;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Text;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ReviewRemovalRequestsManage extends Page implements DeclaresTopbarControls, HasTable
{
    use HasPagePermission {
        canAccess as traitCanAccess;
    }
    use InteractsWithTable;

    protected static ?string $slug = 'review-removal-requests';

    protected static ?int $navigationSort = 4;

    public static function topbarControls(): array
    {
        return ['property' => false];
    }

    protected string $view = 'filament.pages.review-removal-requests-manage';

    public static function shouldRegisterNavigation(): bool
    {
        return SystemMode::isMulti();
    }

    /**
     * Multi-mode only (2026-08-01) — see RemovedReviewsManage.php for the
     * same fix and rationale (shouldRegisterNavigation() alone doesn't
     * block direct URL access).
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
        return __('admin.removal_requests');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.removal_requests');
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
        return __('admin.removal_requests_subheading');
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
            ->with(['user', 'booking.propertyRoom.roomType', 'property', 'images'])
            ->where('removal_requested', true)
            ->where('removal_status', ReviewRemovalStatus::Requested->value)
            ->whereHas('property', fn (Builder $q) => $q->where('country_id', $user->current_country_id));

        if ($user->current_branch_id) {
            $query->where('property_id', $user->current_branch_id);
        }

        return $table
            ->query($query)
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('review')
                    ->label(__('admin.rating_and_comment'))
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
                    ->searchable()
                    ->wrap(),

                TextColumn::make('removal_reason')
                    ->label(__('admin.partner_reason'))
                    ->html()
                    ->formatStateUsing(function (Review $record): string {
                        $reason = e($record->removal_reason ?? '-');
                        $description = e($record->removal_description ?? '');

                        $warningSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#9E6006" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg>';
                        $warningIcon = '<img src="data:image/svg+xml;base64,'.base64_encode($warningSvg).'" width="18" height="18" style="flex-shrink:0;" alt="" />';

                        return '<div style="display:flex;flex-direction:column;gap:6px;">'
                            .'<span style="display:inline-flex;align-items:center;gap:6px;background-color:#FEF5E6;border-radius:0.5rem;padding:5px 10px;width:fit-content;">'
                            .$warningIcon
                            .'<span style="font-size:0.875rem;font-weight:600;color:#9E6006;">'.$reason.'</span>'
                            .'</span>'
                            .($description !== '' ? '<p style="font-size:0.875rem;color:#6b7280;">'.$description.'</p>' : '')
                            .'</div>';
                    })
                    ->wrap(),
            ])
            ->recordActions([
                $this->getViewAction(),
            ])
            ->emptyStateHeading(__('admin.no_removal_requests'))
            ->emptyStateDescription(__('admin.no_removal_requests_description'))
            ->emptyStateIcon('heroicon-o-arrow-path');
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

        $infoSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg>';
        $infoIcon = '<img src="data:image/svg+xml;base64,'.base64_encode($infoSvg).'" width="24" height="24" />';

        $html = '';

        // Reason banner
        if ($record->removal_reason) {
            $html .= '<div style="display:flex;align-items:flex-start;gap:14px;background-color:#FEF5E6;border-radius:0.75rem;padding:16px;">'
                .'<div style="display:flex;height:48px;width:48px;flex-shrink:0;align-items:center;justify-content:center;border-radius:0.75rem;background:#F59E0B;">'
                .$infoIcon
                .'</div>'
                .'<div>'
                .'<p style="font-weight:600;color:#111827;font-size:0.9375rem;">'.__('admin.reason').': '.e($record->removal_reason).'</p>'
                .'<p style="margin-top:4px;font-size:0.875rem;color:#6b7280;">'.e($record->removal_description).'</p>'
                .'</div>'
                .'</div>';
        }

        // Original review heading
        $html .= '<p style="font-weight:600;font-size:0.9375rem;color:#111827;">'.__('admin.original_review').'</p>';

        // Guest avatar + name + date | stars
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

        // Review text
        $html .= '<p style="font-size:0.875rem;color:#374151;line-height:1.6;">'.e($record->review).'</p>';

        // Images
        if ($record->images->isNotEmpty()) {
            $html .= '<div style="display:flex;flex-wrap:wrap;gap:8px;">';
            foreach ($record->images as $image) {
                $url = e(asset('storage/'.$image->image_path));
                $html .= '<img src="'.$url.'" style="height:5rem;width:5rem;border-radius:0.5rem;object-fit:cover;" />';
            }
            $html .= '</div>';
        }

        // Property + guest/room card
        $html .= '<div style="border-radius:0.75rem;background-color:#f9fafb;padding:14px;display:flex;flex-direction:column;gap:10px;">';

        // Property row
        $html .= '<div style="display:flex;align-items:center;gap:12px;">'
            .$propertyImgTag
            .'<div>'
            .'<p style="font-weight:600;color:#111827;">'.e($record->property?->name ?? '-').'</p>'
            .'<p style="font-size:0.75rem;color:#6b7280;">★ '.number_format((float) ($propertyRating ?? 0), 1).' – '.__('admin.overall').'</p>'
            .'</div>'
            .'</div>';

        // Guest + Room type grid
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

    private function getViewAction(): Action
    {
        return Action::make('view')
            ->iconButton()
            ->icon('phosphor-eye')
            ->color('gray')
            ->modalHeading(__('admin.request_details'))
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
            ->extraModalWindowAttributes(['class' => 'removal-request-modal'])
            ->modalFooterActionsAlignment(Alignment::Between)
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->extraModalFooterActions([
                $this->getRejectAction(),
                $this->getApproveAction(),
            ])
            ->schema(function (Review $record): array {
                return [
                    Text::make(new HtmlString('<div class="space-y-4">'.$this->buildReviewHtml($record).'</div>'))
                        ->extraAttributes(['class' => 'block w-full']),
                ];
            });
    }

    private function getApproveAction(): Action
    {
        return Action::make('approve')
            ->label(__('admin.approve_removal'))
            ->color('success')
            ->extraAttributes(['style' => 'flex:1;justify-content:center;'])
            ->requiresConfirmation()
            ->modalHeading(__('admin.approve_removal'))
            ->modalDescription(__('admin.approve_removal_confirmation'))
            ->modalSubmitActionLabel(__('admin.approve_removal'))
            ->action(function (Review $record): void {
                /** @var User $admin */
                $admin = auth()->user();

                $record->update([
                    'status' => ReviewStatus::Removed->value,
                    'removal_status' => ReviewRemovalStatus::Approved->value,
                    'approved_by' => $admin->id,
                    'approved_at' => now(),
                ]);

                Notification::make()
                    ->title(__('admin.review_removed_successfully'))
                    ->success()
                    ->send();

                $this->redirect(static::getUrl(), navigate: true);
            });
    }

    private function getRejectAction(): Action
    {
        return Action::make('reject')
            ->label(__('admin.reject_request'))
            ->color('danger')
            ->extraAttributes(['style' => 'flex:1;justify-content:center;'])
            ->requiresConfirmation()
            ->modalHeading(__('admin.reject_request'))
            ->modalDescription(__('admin.reject_request_confirmation'))
            ->modalSubmitActionLabel(__('admin.reject_request'))
            ->action(function (Review $record): void {
                $record->update([
                    'removal_status' => ReviewRemovalStatus::Rejected->value,
                ]);

                Notification::make()
                    ->title(__('admin.removal_request_rejected'))
                    ->success()
                    ->send();

                $this->redirect(static::getUrl(), navigate: true);
            });
    }
}
