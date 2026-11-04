<?php

namespace App\Http\Controllers;

use App\Services\SiteContent;
use App\Models\PublicCampaignResult;
use Illuminate\Http\Request;

class WebsiteController extends Controller
{
    public function home(SiteContent $content)
    {
        return view('landing', [
            'home' => $content->getPublished('home'),
            'site' => $content->getPublished('settings'),
            'publicResults' => PublicCampaignResult::published()->latest('completed_on')->get(PublicCampaignResult::PUBLIC_COLUMNS),
        ]);
    }

    public function privacy(SiteContent $content)
    {
        return view('site.legal', ['page' => $content->getPublished('privacy'), 'site' => $content->getPublished('settings')]);
    }

    public function terms(SiteContent $content)
    {
        return view('site.legal', ['page' => $content->getPublished('terms'), 'site' => $content->getPublished('settings')]);
    }

    public function preview(Request $request, string $page, SiteContent $content)
    {
        abort_unless($request->user()?->role === 'admin', 403);
        $site = $page === 'settings' ? $content->getDraft('settings') : $content->getPublished('settings');
        $request->attributes->set('website.settings', $site);
        $request->attributes->set('website.preview', true);
        $target = $request->query('view', 'home');
        $view = in_array($page, ['privacy', 'terms'], true)
            ? view('site.legal', ['page' => $content->getDraft($page), 'site' => $site])
            : (in_array($target, ['login', 'register'], true)
                ? view('auth.'.$target)
                : view('landing', ['home' => $page === 'home' ? $content->getDraft('home') : $content->getPublished('home'), 'site' => $site]));

        return response($view)->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('X-Robots-Tag', 'noindex, nofollow')->header('X-Frame-Options', 'SAMEORIGIN');
    }
}
