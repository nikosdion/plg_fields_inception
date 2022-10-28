A small version bump, a giant leap in features.

**Now a native Joomla 4 plugin** with forwards compatibility to Joomla 5. The code has been rewritten using the new Joomla 4 API for building extensions.

**Allow ShowOn in inception fields and nested subforms**. You will need to use my [Custom Fields Show–on Behavior plugin](https://github.com/nikosdion/plg_content_fieldsshowon/releases/latest). Yes, you can have subforms and their included fields display conditionally on other form elements. Just keep in mind that you can only reference elements within the **same** subform. You can't show/hide fields based on fields on a parent, adjacent, or child subform (or the main form the Inception field is contained in). 

**Allow custom backend (edit) layout**. Most likely you want to use Inception to build backend interfaces for your clients. For this, you might need to render subforms a little but different, e.g. use a Flexbox layout to display controls in neat rows. Create layout overrides in your _backend_ template and select them in the Inception field's parameters!
