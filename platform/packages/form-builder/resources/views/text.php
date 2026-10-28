<?php if ($showLabel && $showField) { ?>
    <?php if ($options['wrapper'] !== false) { ?>
    <div <?= $options['wrapperAttrs'] ?> >
    <?php } ?>
<?php } ?>

<?php /** label rendering section */ ?>
<?php if ($showLabel && $options['label'] !== false && $options['label_show']) { ?>
    <?php if (array_key_exists('label_template', $options) && $options['label_template']) { ?>
        <?= view($options['label_template'], get_defined_vars())->render(); ?>
    <?php } else { ?>
        <?php include labelBlockPath(); ?>
    <?php } ?>
<?php } ?>

<?php if ($showField) { ?>
    <?= Form::input($type, $name, $options['value'], $options['attr']) ?>

    <?php include helpBlockPath(); ?>
<?php } ?>

<?php include errorBlockPath(); ?>

<?php if ($showLabel && $showField) { ?>
    <?php if ($options['wrapper'] !== false) { ?>
    </div>
    <?php } ?>
<?php } ?>
