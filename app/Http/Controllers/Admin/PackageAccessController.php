<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Billing\Package;
use App\Models\Frontend\AppCategory;
use App\Models\Frontend\Application;
use App\Services\Admin\AppCatalogRules;
use App\Services\Shared\ActivityLogService;
use App\Services\User\Apps\CatalogVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Which plan includes which app, as one matrix (Doc 01 section 3).
 *
 * A higher plan includes everything a lower one does, so only an app's lowest
 * ticked plan counts: saving ticks every plan above it as well. Saving is
 * refused while a plan would hold an app without what that app needs.
 *
 * Access is not activation. Businesses that already have an app on keep it
 * when it leaves their plan; their apps are brought within the plan when the
 * plan itself changes.
 */
class PackageAccessController extends Controller
{
    public function __construct(
        protected AppCatalogRules $rules,
    ) {}

    public function index()
    {
        try {
            $packages   = Package::orderBy('rank')->get();
            $categories = AppCategory::with(['apps' => fn ($q) => $q->orderBy('sort_order')->orderBy('family_key')
                ->orderByRaw("case when edition = 'lite' then 1 else 2 end")])
                ->orderBy('sort_order')->orderBy('name')->get();
            $uncategorised = Application::whereNull('category_id')->orderBy('sort_order')->get();

            $access = DB::table('package_apps')->get()
                ->groupBy('app_id')
                ->map(fn ($rows) => $rows->pluck('package_id')->all());

            return view('admin.package-access.index', compact('packages', 'categories', 'uncategorised', 'access'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(Request $request)
    {
        try {
            $packages = Package::orderBy('rank')->get();
            $ranks    = $packages->pluck('rank', 'id');
            $appIds   = Application::pluck('id')->flip();

            // app id => rank of the lowest plan ticked for it.
            $lowest = [];

            foreach ((array) $request->input('access', []) as $appId => $packageIds) {
                $ticked = collect((array) $packageIds)->map(fn ($id) => $ranks[(int) $id] ?? null)->filter(fn ($r) => $r !== null);

                if ($appIds->has((int) $appId) && $ticked->isNotEmpty()) {
                    $lowest[(int) $appId] = $ticked->min();
                }
            }

            if ($problems = $this->rules->accessProblems($lowest)) {
                return $this->adminFailure($request, implode(' ', $problems), 422);
            }

            DB::transaction(function () use ($lowest, $packages) {
                DB::table('package_apps')->delete();

                $rows = [];

                foreach ($lowest as $appId => $rank) {
                    foreach ($packages->where('rank', '>=', $rank) as $package) {
                        $rows[] = ['package_id' => $package->id, 'app_id' => $appId];
                    }
                }

                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('package_apps')->insert($chunk);
                }
            });

            CatalogVersion::bump();

            ActivityLogService::record('updated', 'Updated package access');

            return $this->adminSuccess($request, 'Package access saved.', route('admin.package-access.index'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
