<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait FiltersByBranch
{
    /**
     * Apply branch filter to query based on authenticated user's branch
     *
     * @param Builder $query
     * @param string|null $branchIdParam Optional branch_id from request
     * @return Builder
     */
    protected function applyBranchFilter(Builder $query, ?string $branchIdParam = null): Builder
    {
        $user = auth()->user();

        // If user has a specific branch assigned, always filter by that branch
        // This ensures non-admin users only see data from their branch
        if ($user->branch_id) {
            return $query->where('branch_id', $user->branch_id);
        }

        // If user is admin (no branch_id) and branch_id is provided in request, filter by it
        if ($branchIdParam) {
            return $query->where('branch_id', $branchIdParam);
        }

        // If user is admin and no branch_id in request, show all branches
        return $query;
    }

    /**
     * Get the branch ID to use for creating records
     *
     * @param int|null $requestBranchId Branch ID from request
     * @return int
     */
    protected function getBranchIdForCreate(?int $requestBranchId = null): int
    {
        $user = auth()->user();

        // If user has a branch assigned, always use that
        if ($user->branch_id) {
            return $user->branch_id;
        }

        // If admin, use the provided branch_id or default to first branch
        return $requestBranchId ?? 1;
    }

    /**
     * Check if user can access a specific branch
     *
     * @param int $branchId
     * @return bool
     */
    protected function canAccessBranch(int $branchId): bool
    {
        $user = auth()->user();

        // If user has no branch_id (admin), can access all branches
        if (!$user->branch_id) {
            return true;
        }

        // Otherwise, can only access their assigned branch
        return $user->branch_id === $branchId;
    }
}
