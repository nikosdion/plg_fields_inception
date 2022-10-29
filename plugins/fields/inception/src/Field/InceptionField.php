<?php

/**
 * @copyright (C) 2022 Nicholas K. Dionysopoulos
 * @license       GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Based on Joomla's subform field plugin with the following copyright notice:
 * Copyright (C) 2019 Open Source Matters, Inc. <https://www.joomla.org>
 */

namespace Joomla\Plugin\Fields\Inception\Field;

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\Path;
use Joomla\CMS\Form\Field\SubformField;

class InceptionField extends SubformField
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 */
	protected $type = 'Inception';

	protected function getLayoutPaths()
	{
		return array_merge(
			parent::getLayoutPaths(),
			empty($this->element['edit_layout'] ?? '') ? [] : $this->getCustomLayoutPaths(),
			[
				__DIR__ . '/../../layouts',
			]
		);
	}

	/**
	 * Get a list of paths where custom edit layouts are to be sought in.
	 *
	 * @return  array
	 * @throws  Exception
	 * @since   1.0.2
	 */
	private function getCustomLayoutPaths(): array
	{
		$paths = [];

		$activeTemplate = Factory::getApplication()->getTemplate();

		if (empty($activeTemplate))
		{
			return $paths;
		}

		$component = $this->element['_x_component'] ?? '';

		if (!empty($component))
		{
			$paths[] = Path::clean(JPATH_ADMINISTRATOR . '/templates/' . $activeTemplate . '/html/layouts/' . $component . '/field');
		}

		$paths[] = Path::clean(JPATH_ADMINISTRATOR . '/templates/' . $activeTemplate . '/html/layouts/com_fields/field');
		$paths[] = Path::clean(JPATH_ADMINISTRATOR . '/templates/' . $activeTemplate . '/html/layouts/field');

		return $paths;
	}


}