<div class="box panel">
    <div class="panel-body pad">
        <form action="<?=$action_url?>" method="post">
            <input type="hidden" name="csrf_token" value="<?=CSRF_TOKEN?>">
            <fieldset>
                <div class="field-instruct">
                    <label><?=lang('field_manager_pro_default_scope')?></label>
                </div>
                <div class="field-control">
                    <select name="default_scope">
                        <option value="complete" <?=($settings['default_scope'] ?? '') === 'complete' ? 'selected' : ''?>><?=lang('field_manager_pro_scope_complete')?></option>
                        <option value="fields" <?=($settings['default_scope'] ?? '') === 'fields' ? 'selected' : ''?>><?=lang('channel_fields')?></option>
                    </select>
                </div>
            </fieldset>

            <fieldset>
                <div class="field-instruct">
                    <label><?=lang('field_manager_pro_conflict_strategy')?></label>
                </div>
                <div class="field-control">
                    <select name="conflict_strategy">
                        <option value="prompt" <?=($settings['conflict_strategy'] ?? '') === 'prompt' ? 'selected' : ''?>><?=lang('field_manager_pro_conflict_prompt')?></option>
                        <option value="skip" <?=($settings['conflict_strategy'] ?? '') === 'skip' ? 'selected' : ''?>><?=lang('field_manager_pro_conflict_skip')?></option>
                        <option value="overwrite" <?=($settings['conflict_strategy'] ?? '') === 'overwrite' ? 'selected' : ''?>><?=lang('field_manager_pro_conflict_overwrite')?></option>
                        <option value="rename" <?=($settings['conflict_strategy'] ?? '') === 'rename' ? 'selected' : ''?>><?=lang('field_manager_pro_conflict_rename')?></option>
                    </select>
                </div>
            </fieldset>

            <fieldset class="last">
                <label class="choice block">
                    <input type="checkbox" name="backup_before_import" value="1" <?=! empty($settings['backup_before_import']) ? 'checked' : ''?>>
                    <?=lang('field_manager_pro_backup_before_import')?>
                </label>
            </fieldset>

            <fieldset class="form-ctrls">
                <button class="btn action"><?=lang('save')?></button>
            </fieldset>
        </form>
    </div>
</div>
