<?php

namespace App\Enums;

enum LoginFailureReason: string
{
    case InvalidCredentials = 'invalid_credentials';
    case AccountInactive = 'account_inactive';
    case InvalidToken = 'invalid_token';
    case RoleMismatch = 'role_mismatch';

    public function label(): string
    {
        return match ($this) {
            self::InvalidCredentials => __('admin.login_reason_invalid_credentials'),
            self::AccountInactive => __('admin.login_reason_account_inactive'),
            self::InvalidToken => __('admin.login_reason_invalid_token'),
            self::RoleMismatch => __('admin.login_reason_role_mismatch'),
        };
    }
}
