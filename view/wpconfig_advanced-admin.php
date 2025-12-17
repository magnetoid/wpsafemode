<?php
/**
 * WP Config Advanced View - Premium Safe Mode Design
 */
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <span class="material-symbols-outlined" style="vertical-align: middle; margin-right: 8px;">settings</span>
            WP Configuration
        </h3>
    </div>

    <form action="" method="post">
        <div style="padding: var(--space-lg);">

            <?php if (isset($data['wpconfig_options'])): ?>
                <div
                    style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: var(--space-xl);">

                    <?php foreach ($data['wpconfig_options'] as $option): ?>
                        <?php
                        $value = isset($option['value']) ? $option['value'] : (isset($option['default_value']) ? $option['default_value'] : '');
                        $type = isset($option['input_type']) ? $option['input_type'] : 'text';
                        $label = isset($option['input_label']) ? $option['input_label'] : $option['name'];
                        $desc = isset($option['description']) ? $option['description'] : '';
                        $name = isset($option['input_key']) ? $option['input_key'] : $option['name'];
                        $id = 'field_' . $name;
                        ?>

                        <div class="form-group" style="margin-bottom: 0;">

                            <?php if ($type === 'switch' || $type === 'checkbox'): ?>
                                <div
                                    style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-xs);">
                                    <label for="<?php echo $id; ?>"
                                        style="font-weight: 500; cursor: pointer;"><?php echo htmlspecialchars($label); ?></label>
                                    <label class="switch">
                                        <input type="checkbox" id="<?php echo $id; ?>" name="<?php echo htmlspecialchars($name); ?>"
                                            value="true" <?php echo ($value === true || $value === 'true' || $value === 1 || $value === '1') ? 'checked' : ''; ?>>
                                        <span class="slider round"></span>
                                    </label>
                                </div>
                                <?php if ($desc): ?>
                                    <div class="text-muted" style="font-size: var(--font-size-sm); margin-top: var(--space-xs);">
                                        <?php echo htmlspecialchars($desc); ?>
                                    </div>
                                <?php endif; ?>

                            <?php elseif ($type === 'bigtext' || $type === 'textarea'): ?>
                                <label for="<?php echo $id; ?>" class="form-label"><?php echo htmlspecialchars($label); ?></label>
                                <textarea id="<?php echo $id; ?>" name="<?php echo htmlspecialchars($name); ?>" class="form-control"
                                    rows="4"
                                    style="width: 100%; padding: var(--space-sm); background: var(--color-bg-surface); border: 1px solid var(--color-border); border-radius: var(--radius-sm); color: var(--color-text-main); font-family: monospace;"><?php echo htmlspecialchars($value); ?></textarea>
                                <?php if ($desc): ?>
                                    <div class="text-muted" style="font-size: var(--font-size-sm); margin-top: var(--space-xs);">
                                        <?php echo htmlspecialchars($desc); ?>
                                    </div>
                                <?php endif; ?>

                            <?php else: ?>
                                <label for="<?php echo $id; ?>" class="form-label"><?php echo htmlspecialchars($label); ?></label>
                                <input type="<?php echo $name === 'db_password' ? 'password' : 'text'; ?>" id="<?php echo $id; ?>"
                                    name="<?php echo htmlspecialchars($name); ?>" value="<?php echo htmlspecialchars($value); ?>"
                                    class="form-control"
                                    style="width: 100%; padding: var(--space-sm); background: var(--color-bg-surface); border: 1px solid var(--color-border); border-radius: var(--radius-sm); color: var(--color-text-main);">
                                <?php if ($desc): ?>
                                    <div class="text-muted" style="font-size: var(--font-size-sm); margin-top: var(--space-xs);">
                                        <?php echo htmlspecialchars($desc); ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>

                        </div>
                    <?php endforeach; ?>

                </div>
            <?php endif; ?>

            <div
                style="margin-top: var(--space-xl); padding-top: var(--space-lg); border-top: 1px solid var(--color-border);">
                <button type="submit" name="saveconfig_advanced" class="btn btn-primary">
                    <span class="material-symbols-outlined">save</span>
                    Save Configuration
                </button>
                <?php echo CSRFProtection::get_token_field('wpconfig_advanced'); ?>
            </div>
        </div>
    </form>
</div>

<style>
    /* Switch Toggle Style */
    .switch {
        position: relative;
        display: inline-block;
        width: 40px;
        height: 24px;
    }

    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: var(--color-bg-surface-hover);
        transition: .4s;
        border: 1px solid var(--color-border);
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 16px;
        width: 16px;
        left: 3px;
        bottom: 3px;
        background-color: var(--color-text-muted);
        transition: .4s;
    }

    input:checked+.slider {
        background-color: var(--color-primary);
        border-color: var(--color-primary);
    }

    input:checked+.slider:before {
        transform: translateX(16px);
        background-color: #000;
    }

    .slider.round {
        border-radius: 24px;
    }

    .slider.round:before {
        border-radius: 50%;
    }
</style>