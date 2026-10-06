<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SeoPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SeoSettingController extends Controller
{
    public function index(Request $request)
    {

        // validator
        $validator = Validator::make($request->all(), [
            'type' => 'nullable|in:home,hotels,rooms,gallery,about-us,contact-us,help-support,help-support-faq,blogs,list-property',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $query = SeoPage::query();

        if ($request->type) {
            $query->where('page_type', $request->type);
        }

        $seoPages = $query->latest()->get();

        return $this->successResponse([
            'data' => $seoPages,
        ], 'SEO pages fetched successfully');
    }
}
