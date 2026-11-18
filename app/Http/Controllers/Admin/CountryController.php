<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListCountriesRequest;
use App\Http\Requests\Admin\UpdateCountryRequest;
use App\Services\Admin\CountryAdminService;
use Illuminate\Http\Request;

/** Admin > Countries (Doc 15 section 15.19 - Phase F3a): each country, and the defaults a new business there takes, by version from a day. */
class CountryController extends Controller
{
    public function __construct(
        protected CountryAdminService $countries,
    ) {}

    public function index(ListCountriesRequest $request)
    {
        try {
            $search = $request->validated('search');

            return view('admin.countries.index', ['countries' => $this->countries->index($search), 'search' => $search]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function edit(int $id)
    {
        try {
            return view('admin.countries.edit', $this->countries->editPage($id));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(UpdateCountryRequest $request, int $id)
    {
        try {
            $country = $this->countries->findOrFail($id);

            return $this->adminSuccess($request, $this->countries->update($country, $request->validated(), $request->user()), route('admin.countries.edit', $id));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** A version of the country's defaults waiting for its day, removed. */
    public function removeVersion(Request $request, int $id, int $versionId)
    {
        try {
            return $this->adminSuccess($request, $this->countries->removeVersion($id, $versionId, $request->user()));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
