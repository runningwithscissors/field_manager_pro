<?php

namespace YourCompany\FieldManagerPro\Support;

/**
 * Centralises every EE model query used to translate between source-install ids
 * and portable natural keys, in both directions. Adapters never touch ids
 * directly; they go through ExportContext / ImportContext, which delegate here.
 *
 * Lookups are memoised per direction so a large import does not re-query the
 * same channel/field/member repeatedly.
 */
class KeyResolver
{
    /** @var array<string,?string> */
    protected array $idToKey = [];

    /** @var array<string,?int> */
    protected array $keyToId = [];

    // ----- Channels (channel_name) -------------------------------------------

    public function channelKey(int $channelId): ?string
    {
        $cache = "channel_key:{$channelId}";
        if (array_key_exists($cache, $this->idToKey)) {
            return $this->idToKey[$cache];
        }

        $channel = ee('Model')->get('Channel')
            ->fields('channel_name')
            ->filter('channel_id', $channelId)
            ->first();

        return $this->idToKey[$cache] = $channel ? $channel->channel_name : null;
    }

    public function channelId(string $channelName): ?int
    {
        $cache = "channel_id:{$channelName}";
        if (array_key_exists($cache, $this->keyToId)) {
            return $this->keyToId[$cache];
        }

        $channel = ee('Model')->get('Channel')
            ->fields('channel_id')
            ->filter('channel_name', $channelName)
            ->first();

        return $this->keyToId[$cache] = $channel ? (int) $channel->channel_id : null;
    }

    // ----- Channel fields (field_name) ---------------------------------------

    public function fieldKey(int $fieldId): ?string
    {
        $cache = "field_key:{$fieldId}";
        if (array_key_exists($cache, $this->idToKey)) {
            return $this->idToKey[$cache];
        }

        $field = ee('Model')->get('ChannelField')
            ->fields('field_name')
            ->filter('field_id', $fieldId)
            ->first();

        return $this->idToKey[$cache] = $field ? $field->field_name : null;
    }

    public function fieldId(string $fieldName): ?int
    {
        $cache = "field_id:{$fieldName}";
        if (array_key_exists($cache, $this->keyToId)) {
            return $this->keyToId[$cache];
        }

        $field = ee('Model')->get('ChannelField')
            ->fields('field_id')
            ->filter('field_name', $fieldName)
            ->first();

        return $this->keyToId[$cache] = $field ? (int) $field->field_id : null;
    }

    // ----- Categories (group_name/cat_url_title) -----------------------------
    // Relationship "categories" settings store individual cat_ids, so the
    // natural key is "{group_name}/{cat_url_title}" which is stable across
    // installs and unambiguous across groups.

    public function categoryKey(int $catId): ?string
    {
        $cache = "cat_key:{$catId}";
        if (array_key_exists($cache, $this->idToKey)) {
            return $this->idToKey[$cache];
        }

        $category = ee('Model')->get('Category')
            ->with('CategoryGroup')
            ->filter('cat_id', $catId)
            ->first();

        if (! $category) {
            return $this->idToKey[$cache] = null;
        }

        $groupName = $category->CategoryGroup ? $category->CategoryGroup->group_name : '';

        return $this->idToKey[$cache] = $groupName . '/' . $category->cat_url_title;
    }

    public function categoryId(string $key): ?int
    {
        $cache = "cat_id:{$key}";
        if (array_key_exists($cache, $this->keyToId)) {
            return $this->keyToId[$cache];
        }

        [$groupName, $urlTitle] = array_pad(explode('/', $key, 2), 2, null);
        if ($urlTitle === null || $groupName === '') {
            return $this->keyToId[$cache] = null;
        }

        $group = ee('Model')->get('CategoryGroup')
            ->fields('group_id')
            ->filter('group_name', $groupName)
            ->first();

        if (! $group) {
            return $this->keyToId[$cache] = null;
        }

        $category = ee('Model')->get('Category')
            ->fields('cat_id')
            ->filter('group_id', $group->group_id)
            ->filter('cat_url_title', $urlTitle)
            ->first();

        return $this->keyToId[$cache] = $category ? (int) $category->cat_id : null;
    }

    // ----- Members (username, fallback email) --------------------------------

    public function memberKey(int $memberId): ?string
    {
        $cache = "member_key:{$memberId}";
        if (array_key_exists($cache, $this->idToKey)) {
            return $this->idToKey[$cache];
        }

        $member = ee('Model')->get('Member')
            ->fields('username')
            ->filter('member_id', $memberId)
            ->first();

        return $this->idToKey[$cache] = $member ? $member->username : null;
    }

    public function memberId(string $identifier): ?int
    {
        $cache = "member_id:{$identifier}";
        if (array_key_exists($cache, $this->keyToId)) {
            return $this->keyToId[$cache];
        }

        $member = ee('Model')->get('Member')
            ->fields('member_id')
            ->filter('username', $identifier)
            ->first();

        if (! $member) {
            $member = ee('Model')->get('Member')
                ->fields('member_id')
                ->filter('email', $identifier)
                ->first();
        }

        return $this->keyToId[$cache] = $member ? (int) $member->member_id : null;
    }

    // ----- Statuses (name) ---------------------------------------------------
    // EE7 relationship "statuses" settings already store status names, so the
    // key IS the name. We only verify existence on the target.

    public function statusExists(string $statusName): bool
    {
        if (in_array($statusName, ['open', 'closed'], true)) {
            return true;
        }

        return (bool) ee('Model')->get('Status')
            ->filter('status', $statusName)
            ->first();
    }
}
