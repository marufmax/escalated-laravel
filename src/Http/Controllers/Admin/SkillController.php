<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Models\Skill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class SkillController extends Controller
{
    public function index(): Response
    {
        $skills = Skill::withCount('agents')->orderBy('name')->get();

        return Inertia::render('Escalated/Admin/Skills/Index', [
            'skills' => $skills,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Escalated/Admin/Skills/Form');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:'.Skill::make()->getTable().',name',
        ]);

        Skill::create([
            'name' => $request->input('name'),
        ]);

        return redirect()->route('escalated.admin.skills.index')
            ->with('success', 'Skill created.');
    }

    public function edit(Skill $skill): Response
    {
        return Inertia::render('Escalated/Admin/Skills/Form', [
            'skill' => $skill,
        ]);
    }

    public function update(Request $request, Skill $skill): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:'.Skill::make()->getTable().',name,'.$skill->id,
        ]);

        $skill->update([
            'name' => $request->input('name'),
        ]);

        return redirect()->route('escalated.admin.skills.index')
            ->with('success', 'Skill updated.');
    }

    public function destroy(Skill $skill): RedirectResponse
    {
        $skill->delete();

        return redirect()->route('escalated.admin.skills.index')
            ->with('success', 'Skill deleted.');
    }
}
