<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use dacoto\EnvSet\EnvSetEditor;
use dacoto\LaravelWizardInstaller\Exceptions\CantGenerateKeyException;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;

class InstallSetKeysController extends Controller
{
    public function __construct(
        public readonly EnvSetEditor $envEditor,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        try {
            $this->envEditor->setKey('APP_URL', $request->input('app_url'));
            Artisan::call('key:generate', ['--force' => true, '--show' => true]);
            if (empty($this->envEditor->getValue('APP_KEY'))) {
                $this->envEditor->setKey('APP_KEY', trim(str_replace('"', '', Artisan::output())));
            }
            if (empty($this->envEditor->getValue('APP_KEY'))) {
                throw new CantGenerateKeyException();
            }
        } catch (Exception $e) {
            return back()->withErrors($e->getMessage())->withInput();
        }

        try {
            Artisan::call('storage:link', ['--force' => true]);
        } catch (\Throwable $e) {
            // We catch \Throwable because undefined functions (like exec) throw an Error, not an Exception.
            return back()->withErrors('Could not create storage link. Your hosting might have disabled symlink and exec functions. Error: ' . $e->getMessage())->withInput();
        }

        try {
            foreach (config('installer.commands', []) as $command) {
                Artisan::call($command);
            }
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
            if (strlen($errorMsg) > 5000) {
                $errorMsg = substr($errorMsg, 0, 5000) . '... [Error message truncated because it was too large. Please import cities.sql manually via phpMyAdmin]';
            }
            return back()->withErrors($errorMsg)->withInput();
        }

        // Save .env at the very end to prevent `php artisan serve` from restarting
        // the server process mid-execution and causing ERR_EMPTY_RESPONSE.
        try {
            $this->envEditor->save();
        } catch (Exception $e) {
            return back()->withErrors($e->getMessage())->withInput();
        }

        // Force the current request to use the newly submitted app_url so the redirect generates the correct host
        config(['app.url' => $request->input('app_url')]);
        Artisan::call('config:clear');

        return redirect()->route('install.finish');
    }
}
