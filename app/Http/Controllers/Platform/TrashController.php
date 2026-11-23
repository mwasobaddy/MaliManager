<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Trash\BulkSoftDelete;
use App\Actions\Trash\EmptyTrashModule;
use App\Actions\Trash\ForceDeleteTrashed;
use App\Actions\Trash\RestoreTrashed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Trash\TrashBulkRequest;
use App\Http\Requests\Trash\TrashEmptyRequest;
use App\Services\TrashService;
use App\Support\TrashModules;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TrashController extends Controller
{
    public function __construct(private TrashService $trash) {}

    public function index(): Response
    {
        $user = request()->user();

        $modules = $this->trash->platformModulesFor($user);
        $counts = $this->trash->platformCounts($user);

        $requested = (string) request()->query('module', '');
        $active = isset($modules[$requested]) ? $requested : $this->defaultModule($modules, $counts);

        $items = $active !== null
            ? $this->trash->platformItems($user, $active)
            : null;

        return Inertia::render('platform/trash/index', [
            'modules' => collect($modules)->map(fn (array $module, string $key): array => [
                'key' => $key,
                'label' => $module['label'],
                'count' => $counts[$key] ?? 0,
            ])->values()->all(),
            'activeModule' => $active,
            'items' => $items,
        ]);
    }

    public function restore(TrashBulkRequest $request, RestoreTrashed $action): RedirectResponse
    {
        $moduleKey = (string) $request->validated('module');
        $this->authorizeModule($moduleKey);

        $result = $action->handle($request->user(), null, 'platform', $moduleKey, $request->validated('ids'));

        return $this->backWithReport($result, 'restored', 'Restored');
    }

    public function force(TrashBulkRequest $request, ForceDeleteTrashed $action): RedirectResponse
    {
        $moduleKey = (string) $request->validated('module');
        $this->authorizeModule($moduleKey);

        $result = $action->handle($request->user(), null, 'platform', $moduleKey, $request->validated('ids'));

        return $this->backWithReport($result, 'deleted', 'Permanently deleted');
    }

    public function empty(TrashEmptyRequest $request, EmptyTrashModule $action): RedirectResponse
    {
        $moduleKey = (string) $request->validated('module');
        $this->authorizeModule($moduleKey);

        $result = $action->handle($request->user(), null, 'platform', $moduleKey);

        return $this->backWithReport($result, 'deleted', 'Permanently deleted');
    }

    public function bulk(TrashBulkRequest $request, BulkSoftDelete $action): RedirectResponse
    {
        $moduleKey = (string) $request->validated('module');
        $this->authorizeModule($moduleKey);

        $result = $action->handle($request->user(), null, 'platform', $moduleKey, $request->validated('ids'));

        return $this->backWithReport($result, 'deleted', 'Moved to trash');
    }

    private function authorizeModule(string $moduleKey): void
    {
        $module = TrashModules::platform()[$moduleKey] ?? null;

        abort_if($module === null, 422, 'Unknown trash module.');
        abort_unless(request()->user()->can($module['permission']), 403);
    }

    /**
     * @param  array{deleted?: list<int>, restored?: list<int>, skipped: list<array{id: int, reason: string}>}  $result
     */
    private function backWithReport(array $result, string $doneKey, string $verb): RedirectResponse
    {
        $done = count($result[$doneKey] ?? $result['restored'] ?? []);
        $skipped = $result['skipped'] ?? [];

        $message = $done > 0
            ? $verb.' '.$done.' row'.($done === 1 ? '' : 's').(count($skipped) > 0 ? ', skipped '.count($skipped).'.' : '.')
            : 'Nothing changed.'.(count($skipped) > 0 ? ' Skipped '.count($skipped).' row'.(count($skipped) === 1 ? '' : 's').'.' : '');

        Inertia::flash('toast', ['type' => $done > 0 ? 'success' : 'warning', 'message' => $message]);

        if ($skipped !== []) {
            Inertia::flash('trash_skipped', $skipped);
        }

        return back();
    }

    /**
     * @param  array<string, array>  $modules
     * @param  array<string, int>  $counts
     */
    private function defaultModule(array $modules, array $counts): ?string
    {
        foreach ($modules as $key => $module) {
            if (($counts[$key] ?? 0) > 0) {
                return $key;
            }
        }

        return array_key_first($modules);
    }
}
