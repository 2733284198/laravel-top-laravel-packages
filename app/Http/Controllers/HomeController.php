<?php

namespace App\Http\Controllers;

use App\Download;
use App\Package;
use Cache;
use DateTime;
use Illuminate\Support\Collection;

class HomeController extends Controller
{
    /**
     * Homepage.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $packages = Cache::remember('package-list', 60, function () {
            $packages = Package::orderByDesc('downloads')
                ->orderByDesc('favers')
                ->get();

            return $this->filterOfficialPackages($packages);
        });

        return view('home', compact('packages'));
    }

    /**
     * Package downloads ranking.
     *
     * @param string $type
     * @param string $date
     *
     * @return \Illuminate\View\View
     */
    public function ranking(string $type, string $date)
    {
        $unifiedDate = $this->checkParameters($type, $date);

        $ranks = Cache::remember(sprintf('package-ranking-%s-%s', $type, $date), 60, function () use ($type, $unifiedDate) {
            $ranks = Download::with('package:packages.id,name,url,description')
                ->where('type', $type)
                ->where('date', $unifiedDate)
                ->orderByDesc('downloads')
                ->get();

            return $this->filterOfficialPackages($ranks);
        });

        return view('rank', compact('ranks'));
    }

    /**
     * Validate ranking page parameters.
     *
     * @param string $type
     * @param string $date
     *
     * @return string
     */
    protected function checkParameters(string $type, string $date): string
    {
        switch ($type) {
            case 'daily': $format = 'Y-m-d'; break;
            case 'weekly': $format = 'Y-m-d'; break;
            case 'monthly': $format = 'Y-m'; break;
            case 'yearly': $format = 'Y'; break;
            default: abort(404);
        }

        $datetime = DateTime::createFromFormat(sprintf('!%s', $format), $date);

        abort_unless($datetime, 404);

        abort_if($datetime->format($format) !== $date, 404);

        return $datetime->format('Y-m-d');
    }

    /**
     * Filter official packages.
     *
     * @param Collection $collection
     *
     * @return Collection
     */
    protected function filterOfficialPackages(Collection $collection)
    {
        return $collection->filter(function ($model) {
            if ($model instanceof Package) {
                $name = $model->name;
            } elseif ($model instanceof Download) {
                $name = $model->package->name;
            } else {
                return true;
            }

            return ! str_contains($name, ['laravel/', 'illuminate/']);
        });
    }
}
