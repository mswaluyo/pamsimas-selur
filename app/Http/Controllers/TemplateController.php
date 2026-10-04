<?php

namespace App\Http\Controllers;

use App\Models\GaugeTemplate;
use App\Models\IndicatorSetting;
use App\Support\Permission;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    private function check(string $action = 'read'): void
    {
        Permission::abortUnlessCan(session('user.role', 'Viewer'), 'templates', $action);
    }

    public function index()
    {
        // Template gauge kini digabung ke halaman "Tampilan" (menu: Tampilan).
        // Rute lama /templates dipertahankan sebagai pengalih agar tautan lama tidak rusak.
        return redirect()->route('settings.display');
    }

    public function store(Request $request)
    {
        $this->check('create');
        GaugeTemplate::create($request->validate([
            'name' => 'required|string|max:100|unique:gauge_templates,name',
            'description' => 'nullable|string',
            'html_code' => 'nullable|string',
            'css_code' => 'nullable|string',
            'js_code' => 'nullable|string',
        ]));
        return back()->with('success', 'Template ditambahkan.');
    }

    public function update(Request $request, int $id)
    {
        $this->check('update');
        GaugeTemplate::findOrFail($id)->update($request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'html_code' => 'nullable|string',
            'css_code' => 'nullable|string',
            'js_code' => 'nullable|string',
        ]));
        return back()->with('success', 'Template diperbarui.');
    }

    public function destroy(int $id)
    {
        $this->check('delete');
        $template = GaugeTemplate::findOrFail($id);
        if ($template->is_core) {
            return back()->with('error', 'Template bawaan tidak dapat dihapus.');
        }
        $template->delete();
        return back()->with('success', 'Template dihapus.');
    }

    public function activate(int $id)
    {
        $this->check('update');
        $template = GaugeTemplate::findOrFail($id);
        IndicatorSetting::query()->first()->update(['active_template_id' => $template->name]);
        return back()->with('success', "Template {$template->name} diaktifkan.");
    }
}
