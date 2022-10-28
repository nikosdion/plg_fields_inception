<?php

use Joomla\CMS\Form\Field\SubformField;

/**
 * @copyright (C) 2022 Nicholas K. Dionysopoulos
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Based on Joomla's subform field plugin with the following copyright notice:
 * Copyright (C) 2019 Open Source Matters, Inc. <https://www.joomla.org>
 */

class JFormFieldInception extends SubformField
{
	/**
	 * The form field type.
	 * @var    string
	 */
	protected $type = 'Inception';

	protected function getLayoutPaths()
	{
		return array_merge(
			parent::getLayoutPaths(),
			[
				__DIR__ . '/../layouts'
			]
		);
	}


}