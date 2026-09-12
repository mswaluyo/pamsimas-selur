<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GaugeTemplate;

class TemplateApiController extends Controller
{
    /**
     * GET /api/template/preview/{id} — HTML/CSS/JS template gauge untuk preview live.
     */
    public function preview(int $id)
    {
        $template = GaugeTemplate::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $template->id,
                'name' => $template->name,
                'html' => $template->html_code,
                'css' => $template->css_code,
                'js' => $template->js_code,
            ],
        ]);
    }
}
