<?php

namespace App\Filament\Pages;

use App\Filament\Contracts\DeclaresTopbarControls;
use App\Models\Notification;
use App\Models\User;
use App\Support\DemoMode;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class NotificationsManage extends Page implements DeclaresTopbarControls, HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'notifications';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.notifications-manage';

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['property' => false];
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.notifications');
    }

    public function getHeading(): string|Htmlable
    {
        return __('admin.notifications');
    }

    public function getSubheading(): ?string
    {
        return __('admin.notifications_subheading');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();

        return $table
            ->query(
                Notification::query()
                    ->join('notification_user', 'notifications.id', '=', 'notification_user.notification_id')
                    ->where('notification_user.user_id', $user->id)
                    ->where(function ($query) use ($user): void {
                        $query->where('notifications.is_general', true)
                            ->orWhere('notifications.country_id', $user->current_country_id)
                            ->orWhereNull('notifications.country_id');
                    })
                    ->select('notifications.*', 'notification_user.read_at as is_read')
            )
            ->defaultSort('notifications.created_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label(__('admin.notification_details'))
                    ->getStateUsing(fn (Notification $record): ?string => $record->title ?? ($record->data['title'] ?? null))
                    ->description(fn (Notification $record): string => $record->body ?? ($record->data['message'] ?? ''))
                    ->wrap()
                    ->icon(fn (Notification $record): string => is_null($record->is_read) ? 'heroicon-s-bell-alert' : 'heroicon-o-bell')
                    ->iconColor(fn (Notification $record): string => is_null($record->is_read) ? 'primary' : 'gray')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $like = '%'.$search.'%';

                        return $query->where(function (Builder $q) use ($like) {
                            $q->where('notifications.title', 'like', $like)
                                ->orWhere('notifications.body', 'like', $like)
                                ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(notifications.data, '$.title')) LIKE ?", [$like])
                                ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(notifications.data, '$.message')) LIKE ?", [$like]);
                        });
                    })
                    ->url(fn (Notification $record): ?string => $record->link ?? ($record->data['url'] ?? null)),

                TextColumn::make('created_at')
                    ->label(__('admin.time'))
                    ->since()
                    ->sortable(query: fn ($query, $direction) => $query->orderBy('notifications.created_at', $direction)),
            ])
            ->recordActions([
                Action::make('delete')
                    ->iconButton()
                    ->icon('phosphor-trash')
                    ->color('gray')
                    ->before(function (Action $action): void {
                        if (DemoMode::isActive()) {
                            FilamentNotification::make()
                                ->title(__('admin.demo_account_action_not_allowed'))
                                ->danger()
                                ->send();

                            $action->cancel();
                        }
                    })
                    ->action(fn (Notification $record) => $record->users()->detach(auth()->id())),
            ])
            ->defaultPaginationPageOption(10);
    }
}
