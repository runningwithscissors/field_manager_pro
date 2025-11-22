<div class="box panel">
    <div class="panel-body pad">
        <div class="tbl-ctrls">
            <form action="<?=$export_action?>" method="post">
                <input type="hidden" name="csrf_token" value="<?=CSRF_TOKEN?>">
                <fieldset class="tbl-search right">
                    <select name="site_id">
                        <?php foreach ($filters['site_options'] ?? [] as $id => $label): ?>
                            <option value="<?=$id?>" <?=(int) ($filters['site_id'] ?? 0) === (int) $id ? 'selected' : ''?>><?=$label?></option>
                        <?php endforeach; ?>
                    </select>
                </fieldset>

                <h1><?=lang('field_manager_pro_export_heading')?></h1>
                <p class="txt-subhead"><?=lang('field_manager_pro_export_help_text')?></p>

                <div class="field-control">
                    <label class="choice block">
                        <input type="checkbox" id="fmp-select-all">
                        <?=lang('field_manager_pro_select_all_channels')?>
                    </label>
                </div>

                <table class="table-ss">
                    <thead>
                        <tr>
                            <th><?=lang('channels')?></th>
                            <th><?=lang('field_manager_pro_field_count_header')?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($channels as $channel): ?>
                            <tr>
                                <td>
                                    <label>
                                        <input type="checkbox" name="channels[]" value="<?=$channel['channel_id']?>" class="fmp-channel">
                                        <?=$channel['channel_title']?>
                                    </label>
                                </td>
                                <td><?=$channel['field_count'] ?? 0?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <fieldset class="form-ctrls">
                    <button class="btn action"><?=lang('field_manager_pro_export_button')?></button>
                </fieldset>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var selectAll = document.getElementById('fmp-select-all');
    if (!selectAll) {
        return;
    }
    selectAll.addEventListener('change', function () {
        var checkboxes = document.querySelectorAll('.fmp-channel');
        checkboxes.forEach(function (box) {
            box.checked = selectAll.checked;
        });
    });
});
</script>
