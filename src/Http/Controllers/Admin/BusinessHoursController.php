<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Models\BusinessSchedule;
use Escalated\Laravel\Models\Holiday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class BusinessHoursController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Escalated/Admin/BusinessHours/Index', [
            'schedules' => BusinessSchedule::with('holidays')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Escalated/Admin/BusinessHours/Form', [
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'timezone' => 'required|string|timezone',
            'is_default' => 'boolean',
            'schedule' => 'required|array',
            'holidays' => 'nullable|array',
            'holidays.*.name' => 'required|string|max:255',
            'holidays.*.date' => 'required|date',
            'holidays.*.recurring' => 'boolean',
        ]);

        if (!empty($validated['is_default'])) {
            BusinessSchedule::where('is_default', true)->update(['is_default' => false]);
        }

        $schedule = BusinessSchedule::create([
            'name' => $validated['name'],
            'timezone' => $validated['timezone'],
            'is_default' => $validated['is_default'] ?? false,
            'schedule' => $validated['schedule'],
        ]);

        if (!empty($validated['holidays'])) {
            foreach ($validated['holidays'] as $holiday) {
                $schedule->holidays()->create($holiday);
            }
        }

        return redirect()->route('escalated.admin.business-hours.index')
            ->with('success', 'Business schedule created.');
    }

    public function edit(BusinessSchedule $businessHour): Response
    {
        $businessHour->load('holidays');

        return Inertia::render('Escalated/Admin/BusinessHours/Form', [
            'schedule' => $businessHour,
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function update(BusinessSchedule $businessHour, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'timezone' => 'required|string|timezone',
            'is_default' => 'boolean',
            'schedule' => 'required|array',
            'holidays' => 'nullable|array',
            'holidays.*.name' => 'required|string|max:255',
            'holidays.*.date' => 'required|date',
            'holidays.*.recurring' => 'boolean',
        ]);

        if (!empty($validated['is_default'])) {
            BusinessSchedule::where('is_default', true)
                ->where('id', '!=', $businessHour->id)
                ->update(['is_default' => false]);
        }

        $businessHour->update([
            'name' => $validated['name'],
            'timezone' => $validated['timezone'],
            'is_default' => $validated['is_default'] ?? false,
            'schedule' => $validated['schedule'],
        ]);

        // Sync holidays
        $businessHour->holidays()->delete();
        if (!empty($validated['holidays'])) {
            foreach ($validated['holidays'] as $holiday) {
                $businessHour->holidays()->create($holiday);
            }
        }

        return redirect()->route('escalated.admin.business-hours.index')
            ->with('success', 'Business schedule updated.');
    }

    public function destroy(BusinessSchedule $businessHour): RedirectResponse
    {
        $businessHour->delete();

        return redirect()->route('escalated.admin.business-hours.index')
            ->with('success', 'Business schedule deleted.');
    }
}
