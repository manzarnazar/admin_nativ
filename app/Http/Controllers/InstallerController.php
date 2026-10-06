<?php

namespace App\Http\Controllers;

use dacoto\EnvSet\Facades\EnvSet;
use dacoto\LaravelWizardInstaller\Controllers\InstallFolderController;
use dacoto\LaravelWizardInstaller\Controllers\InstallServerController;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class InstallerController extends Controller
{
    public function purchaseCodeIndex(): View|RedirectResponse
    {
        if ((new InstallServerController)->check() === false || (new InstallFolderController)->check() === false) {
            return redirect()->route('install.folders');
        }

        return view('vendor.installer.steps.purchase-code');
    }

    public function checkPurchaseCode(Request $request): View|RedirectResponse
    {
        try {
            $appUrl = (string) url('/');
            $appUrl = preg_replace('#^https?://#i', '', $appUrl) . '/';

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => 'https://validator.wrteam.in/estay_validator?purchase_code=' . $request->input('purchase_code') . '&domain_url=' . $appUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
            ]);
            $response = curl_exec($curl);
            curl_close($curl);

            $response = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

            if ($response['error']) {
                return view('vendor.installer.steps.purchase-code', ['error' => $response['message']]);
            }

            EnvSet::setKey('APPSECRET', $request->input('purchase_code'));
            EnvSet::setKey('APP_URL', (string) url('/'));
            EnvSet::save();

            return redirect()->route('install.database');
        } catch (Exception $e) {
            return view('vendor.installer.steps.purchase-code', [
                'values' => ['purchase_code' => $request->get('purchase_code')],
                'error' => $e->getMessage(),
            ]);
        }
    }
}
