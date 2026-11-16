<?php

namespace App\Http\Controllers\Reader;

use App\Domain\Themes\ThemeRegistry;
use App\Domain\Themes\ThemeStylesheet;
use App\Http\Controllers\Controller;
use App\Models\Encyclopedia;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ThemeStylesheetController extends Controller
{
    public function __invoke(Request $request, string $theme, ThemeRegistry $themes, ThemeStylesheet $stylesheets): Response
    {
        $definition = $themes->find($theme) ?? $themes->default();
        $settings = $definition->defaultSettings();

        if ($request->filled('e')) {
            $encyclopedia = Encyclopedia::where('slug', $request->query('e'))->first();
            if ($encyclopedia) {
                try {
                    $settings = $definition->normalize($encyclopedia->theme_settings ?? []);
                } catch (\Throwable) {
                    $settings = $definition->defaultSettings();
                }
            }
        }

        $css = $stylesheets->css($definition, $settings);
        $etag = '"'.md5($css).'"';

        if ($request->header('If-None-Match') === $etag) {
            return response('', 304);
        }

        return response($css)
            ->header('Content-Type', 'text/css; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600')
            ->header('ETag', $etag);
    }
}
