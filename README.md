# This is obsolete as of Joomla! 5.2

Joomla! 5.2 and later allow nested subform fields, rendering this plugin obsolete 🎉

(And yes, this is a good thing! The ultimate goal of “clever hacks” plugins like this is to ultimately become obsolete thanks to a core Joomla! feature.)

# <img src="./logo/spinning-top-2.svg" width="32" alt="Spinning top logo by Skoll at game-icons.net"> Inception! Form Field

A custom field plugin for nested subforms in Joomla! 4 

Copyright (C) 2022-2023  Nicholas K. Dionysopoulos

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.

## What is this?

This is a custom field plugin which allows you to create sub–forms (a repeatable list consisting of other custom fields), just like Joomla's `subform` custom field type — but with a twist!

Joomla's `subform` field does not allow you to use a `subform` field as another sub–form's field. This plugin allows you to do exactly that: nest the subforms, any level deep. However, just like the movie “Inception”, the more nesting levels you add the more complicated and slow things become.

Also, be careful! You can create an inception field A which contains an inception field B which contains an inception field A — this will **break** both the edit page and the display of whatever has this kind of infinitely nested structure. There is no protection against doing something like that — which is why Joomla does not allow nested subforms to begin with… Unlike Joomla, I think that you are a responsible adult and can be trusted not to do something stupid. _Right?_

## Use case

Nested subforms come in very handy if you are trying to use the Joomla core as a content construction kit (CCK) or non–visual page builder.

Using nested subforms and template overrides you can create complex pages which are easily manageable by your end users. It's a bit more effort than a page builder but, unlike using a page builder, you have _semantic_ input of the content components.

Here's an example: a food blog (the original use case I presented in the Joomla 4 Round Table discussion in 2015 which led to the decision to add custom fields to Joomla).

A food blog entry consists of the following content pieces:
* A title
* An intro image
* Free–form text describing the dish presented, its history, its cultural significance, maybe a story explaining how the author came to prepare it and/or love it.
* An image carousel
* The actual recipe. Each recipe consists of one or more preparations. Each preparation has the following:
  * Ingredients. Each ingredient consists of
    * Quantity.
    * Unit of measurement.
    * Name of the ingredient.
  * Preparation steps.

If we were to map this into a Joomla article, here's how we'd do it.
* Title (part of the standard article)
* Intro image (part of the standard article)
* Free-form text (intro and full text, part of the standard article)
* Carousel (subform field)
  * Image (image field)
* Preparations (**inception** field)
  * Preparation name, e.g. sauce, base etc (text field)
  * Ingredients (**inception** field)
    * Quantity (number field)
    * Unit of measurement (list field, choosing one of tsp, tbsp, oz, lb, mL, L, g, Kg and so on)
  * Preparation steps (**inception** field)
    * Step Text (editor field)

Now it's just a matter of creating template overrides for the article list and single article of this category and... we have a food blog!

With a bit of love from [d2 Content](https://extensions.joomla.org/extension/d2-content/) we can even have a rockstar backend experience for our aspiring food blogger.

Joomla can do everything. All it takes is a bit of creativity on your part.

Enjoy!
