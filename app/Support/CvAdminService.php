<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CvAdminService
{
    public static function getCvList(
        int $page = 1,
        int $perPage = 15,
        string $sort = 'id',
        string $order = 'DESC',
        string $search = '',
        string $status = ''
    ): array {
        $sort = in_array($sort, ['id', 'full_name', 'job_title', 'created_at', 'view_count', 'download_count'], true)
            ? $sort : 'id';
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        $offset = max(0, ($page - 1) * $perPage);

        $query = DB::table('cv_infos')
            ->leftJoin('users', 'cv_infos.user_id', '=', 'users.id')
            ->select('cv_infos.id', 'cv_infos.user_id', 'cv_infos.full_name', 'cv_infos.job_title', 'cv_infos.email', 'cv_infos.phone', 'cv_infos.is_active', 'cv_infos.view_count', 'cv_infos.download_count', 'cv_infos.last_viewed_at', 'cv_infos.created_at', 'cv_infos.updated_at', 'users.username as username')
            ->where('cv_infos.deleted_at', null);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('cv_infos.full_name', 'LIKE', "%{$search}%")
                    ->orWhere('cv_infos.job_title', 'LIKE', "%{$search}%")
                    ->orWhere('cv_infos.email', 'LIKE', "%{$search}%");
            });
        }

        if ($status !== '' && in_array($status, ['active', 'inactive'])) {
            $query->where('is_active', $status === 'active' ? 1 : 0);
        }

        $total = $query->count();

        $cvs = $query
            // Qualify with the table: the users join makes bare id/created_at
            // ambiguous in the ORDER BY clause.
            ->orderBy('cv_infos.' . $sort, $order)
            ->skip($offset)
            ->take($perPage)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'user_id' => $c->user_id,
                'full_name' => $c->full_name ?? '',
                'job_title' => $c->job_title ?? '',
                'email' => $c->email ?? '',
                'phone' => $c->phone ?? '',
                'username' => $c->username ?? 'Unknown',
                'is_active' => (bool) ($c->is_active ?? false),
                'view_count' => $c->view_count ?? 0,
                'download_count' => $c->download_count ?? 0,
                'last_viewed_at' => $c->last_viewed_at ? Carbon::parse($c->last_viewed_at)->format('M j, Y g:i A') : 'Never',
                'created_at' => $c->created_at,
                'updated_at' => $c->updated_at,
            ])
            ->all();

        return [
            'cvs' => $cvs,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) ceil($total / $perPage),
            ],
            'sort' => $sort,
            'order' => $order,
            'search' => $search,
            'status_filter' => $status,
        ];
    }

    public static function getCvById(int $id): ?array
    {
        $cv = DB::table('cv_infos')
            ->select('id', 'user_id', 'full_name', 'job_title', 'email', 'phone', 'address', 'date_of_birth', 'nationality', 'gender', 'website', 'linkedin', 'github', 'twitter', 'portfolio', 'is_active', 'view_count', 'download_count', 'last_viewed_at', 'created_at', 'updated_at', 'profile_photo')
            ->where('id', $id)
            ->where('deleted_at', null)
            ->first();

        if (!$cv) {
            return null;
        }

        $user = DB::table('users')->where('id', $cv->user_id)->where('deleted_at', null)->first();

        return [
            'id' => $cv->id,
            'user_id' => $cv->user_id,
            'full_name' => $cv->full_name ?? '',
            'job_title' => $cv->job_title ?? '',
            'email' => $cv->email ?? '',
            'phone' => $cv->phone ?? '',
            'address' => $cv->address ?? '',
            'date_of_birth' => $cv->date_of_birth,
            'nationality' => $cv->nationality ?? '',
            'gender' => $cv->gender ?? '',
            'website' => $cv->website ?? '',
            'linkedin' => $cv->linkedin ?? '',
            'github' => $cv->github ?? '',
            'twitter' => $cv->twitter ?? '',
            'portfolio' => $cv->portfolio ?? '',
            'is_active' => (bool) ($cv->is_active ?? false),
            'view_count' => $cv->view_count ?? 0,
            'download_count' => $cv->download_count ?? 0,
            'last_viewed_at' => $cv->last_viewed_at ? Carbon::parse($cv->last_viewed_at)->format('M j, Y g:i A') : 'Never',
            'profile_photo' => $cv->profile_photo ?? '',
            'created_at' => $cv->created_at,
            'updated_at' => $cv->updated_at,
            'username' => $user?->username ?? 'Unknown',
            'user_email' => $user?->email ?? 'Unknown',
        ];
    }
}
