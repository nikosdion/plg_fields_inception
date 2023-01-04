<?php
/**
 * @copyright (C) 2022-2023 Nicholas K. Dionysopoulos
 * @license       GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Based on Joomla's subform field plugin with the following copyright notice:
 * Copyright (C) 2019 Open Source Matters, Inc. <https://www.joomla.org>
 */

namespace Joomla\Plugin\Fields\Inception\Plugin;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Joomla\CMS\Event\GenericEvent;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Filesystem\Path;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

defined('_JEXEC') or die;

/**
 * Fields inception (nested subform) Plugin
 *
 * @since  1.0.0
 */
final class InceptionPlugin extends CMSPlugin implements SubscriberInterface
{
	/**
	 * Cache of custom field items.
	 *
	 * @since 1.0.0
	 * @var   array
	 *
	 */
	private static $customFieldsCache = null;

	private static $rowCounters = [];

	protected $allowLegacyListeners = false;

	/**
	 * Cache of rendered subfield values.
	 *
	 * @since 1.0.0
	 * @var   array
	 *
	 */
	private $renderCache = [];

	/** @inheritDoc */
	public static function getSubscribedEvents(): array
	{
		return [
			'onContentPrepareForm'             => 'onContentPrepareForm',
			'onCustomFieldsBeforePrepareField' => 'onCustomFieldsBeforePrepareField',
			'onCustomFieldsGetTypes'           => 'onCustomFieldsGetTypes',
			'onCustomFieldsPrepareDom'         => 'onCustomFieldsPrepareDom',
			'onCustomFieldsPrepareField'       => 'onCustomFieldsPrepareField',
		];
	}

	/**
	 * Adds form definitions to relevant forms.
	 *
	 * @param   Event  $event  The onContentPrepareForm event we are handling
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function onContentPrepareForm(Event $event): void
	{
		/**
		 * @var   Form         $form The form to manipulate
		 * @var   array|object $data The data of the form
		 */
		[$form, $data] = $event->getArguments();

		// Get the path to our own form definition (basically ./src/params/inception.xml)
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
	 * Manipulates the value before the field is passed to onCustomFieldsPrepareField.
	 *
	 * @param   Event  $event  The onCustomFieldsPrepareField event we are handling
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function onCustomFieldsBeforePrepareField(Event $event): void
	{
		/**
		 * @var   string $context The context
		 * @var   object $item    The item
		 * @var   object $field   The field
		 */
		[$context, $item, $field] = $event->getArguments();

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
	 * Returns the custom fields types.
	 *
	 * @param   Event  $event  The onCustomFieldsGetTypes event we are handling
	 *
	 * @return  void
	 * @since   1.0.1
	 */
	public function onCustomFieldsGetTypes(Event $event): void
	{
		// Cache filesystem access / checks
		static $types_cache = [];

		if (isset($types_cache[$this->_type . $this->_name]))
		{
			$result = $event->getArgument('result', []);
			$event->setArgument(
				'result',
				array_merge(
					$result,
					[
						$types_cache[$this->_type . $this->_name],
					]
				)
			);

			return;
		}

		$this->loadLanguage();

		$types = [];

		// The root of the plugin
		$root = JPATH_PLUGINS . '/' . $this->_type . '/' . $this->_name;

		foreach (Folder::files($root . '/tmpl', '.php') as $layout)
		{
			// Strip the extension
			$layout = str_replace('.php', '', $layout);

			// The data array
			$data = [];

			// The language key
			$key = strtoupper($layout);

			if ($key != strtoupper($this->_name))
			{
				$key = strtoupper($this->_name) . '_' . $layout;
			}

			// Needed attributes
			$data['type'] = $layout;

			if ($this->getApplication()->getLanguage()->hasKey('PLG_FIELDS_' . $key . '_LABEL'))
			{
				$data['label'] = Text::sprintf('PLG_FIELDS_' . $key . '_LABEL', strtolower($key));

				// Fix wrongly set parentheses in RTL languages
				if ($this->getApplication()->getLanguage()->isRtl())
				{
					$data['label'] = $data['label'] . '&#x200E;';
				}
			}
			else
			{
				$data['label'] = $key;
			}

			$path = $root . '/fields';

			// Add the path when it exists
			if (file_exists($path))
			{
				$data['path'] = $path;
			}

			$path = $root . '/rules';

			// Add the path when it exists
			if (file_exists($path))
			{
				$data['rules'] = $path;
			}

			$types[] = $data;
		}

		// Add to cache and return the data
		$types_cache[$this->_type . $this->_name] = $types;

		$result = $event->getArgument('result', []);
		$event->setArgument(
			'result',
			array_merge(
				$result,
				[$types]
			)
		);
	}

	/**
	 * Return the field's XML form definition as a DOMElement which is the child of $parent.
	 *
	 * @param   Event  $event  The onCustomFieldsPrepareDom we are handling
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function onCustomFieldsPrepareDom(Event $event): void
	{
		/**
		 * @var   object     $field  The field
		 * @var   DOMElement $parent The original parent element
		 * @var   Form       $form   The form
		 */
		[$field, $parent, $form] = $event->getArguments();

		// Call the onCustomFieldsPrepareDom method on FieldsPlugin
		$parent_field = $this->getParentFieldIncludingThisField($field, $parent);

		if (!$parent_field)
		{
			$result = $event->getArgument('result', []);
			$event->setArgument(
				'result',
				array_merge(
					$result,
					[$parent_field]
				)
			);

			return;
		}

		$inception = $parent_field->parentNode->tagName === 'form';

		//$parent_field->setAttribute('addfieldpath', __DIR__ . '/src/Field');
		$parent_field->setAttribute('addfieldprefix', 'Joomla\Plugin\Fields\Inception\Field');

		// Override the fieldname attribute of the subform - this is being used to index the rows
		$parent_field->setAttribute('fieldname', $this->getUniqueRowCounterField());

		// If the user configured this subform instance as required
		if ($field->required)
		{
			// Then we need to have at least one row
			$parent_field->setAttribute('min', '1');
		}

		// Get the configured parameters for this field
		$field_params = $this->getParamsFromField($field);

		// This is always a repeatable field; set some attributes on the subform element
		$parent_field->setAttribute('multiple', 'true');
		$parent_field->setAttribute('layout', 'inception.repeatable');

		// Create a child 'form' DOMElement under the field[type=inception] element.
		$parent_fieldset = $parent_field->appendChild(new DOMElement('form'));
		$parent_fieldset->setAttribute('hidden', 'true');
		$parent_fieldset->setAttribute('name', ($field->name . '_modal'));

		$customLayout = $field->fieldparams->get('edit_layout');

		if ($customLayout)
		{
			$context = $field->context ?? '';
			[$component,] = explode('.', $context, 2);
			$parent_field->setAttribute('_x_component', $component);
			$parent_field->setAttribute('layout', $customLayout);
		}

		if ($field_params->get('max_rows'))
		{
			$parent_field->setAttribute('max', $field_params->get('max_rows'));
		}

		// If this field should be repeatable, set some attributes on the modal
		if ($field_params->get('repeat', '1') == '1')
		{
			$parent_fieldset->setAttribute('repeat', 'true');
		}

		// Get the configured subfields for this field
		$subfields = $this->getSubfieldsFromField($field);

		// Iterate over the subfields to call prepareDom on each of those subfields
		foreach ($subfields as $subfield)
		{
			$newEvent = new GenericEvent('onCustomFieldsPrepareDom', [$subfield, $parent_fieldset, $form]);
			$this->getApplication()->getDispatcher()->dispatch(
				$newEvent->getName(),
				$newEvent
			);
		}

		$result = $event->getArgument('result', []);
		$event->setArgument(
			'result',
			array_merge(
				$result,
				[$parent_field]
			)
		);
	}

	/**
	 * Render this field as the combined rendering of all subfields.
	 *
	 * @param   Event  $event  The onCustomFieldsPrepareField event we are handling
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function onCustomFieldsPrepareField(Event $event): void
	{
		/**
		 * @var   string $context The context
		 * @var   object $item    The item
		 * @var   object $field   The field
		 */
		[$context, $item, $field] = $event->getArguments();

		// Check if the field should be processed by us
		if (!$this->isTypeSupported($field->type))
		{
			return;
		}

		// If we don't have any subfields (or values for them), nothing to do.
		if (!is_array($field->value) || count($field->value) < 1)
		{
			return;
		}

		// Get the field params
		$field_params = $this->getParamsFromField($field);

		/**
		 * Placeholder to hold all rows (if this field is repeatable).
		 * Each array entry is another array representing a row, containing all the subfields that
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
			// Holds all subfields of this row, incl. their raw and rendered value
			$row_subfields = [];

			// For each row, iterate over all the subfields
			foreach ($this->getSubfieldsFromField($field) as $subfield)
			{
				// Fill value (and raw value) if we have data for this subfield in the current row, otherwise set them to empty
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
						$newEvent = new GenericEvent(
							'onCustomFieldsPrepareField',
							[$context, $item, $subfield]
						);

						$resultEvent = $this->getApplication()->getDispatcher()
						                    ->dispatch($newEvent->getName(), $newEvent);

						$subfield->value = $resultEvent->getArgument('result', []);

						$this->renderCache[$renderCache_key] = $subfield->value;
					}
				}

				// Flatten the value if it is an array (list, checkboxes, etc.) [independent of render_values]
				if (is_array($subfield->value))
				{
					$subfield->value = implode(' ', $subfield->value);
				}

				// Store the subfield (incl. its raw and rendered value) into this rows subfields
				$row_subfields[$subfield->fieldname] = $subfield;
			}

			// Store all the subfields of this row
			$subform_rows[] = $row_subfields;
		}

		// Store all the rows and their corresponding subfields in $field->subform_rows
		$field->subform_rows = $subform_rows;

		// Call our parent to combine all those together for the final $field->value
		$result = $event->getArgument('result', []);
		$event->setArgument(
			'result',
			array_merge(
				$result,
				[$this->renderFieldAsHTML($field)]
			)
		);
	}

	/**
	 * Returns the path of the XML definition file for the field parameters.
	 *
	 * @param   Form          $form  The form
	 * @param   object|array  $data  The data
	 *
	 * @return  string|null
	 *
	 * @since   1.0.1
	 */
	private function getFormPath(Form $form, $data): ?string
	{
		// Check if the field form is calling us
		if (strpos($form->getName(), 'com_fields.field') !== 0)
		{
			return null;
		}

		// Ensure it is an object
		$formData = (object) $data;

		// Gather the type
		$type = $form->getValue('type');

		if (!empty($formData->type))
		{
			$type = $formData->type;
		}

		// Not us
		if (!$this->isTypeSupported($type))
		{
			return null;
		}

		$path = JPATH_PLUGINS . '/' . $this->_type . '/' . $this->_name . '/src/params/' . $type . '.xml';

		// Check if params file exists
		if (!file_exists($path))
		{
			return null;
		}

		return $path;
	}

	/**
	 * Returns an array of all options configured for this field.
	 *
	 * @param   object  $field  The field
	 *
	 * @return  object[]
	 * @since   1.0.0
	 */
	private function getOptionsFromField(object $field): array
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
	 * @param   object  $field  The field
	 *
	 * @return  Registry
	 * @since   1.0.0
	 */
	private function getParamsFromField(object $field): Registry
	{
		$params = (clone $this->params);

		if (isset($field->fieldparams) && is_object($field->fieldparams))
		{
			$params->merge($field->fieldparams);
		}

		return $params;
	}

	/**
	 * Transforms the field into a DOM XML element and appends it as a child on the given parent.
	 *
	 * @param   object      $field   The field.
	 * @param   DOMElement  $parent  The field node parent.
	 *
	 * @return  DOMElement|null
	 *
	 * @since   1.0.1
	 */
	private function getParentFieldIncludingThisField(object $field, DOMElement $parent): ?DOMElement
	{
		// Check if the field should be processed by us
		if (!$this->isTypeSupported($field->type))
		{
			return null;
		}

		// Detect if the field is configured to be displayed on the form
		if (!FieldsHelper::displayFieldOnForm($field))
		{
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

		if ($layout = $field->params->get('form_layout'))
		{
			$node->setAttribute('layout', $layout);
		}

		if ($field->default_value !== '')
		{
			$defaultNode = $node->appendChild(new DOMElement('default'));
			$defaultNode->appendChild(new \DOMCdataSection($field->default_value));
		}

		// Combine the two params
		$params = clone $this->params;
		$params->merge($field->fieldparams);

		// Set the specific field parameters
		foreach ($params->toArray() as $key => $param)
		{
			if (is_array($param))
			{
				// Multidimensional arrays (eg. list options) can't be transformed properly
				$param = count($param) == count($param, COUNT_RECURSIVE) ? implode(',', $param) : '';
			}

			if ($param === '' || (!is_string($param) && !is_numeric($param)))
			{
				continue;
			}

			$node->setAttribute($key, $param);
		}

		// Check if it is allowed to edit the field
		if (!FieldsHelper::canEditFieldValue($field))
		{
			$node->setAttribute('disabled', 'true');
		}

		// Return the node
		return $node;
	}

	/**
	 * Get a unique field name for a row counter.
	 *
	 * This is required for showon to work, as long as you are also using my ShowOn plugin for forms.
	 *
	 * @return  string
	 * @throws  \Exception
	 * @since   1.0.1
	 */
	private function getUniqueRowCounterField()
	{
		do
		{
			$rowCounter = 'rowCounter_' . md5(random_bytes(16));
		} while (in_array($rowCounter, static::$rowCounters));

		return $rowCounter;
	}

	/**
	 * Returns an array of all subfields for a given field.
	 *
	 * This will always return a bare clone of a subfield, so manipulating it is safe.
	 *
	 * @param   object  $field  The field
	 *
	 * @return  object[]
	 * @since   1.0.0
	 */
	private function getSubfieldsFromField(object $field): array
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
			// Check whether the wanted subfield really is an existing custom field
			if (!isset(static::$customFieldsCache[$option->customfield]))
			{
				continue;
			}

			// Get a clone of the subfield, so we and the caller can do some manipulation with it.
			$cur_field = (clone static::$customFieldsCache[$option->customfield]);

			// Manipulate it and add our custom configuration to it
			$cur_field->render_values = $option->render_values;

			/**
			 * Set the name of the subfield to its id so that the values in the database are being saved
			 * based on the id of the subfields, not on their name. Actually we do not need the name of
			 * the subfields to render them, but just to make sure we have the name when we need it, we
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
	 * Returns true if the given type is supported by the plugin.
	 *
	 * @param   string  $type  The type
	 *
	 * @return  bool
	 *
	 * @since   1.0.1
	 */
	private function isTypeSupported(string $type): bool
	{
		$pseudoEvent = new GenericEvent('onCustomFieldsGetTypes', []);
		$this->onCustomFieldsGetTypes($pseudoEvent);

		foreach ($pseudoEvent->getArgument('result', [null])[0] as $typeSpecification)
		{
			if (!is_array($typeSpecification))
			{
				continue;
			}

			if ($type == $typeSpecification['type'])
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Prepares the field value.
	 *
	 * @param   object  $field  The field.
	 *
	 * @return  string
	 *
	 * @since   1.0.1
	 */
	private function renderFieldAsHTML(object $field): string
	{
		// Check if the field should be processed by us
		if (!$this->isTypeSupported($field->type))
		{
			return '';
		}

		// Merge the params from the plugin and field which has precedence
		$fieldParams = clone $this->params;
		$fieldParams->merge($field->fieldparams);

		// Get the path for the layout file
		$path = PluginHelper::getLayoutPath('fields', $this->_name, $field->type);

		$context = $field->context ?? null;

		// Render the layout
		ob_start();
		include $path;
		$output = ob_get_clean();

		// Return the output
		return $output;
	}
}
