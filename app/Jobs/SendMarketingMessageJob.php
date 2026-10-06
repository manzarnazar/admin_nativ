<?php

namespace App\Jobs;

use App\Enums\MarketingMessageAudience;
use App\Enums\MarketingMessageStatus;
use App\Enums\MarketingMessageType;
use App\Mail\MarketingEmailMailable;
use App\Models\MarketingMessage;
use App\Models\Notification;
use App\Services\MarketingMessageService;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendMarketingMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public MarketingMessage $marketingMessage
    ) {}

    public function handle(MarketingMessageService $service): void
    {
        $msg = $this->marketingMessage->fresh();

        if (! $msg || $msg->status === MarketingMessageStatus::Sent) {
            return;
        }

        $sentTo = 0;
        $isGeneral = $msg->audience === MarketingMessageAudience::All;
        $inAppNotification = null;

        $service->recipientsQuery($msg->audience, $msg->city_id)
            ->with('fcmTokens')
            ->chunkById(100, function (Collection $chunk) use ($msg, $isGeneral, &$sentTo, &$inAppNotification) {
                $sentTo += $chunk->count();

                if (in_array($msg->type, [MarketingMessageType::Push, MarketingMessageType::Both])) {
                    app(NotificationService::class)->sendPush(
                        title: $msg->title,
                        body: $msg->body,
                        userIds: $chunk->pluck('id')->all(),
                        data: array_filter([
                            'type' => 'marketing',
                            'message_id' => (string) $msg->id,
                            'image' => $msg->image ? asset('storage/'.$msg->image) : null,
                            'redirect_url' => $msg->redirect_url ?? null,
                        ]),
                        image: $msg->image ? asset('storage/'.$msg->image) : null,
                    );
                }

                if (in_array($msg->type, [MarketingMessageType::Email, MarketingMessageType::Both])) {
                    $this->sendEmails($msg, $chunk);
                }

                if (! $isGeneral) {
                    try {
                        $inAppNotification ??= $this->createInAppNotificationRecord($msg, false);
                        $inAppNotification->users()->attach($chunk->pluck('id')->toArray());
                    } catch (\Throwable $e) {
                        Log::error('MarketingMessage in-app notification storage failed', [
                            'message_id' => $msg->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        if ($sentTo === 0) {
            $msg->update([
                'status' => MarketingMessageStatus::Sent,
                'sent_at' => now(),
                'sent_to' => 0,
            ]);

            return;
        }

        if ($isGeneral) {
            try {
                $this->createInAppNotificationRecord($msg, true);
            } catch (\Throwable $e) {
                Log::error('MarketingMessage in-app notification storage failed', [
                    'message_id' => $msg->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $msg->update([
            'status' => MarketingMessageStatus::Sent,
            'sent_at' => now(),
            'sent_to' => $sentTo,
        ]);
    }

    private function sendEmails(MarketingMessage $msg, Collection $recipients): void
    {
        foreach ($recipients as $user) {
            if (empty($user->email)) {
                continue;
            }

            try {
                Mail::to($user->email)->queue(new MarketingEmailMailable($msg));
            } catch (\Throwable $e) {
                Log::error('MarketingMessage email failed', [
                    'message_id' => $msg->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function createInAppNotificationRecord(MarketingMessage $msg, bool $isGeneral): Notification
    {
        return Notification::create([
            'type' => 'marketing',
            'is_general' => $isGeneral,
            'title' => $msg->title,
            'body' => $msg->body,
            'image' => $msg->image ? asset('storage/'.$msg->image) : null,
            'link' => $msg->redirect_url,
            'data' => array_filter([
                'marketing_message_id' => $msg->id,
            ]),
        ]);
    }
}
