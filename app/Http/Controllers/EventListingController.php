<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Sponsor;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventListingController extends Controller
{
    public function __invoke(Request $request): View
    {
        $events = Event::query();
        $events->where('status', 'approved')
            ->whereDate('end_date', '>=', today());

        return view('publik.lomba.index', [
            'events' => $events->orderBy('start_date')->orderByDesc('id')->paginate(15)->withQueryString(),
            'isVerified' => $request->user()?->hasVerifiedEmail() ?? false,
            'isAdmin' => $request->user()?->role === 'admin',
            'webSponsors' => Sponsor::query()
                ->web()->approved()->active()
                ->where('package', Sponsor::PACKAGE_WEB_BANNER)
                ->get(),
            'eventSponsors' => Sponsor::query()
                ->forEvent()->approved()->active()->get()->keyBy('event_id'),
        ]);
    }
}
