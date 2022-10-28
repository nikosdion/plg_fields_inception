<?php
/**
 * @copyright (C) 2022 Nicholas K. Dionysopoulos
 * @license       GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Based on Joomla's subform field plugin with the following copyright notice:
 * Copyright (C) 2019 Open Source Matters, Inc. <https://www.joomla.org>
 */

namespace Joomla\Plugin\Fields\Inception\Plugin;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use stdClass;

defined('_JEXEC') or die;

/**
 * Fields inception (nested subform) Plugin
 *
 * @since  1.0.0
 */
class InceptionPlugin extends CMSPlugin
{
	/**
	 * Array to do a fast in-memory caching of all custom field items.
	 *
	 * @since 1.0.0
	 * @var   array
	 *
	 */
	protected static $customFieldsCache = null;

	/**
	 * Two-dimensional array to hold to do a fast in-memory caching of rendered
	 * subfield values.
	 *
	 * @since 1.0.0
	 * @var   array
	 *
	 */
	protected $renderCache = [];

	/**
	 * Returns the custom fields types.
	 *
	 * @return  string[][]
	 *
	 * @since   1.0.1
	 */
	public function onCustomFieldsGetTypes()
	{
		// Cache filesystem access / checks
		static $types_cache = array();

		if (isset($types_cache[$this->_type . $this->_name])) {
			return $types_cache[$this->_type . $this->_name];
		}

		$this->loadLanguage();

		$types = array();

		// The root of the plugin
		$root = JPATH_PLUGINS . '/' . $this->_type . '/' . $this->_name;

		foreach (Folder::files($root . '/tmpl', '.php') as $layout) {
			// Strip the extension
			$layout = str_replace('.php', '', $layout);

			// The data array
			$data = array();

			// The language key
			$key = strtoupper($layout);

			if ($key != strtoupper($this->_name)) {
				$key = strtoupper($this->_name) . '_' . $layout;
			}

			// Needed attributes
			$data['type'] = $layout;

			if ($this->getApplication()->getLanguage()->hasKey('PLG_FIELDS_' . $key . '_LABEL')) {
				$data['label'] = Text::sprintf('PLG_FIELDS_' . $key . '_LABEL', strtolower($key));

				// Fix wrongly set parentheses in RTL languages
				if ($this->getApplication()->getLanguage()->isRtl()) {
					$data['label'] = $data['label'] . '&#x200E;';
				}
			} else {
				$data['label'] = $key;
			}

			$path = $root . '/fields';

			// Add the path when it exists
			if (file_exists($path)) {
				$data['path'] = $path;
			}

			$path = $root . '/rules';

			// Add the path when it exists
			if (file_exists($path)) {
				$data['rules'] = $path;
			}

			$types[] = $data;
		}

		// Add to cache and return the data
		$types_cache[$this->_type . $this->_name] = $types;

		return $types;
	}

	/**
	 * Handles the onContentPrepareForm event. Adds form definitions to relevant forms.
	 *
	 * @param   Form          $form  The form to manipulate
	 * @param   array|object  $data  The data of the form
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function onContentPrepareForm(Form $form, $data)
	{
		// Get the path to our own form definition (basically ./params/inception.xml)
		$path = $this->getFormPath($form, $data);

		if ($path === null)
		{
			return;
		}

		// Ensure it is an object
		$formData = (object) $data;

		// Now load our own form definition into a DOMDocument, because we want to manipulate it
		$xml = new DOMDocument();
		$xml->load($path);

		// Prepare a DOMXPath object
		$xmlxpath = new DOMXPath($xml);

		/**
		 * Get all fields of type "subfields" in our own XML
		 *
		 * @var $valuefields \DOMNodeList
		 */
		$valuefields = $xmlxpath->evaluate('//field[@type="subfields"]');

		// If we haven't found it, something is wrong
		if (!$valuefields || $valuefields->length != 1)
		{
			return;
		}

		// Now iterate over those fields and manipulate them, set its parameter `context` to our context
		foreach ($valuefields as $valuefield)
		{
			$valuefield->setAttribute('context', $formData->context);
		}

		// When this is not a new instance (editing an existing instance)
		if (isset($formData->id) && $formData->id > 0)
		{
			// Don't allow the 'repeat' attribute to be edited
			foreach ($xmlxpath->evaluate('//field[@name="repeat"]') as $field)
			{
				$field->setAttribute('readonly', '1');
			}
		}

		// And now load our manipulated form definition into the JForm
		$form->load($xml->saveXML(), true, '/form/*');
	}

	/**
	 * Manipulates the $field->value before the field is being passed to
	 * onCustomFieldsPrepareField.
	 *
	 * @param   string    $context  The context
	 * @param   object    $item     The item
	 * @param   stdClass  $field    The field
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function onCustomFieldsBeforePrepareField($context, $item, $field)
	{
		// Check if the field should be processed by us
		if (!$this->isTypeSupported($field->type))
		{
			return;
		}

		$decoded_value = json_decode($field->value, true);

		if (!$decoded_value || !is_array($decoded_value))
		{
			return;
		}

		$field->value = $decoded_value;
	}

	/**
	 * Returns a DOMElement which is the child of $parent and represents
	 * the form XML definition for this field.
	 *
	 * @param   stdClass    $field   The field
	 * @param   DOMElement  $parent  The original parent element
	 * @param   Form        $form    The form
	 *
	 * @return  DOMElement
	 * @since   1.0.0
	 */
	public function onCustomFieldsPrepareDom($field, DOMElement $parent, Form $form)
	{
		// Call the onCustomFieldsPrepareDom method on FieldsPlugin
		$parent_field = $this->parentOnCustomFieldsPrepareDom($field, $parent, $form);

		if (!$parent_field)
		{
			return $parent_field;
		}

		$inception = $parent_field->parentNode->tagName === 'form';

		$parent_field->setAttribute('addfieldpath', __DIR__ . '/fields');

		// Override the fieldname attribute of the subform - this is being used to index the rows
		$parent_field->setAttribute('fieldname', 'row');

		// If the user configured this subform instance as required
		if ($field->required)
		{
			// Then we need to have at least one row
			$parent_field->setAttribute('min', '1');
		}

		// Get the configured parameters for this field
		$field_params = $this->getParamsFromField($field);

		// If this fields should be repeatable, set some attributes on the subform element
		if ($field_params->get('repeat', '1') == '1')
		{
			$parent_field->setAttribute('multiple', 'true');
			$parent_field->setAttribute('layout', 'inception.repeatable-table');
		}

		// Create a child 'form' DOMElement under the field[type=subform] element.
		$parent_fieldset = $parent_field->appendChild(new DOMElement('form'));
		$parent_fieldset->setAttribute('hidden', 'true');
		$parent_fieldset->setAttribute('name', ($field->name . '_modal'));

		if ($field_params->get('max_rows'))
		{
			$parent_field->setAttribute('max', $field_params->get('max_rows'));
		}

		// If this field should be repeatable, set some attributes on the modal
		if ($field_params->get('repeat', '1') == '1')
		{
			$parent_fieldset->setAttribute('repeat', 'true');
		}

		// Get the configured sub fields for this field
		$subfields = $this->getSubfieldsFromField($field);

		// If we have 5 or more of them, use the `repeatable` layout instead of the `repeatable-table`
		if (!$inception || count($subfields) >= 5)
		{
			$parent_field->setAttribute('layout', 'inception.repeatable');
		}

		$customLayout = $field->params->get('edit_layout');

		if ($customLayout)
		{
			$parent_field->setAttribute('layout', $customLayout);
		}

		// Iterate over the sub fields to call prepareDom on each of those sub-fields
		foreach ($subfields as $subfield)
		{
			// Let the relevant plugins do their work and insert the correct
			// DOMElement's into our $parent_fieldset.
			Factory::getApplication()->triggerEvent(
				'onCustomFieldsPrepareDom',
				[$subfield, $parent_fieldset, $form]
			);
		}

		return $parent_field;
	}

	/**
	 * Renders this fields value by rendering all sub fields and joining all those rendered sub fields together.
	 *
	 * @param   string    $context  The context
	 * @param   object    $item     The item
	 * @param   stdClass  $field    The field
	 *
	 * @return  string
	 * @since   1.0.0
	 */
	public function onCustomFieldsPrepareField($context, $item, $field)
	{
		// Check if the field should be processed by us
		if (!$this->isTypeSupported($field->type))
		{
			return '';
		}

		// If we don't have any subfields (or values for them), nothing to do.
		if (!is_array($field->value) || count($field->value) < 1)
		{
			return '';
		}

		// Get the field params
		$field_params = $this->getParamsFromField($field);

		/**
		 * Placeholder to hold all rows (if this field is repeatable).
		 * Each array entry is another array representing a row, containing all of the sub fields that
		 * are valid for this row and their raw and rendered values.
		 */
		$subform_rows = [];

		// Create an array with entries being subfields forms, and if not repeatable, containing only one element.
		$rows = $field->value;

		if ($field_params->get('repeat', '1') == '0')
		{
			$rows = [$field->value];
		}

		// Iterate over each row of the data
		foreach ($rows as $row)
		{
			// Holds all sub fields of this row, incl. their raw and rendered value
			$row_subfields = [];

			// For each row, iterate over all the subfields
			foreach ($this->getSubfieldsFromField($field) as $subfield)
			{
				// Fill value (and rawvalue) if we have data for this subfield in the current row, otherwise set them to empty
				$subfield->rawvalue = $subfield->value = $row[$subfield->name] ?? '';

				// Do we want to render the value of this field, and is the value non-empty?
				if ($subfield->value !== '' && $subfield->render_values == '1')
				{
					/**
					 * Construct the cache-key for our renderCache. It is important that the cache key
					 * is as unique as possible to avoid false duplicates (e.g. type and rawvalue is not
					 * enough for the cache key, because type 'list' and value '1' can have different
					 * rendered values, depending on the list items), but it also must be as general as possible
					 * to not cause too many unneeded rendering processes (e.g. the type 'text' will always be
					 * rendered the same when it has the same rawvalue).
					 */
					$renderCache_key = serialize(
						[
							$subfield->type,
							$subfield->id,
							$subfield->rawvalue,
						]
					);

					// Let's see if we have a fast in-memory result for this
					if (isset($this->renderCache[$renderCache_key]))
					{
						$subfield->value = $this->renderCache[$renderCache_key];
					}
					else
					{
						// Render this virtual subfield
						$subfield->value                     = Factory::getApplication()->triggerEvent(
							'onCustomFieldsPrepareField',
							[$context, $item, $subfield]
						);
						$this->renderCache[$renderCache_key] = $subfield->value;
					}
				}

				// Flatten the value if it is an array (list, checkboxes, etc.) [independent of render_values]
				if (is_array($subfield->value))
				{
					$subfield->value = implode(' ', $subfield->value);
				}

				// Store the subfield (incl. its raw and rendered value) into this rows sub fields
				$row_subfields[$subfield->fieldname] = $subfield;
			}

			// Store all the sub fields of this row
			$subform_rows[] = $row_subfields;
		}

		// Store all the rows and their corresponding sub fields in $field->subform_rows
		$field->subform_rows = $subform_rows;

		// Call our parent to combine all those together for the final $field->value
		return $this->parentOnCustomFieldsPrepareField($context, $item, $field);
	}

	/**
	 * Returns an array of all options configured for this field.
	 *
	 * @param   stdClass  $field  The field
	 *
	 * @return  stdClass[]
	 * @since   1.0.0
	 */
	protected function getOptionsFromField(stdClass $field)
	{
		$result = [];

		// Fetch the options from the plugin
		$params = $this->getParamsFromField($field);

		foreach ($params->get('options', []) as $option)
		{
			$result[] = (object) $option;
		}

		return $result;
	}

	/**
	 * Returns the configured params for a given field.
	 *
	 * @param   stdClass  $field  The field
	 *
	 * @return  \Joomla\Registry\Registry
	 * @since   1.0.0
	 */
	protected function getParamsFromField(stdClass $field)
	{
		$params = (clone $this->params);

		if (isset($field->fieldparams) && is_object($field->fieldparams))
		{
			$params->merge($field->fieldparams);
		}

		return $params;
	}

	/**
	 * Returns an array of all subfields for a given field. This will always return a bare clone
	 * of a sub field, so manipulating it is safe.
	 *
	 * @param   stdClass  $field  The field
	 *
	 * @return  stdClass[]
	 * @since   1.0.0
	 */
	protected function getSubfieldsFromField(stdClass $field)
	{
		if (static::$customFieldsCache === null)
		{
			// Prepare our cache
			static::$customFieldsCache = [];

			// Get all custom field instances
			$customFields = FieldsHelper::getFields('', null, false, null, true);

			foreach ($customFields as $customField)
			{
				// Store each custom field instance in our cache with its id as key
				static::$customFieldsCache[$customField->id] = $customField;
			}
		}

		$result = [];

		// Iterate over all configured options for this field
		foreach ($this->getOptionsFromField($field) as $option)
		{
			// Check whether the wanted sub field really is an existing custom field
			if (!isset(static::$customFieldsCache[$option->customfield]))
			{
				continue;
			}

			// Get a clone of the sub field, so we and the caller can do some manipulation with it.
			$cur_field = (clone static::$customFieldsCache[$option->customfield]);

			// Manipulate it and add our custom configuration to it
			$cur_field->render_values = $option->render_values;

			/**
			 * Set the name of the sub field to its id so that the values in the database are being saved
			 * based on the id of the sub fields, not on their name. Actually we do not need the name of
			 * the sub fields to render them, but just to make sure we have the name when we need it, we
			 * store it as `fieldname`.
			 */
			$cur_field->fieldname = $cur_field->name;
			$cur_field->name      = 'field' . $cur_field->id;

			// And add it to our result
			$result[] = $cur_field;
		}

		return $result;
	}

	/**
	 * Prepares the field value.
	 *
	 * @param   string    $context  The context.
	 * @param   stdClass  $item     The item.
	 * @param   stdClass  $field    The field.
	 *
	 * @return  string
	 *
	 * @since   1.0.1
	 */
	protected function parentOnCustomFieldsPrepareField($context, $item, $field)
	{
		// Check if the field should be processed by us
		if (!$this->isTypeSupported($field->type)) {
			return '';
		}

		// Merge the params from the plugin and field which has precedence
		$fieldParams = clone $this->params;
		$fieldParams->merge($field->fieldparams);

		// Get the path for the layout file
		$path = PluginHelper::getLayoutPath('fields', $this->_name, $field->type);

		// Render the layout
		ob_start();
		include $path;
		$output = ob_get_clean();

		// Return the output
		return $output;
	}

	/**
	 * Transforms the field into a DOM XML element and appends it as a child on the given parent.
	 *
	 * @param   stdClass    $field   The field.
	 * @param   DOMElement  $parent  The field node parent.
	 * @param   Form        $form    The form.
	 *
	 * @return  DOMElement
	 *
	 * @since   1.0.1
	 */
	protected function parentOnCustomFieldsPrepareDom($field, DOMElement $parent, Form $form)
	{
		// Check if the field should be processed by us
		if (!$this->isTypeSupported($field->type)) {
			return null;
		}

		// Detect if the field is configured to be displayed on the form
		if (!FieldsHelper::displayFieldOnForm($field)) {
			return null;
		}

		// Create the node
		$node = $parent->appendChild(new DOMElement('field'));

		// Set the attributes
		$node->setAttribute('name', $field->name);
		$node->setAttribute('type', $field->type);
		$node->setAttribute('label', $field->label);
		$node->setAttribute('labelclass', $field->params->get('label_class', ''));
		$node->setAttribute('description', $field->description);
		$node->setAttribute('class', $field->params->get('class', ''));
		$node->setAttribute('hint', $field->params->get('hint', ''));
		$node->setAttribute('required', $field->required ? 'true' : 'false');

		if ($layout = $field->params->get('form_layout')) {
			$node->setAttribute('layout', $layout);
		}

		if ($field->default_value !== '') {
			$defaultNode = $node->appendChild(new DOMElement('default'));
			$defaultNode->appendChild(new \DOMCdataSection($field->default_value));
		}

		// Combine the two params
		$params = clone $this->params;
		$params->merge($field->fieldparams);

		// Set the specific field parameters
		foreach ($params->toArray() as $key => $param) {
			if (is_array($param)) {
				// Multidimensional arrays (eg. list options) can't be transformed properly
				$param = count($param) == count($param, COUNT_RECURSIVE) ? implode(',', $param) : '';
			}

			if ($param === '' || (!is_string($param) && !is_numeric($param))) {
				continue;
			}

			$node->setAttribute($key, $param);
		}

		// Check if it is allowed to edit the field
		if (!FieldsHelper::canEditFieldValue($field)) {
			$node->setAttribute('disabled', 'true');
		}

		// Return the node
		return $node;
	}

	/**
	 * Returns the path of the XML definition file for the field parameters
	 *
	 * @param   Form       $form  The form
	 * @param   \stdClass  $data  The data
	 *
	 * @return  string
	 *
	 * @since   1.0.1
	 */
	protected function getFormPath(Form $form, $data)
	{
		// Check if the field form is calling us
		if (strpos($form->getName(), 'com_fields.field') !== 0) {
			return null;
		}

		// Ensure it is an object
		$formData = (object) $data;

		// Gather the type
		$type = $form->getValue('type');

		if (!empty($formData->type)) {
			$type = $formData->type;
		}

		// Not us
		if (!$this->isTypeSupported($type)) {
			return null;
		}

		$path = JPATH_PLUGINS . '/' . $this->_type . '/' . $this->_name . '/params/' . $type . '.xml';

		// Check if params file exists
		if (!file_exists($path)) {
			return null;
		}

		return $path;
	}

	/**
	 * Returns true if the given type is supported by the plugin.
	 *
	 * @param   string  $type  The type
	 *
	 * @return  boolean
	 *
	 * @since   1.0.1
	 */
	protected function isTypeSupported($type)
	{
		foreach ($this->onCustomFieldsGetTypes() as $typeSpecification) {
			if ($type == $typeSpecification['type']) {
				return true;
			}
		}

		return false;
	}
}
