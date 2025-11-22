<div class="box panel">
    <div class="panel-body pad">
        <form action="<?=$import_action?>" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?=CSRF_TOKEN?>">
            <fieldset>
                <div class="field-instruct">
                    <label><?=lang('field_manager_pro_upload_label')?></label>
                    <em><?=lang('field_manager_pro_upload_desc')?></em>
                </div>
                <div class="field-control">
                    <input type="file" name="import_file" accept="application/json">
                </div>
            </fieldset>

            <fieldset>
                <div class="field-instruct">
                    <label><?=lang('field_manager_pro_conflict_strategy')?></label>
                    <em><?=lang('field_manager_pro_conflict_desc')?></em>
                </div>
                <div class="field-control">
                    <select name="strategy">
                        <?php foreach ($strategies as $key => $label): ?>
                            <option value="<?=$key?>" <?=($selected_strategy ?? '') === $key ? 'selected' : ''?>><?=$label?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </fieldset>

            <?php if (! empty($result)): ?>
                <div class="alert <?=! empty($result['success']) ? 'inline success' : 'inline issue'?>">
                    <h3><?=lang('field_manager_pro_import_result')?></h3>
                    <pre><?=json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)?></pre>
                </div>
            <?php endif; ?>

            <?php if (! empty($preview)): ?>
                <fieldset>
                    <div class="field-instruct">
                        <label><?=lang('field_manager_pro_preview_label')?></label>
                    </div>
                    <div class="field-control">
                        <ul>
                            <li><?=lang('channels')?>: <?=$preview['channels']?></li>
                            <li><?=lang('field_groups')?>: <?=$preview['field_groups']?></li>
                            <li><?=lang('fields')?>: <?=$preview['fields']?></li>
                        </ul>
                    </div>
                </fieldset>
            <?php endif; ?>

        <?php if (! empty($errors)): ?>
            <div class="alert inline issue">
                <h3><?=lang('field_manager_pro_import_errors')?></h3>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <?php if (is_array($error)) $error = implode(', ', array_filter($error)); ?>
                        <li><?=$error?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

            <fieldset class="form-ctrls">
                <button class="btn" name="action" value="preview"><?=lang('field_manager_pro_preview_button')?></button>
                <button class="btn action" name="action" value="import"><?=lang('field_manager_pro_import_button')?></button>
            </fieldset>
        </form>
    </div>
</div>
