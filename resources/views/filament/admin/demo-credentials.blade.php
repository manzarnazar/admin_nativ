<div class="demo-credentials-section">
    <div class="demo-credentials-divider">
        <span>{{ __('admin.demo_credentials') }}</span>
    </div>

    <div class="demo-credentials-card">
        <div class="demo-credentials-row">
            <div class="demo-credentials-info">
                <p class="demo-credentials-label">{{ __('admin.email') }}</p>
                <p class="demo-credentials-value" id="demo-email">{{ $demoEmail }}</p>
                <p class="demo-credentials-label" style="margin-top: 12px;">{{ __('admin.password') }}</p>
                <p class="demo-credentials-value" id="demo-password">{{ $demoPassword }}</p>
            </div>

            <button
                type="button"
                class="demo-credentials-copy-btn"
                onclick="fillDemoCredentials()"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect width="14" height="14" x="8" y="8" rx="2" ry="2"/>
                    <path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>
                </svg>
                {{ __('admin.copy') }}
            </button>
        </div>

        <p class="demo-credentials-hint">{{ __('admin.click_copy_to_auto_fill_the_form') }}</p>
    </div>
</div>

<style>
.demo-credentials-section {
    margin-top: 24px;
}

.demo-credentials-divider {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
    color: #6b7280;
    font-size: 0.875rem;
}

.demo-credentials-divider::before,
.demo-credentials-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background-color: #e5e7eb;
}

.demo-credentials-card {
    background-color: var(--brand-primary-light, #eff6ff);
    border-radius: 16px;
    padding: 20px;
}

.demo-credentials-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 16px;
}

.demo-credentials-info {
    flex: 1;
}

.demo-credentials-label {
    font-size: 0.875rem;
    color: #6b7280;
    margin: 0 0 2px 0;
}

.demo-credentials-value {
    font-size: 0.9375rem;
    font-weight: 700;
    color: #111827;
    margin: 0;
}

.demo-credentials-copy-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background-color: #111827;
    color: #ffffff;
    font-size: 0.9375rem;
    font-weight: 600;
    padding: 12px 20px;
    border-radius: 10px;
    border: none;
    cursor: pointer;
    white-space: nowrap;
    flex-shrink: 0;
    transition: background-color 0.15s ease;
}

.demo-credentials-copy-btn:hover {
    background-color: #1f2937;
}

.demo-credentials-hint {
    text-align: center;
    font-size: 0.875rem;
    color: #6b7280;
    margin: 0;
}
</style>

<script>
function fillDemoCredentials() {
    const email = document.getElementById('demo-email')?.textContent?.trim();
    const password = document.getElementById('demo-password')?.textContent?.trim();

    // Filament login form uses wire:model="data.email" / wire:model="data.password"
    const emailInput = document.querySelector('input[wire\\:model*="email"], input[id*="email"]');
    const passwordInput = document.querySelector('input[wire\\:model*="password"], input[id*="password"]');

    if (emailInput && email) {
        emailInput.value = email;
        emailInput.dispatchEvent(new Event('input', { bubbles: true }));
        emailInput.dispatchEvent(new Event('change', { bubbles: true }));
    }

    if (passwordInput && password) {
        passwordInput.value = password;
        passwordInput.dispatchEvent(new Event('input', { bubbles: true }));
        passwordInput.dispatchEvent(new Event('change', { bubbles: true }));
    }
}
</script>
