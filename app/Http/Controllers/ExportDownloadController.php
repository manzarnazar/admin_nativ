<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportDownloadController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        $path = decrypt($request->query('path'));

        if (! str_starts_with($path, storage_path('app/exports/'))) {
            abort(403);
        }

        if (! file_exists($path)) {
            abort(404);
        }

        return response()->download($path)->deleteFileAfterSend();
    }
}
