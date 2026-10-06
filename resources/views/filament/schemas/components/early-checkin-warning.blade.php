<div style="display: flex; align-items: center; gap: 10px; background-color: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; padding: 12px 16px;">
    <x-heroicon-o-exclamation-triangle style="width: 20px; height: 20px; color: #d97706; flex-shrink: 0;" />
    <div>
        <p style="font-size: 13px; font-weight: 600; color: #92400e; margin: 0;">
            {{ __('admin.early_checkin_time_warning', ['check_in_time' => $check_in_time, 'current_time' => $current_time]) }}
        </p>
    </div>
</div>
