<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PropertyStatus;
use App\Enums\PropertyVerificationStatus;
use App\Models\Property;
use App\Models\PropertyRegistrationValue;
use App\Models\PropertyRoom;
use App\Models\PropertyRuleAnswer;
use App\Models\RoomType;
use App\Support\PercentageValidator;
use App\Support\SystemMode;

class PropertyService
{
    /**
     * Delete a property, unless it has ongoing or upcoming confirmed bookings.
     *
     * @throws \InvalidArgumentException when the property has active bookings.
     */
    public function deleteProperty(Property $property): void
    {
        if ($this->hasActiveOrUpcomingBookings($property)) {
            throw new \InvalidArgumentException(__('admin.property_has_active_bookings_cannot_delete'));
        }

        $property->delete();
    }

    /**
     * Whether the property has any confirmed or checked-in booking whose stay
     * has not yet ended (i.e. ongoing or in the future).
     */
    public function hasActiveOrUpcomingBookings(Property $property): bool
    {
        return $property->bookings()
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::CheckedIn])
            ->whereDate('check_out', '>=', now()->toDateString())
            ->exists();
    }

    /**
     * Create a new property with basic details (Step 1).
     *
     * @param  array{country_id: int, property_type_id: ?int, name: string, description: ?string, phone: ?string, email: ?string, landline: ?string, street_address: ?string, ref_state_id: ?int, ref_city_id: ?int, zip_code: ?string, latitude: ?string, longitude: ?string, bank_account_holder: ?string, bank_name: ?string, bank_account_number: ?string, bank_code: ?string}  $data
     */
    public function createProperty(array $data): Property
    {
        $property = Property::query()->create($data);
        $this->completeStep($property, 1);

        if (SystemMode::isMulti() && ! empty($data['ref_city_id']) && ! empty($data['country_id'])) {
            app(CityService::class)->ensureOperationalCity(
                (int) $data['ref_city_id'],
                (int) $data['country_id'],
            );
        }

        return $property;
    }

    /**
     * Update basic details for an existing property (Step 1).
     *
     * @param  array{name?: string, description?: ?string, phone?: ?string, email?: ?string, landline?: ?string, street_address?: ?string, ref_state_id?: ?int, ref_city_id?: ?int, zip_code?: ?string, latitude?: ?string, longitude?: ?string, bank_account_holder?: ?string, bank_name?: ?string, bank_account_number?: ?string, bank_code?: ?string}  $data
     */
    public function updateBasicDetails(Property $property, array $data): void
    {
        $property->update($data);
        $this->completeStep($property, 1);

        // Fetch place_id if empty and address fields exist
        if (empty($property->place_id) && ! empty($property->street_address) && ! empty($property->ref_city_id)) {
            $placeId = $this->fetchPlaceId($property);
            if ($placeId) {
                $property->place_id = $placeId;
                $property->saveQuietly();
            }
        }
    }

    private function fetchPlaceId(Property $property): ?string
    {
        try {
            $address = $this->buildAddressString($property);
            if (empty($address)) {
                return null;
            }

            $places = app(GooglePlacesService::class)->searchPlaces($address, 1);

            if (! empty($places) && isset($places[0]['place_id'])) {
                return $places[0]['place_id'];
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function buildAddressString(Property $property): string
    {
        $parts = array_filter([
            $property->street_address,
            $property->refCity?->name,
            $property->refState?->name,
            $property->country?->name,
            $property->zip_code,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Sync facilities for a property (Step 2).
     *
     * @param  array<int>  $facilityIds
     */
    public function syncFacilities(Property $property, array $facilityIds): void
    {
        $property->facilities()->sync($facilityIds);
        $this->completeStep($property, 2);
    }

    /**
     * Add a room to a property (Step 3).
     *
     * @param  array{room_type_id: int, total_rooms: int, room_size: ?string, base_price_per_night: string}  $data
     */
    public function addRoom(Property $property, array $data): PropertyRoom
    {
        $exists = $property->rooms()
            ->where('room_type_id', $data['room_type_id'])
            ->exists();

        if ($exists) {
            throw new \InvalidArgumentException('This room type has already been added to this property.');
        }

        return $property->rooms()->create($data);
    }

    /**
     * Update an existing property room (Step 3).
     *
     * @param  array{total_rooms?: int, room_size?: ?string, base_price_per_night?: string}  $data
     */
    public function updateRoom(PropertyRoom $propertyRoom, array $data): void
    {
        $propertyRoom->update($data);
    }

    /**
     * Remove a room from a property (Step 3).
     */
    public function removeRoom(PropertyRoom $propertyRoom): void
    {
        $propertyRoom->delete();
    }

    /**
     * Save a room with its global room type data (shared by Property Wizard + All Rooms page).
     * When room_type_id is empty, a new RoomType catalog entry is created from the room
     * fields instead of updating an existing one — the partner wizard's "start from
     * scratch" path, where $partnerId must be supplied to own the newly created entry.
     *
     * @param  array{room_type_id: ?int, room_name: string, bed_type: string, max_guests: int, room_description: string, room_images?: array<string>, room_amenities?: array<int>, total_rooms: int, room_size?: ?string, base_price_per_night: string}  $data
     */
    public function saveRoomWithType(Property $property, array $data, ?int $propertyRoomId = null, ?int $partnerId = null): void
    {
        if (empty($data['room_type_id'])) {
            $roomType = app(RoomTypeService::class)->createRoomType(
                data: [
                    'partner_id' => $partnerId,
                    'name' => $data['room_name'],
                    'bed_type' => $data['bed_type'],
                    'max_guests' => $data['max_guests'],
                    'description' => $data['room_description'],
                ],
                facilityIds: $data['room_amenities'] ?? [],
                images: $data['room_images'] ?? [],
            );
        } else {
            // Update global room type
            $roomType = RoomType::query()->findOrFail($data['room_type_id']);
            $nameChanged = $roomType->name !== $data['room_name'];
            $roomType->update([
                'name' => $data['room_name'],
                'bed_type' => $data['bed_type'],
                'max_guests' => $data['max_guests'],
                'description' => $data['room_description'],
            ]);

            // Regenerate slugs for all property_rooms of this room type when name changes
            if ($nameChanged) {
                PropertyRoom::query()
                    ->where('room_type_id', $roomType->id)
                    ->with('property')
                    ->get()
                    ->each(function (PropertyRoom $pr) {
                        $pr->update(['slug' => PropertyRoom::generateSlug($pr)]);
                    });
            }

            // Sync room type images
            $roomType->images()->delete();
            foreach ($data['room_images'] ?? [] as $index => $imagePath) {
                $roomType->images()->create([
                    'image_path' => $imagePath,
                    'sort_order' => $index,
                ]);
            }

            // Sync room type amenities
            $roomType->facilities()->sync($data['room_amenities'] ?? []);
        }

        // Save property-specific data
        $roomData = [
            'room_type_id' => $roomType->id,
            'total_rooms' => 0,
            'room_size' => $data['room_size'] ?? null,
            'base_price_per_night' => $data['base_price_per_night'],
        ];

        if ($propertyRoomId) {
            $propertyRoom = PropertyRoom::query()->findOrFail($propertyRoomId);
            $this->updateRoom($propertyRoom, $roomData);
        } else {
            $this->addRoom($property, $roomData);
        }
    }

    /**
     * Mark Step 3 (Rooms & Pricing) as complete.
     */
    public function completeRoomsStep(Property $property): void
    {
        $this->completeStep($property, 3);
    }

    /**
     * Save property rule answers and check times (Step 4).
     *
     * @param  array{check_in_time: ?string, check_out_time: ?string, custom_rules: ?string, pets_allowed: bool, answers: array<int, mixed>}  $data
     */
    public function saveRuleAnswers(Property $property, array $data): void
    {
        $property->update([
            'check_in_time' => $data['check_in_time'] ?? null,
            'check_out_time' => $data['check_out_time'] ?? null,
            'custom_rules' => $data['custom_rules'] ?? null,
            'pets_allowed' => $data['pets_allowed'] ?? false,
            'pet_policy_details' => $data['pet_policy_details'] ?? null,
        ]);

        foreach ($data['answers'] ?? [] as $questionId => $answerValue) {
            PropertyRuleAnswer::query()->updateOrCreate(
                [
                    'property_id' => $property->id,
                    'property_rule_question_id' => $questionId,
                ],
                ['answer_value' => $answerValue],
            );
        }

        $this->completeStep($property, 4);
    }

    /**
     * Save cancellation policy step (Step 5).
     * Actual policy editing is done via CancellationPolicyService (global change).
     */
    public function completeCancellationPolicyStep(Property $property): void
    {
        $this->completeStep($property, 5);
    }

    /**
     * Save payment configuration (Step 6).
     *
     * @param  array{pay_at_property: bool, advance_percentage: ?string}  $data
     */
    public function savePaymentConfig(Property $property, array $data): void
    {
        if (isset($data['advance_percentage']) && $data['advance_percentage'] !== null) {
            PercentageValidator::assertValid((float) $data['advance_percentage'], 'Advance percentage');

            // Backstop behind the form-level check in PartnerPropertyCreate.php — a
            // single-mode property has no partner/commission concept at all (checkIn()'s
            // wallet-crediting itself is gated the same way), so this only ever applies
            // to a multi-mode, partner-owned property.
            if (SystemMode::isMulti() && $property->partner_id) {
                $commissionRate = app(CommissionService::class)->resolveRate(
                    (int) $property->country_id,
                    $property->partner_id,
                    (int) $property->property_type_id,
                );

                if ((float) $data['advance_percentage'] < $commissionRate) {
                    throw new \RuntimeException("Advance percentage must be at least {$commissionRate}% (the effective commission rate), otherwise the amount collected online won't cover commission owed on bookings for this property.");
                }
            }
        }

        $property->update($data);
        $this->completeStep($property, 6);
    }

    /**
     * Save property images — primary showcase and gallery groups (Step 7).
     *
     * @param  array<string>  $primaryImages
     * @param  array<array{name: string, images: array<string>}>  $galleryGroups
     */
    public function saveImages(Property $property, array $primaryImages, array $galleryGroups, ?string $primaryVideo = null): void
    {
        $property->images()->delete();

        // Save primary video first (sort_order = 0) if provided
        $imageOffset = 0;

        if ($primaryVideo) {
            $property->images()->create([
                'image_path' => $primaryVideo,
                'sort_order' => 0,
                'is_primary' => true,
                'group_name' => null,
                'media_type' => 'video',
            ]);
            $imageOffset = 1;
        }

        foreach ($primaryImages as $index => $imagePath) {
            $property->images()->create([
                'image_path' => $imagePath,
                'sort_order' => $index + $imageOffset,
                'is_primary' => true,
                'group_name' => null,
                'media_type' => 'image',
            ]);
        }

        // sort_order runs continuously across every group (not reset per group) — there's
        // no separate column for group display order, so this single counter encodes both
        // the groups' own order and each image's order within its group at once.
        $gallerySortOrder = 0;

        foreach ($galleryGroups as $group) {
            foreach ($group['images'] as $imagePath) {
                $property->images()->create([
                    'image_path' => $imagePath,
                    'sort_order' => $gallerySortOrder++,
                    'is_primary' => false,
                    'group_name' => $group['name'],
                    'media_type' => 'image',
                ]);
            }
        }

        $this->completeStep($property, 7);
    }

    /**
     * Save registration field values (Step 8 — Legal & Compliance).
     *
     * @param  array<int, mixed>  $values  Keyed by registration_field_id
     */
    public function saveRegistrationValues(Property $property, array $values): void
    {
        foreach ($values as $fieldId => $value) {
            PropertyRegistrationValue::query()->updateOrCreate(
                [
                    'property_id' => $property->id,
                    'registration_field_id' => $fieldId,
                ],
                ['value' => $value],
            );
        }

        $this->completeStep($property, 8);
    }

    /**
     * Finalize property with status selection (after Step 8).
     */
    public function finalizeProperty(Property $property, string $status): void
    {
        $property->update(['status' => $status]);
    }

    /**
     * Submit a partner-owned property for admin verification (after Step 8).
     * Partner's chosen status (active/inactive) is preserved as-is — the property
     * is hidden from public listings by the verification_status gate, not by forcing Draft.
     */
    public function submitForVerification(Property $property): void
    {
        $newStatus = in_array($property->verification_status, [
            PropertyVerificationStatus::CorrectionRequested,
            PropertyVerificationStatus::Rejected,
        ], true)
            ? PropertyVerificationStatus::Resubmission
            : PropertyVerificationStatus::Pending;

        $property->update([
            'verification_status' => $newStatus,
        ]);
    }

    /**
     * Suspend a property (multi-mode oversight), unless it has ongoing or
     * upcoming confirmed bookings.
     *
     * @throws \InvalidArgumentException when the property has active bookings.
     */
    public function suspendProperty(Property $property, string $reason): void
    {
        if ($this->hasActiveOrUpcomingBookings($property)) {
            throw new \InvalidArgumentException(__('admin.cannot_suspend_property_active_bookings'));
        }

        $property->update([
            'pre_suspension_status' => $property->status?->value,
            'status' => PropertyStatus::Suspended,
            'suspended_at' => now(),
            'suspension_reason' => $reason,
        ]);
    }

    /**
     * Reactivate a previously-suspended property, restoring its pre-suspension status.
     */
    public function unsuspendProperty(Property $property): void
    {
        $restore = $property->pre_suspension_status
            ? PropertyStatus::from($property->pre_suspension_status)
            : PropertyStatus::Active;

        $property->update([
            'status' => $restore,
            'suspended_at' => null,
            'suspension_reason' => null,
            'pre_suspension_status' => null,
        ]);
    }

    /**
     * Mark a wizard step as complete (only advances forward).
     */
    public function completeStep(Property $property, int $step): void
    {
        if ($step > $property->completed_step) {
            $property->update(['completed_step' => $step]);
        }
    }
}
