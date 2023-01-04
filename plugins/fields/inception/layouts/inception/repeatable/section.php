<?php
/**
 * @copyright (C) 2022-2023 Nicholas K. Dionysopoulos
 * @license       GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Based on Joomla's subform field plugin with the following copyright notice:
 * Copyright (C) 2019 Open Source Matters, Inc. <https://www.joomla.org>
 */

defined('_JEXEC') or die;

use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormHelper;
use Joomla\CMS\Language\Text;

extract($displayData);

/**
 * Layout variables
 * -----------------
 * @var   Form    $form       The form instance for render the section
 * @var   string  $basegroup  The base group name
 * @var   string  $group      Current group name
 * @var   array   $buttons    Array of the buttons that will be rendered
 */
?>

<div class="subform-repeatable-group" data-base-name="<?php echo $basegroup; ?>" data-group="<?php echo $group; ?>">
    <?php if (!empty($buttons)) : ?>
    <div class="btn-toolbar text-end">
        <div class="btn-group">
            <?php if (!empty($buttons['add'])) :
                ?><button type="button" class="group-add btn btn-sm btn-success" aria-label="<?php echo Text::_('JGLOBAL_FIELD_ADD'); ?>"><span class="icon-plus icon-white" aria-hidden="true"></span> </button><?php
            endif; ?>
            <?php if (!empty($buttons['remove'])) :
                ?><button type="button" class="group-remove btn btn-sm btn-danger" aria-label="<?php echo Text::_('JGLOBAL_FIELD_REMOVE'); ?>"><span class="icon-minus icon-white" aria-hidden="true"></span> </button><?php
            endif; ?>
            <?php if (!empty($buttons['move'])) :
                ?><button type="button" class="group-move btn btn-sm btn-primary" aria-label="<?php echo Text::_('JGLOBAL_FIELD_MOVE'); ?>"><span class="icon-arrows-alt icon-white" aria-hidden="true"></span> </button><?php
            endif; ?>
        </div>
    </div>
    <?php endif; ?>

<?php foreach ($form->getGroup('') as $field) : ?>
	<?php
		$refObj = new ReflectionObject($field);
		$refGetLabel = $refObj->getMethod('getLabel');
		$refGetLabel->setAccessible(true);
		$refGetInput = $refObj->getMethod('getInput');
		$refGetInput->setAccessible(true);

		$attributes = [];

		if ($field->showon) {
			$attributes[] = 'data-showon=\'' . json_encode(FormHelper::parseShowOnConditions($field->showon, $field->formControl, $field->group)) . '\'';
		}

	?>
<div <?= implode(' ', $attributes) ?>
		data-derp-showon="<?= $field->showon ?>"
		data-derp-formControl="<?= $field->formControl ?>"
		data-derp-group="<?= $field->group ?>"
>
	<h3><?= $refGetLabel->invoke($field) ?></h3>
    <div>
		<?= $refGetInput->invoke($field) ?>
	</div>
</div>
<?php endforeach; ?>
</div>
