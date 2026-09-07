<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Tags + categories lookups — ported from legacy
 * ContentModel::getTagsForContent / getCategoriesForContent (+ batch forms).
 */
class PostTaxonomy
{
    public function tagsForContent(string $type, int $contentId): array
    {
        return $this->tagsForContentBatch($type, [$contentId])[$contentId] ?? [];
    }

    public function categoriesForContent(string $type, int $contentId): array
    {
        return $this->categoriesForContentBatch($type, [$contentId])[$contentId] ?? [];
    }

    public function tagsForContentBatch(string $type, array $contentIds): array
    {
        if (empty($contentIds)) {
            return [];
        }

        $rows = DB::table('content_tags as ct')
            ->join('tags as t', 'ct.tag_id', '=', 't.id')
            ->select('ct.content_id', 't.id', 't.name', 't.slug')
            ->where('ct.content_type', $type)
            ->whereIn('ct.content_id', $contentIds)
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row->content_id][] = ['id' => (int) $row->id, 'name' => $row->name, 'slug' => $row->slug];
        }

        return $grouped;
    }

    public function categoriesForContentBatch(string $type, array $contentIds): array
    {
        if (empty($contentIds)) {
            return [];
        }

        $rows = DB::table('content_categories as cc')
            ->join('categories as c', 'cc.category_id', '=', 'c.id')
            ->select('cc.content_id', 'c.id', 'c.name', 'c.slug')
            ->where('cc.content_type', $type)
            ->whereIn('cc.content_id', $contentIds)
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row->content_id][] = ['id' => (int) $row->id, 'name' => $row->name, 'slug' => $row->slug];
        }

        return $grouped;
    }
}