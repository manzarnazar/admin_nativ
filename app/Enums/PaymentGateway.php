<?php

namespace App\Enums;

enum PaymentGateway: string
{
    case Razorpay = 'razorpay';
    case Stripe = 'stripe';
    case Flutterwave = 'flutterwave';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Razorpay => __('admin.razorpay'),
            self::Stripe => __('admin.stripe'),
            self::Flutterwave => __('admin.flutterwave'),
            self::Manual => __('admin.manual'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Razorpay => 'primary',
            self::Stripe => 'success',
            self::Flutterwave => 'warning',
            self::Manual => 'gray',
        };
    }
}
