# Maritime Collision

This is a module for the [SmartResolution](http://smartresolution.org) online dispute resolution platform.

It adds a "maritime collision" dispute type, which supports specialised questions and then an approximation of what might happen in court, based on the given answers and maritime law itself.

## @TODO

This is a temporary list, exploring what is required of the underling system for this module to be supported:

* ability to add a custom menu item to a dispute dashboard
    - we may need to add an item when the dispute is in negotiation, fully underway, or in mediation, so the platform should support that level of specificity.
    - we should also be able to define the menu item icon, relative to the maritime_collision directory.
* ability to define custom routes, e.g. /disputes/3/maritime-collision
    - this should again be built into the API, so that things like extracting the dispute ID from the URL is built-in.
* ability to define the HTML rendered on the custom route.
* some persistence will be required, e.g. agent a answers maritime collision question - it needs to be stored somewhere so that it can be processed later along with the wider picture.
* all of the above should be handled by the underlying platform's API as much as possible, e.g. the module itself should not have a handle on the database instance object.

## Acknowledgements

The anchor icon is in the public domain and was taken from pixabay.org.
