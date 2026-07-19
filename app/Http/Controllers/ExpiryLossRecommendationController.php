<?php

namespace App\Http\Controllers;

use App\Models\AgentRun;
use App\Models\ExpiryLossRecommendation;
use App\Services\Agent\ExpiryLossPreventionService;
use App\Services\Agent\HumanApprovalGuardService;
use App\Services\Agent\ReasoningActivityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class ExpiryLossRecommendationController extends Controller
{
    public function agentExpiryLossPage(): View
    {
        return view('agent.expiry-loss', [
            'title' => 'Ting Hao | Expiry Loss Prevention',
            'summary' => $this->summary(),
            'recommendations' => ExpiryLossRecommendation::query()
                ->with(['ingredient:id,name,sku,unit,expiry_date', 'reviewedBy:id,name', 'agentRun:id,status,created_at'])
                ->latest()
                ->paginate(10),
            'latestScan' => AgentRun::query()
                ->where('input_type', 'expiry_loss_scan')
                ->latest()
                ->first(),
        ]);
    }

    public function scan(Request $request, ExpiryLossPreventionService $service): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $result = $service->scan($request->user());
        Cache::forget(DashboardController::CACHE_KEY);

        return redirect()
            ->route('agent.runs.show', $result['agent_run'])
            ->with('status', 'Expiry loss scan completed. Potential RM at risk: RM '.number_format($result['total_potential_loss'], 2).'.');
    }

    public function show(ExpiryLossRecommendation $expiryLossRecommendation): View
    {
        $expiryLossRecommendation->load(['ingredient.category', 'agentRun.toolCalls', 'agentRun.reasoningSteps.relatedToolCall', 'reviewedBy:id,name']);

        return view('expiry-loss-recommendations.show', [
            'title' => 'Ting Hao | Expiry Loss Recommendation',
            'recommendation' => $expiryLossRecommendation,
        ]);
    }

    public function review(Request $request, ExpiryLossRecommendation $expiryLossRecommendation, HumanApprovalGuardService $guard, ReasoningActivityService $reasoningActivity): RedirectResponse
    {
        return $this->updateStatus($request, $expiryLossRecommendation, ExpiryLossRecommendation::STATUS_REVIEWED, 'Recommendation marked reviewed.', $guard, $reasoningActivity);
    }

    public function dismiss(Request $request, ExpiryLossRecommendation $expiryLossRecommendation, HumanApprovalGuardService $guard, ReasoningActivityService $reasoningActivity): RedirectResponse
    {
        return $this->updateStatus($request, $expiryLossRecommendation, ExpiryLossRecommendation::STATUS_DISMISSED, 'Recommendation dismissed.', $guard, $reasoningActivity);
    }

    public function complete(Request $request, ExpiryLossRecommendation $expiryLossRecommendation, HumanApprovalGuardService $guard, ReasoningActivityService $reasoningActivity): RedirectResponse
    {
        return $this->updateStatus($request, $expiryLossRecommendation, ExpiryLossRecommendation::STATUS_COMPLETED, 'Recommendation marked completed.', $guard, $reasoningActivity);
    }

    private function updateStatus(Request $request, ExpiryLossRecommendation $recommendation, string $status, string $message, HumanApprovalGuardService $guard, ReasoningActivityService $reasoningActivity): RedirectResponse
    {
        $guard->assertAdminCanApprove($request->user(), HumanApprovalGuardService::ACTION_EXPIRY_RECOMMENDATION_COMPLETION);

        $recommendation->update([
            'status' => $status,
            'reviewed_by' => $request->user()->id,
        ]);
        if ($recommendation->agentRun) {
            $reasoningActivity->humanCheckpoint($recommendation->agentRun, 'Expiry recommendation status updated', 'Admin changed the expiry recommendation status to '.$status.'. The agent cannot complete or dismiss expiry recommendations autonomously.', [
                'expiry_loss_recommendation_id' => $recommendation->id,
                'status' => $status,
                'reviewed_by' => $request->user()->id,
            ]);
        }

        Cache::forget(DashboardController::CACHE_KEY);

        return back()->with('status', $message);
    }

    /**
     * @return array{total_potential_loss: float, at_risk_count: int, open_count: int, highest_risk_name: string|null, highest_risk_loss: float|null}
     */
    private function summary(): array
    {
        $openStatuses = ExpiryLossRecommendation::OPEN_STATUSES;

        $openRecommendations = ExpiryLossRecommendation::query()
            ->select(['id', 'ingredient_id', 'potential_loss', 'status'])
            ->with('ingredient:id,name')
            ->whereIn('status', $openStatuses)
            ->get();

        $highestRisk = $openRecommendations
            ->sortByDesc(fn (ExpiryLossRecommendation $recommendation): float => (float) ($recommendation->potential_loss ?? 0))
            ->first();

        return [
            'total_potential_loss' => (float) $openRecommendations->sum(fn (ExpiryLossRecommendation $recommendation): float => (float) ($recommendation->potential_loss ?? 0)),
            'at_risk_count' => $openRecommendations->pluck('ingredient_id')->unique()->count(),
            'open_count' => $openRecommendations->count(),
            'highest_risk_name' => $highestRisk?->ingredient?->name,
            'highest_risk_loss' => $highestRisk?->potential_loss !== null ? (float) $highestRisk->potential_loss : null,
        ];
    }
}
