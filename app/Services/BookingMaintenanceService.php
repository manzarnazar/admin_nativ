<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\InventoryLockStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\Booking;
use App\Models\InventoryLock;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BookingMaintenanceService
{
    public function __construct(private BookingInventoryService $inventoryService) {}

    /**
     * Mark PendingPayment bookings as Expired, release their inventory,
     * and clean up related payments and locks.
     * Called by scheduled command every minute.
     */
    public function expireGhostBookings(): void
    {
        $expiryMinutes = config('app.inventory_lock_expiry_minutes', 10);

        $expiredBookings = Booking::query()
            ->with(['propertyRoom'])
            ->where('status', BookingStatus::PendingPayment)
            ->where('created_at', '<', now()->subMinutes($expiryMinutes))
            ->get();

        foreach ($expiredBookings as $booking) {
            $booking->update(['status' => BookingStatus::Expired]);

            if ($booking->propertyRoom) {
                $this->inventoryService->release(
                    $booking->propertyRoom,
                    $booking->check_in,
                    $booking->check_out,
                    $booking->booked_rooms,
                );
            }
        }

        Payment::query()
            ->where('status', PaymentTransactionStatus::Pending)
            ->whereHas('booking', fn ($q) => $q->where('status', BookingStatus::Expired))
            ->update(['status' => PaymentTransactionStatus::Expired]);

        InventoryLock::query()
            ->whereHas('booking', fn ($q) => $q->where('status', BookingStatus::Expired))
            ->where('status', InventoryLockStatus::Active)
            ->update(['status' => InventoryLockStatus::Released]);
    }

    /**
     * Release booked_rooms inventory for all expired/cancelled bookings where the
     * inventory counter was never decremented (drift caused by old bug).
     * Returns the number of bookings repaired.
     */
    public function fixInventoryDrift(): int
    {
        $driftedBookings = Booking::query()
            ->with(['propertyRoom'])
            ->whereIn('status', [BookingStatus::Expired, BookingStatus::Cancelled])
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('room_inventory')
                    ->whereColumn('room_inventory.property_room_id', 'bookings.property_room_id')
                    ->whereColumn('room_inventory.date', '>=', 'bookings.check_in')
                    ->whereColumn('room_inventory.date', '<', 'bookings.check_out')
                    ->where('room_inventory.booked_rooms', '>', 0);
            })
            ->get();

        $fixed = 0;

        foreach ($driftedBookings as $booking) {
            if (! $booking->propertyRoom) {
                continue;
            }

            $this->inventoryService->release(
                $booking->propertyRoom,
                $booking->check_in,
                $booking->check_out,
                $booking->booked_rooms,
            );

            Log::info('Inventory drift fixed', [
                'booking_number' => $booking->booking_number,
                'status' => $booking->status->value,
                'property_room_id' => $booking->property_room_id,
                'check_in' => $booking->check_in,
                'check_out' => $booking->check_out,
                'released_rooms' => $booking->booked_rooms,
            ]);

            $fixed++;
        }

        return $fixed;
    }

    /**
     * Expire active inventory locks that have passed their expires_at timestamp.
     * Called by scheduled command.
     */
    public function expireStaleLocks(): void
    {
        $expiredLocks = InventoryLock::query()
            ->where('status', InventoryLockStatus::Active->value)
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expiredLocks as $lock) {
            $this->inventoryService->expireLock($lock);
        }
    }
}
