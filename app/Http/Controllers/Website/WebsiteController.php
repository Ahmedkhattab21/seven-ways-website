<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class WebsiteController extends Controller
{
    public function home(): View
    {
        return $this->page('home');
    }

    public function about(): View
    {
        return $this->page('about');
    }

    public function services(): View
    {
        return $this->page('services');
    }

    public function contact(): View
    {
        return $this->page('contact');
    }

    public function language(Request $request, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, ['ar', 'en'], true), 404);

        $request->session()->put('website_locale', $locale);

        $redirectTo = $request->string('redirect_to')->toString();
        $redirectPath = parse_url($redirectTo, PHP_URL_PATH);
        $allowedPaths = ['/', '/about-us', '/our-services', '/contact-us'];

        if (
            ! is_string($redirectPath)
            || ! in_array($redirectPath, $allowedPaths, true)
            || str_contains($redirectTo, '\\')
            || parse_url($redirectTo, PHP_URL_HOST) !== null
        ) {
            $redirectPath = route('website.home', absolute: false);
        }

        return redirect($redirectPath.'?lang='.$locale);
    }

    public function sitemap(): Response
    {
        return response()
            ->view('website.sitemap')
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    private function page(string $view): View
    {
        return view("website.{$view}", [
            'website' => config('website'),
            'branches' => config('website.branches', []),
        ]);
    }
}
